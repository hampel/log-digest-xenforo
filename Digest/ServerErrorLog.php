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

	protected function prepareLog(array $log)
	{
		// plain text for the template to escape - dump_simple() leaves its output unescaped when
		// run from the CLI, which is where a cron-driven digest usually runs
		$log['requestStateDump'] = print_r(isset($log['request_state']) ? $log['request_state'] : [], true);

		return $log;
	}
}
