<?php namespace Hampel\LogDigest\Option;

use XF\Option\AbstractOption;

class Email extends AbstractOption
{
	public static function get()
	{
		$email = \XF::options()->logdigestEmail;
		return (empty($email) ? \XF::options()->contactEmailAddress : $email);
	}
}
