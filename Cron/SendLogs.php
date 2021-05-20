<?php namespace Hampel\LogDigest\Cron;

class SendLogs
{
	public static function serverError()
	{
		\XF::app()->get('logDigest')->sendAll();
	}
}
