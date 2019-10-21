<?php namespace Hampel\LogDigest\Cron;

use Hampel\LogDigest\Option\Email;
use Hampel\LogDigest\Option\ServerError;
use Hampel\LogDigest\Option\TimeZone;
use Hampel\LogDigest\Repository\Log;
use Hampel\LogDigest\Service\LogSender\ServerError as ServerErrorService;

class SendLogs
{
	public static function serverError()
	{
		if (!ServerError::isEnabled()) return; // stop if sending server error logs is not enabled

		/** @var Log $repo */
		$repo = \XF::repository('Hampel\LogDigest:Log');

		$logs = $repo->getServerErrorLogs(ServerError::frequencySeconds());

		if ($logs)
		{
			/** @var ServerErrorService $sender */
			$sender = \XF::service('Hampel\LogDigest:LogSender\ServerError', $logs, TimeZone::get());
			$params = $sender->filterLogData(ServerError::deduplicate(), ServerError::limit());

			if (!empty($params))
			{
				if ($sender->send(Email::get(), 'server_error', $params))
				{
					$repo->updateLastSent('XF:ErrorLog', $sender->getLastId());
				}
			}
		}
	}
}
