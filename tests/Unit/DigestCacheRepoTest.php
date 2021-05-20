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
		$this->assertEquals(0, $value);
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

		$this->cache->setValue('foo1', 2);
		$this->assertEquals(2, $this->cache->getLastChecked('foo1'));

		$this->cache->setLastChecked('foo2', 3);
		$this->assertEquals(3, $this->cache->getLastChecked('foo2'));

		// legacy data check
		$this->cache->setValue('foo3', ['checked' => 4, 'id' => 5]);
		$this->assertEquals(4, $this->cache->getLastChecked('foo3'));
	}

	public function test_reset_returns_zero()
	{
		$this->fakesSimpleCache();

		$this->cache->setValue('foo', 6);
		$this->assertEquals(6, $this->cache->getValue('foo'));

		$this->cache->reset('foo');
		$this->assertEquals(0, $this->cache->getValue('foo'));
	}

}
