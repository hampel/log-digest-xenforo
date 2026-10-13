<?php namespace Hampel\LogDigest\SubContainer;

use XF\SubContainer\AbstractSubContainer;

class Log extends AbstractSubContainer
{
	public function initialize()
	{
		$container = $this->container;

		$container['log'] = function($c)
		{
			if (!$this->parent->offsetExists('monolog'))
			{
				// no logging add-on is installed: every call goes nowhere
				return new \Psr\Log\NullLogger();
			}

			$monolog = $this->parent['monolog'];

			// channel() arrived in Monolog 5.0; newChannel() is the 4.x name, deprecated in 5.x
			return method_exists($monolog, 'channel') ? $monolog->channel('logdigest') : $monolog->newChannel('logdigest');
		};
	}

	public function emergency($message, array $context = array())
	{
		return $this->log('emergency', $message, $context);
	}

	public function alert($message, array $context = array())
	{
		return $this->log('alert', $message, $context);
	}

	public function critical($message, array $context = array())
	{
		return $this->log('critical', $message, $context);
	}

	public function error($message, array $context = array())
	{
		return $this->log('error', $message, $context);
	}

	public function warning($message, array $context = array())
	{
		return $this->log('warning', $message, $context);
	}

	public function notice($message, array $context = array())
	{
		return $this->log('notice', $message, $context);
	}

	public function info($message, array $context = array())
	{
		return $this->log('info', $message, $context);
	}

	public function debug($message, array $context = array())
	{
		return $this->log('debug', $message, $context);
	}


	/**
	 * @param $level
	 * @param $message
	 * @param array $context
	 */
	public function log($level, $message, array $context = array()): void
	{
		/** @var \Psr\Log\LoggerInterface $logger */
		$logger = $this->container['log'];
		$logger->log($level, $message, $context);
	}
}
