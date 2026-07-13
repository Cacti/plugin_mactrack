<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

require_once __DIR__ . '/../../../../include/cli_check.php';
require_once __DIR__ . '/../../vendor/autoload.php';

$status = db_fetch_cell_prepared(
	'SELECT status
	FROM plugin_config
	WHERE directory = ?',
	['mactrack']
);

if (!api_plugin_is_enabled('mactrack')) {
	fwrite(STDERR, "Mactrack plugin is not enabled (status: {$status})\n");
	exit(1);
}

foreach (['mac_track_sites', 'mac_track_devices', 'mac_track_ports'] as $table) {
	if (!db_table_exists($table)) {
		fwrite(STDERR, "Missing Mactrack table: $table\n");
		exit(1);
	}
}

if (!class_exists('Net_DNS2_Resolver')) {
	fwrite(STDERR, "Mactrack Composer DNS dependency is unavailable\n");
	exit(1);
}

print "Mactrack integration bootstrap passed\n";
