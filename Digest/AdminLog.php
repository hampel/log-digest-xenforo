<?php namespace Hampel\LogDigest\Digest;

class AdminLog extends AbstractDigest
{
	protected function getOptionId()
	{
		return 'logdigestAdminLog';
	}

	public function getLogName()
	{
		return \XF::phrase('admin_log');
	}

	protected function getEntityId()
	{
		return 'XF:AdminLog';
	}

	protected function getTimestampColumn()
	{
		return 'request_date';
	}

	protected function getTemplate()
	{
		return 'logdigest_admin_log';
	}

	protected function getComparisonFields()
	{
		return ['user_id', 'ip_address', 'request_url', 'request_data'];
	}

	protected function getRoute()
	{
		return 'logs/admin';
	}
}
