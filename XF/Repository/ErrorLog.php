<?php namespace Hampel\LogDigest\XF\Repository;

use Hampel\LogDigest\Cache\DigestCache;

class ErrorLog extends XFCP_ErrorLog
{
	public function clearErrorLog()
	{
		parent::clearErrorLog();

		// reset the last updated cache for server errors so we get all new log entries
		DigestCache::reset('server-error');
	}
}
