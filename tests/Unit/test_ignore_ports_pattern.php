<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

require_once __DIR__ . '/../Support/CactiStubs.php';
require_once __DIR__ . '/../../lib/mactrack_functions.php';

$default = '(Vlan|Loopback|Null)';
$failed  = 0;

foreach ([$default, '^(Gi|Te)[0-9/]+$', 'Port~Channel', 'Vlan\\~Trunk'] as $valid) {
	if (mactrack_validate_ignore_ports_pattern($valid) !== $valid) {
		fwrite(STDERR, 'Valid ignore-ports pattern changed: ' . $valid . "\n");
		$failed++;
	}
}

foreach (['', null, '(Vlan', '[a-', '(a+)+$'] as $invalid) {
	if (mactrack_validate_ignore_ports_pattern($invalid) !== $default) {
		fwrite(STDERR, 'Invalid ignore-ports pattern did not fall back: ' . var_export($invalid, true) . "\n");
		$failed++;
	}
}

$source = file_get_contents(__DIR__ . '/../../mactrack_view_interfaces.php');

if ($source === false ||
	strpos($source, 'mactrack_get_ignore_ports_predicate($sql_params)') === false ||
	strpos($source, 'db_fetch_assoc_prepared($sql_query, $sql_params)') === false ||
	strpos($source, 'db_fetch_cell_prepared($rows_query_string, $sql_params)') === false) {
	fwrite(STDERR, "The Interfaces query must validate and preserve the configured RLIKE pattern\n");
	$failed++;
}

$GLOBALS['mactrack_test_config_options']['mt_ignorePorts'] = $default;
$GLOBALS['mactrack_test_db_calls'] = [];
$params    = [];
$predicate = mactrack_get_ignore_ports_predicate($params);

if ($predicate !== '(ifName NOT RLIKE ? AND ifDescr NOT RLIKE ?)' || $params !== [$default, $default]) {
	fwrite(STDERR, "The default ignore-ports expression must survive into bound RLIKE parameters\n");
	$failed++;
}

$filter_expectations = [
	'-4' => [-1 => true, 70 => true],
	'-3' => [-1 => true, 70 => true],
	'-2' => [-1 => false, 70 => false],
	'-1' => [-1 => true, 70 => true],
	'0'  => [-1 => true, 70 => true],
	'1'  => [-1 => true, 70 => true],
	'2'  => [-1 => true, 70 => true],
	'3'  => [-1 => true, 70 => true],
	'7'  => [-1 => false, 70 => false],
	'9'  => [-1 => false, 70 => true],
	'10' => [-1 => false, 70 => true],
	'11' => [-1 => false, 70 => true],
];

foreach ($filter_expectations as $issues => $bandwidth_cases) {
	foreach ($bandwidth_cases as $bwusage => $expected_ignore) {
		$actual_ignore = mactrack_interface_filter_needs_ignore($issues, $bwusage);
		$params        = [];
		$sql           = '';

		if ($actual_ignore) {
			$sql = mactrack_get_ignore_ports_predicate($params);
		}

		if ($actual_ignore !== $expected_ignore || substr_count($sql, '?') !== count($params)) {
			fwrite(STDERR, "Ignore predicate/parameter mismatch for issues=$issues bwusage=$bwusage\n");
			$failed++;
		}
	}
}

if (strpos($source, "' NOT ' . \$ignore") === false) {
	fwrite(STDERR, "The ignored-interface filter must preserve bound placeholders under NOT\n");
	$failed++;
}

$GLOBALS['mactrack_test_config_options']['mt_ignorePorts'] = '(Vlan';
$GLOBALS['mactrack_test_db_calls'] = [];

if (mactrack_get_ignore_ports_pattern() !== $default || $GLOBALS['mactrack_test_db_calls'] !== []) {
	fwrite(STDERR, "A viewer fallback must not overwrite a non-empty administrator setting\n");
	$failed++;
}

$GLOBALS['mactrack_test_config_options']['mt_ignorePorts'] = '';
$GLOBALS['mactrack_test_db_calls'] = [];

if (mactrack_get_ignore_ports_pattern() !== $default || count($GLOBALS['mactrack_test_db_calls']) !== 1) {
	fwrite(STDERR, "An empty ignore-ports setting must be initialized once\n");
	$failed++;
}

if ($failed) {
	exit(1);
}

print "OK\n";
