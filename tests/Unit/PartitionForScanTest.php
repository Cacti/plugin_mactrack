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
 * A read of one known scan names the partition that holds it, as well as
 * filtering on scan_date, so it reads one partition even if the server's
 * pruning does not apply. Only the scan-date layout gets a partition name.
 */

beforeAll(function () {
	mactrack_test_load(dirname(__DIR__, 2) . '/lib/mactrack_functions.php');
});

beforeEach(function () {
	$GLOBALS['__test_db_calls']        = array();
	$GLOBALS['__test_next_returns']    = array();
	$GLOBALS['__test_matched_returns'] = array();
	$GLOBALS['__test_config_options']  = array();
	$GLOBALS['database_default']       = 'cacti';
});

/**
 * Queues information_schema rows, VERSION() and the scan's epoch.
 *
 * @param array       $parts      [name, description] pairs.
 * @param string|null $epoch      UNIX_TIMESTAMP(?) result.
 * @param string      $expression Partition expression.
 * @return void
 */
function mt_pfs_db(array $parts, ?string $epoch, string $expression = 'unix_timestamp(`scan_date`)'): void {
	$rows = array();

	foreach (array_values($parts) as $i => $p) {
		$rows[] = array(
			'PARTITION_NAME' => $p[0], 'PARTITION_ORDINAL_POSITION' => (string) ($i + 1), 'PARTITION_METHOD' => 'RANGE',
			'PARTITION_EXPRESSION' => $expression, 'PARTITION_DESCRIPTION' => $p[1], 'TABLE_ROWS' => '0',
		);
	}

	mactrack_test_queue_return_for('db_fetch_assoc_prepared', 'information_schema.PARTITIONS', $rows);
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'VERSION()', '10.5.29-MariaDB');
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'SELECT UNIX_TIMESTAMP(?)', $epoch);
}

const MT_PFS_PARTS = array(
	array('s20261112061507', '1794464108'),
	array('h2026111207', '1794470400'),
	array('dMaxValue', 'MAXVALUE'),
);

it('names the partition whose range holds the scan', function (string $epoch, string $name) {
	mt_pfs_db(MT_PFS_PARTS, $epoch);

	expect(mactrack_partition_for_scan('2026-11-12 06:15:07'))->toBe($name);
})->with(array(
	'first partition, at its last second' => array('1794464107', 's20261112061507'),
	'at a bound, the next partition'      => array('1794464108', 'h2026111207'),
	'inside an hourly partition'          => array('1794468000', 'h2026111207'),
	'past every bound'                    => array('1794470400', 'dMaxValue'),
));

it('asks the server for the epoch of the scan date', function () {
	mt_pfs_db(MT_PFS_PARTS, '1794464107');

	mactrack_partition_for_scan('2026-11-12 06:15:07');

	$call = current(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['sql'] === 'SELECT UNIX_TIMESTAMP(?)'));
	expect($call['params'])->toBe(array('2026-11-12 06:15:07'));
});

it('names no partition outside the scan-date layout', function (array $parts, string $expression) {
	mt_pfs_db($parts, '1794464107', $expression);

	expect(mactrack_partition_for_scan('2026-11-12 06:15:07'))->toBeNull();
})->with(array(
	'unpartitioned' => array(array(), 'unix_timestamp(`scan_date`)'),
	'legacy'        => array(array(array('d20261111', '740297'), array('dMaxValue', 'MAXVALUE')), 'to_days(`scan_date`)'),
	'invalid'       => array(array(array('d20261112', '1794528000')), 'unix_timestamp(`scan_date`)'),
));

it('names no partition for a date the server cannot read', function (?string $epoch) {
	mt_pfs_db(MT_PFS_PARTS, $epoch);

	expect(mactrack_partition_for_scan('not a date'))->toBeNull();
})->with(array(null, '0', ''));

it('reads the whole table for anything but one scan date', function (string $filter) {
	expect(mactrack_ports_table_for($filter))->toBe('mac_track_ports');
	expect($GLOBALS['__test_db_calls'])->toBe(array());
})->with(array('1', '2', '3', ''));

it('reads one partition for one scan date', function () {
	mt_pfs_db(MT_PFS_PARTS, '1794468000');

	expect(mactrack_ports_table_for('2026-11-12 07:00:00'))->toBe('mac_track_ports PARTITION (`h2026111207`)');
});

it('reads the whole table for one scan date when the table is not partitioned', function () {
	mt_pfs_db(array(), '1794468000');

	expect(mactrack_ports_table_for('2026-11-12 07:00:00'))->toBe('mac_track_ports');
});

it('uses the partition in every port read of a selected scan date', function () {
	$src = file_get_contents(dirname(__DIR__, 2) . '/mactrack_view_macs.php');

	expect($src)->not->toContain('FROM mac_track_ports AS mtp');
	expect(substr_count($src, 'FROM $ports_table AS mtp'))->toBe(4);
	expect(substr_count($src, "\$ports_table = mactrack_ports_table_for((string) get_request_var('scan_date'));"))->toBe(2);
});
