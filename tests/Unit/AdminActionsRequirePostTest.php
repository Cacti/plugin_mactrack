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
 * mactrack_ajax_admin.php dispatches to these four functions. Cacti checks
 * the CSRF token on POST only, so each one must refuse any other method
 * before it reads or changes anything.
 */

require_once dirname(__DIR__, 2) . '/lib/mactrack_functions.php';

beforeEach(function () {
	$GLOBALS['__test_db_calls']     = [];
	$GLOBALS['__test_next_returns'] = [];
	$_SESSION['sess_user_id']       = 1;
});

afterEach(function () {
	unset($_SERVER['REQUEST_METHOD']);
	http_response_code(200);
});

/**
 * Run an admin action and return what it printed.
 *
 * @param string $action Function name.
 *
 * @return string
 */
function mactrack_test_run_admin_action($action) {
	ob_start();

	if ($action === 'mactrack_rescan' || $action === 'mactrack_site_scan') {
		$action(true);
	} else {
		$action();
	}

	return (string) ob_get_clean();
}

dataset('admin actions', ['mactrack_site_scan', 'mactrack_rescan', 'mactrack_enable', 'mactrack_disable']);

test('a GET to an admin action is refused before it touches the database', function ($action) {
	$_SERVER['REQUEST_METHOD'] = 'GET';

	$output = mactrack_test_run_admin_action($action);

	expect(json_decode($output, true))->toBe(['error' => 'POST required']);
	expect(http_response_code())->toBe(405);
	expect($GLOBALS['__test_db_calls'])->toBe([]);
})->with('admin actions');

test('a request with no method is refused like a GET', function () {
	$output = mactrack_test_run_admin_action('mactrack_disable');

	expect(json_decode($output, true))->toBe(['error' => 'POST required']);
	expect($GLOBALS['__test_db_calls'])->toBe([]);
});

test('a POST enables and disables the device', function ($action, $disabled) {
	$_SERVER['REQUEST_METHOD'] = 'POST';

	$device = [
		'device_id'        => '',
		'disabled'         => $disabled,
		'device_name'      => 'sw1',
		'site_name'        => 'HQ',
		'snmp_status'      => 3,
		'hostname'         => 'sw1.example.com',
		'device_type'      => 'Switch',
		'scan_type'        => '2',
		'ips_total'        => 1,
		'ports_total'      => 48,
		'ports_active'     => 12,
		'ports_trunk'      => 2,
		'macs_active'      => 30,
		'last_runduration' => 4.2,
	];

	mactrack_test_queue_return('db_fetch_row_prepared', ['hostname' => 'sw1.example.com']);
	mactrack_test_queue_return('db_fetch_row_prepared', ['username' => 'admin', 'full_name' => 'Administrator']);
	mactrack_test_queue_return('db_fetch_row_prepared', $device);

	$output = mactrack_test_run_admin_action($action);

	$updates = array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute_prepared' && strpos($call['sql'], 'UPDATE mac_track_devices') === 0;
	}));

	expect($updates)->toHaveCount(1);
	expect($updates[0]['sql'])->toContain('SET disabled' . ($disabled === 'on' ? '="on"' : ' = ""'));
	expect(json_decode($output, true))->toHaveKey('content');
	expect(http_response_code())->not->toBe(405);
})->with([
	['mactrack_enable', ''],
	['mactrack_disable', 'on'],
]);

test('a POST rescan and site scan look up their target', function ($action, $table) {
	$_SERVER['REQUEST_METHOD'] = 'POST';

	$output = mactrack_test_run_admin_action($action);

	expect($GLOBALS['__test_db_calls'])->toHaveCount(1);
	expect($GLOBALS['__test_db_calls'][0]['fn'])->toBe('db_fetch_row_prepared');
	expect($GLOBALS['__test_db_calls'][0]['sql'])->toContain('FROM ' . $table);
	expect(json_decode($output, true))->toBe([]);
})->with([
	['mactrack_rescan', 'mac_track_devices'],
	['mactrack_site_scan', 'mac_track_sites'],
]);

test('the admin actions are sent as POST with the CSRF token and report failures', function () {
	$js = plugin_test_read_source('js/mactrack.js');

	expect(substr_count($js, 'mactrack_ajax_admin.php'))->toBe(5);
	expect($js)->not->toContain('getJSON');
	expect(substr_count($js, '$.post(url, { __csrf_magic: csrfMagicToken }'))->toBe(5);
	expect(substr_count($js, "}, 'json').fail(function(data) {"))->toBe(5);
	expect(substr_count($js, 'getPresentHTTPError(data);'))->toBe(5);
	expect(substr_count($js, "removeClass('fa-spin')"))->toBe(6);
});
