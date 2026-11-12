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

if (!function_exists('get_order_string')) {
	function get_order_string() {
		return 'ORDER BY site_name ASC';
	}
}

if (!function_exists('mactrack_view_get_site_records')) {
	// Load only the query builder from the page file.
	$src = file_get_contents(dirname(__DIR__, 2) . '/mactrack_view_sites.php');
	$at  = strpos($src, 'function mactrack_view_get_site_records(');
	$end = strpos($src, "\n}\n", $at);

	eval(substr($src, $at, $end - $at + 2));
}

beforeEach(function () {
	$GLOBALS['__test_db_calls'] = [];
	set_request_var('page', '1');
});

/**
 * Builds the Sites query for one set of filters and returns its SQL.
 */
function mt_vs_sql(string $detail, string $filter, string $site, string $type): string {
	set_request_var('detail', $detail);
	set_request_var('filter', $filter);
	set_request_var('site_id', $site);
	set_request_var('device_type_id', $type);

	if ($type !== '-1') {
		mactrack_test_queue_return('db_fetch_row_prepared', ['device_type_id' => $type]);
	}

	$where = '';
	mactrack_view_get_site_records($where, 30);

	$calls = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_fetch_assoc'));

	return preg_replace('/\s+/', ' ', end($calls)['sql']);
}

it('keeps the filter when a device type is chosen', function () {
	$sql = mt_vs_sql('true', 'abc', '-1', '7');

	expect($sql)->toContain("WHERE (mac_track_device_types.vendor LIKE '%abc%'");
	expect($sql)->toContain(' AND (mac_track_devices.device_type_id=7)');
});

it('filters the summary by site on the sites table', function () {
	$sql = mt_vs_sql('false', '', '3', '-1');

	expect($sql)->toContain('FROM mac_track_sites WHERE (mac_track_sites.site_id=3)');
	expect($sql)->not->toContain('mac_track_devices');
});

it('ignores the device type in the summary', function () {
	expect(mt_vs_sql('false', 'abc', '-1', '7'))->not->toContain('device_type_id=7');
});
