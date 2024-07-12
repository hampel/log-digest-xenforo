<?php namespace Hampel\LogDigest\Repository;

use XF\Mvc\Entity\Repository;
use XF\SimpleCacheSet;

class DigestCache extends Repository
{
	/** @return SimpleCacheSet */
	public function getCache()
	{
		return \XF::app()->simpleCache()->getSet('Hampel/LogDigest');
	}

	public function getValue($type)
	{
		return $this->getCache()->getValue($type);
	}

	public function getLastChecked($type)
	{
		$lastChecked = $this->getValue($type);

		// legacy support - in case cache still has old array values stored
		if (is_array($lastChecked) && isset($lastChecked['checked']))
		{
			return $lastChecked['checked'];
		}

		return intval($lastChecked ?? 0);
	}

	public function setValue($type, $data)
	{
		$this->getCache()->setValue($type, $data);
	}

	public function setLastChecked($type, $timestamp)
	{
		$this->setValue($type, $timestamp);
	}

	public function reset($type)
	{
		$this->setLastChecked($type, 0);
	}
}
