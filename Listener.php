<?php namespace Hampel\LogDigest;

use XF\App;
use XF\Container;

class Listener
{
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
}