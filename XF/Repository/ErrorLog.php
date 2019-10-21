<?php namespace Hampel\LogDigest\XF\Repository;

use Hampel\LogDigest\Repository\DigestCache;

class ErrorLog extends XFCP_ErrorLog
{
	public function clearErrorLog()
	{
		parent::clearErrorLog();

		// reset the last updated cache for server errors so we get all new log entries

		/** @var DigestCache $cache */
		$cache = $this->repository('Hampel\LogDigest:DigestCache');
		$cache->reset('server-error');
	}
}
