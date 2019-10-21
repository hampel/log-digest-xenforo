<?php namespace Hampel\LogDigest\Option;

use XF\Option\AbstractOption;

class ServerError extends AbstractOption
{
	public static function isEnabled()
	{
		return boolval(\XF::options()->logdigestServerError['enabled']);
	}

	public static function frequency()
	{
		return intval(\XF::options()->logdigestServerError['frequency']);
	}

	public static function frequencySeconds()
	{
		return self::frequency() * 60;
	}

	public static function limit()
	{
		$limit = intval(\XF::options()->logdigestServerError['limit']);
		return ($limit > 0 ? $limit : null);
	}

	public static function deduplicate()
	{
		return boolval(\XF::options()->logdigestServerError['deduplicate']);
	}
}
