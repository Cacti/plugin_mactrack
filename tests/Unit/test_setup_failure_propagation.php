<?php

if (PHP_SAPI !== 'cli') {
	exit(1);
}
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../Support/StandaloneTest.php';
require_once __DIR__ . '/../Support/ProcessRunner.php';

function api_plugin_register_hook() {
}

function api_plugin_register_realm() {
}

function api_plugin_is_enabled() {
	return true;
}

function api_plugin_enable_hooks() {
	$GLOBALS['__test_enable_hooks_calls']++;
}

function get_current_page() {
	return $GLOBALS['__test_current_page'] ?? 'plugins.php';
}

$root = realpath(__DIR__ . '/../..');
$fixture_root = sys_get_temp_dir() . '/mactrack-setup-' . bin2hex(random_bytes(8));
mkdir($fixture_root . '/plugins', 0777, true);
symlink($root, $fixture_root . '/plugins/mactrack');
$config = ['base_path' => $fixture_root];

require_once $root . '/setup.php';

$GLOBALS['database_default'] = 'mactrack_unit';
$GLOBALS['__test_enable_hooks_calls'] = 0;
$unit_site_exists = false;
$unit_insert_allowed = false;
$GLOBALS['__test_db_fetch_cell_prepared'] = function ($sql) use (&$unit_site_exists) {
	if (strpos($sql, 'GET_LOCK') !== false || strpos($sql, 'RELEASE_LOCK') !== false) {
		return '1';
	}

	if (strpos($sql, 'plugin_config') !== false) {
		return '';
	}

	return $unit_site_exists ? '1' : '0';
};
$GLOBALS['__test_db_execute_prepared'] = function ($sql) use (&$unit_site_exists, &$unit_insert_allowed) {
	if (strpos($sql, 'INTO mac_track_sites') !== false) {
		if ($unit_insert_allowed) {
			$unit_site_exists = true;
		}

		return $unit_insert_allowed;
	}

	return true;
};
$GLOBALS['__test_db_fetch_assoc'] = function ($sql) {
	if (strpos($sql, 'SHOW COLUMNS FROM mac_track_ips') !== false) {
		return [['Field' => 'port_number', 'Type' => 'varchar(30)']];
	}

	return [];
};

if (($argv[1] ?? '') === 'cli-install-failure-child') {
	$installed = plugin_mactrack_install();
	unlink($fixture_root . '/plugins/mactrack');
	rmdir($fixture_root . '/plugins');
	rmdir($fixture_root);
	exit($installed ? 0 : 1);
}

if (($argv[1] ?? '') === 'upgrade-reentry-child') {
	$GLOBALS['__test_db_fetch_row'] = function () {
		return ['version' => '0.0.0', 'status' => 1];
	};
	$GLOBALS['__test_config']['mt_default_site_seed_pending'] = 'on';
	$GLOBALS['__test_config']['mt_default_site_seed_attempts'] = '2';
	$GLOBALS['__test_config']['mt_default_site_seed_next_retry'] = '0';
	$GLOBALS['__test_db_fetch_cell_prepared'] = function ($sql) {
		if (strpos($sql, 'GET_LOCK') !== false || strpos($sql, 'RELEASE_LOCK') !== false) {
			return '1';
		}

		return strpos($sql, 'plugin_config') !== false ? '1' : '0';
	};
	$GLOBALS['__test_db_execute_prepared'] = function ($sql) {
		if (strpos($sql, 'INTO mac_track_sites') !== false || strpos($sql, 'UPDATE plugin_config') !== false) {
			return false;
		}

		return true;
	};
	mactrack_check_upgrade();
	$first_attempts = $GLOBALS['__test_config']['mt_default_site_seed_attempts'];
	$GLOBALS['__test_config']['mt_default_site_seed_next_retry'] = '0';
	mactrack_check_upgrade();
	echo $first_attempts . ':' . $GLOBALS['__test_config']['mt_default_site_seed_attempts'];
	unlink($fixture_root . '/plugins/mactrack');
	rmdir($fixture_root . '/plugins');
	rmdir($fixture_root);
	exit(0);
}

$cli_install_result = MactrackProcessRunner::run([PHP_BINARY, __FILE__, 'cli-install-failure-child']);
MactrackStandaloneTest::assertTrue($cli_install_result['status'] !== 0, 'a failed CLI install exits non-zero');
MactrackStandaloneTest::assertContains('installed without a Default site', $cli_install_result['error'], 'a failed CLI install writes an actionable error to stderr');
MactrackStandaloneTest::assertSame(false, mactrack_setup_table_new(), 'the setup hook returns a failed Default-site postcondition without throwing');
MactrackStandaloneTest::assertSame([], $GLOBALS['__test_messages'], 'CLI installation does not attempt a web message');

$GLOBALS['__test_messages'] = [];
$unit_insert_allowed = true;
$GLOBALS['__test_config']['mt_default_site_seed_next_retry'] = '0';
MactrackStandaloneTest::assertSame(null, mactrack_check_upgrade(), 'the legacy upgrade lifecycle retains its void contract');
MactrackStandaloneTest::assertSame(1, $GLOBALS['__test_enable_hooks_calls'], 'a seed diagnostic does not redefine the whole-schema hook lifecycle');
MactrackStandaloneTest::assertSame([], $GLOBALS['__test_messages'], 'the CLI upgrade path does not attempt a web message');
MactrackStandaloneTest::assertTrue(count($GLOBALS['__test_logs']) >= 1, 'the CLI install failure is recorded in the Cacti log');
$plugin_updates = array_filter($GLOBALS['__test_db_calls'], function ($call) {
	return $call['fn'] === 'db_execute_prepared' && strpos($call['sql'], 'UPDATE plugin_config') !== false;
});
MactrackStandaloneTest::assertSame(1, count($plugin_updates), 'a seed diagnostic does not force repeated legacy migrations by withholding the version stamp');
$info = plugin_mactrack_version();
$plugin_update = reset($plugin_updates);
MactrackStandaloneTest::assertSame(
	[$info['longname'], $info['author'], $info['homepage'], $info['version'], ''],
	$plugin_update['params'],
	'the prepared plugin metadata update binds the prior values in column order'
);
MactrackStandaloneTest::assertSame('off', $GLOBALS['__test_config']['mt_default_site_seed_pending'], 'a later successful lifecycle clears the focused retry marker');

$GLOBALS['__test_db_fetch_row'] = function () use ($info) {
	return ['version' => $info['version'], 'status' => 1];
};
$unit_site_exists = false;
$unit_insert_allowed = false;
$GLOBALS['__test_config']['mt_default_site_seed_pending'] = 'on';
$GLOBALS['__test_config']['mt_default_site_seed_attempts'] = '1';
$GLOBALS['__test_config']['mt_default_site_seed_next_retry'] = '0';
$inserts_before_retry = count(array_filter($GLOBALS['__test_db_calls'], function ($call) {
	return $call['fn'] === 'db_execute_prepared' && strpos($call['sql'], 'INTO mac_track_sites') !== false;
}));
mactrack_check_upgrade();
$inserts_after_retry = count(array_filter($GLOBALS['__test_db_calls'], function ($call) {
	return $call['fn'] === 'db_execute_prepared' && strpos($call['sql'], 'INTO mac_track_sites') !== false;
}));
$plugin_updates_after_retry = array_filter($GLOBALS['__test_db_calls'], function ($call) {
	return $call['fn'] === 'db_execute_prepared' && strpos($call['sql'], 'UPDATE plugin_config') !== false;
});
MactrackStandaloneTest::assertTrue($inserts_after_retry > $inserts_before_retry, 'a pending seed retries after the plugin version is current');
MactrackStandaloneTest::assertSame(1, count($plugin_updates_after_retry), 'a seed-only retry does not rerun or restamp the legacy migration');
$GLOBALS['__test_config']['mt_default_site_seed_pending'] = 'on';
$GLOBALS['__test_config']['mt_default_site_seed_attempts'] = '1';
$GLOBALS['__test_config']['mt_default_site_seed_next_retry'] = '0';
$forced_pending_reads = 0;
$GLOBALS['__test_read_config_option'] = function ($name, $force) use (&$forced_pending_reads) {
	if ($name === 'mt_default_site_seed_pending' && !$force) {
		return 'off';
	}

	if ($name === 'mt_default_site_seed_pending' && $force) {
		$forced_pending_reads++;
	}

	return $GLOBALS['__test_config'][$name] ?? '';
};
$inserts_before_stale_cache_check = count(array_filter($GLOBALS['__test_db_calls'], function ($call) {
	return $call['fn'] === 'db_execute_prepared' && strpos($call['sql'], 'INTO mac_track_sites') !== false;
}));
mactrack_check_upgrade();
$inserts_after_stale_cache_check = count(array_filter($GLOBALS['__test_db_calls'], function ($call) {
	return $call['fn'] === 'db_execute_prepared' && strpos($call['sql'], 'INTO mac_track_sites') !== false;
}));
MactrackStandaloneTest::assertTrue($forced_pending_reads > 0, 'the recovery path bypasses a stale session-cached pending marker');
MactrackStandaloneTest::assertTrue($inserts_after_stale_cache_check > $inserts_before_stale_cache_check, 'a stale cached off value cannot suppress a due retry');
$GLOBALS['__test_read_config_option'] = null;
$retry_queries = 0;
$GLOBALS['__test_db_fetch_cell_prepared'] = function () use (&$retry_queries) {
	$retry_queries++;

	return '1';
};
$GLOBALS['__test_config']['mt_default_site_seed_pending'] = 'on';
$GLOBALS['__test_config']['mt_default_site_seed_attempts'] = '2';
$GLOBALS['__test_config']['mt_default_site_seed_next_retry'] = (string) (time() + 300);
mactrack_check_upgrade();
MactrackStandaloneTest::assertSame(1, $retry_queries, 'a pending seed uses one read-only check to detect external recovery during backoff');
MactrackStandaloneTest::assertSame('off', $GLOBALS['__test_config']['mt_default_site_seed_pending'], 'a site found during backoff clears the stale pending marker');
MactrackStandaloneTest::assertSame([], $GLOBALS['__test_messages'], 'external recovery during backoff raises no false operator error');
$GLOBALS['__test_config']['mt_default_site_seed_pending'] = 'on';
$GLOBALS['__test_config']['mt_default_site_seed_attempts'] = '5';
$GLOBALS['__test_config']['mt_default_site_seed_next_retry'] = '0';
mactrack_check_upgrade();
MactrackStandaloneTest::assertSame(2, $retry_queries, 'a degraded seed resumes automatic recovery after its hourly window');
MactrackStandaloneTest::assertSame('off', $GLOBALS['__test_config']['mt_default_site_seed_pending'], 'a successful degraded retry clears the pending marker');
$GLOBALS['__test_config']['mt_default_site_seed_pending'] = 'off';
$non_pending_site_queries = 0;
$GLOBALS['__test_db_fetch_cell_prepared'] = function () use (&$non_pending_site_queries) {
	$non_pending_site_queries++;

	return '1';
};
$config_before_non_pending_check = $GLOBALS['__test_config'];
$calls_before_non_pending_check = count($GLOBALS['__test_db_calls']);
mactrack_check_upgrade();
MactrackStandaloneTest::assertSame(0, $non_pending_site_queries, 'a non-pending current-version check does not acquire a lock or query the sites table');
MactrackStandaloneTest::assertSame($calls_before_non_pending_check, count($GLOBALS['__test_db_calls']), 'a non-pending current-version check performs no insert or metadata write');
MactrackStandaloneTest::assertSame($config_before_non_pending_check, $GLOBALS['__test_config'], 'a non-pending current-version check does not rewrite the retry marker');
$GLOBALS['__test_config']['mt_default_site_seed_pending'] = 'on';
$GLOBALS['__test_config']['mt_default_site_seed_attempts'] = '0';
$GLOBALS['__test_config']['mt_default_site_seed_next_retry'] = '0';
$GLOBALS['__test_db_fetch_cell_prepared'] = function ($sql) {
	if (strpos($sql, 'GET_LOCK') !== false || strpos($sql, 'RELEASE_LOCK') !== false) {
		return '1';
	}

	return '0';
};
$GLOBALS['__test_db_execute_prepared'] = function ($sql) {
	if (strpos($sql, 'INTO mac_track_sites') !== false) {
		throw new RuntimeException('simulated plugin-page insert exception');
	}

	return true;
};
MactrackStandaloneTest::assertSame(null, mactrack_check_upgrade(), 'the plugin-management lifecycle survives a throwing Default-site insert');
MactrackStandaloneTest::assertSame('1', $GLOBALS['__test_config']['mt_default_site_seed_attempts'], 'the surviving plugin page records its failed retry');
$page_exception_log = end($GLOBALS['__test_logs']);
MactrackStandaloneTest::assertContains('simulated plugin-page insert exception', $page_exception_log['message'], 'the surviving plugin page logs the swallowed exception');
$GLOBALS['__test_config']['mt_default_site_seed_pending'] = 'on';
$GLOBALS['__test_config']['mt_default_site_seed_attempts'] = '5';
$GLOBALS['__test_config']['mt_default_site_seed_next_retry'] = '0';
$GLOBALS['__test_db_execute_prepared'] = function () {
	return false;
};
MactrackStandaloneTest::assertSame(false, mactrack_setup_table_new(), 'an explicit failed setup remains recoverable without unwinding Cacti registration');
MactrackStandaloneTest::assertSame('1', $GLOBALS['__test_config']['mt_default_site_seed_attempts'], 'an operator-initiated setup bypasses an exhausted circuit and makes a fresh attempt');

$upgrade_reentry = MactrackProcessRunner::run([PHP_BINARY, __FILE__, 'upgrade-reentry-child']);
MactrackStandaloneTest::assertSame(0, $upgrade_reentry['status'], 'a repeated failed upgrade remains lifecycle-safe');
MactrackStandaloneTest::assertSame('3:4', $upgrade_reentry['output'], 'repeated failed upgrades preserve and increment the seed attempt count instead of resetting it');
MactrackStandaloneTest::assertContains('installed without a Default site', $upgrade_reentry['error'], 'a CLI install during active seed backoff remains visible to the operator');

$uninstall_calls_before = count($GLOBALS['__test_db_calls']);
MactrackStandaloneTest::assertSame(true, plugin_mactrack_uninstall(), 'the uninstall hook retains its successful lifecycle contract');
$uninstall_call = $GLOBALS['__test_db_calls'][$uninstall_calls_before];
MactrackStandaloneTest::assertSame('db_execute_prepared', $uninstall_call['fn'], 'uninstall clears retry state with a prepared statement');
MactrackStandaloneTest::assertSame(
	['mt_default_site_seed_pending', 'mt_default_site_seed_attempts', 'mt_default_site_seed_next_retry'],
	$uninstall_call['params'],
	'uninstall removes all Default-site retry settings so reinstall starts cleanly'
);
$GLOBALS['__test_current_page'] = 'plugin_manage.php';
$calls_before_check_config = count($GLOBALS['__test_db_calls']);
MactrackStandaloneTest::assertSame(true, plugin_mactrack_check_config(), 'Cacti check-config retains its dependency-only contract');
MactrackStandaloneTest::assertSame($calls_before_check_config, count($GLOBALS['__test_db_calls']), 'check-config performs no database work outside the upgrade page guard');

unlink($fixture_root . '/plugins/mactrack');
rmdir($fixture_root . '/plugins');
rmdir($fixture_root);

MactrackStandaloneTest::finish('MacTrack CLI setup failure propagation');
