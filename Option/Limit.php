<?php namespace Hampel\LogDigest\Option;

use XF\Option\AbstractOption;

class Limit extends AbstractOption
{
	public static function get()
	{
		$limit = \XF::options()->logdigestLimit;
		return ($limit > 0 ? $limit : null);
	}
}
