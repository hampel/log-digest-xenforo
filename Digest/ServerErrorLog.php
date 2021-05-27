<?php namespace Hampel\LogDigest\Digest;

class ServerErrorLog extends AbstractDigest
{
	protected function getOptionId()
	{
		return 'logdigestServerError';
	}

	public function getLogName()
	{
		return \XF::phrase('server_error_log');
	}

	protected function getEntityId()
	{
		return 'XF:ErrorLog';
	}

	protected function getTimestampColumn()
	{
		return 'exception_date';
	}

	protected function getTemplate()
	{
		return 'logdigest_server_error';
	}

	protected function getComparisonFields()
	{
		return ['exception_type', 'message', 'filename', 'line', 'trace_string'];
	}

	protected function getRoute()
	{
		return 'logs/server-errors';
	}
}
