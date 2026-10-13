<?php namespace Tests\Feature;

use Hampel\LogDigest\Cli\Command\Config;
use Hampel\LogDigest\Cli\Command\Validate;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;
use XF\Mvc\Entity\ArrayCollection;

/**
 * log-digest:config and log-digest:validate. The exit code is a contract other things rely on: 1
 * always means failed, a warning is 0 unless --strict asks for 2, and --unattended sends nothing.
 */
class CommandsTest extends TestCase
{
	const CACHE = 'Hampel/LogDigest';

	/** @var array the cron entry the command will find, set before it runs */
	protected $cron;

	protected function setUp() : void
	{
		parent::setUp();

		$this->fakesErrors();
		$this->fakesSimpleCache();
		$this->fakesMail();

		// a forum that is set up correctly, with nothing waiting
		$this->setOptions([
			'logdigestEmail' => 'admin@example.com',
			'logdigestTimeZone' => 'UTC',
			'logdigestServerError' => ['enabled' => true, 'frequency' => 5, 'limit' => 10, 'deduplicate' => true],
			'logdigestAdminLog' => ['enabled' => false, 'frequency' => 360, 'limit' => 25, 'deduplicate' => true],
		]);
		$this->cron = ['active' => true, 'next_run' => \XF::$time + 60];
	}

	public function test_config_prints_the_settings_and_changes_nothing()
	{
		$this->waiting(0);
		$this->lastChecked(\XF::$time - 120);

		$tester = $this->command(Config::class);
		$display = $tester->getDisplay();

		$this->assertSame(0, $tester->getStatusCode());
		$this->assertStringContainsString('admin@example.com', $display);
		$this->assertStringContainsString('(options)', $display);
		$this->assertStringContainsString('5 minutes', $display);
		$this->assertStringContainsString('10 entries per digest', $display);
		$this->assertStringContainsString('active', $display);
		$this->assertNoMailSent();
		$this->assertSimpleCacheEqual(\XF::$time - 120, self::CACHE, 'XF:ErrorLog');
		$this->assertNoErrorsLogged();
	}

	public function test_config_says_when_the_board_contact_address_is_used()
	{
		$this->waiting(0);
		$this->setOptions(['logdigestEmail' => '', 'contactEmailAddress' => 'contact@example.com']);

		$display = $this->command(Config::class)->getDisplay();

		$this->assertStringContainsString('contact@example.com', $display);
		$this->assertStringContainsString('(the board contact address)', $display);
	}

	public static function states() : array
	{
		return [
			'clean' => ['clean', false, 0],
			'clean, strict' => ['clean', true, 0],
			'warning' => ['warning', false, 0],
			'warning, strict' => ['warning', true, 2],
			'failure' => ['failure', false, 1],
			'failure, strict' => ['failure', true, 1],
			'failure and warning, strict' => ['both', true, 1],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('states')]
	public function test_the_exit_code_is_the_contract($state, $strict, $expected)
	{
		$this->waiting(0);

		if ($state == 'warning' || $state == 'both')
		{
			// nothing is switched on
			$this->setOption('logdigestServerError', ['enabled' => false, 'frequency' => 5, 'limit' => 10, 'deduplicate' => true]);
		}
		if ($state == 'failure' || $state == 'both')
		{
			$this->setOption('logdigestEmail', 'not an email address');
		}

		$tester = $this->command(Validate::class, ['--unattended' => true] + ($strict ? ['--strict' => true] : []));
		$display = $tester->getDisplay();

		$this->assertSame($expected, $tester->getStatusCode(), $display);
		$this->assertSame($state == 'warning' || $state == 'both', strpos($display, '[warn]') !== false, $display);
		$this->assertSame($state == 'failure' || $state == 'both', strpos($display, '[fail]') !== false, $display);
	}

	public function test_nothing_switched_on_is_a_warning_and_not_a_pass()
	{
		$this->waiting(0);
		$this->setOption('logdigestServerError', ['enabled' => false, 'frequency' => 5, 'limit' => 10, 'deduplicate' => true]);

		$display = $this->command(Validate::class, ['--unattended' => true])->getDisplay();

		$this->assertMatchesRegularExpression('/\[warn\] log types\s+no log type is enabled/', $display);
		$this->assertStringContainsString('Validated, with warnings', $display);
	}

	public function test_unattended_sends_nothing()
	{
		// there is an entry to send, so a run that did not skip would send it
		$this->waiting(0, [$this->errorLog()]);

		$tester = $this->command(Validate::class, ['--unattended' => true]);

		$this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
		$this->assertStringContainsString('skipped - --unattended', $tester->getDisplay());
		$this->assertNoMailSent();
	}

	public function test_the_test_digest_is_sent_and_leaves_last_checked_alone()
	{
		$this->waiting(0, [$this->errorLog()]);
		$this->lastChecked(\XF::$time - 120);

		$tester = $this->command(Validate::class);

		$this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
		$this->assertStringContainsString('test digest sent to admin@example.com', $tester->getDisplay());
		$this->assertMailSent(function ($mail) {
			return $mail->getTo()[0]->getAddress() == 'admin@example.com'
				&& strpos($mail->getSubject(), '[Test]') !== false;
		});
		$this->assertSimpleCacheEqual(\XF::$time - 120, self::CACHE, 'XF:ErrorLog');
	}

	public function test_a_test_digest_that_cannot_be_sent_fails_the_run()
	{
		$this->fakesMail()->failWith(new TransportException('Connection refused'));
		$this->waiting(0, [$this->errorLog()]);

		$tester = $this->command(Validate::class);

		$this->assertSame(1, $tester->getStatusCode(), $tester->getDisplay());
		$this->assertMatchesRegularExpression('/\[fail\] server error log\s+could not be sent/', $tester->getDisplay());
		$this->assertStringContainsString('Validation failed', $tester->getDisplay());
	}

	public function test_an_empty_log_skips_the_test_digest_and_says_delivery_was_not_tested()
	{
		$this->waiting(0, []);

		$tester = $this->command(Validate::class);

		$this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
		$this->assertStringContainsString('delivery was not tested', $tester->getDisplay());
		$this->assertNoMailSent();
	}

	public function test_entries_waiting_long_after_the_last_digest_fail_the_run()
	{
		$this->waiting(7);
		$this->lastChecked(\XF::$time - 7200);

		$tester = $this->command(Validate::class, ['--unattended' => true]);

		$this->assertSame(1, $tester->getStatusCode(), $tester->getDisplay());
		$this->assertMatchesRegularExpression('/\[fail\] server error log\s+7 waiting since the last digest/', $tester->getDisplay());
		$this->assertStringContainsString('digests are not going out', $tester->getDisplay());
	}

	public function test_entries_waiting_inside_the_frequency_are_not_a_failure()
	{
		$this->waiting(7);
		$this->lastChecked(\XF::$time - 240); // frequency is 5 minutes

		$tester = $this->command(Validate::class, ['--unattended' => true]);

		$this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
		$this->assertMatchesRegularExpression('/\[ ok \] server error log\s+last digest .* 7 waiting/', $tester->getDisplay());
	}

	public function test_a_log_type_never_sent_with_entries_waiting_is_a_warning()
	{
		$this->waiting(3);

		$tester = $this->command(Validate::class, ['--unattended' => true, '--strict' => true]);

		$this->assertSame(2, $tester->getStatusCode(), $tester->getDisplay());
		$this->assertStringContainsString('never sent, or reset - 3 waiting', $tester->getDisplay());
	}

	public function test_a_disabled_cron_entry_fails_the_run()
	{
		$this->waiting(0);
		$this->cron = ['active' => false, 'next_run' => \XF::$time + 60];

		$tester = $this->command(Validate::class, ['--unattended' => true]);

		$this->assertSame(1, $tester->getStatusCode(), $tester->getDisplay());
		$this->assertStringContainsString('the cron entry is disabled', $tester->getDisplay());
	}

	public function test_a_cron_entry_that_is_overdue_is_a_warning()
	{
		$this->waiting(0);
		$this->cron = ['active' => true, 'next_run' => \XF::$time - 7200];

		$tester = $this->command(Validate::class, ['--unattended' => true, '--strict' => true]);

		$this->assertSame(2, $tester->getStatusCode(), $tester->getDisplay());
		$this->assertStringContainsString('is the job queue running?', $tester->getDisplay());
	}

	public function test_a_time_zone_that_is_not_one_fails_the_run()
	{
		$this->waiting(0);
		$this->setOption('logdigestTimeZone', 'Mars/Olympus');

		$tester = $this->command(Validate::class, ['--unattended' => true]);

		$this->assertSame(1, $tester->getStatusCode(), $tester->getDisplay());
		$this->assertMatchesRegularExpression('/\[fail\] time zone\s+\'Mars\/Olympus\' is not a time zone/', $tester->getDisplay());
	}

	public function test_a_check_that_throws_is_reported_and_the_rest_still_run()
	{
		$this->mockFinder('XF:ErrorLog', function ($mock) {
			$mock->allows('where')->andReturnSelf();
			$mock->allows('total')->andThrow(new \RuntimeException('the table is gone'));
		});

		$tester = $this->command(Validate::class, ['--unattended' => true]);
		$display = $tester->getDisplay();

		$this->assertSame(1, $tester->getStatusCode(), $display);
		$this->assertMatchesRegularExpression('/\[fail\] server error log\s+RuntimeException: the table is gone/', $display);
		// the checks before it, and the sections after it, still reported
		$this->assertMatchesRegularExpression('/\[ ok \] recipient/', $display);
		$this->assertStringContainsString('Test digest', $display);
		$this->assertExceptionLogged(\RuntimeException::class, 'LogDigest: ');
	}

	/**
	 * How many entries the server error log reports as waiting, and what a fetch of it returns.
	 */
	protected function waiting($count, ?array $logs = null)
	{
		$this->mockFinder('XF:ErrorLog', function ($mock) use ($count, $logs) {
			$mock->allows('where')->andReturnSelf();
			$mock->allows('order')->andReturnSelf();
			$mock->allows('limit')->andReturnSelf();
			$mock->allows('total')->andReturns($count);
			$mock->allows('fetch')->andReturns(new ArrayCollection($logs ?: []));
		});
	}

	protected function lastChecked($timestamp)
	{
		$this->app()->repository('Hampel\LogDigest:DigestCache')->setLastChecked('XF:ErrorLog', $timestamp);
	}

	/**
	 * Runs a command against the cron entry in $this->cron. The entry is registered here, once,
	 * because a second registration for the same id would be ignored.
	 */
	protected function command($class, array $input = [])
	{
		$entry = $this->makeEntity('XF:CronEntry', $this->cron);
		$entry->setTrusted('entry_id', 'logdigestSendLogs');
		$this->mockFind('XF:CronEntry', 'logdigestSendLogs', $entry);

		return $this->runConsoleCommand($class, $input);
	}

	protected function errorLog()
	{
		$log = $this->makeEntity('XF:ErrorLog', [
			'exception_date' => \XF::$time - 60,
			'user_id' => 0,
			'ip_address' => '',
			'exception_type' => 'LogicException',
			'message' => 'Something broke',
			'filename' => 'src/addons/Vendor/AddOn/Thing.php',
			'line' => 42,
			'trace_string' => '#0 {main}',
			'request_state' => [],
		]);
		$log->setTrusted('error_id', 1);

		return $log;
	}
}
