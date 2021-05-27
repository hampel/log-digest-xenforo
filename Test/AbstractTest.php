<?php namespace Hampel\LogDigest\Test;

use Hampel\LogDigest\SubContainer\LogDigest;
use XF\App;
use XF\Admin\Controller\AbstractController;

abstract class AbstractTest
{
	protected $app;
	protected $controller;
	protected $data;
	protected $defaultData = [];
	protected $messages = [];

	abstract protected function run();

	public function __construct(App $app, AbstractController $controller, array $data = [])
	{
		$this->app = $app;
		$this->controller = $controller;
		$this->data = $this->setupData($data);
	}

	protected function setupData(array $data)
	{
		return array_merge($this->defaultData, $data);
	}

	public function runTest()
	{
		$email = $this->data['email'];
		if (!$this->validateEmail($email))
		{
			return false;
		}

		return $this->run();
	}

	public function getData()
	{
		return $this->data;
	}

	public function getMessages()
	{
		return $this->messages;
	}

	public function getErrorMessages()
	{
		return array_filter($this->messages, function($value) {
			return isset($value['type']) && ($value['type'] == 'error');
		});
	}

	public function getSuccessMessages()
	{
		return array_filter($this->messages, function($value) {
			return isset($value['type']) && ($value['type'] == 'success');
		});
	}

	protected function getCheckbox($name)
	{
		return isset($this->data[$name]) && $this->data[$name] == "1";
	}

	protected function message($type = 'none', $message)
	{
		$this->messages[] = compact('type', 'message');
	}

	protected function errorMessage($message)
	{
		$this->message('error', $message);
	}

	protected function successMessage($message)
	{
		$this->message('success', $message);
	}

	public function validateEmail($email)
	{
		$validator = $this->app->validator('Email');
		if (!$validator->isValid($email, $error))
		{
			$this->errorMessage(\XF::phrase('logdigest_invalid_email_address'));
			return false;
		}

		return true;
	}

	protected function send($class)
	{
		$logDigest = $this->getLogDigest();

		$digest = $logDigest->digest($class);
		$logs = $digest->fetchLogs(0);

		$email = $this->data['email'];

		$sent = false;
		$count = 0;

		if ($logs)
		{
			$count = $logs->count();

			if ($count > 0)
			{
				$filteredLogs = $digest->prepareLogs($logs, $this->data['limit']);
				$duplicates = $digest->countDuplicates($filteredLogs);
				$sentCount = count($filteredLogs) - $duplicates;

				$sent = $digest->send($filteredLogs, $email, true);
			}
		}

		if ($count == 0)
		{
			$this->errorMessage(\XF::phrase('logdigest_test_no_logs_found'));
			return false;
		}
		elseif (!$sent)
		{
			$this->errorMessage(\XF::phrase('logdigest_test_returned_false'));
			return false;
		}
		else
		{
			$this->successMessage(\XF::phrase('logdigest_test_successfully_sent', ['count' => $sentCount, 'email' => $email]));
			return true;
		}
	}

	/**
	 * @return LogDigest
	 */
	protected function getLogDigest()
	{
		return $this->app->get('logDigest');
	}
}