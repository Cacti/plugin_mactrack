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
 * A per-scan partition is filled by loading the scan into the empty stage
 * table and exchanging it into place. An exchange that fails stays pending
 * with its rows in the stage table, and the next scan retries it first. If
 * the retry fails too, the stage rows go straight into mac_track_ports.
 */

beforeAll(function () {
	mactrack_test_load(dirname(__DIR__, 2) . '/lib/mactrack_functions.php');
	mactrack_test_load(dirname(__DIR__, 2) . '/lib/mactrack_partitions.php');
	mactrack_test_load(dirname(__DIR__, 2) . '/lib/mactrack_partition_ops.php');
});

beforeEach(function () {
	$GLOBALS['__test_db_calls']        = [];
	$GLOBALS['__test_next_returns']    = [];
	$GLOBALS['__test_matched_returns'] = [];
	$GLOBALS['__test_config_options']  = [];
	$GLOBALS['__test_cacti_log']       = [];
	$GLOBALS['database_default']       = 'cacti';
	$GLOBALS['database_sessions']      = [];

	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'VERSION()', '10.5.29-MariaDB');
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'GET_LOCK', '1');
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'lock_wait_timeout', '50');
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'SELECT EXISTS', '0');
});

const MT_LS_PLAN = ['mode' => 'exchange', 'partition' => 's20261112061507', 'reason' => '', 'granularity' => 'scan', 'scan_date' => '2026-11-12 06:15:07'];

/**
 * Returns the SQL of every db_execute_prepared call, in order.
 *
 * @return string[]
 */
function mt_ls_executed(): array {
	return array_values(array_column(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_execute_prepared'), 'sql'));
}

/**
 * Returns the index of the first executed statement containing $fragment.
 *
 * @param  string   $fragment Text to look for.
 * @return int|null
 */
function mt_ls_pos(string $fragment): ?int {
	foreach (mt_ls_executed() as $i => $sql) {
		if (strpos($sql, $fragment) !== false) {
			return $i;
		}
	}

	return null;
}

/**
 * Queues information_schema answers for the stage check: columns and
 * indexes of mac_track_ports, then of the stage table.
 *
 * @param  array|null $stage_cols Stage columns; null for a missing table.
 * @param  array|null $stage_idx  Stage indexes; defaults to the main table's.
 * @return void
 */
function mt_ls_stage(?array $stage_cols, ?array $stage_idx = null, ?array $stage_tbl = null): void {
	$cols = [
		['COLUMN_NAME' => 'site_id', 'COLUMN_TYPE' => 'int(10) unsigned', 'IS_NULLABLE' => 'NO', 'COLUMN_DEFAULT' => '0'],
		['COLUMN_NAME' => 'scan_date', 'COLUMN_TYPE' => 'timestamp', 'IS_NULLABLE' => 'NO', 'COLUMN_DEFAULT' => 'current_timestamp()'],
	];
	$idx  = [
		['INDEX_NAME' => 'PRIMARY', 'SEQ_IN_INDEX' => '1', 'COLUMN_NAME' => 'scan_date', 'NON_UNIQUE' => '0'],
	];

	$tbl  = [['ENGINE' => 'InnoDB', 'TABLE_COLLATION' => 'utf8mb4_unicode_ci']];

	mactrack_test_queue_return('db_fetch_assoc_prepared', $cols);
	mactrack_test_queue_return('db_fetch_assoc_prepared', $idx);
	mactrack_test_queue_return('db_fetch_assoc_prepared', $tbl);
	mactrack_test_queue_return('db_fetch_assoc_prepared', $stage_cols === null ? [] : ($stage_cols === ['same'] ? $cols : $stage_cols));
	mactrack_test_queue_return('db_fetch_assoc_prepared', $stage_cols === null ? [] : ($stage_idx ?? $idx));
	mactrack_test_queue_return('db_fetch_assoc_prepared', $stage_cols === null ? [] : ($stage_tbl ?? $tbl));
}

it('loads a direct scan straight into mac_track_ports', function () {
	$rows = mactrack_partition_load_scan(['mode' => 'direct', 'partition' => null, 'reason' => 'layout none', 'granularity' => null, 'scan_date' => '2026-11-12 06:15:07']);

	expect($rows)->toBe(0);
	expect(mt_ls_pos('EXCHANGE'))->toBeNull();
	expect(mt_ls_pos('TRUNCATE'))->toBeNull();
	expect(array_column($GLOBALS['__test_db_calls'], 'sql'))->not->toContain('SELECT VERSION()');
});

it('loads into the stage table and exchanges it into the scan partition', function () {
	mt_ls_stage(['same']);
	mactrack_test_queue_return('db_fetch_assoc_prepared', [mt_ls_row(1), mt_ls_row(2)]);

	$rows = mactrack_partition_load_scan(MT_LS_PLAN);

	expect($rows)->toBe(2);
	expect(mt_ls_pos('CREATE TABLE'))->toBeNull();
	expect(mt_ls_pos('TRUNCATE TABLE `mac_track_ports_stage`'))->toBeLessThan(mt_ls_pos('INSERT IGNORE INTO `mac_track_ports_stage`'));
	expect(mt_ls_pos('INSERT IGNORE INTO `mac_track_ports_stage`'))->toBeLessThan(mt_ls_pos('EXCHANGE'));
	expect(mt_ls_executed())->toContain(mactrack_partition_exchange_sql('s20261112061507', false));
	expect($GLOBALS['__test_config_options']['mt_partition_pending'] ?? '')->toBe('');
});

it('puts only this scan into its partition and older leftovers where their dates belong', function () {
	mt_ls_stage(['same']);
	mactrack_test_queue_return_for('db_fetch_assoc_prepared', 'WHERE scan_date = ?', [mt_ls_row(1), mt_ls_row(2)]);
	mactrack_test_queue_return_for('db_fetch_assoc_prepared', 'WHERE scan_date <> ?', [mt_ls_row(3)]);

	$rows = mactrack_partition_load_scan(MT_LS_PLAN);

	expect($rows)->toBe(3);
	expect(mt_ls_pos('INSERT IGNORE INTO `mac_track_ports_stage`'))->toBeLessThan(mt_ls_pos('EXCHANGE'));
	expect(mt_ls_pos('EXCHANGE'))->toBeLessThan(mt_ls_pos('INSERT IGNORE INTO `mac_track_ports` '));

	$reads = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_fetch_assoc_prepared' && strpos($c['sql'], 'FROM `mac_track_temp_ports`') !== false));
	expect($reads)->toHaveCount(2);
	expect($reads[0]['params'])->toBe(['2026-11-12 06:15:07']);
	expect($reads[1]['params'])->toBe(['2026-11-12 06:15:07']);
});

it('exchanges without validation where the server supports it', function () {
	$GLOBALS['__test_matched_returns']['db_fetch_cell_prepared'][0] = ['VERSION()', '11.8.9-MariaDB'];
	mt_ls_stage(['same']);

	mactrack_partition_load_scan(MT_LS_PLAN);

	expect(mt_ls_executed())->toContain(mactrack_partition_exchange_sql('s20261112061507', true));
});

it('recreates a missing stage table without partitions', function () {
	mt_ls_stage(null);

	mactrack_partition_load_scan(MT_LS_PLAN);

	$create = mt_ls_pos('CREATE TABLE `mac_track_ports_stage` LIKE `mac_track_ports`');
	expect(mt_ls_pos('DROP TABLE IF EXISTS `mac_track_ports_stage`'))->toBeLessThan($create);
	expect($create)->toBeLessThan(mt_ls_pos('ALTER TABLE `mac_track_ports_stage` REMOVE PARTITIONING'));
	expect(mt_ls_pos('REMOVE PARTITIONING'))->toBeLessThan(mt_ls_pos('TRUNCATE'));
});

it('recreates a stage table whose columns, indexes or collation differ', function (?array $cols, ?array $idx, ?array $tbl = null) {
	mt_ls_stage($cols, $idx, $tbl);

	mactrack_partition_load_scan(MT_LS_PLAN);

	expect(mt_ls_pos('CREATE TABLE `mac_track_ports_stage`'))->not->toBeNull();
})->with([
	'column type' => [[
		['COLUMN_NAME' => 'site_id', 'COLUMN_TYPE' => 'int(11)', 'IS_NULLABLE' => 'NO', 'COLUMN_DEFAULT' => '0'],
		['COLUMN_NAME' => 'scan_date', 'COLUMN_TYPE' => 'timestamp', 'IS_NULLABLE' => 'NO', 'COLUMN_DEFAULT' => 'current_timestamp()'],
	], null],
	'column missing' => [[
		['COLUMN_NAME' => 'site_id', 'COLUMN_TYPE' => 'int(10) unsigned', 'IS_NULLABLE' => 'NO', 'COLUMN_DEFAULT' => '0'],
	], null],
	'index' => [['same'], [
		['INDEX_NAME' => 'PRIMARY', 'SEQ_IN_INDEX' => '1', 'COLUMN_NAME' => 'site_id', 'NON_UNIQUE' => '0'],
	]],
	'table collation' => [['same'], null, [['ENGINE' => 'InnoDB', 'TABLE_COLLATION' => 'utf8mb3_general_ci']]],
]);

it('falls back to a direct load when the stage table cannot be made', function () {
	mt_ls_stage(null);
	mactrack_test_queue_return('db_execute_prepared', true);  // DROP
	mactrack_test_queue_return('db_execute_prepared', false); // CREATE
	mactrack_test_queue_return('db_fetch_assoc_prepared', [mt_ls_row(1)]);

	expect(mactrack_partition_load_scan(MT_LS_PLAN))->toBe(1);

	expect(mt_ls_pos('REMOVE PARTITIONING'))->toBeNull();
	expect(mt_ls_pos('EXCHANGE'))->toBeNull();
	expect(mt_ls_pos('INSERT IGNORE INTO `mac_track_ports` ('))->not->toBeNull();
	expect(json_decode($GLOBALS['__test_config_options']['mt_partition_status'], true)['reason'])->toBe('stage table unavailable');
	expect($GLOBALS['__test_config_options']['mt_partition_pending'] ?? '')->toBe('');
});

it('leaves a failed exchange pending with its rows in the stage table', function () {
	$GLOBALS['__test_matched_returns']['db_fetch_cell_prepared'][1] = ['GET_LOCK', '0'];
	mt_ls_stage(['same']);

	mactrack_partition_load_scan(MT_LS_PLAN);

	expect(mt_ls_pos('EXCHANGE'))->toBeNull();
	expect($GLOBALS['__test_config_options']['mt_partition_pending'])->toBe('s20261112061507');

	$status = json_decode($GLOBALS['__test_config_options']['mt_partition_status'], true);
	expect($status['pending'])->toBe('s20261112061507');
	expect($status['reason'])->toBe('exchange failed');
});

it('retries a pending exchange before it touches the stage table', function () {
	$GLOBALS['__test_config_options']['mt_partition_pending'] = 's20261112060007';
	mt_ls_stage(['same']);

	mactrack_partition_load_scan(MT_LS_PLAN);

	$retry = mt_ls_pos(mactrack_partition_exchange_sql('s20261112060007', false));
	expect($retry)->toBeLessThan(mt_ls_pos('TRUNCATE'));
	expect(mt_ls_executed())->toContain(mactrack_partition_exchange_sql('s20261112061507', false));
	expect($GLOBALS['__test_config_options']['mt_partition_pending'])->toBe('');
});

it('moves the stage rows in directly when the retry fails too', function () {
	$GLOBALS['__test_config_options']['mt_partition_pending']       = 's20261112060007';
	$GLOBALS['__test_matched_returns']['db_fetch_cell_prepared'][1] = ['GET_LOCK', '0'];
	mactrack_test_queue_return_for('db_fetch_assoc_prepared', 'FROM `mac_track_ports_stage`', [mt_ls_row(1)]);
	mactrack_test_queue_return_for('db_fetch_assoc_prepared', 'FROM `mac_track_temp_ports`', [mt_ls_row(2)]);

	$rows = mactrack_partition_load_scan(MT_LS_PLAN);

	expect($rows)->toBe(1);
	expect(mt_ls_pos('EXCHANGE'))->toBeNull();
	expect(mt_ls_pos('INSERT IGNORE INTO `mac_track_ports` ('))->toBeLessThan(mt_ls_pos('TRUNCATE TABLE `mac_track_ports_stage`'));
	expect($GLOBALS['__test_config_options']['mt_partition_pending'])->toBe('');
	expect(json_decode($GLOBALS['__test_config_options']['mt_partition_status'], true)['reason'])->toBe('pending exchange s20261112060007 failed; its rows were inserted directly');
});

it('keeps the stage rows pending when moving them in fails', function () {
	$GLOBALS['__test_config_options']['mt_partition_pending']       = 's20261112060007';
	$GLOBALS['__test_matched_returns']['db_fetch_cell_prepared'][1] = ['GET_LOCK', '0'];
	mactrack_test_queue_return_for('db_fetch_assoc_prepared', 'FROM `mac_track_ports_stage`', [mt_ls_row(1)]);
	mactrack_test_queue_return_for('db_execute_prepared', 'INSERT IGNORE INTO `mac_track_ports` (', false);

	mactrack_partition_load_scan(MT_LS_PLAN);

	expect(mt_ls_pos('TRUNCATE'))->toBeNull();
	expect($GLOBALS['__test_config_options']['mt_partition_pending'])->toBe('s20261112060007');
	expect(json_decode($GLOBALS['__test_config_options']['mt_partition_status'], true)['reason'])->toBe('pending exchange s20261112060007 failed; its rows are still in the stage table');
});

it('retries a pending exchange even when this scan loads direct', function () {
	$GLOBALS['__test_config_options']['mt_partition_pending'] = 's20261112060007';

	mactrack_partition_load_scan(['mode' => 'direct', 'partition' => 'h2026111206', 'reason' => '', 'granularity' => 'hour', 'scan_date' => '2026-11-12 06:15:07']);

	expect(mt_ls_executed())->toContain(mactrack_partition_exchange_sql('s20261112060007', false));
	expect($GLOBALS['__test_config_options']['mt_partition_pending'])->toBe('');
});

it('drops a pending value that is not a partition name', function () {
	$GLOBALS['__test_config_options']['mt_partition_pending'] = 's1;DROP';

	mactrack_partition_load_scan(['mode' => 'direct', 'partition' => null, 'reason' => '', 'granularity' => null, 'scan_date' => '2026-11-12 06:15:07']);

	expect(mt_ls_pos('EXCHANGE'))->toBeNull();
	expect($GLOBALS['__test_config_options']['mt_partition_pending'])->toBe('');
	expect($GLOBALS['__test_cacti_log'])->toHaveCount(1);
});

it('does not exchange a populated partition back into staging', function ($occupied) {
	$GLOBALS['__test_config_options']['mt_partition_pending']       = 's20261112060007';
	$GLOBALS['__test_matched_returns']['db_fetch_cell_prepared'][3] = ['SELECT EXISTS', $occupied];
	mactrack_test_queue_return_for('db_fetch_assoc_prepared', 'FROM `mac_track_ports_stage`', []);
	mactrack_test_queue_return_for('db_fetch_assoc_prepared', 'FROM `mac_track_temp_ports`', [mt_ls_row(2)]);

	expect(mactrack_partition_load_scan(MT_LS_PLAN))->toBe(1);
	expect(mt_ls_pos('EXCHANGE'))->toBeNull();
	expect($GLOBALS['__test_config_options']['mt_partition_pending'])->toBe('');
	expect(mt_ls_pos('INSERT IGNORE INTO `mac_track_ports` ('))->not->toBeNull();
})->with([1, '1']);

it('does not exchange when the destination read fails', function () {
	$GLOBALS['__test_matched_returns']['db_fetch_cell_prepared'][3] = ['SELECT EXISTS', false];

	expect(mactrack_partition_exchange('s20261112060007', mactrack_partition_server('8.4.13')))->toBeFalse();
	expect(mt_ls_pos('EXCHANGE'))->toBeNull();
	expect(array_column($GLOBALS['__test_db_calls'], 'sql'))->toContain('SELECT RELEASE_LOCK(?)');
});

it('retains pending staging after a failed recovery read', function () {
	$GLOBALS['__test_config_options']['mt_partition_pending']       = 's20261112060007';
	$GLOBALS['__test_matched_returns']['db_fetch_cell_prepared'][3] = ['SELECT EXISTS', '1'];
	mactrack_test_queue_return_for('db_fetch_assoc_prepared', 'FROM `mac_track_ports_stage`', false);

	mactrack_partition_load_scan(MT_LS_PLAN);

	expect(mt_ls_pos('TRUNCATE'))->toBeNull();
	expect(mt_ls_pos('EXCHANGE'))->toBeNull();
	expect($GLOBALS['__test_config_options']['mt_partition_pending'])->toBe('s20261112060007');
});

it('retains the pending marker when staging cleanup fails', function () {
	$GLOBALS['__test_config_options']['mt_partition_pending']       = 's20261112060007';
	$GLOBALS['__test_matched_returns']['db_fetch_cell_prepared'][3] = ['SELECT EXISTS', '1'];
	mactrack_test_queue_return_for('db_fetch_assoc_prepared', 'FROM `mac_track_ports_stage`', [mt_ls_row(1)]);
	mactrack_test_queue_return_for('db_execute_prepared', 'TRUNCATE', false);

	mactrack_partition_load_scan(MT_LS_PLAN);

	expect(mt_ls_pos('EXCHANGE'))->toBeNull();
	expect($GLOBALS['__test_config_options']['mt_partition_pending'])->toBe('s20261112060007');
});

it('releases the partition lock after an exchange', function () {
	expect(mactrack_partition_exchange('s20261112061507', mactrack_partition_server('8.4.13')))->toBeTrue();

	$sql = array_column($GLOBALS['__test_db_calls'], 'sql');
	expect(count(array_filter($sql, fn ($s) => strpos($s, 'RELEASE_LOCK') !== false)))->toBe(1);
	expect(mt_ls_executed())->toContain(mactrack_partition_exchange_sql('s20261112061507', true));
});

it('wires the poller to prepare the partition before scanning and load through it', function () {
	$poller = file_get_contents(dirname(__DIR__, 2) . '/poller_mactrack.php');

	$funcs   = file_get_contents(dirname(__DIR__, 2) . '/lib/mactrack_functions.php');
	$scan    = strpos($poller, "set_config_option('mt_scan_date', \$scan_date);");
	$prepare = strpos($poller, '$partition_plan = mactrack_partition_prepare_scan($scan_date);');
	$load    = strpos($poller, '$loaded = mactrack_partition_load_scan($partition_plan);');

	// The poller includes mactrack_functions.php, which includes both partition files.
	expect($poller)->toContain("lib/mactrack_functions.php'");
	expect($funcs)->toContain("include_once(__DIR__ . '/mactrack_partitions.php');");
	expect($funcs)->toContain("include_once(__DIR__ . '/mactrack_partition_ops.php');");
	expect($prepare)->toBeGreaterThan($scan);
	expect($load)->toBeGreaterThan($prepare);
	expect($poller)->not->toContain('$loaded = mactrack_ports_load();');
});

/**
 * Builds one temp-table row.
 *
 * @param  int                  $n Distinguishes rows.
 * @return array<string, mixed>
 */
function mt_ls_row(int $n): array {
	return [
		'site_id'    => 1, 'device_id' => $n, 'hostname' => "sw$n", 'dns_hostname' => '', 'device_name' => "sw$n",
		'vlan_id'    => '10', 'vlan_name' => 'V10', 'mac_address' => sprintf('00:11:22:33:44:%02X', $n), 'vendor_mac' => '00:11:22',
		'ip_address' => "10.0.0.$n", 'port_number' => (string) $n, 'port_name' => "Gi0/$n", 'scan_date' => '2026-11-12 06:15:07', 'authorized' => 0,
	];
}

it('reports direct load failures instead of allowing temp cleanup', function ($operation) {
	mactrack_test_queue_return_for('db_fetch_assoc_prepared', 'FROM `mac_track_temp_ports`', $operation === 'read' ? false : [mt_ls_row(1)]);
	if ($operation === 'write') {
		mactrack_test_queue_return_for('db_execute_prepared', 'INSERT IGNORE INTO `mac_track_ports`', false);
	}

	expect(mactrack_partition_load_scan(['mode' => 'direct', 'partition' => null, 'scan_date' => MT_LS_PLAN['scan_date']]))->toBeFalse();
	expect(mt_ls_pos('EXCHANGE'))->toBeNull();
})->with(['read', 'write']);

it('does not exchange an incomplete stage or mark it pending', function ($operation) {
	mt_ls_stage(['same']);
	mactrack_test_queue_return('db_fetch_assoc_prepared', $operation === 'read' ? false : [mt_ls_row(1)]);
	if ($operation === 'write') {
		mactrack_test_queue_return_for('db_execute_prepared', 'INSERT IGNORE INTO `mac_track_ports_stage`', false);
	}

	expect(mactrack_partition_load_scan(MT_LS_PLAN))->toBeFalse();
	expect(mt_ls_pos('EXCHANGE'))->toBeNull();
	expect(read_config_option('mt_partition_pending'))->toBe('');
})->with(['read', 'write']);

it('stops when stage cleanup fails before loading a scan', function () {
	mt_ls_stage(['same']);
	mactrack_test_queue_return_for('db_execute_prepared', 'TRUNCATE TABLE `mac_track_ports_stage`', false);

	expect(mactrack_partition_load_scan(MT_LS_PLAN))->toBeFalse();
	expect(mt_ls_pos('INSERT IGNORE'))->toBeNull();
	expect(mt_ls_pos('EXCHANGE'))->toBeNull();
});

it('reports failures copying older temp rows after a complete exchange', function ($operation) {
	mt_ls_stage(['same']);
	mactrack_test_queue_return_for('db_fetch_assoc_prepared', 'WHERE scan_date <> ?', $operation === 'read' ? false : [mt_ls_row(3)]);
	if ($operation === 'write') {
		mactrack_test_queue_return_for('db_execute_prepared', 'INSERT IGNORE INTO `mac_track_ports` ', false);
	}

	expect(mactrack_partition_load_scan(MT_LS_PLAN))->toBeFalse();
	expect(mt_ls_pos('EXCHANGE'))->not->toBeNull();
})->with(['read', 'write']);

it('does not exchange when a later staging page cannot be read', function () {
	mt_ls_stage(['same']);
	mactrack_test_queue_return('db_fetch_assoc_prepared', array_map('mt_ls_row', range(1, 1000)));
	mactrack_test_queue_return('db_fetch_assoc_prepared', false);

	expect(mactrack_partition_load_scan(MT_LS_PLAN))->toBeFalse();
	expect(mt_ls_pos('INSERT IGNORE INTO `mac_track_ports_stage`'))->not->toBeNull();
	expect(mt_ls_pos('EXCHANGE'))->toBeNull();
});


it('retains temp rows and skips aggregation when the poller load fails', function () {
	require_once dirname(__DIR__) . '/Support/load_function.php';
	mactrack_test_load_function('poller_mactrack.php', 'collect_mactrack_data');
	mactrack_test_load_function('poller_mactrack.php', 'mactrack_reap_unstarted_scanners');
	mactrack_test_load(dirname(__DIR__, 2) . '/lib/mactrack_activity.php');
	$GLOBALS['debug'] = false;
	$GLOBALS['max_run_duration'] = 3600;
	$GLOBALS['__test_config_options']['mt_processes'] = 1;
	$GLOBALS['__test_config_options']['mt_dns_prime_interval'] = 300;
	mactrack_test_queue_return_for('db_fetch_cell', "name='mt_processes'", '1');
	mactrack_test_queue_return_for('db_fetch_cell', 'COUNT(*) FROM mac_track_processes', '0');
	mactrack_test_queue_return_for('db_fetch_assoc', 'SELECT device_id FROM mac_track_devices', [['device_id' => 1]]);
	mactrack_test_queue_return_for('db_fetch_assoc_prepared', 'FROM `mac_track_temp_ports`', false);

	collect_mactrack_data(microtime(true));

	$sql = implode("\n", array_column($GLOBALS['__test_db_calls'], 'sql'));
	expect($sql)->not->toContain('TRUNCATE TABLE mac_track_temp_ports', 'INSERT IGNORE INTO mac_track_scan_dates', 'INSERT INTO mac_track_aggregated_ports');
	expect(implode("\n", $GLOBALS['__test_cacti_log']))->toContain('scan results retained');
});
