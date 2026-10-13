<?php namespace Hampel\LogDigest\Cli\Command;

use Hampel\LogDigest\Cli\RendersReport;
use Hampel\LogDigest\Service\HealthCheck;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Whether this forum can send the digests it is configured to: the recipient and time zone are
 * usable, the cron entry is running, each enabled log type's digests are going out, and a test
 * digest can be delivered.
 *
 * The test digest is a send - an email to the configured recipient, marked as a test - and it is
 * the only proof that delivery works, so it runs by default. --unattended skips it, for a deploy or
 * cron gate nobody is watching; every other check still runs. Nothing here moves a log type's last
 * digest time.
 *
 * Exit code: 1 if any check failed, 0 otherwise - a warning does not fail, so a gate can rely on it.
 * --strict makes a warning exit 2, for a monitor that wants to hear of one; it changes nothing
 * else. This is not the monitoring-plugin numbering: 1 always means failed.
 */
class Validate extends Command
{
	use RendersReport;

	protected function configure()
	{
		$this
			->setName('log-digest:validate')
			->setDescription('Check that digests can be sent. Exits 1 on a failure; --strict exits 2 on a warning.')
			->addOption('unattended', null, InputOption::VALUE_NONE,
				'Skip the test digest, which sends email - for a gate nobody is watching')
			->addOption('strict', null, InputOption::VALUE_NONE,
				'Exit 2 when there are warnings and no failures, instead of 0');
	}

	protected function errorLogPrefix(): string
	{
		return HealthCheck::ERROR_LOG_PREFIX;
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$this->report = $output;
		$health = $this->healthCheck();

		// facts about the configuration: these report under --unattended too, so they stay above it
		$this->checkSection('Configuration');
		$results = $this->probe('checks', function () use ($health)
		{
			return $health->run();
		});
		$this->rows(array_filter((array) $results, function ($result)
		{
			return strpos($result['check'], 'window_') !== 0;
		}));

		$this->checkSection('Digests');
		$this->rows(array_filter((array) $results, function ($result)
		{
			return strpos($result['check'], 'window_') === 0;
		}));

		$this->checkSection('Test digest');
		foreach (array_keys((array) \XF::app()->get('logDigest')->digestList()) AS $key)
		{
			if ($input->getOption('unattended'))
			{
				$this->checkSkip(str_replace('_', ' ', $key), 'skipped - --unattended');
				continue;
			}

			$this->rows([$this->probe($key, function () use ($health, $key)
			{
				return $health->sendTestDigest($key);
			})]);
		}

		$this->report->writeln('');
		if ($this->checksFailed())
		{
			$this->report->writeln('<error>Validation failed - digests are not being sent as configured</error>');
		}
		else
		{
			$this->report->writeln($this->checksWarned() ? '<comment>Validated, with warnings</comment>' : '<info>Validated</info>');
		}

		return $this->checkExitCode((bool) $input->getOption('strict'));
	}

	/**
	 * Renders rows from the health check. A row that is null is a check that threw inside probe(),
	 * which has already reported it.
	 */
	private function rows(array $results): void
	{
		foreach (array_filter($results) AS $result)
		{
			switch ($result['status'])
			{
				case HealthCheck::OK: $this->checkOk($result['label'], $result['detail']); break;
				case HealthCheck::WARNING: $this->checkWarn($result['label'], $result['detail']); break;
				case HealthCheck::FAIL: $this->checkFail($result['label'], $result['detail']); break;
				default: $this->checkSkip($result['label'], $result['detail']);
			}
		}
	}

	/**
	 * @return HealthCheck
	 */
	private function healthCheck()
	{
		return \XF::app()->service('Hampel\LogDigest:HealthCheck');
	}
}
