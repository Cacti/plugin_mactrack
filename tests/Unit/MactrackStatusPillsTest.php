<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for the interface/dot1x status-pill helpers added to
 * lib/mactrack_functions.php: the show-issues gate, the per-interface active
 * status computation, the graph-existence lookup, and the interface and
 * dot1x pill markup. These pin the behaviour the Status pills and multi-select
 * Status filter rely on so a future legend or status-map change surfaces here.
 */

if (!function_exists('cacti_version_compare')) {
	function cacti_version_compare($v1, $v2, $op) {
		return version_compare($v1, $v2, $op);
	}
}

if (!defined('CACTI_VERSION')) {
	define('CACTI_VERSION', '1.2.32');
}

final class MactrackStatusPillsTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('lib/mactrack_functions.php');
	}

	/**
	 * @return void
	 */
	protected function setUp(): void {
		$GLOBALS['__test_next_returns']    = array();
		$GLOBALS['__test_matched_returns'] = array();
		$GLOBALS['__test_db_calls']        = array();
	}

	/**
	 * @param array $over
	 *
	 * @return array
	 */
	private function stat(array $over): array {
		return array_merge([
			'ifOperStatus'        => 1,
			'ifAlias'             => 'uplink',
			'int_errors_present'  => 0,
			'int_discards_present' => 0,
			'has_graphs'          => 1,
		], $over);
	}

	/**
	 * @return void
	 */
	public function test_show_issues_true_on_supported_core(): void {
		$this->assertTrue(mactrack_interfaces_show_issues());
	}

	/**
	 * @return void
	 */
	public function test_legend_exposes_the_six_known_classes(): void {
		$this->assertSame(
			['int_up', 'int_up_wo_alias', 'int_errors', 'int_discards', 'int_no_graph', 'int_down'],
			array_keys(mactrack_interface_status_legend())
		);
	}

	/**
	 * @return void
	 */
	public function test_has_graphs_false_when_host_empty(): void {
		$this->assertFalse(mactrack_interface_has_graphs(0, 5));
	}

	/**
	 * @return void
	 */
	public function test_has_graphs_true_when_db_reports_a_count(): void {
		mactrack_test_queue_return('db_fetch_cell_prepared', 3);

		$this->assertTrue(mactrack_interface_has_graphs(10, 5));
	}

	/**
	 * @return void
	 */
	public function test_has_graphs_false_when_db_reports_zero(): void {
		mactrack_test_queue_return('db_fetch_cell_prepared', 0);

		$this->assertFalse(mactrack_interface_has_graphs(10, 5));
	}

	/**
	 * @return void
	 */
	public function test_int_statuses_up_with_alias_and_graphs(): void {
		$active = mactrack_int_statuses($this->stat([]));

		$this->assertArrayHasKey('int_up', $active);
		$this->assertArrayNotHasKey('int_up_wo_alias', $active);
		$this->assertArrayNotHasKey('int_errors', $active);
		$this->assertArrayNotHasKey('int_discards', $active);
		$this->assertArrayNotHasKey('int_no_graph', $active);
		$this->assertArrayNotHasKey('int_down', $active);
	}

	/**
	 * @return void
	 */
	public function test_int_statuses_up_without_alias_with_errors_discards_and_no_graphs(): void {
		$active = mactrack_int_statuses($this->stat([
			'ifAlias'              => '',
			'int_errors_present'   => 1,
			'int_discards_present' => 1,
			'has_graphs'           => 0,
		]));

		$this->assertArrayHasKey('int_up', $active);
		$this->assertArrayHasKey('int_up_wo_alias', $active);
		$this->assertArrayHasKey('int_errors', $active);
		$this->assertArrayHasKey('int_discards', $active);
		$this->assertArrayHasKey('int_no_graph', $active);
	}

	/**
	 * @return void
	 */
	public function test_int_statuses_down(): void {
		$active = mactrack_int_statuses($this->stat(['ifOperStatus' => 2]));

		$this->assertArrayHasKey('int_down', $active);
		$this->assertArrayNotHasKey('int_up', $active);
	}

	/**
	 * @return void
	 */
	public function test_int_statuses_falls_back_to_db_when_has_graphs_absent(): void {
		mactrack_test_queue_return('db_fetch_cell_prepared', 0);

		$active = mactrack_int_statuses([
			'ifOperStatus'         => 1,
			'ifAlias'              => 'uplink',
			'int_errors_present'   => 0,
			'int_discards_present' => 0,
			'host_id'              => 10,
			'ifIndex'              => 5,
		]);

		$this->assertArrayHasKey('int_no_graph', $active);
	}

	/**
	 * @return void
	 */
	public function test_interface_pills_render_every_class_and_mark_active(): void {
		$html = mactrack_interface_status_pills($this->stat([]));

		$this->assertStringContainsString('class="mactrackPills"', $html);
		$this->assertStringContainsString('data-status="int_up"', $html);
		$this->assertStringContainsString('mactrackPillActive', $html);

		foreach (array_keys(mactrack_interface_status_legend()) as $class) {
			$this->assertStringContainsString($class, $html);
		}
	}

	/**
	 * @return void
	 */
	public function test_dot1x_pill_uses_row_class_and_status_hook(): void {
		$html = mactrack_dot1x_status_pill(['status' => 2]);

		$this->assertStringContainsString('mactrackPill', $html);
		$this->assertStringContainsString('data-dot1x-status="2"', $html);
		$this->assertStringContainsString('dot1x_running', $html);
	}

	/**
	 * @return void
	 */
	public function test_dot1x_pill_unknown_status_uses_default_class(): void {
		$html = mactrack_dot1x_status_pill(['status' => 99]);

		$this->assertStringContainsString('dot1x_authn_success', $html);
	}

	/**
	 * @return void
	 */
	public function test_format_dot1x_row_emits_single_status_pill_cell(): void {
		mactrack_test_queue_return('db_fetch_row_prepared', ['host_id' => 0, 'disabled' => '']);
		set_request_var('scan_date', '1');

		$html = mactrack_format_dot1x_row([
			'scan_date'     => '2026-01-01 00:00:00',
			'max_scan_date' => '2026-01-01 00:00:00',
			'device_id'     => 10,
			'port_number'   => '1',
			'device_name'   => 'switch-a',
			'hostname'      => 'switch-a.example.net',
			'username'      => 'jdoe',
			'status'        => 2,
			'ip_address'    => '10.0.0.5',
			'mac_address'   => '001122334455',
			'ifName'        => 'Gi1/0/1',
			'domain'        => 2,
		]);

		$this->assertStringContainsString('data-dot1x-status="2"', $html);
		$this->assertSame(1, substr_count($html, 'data-dot1x-status='));
	}
}
