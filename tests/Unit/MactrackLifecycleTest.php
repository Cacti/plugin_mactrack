<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for the plugin lifecycle contract functions in setup.php:
 * plugin_mactrack_uninstall(), plugin_mactrack_check_config(),
 * plugin_mactrack_upgrade(), and mactrack_check_dependencies().
 *
 * check_config()/upgrade() delegate to mactrack_check_upgrade(), whose
 * migration cascade is already covered indirectly elsewhere in this
 * suite; get_current_page() is set to a page outside its guard list so
 * these tests stay focused on the wrapper functions' own contract.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

beforeEach(function () {
	$GLOBALS['__test_db_calls']      = array();
	$GLOBALS['__test_next_returns']  = array();
	$GLOBALS['__test_matched_returns'] = array();
	mactrack_test_queue_return('get_current_page', '/graphs.php');
});

it('removes every setting it owns on uninstall', function () {
	plugin_mactrack_uninstall();

	expect($GLOBALS['__test_db_calls'])->toHaveCount(1);
	expect($GLOBALS['__test_db_calls'][0]['fn'])->toBe('db_execute_prepared');
	expect($GLOBALS['__test_db_calls'][0]['params'])->toBe(array(
		'mt_default_site_seed_pending',
		'mt_default_site_seed_attempts',
		'mt_default_site_seed_next_retry',
	));
});

it('reports the config as always valid', function () {
	expect(plugin_mactrack_check_config())->toBeTrue();

	expect($GLOBALS['__test_db_calls'])->toBeEmpty();
});

it('reports that no upgrade is pending', function () {
	expect(plugin_mactrack_upgrade())->toBeFalse();

	expect($GLOBALS['__test_db_calls'])->toBeEmpty();
});

it('reports that its dependencies are always satisfied', function () {
	expect(mactrack_check_dependencies())->toBeTrue();
});
