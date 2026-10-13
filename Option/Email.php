<?php namespace Hampel\LogDigest\Option;

use XF\Option\AbstractOption;

class Email extends AbstractOption
{
	public static function get()
	{
		$email = \XF::options()->logdigestEmail;
		return (empty($email) ? \XF::options()->contactEmailAddress : $email);
	}

	/**
	 * @return bool true when no recipient is set, so the board's contact address is used
	 */
	public static function isContactAddress()
	{
		return empty(\XF::options()->logdigestEmail);
	}
}
