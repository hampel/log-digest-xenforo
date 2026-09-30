<?php namespace Tests\Feature;

use Tests\TestCase;
use XF\Mvc\Entity\ArrayCollection;

class ToolsControllerTest extends TestCase
{
	protected function setUp() : void
	{
		parent::setUp();

		$this->fakesErrors();
		$this->fakesSimpleCache();
	}

	public static function toolRoutes() : array
	{
		return [
			'test' => ['tools/test-logdigest', 'logdigest_tools_test_logdigest'],
			'reset' => ['tools/reset-logdigest', 'logdigest_tools_reset_logdigest'],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('toolRoutes')]
	public function test_a_tool_needs_the_option_admin_permission($route, $template)
	{
		$this->actingAsMember(['is_admin' => true]);

		$reply = $this->dispatch($route, 'admin');

		$this->assertReplyIsError($reply, 403);
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('toolRoutes')]
	public function test_a_tool_renders_for_an_admin_with_the_permission($route, $template)
	{
		$this->actingAsOptionAdmin();

		$reply = $this->dispatch($route, 'admin');

		$this->assertReplyTemplate($reply, $template);

		$html = $this->renderReply($reply);
		$this->assertSee($html, 'Server error log');
		$this->assertSee($html, 'Admin log');
		$this->assertNoUnresolvedPhrases($html, 'logdigest_');
		$this->assertNoTemplateErrors();
	}

	public function test_the_test_tool_defaults_to_the_visitors_email_address()
	{
		$this->actingAsOptionAdmin(['email' => 'visitor@example.com']);

		$reply = $this->dispatch('tools/test-logdigest', 'admin');

		$this->assertSame(
			['email' => 'visitor@example.com', 'generate' => false, 'limit' => 10],
			$this->replyParam($reply, 'options')
		);
	}

	public function test_the_test_tool_sends_a_test_digest()
	{
		$this->actingAsOptionAdmin();
		$this->fakesMail();
		$this->mockFinder('XF:ErrorLog', function ($mock) {
			$mock->allows('where')->andReturnSelf();
			$mock->allows('order')->andReturnSelf();
			$mock->allows('limit')->andReturnSelf();
			$mock->expects('fetch')->andReturns(new ArrayCollection([$this->errorLog()]));
		});

		$reply = $this->callAction('XF:Tools', 'test-logdigest', 'admin', [
			'test' => 'Hampel\LogDigest:ServerErrorLog',
			'options' => ['email' => 'test@example.com', 'limit' => 5],
		]);

		$this->assertTrue($this->replyParam($reply, 'results'));
		$this->assertMailSent(function ($mail) {
			return $mail->getTo()[0]->getAddress() == 'test@example.com'
				&& strpos($mail->getSubject(), '[Test]') !== false;
		});
		// a test send reads all logs and must not move the real digest's window
		$this->assertSimpleCacheHasNot('Hampel/LogDigest', 'XF:ErrorLog');
	}

	public function test_the_test_tool_refuses_an_invalid_email_address()
	{
		$this->actingAsOptionAdmin();
		$this->fakesMail();

		$reply = $this->callAction('XF:Tools', 'test-logdigest', 'admin', [
			'test' => 'Hampel\LogDigest:ServerErrorLog',
			'options' => ['email' => 'not an email', 'limit' => 5],
		]);

		$this->assertFalse($this->replyParam($reply, 'results'));
		$this->assertNoMailSent();
	}

	public function test_the_test_tool_reports_a_test_that_does_not_exist()
	{
		$this->actingAsOptionAdmin();

		$reply = $this->callAction('XF:Tools', 'test-logdigest', 'admin', [
			'test' => 'Hampel\LogDigest:NoSuchLog',
			'options' => ['email' => 'test@example.com'],
		]);

		$this->assertReplyIsError($reply, 500);
		$this->assertContains('This test could not be run', array_map('strval', $this->replyErrors($reply)));
	}

	public function test_the_reset_tool_resets_only_the_chosen_log_type()
	{
		$this->actingAsOptionAdmin();
		$repo = $this->app()->repository('Hampel\LogDigest:DigestCache');
		$repo->setLastChecked('XF:ErrorLog', 1000);
		$repo->setLastChecked('XF:AdminLog', 2000);

		$reply = $this->callAction('XF:Tools', 'reset-logdigest', 'admin', [
			'options' => ['server_error_log' => 1, 'admin_log' => 0],
		]);

		$this->assertReplyTemplate($reply, 'logdigest_tools_reset_logdigest');
		$this->assertSimpleCacheEqual(0, 'Hampel/LogDigest', 'XF:ErrorLog');
		$this->assertSimpleCacheEqual(2000, 'Hampel/LogDigest', 'XF:AdminLog');
	}

	protected function actingAsOptionAdmin(array $values = [])
	{
		$admin = $this->actingAsMember(['is_admin' => true] + $values);
		$this->setVisitorAdminPermissions($admin, ['option' => true]);

		return $admin;
	}

	protected function errorLog()
	{
		$log = $this->makeEntity('XF:ErrorLog', [
			'exception_date' => \XF::$time - 60,
			'user_id' => 0,
			'ip_address' => '',
			'exception_type' => 'LogicException',
			'message' => 'Something broke',
			'filename' => 'src/addons/Vendor/AddOn/Thing.php',
			'line' => 42,
			'trace_string' => '#0 {main}',
			'request_state' => [],
		]);
		$log->setTrusted('error_id', 1);

		return $log;
	}
}
