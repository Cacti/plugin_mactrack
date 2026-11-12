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
 * mac_track_scan_dates is kept one row at a time: the poller records its
 * scan date, maintenance prunes with its retention cutoff, and only the
 * explicit Utilities rebuild reads mac_track_ports, one partition at a
 * time. No path may run SELECT DISTINCT over the whole ports table.
 */

beforeAll(function () {
	mactrack_test_load(dirname(__DIR__, 2) . '/lib/mactrack_functions.php');
});

beforeEach(function () {
	$GLOBALS['__test_db_calls']        = [];
	$GLOBALS['__test_next_returns']    = [];
	$GLOBALS['__test_matched_returns'] = [];
	$GLOBALS['__test_config_options']  = [];
	$GLOBALS['__test_cacti_log']       = [];
});

/**
 * Returns the recorded database calls, optionally filtered to one function; with no filter, cacti_log entries are excluded.
 *
 * @param  string $fn Function name to keep, or an empty string for all non-log calls.
 * @return array
 */
function mt_scan_dates_calls(string $fn = ''): array {
	return array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) use ($fn) {
		return $fn === '' ? $call['fn'] !== 'cacti_log' : $call['fn'] === $fn;
	}));
}

/**
 * Returns the recorded non-log database calls whose SQL contains the fragment, case-insensitively.
 *
 * @param  string $fragment SQL substring to look for.
 * @return array
 */
function mt_scan_dates_sql_matching(string $fragment): array {
	return array_values(array_filter(mt_scan_dates_calls(), function ($call) use ($fragment) {
		return stripos($call['sql'], $fragment) !== false;
	}));
}

/**
 * Builds the PARTITION_NAME rows a partition-listing query would return.
 *
 * @param  array $names Partition names.
 * @return array
 */
function mt_scan_dates_partition_rows(array $names): array {
	return array_map(function ($name) {
		return ['PARTITION_NAME' => $name];
	}, $names);
}

it('does not record a scan that collected no rows', function () {
	mactrack_test_queue_return('db_fetch_cell', false);

	expect(mactrack_scan_dates_record('2026-11-11 10:00:00'))->toBeFalse();
	expect(mt_scan_dates_calls('db_execute_prepared'))->toBe([]);
});

it('records the scan date once with INSERT IGNORE', function () {
	mactrack_test_queue_return('db_fetch_cell', '1');

	expect(mactrack_scan_dates_record('2026-11-11 10:00:00'))->toBeTrue();

	$writes = mt_scan_dates_calls('db_execute_prepared');
	expect($writes)->toHaveCount(1);
	expect($writes[0]['sql'])->toContain('INSERT IGNORE INTO mac_track_scan_dates');
	expect($writes[0]['params'])->toBe(['2026-11-11 10:00:00']);
	expect(mt_scan_dates_sql_matching('mac_track_ports'))->toBe([]);
});

it('prunes scan dates before the cutoff', function () {
	mactrack_scan_dates_prune('2026-11-01 00:00:00');

	$writes = mt_scan_dates_calls('db_execute_prepared');
	expect($writes)->toHaveCount(1);
	expect($writes[0]['sql'])->toMatch('/DELETE FROM mac_track_scan_dates\s+WHERE scan_date < \?/');
	expect($writes[0]['params'])->toBe(['2026-11-01 00:00:00']);
});

it('removes scan dates left with no port rows', function () {
	mactrack_scan_dates_prune_empty();

	$sql = preg_replace('/\s+/', ' ', $GLOBALS['__test_db_calls'][0]['sql']);
	expect($sql)->toBe('DELETE FROM mac_track_scan_dates WHERE NOT EXISTS (SELECT 1 FROM mac_track_ports AS p WHERE p.scan_date = mac_track_scan_dates.scan_date)');
});

it('removes empty scan dates after device, site and MAC deletes', function (string $page, string $remove) {
	$src  = file_get_contents(dirname(__DIR__, 2) . "/$page");
	$loop = strpos($src, "$remove(\$selected_items[\$i]);");

	expect($loop)->not->toBeFalse();
	expect(strpos($src, 'mactrack_scan_dates_prune_empty();', $loop))->toBeGreaterThan($loop);
})->with([
	'devices' => ['mactrack_devices.php', 'api_mactrack_device_remove'],
	'sites'   => ['mactrack_sites.php', 'api_mactrack_site_remove'],
	'macauth' => ['mactrack_macauth.php', 'api_mactrack_maca_remove'],
]);

it('rebuilds from each partition and syncs the table', function () {
	mactrack_test_queue_return('db_fetch_assoc_prepared', mt_scan_dates_partition_rows(['d20261110', 'd20261111', 'dMaxValue']));
	mactrack_test_queue_return_for('db_fetch_assoc', 'PARTITION (`d20261110`)', [['scan_date' => '2026-11-10 10:00:00']]);
	mactrack_test_queue_return_for('db_fetch_assoc', 'PARTITION (`d20261111`)', [['scan_date' => '2026-11-11 10:00:00'], ['scan_date' => '2026-11-11 11:00:00']]);
	mactrack_test_queue_return_for('db_fetch_assoc', 'PARTITION (`dMaxValue`)', []);
	mactrack_test_queue_return_for('db_fetch_assoc', 'FROM mac_track_scan_dates', [['scan_date' => '2026-11-10 10:00:00'], ['scan_date' => '2026-10-01 10:00:00']]);

	expect(mactrack_scan_dates_rebuild())->toBe(3);

	expect(mt_scan_dates_sql_matching('PARTITION (`'))->toHaveCount(3);
	expect(mt_scan_dates_sql_matching('SELECT DISTINCT scan_date FROM mac_track_ports '))->toHaveCount(3);

	$inserts = mt_scan_dates_sql_matching('INSERT IGNORE INTO mac_track_scan_dates');
	expect($inserts)->toHaveCount(1);
	expect($inserts[0]['params'])->toBe(['2026-11-11 10:00:00', '2026-11-11 11:00:00']);

	$deletes = mt_scan_dates_sql_matching('DELETE FROM mac_track_scan_dates');
	expect($deletes)->toHaveCount(1);
	expect($deletes[0]['params'])->toBe(['2026-10-01 10:00:00']);
});

it('skips a partition name that is not a plain identifier', function () {
	mactrack_test_queue_return('db_fetch_assoc_prepared', mt_scan_dates_partition_rows(['bad`name', 'd20261111']));
	mactrack_test_queue_return_for('db_fetch_assoc', 'PARTITION (`d20261111`)', [['scan_date' => '2026-11-11 10:00:00']]);

	expect(mactrack_scan_dates_rebuild())->toBe(1);

	expect(mt_scan_dates_sql_matching('bad`name'))->toBe([]);
	expect(implode("\n", $GLOBALS['__test_cacti_log']))->toContain('bad`name');
});

it('reads an unpartitioned table with one query', function () {
	mactrack_test_queue_return('db_fetch_assoc_prepared', mt_scan_dates_partition_rows([null]));
	mactrack_test_queue_return_for('db_fetch_assoc', 'FROM mac_track_ports', [['scan_date' => '2026-11-11 10:00:00']]);

	expect(mactrack_scan_dates_rebuild())->toBe(1);

	expect(mt_scan_dates_sql_matching('PARTITION (`'))->toBe([]);
	expect(mt_scan_dates_sql_matching('SELECT DISTINCT scan_date FROM mac_track_ports'))->toHaveCount(1);
	expect(mt_scan_dates_sql_matching('DELETE FROM mac_track_scan_dates'))->toBe([]);
});

it('writes in batches of 1000 dates', function () {
	$dates = [];

	for ($i = 0; $i < 1001; $i++) {
		$dates[] = ['scan_date' => gmdate('Y-m-d H:i:s', 1762819200 + $i * 300)];
	}

	mactrack_test_queue_return('db_fetch_assoc_prepared', []);
	mactrack_test_queue_return_for('db_fetch_assoc', 'FROM mac_track_ports', $dates);
	mactrack_test_queue_return_for('db_fetch_assoc', 'FROM mac_track_scan_dates', []);

	expect(mactrack_scan_dates_rebuild())->toBe(1001);

	$inserts = mt_scan_dates_sql_matching('INSERT IGNORE INTO mac_track_scan_dates');
	expect($inserts)->toHaveCount(2);
	expect($inserts[0]['params'])->toHaveCount(1000);
	expect($inserts[1]['params'])->toHaveCount(1);
});

/**
 * Runs perform_mactrack_db_maint() against an unpartitioned mac_track_ports table with the given retention.
 *
 * @param  string $retention Value for the mt_data_retention option.
 * @return void
 */
function mt_scan_dates_maint_unpartitioned(string $retention): void {
	set_config_option('mt_data_retention', $retention);

	perform_mactrack_db_maint();
}

/**
 * Runs maintenance on a daily-partitioned table whose retention query
 * returns $old_partitions as the partitions to drop.
 */
function mt_scan_dates_maint_partitioned(array $old_partitions): void {
	set_config_option('mt_data_retention', '7');
	mactrack_test_queue_return_for('db_fetch_assoc_prepared', 'information_schema.PARTITIONS', [
		['PARTITION_NAME'       => 'd20261111', 'PARTITION_ORDINAL_POSITION' => '1', 'PARTITION_METHOD' => 'RANGE',
			'PARTITION_EXPRESSION' => 'to_days(`scan_date`)', 'PARTITION_DESCRIPTION' => '740297', 'TABLE_ROWS' => '0'],
		['PARTITION_NAME'       => 'dMaxValue', 'PARTITION_ORDINAL_POSITION' => '2', 'PARTITION_METHOD' => 'RANGE',
			'PARTITION_EXPRESSION' => 'to_days(`scan_date`)', 'PARTITION_DESCRIPTION' => 'MAXVALUE', 'TABLE_ROWS' => '0'],
	]);
	mactrack_test_queue_return('db_fetch_row', ['today' => '740296']);
	mactrack_test_queue_return('db_fetch_row', ['today' => '740295']);
	mactrack_test_queue_return('db_fetch_assoc_prepared', []);
	mactrack_test_queue_return('db_fetch_assoc_prepared', $old_partitions);

	perform_mactrack_db_maint();
}

/**
 * Returns the bound parameters of every recorded DELETE FROM mac_track_scan_dates call.
 *
 * @return array
 */
function mt_scan_dates_prunes(): array {
	return array_column(mt_scan_dates_sql_matching('DELETE FROM mac_track_scan_dates'), 'params');
}

it('prunes scan dates with the unpartitioned retention cutoff', function () {
	mt_scan_dates_maint_unpartitioned('7');

	$delete = mt_scan_dates_sql_matching('DELETE QUICK FROM mac_track_ports');
	expect($delete)->toHaveCount(1);
	expect(mt_scan_dates_prunes())->toBe([$delete[0]['params']]);
	expect(mt_scan_dates_sql_matching('SELECT DISTINCT scan_date FROM mac_track_ports'))->toBe([]);
});

it('prunes no scan dates when unpartitioned retention is off', function () {
	mt_scan_dates_maint_unpartitioned('0');

	expect(mt_scan_dates_sql_matching('DELETE QUICK FROM mac_track_ports'))->toBe([]);
	expect(mt_scan_dates_prunes())->toBe([]);
});

it('prunes scan dates below the highest dropped partition bound', function () {
	mt_scan_dates_maint_partitioned([
		['PARTITION_NAME' => 'd20261101', 'PARTITION_DESCRIPTION' => '740287'],
		['PARTITION_NAME' => 'd20261102', 'PARTITION_DESCRIPTION' => '740288'],
	]);

	expect(mt_scan_dates_sql_matching('DROP PARTITION'))->toHaveCount(2);
	expect(mt_scan_dates_prunes())->toBe([['2026-11-03 00:00:00']]);
	expect(mt_scan_dates_sql_matching('SELECT DISTINCT scan_date FROM mac_track_ports'))->toBe([]);
});

it('prunes no scan dates when no partition is dropped', function () {
	mt_scan_dates_maint_partitioned([]);

	expect(mt_scan_dates_sql_matching('DROP PARTITION'))->toBe([]);
	expect(mt_scan_dates_prunes())->toBe([]);
});

it('ignores a non-numeric partition bound for the cutoff', function () {
	mt_scan_dates_maint_partitioned([
		['PARTITION_NAME' => 'd20261101', 'PARTITION_DESCRIPTION' => 'MAXVALUE'],
	]);

	expect(mt_scan_dates_sql_matching('DROP PARTITION'))->toHaveCount(1);
	expect(mt_scan_dates_prunes())->toBe([]);
});
