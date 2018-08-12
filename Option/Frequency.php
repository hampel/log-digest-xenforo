<?php namespace Hampel\LogDigest\Option;

use XF\Option\AbstractOption;

class Frequency extends AbstractOption
{
	public static function get()
	{
		return \XF::options()->logdigestFrequency;
	}

	public static function getSeconds()
	{
		return (self::get() * 60);
	}
}
