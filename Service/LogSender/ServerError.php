<?php namespace Hampel\LogDigest\Service\LogSender;

use XF\Mvc\Entity\ArrayCollection;
use XF\Service\AbstractService;

class ServerError extends AbstractService
{
	protected $logs;

	protected $tz;

	protected $lastid;

	public function __construct(\XF\App $app, ArrayCollection $logs, $timezone)
	{
		parent::__construct($app);

		$this->logs = $logs;
		$this->tz = new \DateTimeZone($timezone);
	}

	public function filterLogData($deduplicate, $limit)
	{
		$logsToSend = [];
		$duplicateCount = 0;
		$logCount = 0;
		$extra = [];

		$ignored = ['error_id', 'exception_date', 'user_id', 'ip_address', 'request_state'];

		foreach ($this->logs as $log)
		{
			$id = $log['error_id'];

			$this->lastid = $id;

			$logDate = new \DateTime();
			$logDate->setTimestamp(intval($log['exception_date']));
			$logDate->setTimezone($this->tz);

			$extra[$id]['date'] = $logDate->format('r');

			$extra[$id]['duplicate'] = false;

			if (
				$deduplicate
				&& !empty($logsToSend)
				&& $this->isDuplicate($log, $logsToSend, $ignored)
			)
			{
				$duplicateCount++;
				$extra[$id]['duplicate'] = true;
				$logsToSend[] = $log;
				continue; // skip to next entry
			}

			$logsToSend[] = $log;
			$logCount++;

			if (isset($limit) && $logCount >= $limit) break; // stop once we get to our limit
		}

		if ($logCount == 0) return; // stop if we didn't actually get any logs to send

		return [
			'type' => 'Server error',
			'route' => 'logs/server-errors',
			'logs' => $logsToSend,
			'extra' => $extra,
			'duplicateCount' => $duplicateCount,
		];
	}

	private function isDuplicate($thisLog, $existingLogs, $ignore)
	{
		foreach ($existingLogs as $previousLog)
		{
			if (get_class($thisLog) != get_class($previousLog)) continue; // this shouldn't happen!

			$thisLogData = $thisLog->toArray();
			$previousLogData = $previousLog->toArray();

			// remove ignored columns
			foreach ($ignore as $column)
			{
				unset($thisLogData[$column]);
				unset($previousLogData[$column]);
			}

			if ($thisLogData === $previousLogData) return true;
		}

		return false;
	}


	public function send($email, $template, array $params)
	{
		// only send if we've filtered the logs
		if ($this->lastid)
		{
			return $this->app->mailer()
			                 ->newMail()
			                 ->setTo($email)
			                 ->setTemplate("logdigest_{$template}", $params)
			                 ->send();
		}
	}

	public function getLastId()
	{
		return $this->lastid;
	}
}
