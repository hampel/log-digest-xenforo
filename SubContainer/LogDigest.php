<?php namespace Hampel\LogDigest\SubContainer;

use Hampel\LogDigest\Digest\AbstractDigest;
use Hampel\LogDigest\Helper\Log;
use Hampel\LogDigest\Option\Email;
use Hampel\LogDigest\Option\TimeZone;
use XF\Container;
use XF\SubContainer\AbstractSubContainer;

class LogDigest extends AbstractSubContainer
{
	public function initialize()
	{
		$app = $this->app;
		$container = $this->container;

		$container->factory('digest', function($class, array $params, Container $c) use ($app)
		{
			$class = \XF::stringToClass($class, '\%s\Digest\%s');
			$class = $app->extendClass($class);

			array_unshift($params, $app);

			return $c->createObject($class, $params, true);
		});

		$container['digest.list'] = function(Container $c)
		{
			$digestList = [
				'server_error' => 'Hampel\LogDigest:ServerError',
			];

			// TODO: admin log
			// TODO: moderator log
			// TODO: email bounce log?
			// TODO: payment provider log?

			return $digestList;
		};
	}

	/**
	 * @param string $class
	 *
	 * @return AbstractDigest
	 */
	public function digest($class)
	{
		return $this->container()->create('digest', $class, [TimeZone::get()]);
	}

	public function digestList()
	{
		return $this->container['digest.list'];
	}

	/**
	 * @param string $class class name for log digest
	 * @param string $email email address to send to
	 */
	public function send($class, $email)
	{
		$digest = $this->digest($class);

		// stop if this log type is not enabled
		if (!$digest->isEnabled())
		{
			Log::info("Skipping sending disabled log", ['class' => $class]);

			return;
		}

		$logs = $digest->getLogs();

		if ($logs)
		{
			$count = $logs->count();

			if ($count > 0)
			{
				$filteredLogs = $digest->prepareLogs($logs);
				$digest->send($filteredLogs, $email);

				Log::info("Sent logs", ['class' => $class, 'email' => $email, 'count' => $count]);

				$digest->updateLastChecked(\XF::$time);
			}
		}
	}

	/**
	 * Send digest emails for all log types
	 */
	public function sendAll()
	{
		$logs = $this->digestList();
		$email = Email::get();

		foreach ($logs as $key => $class)
		{
			$this->send($class, $email);
		}
	}

	public function getAllLogsForReset()
	{
		$digestList = $this->digestList();
		$logTypes = [];

		foreach ($digestList as $key => $class)
		{
			$digest = $this->digest($class);

			$lastChecked = $digest->getLastChecked();

			$logTypes[$key] = [
				'name' => $digest->getLogName(),
				'lastChecked' => $lastChecked,
				'lastCheckedFormatted' => $lastChecked > 0 ? $digest->formatDate($digest->getLastChecked()) : ""
			];
		}

		return $logTypes;
	}

	public function reset($type)
	{
		$digestList = $this->digestList();
		$class = $digestList[$type];

		$this->digest($class)->resetLastChecked();

		Log::info("Reset last checked", ['type' => $type, 'class' => $class]);
	}
}
