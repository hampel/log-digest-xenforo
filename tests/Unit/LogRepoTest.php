<?php namespace Tests\Unit;

use Carbon\Carbon;
use Hampel\LogDigest\Repository\Log;
use Tests\TestCase;
use XF\Mvc\Entity\ArrayCollection;

class LogRepoTest extends TestCase
{
	/**
	 * @var Log
	 */
	private $log;

	protected function setUp() : void
	{
		parent::setUp();

		$this->log = new Log($this->app()->em(), 'foo');
	}

	public function test_getServerErrorLogs_last_checked_recently()
	{
		/** @var Carbon $time */
		$time = Carbon::now();
		$this->setTestTime($time);

		$this->mockRepository('Hampel\LogDigest:DigestCache', function ($mock) use ($time) {
			$mock->expects('getLastChecked')->with('XF:ErrorLog')->andReturns($time->copy()->subMinute()->timestamp);
		});

		$this->assertNull($this->log->getServerErrorLogs(5*60));
	}

	public function test_getServerErrorLogs_no_logs()
	{
		$time = Carbon::now();
		$this->setTestTime($time);

		$this->mockRepository('Hampel\LogDigest:DigestCache', function ($mock) use ($time) {
			$mock->expects('getLastChecked')->with('XF:ErrorLog')->andReturns($time->copy()->subMinutes(6)->timestamp);
			$mock->expects('getLastId')->with('XF:ErrorLog')->andReturns(3);
			$mock->expects('setLastChecked')->with('XF:ErrorLog', $time->timestamp);
		});

		$this->mockFinder('XF:ErrorLog', function ($mock) {
			$mock->expects('where')->with('error_id', '>', 3)->andReturns($mock);
			$mock->expects('order')->with('error_id', 'ASC')->andReturns($mock);
			$mock->expects('fetch')->andReturns(new ArrayCollection([]));
		});

		$this->assertNull($this->log->getServerErrorLogs(5*60));
	}

	public function test_getServerErrorLogs_logs()
	{
		$time = Carbon::now();
		$this->setTestTime($time);

		$this->mockRepository('Hampel\LogDigest:DigestCache', function ($mock) use ($time) {
			$mock->expects('getLastChecked')->with('XF:ErrorLog')->andReturns($time->copy()->subMinutes(6)->timestamp);
			$mock->expects('getLastId')->with('XF:ErrorLog')->andReturns(3);
		});

		$logs = [
			$this->app()->em()->create('XF:ErrorLog'),
			$this->app()->em()->create('XF:ErrorLog'),
		];

		$this->mockFinder('XF:ErrorLog', function ($mock) use ($logs) {
			$mock->expects('where')->with('error_id', '>', 3)->andReturns($mock);
			$mock->expects('order')->with('error_id', 'ASC')->andReturns($mock);
			$mock->expects('fetch')->andReturns(new ArrayCollection($logs));
		});

		$logs = $this->log->getServerErrorLogs(5*60);
		$this->assertCount(2, $logs);
	}

	public function test_updateLastSent_no_id()
	{
		$this->mockRepository('Hampel\LogDigest:DigestCache', function ($mock) {});

		$this->assertNull($this->log->updateLastSent('foo', null));
	}

	public function test_updateLastSent_with_id()
	{
		$time = Carbon::now();
		$this->setTestTime($time);

		$this->mockRepository('Hampel\LogDigest:DigestCache', function ($mock) use ($time) {
			$mock->expects('setValue')->with('foo', ['checked' => $time->timestamp, 'id' => 3]);
		});

		$this->log->updateLastSent('foo', 3);
	}
}
