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

$failed = 0;

// mac_track_interfaces.ifPhysAddress is a MAC column.  A VLAN SVI, loopback or
// tunnel reports an empty ifPhysAddress and must stay empty rather than pick up
// a placeholder string.
foreach (['', '  ', null] as $empty) {
	$actual = xform_mac_address($empty);

	if ($actual !== '') {
		fwrite(STDERR, sprintf("Empty ifPhysAddress %s became %s\n", var_export($empty, true), var_export($actual, true)));
		$failed++;
	}
}

$cases = [
	'HEX-00:aa:bb:cc:dd:ee' => 'AABBCCDDEE',
	'aa-bb-cc-dd-ee-ff'     => 'AABBCCDDEEFF',
	'AA:BB:CC:DD:EE:FF'     => 'AABBCCDDEEFF',
];

foreach ($cases as $raw => $expected) {
	$actual = xform_mac_address($raw);

	if ($actual !== $expected) {
		fwrite(STDERR, sprintf("xform_mac_address('%s'): expected '%s', got %s\n", $raw, $expected, var_export($actual, true)));
		$failed++;
	}
}

if ($failed) {
	exit(1);
}

print "OK\n";
