<?php namespace Hampel\LogDigest\XF\Admin\Controller;

use Hampel\LogDigest\Option\TimeZone;
use Hampel\LogDigest\Repository\DigestCache;
use Hampel\LogDigest\SubContainer\LogDigest;

class Tools extends XFCP_Tools
{
	public function actionTestLogDigest()
	{
		$this->setSectionContext('testLogDigest');
        $this->assertAdminPermission('option');

		$messages = [];
		$results = false;
		$test = '';
		$options = [
			'email' => \XF::visitor()->email,
			'generate' => false,
			'limit' => 10
		];

		if ($this->isPost())
		{
			$test = $this->filter('test', 'str');
			$options = $this->filter('options', 'array');

			/** @var AbstractTest $tester */
			$tester = $this->app->container()->create('logdigest.test', $test, [$this, $options]);
			if ($tester)
			{
				$results = $tester->runTest();
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
        $this->assertAdminPermission('option');

		/** @var LogDigest $digest */
		$digest = \XF::app()->get('logDigest');

		$messages = [];
		$options = [];

		if ($this->isPost())
		{
			$types = $digest->getAllLogsForReset();

			$options = $this->filter('options', 'array');

			foreach ($options as $type => $reset)
			{
				if ($reset)
				{
					$digest->reset($type);
					$messages[] = ['type' => 'success', 'message' => \XF::phrase('logdigest_successfully_reset', ['log' => $types[$type]['name']])];
				}
			}
		}

		$types = $digest->getAllLogsForReset();

		$viewParams = compact('messages', 'options', 'types');
		return $this->view('XF:Tools\ResetLogDigest', 'logdigest_tools_reset_logdigest', $viewParams);
	}
}