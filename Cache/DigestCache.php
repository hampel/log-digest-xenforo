<?php namespace Hampel\LogDigest\Cache;

use XF\SimpleCacheSet;

class DigestCache
{
	// TODO: turn this into a repo

	/** @return SimpleCacheSet */
	public static function getCache()
	{
		return \XF::app()->simpleCache()->getSet('Hampel/LogDigest');
	}

	public static function getValue($type)
	{
		$data = self::getCache()->getValue($type);
		if (!isset($data['checked'])) $data['checked'] = 0;
		if (!isset($data['id'])) $data['id'] = 0;
		return $data;
	}

	public static function getLastChecked($type)
	{
		return self::getValue($type)['checked'];
	}

	public static function getLastId($type)
	{
		return self::getValue($type)['id'];
	}

	public static function reset($type)
	{
		$data = ['checked' => 0, 'id' => 0];
		self::getCache()->setValue($type, $data);
	}

	public static function setValue($type, $data)
	{
		self::getCache()->setValue($type, $data);
	}

	public static function setLastChecked($type, $timestamp)
	{
		$data = self::getValue($type);
		$data['checked'] = $timestamp;
		self::setValue($type, $data);
	}

	public static function setLastId($type, $id)
	{
		$data = self::getValue($type);
		$data['id'] = $id;
		self::setValue($type, $data);
	}
}
