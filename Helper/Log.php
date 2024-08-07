<?php namespace Hampel\LogDigest\Helper;

class Log
{
	public static function emergency($message, array $context = array())
	{
		return self::log('emergency', $message, $context);
	}

	public static function alert($message, array $context = array())
	{
		return self::log('alert', $message, $context);
	}

	public static function critical($message, array $context = array())
	{
		return self::log('critical', $message, $context);
	}

	public static function error($message, array $context = array())
	{
		return self::log('error', $message, $context);
	}

	public static function warning($message, array $context = array())
	{
		return self::log('warning', $message, $context);
	}

	public static function notice($message, array $context = array())
	{
		return self::log('notice', $message, $context);
	}

	public static function info($message, array $context = array())
	{
		return self::log('info', $message, $context);
	}

	public static function debug($message, array $context = array())
	{
		return self::log('debug', $message, $context);
	}

	public static function log($level, $message, array $context = array())
	{
		$logger = self::getLogger();

		if (isset($logger))
		{
			return $logger->log($level, $message, $context);
		}
	}

	/**
	 * @return \Hampel\NativeAds\SubContainer\Log
	 */
	public static function getLogger()
	{
		return \XF::app()->get('logDigest.log');
	}
}
