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
	$GLOBALS['__test_db_calls']        = array();
	$GLOBALS['__test_next_returns']    = array();
	$GLOBALS['__test_matched_returns'] = array();
	$GLOBALS['__test_config_options']  = array();
	$GLOBALS['__test_cacti_log']       = array();
});

/**
 * Returns the recorded database calls, optionally filtered to one function; with no filter, cacti_log entries are excluded.
 *
 * @param string $fn Function name to keep, or an empty string for all non-log calls.
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
 * @param string $fragment SQL substring to look for.
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
 * @param array $names Partition names.
 * @return array
 */
function mt_scan_dates_partition_rows(array $names): array {
	return array_map(function ($name) {
		return array('PARTITION_NAME' => $name);
	}, $names);
}

it('does not record a scan that collected no rows', function () {
	mactrack_test_queue_return('db_fetch_cell', false);

	expect(mactrack_scan_dates_record('2026-11-11 10:00:00'))->toBeFalse();
	expect(mt_scan_dates_calls('db_execute_prepared'))->toBe(array());
});

it('records the scan date once with INSERT IGNORE', function () {
	mactrack_test_queue_return('db_fetch_cell', '1');

	expect(mactrack_scan_dates_record('2026-11-11 10:00:00'))->toBeTrue();

	$writes = mt_scan_dates_calls('db_execute_prepared');
	expect($writes)->toHaveCount(1);
	expect($writes[0]['sql'])->toContain('INSERT IGNORE INTO mac_track_scan_dates');
	expect($writes[0]['params'])->toBe(array('2026-11-11 10:00:00'));
	expect(mt_scan_dates_sql_matching('mac_track_ports'))->toBe(array());
});

it('prunes scan dates before the cutoff', function () {
	mactrack_scan_dates_prune('2026-11-01 00:00:00');

	$writes = mt_scan_dates_calls('db_execute_prepared');
	expect($writes)->toHaveCount(1);
	expect($writes[0]['sql'])->toMatch('/DELETE FROM mac_track_scan_dates\s+WHERE scan_date < \?/');
	expect($writes[0]['params'])->toBe(array('2026-11-01 00:00:00'));
});

it('rebuilds from each partition and syncs the table', function () {
	mactrack_test_queue_return('db_fetch_assoc_prepared', mt_scan_dates_partition_rows(array('d20261110', 'd20261111', 'dMaxValue')));
	mactrack_test_queue_return_for('db_fetch_assoc', 'PARTITION (`d20261110`)', array(array('scan_date' => '2026-11-10 10:00:00')));
	mactrack_test_queue_return_for('db_fetch_assoc', 'PARTITION (`d20261111`)', array(array('scan_date' => '2026-11-11 10:00:00'), array('scan_date' => '2026-11-11 11:00:00')));
	mactrack_test_queue_return_for('db_fetch_assoc', 'PARTITION (`dMaxValue`)', array());
	mactrack_test_queue_return_for('db_fetch_assoc', 'FROM mac_track_scan_dates', array(array('scan_date' => '2026-11-10 10:00:00'), array('scan_date' => '2026-10-01 10:00:00')));

	expect(mactrack_scan_dates_rebuild())->toBe(3);

	expect(mt_scan_dates_sql_matching('PARTITION (`'))->toHaveCount(3);
	expect(mt_scan_dates_sql_matching('SELECT DISTINCT scan_date FROM mac_track_ports '))->toHaveCount(3);

	$inserts = mt_scan_dates_sql_matching('INSERT IGNORE INTO mac_track_scan_dates');
	expect($inserts)->toHaveCount(1);
	expect($inserts[0]['params'])->toBe(array('2026-11-11 10:00:00', '2026-11-11 11:00:00'));

	$deletes = mt_scan_dates_sql_matching('DELETE FROM mac_track_scan_dates');
	expect($deletes)->toHaveCount(1);
	expect($deletes[0]['params'])->toBe(array('2026-10-01 10:00:00'));
});

it('skips a partition name that is not a plain identifier', function () {
	mactrack_test_queue_return('db_fetch_assoc_prepared', mt_scan_dates_partition_rows(array('bad`name', 'd20261111')));
	mactrack_test_queue_return_for('db_fetch_assoc', 'PARTITION (`d20261111`)', array(array('scan_date' => '2026-11-11 10:00:00')));

	expect(mactrack_scan_dates_rebuild())->toBe(1);

	expect(mt_scan_dates_sql_matching('bad`name'))->toBe(array());
	expect(implode("\n", $GLOBALS['__test_cacti_log']))->toContain('bad`name');
});

it('reads an unpartitioned table with one query', function () {
	mactrack_test_queue_return('db_fetch_assoc_prepared', mt_scan_dates_partition_rows(array(null)));
	mactrack_test_queue_return_for('db_fetch_assoc', 'FROM mac_track_ports', array(array('scan_date' => '2026-11-11 10:00:00')));

	expect(mactrack_scan_dates_rebuild())->toBe(1);

	expect(mt_scan_dates_sql_matching('PARTITION (`'))->toBe(array());
	expect(mt_scan_dates_sql_matching('SELECT DISTINCT scan_date FROM mac_track_ports'))->toHaveCount(1);
	expect(mt_scan_dates_sql_matching('DELETE FROM mac_track_scan_dates'))->toBe(array());
});

it('writes in batches of 1000 dates', function () {
	$dates = array();

	for ($i = 0; $i < 1001; $i++) {
		$dates[] = array('scan_date' => gmdate('Y-m-d H:i:s', 1762819200 + $i * 300));
	}

	mactrack_test_queue_return('db_fetch_assoc_prepared', array());
	mactrack_test_queue_return_for('db_fetch_assoc', 'FROM mac_track_ports', $dates);
	mactrack_test_queue_return_for('db_fetch_assoc', 'FROM mac_track_scan_dates', array());

	expect(mactrack_scan_dates_rebuild())->toBe(1001);

	$inserts = mt_scan_dates_sql_matching('INSERT IGNORE INTO mac_track_scan_dates');
	expect($inserts)->toHaveCount(2);
	expect($inserts[0]['params'])->toHaveCount(1000);
	expect($inserts[1]['params'])->toHaveCount(1);
});

/**
 * Runs perform_mactrack_db_maint() against an unpartitioned mac_track_ports table with the given retention.
 *
 * @param string $retention Value for the mt_data_retention option.
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
	mactrack_test_queue_return_for('db_fetch_assoc_prepared', 'information_schema.PARTITIONS', array(
		array('PARTITION_NAME' => 'd20261111', 'PARTITION_ORDINAL_POSITION' => '1', 'PARTITION_METHOD' => 'RANGE',
			'PARTITION_EXPRESSION' => 'to_days(`scan_date`)', 'PARTITION_DESCRIPTION' => '740297', 'TABLE_ROWS' => '0'),
		array('PARTITION_NAME' => 'dMaxValue', 'PARTITION_ORDINAL_POSITION' => '2', 'PARTITION_METHOD' => 'RANGE',
			'PARTITION_EXPRESSION' => 'to_days(`scan_date`)', 'PARTITION_DESCRIPTION' => 'MAXVALUE', 'TABLE_ROWS' => '0'),
	));
	mactrack_test_queue_return('db_fetch_row', array('today' => '740296'));
	mactrack_test_queue_return('db_fetch_row', array('today' => '740295'));
	mactrack_test_queue_return('db_fetch_assoc_prepared', array());
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
	expect(mt_scan_dates_prunes())->toBe(array($delete[0]['params']));
	expect(mt_scan_dates_sql_matching('SELECT DISTINCT scan_date FROM mac_track_ports'))->toBe(array());
});

it('prunes no scan dates when unpartitioned retention is off', function () {
	mt_scan_dates_maint_unpartitioned('0');

	expect(mt_scan_dates_sql_matching('DELETE QUICK FROM mac_track_ports'))->toBe(array());
	expect(mt_scan_dates_prunes())->toBe(array());
});

it('prunes scan dates below the highest dropped partition bound', function () {
	mt_scan_dates_maint_partitioned(array(
		array('PARTITION_NAME' => 'd20261101', 'PARTITION_DESCRIPTION' => '740287'),
		array('PARTITION_NAME' => 'd20261102', 'PARTITION_DESCRIPTION' => '740288'),
	));

	expect(mt_scan_dates_sql_matching('DROP PARTITION'))->toHaveCount(2);
	expect(mt_scan_dates_prunes())->toBe(array(array('2026-11-03 00:00:00')));
	expect(mt_scan_dates_sql_matching('SELECT DISTINCT scan_date FROM mac_track_ports'))->toBe(array());
});

it('prunes no scan dates when no partition is dropped', function () {
	mt_scan_dates_maint_partitioned(array());

	expect(mt_scan_dates_sql_matching('DROP PARTITION'))->toBe(array());
	expect(mt_scan_dates_prunes())->toBe(array());
});

it('ignores a non-numeric partition bound for the cutoff', function () {
	mt_scan_dates_maint_partitioned(array(
		array('PARTITION_NAME' => 'd20261101', 'PARTITION_DESCRIPTION' => 'MAXVALUE'),
	));

	expect(mt_scan_dates_sql_matching('DROP PARTITION'))->toHaveCount(1);
	expect(mt_scan_dates_prunes())->toBe(array());
});
