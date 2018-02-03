<?php namespace LogDigest\XF\Cron;

use XF\App;
use LogDigest\Option\Email;
use LogDigest\Option\Limit;
use LogDigest\Option\TimeZone;
use LogDigest\Option\Frequency;
use LogDigest\Cache\DigestCache;
use LogDigest\Option\Deduplicate;

class SendLogs
{
	public static function send()
	{
		/** @var App $app */
		$app = \XF::app();
		$mailer = $app->mailer();
		$email = Email::get();
		$frequency = Frequency::getSeconds();
		$limit = Limit::get();
		$deduplicate = Deduplicate::get();
		$tz = new \DateTimeZone(TimeZone::get());

		$logTypes = [
			'server-error' => [
				'name' => 'Server error',
				'route' => 'logs/server-errors',
				'entity' => 'ErrorLog',
				'id' => 'error_id',
				'date' => 'exception_date',
				'template' => 'server_error',
				'ignored' => ['error_id', 'exception_date', 'user_id', 'ip_address', 'request_state'],
			]
		];

		foreach ($logTypes as $type => $details)
		{
			$lastChecked = DigestCache::getLastChecked($type);

			// if it's been less than $frequency seconds since we last checked, then just skip and wait for the next
			// check cycle
			if ($lastChecked > 0 && ($lastChecked + $frequency > $app['time'])) continue;

			$lastid = DigestCache::getLastId($type);

			$logs = \XF::finder("XF:{$details['entity']}")
			           ->where($details['id'], '>', $lastid)
			           ->order($details['id'], 'ASC')
			           ->fetch();

			if (empty($logs))
			{
				// update the last checked time so we don't keep retrying
				DigestCache::setLastChecked($type, $app['time']);
				continue; // skip to the next type
			}

			$logsToSend = [];
			$duplicateCount = 0;
			$logCount = 0;
			$extra = [];

			foreach ($logs as $log)
			{
				$id = $log[$details['id']];
				$lastid = $id;

				$logDate = new \DateTime();
				$logDate->setTimestamp(intval($log[$details['date']]));
				$logDate->setTimezone($tz);
				$extra[$id]['date'] = $logDate->format('r');

				$extra[$id]['duplicate'] = false;

				if (
					$deduplicate
					&& !empty($logsToSend)
					&& self::isDuplicate($log, $logsToSend, $details['ignored'])
				)
				{
					$duplicateCount++;
					$extra[$id]['duplicate'] = true;
					$logsToSend[] = $log;
					continue; // skip to next entry
				}

				$logsToSend[] = $log;
				$logCount++;

				if ($logCount >= $limit) break; // stop once we get to our limit
			}

			if ($logCount == 0) continue; // skip if we didn't actually get any logs to send

			$params = [
				'type' => $details['name'],
				'route' => 'logs/server-errors',
				'logs' => $logsToSend,
				'extra' => $extra,
				'duplicateCount' => $duplicateCount,
			];

			$sent = $mailer->newMail()
			               ->setTo($email)
			               ->setTemplate("logdigest_{$details['template']}", $params)
			               ->send();

			// if sending the logs failed (possibly due a temporary error?) the we'll want to try again
			if ($sent) DigestCache::setValue($type, ['checked' => $app['time'], 'id' => $lastid]);
		}
	}

	private static function isDuplicate($thisLog, $existingLogs, $ignore)
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
}
