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

if (!function_exists('get_allowed_graphs')) {
	function get_allowed_graphs($sql_where = '', $order_by = '', $limit = '', &$total_rows = 0) {
		$GLOBALS['__test_graph_calls'][] = [$sql_where, $order_by, $limit];
		$total_rows = $GLOBALS['__test_graph_total'] ?? 0;

		return [];
	}
}

final class MactrackRealmAndGraphViewerTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('lib/mactrack_functions.php');
		self::loadPluginSource('setup.php');
	}

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['__test_graph_calls'] = [];
		$GLOBALS['__test_graph_total'] = 0;
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
