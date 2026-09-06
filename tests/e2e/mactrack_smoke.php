<?php

if (PHP_SAPI !== 'cli') {
	exit(1);
}
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

require_once __DIR__ . '/../../../../include/cli_check.php';
require_once __DIR__ . '/../../vendor/autoload.php';

if (!defined('MESSAGE_LEVEL_ERROR') || MESSAGE_LEVEL_ERROR !== 3) {
	fwrite(STDERR, "Unexpected Cacti MESSAGE_LEVEL_ERROR contract\n");
	exit(1);
}

foreach (['db_column_exists', 'db_index_exists'] as $helper) {
	if (!function_exists($helper)) {
		fwrite(STDERR, "Pinned Cacti core is missing required database helper: $helper\n");
		exit(1);
	}

	$signature = new ReflectionFunction($helper);

	if ($signature->getNumberOfRequiredParameters() !== 2 || $signature->getNumberOfParameters() < 2) {
		fwrite(STDERR, "Pinned Cacti database helper has an incompatible signature: $helper\n");
		exit(1);
	}
}

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

$manifest = require __DIR__ . '/../Support/SchemaManifest.php';

foreach ($manifest['tables'] as $table) {
	if (!db_table_exists($table)) {
		fwrite(STDERR, "Missing Mactrack table: $table\n");
		exit(1);
	}
}

foreach ($manifest['critical_columns'] as $table => $columns) {
	foreach ($columns as $column) {
		if (!db_column_exists($table, $column)) {
			fwrite(STDERR, "Missing Mactrack column: $table.$column\n");
			exit(1);
		}
	}
}

foreach ($manifest['critical_indexes'] as $table => $indexes) {
	foreach ($indexes as $index) {
		if (!db_index_exists($table, $index)) {
			fwrite(STDERR, "Missing Mactrack index: $table.$index\n");
			exit(1);
		}
	}
}

$default_site = db_fetch_cell_prepared(
	'SELECT COUNT(*) FROM mac_track_sites WHERE site_name = ?',
	['Default']
);

if ((int) $default_site !== 1) {
	fwrite(STDERR, "Mactrack default site was not seeded exactly once\n");
	exit(1);
}

if (!class_exists('Net_DNS2_Resolver')) {
	fwrite(STDERR, "Mactrack Composer DNS dependency is unavailable\n");
	exit(1);
}

print 'Mactrack integration bootstrap passed: ' . count($manifest['tables']) . " tables verified\n";
