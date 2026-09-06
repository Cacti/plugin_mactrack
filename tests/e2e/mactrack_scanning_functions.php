<?php

if (PHP_SAPI !== 'cli') {
	exit(1);
}
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

if (getenv('MACTRACK_E2E') !== '1') {
	fwrite(STDERR, "This database-mutating check is restricted to the disposable E2E environment\n");
	exit(2);
}

require_once __DIR__ . '/../../../../include/cli_check.php';
require_once __DIR__ . '/../../lib/mactrack_functions.php';
require_once __DIR__ . '/../Support/E2eDatabaseGuard.php';

global $database_default;

if (!MactrackE2eDatabaseGuard::isDisposable($database_default, getenv('MACTRACK_E2E_EXPECT_DB'))) {
	fwrite(STDERR, "Refusing scanning-function rebuild outside a dedicated mactrack_e2e database\n");
	exit(2);
}

mactrack_rebuild_scanning_funcs();

$expected = [
	'get_generic_switch_ports',
	'get_standard_arp_table',
	'Not Applicable',
];

foreach ($expected as $function) {
	$count = db_fetch_cell_prepared(
		'SELECT COUNT(*) FROM mac_track_scanning_functions WHERE scanning_function = ?',
		[$function]
	);

	if ((int) $count !== 1) {
		fwrite(STDERR, "Scanning function was not rebuilt exactly once: $function\n");
		exit(1);
	}
}

print "Mactrack scanning-function rebuild passed\n";
