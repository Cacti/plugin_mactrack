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
 * The Utilities status page reports the partition layout, the effective
 * partition size and why, a pending exchange, and the last maintenance.
 * Every value it prints is escaped.
 */

beforeAll(function () {
	mactrack_test_load(dirname(__DIR__, 2) . '/lib/mactrack_functions.php');
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
});

/**
 * Queues information_schema rows and VERSION() for the report.
 *
 * @param array  $parts      [name, description] pairs.
 * @param string $expression Partition expression.
 * @param string $version    VERSION() result.
 * @return void
 */
function mt_pr_db(array $parts, string $expression = 'unix_timestamp(`scan_date`)', string $version = '10.5.29-MariaDB'): void {
	$rows = array();

	foreach (array_values($parts) as $i => $p) {
		$rows[] = array(
			'PARTITION_NAME' => $p[0], 'PARTITION_ORDINAL_POSITION' => (string) ($i + 1), 'PARTITION_METHOD' => 'RANGE',
			'PARTITION_EXPRESSION' => $expression, 'PARTITION_DESCRIPTION' => $p[1], 'TABLE_ROWS' => '0',
		);
	}

	mactrack_test_queue_return_for('db_fetch_assoc_prepared', 'information_schema.PARTITIONS', $rows);
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'VERSION()', $version);
}

it('reports the scan-date layout, its coverage, and the last maintenance', function () {
	mt_pr_db(array(
		array('s20261105061507', '1793859308'),
		array('s20261112061507', '1794464108'),
		array('dMaxValue', 'MAXVALUE'),
	));
	$GLOBALS['__test_config_options']['mt_partition_pending'] = 's20261112060007';
	$GLOBALS['__test_config_options']['mt_partition_status']  = json_encode(array(
		'reason'      => 'exchange failed',
		'maintenance' => array('dropped' => array('s20261104061507'), 'created' => array(), 'reason' => '', 'time' => 1794460000),
	));

	$r = mactrack_partition_report();

	expect($r['layout'])->toBe('range');
	expect($r['partitions'])->toBe(3);
	expect($r['cap'])->toBe(1024);
	expect($r['oldest'])->toBe('s20261105061507');
	expect($r['newest'])->toBe('s20261112061507');
	expect($r['covered_until'])->toBe(1794464108);
	expect($r['granularity'])->toBe('scan');
	expect($r['fell_back'])->toBeFalse();
	expect($r['projected'])->toBe(770);
	expect($r['pending'])->toBe('s20261112060007');
	expect($r['load_path'])->toBe('php');
	expect($r['last_scan_reason'])->toBe('exchange failed');
	expect($r['maintenance']['dropped'])->toBe(array('s20261104061507'));
	expect($r['conversion_needed'])->toBeFalse();
});

it('reports the cap fallback on MySQL', function () {
	mt_pr_db(array(array('dMaxValue', 'MAXVALUE')), 'unix_timestamp(`scan_date`)', '8.4.13');

	$r = mactrack_partition_report();

	expect($r['cap'])->toBe(512);
	expect($r['granularity'])->toBe('hour');
	expect($r['fell_back'])->toBeTrue();
	expect($r['oldest'])->toBeNull();
	expect($r['covered_until'])->toBeNull();
});

it('reports an unpartitioned table with nothing else to show', function () {
	mt_pr_db(array());

	$r = mactrack_partition_report();

	expect($r['layout'])->toBe('none');
	expect($r['partitions'])->toBe(0);
	expect($r['pending'])->toBe('');
	expect($r['maintenance'])->toBeNull();
	expect($r['conversion_needed'])->toBeFalse();
});

it('flags the legacy daily layout for conversion without reading its bounds as dates', function () {
	mt_pr_db(array(array('d20261111', '740297'), array('dMaxValue', 'MAXVALUE')), 'to_days(`scan_date`)');

	$r = mactrack_partition_report();

	expect($r['layout'])->toBe('legacy');
	expect($r['conversion_needed'])->toBeTrue();
	expect($r['newest'])->toBe('d20261111');
	expect($r['covered_until'])->toBeNull();
});

it('ignores a status record that is not JSON', function () {
	mt_pr_db(array());
	$GLOBALS['__test_config_options']['mt_partition_status'] = 'garbage';

	$r = mactrack_partition_report();

	expect($r['last_scan_reason'])->toBe('');
	expect($r['maintenance'])->toBeNull();
});

/**
 * Returns the source of one function in mactrack_utilities.php.
 *
 * @param string $name Function name.
 * @return string
 */
function mt_pr_function_source(string $name): string {
	$src = file_get_contents(dirname(__DIR__, 2) . '/mactrack_utilities.php');
	$at  = strpos($src, "function $name(");

	expect($at)->not->toBeFalse();

	$next = strpos($src, "\nfunction ", $at + 1);

	return substr($src, $at, $next === false ? null : $next - $at);
}

it('escapes every value the status page prints', function () {
	$src = mt_pr_function_source('mactrack_utilities_partition_status');

	expect($src)->toContain('mactrack_partition_report()');

	// Each print goes through one helper that escapes label and value.
	preg_match_all('/print\s+([^;]+);/', $src, $m);
	expect($m[1])->not->toBe(array());

	foreach ($m[1] as $expr) {
		expect($expr)->toMatch('/html_escape\(/');
	}
});

it('runs OPTIMIZE only on a POST, under the scan lock', function () {
	$src = mt_pr_function_source('mactrack_utilities_optimize_ports');

	expect(mactrack_utilities_may_run('mactrack_utilities_optimize_ports', 'GET'))->toBeFalse();
	expect(strpos($src, 'mactrack_partition_scan_begin()'))->toBeLessThan(strpos($src, 'OPTIMIZE TABLE mac_track_ports'));
	expect($src)->toContain('mactrack_partition_scan_end();');
	expect($src)->not->toContain('form_confirm(');
	expect($src)->not->toContain("isset_request_var('confirm')");
});

it('dispatches the status page and OPTIMIZE from Utilities', function () {
	$src = file_get_contents(dirname(__DIR__, 2) . '/mactrack_utilities.php');

	expect($src)->toContain("case 'mactrack_partition_status':");
	expect($src)->toContain("case 'mactrack_utilities_optimize_ports':");
	expect($src)->toContain("href='mactrack_utilities.php?action=mactrack_partition_status'");
	expect($src)->toContain("mactrack_utilities_action_link('mactrack_utilities_optimize_ports'");
});
