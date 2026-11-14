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

beforeAll(function () {
	mactrack_test_load(dirname(__DIR__, 2) . '/lib/mactrack_functions.php');
});

beforeEach(function () {
	$GLOBALS['__test_db_calls']        = [];
	$GLOBALS['__test_next_returns']    = [];
	$GLOBALS['__test_matched_returns'] = [];
	$GLOBALS['database_sessions']      = [];
	$GLOBALS['__test_config_options']  = [];
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'GET_LOCK', '1');
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'VERSION()', '8.4.13');
});

function mt_opt_layout(string $expression = 'unix_timestamp(scan_date)'): void {
	$rows = [];

	foreach (['d20261112' => '1794528000', 'd20261113' => '1794614400', 'd20261114' => '1794700800', 'dMaxValue' => 'MAXVALUE'] as $name => $bound) {
		$rows[] = ['PARTITION_NAME'   => $name, 'PARTITION_DESCRIPTION' => $bound,
			'PARTITION_ORDINAL_POSITION' => count($rows) + 1, 'PARTITION_METHOD' => 'RANGE', 'PARTITION_EXPRESSION' => $expression];
	}
	mactrack_test_queue_return_for('db_fetch_assoc_prepared', 'information_schema.PARTITIONS', $rows);
}

it('rebuilds only the latest populated partition and analyzes it', function ($latest, $name) {
	mt_opt_layout();
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'UNIX_TIMESTAMP(MAX(scan_date))', $latest);
	mactrack_test_queue_return_for('db_fetch_assoc_prepared', 'ANALYZE PARTITION', [['Msg_type' => 'status', 'Msg_text' => 'OK']]);
	expect(mactrack_partition_optimize()[0]['Msg_text'])->toBe('OK');
	$sql = array_column($GLOBALS['__test_db_calls'], 'sql');
	expect($sql)->toContain("ALTER TABLE `mac_track_ports` REBUILD PARTITION `$name`", "ALTER TABLE `mac_track_ports` ANALYZE PARTITION `$name`");
	expect($sql)->not->toContain('OPTIMIZE TABLE mac_track_ports');
	expect(end($sql))->toBe('SELECT RELEASE_LOCK(?)');
})->with([
	'current before future partitions' => ['1794531600', 'd20261113'],
	'catch-all recovery data'          => ['1794800000', 'dMaxValue'],
	'zero date'                        => ['0', 'd20261112'],
]);

it('uses whole-table optimize only for an unpartitioned table', function () {
	mactrack_test_queue_return_for('db_fetch_assoc_prepared', 'OPTIMIZE TABLE', [['Msg_type' => 'status', 'Msg_text' => 'OK']]);
	expect(mactrack_partition_optimize()[0]['Msg_text'])->toBe('OK');
	expect(array_column($GLOBALS['__test_db_calls'], 'sql'))->toContain('OPTIMIZE TABLE mac_track_ports');
});

it('refuses a legacy layout without rebuilding the table', function () {
	mt_opt_layout('to_days(scan_date)');
	expect(mactrack_partition_optimize()[0]['Msg_type'])->toBe('error');
	expect(array_column($GLOBALS['__test_db_calls'], 'sql'))->not->toContain('OPTIMIZE TABLE mac_track_ports');
});

it('does not rebuild empty tables or when the latest date cannot be read', function ($latest, $type) {
	mt_opt_layout();
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'UNIX_TIMESTAMP(MAX(scan_date))', $latest);
	expect(mactrack_partition_optimize()[0]['Msg_type'])->toBe($type);
	expect(array_filter(array_column($GLOBALS['__test_db_calls'], 'sql'), fn ($sql) => strpos($sql, 'ALTER TABLE') === 0))->toBe([]);
})->with([[null, 'status'], [false, 'error']]);

it('refuses a busy partition lock', function () {
	$GLOBALS['__test_matched_returns'] = [];
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'GET_LOCK', '0');
	expect(mactrack_partition_optimize()[0]['Msg_type'])->toBe('error');
	expect($GLOBALS['__test_db_calls'])->toHaveCount(1);
});

it('reports rebuild failure and releases its lock without analyzing', function () {
	mt_opt_layout();
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'UNIX_TIMESTAMP(MAX(scan_date))', '1794531600');
	mactrack_test_queue_return_for('db_execute_prepared', 'REBUILD PARTITION', false);
	expect(mactrack_partition_optimize()[0]['Msg_type'])->toBe('error');
	$sql = array_column($GLOBALS['__test_db_calls'], 'sql');
	expect(end($sql))->toBe('SELECT RELEASE_LOCK(?)');
	expect(array_filter($sql, fn ($s) => strpos($s, 'ANALYZE PARTITION') !== false))->toBe([]);
});
