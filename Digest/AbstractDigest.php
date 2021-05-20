<?php namespace Hampel\LogDigest\Digest;

use Hampel\LogDigest\Repository\DigestCache;
use XF\Mvc\Entity\ArrayCollection;

abstract class AbstractDigest
{
	/** @var \XF\App */
	protected $app;

	/** @var \DateTimeZone */
	protected $tz;

	/**
	 * AbstractDigest constructor.
	 *
	 * @param \XF\App $app
	 * @param string $tz timezone string compatible with DateTimeZone
	 */
	public function __construct(\XF\App $app, $tz)
	{
		$this->app = $app;
		$this->tz = new \DateTimeZone($tz);
	}

	/**
	 * @return string option id for retrieving admin options
	 */
	abstract protected function getOptionId();

	/**
	 * @return \XF\Phrase|string log name for emails
	 */
	abstract public function getLogName();

	/**
	 * @return string entity class identifier
	 */
	abstract protected function getEntityId();

	/**
	 * @return string column name for log timestamp
	 */
	abstract protected function getTimestampColumn();

	/**
	 * @return string email template name
	 */
	abstract protected function getTemplate();

	/**
	 * @return array list of column names to use for comparison purposes
	 */
	abstract protected function getComparisonFields();

	/**
	 * @return string route name for generating links to logs
	 */
	abstract protected function getRoute();

	/**
	 * @return bool is this log type enabled in options?
	 */
	public function isEnabled()
	{
		$options = $this->getOptions();
		return boolval($options['enabled']);
	}

	/**
	 * @return void|\XF\Mvc\Entity\ArrayCollection
	 */
	public function getLogs()
	{
		$lastChecked = $this->getLastChecked();
		$frequency = $this->frequency();

		// if it's been less than $frequency seconds since we last checked, then just skip and wait for the next
		// check cycle
		if ($lastChecked > 0 && ($lastChecked + $frequency > \XF::$time)) return;

		$logs = $this->fetchLogs($lastChecked);

		if ($logs->count() == 0)
		{
			// update the last checked time so we don't keep retrying
			$this->updateLastChecked(\XF::$time);
		}

		return $logs;
	}

	protected function fetchLogs($timestamp)
	{
		$column = $this->getTimestampColumn();

		return $this->app->finder($this->getEntityId())
		                 ->where($column, '>', $timestamp)
		                 ->order($column, 'ASC')
		                 ->fetch();
	}

	/**
	 * @param ArrayCollection $logs collection of logs to filter
	 * @param bool $update flag to indicate whether to update the last sent time (set to false when testing)
	 *
	 * @return ArrayCollection
	 */
	public function prepareLogs($logs, $update = true)
	{
		$filteredLogs = [];
		$previousLogs = [];
		$logCount = 0;
		$lastTimestamp = 0;

		$deduplicate = $this->deduplicate();
		$limit = $this->limit();

		foreach ($logs as $log)
		{
			$thisLog = $log->toArray();

			$timestamp = $thisLog[$this->getTimestampColumn()];
			$thisLog['dateFormatted'] = $this->formatDate($timestamp);

			$thisLog['duplicate'] = false;

			if ($deduplicate)
			{
				$logDataForComparison = [];
				foreach ($this->getComparisonFields() as $field)
				{
					$logDataForComparison[$field] = $thisLog[$field] ?? null;
				}

				if (!empty($previousLogs))
				{
					foreach ($previousLogs as $previousLog)
					{
						if ($logDataForComparison === $previousLog)
						{
							$thisLog['duplicate'] = true;
							continue;
						}
					}
				}

				$previousLogs[] = $logDataForComparison;
			}

			// only count non-duplicate logs
			if (!$thisLog['duplicate'])
			{
				$logCount++;
			}

			$filteredLogs[] = $thisLog;

			$lastTimestamp = $timestamp;

			// stop if we've reached our limit
			if ($limit > 0 && $logCount >= $limit)
			{
				break;
			}
		}

		// if we found some logs - cache the time of the last log we're sending so we know where to start in future
		if ($update && $lastTimestamp > 0)
		{
			$this->updateLastChecked($timestamp);
		}

		return $filteredLogs;
	}

	public function formatDate($timestamp)
	{
		$logDate = new \DateTime();
		$logDate->setTimestamp($timestamp);
		$logDate->setTimezone($this->tz);
		return $logDate->format('r');
	}

	/**
	 * @param array $logs array of prepared logs
	 * @param string $email email address to send to
	 *
	 * @return int
	 */
	public function send(array $logs, $email)
	{
		if (!empty($logs))
		{
			$params = $this->buildParameters($logs);

			return $this->app->mailer()
			                 ->newMail()
			                 ->setTo($email)
			                 ->setTemplate($this->getTemplate(), $params)
			                 ->send();
		}
	}

	protected function buildParameters(array $logs)
	{
		return [
			'type' => $this->getLogName(),
			'route' => $this->getRoute(),
			'logs' => $logs,
			'duplicateCount' => $this->countDuplicates($logs),
		];
	}

	protected function isDuplicate(array $thisLog, array $existingLogs)
	{
		foreach ($existingLogs as $previousLog)
		{
			if ($thisLog === $previousLog) return true;
		}

		return false;
	}

	protected function countDuplicates($logs)
	{
		$count = 0;

		foreach ($logs as $log)
		{
			if (isset($log['duplicate']) && $log['duplicate'] === true)
			{
				$count++;
			}
		}

		return $count;
	}

	protected function getOptions()
	{
		$options = \XF::options();
		return $options[$this->getOptionId()];
	}

	protected function frequency()
	{
		$options = $this->getOptions();
		return intval($options['frequency']);
	}

	protected function frequencySeconds()
	{
		return $this->frequency() * 60;
	}

	protected function limit()
	{
		$options = $this->getOptions();
		return intval($options['limit']);
	}

	protected function deduplicate()
	{
		$options = $this->getOptions();
		return boolval($options['deduplicate']);
	}

	public function getLastChecked()
	{
		return $this->getCacheRepo()->getLastChecked($this->getEntityId());
	}

	/**
	 * @param $timestamp int timestamp of last sent log (or time we last checked for new logs)
	 */
	protected function updateLastChecked($timestamp)
	{
		if (!$timestamp) return;

		$this->getCacheRepo()->setLastChecked($this->getEntityId(), $timestamp);
	}

	public function resetLastChecked()
	{
		$this->getCacheRepo()->reset($this->getEntityId());
	}

	/**
	 * @return DigestCache
	 */
	protected function getCacheRepo()
	{
		return $this->app->repository('Hampel\LogDigest:DigestCache');
	}
}
