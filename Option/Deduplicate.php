<?php namespace Hampel\LogDigest\Option;

use XF\Option\AbstractOption;

class Deduplicate extends AbstractOption
{
	public static function get()
	{
		return \XF::options()->logdigestDeduplicate;
	}
}
