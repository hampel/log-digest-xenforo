<?php namespace Hampel\LogDigest\Test;

use Hampel\LogDigest\SubContainer\LogDigest;

class DigestTest extends AbstractTest
{
	public function run()
	{
		$email = $this->data['email'];
		$validator = $this->app->validator('Email');
		if (!$validator->isValid($email, $error))
		{
			$this->errorMessage(\XF::phrase('logdigest_invalid_email_address'));
			return false;
		}

		$generate = $this->getCheckbox('generate');

		if ($generate)
		{
			// create a test exception so we have log data to return
			\XF::logException(new \Exception("This is a test exception generated by the LogDigest addon"));
		}

		$logDigest = $this->getLogDigest();

		$digest = $logDigest->digest('Hampel\LogDigest:ServerError');
		$logs = $digest->getLogs();

		$sent = false;

		if ($logs)
		{
			$count = $logs->count();

			if ($count > 0)
			{
				$filteredLogs = $digest->prepareLogs($logs, false);
				$sent = $digest->send($filteredLogs, $email);
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
			$this->successMessage(\XF::phrase('logdigest_test_successfully_sent', ['count' => $count, 'email' => $email]));
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
