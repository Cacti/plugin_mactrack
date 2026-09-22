<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for mactrack_poller_bottom() in setup.php.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

beforeEach(function () {
	$GLOBALS['__test_db_calls']       = array();
	$GLOBALS['__test_next_returns']   = array();
	$GLOBALS['__test_config_options'] = array();
});

it('launches poller_mactrack.php in the background', function () {
	$GLOBALS['__test_config_options']['path_php_binary'] = '/usr/bin/php';

	mactrack_poller_bottom();

	$calls = array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'exec_background';
	}));

	expect($calls)->toHaveCount(1);
	expect($calls[0]['sql'])->toBe('/usr/bin/php');
	expect($calls[0]['params'][0])->toContain('poller_mactrack.php');
});
