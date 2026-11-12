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
 * bound. Hourly and daily layouts also get the current and next period
 * created ahead, so the poller rarely has to split.
 */

beforeAll(function () {
	mactrack_test_load(dirname(__DIR__, 2) . '/lib/mactrack_functions.php');
	mactrack_test_load(dirname(__DIR__, 2) . '/lib/mactrack_partitions.php');
	mactrack_test_load(dirname(__DIR__, 2) . '/lib/mactrack_partition_ops.php');
});

beforeEach(function () {
	$GLOBALS['__test_db_calls']        = array();
	$GLOBALS['__test_next_returns']    = array();
	$GLOBALS['__test_matched_returns'] = array();
	$GLOBALS['__test_config_options']  = array(
		'mt_collection_timing' => '15',
		'mt_data_retention'    => '7',
	);
	$GLOBALS['__test_cacti_log']       = array();
	$GLOBALS['database_default']       = 'cacti';
	$GLOBALS['database_sessions']      = array();
});

// 2026-11-12 06:15:07 UTC; seven days earlier is 1793859307.
const MT_PM_NOW    = 1794464107;
const MT_PM_CUTOFF = 1793859307;

/**
 * Queues the database answers maintain() asks for.
 *
 * @param array $parts [name, description] pairs, dMaxValue last.
 * @param array $opts  version, lock, stranded, expression.
 * @return void
 */
function mt_pm_db(array $parts, array $opts = array()): void {
	$rows = array();

	foreach (array_values($parts) as $i => $p) {
		$rows[] = array(
			'PARTITION_NAME'             => $p[0],
			'PARTITION_ORDINAL_POSITION' => (string) ($i + 1),
			'PARTITION_METHOD'           => 'RANGE',
			'PARTITION_EXPRESSION'       => $opts['expression'] ?? 'unix_timestamp(`scan_date`)',
			'PARTITION_DESCRIPTION'      => $p[1],
			'TABLE_ROWS'                 => '0',
		);
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

	return array(
		count(array_filter($sql, fn ($s) => strpos($s, 'GET_LOCK') !== false)),
		count(array_filter($sql, fn ($s) => strpos($s, 'RELEASE_LOCK') !== false)),
	);
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
	mt_pm_db(array(
		array('s20261101000000', '1793491201'),
		array('r20261103000000', (string) (MT_PM_CUTOFF - 100)),
		array('h2026110500', (string) MT_PM_CUTOFF),
		array('h2026110506', (string) (MT_PM_CUTOFF + 1)),
		array('s20261112061507', '1794464108'),
		array('dMaxValue', 'MAXVALUE'),
	));

	$result = mactrack_partition_maintain(7, MT_PM_NOW);

	expect($result['dropped'])->toBe(array('s20261101000000', 'r20261103000000', 'h2026110500'));
	expect(mt_pm_ddl())->toBe(array(mactrack_partition_drop_sql(array('s20261101000000', 'r20261103000000', 'h2026110500'))));
	expect(mt_pm_locks())->toBe(array(1, 1));
});

it('prunes scan dates below the highest dropped bound, as the server reads it', function () {
	mt_pm_db(array(
		array('s20261101000000', '1793491201'),
		array('h2026110500', (string) MT_PM_CUTOFF),
		array('s20261112061507', '1794464108'),
		array('dMaxValue', 'MAXVALUE'),
	), array('from_unixtime' => '2026-11-05 06:15:07'));

	mactrack_partition_maintain(7, MT_PM_NOW);

	$conv = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => strpos($c['sql'], 'FROM_UNIXTIME(?)') !== false));
	expect($conv[0]['params'])->toBe(array(MT_PM_CUTOFF));
	expect(mt_pm_prunes())->toBe(array(array('2026-11-05 06:15:07')));
});

it('keeps the newest bounded partition even when it has expired', function () {
	mt_pm_db(array(
		array('s20261101000000', '1793491201'),
		array('s20261102000000', '1793577601'),
		array('dMaxValue', 'MAXVALUE'),
	));

	expect(mactrack_partition_maintain(7, MT_PM_NOW)['dropped'])->toBe(array('s20261101000000'));
});

it('drops nothing without a retention period', function (int $days) {
	mt_pm_db(array(
		array('s20261101000000', '1793491201'),
		array('s20261102000000', '1793577601'),
		array('dMaxValue', 'MAXVALUE'),
	));

	expect(mactrack_partition_maintain($days, MT_PM_NOW)['dropped'])->toBe(array());
	expect(mt_pm_ddl())->toBe(array());
	expect(mt_pm_prunes())->toBe(array());
})->with(array(0, -1));

it('prunes nothing and says why when the drop fails', function () {
	mt_pm_db(array(
		array('s20261101000000', '1793491201'),
		array('s20261112061507', '1794464108'),
		array('dMaxValue', 'MAXVALUE'),
	));
	mactrack_test_queue_return('db_execute_prepared', true);  // SET 2
	mactrack_test_queue_return('db_execute_prepared', false); // attempts
	mactrack_test_queue_return('db_execute_prepared', false);
	mactrack_test_queue_return('db_execute_prepared', false);

	$result = mactrack_partition_maintain(7, MT_PM_NOW);

	expect($result['dropped'])->toBe(array());
	expect($result['reason'])->toBe('drop failed');
	expect(mt_pm_prunes())->toBe(array());
	expect(mt_pm_status()['reason'])->toBe('drop failed');
	expect(mt_pm_locks())->toBe(array(1, 1));
});

it('runs no DDL while another session holds the partition lock', function () {
	mt_pm_db(array(
		array('s20261101000000', '1793491201'),
		array('s20261112061507', '1794464108'),
		array('dMaxValue', 'MAXVALUE'),
	), array('lock' => '0'));

	$result = mactrack_partition_maintain(7, MT_PM_NOW);

	expect($result['reason'])->toBe('partition lock busy');
	expect(mt_pm_ddl())->toBe(array());
	expect(mt_pm_locks())->toBe(array(1, 0));
});

it('leaves other layouts to their own maintenance', function (array $parts, string $expression, string $state) {
	mt_pm_db($parts, array('expression' => $expression));

	$result = mactrack_partition_maintain(7, MT_PM_NOW);

	expect($result['reason'])->toContain($state);
	expect(mt_pm_ddl())->toBe(array());
	expect(mt_pm_locks())->toBe(array(0, 0));
})->with(array(
	'none'    => array(array(), 'unix_timestamp(`scan_date`)', 'none'),
	'legacy'  => array(array(array('d20261111', '740297'), array('dMaxValue', 'MAXVALUE')), 'to_days(`scan_date`)', 'legacy'),
	'invalid' => array(array(array('d20261112', '1794528000')), 'unix_timestamp(`scan_date`)', 'invalid'),
));

it('creates the current and next hour ahead on the hourly layout', function () {
	$GLOBALS['__test_config_options']['mt_partition_granularity'] = 'hour';
	mt_pm_db(array(
		array('h2026111205', '1794463200'),
		array('dMaxValue', 'MAXVALUE'),
	));

	$result = mactrack_partition_maintain(7, MT_PM_NOW);

	expect($result['created'])->toBe(array('h2026111206', 'h2026111207'));
	expect(mt_pm_ddl())->toBe(array(
		mactrack_partition_split_sql('h2026111206', 1794466800),
		mactrack_partition_split_sql('h2026111207', 1794470400),
	));
});

it('skips periods that already exist', function () {
	$GLOBALS['__test_config_options']['mt_partition_granularity'] = 'hour';
	mt_pm_db(array(
		array('h2026111206', '1794466800'),
		array('dMaxValue', 'MAXVALUE'),
	));

	expect(mactrack_partition_maintain(7, MT_PM_NOW)['created'])->toBe(array('h2026111207'));
});

it('creates the current and next day ahead on the daily layout', function () {
	$GLOBALS['__test_config_options']['mt_partition_granularity'] = 'day';
	mt_pm_db(array(array('dMaxValue', 'MAXVALUE')));

	expect(mactrack_partition_maintain(7, MT_PM_NOW)['created'])->toBe(array('d20261112', 'd20261113'));
	expect(mt_pm_ddl()[1])->toBe(mactrack_partition_split_sql('d20261113', 1794614400));
});

it('falls back to hourly ahead creation when per-scan partitions would pass the cap', function () {
	// 15-minute scans for 7 days are 673 partitions, over MySQL's 512.
	mt_pm_db(array(array('dMaxValue', 'MAXVALUE')), array('version' => '8.4.13'));

	expect(mactrack_partition_maintain(7, MT_PM_NOW)['created'])->toBe(array('h2026111206', 'h2026111207'));
});

it('leaves per-scan partitions to the poller', function () {
	mt_pm_db(array(array('dMaxValue', 'MAXVALUE')));

	expect(mactrack_partition_maintain(7, MT_PM_NOW)['created'])->toBe(array());
	expect(mt_pm_ddl())->toBe(array());
});

it('creates nothing ahead while dMaxValue holds rows', function () {
	$GLOBALS['__test_config_options']['mt_partition_granularity'] = 'hour';
	mt_pm_db(array(array('dMaxValue', 'MAXVALUE')), array('stranded' => '1794460000'));

	$result = mactrack_partition_maintain(7, MT_PM_NOW);

	expect($result['created'])->toBe(array());
	expect($result['reason'])->toBe('dMaxValue holds rows');
});

it('creates nothing ahead at the partition cap', function () {
	$GLOBALS['__test_config_options']['mt_partition_granularity'] = 'hour';
	$parts = array();

	for ($i = 0; $i < 511; $i++) {
		$parts[] = array(sprintf('s%014d', $i + 1), (string) (1794000000 + $i));
	}

	$parts[] = array('dMaxValue', 'MAXVALUE');
	mt_pm_db($parts, array('version' => '8.4.13'));

	$result = mactrack_partition_maintain(0, MT_PM_NOW);

	expect($result['created'])->toBe(array());
	expect($result['reason'])->toBe('partition cap 512 reached');
});

it('stops creating ahead after a failed split and still releases the lock', function () {
	$GLOBALS['__test_config_options']['mt_partition_granularity'] = 'hour';
	mt_pm_db(array(array('dMaxValue', 'MAXVALUE')));
	mactrack_test_queue_return('db_execute_prepared', true);  // SET 2
	mactrack_test_queue_return('db_execute_prepared', false);
	mactrack_test_queue_return('db_execute_prepared', false);
	mactrack_test_queue_return('db_execute_prepared', false);

	$result = mactrack_partition_maintain(7, MT_PM_NOW);

	expect($result['created'])->toBe(array());
	expect($result['reason'])->toBe('split failed');
	expect(count(mt_pm_ddl()))->toBe(3);
	expect(mt_pm_locks())->toBe(array(1, 1));
});

it('records the outcome under maintenance in the status', function () {
	mt_pm_db(array(
		array('s20261101000000', '1793491201'),
		array('s20261112061507', '1794464108'),
		array('dMaxValue', 'MAXVALUE'),
	));

	mactrack_partition_maintain(7, MT_PM_NOW);

	$status = mt_pm_status();
	expect($status['dropped'])->toBe(array('s20261101000000'));
	expect($status['created'])->toBe(array());
	expect($status['reason'])->toBe('');
	expect($status['time'])->toBeInt();
});

it('retires partitions instead of deleting rows on the scan-date layout', function () {
	set_config_option('mt_data_retention', '7');
	mt_pm_db(array(
		array('s20261101000000', '1793491201'),
		array('s20261112061507', '1794464108'),
		array('dMaxValue', 'MAXVALUE'),
	));

	perform_mactrack_db_maint();

	$sql = array_column($GLOBALS['__test_db_calls'], 'sql');
	expect(mt_pm_ddl())->toBe(array(mactrack_partition_drop_sql(array('s20261101000000'))));
	expect(array_filter($sql, fn ($s) => strpos($s, 'DELETE QUICK FROM mac_track_ports') !== false))->toBe(array());
	expect(array_filter($sql, fn ($s) => strpos($s, 'REORGANIZE') !== false))->toBe(array());
});

it('deletes old rows without OPTIMIZE on an unpartitioned table', function () {
	set_config_option('mt_data_retention', '7');

	perform_mactrack_db_maint();

	$sql = array_column($GLOBALS['__test_db_calls'], 'sql');
	expect(array_filter($sql, fn ($s) => strpos($s, 'DELETE QUICK FROM mac_track_ports') !== false))->toHaveCount(1);
	expect(array_filter($sql, fn ($s) => strpos($s, 'OPTIMIZE TABLE mac_track_ports') !== false))->toBe(array());
	expect($GLOBALS['__test_config_options'])->not->toHaveKey('mt_partition_status');
});

it('deletes old rows and records why on an invalid layout', function () {
	set_config_option('mt_data_retention', '7');
	mt_pm_db(array(array('d20261112', '1794528000')));

	perform_mactrack_db_maint();

	$sql = array_column($GLOBALS['__test_db_calls'], 'sql');
	expect(array_filter($sql, fn ($s) => strpos($s, 'DELETE QUICK FROM mac_track_ports') !== false))->toHaveCount(1);
	expect(mt_pm_ddl())->toBe(array());
	expect(mt_pm_status()['reason'])->toContain('layout invalid');
});
