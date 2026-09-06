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

if (getenv('MACTRACK_E2E') !== '1') {
	fwrite(STDERR, "This database-mutating check is restricted to the disposable E2E environment\n");
	exit(2);
}

require_once __DIR__ . '/../../../../include/cli_check.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../Support/E2eDatabaseGuard.php';

global $database_default;

if (!MactrackE2eDatabaseGuard::isDisposable($database_default, getenv('MACTRACK_E2E_EXPECT_DB'))) {
	fwrite(STDERR, "Refusing concurrent seed checks outside the dedicated mactrack_e2e database\n");
	exit(2);
}

if (($argv[1] ?? '') === 'worker') {
	exit(mactrack_seed_default_site() ? 0 : 1);
}

$original_sites = db_fetch_assoc('SELECT * FROM mac_track_sites ORDER BY site_id');
$restore_sites = function () use ($original_sites) {
	db_execute('DELETE FROM mac_track_sites');

	foreach ($original_sites as $site) {
		$columns = array_keys($site);
		$quoted_columns = array_map(function ($column) {
			return '`' . str_replace('`', '``', $column) . '`';
		}, $columns);
		$placeholders = implode(', ', array_fill(0, count($columns), '?'));
		db_execute_prepared(
			'INSERT INTO mac_track_sites (' . implode(', ', $quoted_columns) . ') VALUES (' . $placeholders . ')',
			array_values($site)
		);
	}
};
register_shutdown_function($restore_sites);

db_execute('DELETE FROM mac_track_sites');

$lock_name = 'mactrack.default.' . sha1((string) $database_default);
$lock_holder = new mysqli(
	(string) getenv('DB_HOST'),
	(string) getenv('DB_USER'),
	(string) getenv('DB_PASS'),
	(string) getenv('DB_NAME'),
	(int) (getenv('DB_PORT') ?: 3306)
);
$escaped_lock_name = $lock_holder->real_escape_string($lock_name);
$lock_result = $lock_holder->query("SELECT GET_LOCK('$escaped_lock_name', 0)");
$lock_row = $lock_result ? $lock_result->fetch_row() : null;

if (!$lock_row || (string) $lock_row[0] !== '1') {
	fwrite(STDERR, "Unable to hold the Default-site advisory lock for the timeout check\n");
	exit(1);
}

$lock_wait_started = microtime(true);
$locked_seed_result = mactrack_seed_default_site(1);
$lock_wait_elapsed = microtime(true) - $lock_wait_started;
$lock_holder->query("SELECT RELEASE_LOCK('$escaped_lock_name')");
$lock_holder->close();

if ($locked_seed_result || $lock_wait_elapsed > 3) {
	fwrite(STDERR, "Held-lock seed check returned unexpectedly or took too long ($lock_wait_elapsed seconds)\n");
	exit(1);
}

$workers = [];
$worker_count = 8;
$deadline = microtime(true) + 30;

for ($worker = 0; $worker < $worker_count; $worker++) {
	$output_path = tempnam(sys_get_temp_dir(), 'mactrack-worker-out-');
	$error_path = tempnam(sys_get_temp_dir(), 'mactrack-worker-err-');

	if ($output_path === false || $error_path === false) {
		fwrite(STDERR, "Unable to allocate concurrent Default-site worker output\n");
		exit(1);
	}

	$process = proc_open(
		[PHP_BINARY, __FILE__, 'worker'],
		[1 => ['file', $output_path, 'w'], 2 => ['file', $error_path, 'w']],
		$pipes
	);

	if (!is_resource($process)) {
		unlink($output_path);
		unlink($error_path);
		fwrite(STDERR, "Unable to start concurrent Default-site worker\n");
		exit(1);
	}

	$workers[] = [$process, $output_path, $error_path];
}

while ($workers) {
	foreach ($workers as $index => $worker) {
		$status = proc_get_status($worker[0]);

		if ($status['running']) {
			continue;
		}

		proc_close($worker[0]);
		$output = (string) file_get_contents($worker[1]) . (string) file_get_contents($worker[2]);
		unlink($worker[1]);
		unlink($worker[2]);
		unset($workers[$index]);

		if ($status['exitcode'] !== 0) {
			fwrite(STDERR, $output !== '' ? $output : "A concurrent Default-site worker failed\n");
			exit(1);
		}
	}

	if ($workers && microtime(true) >= $deadline) {
		foreach ($workers as $worker) {
			proc_terminate($worker[0]);
			proc_close($worker[0]);
			unlink($worker[1]);
			unlink($worker[2]);
		}

		fwrite(STDERR, "Concurrent Default-site workers exceeded the 30-second deadline\n");
		exit(1);
	}

	if ($workers) {
		usleep(10000);
	}
}

$site_count = db_fetch_cell('SELECT COUNT(*) FROM mac_track_sites');
$default_count = db_fetch_cell_prepared(
	'SELECT COUNT(*) FROM mac_track_sites WHERE site_name = ?',
	['Default']
);

if ((int) $site_count !== 1 || (int) $default_count !== 1) {
	fwrite(STDERR, "Concurrent setup created $site_count sites and $default_count Default rows\n");
	exit(1);
}

print "Mactrack held-lock timeout and concurrent Default-site setup passed\n";
