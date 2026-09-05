<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once __DIR__ . '/../../../../include/cli_check.php';
require_once __DIR__ . '/../../lib/mactrack_functions.php';

$raw      = 'aabbccddeeff';
$original = read_config_option('mt_mac_format');
$failed   = 0;

// The last two cases are the regression: an unset or unrecognised format used
// to fall off the end of mactrack_format_mac() and blank every address.
$cases = [
	'aa:bb:cc:dd:ee:ff' => 'aa:bb:cc:dd:ee:ff',
	'aa-bb-cc-dd-ee-ff' => 'aa-bb-cc-dd-ee-ff',
	'aabbccddeeff'      => 'aabbccddeeff',
	'aabb-ccdd-eeff'    => 'aabb-ccdd-eeff',
	'aabb.ccdd.eeff'    => 'aabb.ccdd.eeff',
	''                  => 'aabbccddeeff',
	'not-a-format'      => 'aabbccddeeff',
];

foreach ($cases as $format => $expected) {
	set_config_option('mt_mac_format', $format);

	$actual = mactrack_format_mac($raw);

	if ($actual !== $expected) {
		fwrite(STDERR, sprintf("mt_mac_format '%s': expected '%s', got '%s'\n", $format, $expected, var_export($actual, true)));
		$failed++;
	}
}

// A short or null address is returned untouched rather than split.
foreach ([null, '', 'aabbcc'] as $short) {
	if (mactrack_format_mac($short) !== $short) {
		fwrite(STDERR, 'Short address ' . var_export($short, true) . " must be returned unchanged\n");
		$failed++;
	}
}

set_config_option('mt_mac_format', $original);

if ($failed) {
	exit(1);
}

print "OK\n";
