<?php namespace Tests\Unit;

use Carbon\Carbon;
use Hampel\LogDigest\Digest\AbstractDigest;
use Hampel\LogDigest\Repository\Log;
use Tests\TestCase;
use XF\Mvc\Entity\ArrayCollection;

class LogRepoTest extends TestCase
{
	/**
	 * @var Log
	 */
	private $digest;

	protected function setUp() : void
	{
		parent::setUp();

		$this->digest = new class($this->app(), 'UTC') extends AbstractDigest {

			protected function getOptionId()
			{
				return 'optionid';
			}

			public function getLogName()
			{
				return 'log name';
			}

			protected function getEntityId()
			{
				return 'entity id';
			}

			protected function getTimestampColumn()
			{
				return 'timestamp column';
			}

			protected function getTemplate()
			{
				return 'email template';
			}

			protected function getComparisonFields()
			{
				return ['field1', 'field2'];
			}

			protected function getRoute()
			{
				return 'log route';
			}
		};
	}

	public function test_getLogs_returns_null_lastChecked()
	{
		$this->setOptions([
			'optionid' => [
				'frequency' => 5
			]
		]);

		$time = Carbon::now();
		$this->setTestTime($time);

		$this->mockRepository('Hampel\LogDigest:DigestCache', function ($mock) use ($time) {
			$mock->expects('getLastChecked')->with('entity id')->andReturns($time->copy()->subMinutes(3)->timestamp);
		});

		$this->assertNull($this->digest->getLogs());
	}

	public function test_getLogs_returns_empty_arraycollection()
	{
		$this->setOptions([
			'optionid' => [
				'frequency' => 5
			]
		]);

		$time = Carbon::now();
		$this->setTestTime($time);

		$this->mockRepository('Hampel\LogDigest:DigestCache', function ($mock) use ($time) {
			$mock->expects('getLastChecked')->with('entity id')->andReturns($time->copy()->subMinutes(10)->timestamp);
		});

		$this->mockFinder('entity id', function ($mock) use ($time) {
			$mock->expects('where')->with('timestamp column', '>', $time->copy()->subMinutes(10)->timestamp)->andReturns($mock);
			$mock->expects('order')->with('timestamp column', 'DESC')->andReturns($mock);
			$mock->expects('limit')->with(200)->andReturns($mock);
			$mock->expects('fetch')->andReturns(new ArrayCollection([]));
		});

		$logs = $this->digest->getLogs();

		$this->assertTrue($logs instanceof ArrayCollection);
		$this->assertCount(0, $logs);
	}

	public function test_getLogs_returns_arraycollection()
	{
		$this->setOptions([
			'optionid' => [
				'frequency' => 5
			]
		]);

		$time = Carbon::now();
		$this->setTestTime($time);

		$this->mockRepository('Hampel\LogDigest:DigestCache', function ($mock) use ($time) {
			$mock->expects('getLastChecked')->with('entity id')->andReturns($time->copy()->subMinutes(6)->timestamp);
		});

		$this->mockEntity('foo', false);

		$logs = [
			$this->app()->em()->create('foo'),
			$this->app()->em()->create('foo'),
		];

		$this->mockFinder('entity id', function ($mock) use ($logs, $time) {
			$mock->expects('where')->with('timestamp column', '>', $time->copy()->subMinutes(6)->timestamp)->andReturns($mock);
			$mock->expects('order')->with('timestamp column', 'DESC')->andReturns($mock);
			$mock->expects('limit')->with(200)->andReturns($mock);
			$mock->expects('fetch')->andReturns(new ArrayCollection($logs));
		});

		$logs = $this->digest->getLogs();

		$this->assertCount(2, $logs);
	}

	public function test_prepareLogs_returns_array()
	{
		$this->setOptions([
			'optionid' => [
				'deduplicate' => false,
				'limit' => 0,
			]
		]);

		$time = Carbon::now();
		$this->setTestTime($time);

//		$this->mockRepository('Hampel\LogDigest:DigestCache', function ($mock) use ($time) {
//			$mock->expects('setLastChecked')->with('entity id', 4000000);
//		});

		$user = $this->mockEntity('XF:User', true, function ($mock) {
			$mock->expects()->get('username')->twice()->andReturn(['username1', 'username2']);
		});

		$this->mockEntity('foo', false, function ($mock) use ($user) {
			$mock->expects()->toArray()->twice()->andSet('User', $user)->andReturn(['a' => 1, 'timestamp column' => 2000000], ['a' => 3, 'timestamp column' => 4000000]);
		});

		$logs = new ArrayCollection([
			$this->app()->em()->create('foo'),
			$this->app()->em()->create('foo'),
		]);

		$prepared = $this->digest->prepareLogs($logs);

		$this->assertIsArray($prepared);
		$this->assertCount(2, $prepared);
	}

	public function test_prepareLogs_returns_array_limited()
	{
		$this->setOptions([
			'optionid' => [
				'deduplicate' => false,
				'limit' => 1,
			]
		]);

		$time = Carbon::now();
		$this->setTestTime($time);

//		$this->mockRepository('Hampel\LogDigest:DigestCache', function ($mock) use ($time) {
//			$mock->expects('setLastChecked')->with('entity id', 2000000);
//		});

		$user = $this->mockEntity('XF:User', true, function ($mock) {
			$mock->expects()->get('username')->once()->andReturn(['username1']);
		});

		$this->mockEntity('foo', false, function ($mock) use ($user) {
			$mock->expects()->toArray()->once()->andSet('User', $user)->andReturn(['a' => 1, 'timestamp column' => 2000000]);
		});

		$logs = new ArrayCollection([
	        $this->app()->em()->create('foo'),
	        $this->app()->em()->create('foo'),
        ]);

		$prepared = $this->digest->prepareLogs($logs);

		$this->assertIsArray($prepared);
		$this->assertCount(1, $prepared);
	}

	public function test_prepareLogs_returns_array_dedup()
	{
		$this->setOptions([
			'optionid' => [
				'deduplicate' => true,
				'limit' => 0,
			]
		]);

		$time = Carbon::now();
		$this->setTestTime($time);

//		$this->mockRepository('Hampel\LogDigest:DigestCache', function ($mock) use ($time) {
//			$mock->expects('setLastChecked')->with('entity id', 6000000);
//		});

		$user = $this->mockEntity('XF:User', true, function ($mock) {
			$mock->expects()->get('username')->times(6)->andReturn(['username1', 'username2', 'username3', 'username1', 'username1', 'username1']);
		});

		$this->mockEntity('foo', false, function ($mock) use ($user) {
			$mock->expects()->toArray()->times(6)->andSet('User', $user)->andReturn(
				['field1' => 1, 'field2' => 'a', 'field3' => true, 'timestamp column' => 1000000],
				['field1' => 2, 'field2' => 'b', 'field3' => false, 'timestamp column' => 2000000],
				['field1' => 1, 'field2' => 'c', 'field3' => true, 'timestamp column' => 3000000],
				['field1' => 1, 'field2' => 'a', 'field3' => false, 'timestamp column' => 4000000],
				['field1' => 1, 'field2' => 'c', 'field3' => true, 'timestamp column' => 5000000],
				['field1' => 1, 'field2' => 'b', 'field3' => false, 'timestamp column' => 6000000]
			);
		});

		$logs = new ArrayCollection([
	        $this->app()->em()->create('foo'),
	        $this->app()->em()->create('foo'),
	        $this->app()->em()->create('foo'),
	        $this->app()->em()->create('foo'),
	        $this->app()->em()->create('foo'),
	        $this->app()->em()->create('foo'),
        ]);

		$prepared = $this->digest->prepareLogs($logs);

		$this->assertIsArray($prepared);
		$this->assertCount(6, $prepared);
		$this->assertFalse($prepared[0]['duplicate']);
		$this->assertFalse($prepared[1]['duplicate']);
		$this->assertFalse($prepared[2]['duplicate']);
		$this->assertTrue($prepared[3]['duplicate']);
		$this->assertTrue($prepared[4]['duplicate']);
		$this->assertFalse($prepared[5]['duplicate']);
	}

}
