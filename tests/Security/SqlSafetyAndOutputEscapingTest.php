<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/*
 * Locks in a series of previously fixed SQL-injection and output-escaping
 * regressions across the plugin's view/action files. Each check targets the
 * exact source pattern the fix introduced, so a revert or a careless edit
 * that reintroduces the vulnerable pattern fails immediately.
 */
describe('SQL safety and output escaping in mactrack', function () {
	it('quotes the device-type vendor filter through Cacti SQL escaping', function () {
		$source = plugin_test_read_source('mactrack_device_types.php');

		expect($source)->not->toContain("(mtdt.vendor='\" . get_request_var('vendor')");
		expect($source)->toContain("mtdt.vendor = ' . db_qstr(get_request_var('vendor'))");
	});

	it('normalizes aggregated MAC bulk-action IDs and uses prepared deletion SQL', function () {
		$source = plugin_test_read_source('mactrack_view_macs.php');

		expect($source)->toContain('function mactrack_normalize_ids(array $ids): array');
		expect($source)->toContain("db_execute_prepared('DELETE FROM mac_track_aggregated_ports WHERE row_id IN('");
	});

	it('escapes MAC authorization and action output instead of deserializing request input', function () {
		$source = plugin_test_read_source('mactrack_view_macs.php');

		expect($source)->not->toContain("unserialize(get_nfilter_request_var('selected_items')");
		expect($source)->toContain("json_decode(get_nfilter_request_var('selected_items'), true)");
		expect($source)->toContain('html_escape(json_encode($mac_address_array))');
		expect($source)->not->toContain("sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'))");
		expect($source)->toContain('html_escape(json_encode($row_array))');
		expect($source)->toContain("html_escape((string) get_request_var('drp_action'))");
	});

	it('escapes the rescan executable and script paths before command execution', function () {
		$source = plugin_test_read_source('lib/mactrack_functions.php');

		expect($source)->toContain("cacti_escapeshellcmd(read_config_option('path_php_binary'))");
		expect($source)->toContain('cacti_escapeshellarg($command_string)');
	});

	it('parameterizes stale-process site filtering and normalizes process IDs', function () {
		$source = plugin_test_read_source('poller_mactrack.php');

		expect($source)->toContain('site_id = ?');
		expect($source)->toContain("intval(\$p['process_id'])");
	});

	it('escapes Cabletron SNMP command arguments', function () {
		$source = plugin_test_read_source('lib/mactrack_cabletron.php');

		expect($source)->toContain("cacti_escapeshellcmd(read_config_option('path_snmpgetnext'))");
		expect($source)->toContain('cacti_escapeshellarg($device[\'hostname\'] . \':\' . intval($device[\'snmp_port\']))');
	});

	it('safely quotes the ignored-interfaces RLIKE pattern and normalizes numeric filters', function () {
		$source = plugin_test_read_source('mactrack_view_interfaces.php');

		expect($source)->toContain('mactrack_get_ignore_ports_predicate($sql_params)');
		expect($source)->toContain('db_fetch_assoc_prepared($sql_query, $sql_params)');
		expect($source)->toContain('db_fetch_cell_prepared($rows_query_string, $sql_params)');
		expect($source)->not->toContain('db_qstr($match)');
		expect($source)->not->toContain('db_qstr_rlike');
		expect($source)->toContain("intval(get_filter_request_var('bwusage'))");
	});

	it('normalizes numeric filters on the device report views', function () {
		foreach (['mactrack_view_devices.php', 'mactrack_devices.php'] as $file) {
			$source = plugin_test_read_source($file);

			expect($source)->toContain("intval(get_filter_request_var('status'))");
			expect($source)->toContain("intval(get_filter_request_var('site_id'))");
		}
	});

	it('validates the canonical local-file path before importing an OUI database', function () {
		$source = plugin_test_read_source('mactrack_import_ouidb.php');

		expect($source)->toContain('function mactrack_validate_oui_file(string $path): string');
		expect($source)->toContain('is_file($resolved)');
		expect($source)->toContain('is_readable($resolved)');
	});

	it('does not call undefined MAC-formatting or graph-settings compatibility handlers', function () {
		$arpSource  = plugin_test_read_source('mactrack_view_arp.php');
		$ajaxSource = plugin_test_read_source('mactrack_ajax.php');

		expect($arpSource)->toContain('mactrack_format_mac(');
		expect($arpSource)->not->toContain('format_mac_address(');
		expect($ajaxSource)->not->toContain('mactrack_save_graph_settings');
	});

	it('does not gate plugin enablement on the DNS resolver library', function () {
		// DNS resolution is an optional collector feature, so a missing or
		// unloadable Net_DNS2 must never leave the plugin stuck in "needs
		// configuration".
		$source = plugin_test_read_source('setup.php');

		expect($source)->not->toContain('Net_DNS2');
		expect($source)->not->toContain('Net/DNS2.php');
	});
});
