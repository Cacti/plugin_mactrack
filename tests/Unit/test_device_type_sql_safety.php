<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

$source = file_get_contents(__DIR__ . '/../../mactrack_device_types.php');

if ($source === false) {
	fwrite(STDERR, "Unable to read mactrack_device_types.php\n");
	exit(1);
}

if (strpos($source, "(mtdt.vendor='\" . get_request_var('vendor')") !== false) {
	fwrite(STDERR, "Device-type vendor filter must not concatenate request input into SQL\n");
	exit(1);
}

if (strpos($source, "mtdt.vendor = ' . db_qstr(get_request_var('vendor'))") === false) {
	fwrite(STDERR, "Device-type vendor filter must use Cacti SQL quoting\n");
	exit(1);
}

$macSource = file_get_contents(__DIR__ . '/../../mactrack_view_macs.php');

if ($macSource === false) {
	fwrite(STDERR, "Unable to read mactrack_view_macs.php\n");
	exit(1);
}

if (strpos($macSource, 'function mactrack_normalize_ids(array $ids): array')                         === false ||
	strpos($macSource, "db_execute_prepared('DELETE FROM mac_track_aggregated_ports WHERE row_id IN('") === false) {
	fwrite(STDERR, "Aggregated MAC deletion must normalize IDs and use prepared SQL\n");
	exit(1);
}

if (strpos($macSource, "unserialize(get_nfilter_request_var('selected_items')") !== false ||
	strpos($macSource, "json_decode(get_nfilter_request_var('selected_items'), true)") === false ||
	strpos($macSource, 'html_escape(json_encode($mac_address_array))')                 === false) {
	fwrite(STDERR, "MAC authorization actions must use escaped JSON instead of request deserialization\n");
	exit(1);
}

if (strpos($macSource, "sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'))") !== false ||
	strpos($macSource, 'html_escape(json_encode($row_array))')                === false ||
	strpos($macSource, "html_escape((string) get_request_var('drp_action'))") === false) {
	fwrite(STDERR, "MAC action form values must use escaped JSON and contextual output encoding\n");
	exit(1);
}

$functionsSource = file_get_contents(__DIR__ . '/../../lib/mactrack_functions.php');

if ($functionsSource                                                                     === false ||
	strpos($functionsSource, "cacti_escapeshellcmd(read_config_option('path_php_binary'))") === false ||
	strpos($functionsSource, 'cacti_escapeshellarg($command_string)')                       === false) {
	fwrite(STDERR, "Rescan commands must escape executable and script paths\n");
	exit(1);
}

$pollerSource = file_get_contents(__DIR__ . '/../../poller_mactrack.php');

if ($pollerSource                                   === false ||
	strpos($pollerSource, 'site_id = ?')               === false ||
	strpos($pollerSource, "intval(\$p['process_id'])") === false) {
	fwrite(STDERR, "Poller process cleanup must parameterize site IDs and normalize PIDs\n");
	exit(1);
}

$cabletronSource = file_get_contents(__DIR__ . '/../../lib/mactrack_cabletron.php');

if ($cabletronSource                                                                                              === false ||
	strpos($cabletronSource, "cacti_escapeshellcmd(read_config_option('path_snmpgetnext'))")                         === false ||
	strpos($cabletronSource, 'cacti_escapeshellarg($device[\'hostname\'] . \':\' . intval($device[\'snmp_port\']))') === false) {
	fwrite(STDERR, "Cabletron SNMP command must escape executable and device endpoint values\n");
	exit(1);
}

$interfacesSource = file_get_contents(__DIR__ . '/../../mactrack_view_interfaces.php');

if ($interfacesSource                         === false ||
	strpos($interfacesSource, 'db_qstr($match)') === false ||
	strpos($interfacesSource, 'db_qstr_rlike') !== false ||
	strpos($interfacesSource, "intval(get_filter_request_var('bwusage'))") === false) {
	fwrite(STDERR, "Interface filters must use safe RLIKE quoting and normalized numeric values\n");
	exit(1);
}

$devicesSource = file_get_contents(__DIR__ . '/../../mactrack_view_devices.php');

if ($devicesSource                                                   === false ||
	strpos($devicesSource, "intval(get_filter_request_var('status'))")  === false ||
	strpos($devicesSource, "intval(get_filter_request_var('site_id'))") === false) {
	fwrite(STDERR, "Device report SQL filters must use normalized numeric values\n");
	exit(1);
}

$adminDevicesSource = file_get_contents(__DIR__ . '/../../mactrack_devices.php');

if ($adminDevicesSource                                                   === false ||
	strpos($adminDevicesSource, "intval(get_filter_request_var('status'))")  === false ||
	strpos($adminDevicesSource, "intval(get_filter_request_var('site_id'))") === false) {
	fwrite(STDERR, "Administrative device SQL filters must use normalized numeric values\n");
	exit(1);
}

$ouiImportSource = file_get_contents(__DIR__ . '/../../mactrack_import_ouidb.php');

if ($ouiImportSource                                                                   === false ||
	strpos($ouiImportSource, 'function mactrack_validate_oui_file(string $path): string') === false ||
	strpos($ouiImportSource, 'is_file($resolved)')                                        === false ||
	strpos($ouiImportSource, 'is_readable($resolved)')                                    === false) {
	fwrite(STDERR, "OUI import must validate canonical regular-file paths\n");
	exit(1);
}

$convertSource = file_get_contents(__DIR__ . '/../../mactrack_convert.php');
$arpSource     = file_get_contents(__DIR__ . '/../../mactrack_view_arp.php');
$dot1xSource   = file_get_contents(__DIR__ . '/../../mactrack_view_dot1x.php');

if ($convertSource                                                                                          === false || $arpSource === false || $dot1xSource === false ||
	strpos($convertSource, 'mactrack_create_partitioned_table($engine, $charset, $collate, $days, true)')      === false ||
	strpos($arpSource, 'function mactrack_view_get_ip_records(&$sql_where, $rows, $apply_limits = true)')      === false ||
	strpos($dot1xSource, 'function mactrack_view_get_dot1x_records(&$sql_where, $rows, $apply_limits = true)') === false) {
	fwrite(STDERR, "Cacti 1.2.31 PHP 8 compatibility signatures must preserve argument order\n");
	exit(1);
}

$resolverSource = file_get_contents(__DIR__ . '/../../mactrack_resolver.php');

if ($resolverSource                                           === false ||
	strpos($resolverSource, 'set_include_path(')                 === false ||
	strpos($resolverSource, 'if (is_file($dns2))')               === false ||
	strpos($resolverSource, "class_exists('Net_DNS2_Resolver')") === false) {
	fwrite(STDERR, "DNS resolver must reach Net_DNS2 regardless of cwd and report a missing library rather than fatal\n");
	exit(1);
}

$setupSource = file_get_contents(__DIR__ . '/../../setup.php');

// DNS resolution is an optional collector feature, so a missing or unloadable
// Net_DNS2 must never leave the plugin stuck in "needs configuration".
if ($setupSource === false ||
	strpos($setupSource, 'Net_DNS2') !== false ||
	strpos($setupSource, 'Net/DNS2.php') !== false) {
	fwrite(STDERR, "plugin_mactrack_check_config() must not gate enablement on the DNS library\n");
	exit(1);
}

print "OK\n";
