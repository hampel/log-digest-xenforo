<?php namespace Tests\Unit;

use Hampel\LogDigest\Cli\Command\Config;
use Hampel\LogDigest\Cli\Command\Validate;
use Hampel\LogDigest\Service\HealthCheck;
use Symfony\Component\Console\Command\Command;
use Tests\TestCase;

/**
 * XenForo loads every add-on's command classes to list them, so one that cannot load stops cmd.php
 * for the whole forum. Every file under Cli/Command is checked, so a new command is covered here
 * without being named - and has to be added to the list below.
 */
class CommandClassesTest extends TestCase
{
	public function test_every_command_class_is_one_xenforo_can_load()
	{
		$classes = $this->assertConsoleCommandsLoad();
		sort($classes);

		$this->assertSame([Config::class, Validate::class], $classes);
	}

	public function test_the_commands_have_the_names_every_add_on_uses()
	{
		$this->assertSame('log-digest:config', (new Config())->getName());
		$this->assertSame('log-digest:validate', (new Validate())->getName());
	}

	public function test_no_command_depends_on_a_base_class_missing_from_xenforo_2_2()
	{
		foreach ($this->assertConsoleCommandsLoad() AS $class)
		{
			$this->assertNotContains(
				'XF\Cli\Command\AbstractCommand',
				array_values(class_parents($class)),
				"{$class} extends XF\\Cli\\Command\\AbstractCommand, which XenForo 2.2 does not have"
			);
		}
	}

	public function test_no_command_redefines_run()
	{
		foreach ($this->assertConsoleCommandsLoad() AS $class)
		{
			$this->assertSame(
				Command::class,
				(new \ReflectionMethod($class, 'run'))->getDeclaringClass()->getName(),
				"{$class} defines run(), which collides with Symfony's Command::run()"
			);
		}
	}

	/**
	 * XenForo 2.2's console knows only the eight basic colours and throws on grey, so grey goes
	 * through the trait's muted(), which falls back. The trait itself is the one place that names it.
	 */
	public function test_nothing_writes_a_grey_tag_directly()
	{
		$offenders = [];
		foreach ($this->sourceFiles() AS $file)
		{
			if (basename($file) != 'RendersReport.php' && preg_match('/fg=gr[ae]y/', file_get_contents($file)))
			{
				$offenders[] = $file;
			}
		}

		$this->assertSame([], $offenders);
	}

	/**
	 * Something that mirrors the server error log skips an add-on's rows by its prefix; a row
	 * without one is mirrored and alerted on.
	 */
	public function test_every_server_error_log_row_begins_with_the_add_on_prefix()
	{
		$this->assertSame('LogDigest: ', HealthCheck::ERROR_LOG_PREFIX);

		$calls = [];
		foreach ($this->sourceFiles() AS $file)
		{
			foreach (file($file) AS $number => $line)
			{
				if (preg_match('/\\\\XF::log(Exception|Error)\(/', $line))
				{
					$calls[] = [basename($file) . ':' . ($number + 1), $line];
				}
			}
		}

		$this->assertNotEmpty($calls, 'no server error log calls were found to check');

		$unprefixed = [];
		foreach ($calls AS $call)
		{
			if (!preg_match('/ERROR_LOG_PREFIX|errorLogPrefix\(\)|\'LogDigest: \'/', $call[1]))
			{
				$unprefixed[] = $call[0];
			}
		}

		$this->assertSame([], $unprefixed);
	}

	/**
	 * @return string[] the add-on's own PHP files
	 */
	private function sourceFiles() : array
	{
		$root = dirname(__DIR__, 2);
		$files = [];

		$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
		foreach ($iterator AS $file)
		{
			$path = $file->getPathname();
			if (substr($path, -4) == '.php' && !preg_match('#/(vendor|tests|_output|_build|_releases)/#', $path))
			{
				$files[] = $path;
			}
		}

		return $files;
	}
}
