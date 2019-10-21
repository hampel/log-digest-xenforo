<?php namespace Tests\Unit;

use Hampel\LogDigest\Repository\DigestCache;
use Tests\TestCase;
use XF\SimpleCacheSet;

class DigestCacheRepoTest extends TestCase
{
	/**
	 * @var DigestCache
	 */
	private $cache;

	protected function setUp() : void
	{
		parent::setUp();

		$this->cache = new DigestCache($this->app()->em(), 'foo');
	}

	public function test_getCache()
	{
		$this->fakesSimpleCache();

		$this->assertInstanceOf(SimpleCacheSet::class, $this->cache->getCache());
	}

	public function test_getValue_type_returns_zeros_when_no_data_set()
	{
		$this->fakesSimpleCache();

		$value = $this->cache->getValue('foo');
		$this->assertArrayHasKey('checked', $value);
		$this->assertArrayHasKey('id', $value);
		$this->assertEquals(0, $value['checked']);
		$this->assertEquals(0, $value['id']);
	}

	public function test_setValue_sets_data()
	{
		$this->fakesSimpleCache();

		$this->cache->setValue('foo', 'bar');
		$this->assertSimpleCacheHas('Hampel/LogDigest', 'foo');
		$this->assertSimpleCacheEqual('bar', 'Hampel/LogDigest', 'foo');
	}

	public function test_getters_and_setters()
	{
		$this->fakesSimpleCache();

		$this->cache->setValue('foo', ['checked' => 2, 'id' => 3]);
		$this->assertEquals(2, $this->cache->getLastChecked('foo'));
		$this->assertEquals(3, $this->cache->getLastId('foo'));

		$this->cache->setLastChecked('foo', 4);
		$this->assertEquals(4, $this->cache->getLastChecked('foo'));
		$this->assertEquals(3, $this->cache->getLastId('foo'));

		$this->cache->setLastId('foo', 5);
		$this->assertEquals(5, $this->cache->getLastId('foo'));
		$this->assertEquals(4, $this->cache->getLastChecked('foo'));

		$this->cache->reset('foo');
		$this->assertEquals(0, $this->cache->getLastChecked('foo'));
		$this->assertEquals(0, $this->cache->getLastId('foo'));
	}

}
