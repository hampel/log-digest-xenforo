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
		$data = $this->getCache()->getValue($type);
		if (!isset($data['checked'])) $data['checked'] = 0;
		if (!isset($data['id'])) $data['id'] = 0;
		return $data;
	}

	public function getLastChecked($type)
	{
		return $this->getValue($type)['checked'];
	}

	public function getLastId($type)
	{
		return $this->getValue($type)['id'];
	}

	public function reset($type)
	{
		$data = ['checked' => 0, 'id' => 0];
		$this->setValue($type, $data);
	}

	public function setValue($type, $data)
	{
		$this->getCache()->setValue($type, $data);
	}

	public function setLastChecked($type, $timestamp)
	{
		$data = $this->getValue($type);
		$data['checked'] = $timestamp;
		$this->setValue($type, $data);
	}

	public function setLastId($type, $id)
	{
		$data = $this->getValue($type);
		$data['id'] = $id;
		$this->setValue($type, $data);
	}
}
