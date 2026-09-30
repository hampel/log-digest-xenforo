<?php namespace Tests\Unit;

use Hampel\LogDigest\Cron\SendLogs;
use Hampel\LogDigest\SubContainer\LogDigest;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;
use XF\Mvc\Entity\ArrayCollection;

class SendDigestTest extends TestCase
{
	const DIGEST = 'Hampel\LogDigest:ServerErrorLog';
	const EMAIL = 'admin@example.com';

	protected function setUp() : void
	{
		parent::setUp();

		$this->fakesSimpleCache();

		$this->setOptions([
			'logdigestServerError' => ['enabled' => true, 'frequency' => 5, 'limit' => 10, 'deduplicate' => true],
			'logdigestTimeZone' => 'UTC',
		]);
	}

	public function test_send_emails_the_logs_and_advances_last_checked()
	{
		$this->fakesMail();
		$this->finderReturns([
			$this->errorLog(2, 'Something broke'),
			$this->errorLog(1, 'Something broke'),
		]);

		$this->logDigest()->send(self::DIGEST, self::EMAIL);

		$this->assertMailSent(function ($mail) {
			$html = $mail->getHtmlBody();

			return $mail->getTo()[0]->getAddress() == self::EMAIL
				&& strpos($mail->getSubject(), 'Server error log digest') !== false
				&& strpos($mail->getSubject(), '[Test]') === false
				&& strpos($html, 'Something broke') !== false
				&& strpos($html, 'Duplicate entry') !== false
				&& strpos($html, 'logdigest_') === false;
		});
		$this->assertMailSentTimes(1);
		$this->assertSimpleCacheEqual(\XF::$time, 'Hampel/LogDigest', 'XF:ErrorLog');
	}

	public function test_a_failed_send_leaves_last_checked_alone_so_the_next_run_retries()
	{
		$this->fakesErrors();
		$this->fakesMail()->failWith(new TransportException('Connection refused'));
		$this->lastChecked(\XF::$time - 600);
		$this->finderReturns([$this->errorLog(1, 'Something broke')]);

		$this->logDigest()->send(self::DIGEST, self::EMAIL);

		// the send was attempted and failed...
		$this->assertExceptionLogged(TransportException::class);
		$this->assertMailNotSent();
		// ...and the window did not move
		$this->assertSimpleCacheEqual(\XF::$time - 600, 'Hampel/LogDigest', 'XF:ErrorLog');
	}

	public function test_a_disabled_log_type_is_not_sent()
	{
		$this->fakesMail();
		$this->setOption('logdigestServerError', ['enabled' => false, 'frequency' => 5, 'limit' => 10, 'deduplicate' => true]);
		$this->finderIsNotUsed();

		$this->logDigest()->send(self::DIGEST, self::EMAIL);

		$this->assertNoMailSent();
		$this->assertSimpleCacheHasNot('Hampel/LogDigest', 'XF:ErrorLog');
	}

	public function test_nothing_is_sent_until_the_frequency_has_passed()
	{
		$this->fakesMail();
		$this->lastChecked(\XF::$time - 120); // 2 minutes ago, frequency is 5
		$this->finderIsNotUsed();

		$this->logDigest()->send(self::DIGEST, self::EMAIL);

		$this->assertNoMailSent();
		$this->assertSimpleCacheEqual(\XF::$time - 120, 'Hampel/LogDigest', 'XF:ErrorLog');
	}

	public function test_no_new_logs_sends_nothing_and_leaves_last_checked_alone()
	{
		$this->fakesMail();
		$this->lastChecked(\XF::$time - 600);
		$this->finderReturns([]);

		$this->logDigest()->send(self::DIGEST, self::EMAIL);

		$this->assertNoMailSent();
		$this->assertSimpleCacheEqual(\XF::$time - 600, 'Hampel/LogDigest', 'XF:ErrorLog');
	}

	public function test_reset_sets_last_checked_back_to_zero()
	{
		$this->lastChecked(\XF::$time - 600);

		$this->logDigest()->reset('server_error_log');

		$this->assertSimpleCacheEqual(0, 'Hampel/LogDigest', 'XF:ErrorLog');
	}

	public function test_the_cron_sends_every_log_type()
	{
		$this->swap('logDigest', function () {
			$mock = \Mockery::mock(LogDigest::class);
			$mock->expects('sendAll')->once();

			return $mock;
		});

		SendLogs::serverError();
	}

	public function test_send_all_uses_the_board_contact_address_when_no_address_is_set()
	{
		$this->setOptions(['logdigestEmail' => '', 'contactEmailAddress' => 'contact@example.com']);

		$this->swap('logDigest', function () {
			$mock = \Mockery::mock(LogDigest::class)->makePartial();
			$mock->allows('digestList')->andReturns(['server_error_log' => self::DIGEST]);
			$mock->expects('send')->with(self::DIGEST, 'contact@example.com')->once();

			return $mock;
		});

		$this->app()->get('logDigest')->sendAll();
	}

	/**
	 * @return LogDigest
	 */
	protected function logDigest()
	{
		return $this->app()->get('logDigest');
	}

	protected function lastChecked($timestamp)
	{
		$this->app()->repository('Hampel\LogDigest:DigestCache')->setLastChecked('XF:ErrorLog', $timestamp);
	}

	protected function errorLog($id, $message)
	{
		$log = $this->makeEntity('XF:ErrorLog', [
			'exception_date' => \XF::$time - $id * 60,
			'user_id' => 0,
			'ip_address' => '',
			'exception_type' => 'LogicException',
			'message' => $message,
			'filename' => 'src/addons/Vendor/AddOn/Thing.php',
			'line' => 42,
			'trace_string' => '#0 {main}',
			'request_state' => [],
		]);
		$log->setTrusted('error_id', $id);

		return $log;
	}

	protected function finderReturns(array $logs)
	{
		$this->mockFinder('XF:ErrorLog', function ($mock) use ($logs) {
			$mock->allows('where')->andReturnSelf();
			$mock->allows('order')->with('exception_date', 'DESC')->andReturnSelf();
			$mock->allows('limit')->andReturnSelf();
			$mock->expects('fetch')->andReturns(new ArrayCollection($logs));
		});
	}

	protected function finderIsNotUsed()
	{
		$this->mockFinder('XF:ErrorLog', function ($mock) {
			$mock->expects('fetch')->never();
		});
	}
}
