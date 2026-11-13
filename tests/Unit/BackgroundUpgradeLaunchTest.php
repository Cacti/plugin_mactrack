<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/**
 * Covers launching the background database-upgrade CLI. exec_background()
 * returns nothing and does nothing for a missing binary, so a launch that
 * could not start used to leave the "upgrading in the background" notice up
 * with nothing running and nothing logged.
 */
final class BackgroundUpgradeLaunchTest extends TestCase {
	private string $base = '';

	private string $saved_base = '';

	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('includes/database.php');
		self::loadPluginSource('setup.php');
	}

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->base = sys_get_temp_dir() . '/mactrack-launch-' . getmypid();

		if (!is_dir($this->base . '/plugins/mactrack/cli')) {
			mkdir($this->base . '/plugins/mactrack/cli', 0700, true);
		}

		file_put_contents($this->script(), '<?php');

		$this->saved_base                 = $GLOBALS['config']['base_path'];
		$GLOBALS['config']['base_path']   = $this->base;
		$GLOBALS['__test_cacti_log']      = [];

		// No processes table, so no upgrade is registered as running.
		mactrack_test_queue_return('db_table_exists', false);
	}

	/**
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['config']['base_path'] = $this->saved_base;

		if (is_file($this->script())) {
			unlink($this->script());
		}

		rmdir($this->base . '/plugins/mactrack/cli');
		rmdir($this->base . '/plugins/mactrack');
		rmdir($this->base . '/plugins');
		rmdir($this->base);

		parent::tearDown();
	}

	/**
	 * @return string
	 */
	private function script(): string {
		return $this->base . '/plugins/mactrack/cli/upgrade_database.php';
	}

	/**
	 * @param string $fn Stub function name.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function calls(string $fn): array {
		return array_values(array_filter($GLOBALS['__test_db_calls'], fn ($call) => $call['fn'] === $fn));
	}

	/**
	 * @return void
	 */
	public function testNoProblemWhenTheBinaryAndScriptExist(): void {
		$this->assertSame('', mactrack_background_upgrade_launch_problem(PHP_BINARY, $this->script()));
	}

	/**
	 * @return void
	 */
	public function testAnUnsetBinaryIsAProblem(): void {
		$this->assertStringContainsString('path_php_binary', mactrack_background_upgrade_launch_problem('', $this->script()));
	}

	/**
	 * @return void
	 */
	public function testAMissingBinaryIsAProblem(): void {
		$missing = $this->base . '/no-such-php';

		$this->assertStringContainsString($missing, mactrack_background_upgrade_launch_problem($missing, $this->script()));
	}

	/**
	 * @return void
	 */
	public function testAMissingScriptIsAProblem(): void {
		unlink($this->script());

		$this->assertStringContainsString('upgrade_database.php', mactrack_background_upgrade_launch_problem(PHP_BINARY, $this->script()));
	}

	/**
	 * A function in disable_functions is not defined, like this missing name.
	 *
	 * @return void
	 */
	public function testADisabledSpawnFunctionIsAProblem(): void {
		$this->assertStringContainsString('mactrack_no_such_spawn()', mactrack_background_upgrade_launch_problem(PHP_BINARY, $this->script(), 'mactrack_no_such_spawn'));
	}

	/**
	 * @return void
	 */
	public function testALaunchableUpgradeStartsAndRaisesTheRunningNotice(): void {
		set_config_option('path_php_binary', PHP_BINARY);

		mactrack_launch_background_upgrade();

		$this->assertCount(1, $this->calls('exec_background'));
		$this->assertSame(PHP_BINARY, $this->calls('exec_background')[0]['sql']);
		$this->assertSame([], $GLOBALS['__test_cacti_log']);

		$messages = $this->calls('raise_message');
		$this->assertCount(1, $messages);
		$this->assertSame(MESSAGE_LEVEL_WARN, $messages[0]['params'][1]);
		$this->assertSame('on', $GLOBALS['__test_config_options']['mt_db_upgrade_notified']);
	}

	/**
	 * @return void
	 */
	public function testAnUnlaunchableUpgradeLogsAndGivesTheManualCommand(): void {
		set_config_option('path_php_binary', '');

		mactrack_launch_background_upgrade();

		$this->assertSame([], $this->calls('exec_background'));

		$this->assertCount(1, $GLOBALS['__test_cacti_log']);
		$this->assertStringContainsString('ERROR', $GLOBALS['__test_cacti_log'][0]);
		$this->assertStringContainsString('path_php_binary', $GLOBALS['__test_cacti_log'][0]);
		$this->assertStringContainsString($this->script(), $GLOBALS['__test_cacti_log'][0]);

		$messages = $this->calls('raise_message');
		$this->assertCount(1, $messages);
		$this->assertSame(MESSAGE_LEVEL_ERROR, $messages[0]['params'][1]);
		$this->assertStringNotContainsString('upgrading its database in the background', $messages[0]['params'][0]);
		$this->assertSame('on', $GLOBALS['__test_config_options']['mt_db_upgrade_notified']);
	}

	/**
	 * A failed launch is reported once, not on every page load.
	 *
	 * @return void
	 */
	public function testAnUnlaunchableUpgradeIsNotReportedAgainOnceNotified(): void {
		set_config_option('path_php_binary', '');
		set_config_option('mt_db_upgrade_notified', 'on');

		mactrack_launch_background_upgrade();

		$this->assertSame([], $this->calls('exec_background'));
		$this->assertSame([], $GLOBALS['__test_cacti_log']);
		$this->assertSame([], $this->calls('raise_message'));
	}
}
