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

function mactrack_db_key_exists($table, $key) {
	return $GLOBALS['__test_mactrack_key_exists'] ?? false;
}

function mactrack_add_column($table, $column, $sql) {
}

function mactrack_add_index($table, $index, $sql) {
}

function mactrack_execute_sql($message, $sql) {
}

require_once __DIR__ . '/../../includes/database.php';

$schema_manifest = require __DIR__ . '/../Support/SchemaManifest.php';

$sites = [];
$queries = [];
$site_count = function ($sql, $params) use (&$sites, &$queries) {
	$queries[] = ['sql' => $sql, 'params' => $params];

	if (strpos($sql, 'GET_LOCK') !== false || strpos($sql, 'RELEASE_LOCK') !== false) {
		return '1';
	}

	return (string) count($sites);
};
$GLOBALS['__test_db_fetch_cell_prepared'] = $site_count;
$insert_result = true;
$insert_changes_table = true;
$execute_prepared = function ($sql, $params) use (&$sites, &$insert_result, &$insert_changes_table) {
	if (strpos($sql, 'INTO mac_track_sites') !== false) {
		if ($insert_result && $insert_changes_table && !$sites) {
			$sites[] = ['site_name' => $params[0], 'site_info' => $params[1]];
		}

		return $insert_result;
	}

	return true;
};
$GLOBALS['__test_db_execute_prepared'] = $execute_prepared;
$column_type = 'varchar(30)';
$GLOBALS['__test_db_fetch_assoc'] = function ($sql) use (&$column_type) {
	if (strpos($sql, 'SHOW COLUMNS FROM mac_track_ips') !== false) {
		return [['Field' => 'port_number', 'Type' => $column_type]];
	}

	return [];
};

MactrackStandaloneTest::assertSame(
	['port_number' => 'int(10)'],
	array_rekey([['Field' => 'port_number', 'Type' => 'int(10)']], 'Field', 'Type'),
	'the array_rekey double preserves Cacti key/value semantics'
);

mactrack_seed_default_site();
MactrackStandaloneTest::assertSame(
	[['site_name' => 'Default', 'site_info' => 'Default site']],
	$sites,
	'an empty sites table receives the Default site'
);
$insert_queries = array_values(array_filter($GLOBALS['__test_db_calls'], function ($query) {
	return $query['fn'] === 'db_execute_prepared' && strpos($query['sql'], 'INTO mac_track_sites') !== false;
}));
MactrackStandaloneTest::assertContains('WHERE NOT EXISTS', $insert_queries[0]['sql'], 'the helper uses one statement-scoped conditional insert');
MactrackStandaloneTest::assertSame(['Default', 'Default site'], $insert_queries[0]['params'], 'the idempotent insert binds the Default-site values');
$lock_queries = array_values(array_filter($queries, function ($query) {
	return strpos($query['sql'], 'GET_LOCK') !== false || strpos($query['sql'], 'RELEASE_LOCK') !== false;
}));
MactrackStandaloneTest::assertSame(2, count($lock_queries), 'Default-site creation acquires and releases one database lock');
MactrackStandaloneTest::assertSame($lock_queries[0]['params'][0], $lock_queries[1]['params'][0], 'Default-site setup releases the lock it acquired');

$sites = [];
$GLOBALS['__test_logs'] = [];
$GLOBALS['__test_db_fetch_cell_prepared'] = function ($sql, $params) use (&$sites, &$queries) {
	$queries[] = ['sql' => $sql, 'params' => $params];

	if (strpos($sql, 'GET_LOCK') !== false) {
		return '1';
	}

	if (strpos($sql, 'RELEASE_LOCK') !== false) {
		return '0';
	}

	return (string) count($sites);
};
MactrackStandaloneTest::assertSame(true, mactrack_seed_default_site(), 'site creation remains successful when lock ownership is lost before release');
$release_log = end($GLOBALS['__test_logs']);
MactrackStandaloneTest::assertContains('not owned', $release_log['message'], 'a failed advisory-lock release is visible in the Cacti log');
$GLOBALS['__test_db_fetch_cell_prepared'] = $site_count;

mactrack_seed_default_site();
MactrackStandaloneTest::assertSame(1, count($sites), 'repeated seeding does not duplicate Default');
$steady_state_calls = count($GLOBALS['__test_db_calls']);
$steady_state_queries = count($queries);
mactrack_seed_default_site();
MactrackStandaloneTest::assertSame($steady_state_calls, count($GLOBALS['__test_db_calls']), 'steady-state seeding issues no lock and no insert');
MactrackStandaloneTest::assertSame(1, count($queries) - $steady_state_queries, 'steady-state seeding performs only its read-only site check');
$GLOBALS['__test_messages'] = [];
MactrackStandaloneTest::assertSame(true, mactrack_ensure_default_site(), 'successful Default-site assurance returns success');
MactrackStandaloneTest::assertSame('off', $GLOBALS['__test_config']['mt_default_site_seed_pending'], 'successful assurance clears the focused seed retry marker');
MactrackStandaloneTest::assertSame([], $GLOBALS['__test_messages'], 'successful Default-site assurance raises no operator error');
$ensure_signature = new ReflectionFunction('mactrack_ensure_default_site');
MactrackStandaloneTest::assertSame('bool', $ensure_signature->getReturnType()->getName(), 'the notification helper has an explicit boolean contract');

$sites = [];
$insert_result = false;
$GLOBALS['__test_logs'] = [];
MactrackStandaloneTest::assertSame(false, mactrack_seed_default_site(), 'a failed Default-site insert is returned to the caller');
MactrackStandaloneTest::assertSame([], $sites, 'a failed insert does not change simulated table state');
MactrackStandaloneTest::assertSame('MACTRACK', $GLOBALS['__test_logs'][0]['type'], 'a failed Default-site insert is logged for MacTrack operators');
$insert_result = true;

$release_after_exception = false;
$GLOBALS['__test_db_fetch_cell_prepared'] = function ($sql) use (&$release_after_exception) {
	if (strpos($sql, 'GET_LOCK') !== false) {
		return '1';
	}

	if (strpos($sql, 'RELEASE_LOCK') !== false) {
		$release_after_exception = true;
		return '1';
	}

	return false;
};
$GLOBALS['__test_db_execute_prepared'] = function ($sql) {
	if (strpos($sql, 'INTO mac_track_sites') !== false) {
		throw new RuntimeException('simulated missing mac_track_sites table');
	}

	return true;
};
MactrackStandaloneTest::assertSame(false, mactrack_ensure_default_site(true), 'a database exception is converted to a lifecycle-safe failure result');
MactrackStandaloneTest::assertSame(true, $release_after_exception, 'the Default-site lock is released after a database exception');
MactrackStandaloneTest::assertSame('on', $GLOBALS['__test_config']['mt_default_site_seed_pending'], 'a database exception persists the focused seed retry marker');
MactrackStandaloneTest::assertSame([], $GLOBALS['__test_messages'], 'an initial transient database exception is logged without a premature operator banner');
$exception_log = end($GLOBALS['__test_logs']);
MactrackStandaloneTest::assertContains('simulated missing mac_track_sites table', $exception_log['message'], 'a missing sites table remains visible in the Cacti log without escaping the lifecycle');
$GLOBALS['__test_db_fetch_cell_prepared'] = $site_count;
$GLOBALS['__test_db_execute_prepared'] = $execute_prepared;

$sites = [];
$GLOBALS['__test_table_definitions'] = [];
$GLOBALS['__test_messages'] = [];
mactrack_reset_default_site_retry();
$GLOBALS['__test_db_execute_prepared'] = function ($sql) {
	if (strpos($sql, 'INTO mac_track_sites') !== false) {
		throw new RuntimeException('simulated setup insert exception');
	}

	return true;
};
MactrackStandaloneTest::assertSame(false, mactrack_setup_database(true), 'an active web-style schema setup returns failure after a throwing Default-site insert');

$created_after_seed_exception = array_keys($GLOBALS['__test_table_definitions']);
$expected_after_seed_exception = $schema_manifest['tables'];
sort($created_after_seed_exception);
sort($expected_after_seed_exception);
MactrackStandaloneTest::assertSame($expected_after_seed_exception, $created_after_seed_exception, 'a setup seed exception leaves the complete 22-table schema installed');
MactrackStandaloneTest::assertSame(1, count($GLOBALS['__test_messages']), 'an active web-style install reports its first seed failure immediately');
MactrackStandaloneTest::assertSame('mactrack_default_site_seed_failed', $GLOBALS['__test_messages'][0]['id'], 'the active install uses the stable Default-site message id');
MactrackStandaloneTest::assertTrue(strpos($GLOBALS['__test_messages'][0]['text'], 'after repeated attempts') === false, 'the first-attempt operator message does not claim that retries were exhausted');
$GLOBALS['__test_db_execute_prepared'] = $execute_prepared;
$GLOBALS['__test_config']['mt_default_site_seed_attempts'] = '0';
$GLOBALS['__test_config']['mt_default_site_seed_next_retry'] = '0';
$GLOBALS['__test_config']['mt_default_site_seed_pending'] = 'off';
$GLOBALS['__test_config']['mt_default_site_seed_notified'] = 'off';

$sites = [['site_name' => 'Custom', 'site_info' => 'Administrator site']];
mactrack_seed_default_site();
MactrackStandaloneTest::assertSame(
	[['site_name' => 'Custom', 'site_info' => 'Administrator site']],
	$sites,
	'a custom-only site table does not resurrect Default'
);

$sites = [['site_name' => 'Default', 'site_info' => 'Default site']];
mactrack_seed_default_site();
MactrackStandaloneTest::assertSame(1, count($sites), 'an existing Default site remains singular');

$sites = [
	['site_name' => 'Default', 'site_info' => 'Legacy duplicate one'],
	['site_name' => 'Default', 'site_info' => 'Legacy duplicate two'],
];
mactrack_seed_default_site();
MactrackStandaloneTest::assertSame(2, count($sites), 'seeding does not destructively rewrite pre-existing legacy duplicate sites');

foreach ([false, null, ''] as $failed_count) {
	$sites = [];
	$insert_result = false;
	$GLOBALS['__test_logs'] = [];
	$GLOBALS['__test_db_fetch_cell_prepared'] = function ($sql) use ($failed_count) {
		if (strpos($sql, 'GET_LOCK') !== false || strpos($sql, 'RELEASE_LOCK') !== false) {
			return '1';
		}

		return $failed_count;
	};
	$result = mactrack_seed_default_site();
	MactrackStandaloneTest::assertSame(0, count($sites), 'a failed insert and invalid verification query do not seed a site');
	MactrackStandaloneTest::assertSame(false, $result, 'a failed or invalid count query returns failure');
	MactrackStandaloneTest::assertSame('MACTRACK', $GLOBALS['__test_logs'][0]['type'], 'a failed count query is logged for MacTrack operators');
}

$GLOBALS['__test_db_fetch_cell_prepared'] = $site_count;
$insert_result = false;
$sites = [['site_name' => 'Default', 'site_info' => 'Concurrent winner']];
$GLOBALS['__test_logs'] = [];
MactrackStandaloneTest::assertSame(true, mactrack_seed_default_site(), 'a failed racing insert succeeds when another caller established the postcondition');
MactrackStandaloneTest::assertSame([], $GLOBALS['__test_logs'], 'a benign lost insert race does not raise an operator error');

$sites = [];
$insert_result = true;
$insert_changes_table = false;
$GLOBALS['__test_logs'] = [];
MactrackStandaloneTest::assertSame(false, mactrack_seed_default_site(), 'an executed insert without a resulting site fails postcondition verification');
MactrackStandaloneTest::assertSame('MACTRACK', $GLOBALS['__test_logs'][0]['type'], 'an unmet seed postcondition is logged');
$insert_changes_table = true;

$GLOBALS['__test_db_fetch_cell_prepared'] = function () {
	return '0';
};
$GLOBALS['__test_logs'] = [];
MactrackStandaloneTest::assertSame(false, mactrack_seed_default_site(), 'a lock timeout fails closed before attempting site creation');
MactrackStandaloneTest::assertContains('setup lock', $GLOBALS['__test_logs'][0]['message'], 'a lock timeout is visible in the Cacti log');

$lock_loser_counts = 0;
$GLOBALS['__test_db_fetch_cell_prepared'] = function ($sql) use (&$lock_loser_counts) {
	if (strpos($sql, 'GET_LOCK') !== false) {
		return '0';
	}

	$lock_loser_counts++;

	return $lock_loser_counts === 1 ? '0' : '1';
};
$GLOBALS['__test_logs'] = [];
MactrackStandaloneTest::assertSame(true, mactrack_seed_default_site(), 'a lock loser succeeds when another request already established the site postcondition');
MactrackStandaloneTest::assertSame([], $GLOBALS['__test_logs'], 'a satisfied lock-loser postcondition is not logged as a setup failure');

$sites = [];
$insert_result = false;
$GLOBALS['__test_logs'] = [];
$GLOBALS['__test_messages'] = [];
$GLOBALS['__test_db_fetch_cell_prepared'] = $site_count;
MactrackStandaloneTest::assertSame(false, mactrack_setup_database(), 'database setup returns a failed Default-site postcondition');
MactrackStandaloneTest::assertContains('Unable to insert', $GLOBALS['__test_logs'][0]['message'], 'database setup surfaces a failed Default-site seed');
MactrackStandaloneTest::assertSame([], $GLOBALS['__test_messages'], 'database setup does not attempt a web message in CLI mode');
$GLOBALS['__test_messages'] = [];
MactrackStandaloneTest::assertSame(null, mactrack_database_upgrade(), 'database upgrade retains its void migration contract');
MactrackStandaloneTest::assertSame([], $GLOBALS['__test_messages'], 'database upgrade does not attempt a web message in CLI mode');
MactrackStandaloneTest::assertSame('1', $GLOBALS['__test_config']['mt_default_site_seed_attempts'], 'multiple lifecycle calls in one request consume only one retry window');
$first_retry_delay = (int) $GLOBALS['__test_config']['mt_default_site_seed_next_retry'] - time();
MactrackStandaloneTest::assertTrue($first_retry_delay >= 50 && $first_retry_delay <= 60, 'the first failure schedules the 60-second retry window');
$queries_before_web_notice = count($queries);
$calls_before_web_notice = count($GLOBALS['__test_db_calls']);
MactrackStandaloneTest::assertSame(false, mactrack_ensure_default_site(true), 'the first web path after a CLI failure reports safely during backoff');
MactrackStandaloneTest::assertSame($queries_before_web_notice + 1, count($queries), 'the backoff path performs one read-only site check');
MactrackStandaloneTest::assertSame($calls_before_web_notice, count($GLOBALS['__test_db_calls']), 'the backoff path performs no lock or insert');
MactrackStandaloneTest::assertSame('on', $GLOBALS['__test_config']['mt_default_site_seed_pending'], 'failed assurance leaves the focused seed retry marker set');
MactrackStandaloneTest::assertSame([], $GLOBALS['__test_messages'], 'the web path suppresses the banner while transient retries remain');
MactrackStandaloneTest::assertSame(false, mactrack_ensure_default_site(true), 'a repeated request in the same backoff window remains lifecycle-safe');
MactrackStandaloneTest::assertSame('1', $GLOBALS['__test_config']['mt_default_site_seed_attempts'], 'a repeated request in the same backoff window does not consume another attempt');

$sites = [['site_name' => 'Recovered', 'site_info' => 'Added by another request']];
$calls_before_external_recovery = count($GLOBALS['__test_db_calls']);
MactrackStandaloneTest::assertSame(true, mactrack_retry_default_site(), 'the poller path detects a site added during an active backoff');
MactrackStandaloneTest::assertSame($calls_before_external_recovery, count($GLOBALS['__test_db_calls']), 'external recovery clears backoff without taking a lock or inserting');
MactrackStandaloneTest::assertSame('off', $GLOBALS['__test_config']['mt_default_site_seed_pending'], 'external recovery clears the pending marker immediately');
MactrackStandaloneTest::assertSame([], $GLOBALS['__test_messages'], 'external recovery raises no false failure message');
$sites = [];
$GLOBALS['__test_config']['mt_default_site_seed_pending'] = 'on';
$GLOBALS['__test_config']['mt_default_site_seed_attempts'] = '1';
$GLOBALS['__test_config']['mt_default_site_seed_next_retry'] = '0';

foreach ([2 => 300, 3 => 900, 4 => 1800, 5 => 3600] as $expected_attempt => $expected_delay) {
	$GLOBALS['__test_config']['mt_default_site_seed_next_retry'] = '0';
	$retry_started = time();
	MactrackStandaloneTest::assertSame(false, mactrack_ensure_default_site(true), "retry attempt $expected_attempt remains lifecycle-safe");
	MactrackStandaloneTest::assertSame((string) $expected_attempt, $GLOBALS['__test_config']['mt_default_site_seed_attempts'], "retry attempt $expected_attempt is recorded once");
	$scheduled_retry = (int) $GLOBALS['__test_config']['mt_default_site_seed_next_retry'];
	MactrackStandaloneTest::assertTrue($scheduled_retry >= $retry_started + $expected_delay && $scheduled_retry <= time() + $expected_delay, "retry attempt $expected_attempt uses its declared backoff");
}

MactrackStandaloneTest::assertSame(1, count($GLOBALS['__test_messages']), 'the fifth failed window raises the repeated-failure operator message');
MactrackStandaloneTest::assertSame('mactrack_default_site_seed_failed', $GLOBALS['__test_messages'][0]['id'], 'the repeated-failure path uses the stable Default-site message id');
MactrackStandaloneTest::assertSame(3, $GLOBALS['__test_messages'][0]['level'], 'the repeated-failure path uses Cacti MESSAGE_LEVEL_ERROR');
MactrackStandaloneTest::assertContains('after repeated attempts', $GLOBALS['__test_messages'][0]['text'], 'the exhausted retry path accurately identifies repeated failures');
$queries_before_capped_retry = count($queries);
$calls_before_capped_retry = count($GLOBALS['__test_db_calls']);
MactrackStandaloneTest::assertSame(false, mactrack_ensure_default_site(true), 'the degraded state remains lifecycle-safe during its hourly backoff');
MactrackStandaloneTest::assertSame($queries_before_capped_retry + 1, count($queries), 'the degraded hourly backoff performs only its recovery check');
MactrackStandaloneTest::assertSame($calls_before_capped_retry, count($GLOBALS['__test_db_calls']), 'the degraded hourly backoff performs no lock or insert early');
MactrackStandaloneTest::assertSame(2, count($GLOBALS['__test_messages']), 'the degraded state remains operator-visible on later web requests');
$insert_result = true;
$GLOBALS['__test_config']['mt_default_site_seed_next_retry'] = '0';
MactrackStandaloneTest::assertSame(true, mactrack_retry_default_site(), 'the degraded state automatically retries and recovers after its hourly delay');
MactrackStandaloneTest::assertSame('off', $GLOBALS['__test_config']['mt_default_site_seed_pending'], 'successful recovery clears the pending marker');
MactrackStandaloneTest::assertSame('0', $GLOBALS['__test_config']['mt_default_site_seed_attempts'], 'successful recovery clears the attempt counter');
MactrackStandaloneTest::assertSame('0', $GLOBALS['__test_config']['mt_default_site_seed_next_retry'], 'successful recovery clears the retry timestamp');

$sites = [['site_name' => 'Custom', 'site_info' => 'Administrator site']];
mactrack_database_upgrade();
MactrackStandaloneTest::assertSame(
	[['site_name' => 'Custom', 'site_info' => 'Administrator site']],
	$sites,
	'the database upgrade path does not resurrect Default in a populated table'
);

$upgrade_sql = function ($type) use (&$column_type) {
	$column_type = $type;
	$GLOBALS['__test_db_calls'] = [];
	mactrack_database_upgrade();

	return array_column(array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute';
	}), 'sql');
};

$ip_port_conversion = "ALTER TABLE mac_track_ips MODIFY COLUMN port_number varchar(20) NOT NULL default ''";
$port_expansion = "ALTER TABLE mac_track_ports MODIFY COLUMN port_number varchar(30) NOT NULL default ''";
$control_sql = $upgrade_sql('text');
$integer_sql = $upgrade_sql('int(10)');
MactrackStandaloneTest::assertTrue(
	!in_array($ip_port_conversion, $control_sql, true),
	'the non-matching migration control does not convert integer IP port numbers'
);
MactrackStandaloneTest::assertTrue(
	in_array($ip_port_conversion, $integer_sql, true),
	'the upgrade converts integer port numbers to varchar(20)'
);

$varchar_sql = $upgrade_sql('varchar(20)');
MactrackStandaloneTest::assertTrue(!in_array($ip_port_conversion, $varchar_sql, true), 'the varchar migration does not execute the integer-only conversion');
MactrackStandaloneTest::assertSame(
	count(array_keys($control_sql, $port_expansion, true)) + 1,
	count(array_keys($varchar_sql, $port_expansion, true)),
	'the varchar(20) branch adds exactly one port-number expansion beyond the unconditional migration'
);

MactrackStandaloneTest::finish('MacTrack Default-site idempotency');
