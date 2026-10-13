<?php namespace Hampel\LogDigest\Cli\Command;

use Hampel\LogDigest\Cli\RendersReport;
use Hampel\LogDigest\Option\Email;
use Hampel\LogDigest\Option\TimeZone;
use Hampel\LogDigest\Service\HealthCheck;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * What this forum is configured to send, to whom, and when each log type was last sent. It reads
 * and prints; it writes nothing and sends nothing. log-digest:validate is the command that finds
 * out whether any of it works.
 */
class Config extends Command
{
	use RendersReport;

	protected function configure()
	{
		$this
			->setName('log-digest:config')
			->setDescription('Show the digest settings in use, and when each log type was last sent');
	}

	protected function errorLogPrefix(): string
	{
		return HealthCheck::ERROR_LOG_PREFIX;
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$this->report = $output;
		$app = \XF::app();

		$this->heading('Log Digest');
		$addOn = $app->addOnManager()->getById('Hampel/LogDigest');
		$this->detail('add-on', $addOn ? (string) $addOn->getJson()['version_string'] : '');
		$this->detail('XenForo', \XF::$version);

		$this->heading('Recipient');
		$this->detail('to', (string) Email::get() === '' ? '' : Email::get() . ' '
			. $this->muted(Email::isContactAddress() ? '(the board contact address)' : '(options)'));
		$this->detail('time zone', (string) TimeZone::get());

		$logDigest = $app->get('logDigest');
		foreach ($logDigest->digestList() AS $key => $class)
		{
			$this->heading(ucfirst(str_replace('_', ' ', $key)));

			// a setting that cannot be read is reported and the rest still print
			$this->probe($key, function () use ($logDigest, $class)
			{
				$digest = $logDigest->digest($class);
				$settings = $digest->getSettings();
				$lastChecked = (int) $digest->getLastChecked();

				$this->detail('enabled', $settings['enabled'] ? 'yes' : 'no');
				$this->detail('frequency', $settings['frequency'] . ' minutes');
				$this->detail('limit', $settings['limit'] > 0
					? $settings['limit'] . ' entries per digest' : 'every new entry, up to 200');
				$this->detail('collapse duplicates', $settings['deduplicate'] ? 'yes' : 'no');
				$this->detail('last digest', $lastChecked ? $this->date($lastChecked) : 'never, or reset');
				$this->detail('may send again', !$settings['enabled'] ? $this->muted('not enabled')
					: ($lastChecked ? 'from ' . $this->date($lastChecked + $digest->getFrequencySeconds()) : 'at the next cron run'));
			});
		}

		$this->heading('Schedule');
		$entry = $app->em()->find('XF:CronEntry', HealthCheck::CRON_ENTRY);
		$this->detail('cron entry', !$entry ? '' : ($entry->active ? 'active' : 'disabled'));
		$this->detail('next run', $entry && $entry->active ? $this->date($entry->next_run) : '');

		$this->heading('Logging');
		$this->detail('log channel', $app->container()->offsetExists('monolog')
			? 'logdigest' : $this->muted('none - no logging add-on is installed'));

		$this->report->writeln('');

		return 0;
	}

	private function date(int $timestamp): string
	{
		return gmdate('Y-m-d H:i', $timestamp) . ' UTC';
	}
}
