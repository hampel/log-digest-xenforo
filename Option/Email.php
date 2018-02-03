<?php namespace LogDigest\Option;

use XF\Option\AbstractOption;

class Email extends AbstractOption
{
	public static function get()
	{
		$email = \XF::options()->logdigestEmail;
		if (empty($email)) $email = \XF::options()->contactEmailAddress;
		return $email;
	}
}
