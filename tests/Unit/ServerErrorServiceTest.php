<?php namespace Tests\Unit;

use Hampel\LogDigest\Service\LogSender\ServerError;
use Mockery as m;
use Tests\TestCase;
use XF\Mvc\Entity\ArrayCollection;
use XF\Util\Ip;

class ServerErrorServiceTest extends TestCase
{
	public function test_filterLogData_no_logs()
	{
		$service = new ServerError($this->app(), new ArrayCollection([]), 'UTC');

		$this->assertNull($service->filterLogData(false, 0));
	}

	public function test_filterLogData_one_log()
	{
		$log = $this->getLog(1);

		$service = new ServerError($this->app(), new ArrayCollection([$log]), 'UTC');

		$filtered = $service->filterLogData(false, 0);

		$this->assertEquals('Server error', $filtered['type']);
		$this->assertEquals('logs/server-errors', $filtered['route']);
		$this->assertCount(1, $filtered['logs']);
		$this->assertEquals('foo broke', $filtered['logs'][0]['message']);
		$this->assertFalse($filtered['extra'][1]['duplicate']);
		$this->assertEquals(0, $filtered['duplicateCount']);
	}

	public function test_filterLogData_two_logs_no_dedup_limit_1()
	{
		$log1 = $this->getLog(1);
		$log2 = $this->getLog(2);

		$service = new ServerError($this->app(), new ArrayCollection([$log1, $log2]), 'UTC');

		$filtered = $service->filterLogData(false, 1);

		$this->assertCount(1, $filtered['logs']);
		$this->assertEquals(1, $filtered['logs'][0]['error_id']);
		$this->assertEquals(0, $filtered['duplicateCount']);
	}

	public function test_filterLogData_two_logs_no_dedup_limit_2()
	{
		$log1 = $this->getLog(1);
		$log2 = $this->getLog(2);

		$service = new ServerError($this->app(), new ArrayCollection([$log1, $log2]), 'UTC');

		$filtered = $service->filterLogData(false, 2);

		$this->assertCount(2, $filtered['logs']);
		$this->assertEquals(1, $filtered['logs'][0]['error_id']);
		$this->assertEquals(2, $filtered['logs'][1]['error_id']);
		$this->assertEquals(0, $filtered['duplicateCount']);
	}

	public function test_filterLogData_two_logs_dedup()
	{
		$log1 = $this->getLog(1);
		$log2 = $this->getLog(2);

		$service = new ServerError($this->app(), new ArrayCollection([$log1, $log2]), 'UTC');

		$filtered = $service->filterLogData(true, 2);

		$this->assertCount(2, $filtered['logs']);
		$this->assertFalse($filtered['extra'][1]['duplicate']);
		$this->assertTrue($filtered['extra'][2]['duplicate']);
		$this->assertEquals(1, $filtered['duplicateCount']);
	}

	public function test_send_not_filtered_returns_null()
	{
		$this->fakesMail();

		$log1 = $this->getLog(1);
		$service = new ServerError($this->app(), new ArrayCollection([$log1]), 'UTC');
		$this->assertNull($service->send('foo@example.com', 'server_error', []));
	}

	public function test_send()
	{
		$this->fakesMail();

		$log1 = $this->getLog(1);
		$service = new ServerError($this->app(), new ArrayCollection([$log1]), 'UTC');
		$filtered = $service->filterLogData(false, 1);
		$service->send('foo@example.com', 'server_error', $filtered);

		$this->assertMailSent(function ($mail) {
			return $mail->getSubject() == "Server error log digest from " . \XF::options()->boardTitle;
		});
	}

	public function test_getLastId_not_filtered_returns_null()
	{
		$this->fakesMail();

		$log1 = $this->getLog(1);
		$service = new ServerError($this->app(), new ArrayCollection([$log1]), 'UTC');
		$this->assertNull($service->getLastId());
	}

	public function test_getLastId()
	{
		$this->fakesMail();

		$log1 = $this->getLog(1);
		$service = new ServerError($this->app(), new ArrayCollection([$log1]), 'UTC');
		$service->filterLogData(false, 1);

		$this->assertEquals(1, $service->getLastId());
	}

	// --------------------------------------------------------

	private function getLog($id)
	{
		$this->mockFinder('XF:ErrorLog', function ($mock) {
			$mock->shouldReceive('where')->with('error_id', '=', m::any())->andReturns($mock);
			$mock->shouldReceive('fetchOne');
		});

		$log = $this->app()->em()->create('XF:ErrorLog');
		$log->bulkSet([
			'error_id' => $id,
			'exception_date' => \XF::$time,
			'user_id' => 1,
			'ip_address' => Ip::convertIpStringToBinary('10.0.0.1'),
			'exception_type' => 'foo',
			'message' => 'foo broke',
			'filename' => 'foo.php',
			'line' => 2,
			'trace_string' => 'bar',
			'request_state' => [],
		], ['forceSet' => true]);

		return $log;
	}
}
