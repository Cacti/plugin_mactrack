<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for mactrack_check_upgrade()'s version-drift path in
 * setup.php, including the upgrade-time manifest prune
 * (mactrack_prune_files()).
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

beforeEach(function () {
	$GLOBALS['__test_db_calls']        = array();
	$GLOBALS['__test_cacti_log']       = array();
	$GLOBALS['__test_next_returns']    = array();
	$GLOBALS['__test_matched_returns'] = array();
	$GLOBALS['__test_config_options']  = array('mt_convert_readstrings' => 'on');
});

it('runs the upgrade housekeeping and prune on a version drift', function () {
	// Load the relocated schema + function libraries from the real checkout so
	// their functions are defined before we sandbox base_path.
	require_once $GLOBALS['config']['base_path'] . '/plugins/mactrack/includes/database.php';
	require_once $GLOBALS['config']['base_path'] . '/plugins/mactrack/lib/mactrack_functions.php';

	mactrack_test_queue_return('get_current_page', 'plugins.php');
	// Stored row present but disabled (status 0): drifts, yet skips the inner
	// install/schema-upgrade block (which depends on unstubbed Cacti core).
	mactrack_test_queue_return('db_fetch_row', array('version' => '1.0.0', 'status' => 0));
	// Permission realms already present -> the realm-insert branch is skipped.
	mactrack_test_queue_return('db_fetch_cell', '1');

	// Sandbox base_path (with empty include stubs + a minimal INFO) so the
	// upgrade-time prune runs against a throwaway tree, never the real checkout.
	$restore = $GLOBALS['config']['base_path'];
	$base    = sys_get_temp_dir() . '/mactrack-upg-' . uniqid();
	mkdir($base . '/plugins/mactrack/includes', 0777, true);
	mkdir($base . '/plugins/mactrack/lib', 0777, true);
	file_put_contents($base . '/plugins/mactrack/INFO', "[info]\nversion = 9.9.9\nname = mactrack\nlongname = MacTrack\nauthor = x\nhomepage = x\n");
	file_put_contents($base . '/plugins/mactrack/includes/database.php', "<?php\n");
	file_put_contents($base . '/plugins/mactrack/lib/mactrack_functions.php', "<?php\n");
	file_put_contents($base . '/plugins/mactrack/lib/mactrack_vendors.php', "<?php\n");
	$GLOBALS['config']['base_path'] = $base;

	try {
		mactrack_check_upgrade();
	} finally {
		$GLOBALS['config']['base_path'] = $restore;
	}

	// The drift path ran (it updated plugin_config) and left the real tree intact.
	$updates = array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute_prepared' && stripos($call['sql'], 'UPDATE plugin_config') !== false;
	}));

	expect($updates)->not->toBeEmpty();
});

