<?php namespace LogDigest\XF\Admin\Controller;

use LogDigest\Option\TimeZone;
use LogDigest\Cache\DigestCache;

class Tools extends XFCP_Tools
{
	public function actionTestLogDigest()
	{
		$this->setSectionContext('testLogDigest');

		$messages = [];
		$results = false;
		$test = '';
		$options = [
			'email' => \XF::visitor()->email,
			'generate' => true,
		];

		if ($this->isPost())
		{
			$test = $this->filter('test', 'str');
			$options = $this->filter('options', 'array');

			/** @var AbstractTest $tester */
			$tester = $this->app->container()->create('logdigest.test', $test, [$this, $options]);
			if ($tester)
			{
				$results = $tester->run();
				$messages = $tester->getMessages();
			}
			else
			{
				return $this->error(\XF::phrase('logdigest_this_test_could_not_be_run'), 500);
			}
		}

		$viewParams = compact('results', 'messages', 'test', 'options');
		return $this->view('XF:Tools\TestLogDigest', 'logdigest_tools_test_logdigest', $viewParams);
	}

	public function actionResetLogDigest()
	{
		$this->setSectionContext('resetLogDigest');

		$messages = [];

		if ($this->isPost())
		{
			$options = $this->filter('options', 'array');

			foreach ($options as $type => $reset)
			{
				if ($reset)
				{
					DigestCache::reset($type);
					$messages[] = ['type' => 'success', 'message' => \XF::phrase('logdigest_successfully_reset', ['log' => $type])];
				}
			}
		}

		// TODO: will need to change this when we add support for multiple log types

		$lastChecked = DigestCache::getLastChecked('server-error');
		if ($lastChecked > 0)
		{
			$tz = new \DateTimeZone(TimeZone::get());
			$logDate = new \DateTime();
			$logDate->setTimestamp(intval($lastChecked));
			$logDate->setTimezone($tz);
			$lastChecked = $logDate->format('r');
		}

		$types = [
			'server-error' => [
				'name' => 'Server error',
				'route' => 'logs/server-errors',
				'lastchecked' => $lastChecked,
				'lastid' => DigestCache::getLastId('server-error'),
			]
		];

		$viewParams = compact('messages', 'options', 'types');
		return $this->view('XF:Tools\ResetLogDigest', 'logdigest_tools_reset_logdigest', $viewParams);
	}
}