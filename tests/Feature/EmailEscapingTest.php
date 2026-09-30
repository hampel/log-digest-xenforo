<?php namespace Tests\Feature;

use Tests\TestCase;
use XF\Mvc\Entity\ArrayCollection;

/**
 * Log entries carry content a visitor controls - usernames, messages, request URLs and data - and
 * the digest renders them into HTML email. Every one of them must arrive escaped, including when
 * the digest is sent from the CLI, which is where these tests run.
 */
class EmailEscapingTest extends TestCase
{
	protected function setUp() : void
	{
		parent::setUp();

		$this->fakesErrors();
		$this->setOptions(['boardTitle' => 'Board <b id="board">', 'logdigestTimeZone' => 'UTC']);
	}

	public function test_the_server_error_digest_escapes_log_content()
	{
		$log = $this->makeEntity('XF:ErrorLog', [
			'exception_date' => \XF::$time,
			'user_id' => 1,
			'ip_address' => '',
			'exception_type' => 'LogicException<b id="type">',
			'message' => 'Broke <b id="message">',
			'filename' => 'src/<b id="file">.php',
			'line' => 42,
			'trace_string' => '#0 <b id="trace">',
			'request_state' => ['url' => '/<b id="state">'],
		]);
		$log->setTrusted('error_id', 1);
		$log->hydrateRelation('User', $this->hostileUser());

		$html = $this->render('ServerErrorLog', 'logdigest_server_error', 'logs/server-errors', $log);

		$this->assertSee($html, 'Broke');
		$this->assertEscaped($html, ['board', 'user', 'type', 'message', 'file', 'trace', 'state']);
	}

	public function test_the_admin_log_digest_escapes_log_content()
	{
		$log = $this->makeEntity('XF:AdminLog', [
			'request_date' => \XF::$time,
			'user_id' => 1,
			'ip_address' => '',
			'request_url' => 'admin.php?<b id="url">',
			'request_data' => ['field' => '<b id="data">'],
		]);
		$log->setTrusted('admin_log_id', 1);
		$log->hydrateRelation('User', $this->hostileUser());

		$html = $this->render('AdminLog', 'logdigest_admin_log', 'logs/admin', $log);

		$this->assertSee($html, 'admin.php?');
		$this->assertEscaped($html, ['board', 'user', 'url']);
	}

	protected function render($digestClass, $template, $route, $log)
	{
		$digest = $this->app()->get('logDigest')->digest('Hampel\LogDigest:' . $digestClass);

		return $this->renderTemplate("email:$template", [
			'type' => 'Log',
			'route' => $route,
			'logs' => $digest->prepareLogs(new ArrayCollection([$log])),
			'duplicateCount' => 0,
			'test' => false,
		]);
	}

	protected function hostileUser()
	{
		$user = $this->makeEntity('XF:User', ['username' => 'Mallory']);
		$user->setTrusted('user_id', 1);
		$user->setTrusted('username', 'Mallory <b id="user">');

		return $user;
	}

	protected function assertEscaped($html, array $ids)
	{
		foreach ($ids as $id)
		{
			$this->assertStringNotContainsString("<b id=\"$id\">", $html, "unescaped: $id");
			$this->assertMatchesRegularExpression("/&lt;b id=(&quot;|&#039;|\"){$id}/", $html, "missing entirely: $id");
		}
	}
}
