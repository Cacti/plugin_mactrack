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
 * Retention on the scan-date layout drops whole partitions whose upper
 * bound is at or before the cutoff, never dMaxValue and never the newest
 * bounded partition, then prunes the scan dates below the highest dropped
 * bound. Then partitions are created two days ahead, so a full day stays
 * created until the next run: hourly or daily partitions, or daily slots
 * that per-scan partitions are carved from.
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
	$GLOBALS['__test_config_options']  = [
		'mt_collection_timing'     => '15',
		'mt_partition_granularity' => 'scan',
		'mt_data_retention'        => '7',
	];
	$GLOBALS['__test_cacti_log']       = [];
	$GLOBALS['database_default']       = 'cacti';
	$GLOBALS['database_sessions']      = [];
});

// 2026-11-12 06:15:07 UTC; seven days earlier is 1793859307.
const MT_PM_NOW    = 1794464107;
const MT_PM_CUTOFF = 1793859307;

/**
 * Queues the database answers maintain() asks for.
 *
 * @param  array $parts [name, description] pairs, dMaxValue last.
 * @param  array $opts  version, lock, stranded, expression.
 * @return void
 */
function mt_pm_db(array $parts, array $opts = []): void {
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
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'GET_LOCK', $opts['lock'] ?? '1');
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'lock_wait_timeout', '50');
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'FROM_UNIXTIME(?)', $opts['from_unixtime'] ?? '2026-11-05 00:00:00');
}

/**
 * Returns the partition DDL statements that were run.
 *
 * @return string[]
 */
function mt_pm_ddl(): array {
	return array_values(array_filter(
		array_column(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_execute_prepared'), 'sql'),
		fn ($sql) => strpos($sql, 'ALTER TABLE') === 0
	));
}

/**
 * Returns the bound parameters of every scan-date prune.
 *
 * @return array
 */
function mt_pm_prunes(): array {
	return array_values(array_column(array_filter($GLOBALS['__test_db_calls'], fn ($c) => strpos($c['sql'], 'DELETE FROM mac_track_scan_dates') !== false), 'params'));
}

/**
 * Counts GET_LOCK and RELEASE_LOCK calls.
 *
 * @return array{0: int, 1: int}
 */
function mt_pm_locks(): array {
	$sql = array_column($GLOBALS['__test_db_calls'], 'sql');

	return [
		count(array_filter($sql, fn ($s) => strpos($s, 'GET_LOCK') !== false)),
		count(array_filter($sql, fn ($s) => strpos($s, 'RELEASE_LOCK') !== false)),
	];
}

/**
 * Returns the maintenance record from the status option.
 *
 * @return array
 */
function mt_pm_status(): array {
	return json_decode($GLOBALS['__test_config_options']['mt_partition_status'], true)['maintenance'];
}

it('drops partitions at or before the cutoff by their bound, whatever their name', function () {
	mt_pm_db([
		['s20261101000000', '1793491201'],
		['r20261103000000', (string) (MT_PM_CUTOFF - 100)],
		['h2026110500', (string) MT_PM_CUTOFF],
		['h2026110506', (string) (MT_PM_CUTOFF + 1)],
		['s20261112061507', '1794464108'],
		['dMaxValue', 'MAXVALUE'],
	]);

	$result = mactrack_partition_maintain(7, MT_PM_NOW);

	expect($result['dropped'])->toBe(['s20261101000000', 'r20261103000000', 'h2026110500']);
	expect(mt_pm_ddl()[0])->toBe(mactrack_partition_drop_sql(['s20261101000000', 'r20261103000000', 'h2026110500']));
	expect(mt_pm_locks())->toBe([1, 1]);
});

it('prunes scan dates below the highest dropped bound, as the server reads it', function () {
	mt_pm_db([
		['s20261101000000', '1793491201'],
		['h2026110500', (string) MT_PM_CUTOFF],
		['s20261112061507', '1794464108'],
		['dMaxValue', 'MAXVALUE'],
	], ['from_unixtime' => '2026-11-05 06:15:07']);

	mactrack_partition_maintain(7, MT_PM_NOW);

	$conv = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => strpos($c['sql'], 'FROM_UNIXTIME(?)') !== false));
	expect($conv[0]['params'])->toBe([MT_PM_CUTOFF]);
	expect(mt_pm_prunes())->toBe([['2026-11-05 06:15:07']]);
});

it('keeps the newest bounded partition even when it has expired', function () {
	mt_pm_db([
		['s20261101000000', '1793491201'],
		['s20261102000000', '1793577601'],
		['dMaxValue', 'MAXVALUE'],
	]);

	expect(mactrack_partition_maintain(7, MT_PM_NOW)['dropped'])->toBe(['s20261101000000']);
});

it('drops nothing without a retention period', function (int $days) {
	mt_pm_db([
		['s20261101000000', '1793491201'],
		['s20261102000000', '1793577601'],
		['dMaxValue', 'MAXVALUE'],
	]);

	expect(mactrack_partition_maintain($days, MT_PM_NOW)['dropped'])->toBe([]);
	expect(array_filter(mt_pm_ddl(), fn ($sql) => strpos($sql, 'DROP PARTITION') !== false))->toBe([]);
	expect(mt_pm_prunes())->toBe([]);
})->with([0, -1]);

it('prunes nothing and says why when the drop fails', function () {
	mt_pm_db([
		['s20261101000000', '1793491201'],
		['s20261112061507', '1794464108'],
		['dMaxValue', 'MAXVALUE'],
	]);
	mactrack_test_queue_return('db_execute_prepared', true);  // SET 2
	mactrack_test_queue_return('db_execute_prepared', false); // attempts
	mactrack_test_queue_return('db_execute_prepared', false);
	mactrack_test_queue_return('db_execute_prepared', false);

	$result = mactrack_partition_maintain(7, MT_PM_NOW);

	expect($result['dropped'])->toBe([]);
	expect($result['reason'])->toBe('drop failed');
	expect(mt_pm_prunes())->toBe([]);
	expect(mt_pm_status()['reason'])->toBe('drop failed');
	expect(mt_pm_locks())->toBe([1, 1]);
});

it('runs no DDL while another session holds the partition lock', function () {
	mt_pm_db([
		['s20261101000000', '1793491201'],
		['s20261112061507', '1794464108'],
		['dMaxValue', 'MAXVALUE'],
	], ['lock' => '0']);

	$result = mactrack_partition_maintain(7, MT_PM_NOW);

	expect($result['reason'])->toBe('partition lock busy');
	expect(mt_pm_ddl())->toBe([]);
	expect(mt_pm_locks())->toBe([1, 0]);
});

it('leaves other layouts to their own maintenance', function (array $parts, string $expression, string $state) {
	mt_pm_db($parts, ['expression' => $expression]);

	$result = mactrack_partition_maintain(7, MT_PM_NOW);

	expect($result['reason'])->toContain($state);
	expect(mt_pm_ddl())->toBe([]);
	expect(mt_pm_locks())->toBe([0, 0]);
})->with([
	'none'    => [[], 'unix_timestamp(`scan_date`)', 'none'],
	'legacy'  => [[['d20261111', '740297'], ['dMaxValue', 'MAXVALUE']], 'to_days(`scan_date`)', 'legacy'],
	'invalid' => [[['d20261112', '1794528000']], 'unix_timestamp(`scan_date`)', 'invalid'],
]);

it('creates two days ahead on the hourly layout', function () {
	$GLOBALS['__test_config_options']['mt_partition_granularity'] = 'hour';
	mt_pm_db([
		['h2026111205', '1794463200'],
		['dMaxValue', 'MAXVALUE'],
	]);

	$result = mactrack_partition_maintain(7, MT_PM_NOW);

	expect($result['created'])->toHaveCount(49);
	expect($result['created'][0])->toBe('h2026111206');
	expect(end($result['created']))->toBe('h2026111406');
	expect(array_slice(mt_pm_ddl(), -1)[0])->toBe(mactrack_partition_split_sql('h2026111406', 1794639600));
});

it('skips periods that already exist', function () {
	$GLOBALS['__test_config_options']['mt_partition_granularity'] = 'hour';
	mt_pm_db([
		['h2026111206', '1794466800'],
		['dMaxValue', 'MAXVALUE'],
	]);

	expect(mactrack_partition_maintain(7, MT_PM_NOW)['created'])->toHaveCount(48)
		->not->toContain('h2026111206');
});

it('creates the current and next two days ahead on the daily layout', function () {
	$GLOBALS['__test_config_options']['mt_partition_granularity'] = 'day';
	mt_pm_db([['dMaxValue', 'MAXVALUE']]);

	expect(mactrack_partition_maintain(7, MT_PM_NOW)['created'])->toBe(['d20261112', 'd20261113', 'd20261114']);
	expect(mt_pm_ddl()[2])->toBe(mactrack_partition_split_sql('d20261114', 1794700800));
});

it('still covers a full day ahead at the next daily run', function (string $granularity) {
	// Maintenance at 23:30 UTC, the worst case for daily periods.
	$run = 1794353400; // 2026-11-10 23:30:00 UTC

	$GLOBALS['__test_config_options']['mt_partition_granularity'] = $granularity;
	mt_pm_db([['dMaxValue', 'MAXVALUE']]);

	mactrack_partition_maintain(7, $run);

	$bounds = array_map(fn ($sql) => (int) preg_replace('/.*LESS THAN \((\d+)\).*/', '$1', $sql), mt_pm_ddl());
	expect(max($bounds))->toBeGreaterThanOrEqual($run + 86400 + 86400);
})->with(['hour', 'day', 'scan']);

it('continues with weekly partitions after daily ones when only the setting changes', function () {
	$GLOBALS['__test_config_options']['mt_partition_granularity'] = 'week';
	mt_pm_db([['d20261112', '1794528000'], ['dMaxValue', 'MAXVALUE']]);

	$result = mactrack_partition_maintain(7, MT_PM_NOW);

	// The first weekly partition starts at the last daily bound, mid-week.
	expect($result['created'])->toBe(['w20261109', 'w20261116']);
	expect(mt_pm_ddl()[0])->toBe(mactrack_partition_split_sql('w20261109', 1794787200));
});

it('falls back to hourly ahead creation when per-scan partitions would pass the cap', function () {
	// 15-minute scans for 7 days are 780 partitions, over MySQL's 512.
	mt_pm_db([['dMaxValue', 'MAXVALUE']], ['version' => '8.4.13']);

	expect(mactrack_partition_maintain(7, MT_PM_NOW)['created'])->toHaveCount(49);
});

it('creates daily slots ahead for per-scan partitions to be carved from', function () {
	mt_pm_db([['dMaxValue', 'MAXVALUE']]);

	expect(mactrack_partition_maintain(7, MT_PM_NOW)['created'])->toBe(['a20261112', 'a20261113', 'a20261114']);
	expect(mt_pm_ddl()[0])->toBe(mactrack_partition_split_sql('a20261112', 1794528000));
});

it('creates nothing ahead while dMaxValue holds rows', function () {
	$GLOBALS['__test_config_options']['mt_partition_granularity'] = 'hour';
	mt_pm_db([['dMaxValue', 'MAXVALUE']], ['stranded' => '1794460000']);

	$result = mactrack_partition_maintain(7, MT_PM_NOW);

	expect($result['created'])->toBe([]);
	expect($result['reason'])->toBe('dMaxValue holds rows');
});

it('creates nothing ahead at the partition cap', function () {
	$GLOBALS['__test_config_options']['mt_partition_granularity'] = 'hour';
	$parts                                                        = [];

	for ($i = 0; $i < 511; $i++) {
		$parts[] = [sprintf('s%014d', $i + 1), (string) (1794000000 + $i)];
	}

	$parts[] = ['dMaxValue', 'MAXVALUE'];
	mt_pm_db($parts, ['version' => '8.4.13']);

	$result = mactrack_partition_maintain(0, MT_PM_NOW);

	expect($result['created'])->toBe([]);
	expect($result['reason'])->toBe('partition cap 512 reached');
});

it('stops creating ahead after a failed split and still releases the lock', function () {
	$GLOBALS['__test_config_options']['mt_partition_granularity'] = 'hour';
	mt_pm_db([['dMaxValue', 'MAXVALUE']]);
	mactrack_test_queue_return('db_execute_prepared', true);  // SET 2
	mactrack_test_queue_return('db_execute_prepared', false);
	mactrack_test_queue_return('db_execute_prepared', false);
	mactrack_test_queue_return('db_execute_prepared', false);

	$result = mactrack_partition_maintain(7, MT_PM_NOW);

	expect($result['created'])->toBe([]);
	expect($result['reason'])->toBe('split failed');
	expect(count(mt_pm_ddl()))->toBe(3);
	expect(mt_pm_locks())->toBe([1, 1]);
});

it('records the outcome under maintenance in the status', function () {
	mt_pm_db([
		['s20261101000000', '1793491201'],
		['s20261112061507', '1794464108'],
		['dMaxValue', 'MAXVALUE'],
	]);

	mactrack_partition_maintain(7, MT_PM_NOW);

	$status = mt_pm_status();
	expect($status['dropped'])->toBe(['s20261101000000']);
	expect($status['created'])->toBe(['a20261112', 'a20261113', 'a20261114']);
	expect($status['reason'])->toBe('');
	expect($status['time'])->toBeInt();
});

it('retires partitions instead of deleting rows on the scan-date layout', function () {
	set_config_option('mt_data_retention', '7');
	mt_pm_db([
		['s20261101000000', '1793491201'],
		['s20261112061507', '1794464108'],
		['dMaxValue', 'MAXVALUE'],
	]);

	perform_mactrack_db_maint();

	$sql = array_column($GLOBALS['__test_db_calls'], 'sql');
	expect(mt_pm_ddl()[0])->toBe(mactrack_partition_drop_sql(['s20261101000000']));
	expect(array_filter($sql, fn ($s) => strpos($s, 'DELETE QUICK FROM mac_track_ports') !== false))->toBe([]);
	// Only splits of the empty dMaxValue, which move no rows.
	expect(array_filter($sql, fn ($s) => strpos($s, 'REORGANIZE') !== false && strpos($s, 'REORGANIZE PARTITION `dMaxValue`') === false))->toBe([]);
});

it('deletes old rows without OPTIMIZE on an unpartitioned table', function () {
	set_config_option('mt_data_retention', '7');

	perform_mactrack_db_maint();

	$sql = array_column($GLOBALS['__test_db_calls'], 'sql');
	expect(array_filter($sql, fn ($s) => strpos($s, 'DELETE QUICK FROM mac_track_ports') !== false))->toHaveCount(1);
	expect(array_filter($sql, fn ($s) => strpos($s, 'OPTIMIZE TABLE mac_track_ports') !== false))->toBe([]);
	expect($GLOBALS['__test_config_options'])->not->toHaveKey('mt_partition_status');
});

it('deletes old rows and records why on an invalid layout', function () {
	set_config_option('mt_data_retention', '7');
	mt_pm_db([['d20261112', '1794528000']]);

	perform_mactrack_db_maint();

	$sql = array_column($GLOBALS['__test_db_calls'], 'sql');
	expect(array_filter($sql, fn ($s) => strpos($s, 'DELETE QUICK FROM mac_track_ports') !== false))->toHaveCount(1);
	expect(mt_pm_ddl())->toBe([]);
	expect(mt_pm_status()['reason'])->toContain('layout invalid');
});

it('drops expired weeks and creates the next week before data arrives', function () {
	$GLOBALS['__test_config_options']['mt_partition_granularity'] = 'week';
	mt_pm_db([
		['w20261026', (string) strtotime('2026-11-02 UTC')],
		['w20261102', (string) strtotime('2026-11-09 UTC')],
		['w20261109', (string) strtotime('2026-11-16 UTC')],
		['dMaxValue', 'MAXVALUE'],
	]);

	$r = mactrack_partition_maintain(7, MT_PM_NOW);

	expect($r['dropped'])->toBe(['w20261026']);
	expect($r['created'])->toBe(['w20261116']);
});
