<?php namespace Hampel\LogDigest\Repository;

use XF\Mvc\Entity\Repository;

class Log extends Repository
{
	/**
	 * @param $frequency - seconds between checks
	 *
	 * @return void|\XF\Mvc\Entity\ArrayCollection
	 */
	public function getServerErrorLogs($frequency)
	{
		$entity = 'XF:ErrorLog';
		$cache = $this->getCacheRepo();

		$lastChecked = $cache->getLastChecked($entity);

		// if it's been less than $frequency seconds since we last checked, then just skip and wait for the next
		// check cycle
		if ($lastChecked > 0 && ($lastChecked + $frequency > \XF::$time)) return;

		$logs = $this->getLogs($entity, 'error_id');

		if ($logs->count() == 0)
		{
			// update the last checked time so we don't keep retrying
			$cache->setLastChecked($entity, \XF::$time);
			return;
		}

		return $logs;
	}

	public function getLogs($entity, $key, $id = null)
	{
		return $this->app()->finder($entity)
		           ->where($key, '>', $id ?? $this->getCacheRepo()->getLastId($entity))
		           ->order($key, 'ASC')
		           ->fetch();
	}

	public function updateLastSent($type, $lastid)
	{
		if (!$lastid) return;

		$cache = $this->getCacheRepo();

		$cache->setValue($type, ['checked' => \XF::$time, 'id' => $lastid]);
	}

	/**
	 * @return DigestCache
	 */
	protected function getCacheRepo()
	{
		return $this->repository('Hampel\LogDigest:DigestCache');
	}
}
