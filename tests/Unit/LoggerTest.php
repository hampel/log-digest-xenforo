<?php namespace Tests\Unit;

use Hampel\LogDigest\Helper\Log;
use Psr\Log\NullLogger;
use Tests\TestCase;

/**
 * Logging is optional: it goes to a logging add-on's `logdigest` channel where one is installed,
 * and nowhere otherwise. The channel is asked for by the current name where the logging add-on has
 * it, and by the older name where it does not.
 */
class LoggerTest extends TestCase
{
	public function test_without_a_logging_add_on_every_call_goes_to_a_null_logger()
	{
		unset($this->app()->container()['monolog']);

		$this->assertInstanceOf(NullLogger::class, $this->logger());

		// every level the rest of the add-on calls, through the facade it calls them by
		foreach (['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'] AS $level)
		{
			Log::$level('a message', ['key' => 'value']);
		}
	}

	public function test_it_asks_for_its_channel_by_the_current_name_where_that_exists()
	{
		$channel = $this->recorder();
		$this->swap('monolog', new class($channel) {
			public $asked = [];
			private $channel;

			public function __construct($channel) { $this->channel = $channel; }

			public function channel($name) { $this->asked[] = "channel:{$name}"; return $this->channel; }

			public function newChannel($name) { $this->asked[] = "newChannel:{$name}"; return $this->channel; }
		});

		Log::info('a message', ['key' => 'value']);

		$this->assertSame(['channel:logdigest'], $this->app()->container()['monolog']->asked);
		$this->assertSame([['info', 'a message', ['key' => 'value']]], $channel->records);
	}

	public function test_it_falls_back_to_the_older_name_where_that_is_all_there_is()
	{
		$channel = $this->recorder();
		$this->swap('monolog', new class($channel) {
			public $asked = [];
			private $channel;

			public function __construct($channel) { $this->channel = $channel; }

			public function newChannel($name) { $this->asked[] = "newChannel:{$name}"; return $this->channel; }
		});

		Log::warning('another message');

		$this->assertSame(['newChannel:logdigest'], $this->app()->container()['monolog']->asked);
		$this->assertSame([['warning', 'another message', []]], $channel->records);
	}

	/**
	 * A logger that keeps what it is given. Built here, not declared at file scope: PHPUnit loads
	 * test files before XenForo's autoloader exists.
	 */
	private function recorder()
	{
		return new class extends \Psr\Log\AbstractLogger {
			public $records = [];

			public function log($level, $message, array $context = []): void
			{
				$this->records[] = [$level, $message, $context];
			}
		};
	}

	private function logger()
	{
		return $this->app()->get('logDigest.log')->container('log');
	}
}
