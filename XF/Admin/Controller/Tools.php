<?php namespace LogDigest\XF\Admin\Controller;

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
}