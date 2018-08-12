<?php namespace Hampel\LogDigest\Option;

use XF\Option\AbstractOption;

class TimeZone extends AbstractOption
{
	public static function get()
	{
		return \XF::options()->logdigestTimeZone;
	}

	public static function renderOption(\XF\Entity\Option $option, array $htmlParams)
	{
		/** @var \XF\Data\TimeZone $tzData */
		$tzData = \XF::app()->data('XF:TimeZone');

		return self::getSelectRow($option, $htmlParams, $tzData->getTimeZoneOptions());
	}
}
