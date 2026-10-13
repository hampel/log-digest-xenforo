<?php namespace Hampel\LogDigest\Service;

use Hampel\LogDigest\Digest\AbstractDigest;
use Hampel\LogDigest\Option\Email;
use Hampel\LogDigest\Option\TimeZone;
use Hampel\LogDigest\SubContainer\LogDigest;
use XF\Service\AbstractService;

/**
 * Whether this forum can send the digests it is configured to send. Each check answers with a
 * status and a line of detail; a check that throws becomes a failed row and a server error log
 * entry, and the rest still report.
 *
 * run() reads and never writes or sends. sendTestDigest() is the one method that sends.
 */
class HealthCheck extends AbstractService
{
	const OK = 'ok';
	const WARNING = 'warn';
	const FAIL = 'fail';
	const SKIPPED = 'skip';

	/** What every server error log row this add-on writes begins with. */
	const ERROR_LOG_PREFIX = 'LogDigest: ';

	const CRON_ENTRY = 'logdigestSendLogs';

	/** Seconds a digest may run late before it counts as stuck: three runs of the cron. */
	const GRACE = 900;

	/**
	 * @return array[] one row per check: check, label, status, detail
	 */
	public function run()
	{
		$results = [];

		$results[] = $this->check('recipient', 'recipient', function ()
		{
			return $this->recipient();
		});
		$results[] = $this->check('time_zone', 'time zone', function ()
		{
			return $this->timeZone();
		});
		$results[] = $this->check('cron', 'cron entry', function ()
		{
			return $this->cron();
		});
		$results[] = $this->check('log_types', 'log types', function ()
		{
			return $this->logTypes();
		});

		foreach ($this->logDigest()->digestList() AS $key => $class)
		{
			$results[] = $this->check("window_{$key}", str_replace('_', ' ', $key), function () use ($class)
			{
				return $this->window($this->logDigest()->digest($class));
			});
		}

		return $results;
	}

	/**
	 * Sends a test digest of one log type to the configured recipient, without moving that type's
	 * last checked time. A failure XenForo logged while sending is a failure here.
	 *
	 * @param string $key a key of the digest list, eg server_error_log
	 *
	 * @return array check, label, status, detail
	 */
	public function sendTestDigest($key)
	{
		return $this->check("send_{$key}", str_replace('_', ' ', $key), function () use ($key)
		{
			$list = $this->logDigest()->digestList();
			$digest = $this->logDigest()->digest($list[$key]);

			if (!$digest->isEnabled())
			{
				return [self::SKIPPED, 'not enabled'];
			}

			$logs = $digest->fetchLogs(0);
			if (!$logs->count())
			{
				return [self::SKIPPED, 'no entries in this log to send, so delivery was not tested'];
			}

			$db = $this->db();
			$errorsBefore = (int) $db->fetchOne('SELECT MAX(error_id) FROM xf_error_log');

			$prepared = $digest->prepareLogs($logs, 1);
			$sent = $digest->send($prepared, Email::get(), true);

			$error = $db->fetchOne(
				'SELECT message FROM xf_error_log WHERE error_id > ? ORDER BY error_id LIMIT 1',
				$errorsBefore
			);
			if (!$sent || $error)
			{
				return [self::FAIL, 'could not be sent' . ($error ? ': ' . strtok($error, "\n") : '')];
			}

			return [self::OK, 'test digest sent to ' . Email::get()];
		});
	}

	/**
	 * @return string the most serious status in a set of rows
	 */
	public function worstStatus(array $results)
	{
		$statuses = array_column($results, 'status');

		foreach ([self::FAIL, self::WARNING, self::OK] AS $status)
		{
			if (in_array($status, $statuses, true))
			{
				return $status;
			}
		}

		return self::SKIPPED;
	}

	protected function recipient()
	{
		$email = (string) Email::get();
		$source = Email::isContactAddress() ? ' (the board contact address)' : '';

		if ($email === '')
		{
			return [self::FAIL, 'no recipient is set, and the board has no contact address'];
		}

		if (!$this->app->validator('Email')->isValid($email))
		{
			return [self::FAIL, "{$email}{$source} is not a valid email address"];
		}

		return [self::OK, $email . $source];
	}

	protected function timeZone()
	{
		$timeZone = (string) TimeZone::get();

		if ($timeZone === '' || !in_array($timeZone, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true))
		{
			return [self::FAIL, "'{$timeZone}' is not a time zone, so no digest can be built"];
		}

		return [self::OK, $timeZone];
	}

	protected function cron()
	{
		$entry = $this->em()->find('XF:CronEntry', self::CRON_ENTRY);

		if (!$entry)
		{
			return [self::FAIL, 'the cron entry is missing - rebuild the add-on'];
		}

		if (!$entry->active)
		{
			return [self::FAIL, 'the cron entry is disabled, so no digest will be sent'];
		}

		if ($entry->next_run < \XF::$time - self::GRACE)
		{
			return [self::WARNING, 'was due at ' . $this->date($entry->next_run) . ' and has not run - is the job queue running?'];
		}

		return [self::OK, 'active, next run ' . $this->date($entry->next_run)];
	}

	protected function logTypes()
	{
		$enabled = [];
		foreach ($this->logDigest()->digestList() AS $key => $class)
		{
			if ($this->logDigest()->digest($class)->isEnabled())
			{
				$enabled[] = str_replace('_', ' ', $key);
			}
		}

		if (!$enabled)
		{
			return [self::WARNING, 'no log type is enabled, so nothing will be sent'];
		}

		return [self::OK, implode(', ', $enabled)];
	}

	/**
	 * Whether a log type's digests are going out. Entries that have been waiting since well after
	 * the last digest, for longer than the type's frequency allows, mean sends are failing.
	 */
	protected function window(AbstractDigest $digest)
	{
		if (!$digest->isEnabled())
		{
			return [self::SKIPPED, 'not enabled'];
		}

		$lastChecked = (int) $digest->getLastChecked();
		$waiting = $digest->countWaiting($lastChecked, \XF::$time - self::GRACE);

		if (!$lastChecked)
		{
			return $waiting
				? [self::WARNING, "never sent, or reset - {$waiting} waiting for the next cron run"]
				: [self::OK, 'nothing to send yet'];
		}

		$due = $lastChecked + $digest->getFrequencySeconds();
		if ($waiting && $due < \XF::$time - self::GRACE)
		{
			return [self::FAIL, "{$waiting} waiting since the last digest at " . $this->date($lastChecked)
				. ' - digests are not going out'];
		}

		return [self::OK, 'last digest ' . $this->date($lastChecked) . ($waiting ? ", {$waiting} waiting" : '')];
	}

	/**
	 * Runs one check. Anything thrown is a failed row and a server error log entry.
	 */
	protected function check($check, $label, callable $probe)
	{
		try
		{
			list($status, $detail) = $probe();
		}
		catch (\Throwable $e)
		{
			\XF::logException($e, false, self::ERROR_LOG_PREFIX . "health check - {$label}: ");

			$status = self::FAIL;
			$detail = get_class($e) . ': ' . strtok($e->getMessage(), "\n");
		}

		return ['check' => $check, 'label' => $label, 'status' => $status, 'detail' => $detail];
	}

	protected function date($timestamp)
	{
		return gmdate('Y-m-d H:i', $timestamp) . ' UTC';
	}

	/**
	 * @return LogDigest
	 */
	protected function logDigest()
	{
		return $this->app->get('logDigest');
	}
}
