<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for three regressions seen after upgrading:
 *
 * - The Dashboard returned "401 Permission Denied" because its realm file was
 *   only written on a version change. mactrack_realms_are_stale() lets the
 *   upgrade check heal an install whose realm file lists lag the code.
 * - The Graphs tab fataled with "Call to undefined function
 *   graph_view_has_untemplated_graphs()" because Cacti 1.2.x defines that
 *   helper in graph_view.php, which plugin pages never load.
 * - The MacTrack tab script rewrote whichever main tab was selected instead of
 *   only its own, which could repoint the Console tab at a MacTrack page.
 */

final class MactrackRealmAndGraphViewerTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('lib/mactrack_functions.php');
		self::loadPluginSource('setup.php');
	}

	/**
	 * Runs mactrack_check_upgrade() from the Console page against a throwaway
	 * base_path whose INFO reports version 9.9.9 and whose include files are
	 * empty, so nothing outside the stubs can run.
	 *
	 * @param array<string, mixed> $plugin_config The plugin_config row to report.
	 * @param array<int, array<string, string>> $registered The plugin_realms rows to report.
	 *
	 * @return void
	 */
	private function runCheckUpgrade(array $plugin_config, array $registered): void {
		mactrack_test_queue_return('get_current_page', 'index.php');
		mactrack_test_queue_return('db_fetch_row', $plugin_config);
		mactrack_test_queue_return('db_fetch_assoc_prepared', $registered);

		$restore = $GLOBALS['config']['base_path'];
		$base    = sys_get_temp_dir() . '/mactrack-realm-' . uniqid();

		mkdir($base . '/plugins/mactrack/includes', 0777, true);
		mkdir($base . '/plugins/mactrack/lib', 0777, true);
		file_put_contents($base . '/plugins/mactrack/INFO', "[info]\nversion = 9.9.9\nname = mactrack\nlongname = MacTrack\nauthor = x\nhomepage = x\n");
		file_put_contents($base . '/plugins/mactrack/includes/database.php', "<?php\n");
		file_put_contents($base . '/plugins/mactrack/lib/mactrack_functions.php', "<?php\n");
		$GLOBALS['config']['base_path'] = $base;

		try {
			mactrack_check_upgrade();
		} finally {
			$GLOBALS['config']['base_path'] = $restore;
		}
	}

	/**
	 * @param string $fn The recorded stub function name.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function callsTo(string $fn): array {
		return array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) use ($fn) {
			return $call['fn'] === $fn;
		}));
	}

	/**
	 * @return array<int, array<string, string>>
	 */
	private function registeredRows(): array {
		$rows = [];

		foreach (mactrack_realm_definitions() as $files) {
			$rows[] = ['file' => $files];
		}

		return $rows;
	}

	/**
	 * @return void
	 */
	public function test_viewer_realm_protects_the_dashboard(): void {
		$realms = mactrack_realm_definitions();

		$this->assertArrayHasKey('Mactrack Viewer', $realms);
		$this->assertStringContainsString('mactrack_view_dashboard.php', $realms['Mactrack Viewer']);
		$this->assertStringContainsString('mactrack_view_graphs.php', $realms['Mactrack Viewer']);
		$this->assertStringContainsString('mactrack_devices.php', $realms['Mactrack Administrator']);
	}

	/**
	 * @return void
	 */
	public function test_install_registers_every_realm(): void {
		plugin_mactrack_install(false);

		$registered = [];

		foreach ($GLOBALS['__test_db_calls'] as $call) {
			if ($call['fn'] === 'api_plugin_register_realm') {
				$registered[$call['sql']] = $call['params'][0];
			}
		}

		$this->assertSame(mactrack_realm_definitions(), $registered);
	}

	/**
	 * @return void
	 */
	public function test_realm_helper_name_satisfies_the_cacti_entrypoint_check(): void {
		$this->assertMatchesRegularExpression('/(install|upgrade|setup)/i', (new ReflectionFunction('mactrack_install_realms'))->getName());
	}

	/**
	 * @return void
	 */
	public function test_install_grants_new_realms(): void {
		plugin_mactrack_install(false);

		foreach ($this->callsTo('api_plugin_register_realm') as $call) {
			$this->assertTrue($call['params'][1]);
		}
	}

	/**
	 * @return void
	 */
	public function test_same_version_repair_registers_realms_without_granting_access(): void {
		$this->runCheckUpgrade(['version' => '9.9.9', 'status' => 1], []);

		$realms = $this->callsTo('api_plugin_register_realm');

		$this->assertCount(count(mactrack_realm_definitions()), $realms);

		foreach ($realms as $call) {
			$this->assertFalse($call['params'][1]);
		}
	}

	/**
	 * @return void
	 */
	public function test_same_version_repair_skips_the_full_upgrade(): void {
		$this->runCheckUpgrade(['version' => '9.9.9', 'status' => 1], []);

		$this->assertSame([], $this->callsTo('api_plugin_register_hook'));

		foreach ($this->callsTo('db_execute_prepared') as $call) {
			$this->assertStringNotContainsString('UPDATE plugin_config', $call['sql']);
		}
	}

	/**
	 * @return void
	 */
	public function test_same_version_with_current_realms_registers_nothing(): void {
		$this->runCheckUpgrade(['version' => '9.9.9', 'status' => 1], $this->registeredRows());

		$this->assertSame([], $this->callsTo('api_plugin_register_realm'));
	}

	/**
	 * @return void
	 */
	public function test_same_version_repair_ignores_a_disabled_plugin(): void {
		$this->runCheckUpgrade(['version' => '9.9.9', 'status' => 0], []);

		$this->assertSame([], $this->callsTo('api_plugin_register_realm'));
	}

	/**
	 * @return void
	 */
	public function test_realms_are_stale_when_the_dashboard_file_is_unregistered(): void {
		$rows                = $this->registeredRows();
		$rows[0]['file']     = str_replace('mactrack_view_dashboard.php,', '', $rows[0]['file']);

		mactrack_test_queue_return('db_fetch_assoc_prepared', $rows);

		$this->assertTrue(mactrack_realms_are_stale());
	}

	/**
	 * @return void
	 */
	public function test_realms_are_current_when_every_file_is_registered(): void {
		mactrack_test_queue_return('db_fetch_assoc_prepared', $this->registeredRows());

		$this->assertFalse(mactrack_realms_are_stale());
	}

	/**
	 * @return void
	 */
	public function test_realms_are_stale_when_none_are_registered(): void {
		mactrack_test_queue_return('db_fetch_assoc_prepared', []);

		$this->assertTrue(mactrack_realms_are_stale());
	}

	/**
	 * @return void
	 */
	public function test_untemplated_graph_helper_reports_true_when_a_graph_exists(): void {
		$GLOBALS['__test_graph_total'] = 3;

		$this->assertTrue(graph_view_has_untemplated_graphs('gt.id IN (1,2)'));
		$this->assertSame(
			['(gl.graph_template_id = 0) AND gt.id IN (1,2)', '', '1'],
			$GLOBALS['__test_graph_calls'][0]
		);
	}

	/**
	 * @return void
	 */
	public function test_untemplated_graph_helper_reports_false_without_a_restriction(): void {
		$this->assertFalse(graph_view_has_untemplated_graphs());
		$this->assertSame(['(gl.graph_template_id = 0)', '', '1'], $GLOBALS['__test_graph_calls'][0]);
	}

	/**
	 * @return void
	 */
	public function test_tab_script_only_repoints_the_mactrack_tab(): void {
		ob_start();
		mactrack_tabs();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString('plugins/mactrack/mactrack_view_', $html);
		$this->assertStringNotContainsString('.maintabs a.selected', $html);
	}
}
