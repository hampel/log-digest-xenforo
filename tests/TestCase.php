<?php namespace Tests;

use Hampel\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /*
     * Set $rootDir to '../../../..' if you use a vendor in your addon id (ie <Vendor/AddonId>)
     * Otherwise, set this to '../../..' for no vendor
     *
     * No trailing slash!
     */
    protected $rootDir = '../../../..';

    /*
     * Load only this add-on: its listeners, class extensions and vendor tree. Other add-ons'
     * PHPUnit copies would otherwise collide with this one's.
     */
    protected $addonsToLoad = ['Hampel/LogDigest'];

	protected function getMockData($file)
	{
		return file_get_contents(__DIR__ . '/mock/' . $file);
	}
}
