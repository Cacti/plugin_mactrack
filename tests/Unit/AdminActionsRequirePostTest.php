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

test('a POST rescan runs the scan subprocess and reports the outcome in the results dialog', function ($binary, $needle, $has_error) {
	$_SERVER['REQUEST_METHOD'] = 'POST';

	// A controlled no-op binary: /bin/true exits 0 (success path) and
	// /bin/false exits nonzero (subprocess-error path), both without launching
	// the real scanner.
	$GLOBALS['__test_config_options']['path_php_binary'] = $binary;

	// device lookup (enabled), then the user lookup inside mactrack_log_action()
	mactrack_test_queue_return('db_fetch_row_prepared', [
		'device_id' => 5,
		'hostname'  => 'sw1.example.com',
		'disabled'  => '',
	]);
	mactrack_test_queue_return('db_fetch_row_prepared', ['username' => 'admin', 'full_name' => 'Administrator']);

	$data = json_decode(mactrack_test_run_admin_action('mactrack_rescan'), true);

	expect($data)->toHaveKey('content');
	expect($data)->not->toHaveKey('message');
	expect($data['content'])->toContain($needle);
	expect(array_key_exists('error', $data))->toBe($has_error);
})->with([
	'success' => ['/bin/true', 'rescan completed', false],
	'failure' => ['/bin/false', 'Subprocess error', true],
]);

test('a POST site scan runs the scan subprocess and reports the outcome in the results dialog', function ($binary, $needle, $has_error) {
	$_SERVER['REQUEST_METHOD'] = 'POST';

	$GLOBALS['__test_config_options']['path_php_binary'] = $binary;

	// site lookup, then the user lookup inside mactrack_log_action()
	mactrack_test_queue_return('db_fetch_row_prepared', ['site_id' => 3, 'site_name' => 'HQ']);
	mactrack_test_queue_return('db_fetch_row_prepared', ['username' => 'admin', 'full_name' => 'Administrator']);

	$data = json_decode(mactrack_test_run_admin_action('mactrack_site_scan'), true);

	expect($data)->toHaveKey('content');
	expect($data)->not->toHaveKey('message');
	expect($data['content'])->toContain($needle);
	expect(array_key_exists('error', $data))->toBe($has_error);
})->with([
	'success' => ['/bin/true', 'scan completed', false],
	'failure' => ['/bin/false', 'Subprocess error', true],
]);

test('the admin actions are sent as POST with the CSRF token and report failures', function () {
	$js = plugin_test_read_source('js/mactrack.js');

	// five AJAX admin actions: three scans plus enable and disable
	expect(substr_count($js, 'mactrack_ajax_admin.php'))->toBe(5);
	expect($js)->not->toContain('getJSON');

	// the three scans share one helper that POSTs with the CSRF token, bounds
	// the request with a timeout and always clears the spinner, so a stalled
	// subprocess can never leave the rescan icon spinning forever
	expect($js)->toContain('function mactrackRunScan(');
	expect(substr_count($js, "type:     'POST'"))->toBe(1);
	expect($js)->toContain('timeout:  mactrackScanTimeout');
	expect(substr_count($js, '.always(function() {'))->toBe(1);
	expect(substr_count($js, "removeClass('fa-spin')"))->toBe(1);

	// enable and disable also POST with the CSRF token
	expect(substr_count($js, '$.post(url, { __csrf_magic: csrfMagicToken }'))->toBe(2);

	// every path carries the CSRF token and reports failures to the user
	expect(substr_count($js, '__csrf_magic: csrfMagicToken'))->toBe(3);
	expect(substr_count($js, 'getPresentHTTPError(data);'))->toBe(3);
});
