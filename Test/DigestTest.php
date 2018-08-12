<?php namespace Hampel\LogDigest\Test;

use Hampel\LogDigest\Option\TimeZone;
use XF\Mvc\Entity\FinderCollection;

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

		/** @var FinderCollection $logs */
		$logs = \XF::finder('XF:ErrorLog')
		           ->order('exception_date', 'DESC')
		           ->limit(5)
		           ->fetch();

		$count = $logs->count();
		$tz = new \DateTimeZone(TimeZone::get());

		$extra = [];
		foreach ($logs as $log)
		{
			$id = $log['error_id'];
			$logDate = new \DateTime();
			$logDate->setTimestamp(intval($log['exception_date']));
			$logDate->setTimezone($tz);
			$extra[$id]['date'] = $logDate->format('r');
			$extra[$id]['duplicate'] = false; // not used in test
		}

		$params = [
			'test' => true,
			'type' => "Server error",
			'route' => 'logs/server-errors',
			'logs' => $logs,
			'extra' => $extra,
			'duplicateCount' => 0, // not used in test
		];

		$result = $this->app->mailer()->newMail()
			->setTo($email)
			->setTemplate('logdigest_server_error', $params)
			->send();

		if ($result)
		{
			$this->successMessage(\XF::phrase('logdigest_test_successfully_sent', ['count' => $count, 'email' => $email]));
			return true;
		}
		else
		{
			$this->errorMessage(\XF::phrase('logdigest_test_returned_false'));
			return false;
		}
	}
}
