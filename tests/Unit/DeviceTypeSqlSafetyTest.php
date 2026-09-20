<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Converted from the standalone tests/Unit/test_device_type_sql_safety.php
 * script. Guards against regressing the SQL-injection, XSS, command-injection,
 * path-traversal, and dependency-verification fixes applied across mactrack.
 */

it('quotes the device-type vendor filter instead of concatenating request input', function () {
	$source = plugin_test_read_source('mactrack_device_types.php');

	expect($source)->not->toContain("(mtdt.vendor='\" . get_request_var('vendor')");
	expect($source)->toContain("mtdt.vendor = ' . db_qstr(get_request_var('vendor'))");
});

it('normalizes IDs and uses prepared SQL for aggregated MAC deletion', function () {
	$source = plugin_test_read_source('mactrack_view_macs.php');

	expect($source)->toContain('function mactrack_normalize_ids(array $ids): array');
	expect($source)->toContain("db_execute_prepared('DELETE FROM mac_track_aggregated_ports WHERE row_id IN('");
});

it('uses escaped JSON instead of request deserialization for MAC authorization actions', function () {
	$source = plugin_test_read_source('mactrack_view_macs.php');

	expect($source)->not->toContain("unserialize(get_nfilter_request_var('selected_items')");
	expect($source)->toContain("json_decode(get_nfilter_request_var('selected_items'), true)");
	expect($source)->toContain('html_escape(json_encode($mac_address_array))');
});

it('uses escaped JSON and contextual output encoding for MAC action form values', function () {
	$source = plugin_test_read_source('mactrack_view_macs.php');

	expect($source)->not->toContain("sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'))");
	expect($source)->toContain('html_escape(json_encode($row_array))');
	expect($source)->toContain("html_escape((string) get_request_var('drp_action'))");
});

it('escapes the executable and script paths used by rescan commands', function () {
	$source = plugin_test_read_source('lib/mactrack_functions.php');

	expect($source)->toContain("cacti_escapeshellcmd(read_config_option('path_php_binary'))");
	expect($source)->toContain('cacti_escapeshellarg($command_string)');
});

it('parameterizes site IDs and normalizes PIDs during poller process cleanup', function () {
	$source = plugin_test_read_source('poller_mactrack.php');

	expect($source)->toContain('site_id = ?');
	expect($source)->toContain("intval(\$p['process_id'])");
});

it('escapes the executable and device endpoint values in the Cabletron SNMP command', function () {
	$source = plugin_test_read_source('lib/mactrack_cabletron.php');

	expect($source)->toContain("cacti_escapeshellcmd(read_config_option('path_snmpgetnext'))");
	expect($source)->toContain('cacti_escapeshellarg($device[\'hostname\'] . \':\' . intval($device[\'snmp_port\']))');
});

it('uses safe RLIKE quoting and normalized numeric values for interface filters', function () {
	$source = plugin_test_read_source('mactrack_view_interfaces.php');

	expect($source)->toContain('mactrack_get_ignore_ports_predicate($sql_params)');
	expect($source)->toContain('db_fetch_assoc_prepared($sql_query, $sql_params)');
	expect($source)->toContain('db_fetch_cell_prepared($rows_query_string, $sql_params)');
	expect($source)->not->toContain('db_qstr($match)');
	expect($source)->not->toContain('db_qstr_rlike');
	expect($source)->toContain("intval(get_filter_request_var('bwusage'))");
});

it('normalizes numeric values in the device report SQL filters', function () {
	$source = plugin_test_read_source('mactrack_view_devices.php');

	expect($source)->toContain("intval(get_filter_request_var('status'))");
	expect($source)->toContain("intval(get_filter_request_var('site_id'))");
});

it('normalizes numeric values in the administrative device SQL filters', function () {
	$source = plugin_test_read_source('mactrack_devices.php');

	expect($source)->toContain("intval(get_filter_request_var('status'))");
	expect($source)->toContain("intval(get_filter_request_var('site_id'))");
});

it('validates canonical regular-file paths before importing an OUI database', function () {
	$source = plugin_test_read_source('mactrack_import_ouidb.php');

	expect($source)->toContain('function mactrack_validate_oui_file(string $path): string');
	expect($source)->toContain('is_file($resolved)');
	expect($source)->toContain('is_readable($resolved)');
});

it('preserves PHP 8 compatible argument order for the 1.2.31 signatures', function () {
	$convertSource = plugin_test_read_source('mactrack_convert.php');
	$arpSource     = plugin_test_read_source('mactrack_view_arp.php');
	$dot1xSource   = plugin_test_read_source('mactrack_view_dot1x.php');
	$ajaxSource    = plugin_test_read_source('mactrack_ajax.php');

	expect($convertSource)->toContain('mactrack_create_partitioned_table($engine, $charset, $collate, $days, true)');
	expect($arpSource)->toContain('function mactrack_view_get_ip_records(&$sql_where, $rows, $apply_limits = true)');
	expect($dot1xSource)->toContain('function mactrack_view_get_dot1x_records(&$sql_where, &$sql_params, $rows, $apply_limits = true)');

	expect($arpSource)->toContain('mactrack_format_mac(');
	expect($arpSource)->not->toContain('format_mac_address(');
	expect($ajaxSource)->not->toContain('mactrack_save_graph_settings');
});

it('loads Net_DNS2 from the bundled Net/ directory and reports rather than fatals when missing', function () {
	$source = plugin_test_read_source('mactrack_resolver.php');

	expect($source)->toContain("'/plugins/mactrack' . PATH_SEPARATOR . get_include_path()");
	expect($source)->toContain('if (is_file($dns2))');
	expect($source)->toContain("class_exists('Net_DNS2_Resolver')");
});

it('never gates plugin enablement on the optional DNS library', function () {
	$source = plugin_test_read_source('setup.php');

	expect($source)->not->toContain('Net_DNS2');
	expect($source)->not->toContain('Net/DNS2.php');
});
