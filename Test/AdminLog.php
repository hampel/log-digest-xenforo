<?php namespace Hampel\LogDigest\Test;

class AdminLog extends AbstractTest
{
	protected function run()
	{
		return $this->send('Hampel\LogDigest:AdminLog');
	}
}
