<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 2) . '/lib/mactrack_functions.php';

beforeEach(function () {
	$GLOBALS['__test_db_calls']     = [];
	$GLOBALS['__test_next_returns'] = [];
});

it('deletes IP rows older than the retention in days', function () {
	set_config_option('mt_data_retention_ip', '60');
	mactrack_test_queue_return('db_fetch_row', ['Create Table' => 'CREATE TABLE `mac_track_ports` (`scan_date` timestamp)']);

	perform_mactrack_db_maint();

	$delete = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => strpos($c['sql'], 'DELETE FROM mac_track_ips') !== false));

	expect($delete)->toHaveCount(1);
	expect(preg_replace('/\s+/', ' ', $delete[0]['sql']))->toBe('DELETE FROM mac_track_ips WHERE scan_date < CURDATE() - INTERVAL ? DAY ORDER BY scan_date LIMIT 10000');
	expect($delete[0]['params'])->toBe([60]);
});

it('does not rebuild the IP table during maintenance', function () {
	set_config_option('mt_data_retention_ip', '3');
	mactrack_test_queue_return('db_fetch_row', ['Create Table' => 'CREATE TABLE `mac_track_ports` (`scan_date` timestamp)']);

	perform_mactrack_db_maint();

	$optimize = array_filter($GLOBALS['__test_db_calls'], fn ($c) => stripos($c['sql'], 'OPTIMIZE TABLE mac_track_ips') !== false);

	expect($optimize)->toBe([]);
});

it('deletes expired port rows in batches without rebuilding the port table', function () {
	set_config_option('mt_data_retention', '2weeks');
	mactrack_test_queue_return('db_fetch_row', ['Create Table' => 'CREATE TABLE `mac_track_ports` (`scan_date` timestamp)']);

	perform_mactrack_db_maint();

	$delete   = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => strpos($c['sql'], 'DELETE QUICK FROM mac_track_ports') !== false));
	$optimize = array_filter($GLOBALS['__test_db_calls'], fn ($c) => stripos($c['sql'], 'OPTIMIZE TABLE mac_track_ports') !== false);

	expect($delete)->toHaveCount(1);
	expect($delete[0]['sql'])->toBe('DELETE QUICK FROM mac_track_ports WHERE scan_date < ? ORDER BY scan_date LIMIT 10000');
	expect(abs(strtotime($delete[0]['params'][0]) - strtotime('-2 Week')))->toBeLessThanOrEqual(2);
	expect($optimize)->toBe([]);
});
