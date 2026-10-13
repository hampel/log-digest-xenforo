<?php namespace Hampel\LogDigest;

use Hampel\LogDigest\SubContainer\Log;
use Hampel\LogDigest\SubContainer\LogDigest;
use XF\App;
use XF\Container;

class Listener
{
	public static function appSetup(App $app)
	{
		$container = $app->container();

		$container['logDigest'] = function(Container $c) use ($app)
		{
			$class = $app->extendClass(LogDigest::class);
			return new $class($c, $app);
		};

		$container['logDigest.log'] = function(Container $c) use ($app)
		{
			$class = $app->extendClass(Log::class);
			return new $class($c, $app);
		};
	}

	public static function appAdminSetup(App $app)
	{
		$container = $app->container();

		$container->factory('logdigest.test', function($class, array $params, Container $c) use ($app)
		{
			$class = \XF::stringToClass($class, '\%s\Test\%s');
			$class = $app->extendClass($class);

			array_unshift($params, $app);

			return $c->createObject($class, $params, true);
		}, false);
	}

	/**
	 * Declares which of this add-on's settings are secrets. It has none: its options are a
	 * recipient address, a time zone and two sets of digest settings, and it reads no config.php key.
	 */
	public static function adminApiRedaction(array &$declarations)
	{
		$declarations['Hampel/LogDigest'] = [];
	}
}
