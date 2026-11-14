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
 * Before a scan writes any rows, its partition is split off an empty
 * dMaxValue, or carved off an empty daily slot created ahead, so the split
 * moves nothing. Rows stranded in dMaxValue by an
 * earlier failure are moved out first by one recovery split. Anything
 * that prevents the split sends the scan straight into mac_track_ports.
 */

beforeAll(function () {
	mactrack_test_load(dirname(__DIR__, 2) . '/lib/mactrack_partitions.php');
	mactrack_test_load(dirname(__DIR__, 2) . '/lib/mactrack_partition_ops.php');
});

beforeEach(function () {
	$GLOBALS['__test_db_calls']        = [];
	$GLOBALS['__test_next_returns']    = [];
	$GLOBALS['__test_matched_returns'] = [];
	$GLOBALS['__test_config_options']  = [
		'mt_collection_timing'     => '15',
		'mt_partition_granularity' => 'scan',
		'mt_data_retention'        => '7',
	];
	$GLOBALS['__test_cacti_log']       = [];
	$GLOBALS['database_default']       = 'cacti';
	$GLOBALS['database_sessions']      = [];
});

// 2026-11-12 06:15:07 UTC
const MT_PP_TS   = 1794464107;
const MT_PP_DATE = '2026-11-12 06:15:07';

/**
 * Queues the database answers prepare_scan() asks for.
 *
 * @param  array $parts [name, description] pairs for information_schema; [] for unpartitioned.
 * @param  array $opts  version, expression, lock, stranded (MAX epoch in dMaxValue or null).
 * @return void
 */
function mt_pp_db(array $parts, array $opts = []): void {
	$rows = [];

	foreach (array_values($parts) as $i => $p) {
		$rows[] = [
			'PARTITION_NAME'             => $p[0],
			'PARTITION_ORDINAL_POSITION' => (string) ($i + 1),
			'PARTITION_METHOD'           => 'RANGE',
			'PARTITION_EXPRESSION'       => $opts['expression'] ?? 'unix_timestamp(`scan_date`)',
			'PARTITION_DESCRIPTION'      => $p[1],
			'TABLE_ROWS'                 => '0',
		];
	}

	mactrack_test_queue_return_for('db_fetch_assoc_prepared', 'information_schema.PARTITIONS', $rows);
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'VERSION()', $opts['version'] ?? '10.5.29-MariaDB');
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'MAX(UNIX_TIMESTAMP(scan_date))', $opts['stranded'] ?? null);
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'SELECT UNIX_TIMESTAMP(?)', (string) ($opts['epoch'] ?? MT_PP_TS));
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'GET_LOCK', $opts['lock'] ?? '1');
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'lock_wait_timeout', '50');
}

/**
 * Returns the partition DDL statements that were run.
 *
 * @return string[]
 */
function mt_pp_ddl(): array {
	return array_values(array_filter(
		array_column(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_execute_prepared'), 'sql'),
		fn ($sql) => strpos($sql, 'ALTER TABLE') === 0
	));
}

/**
 * Counts GET_LOCK and RELEASE_LOCK calls.
 *
 * @return array{0: int, 1: int}
 */
function mt_pp_locks(): array {
	$sql = array_column($GLOBALS['__test_db_calls'], 'sql');

	return [
		count(array_filter($sql, fn ($s) => strpos($s, 'GET_LOCK') !== false)),
		count(array_filter($sql, fn ($s) => strpos($s, 'RELEASE_LOCK') !== false)),
	];
}

it('loads straight into an unpartitioned or legacy table without DDL', function (array $parts, string $expression, string $state) {
	mt_pp_db($parts, ['expression' => $expression]);

	$plan = mactrack_partition_prepare_scan(MT_PP_DATE);

	expect($plan['mode'])->toBe('direct');
	expect($plan['reason'])->toContain($state);
	expect(mt_pp_ddl())->toBe([]);
	expect(mt_pp_locks())->toBe([0, 0]);
})->with([
	'unpartitioned' => [[], 'unix_timestamp(`scan_date`)', 'none'],
	'legacy'        => [[['d20261111', '740297'], ['dMaxValue', 'MAXVALUE']], 'to_days(`scan_date`)', 'legacy'],
]);

it('stops DDL on an invalid layout and records why', function () {
	mt_pp_db([['d20261112', '1794528000']]);

	$plan = mactrack_partition_prepare_scan(MT_PP_DATE);

	expect($plan['mode'])->toBe('direct');
	expect($plan['reason'])->toContain('invalid');
	expect(mt_pp_ddl())->toBe([]);
	expect(json_decode($GLOBALS['__test_config_options']['mt_partition_status'], true)['reason'])->toContain('dMaxValue');
});

it('splits the scan its own partition and asks for an exchange', function () {
	mt_pp_db([['s20261112060007', '1794463208'], ['dMaxValue', 'MAXVALUE']]);

	$plan = mactrack_partition_prepare_scan(MT_PP_DATE);

	expect($plan)->toBe(['mode' => 'exchange', 'partition' => 's20261112061507', 'reason' => '', 'granularity' => 'scan', 'scan_date' => '2026-11-12 06:15:07']);
	expect(mt_pp_ddl())->toBe([mactrack_partition_split_sql('s20261112061507', 1794464108)]);
	expect(mt_pp_locks())->toBe([1, 1]);
});

it('asks the server for the epoch of the scan date', function () {
	mt_pp_db([['dMaxValue', 'MAXVALUE']]);

	mactrack_partition_prepare_scan(MT_PP_DATE);

	$ask = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => strpos($c['sql'], 'SELECT UNIX_TIMESTAMP(?)') !== false));
	expect($ask[0]['params'])->toBe([MT_PP_DATE]);
});

it('falls back to hourly partitions when per-scan ones exceed the cap, and loads straight in', function () {
	$GLOBALS['__test_config_options']['mt_data_retention'] = '14';
	mt_pp_db([['dMaxValue', 'MAXVALUE']]);

	$plan = mactrack_partition_prepare_scan(MT_PP_DATE);

	expect($plan['mode'])->toBe('direct');
	expect($plan['granularity'])->toBe('hour');
	expect(mt_pp_ddl())->toBe([mactrack_partition_split_sql('h2026111206', 1794466800)]);

	$status = json_decode($GLOBALS['__test_config_options']['mt_partition_status'], true);
	expect($status['granularity'])->toBe('hour');
	expect($status['fell_back'])->toBeTrue();
});

it('uses the configured size and ignores an unknown one', function (string $setting, string $granularity) {
	$GLOBALS['__test_config_options']['mt_partition_granularity'] = $setting;
	mt_pp_db([['dMaxValue', 'MAXVALUE']]);

	expect(mactrack_partition_prepare_scan(MT_PP_DATE)['granularity'])->toBe($granularity);
})->with([
	['day', 'day'],
	['hour', 'hour'],
	['weekly', 'week'],
]);

it('assumes 15-minute scans and 14 days when the settings are not numbers', function () {
	$GLOBALS['__test_config_options']['mt_collection_timing'] = 'disabled';
	$GLOBALS['__test_config_options']['mt_data_retention']    = '';
	mt_pp_db([['dMaxValue', 'MAXVALUE']]);

	mactrack_partition_prepare_scan(MT_PP_DATE);

	$status = json_decode($GLOBALS['__test_config_options']['mt_partition_status'], true);
	expect($status['projected'])->toBe(433); // hourly: 24 x 18 + 1, after 96 x 15 + 18 + 1 exceeds 1024
});

it('carves the scan its partition from the empty slot created ahead, moving no rows', function () {
	mt_pp_db([['s20261112060007', '1794463208'], ['a20261112', '1794528000'], ['a20261113', '1794614400'], ['dMaxValue', 'MAXVALUE']]);
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'PARTITION (`a20261112`) LIMIT 1', '0');

	$plan = mactrack_partition_prepare_scan(MT_PP_DATE);

	expect($plan)->toBe(['mode' => 'exchange', 'partition' => 's20261112061507', 'reason' => '', 'granularity' => 'scan', 'scan_date' => '2026-11-12 06:15:07']);
	expect(mt_pp_ddl())->toBe([mactrack_partition_carve_sql('a20261112', 1794528000, 's20261112061507', 1794464108)]);
	expect(array_filter(mt_pp_ddl(), fn ($sql) => strpos($sql, 'dMaxValue') !== false))->toBe([]);
	expect(mt_pp_locks())->toBe([1, 1]);
});

it('loads straight into a slot created ahead that already holds rows', function () {
	mt_pp_db([['a20261112', '1794528000'], ['dMaxValue', 'MAXVALUE']]);
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'PARTITION (`a20261112`) LIMIT 1', '1');

	$plan = mactrack_partition_prepare_scan(MT_PP_DATE);

	expect($plan['mode'])->toBe('direct');
	expect($plan['partition'])->toBe('a20261112');
	expect(mt_pp_ddl())->toBe([]);
	expect(mt_pp_locks())->toBe([1, 1]);
});

it('never carves a per-scan partition out of another scan\'s partition', function () {
	mt_pp_db([['s20261112063007', '1794465008'], ['dMaxValue', 'MAXVALUE']]);

	$plan = mactrack_partition_prepare_scan(MT_PP_DATE);

	expect($plan['mode'])->toBe('direct');
	expect($plan['partition'])->toBe('s20261112063007');
	expect(mt_pp_ddl())->toBe([]);
	expect(array_filter(array_column($GLOBALS['__test_db_calls'], 'sql'), fn ($sql) => strpos($sql, 'LIMIT 1') !== false))->toBe([]);
});

it('loads straight into a partition that already covers the scan', function () {
	mt_pp_db([['h2026111205', '1794463200'], ['h2026111206', '1794466800'], ['dMaxValue', 'MAXVALUE']]);
	$GLOBALS['__test_config_options']['mt_partition_granularity'] = 'hour';

	$plan = mactrack_partition_prepare_scan(MT_PP_DATE);

	expect($plan['mode'])->toBe('direct');
	expect($plan['partition'])->toBe('h2026111206');
	expect(mt_pp_ddl())->toBe([]);
	expect(mt_pp_locks())->toBe([1, 1]);
});

it('moves rows stranded in dMaxValue out first, in one recovery split', function () {
	mt_pp_db([['s20261112060007', '1794463208'], ['dMaxValue', 'MAXVALUE']], ['stranded' => '1794463507']);

	$plan = mactrack_partition_prepare_scan(MT_PP_DATE);

	expect(mt_pp_ddl())->toBe([
		mactrack_partition_split_sql('r20261112060507', 1794463508),
		mactrack_partition_split_sql('s20261112061507', 1794464108),
	]);
	expect($plan['mode'])->toBe('exchange');
	expect(implode(' ', $GLOBALS['__test_cacti_log']))->toContain('r20261112060507');
});

it('loads straight in when the stranded rows reach past this scan', function () {
	mt_pp_db([['dMaxValue', 'MAXVALUE']], ['stranded' => (string) (MT_PP_TS + 60)]);

	$plan = mactrack_partition_prepare_scan(MT_PP_DATE);

	expect($plan['mode'])->toBe('direct');
	expect($plan['partition'])->toBe('r' . gmdate('YmdHis', MT_PP_TS + 60));
	expect(count(mt_pp_ddl()))->toBe(1); // the recovery split only
});

it('loads straight in when the recovery split fails', function () {
	mt_pp_db([['dMaxValue', 'MAXVALUE']], ['stranded' => '1794463507']);
	mactrack_test_queue_return('db_execute_prepared', true);  // SET 2
	mactrack_test_queue_return('db_execute_prepared', false); // split attempt 1
	mactrack_test_queue_return('db_execute_prepared', false);
	mactrack_test_queue_return('db_execute_prepared', false);

	$plan = mactrack_partition_prepare_scan(MT_PP_DATE);

	expect($plan['mode'])->toBe('direct');
	expect($plan['reason'])->toContain('recovery');
	expect(mt_pp_locks())->toBe([1, 1]);
});

it('loads straight in when the lock is busy', function () {
	mt_pp_db([['dMaxValue', 'MAXVALUE']], ['lock' => '0']);

	$plan = mactrack_partition_prepare_scan(MT_PP_DATE);

	expect($plan['mode'])->toBe('direct');
	expect($plan['reason'])->toContain('lock');
	expect(mt_pp_ddl())->toBe([]);
	expect(mt_pp_locks())->toBe([1, 0]);
});

it('loads straight in when the split fails, and releases the lock', function () {
	mt_pp_db([['dMaxValue', 'MAXVALUE']]);
	mactrack_test_queue_return('db_execute_prepared', true);
	mactrack_test_queue_return('db_execute_prepared', false);
	mactrack_test_queue_return('db_execute_prepared', false);
	mactrack_test_queue_return('db_execute_prepared', false);

	$plan = mactrack_partition_prepare_scan(MT_PP_DATE);

	expect($plan['mode'])->toBe('direct');
	expect($plan['reason'])->toContain('split');
	expect(mt_pp_locks())->toBe([1, 1]);
});

it('stops at the cap when a recovery split takes the last free partition', function () {
	$parts = [];

	for ($i = 0; $i < 1022; $i++) {
		$parts[] = ['s' . gmdate('YmdHis', 1794000000 + $i * 300), (string) (1794000001 + $i * 300)];
	}

	$parts[] = ['dMaxValue', 'MAXVALUE'];
	mt_pp_db($parts, ['stranded' => '1794400000']);

	$plan = mactrack_partition_prepare_scan(MT_PP_DATE);

	expect(mt_pp_ddl())->toBe([mactrack_partition_split_sql('r20261111122640', 1794400001)]);
	expect($plan['mode'])->toBe('direct');
	expect($plan['reason'])->toBe('partition cap 1024 reached');
});

it('loads straight in when the partition cap is reached', function () {
	$parts = [];

	for ($i = 0; $i < 1023; $i++) {
		$parts[] = ['s' . gmdate('YmdHis', 1794000000 + $i * 900), (string) (1794000001 + $i * 900)];
	}

	$parts[] = ['dMaxValue', 'MAXVALUE'];
	mt_pp_db($parts);

	$plan = mactrack_partition_prepare_scan(MT_PP_DATE);

	expect($plan['mode'])->toBe('direct');
	expect($plan['reason'])->toContain('cap');
	expect(mt_pp_ddl())->toBe([]);
});

it('loads straight in when the server cannot convert the scan date', function () {
	mt_pp_db([['dMaxValue', 'MAXVALUE']], ['epoch' => '0']);

	$plan = mactrack_partition_prepare_scan(MT_PP_DATE);

	expect($plan['mode'])->toBe('direct');
	expect($plan['reason'])->toContain('scan date');
	expect(mt_pp_locks())->toBe([0, 0]);
});

it('loads straight in without DDL when the server version cannot be read', function () {
	mt_pp_db([['dMaxValue', 'MAXVALUE']], ['version' => '']);

	$plan = mactrack_partition_prepare_scan(MT_PP_DATE);

	expect($plan['mode'])->toBe('direct');
	expect($plan['reason'])->toContain('Unreadable server version');
	expect(mt_pp_ddl())->toBe([]);
	expect(mt_pp_locks())->toBe([0, 0]);
});
