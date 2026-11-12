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
 * Converting the port table never alters the live table: the data is
 * copied into mac_track_ports_new scan by scan, the row counts must match,
 * and one RENAME swaps the tables. A scan lock keeps scans and the
 * conversion apart, and the server frees it if the conversion dies.
 */

beforeAll(function () {
	mactrack_test_load(dirname(__DIR__, 2) . '/lib/mactrack_functions.php');
});

beforeEach(function () {
	$GLOBALS['__test_db_calls']        = array();
	$GLOBALS['__test_next_returns']    = array();
	$GLOBALS['__test_matched_returns'] = array();
	$GLOBALS['__test_config_options']  = array(
		'mt_collection_timing'     => '15',
		'mt_data_retention'        => '7',
		'mt_partition_granularity' => 'scan',
	);
	$GLOBALS['__test_cacti_log']       = array();
	$GLOBALS['database_default']       = 'cacti';
	$GLOBALS['database_sessions']      = array();
});

// 2026-11-12 06:15:07 and 06:30:07 UTC.
const MT_PC_SCAN1 = 1794464107;
const MT_PC_SCAN2 = 1794465007;

/**
 * Queues the database answers a conversion asks for.
 *
 * @param array $opts layout (none, legacy, range, invalid), type, scan_lock,
 *                    old_exists, rows, copied, scans.
 * @return void
 */
function mt_pc_db(array $opts = array()): void {
	$part = fn ($name, $pos, $method, $expr, $desc) => array(
		'PARTITION_NAME' => $name, 'PARTITION_ORDINAL_POSITION' => $pos, 'PARTITION_METHOD' => $method,
		'PARTITION_EXPRESSION' => $expr, 'PARTITION_DESCRIPTION' => $desc, 'TABLE_ROWS' => '0',
	);
	$layouts = array(
		'none'    => array(),
		'legacy'  => array($part('d20261111', '1', 'RANGE', 'to_days(`scan_date`)', '740297'), $part('dMaxValue', '2', 'RANGE', 'to_days(`scan_date`)', 'MAXVALUE')),
		'range'   => array($part('dMaxValue', '1', 'RANGE', 'unix_timestamp(`scan_date`)', 'MAXVALUE')),
		'invalid' => array($part('p0', '1', 'LIST', 'device_id', '1')),
	);

	mactrack_test_queue_return_for('db_fetch_assoc_prepared', 'information_schema.PARTITIONS', $layouts[$opts['layout'] ?? 'none']);
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'VERSION()', '10.5.29-MariaDB');
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'PLUGIN_STATUS', $opts['plugin'] ?? 'ACTIVE');
	mactrack_test_queue_return_for('db_table_exists', 'processes', isset($opts['upgrade_started']));
	mactrack_test_queue_return_for('db_fetch_row_prepared', 'FROM processes', isset($opts['upgrade_started']) ? array('pid' => '99', 'started' => $opts['upgrade_started'], 'now' => '100000', 'timeout' => '86400') : array());
	mactrack_test_queue_return_for('db_table_exists', 'mac_track_aggregated_ports_upgrade', $opts['upgrade_table'] ?? false);
	mactrack_test_queue_return_for('db_table_exists', 'mac_track_ports_old', $opts['old_exists'] ?? false);
	mactrack_test_queue_return_for('db_fetch_cell_prepared', "GET_LOCK('mactrack_scan'", $opts['scan_lock'] ?? '1');
	mactrack_test_queue_return_for('db_fetch_cell_prepared', "GET_LOCK('mactrack_partitions'", $opts['partition_lock'] ?? '1');
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'DATA_TYPE', $opts['type'] ?? 'timestamp');
	mactrack_test_queue_return_for('db_fetch_cell_prepared', '@@SESSION.time_zone', 'SYSTEM');
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'lock_wait_timeout', '50');
	mactrack_test_queue_return_for('db_fetch_assoc_prepared', 'SELECT DISTINCT scan_date', $opts['scans'] ?? array(
		array('scan_date' => '0000-00-00 00:00:00', 'epoch' => '0'),
		array('scan_date' => '2026-11-12 06:15:07', 'epoch' => (string) MT_PC_SCAN1),
		array('scan_date' => '2026-11-12 06:30:07', 'epoch' => (string) MT_PC_SCAN2),
	));
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'COUNT(*) FROM `mac_track_ports`', $opts['rows'] ?? '30');
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'COUNT(*) FROM `mac_track_ports_new`', $opts['copied'] ?? '30');
}

/**
 * Returns the statements run through db_execute_prepared(), session
 * settings left out.
 *
 * @return string[]
 */
function mt_pc_statements(): array {
	$calls = array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_execute_prepared' && strpos($c['sql'], 'SET SESSION lock_wait_timeout') !== 0);

	return array_values(array_map(fn ($c) => preg_replace('/\s+/', ' ', $c['sql']), $calls));
}

/**
 * Returns the SQL of every lock call made.
 *
 * @return string[]
 */
function mt_pc_locks(): array {
	return array_values(array_map(fn ($c) => $c['sql'], array_filter($GLOBALS['__test_db_calls'], fn ($c) => strpos($c['sql'], '_LOCK(') !== false)));
}

describe('bounds', function () {
	it('gives each scan its own partition, skipping zero dates', function () {
		expect(mactrack_partition_convert_bounds(array('0', MT_PC_SCAN1, MT_PC_SCAN2), 'scan', 1024))->toBe(array(
			'granularity' => 'scan',
			'bounds'      => array('s20261112061507' => MT_PC_SCAN1 + 1, 's20261112063007' => MT_PC_SCAN2 + 1),
			'reason'      => '',
		));
	});

	it('uses hourly partitions when one per scan would pass the cap', function () {
		expect(mactrack_partition_convert_bounds(array(MT_PC_SCAN1, MT_PC_SCAN2), 'scan', 2))->toBe(array(
			'granularity' => 'hour',
			'bounds'      => array('h2026111206' => 1794466800),
			'reason'      => '',
		));
	});

	it('uses daily partitions when hourly ones would pass the cap', function () {
		$hours = array(MT_PC_SCAN1, MT_PC_SCAN1 + 3600, MT_PC_SCAN1 + 7200);

		expect(mactrack_partition_convert_bounds($hours, 'hour', 2)['bounds'])->toBe(array('d20261112' => 1794528000));
	});

	it('never goes finer than the setting', function () {
		expect(mactrack_partition_convert_bounds(array(MT_PC_SCAN1, MT_PC_SCAN2), 'day', 1024)['granularity'])->toBe('day');
	});

	it('gives the reason when even daily partitions pass the cap', function () {
		$days = array(MT_PC_SCAN1, MT_PC_SCAN1 + 86400, MT_PC_SCAN1 + 172800);
		$r    = mactrack_partition_convert_bounds($days, 'scan', 3);

		expect($r['granularity'])->toBe('');
		expect($r['reason'])->toContain('needs 4 daily partitions, more than the limit of 3');
	});

	it('returns the bounds in increasing order', function () {
		expect(array_values(mactrack_partition_convert_bounds(array(MT_PC_SCAN2, MT_PC_SCAN1), 'scan', 1024)['bounds']))->toBe(array(MT_PC_SCAN1 + 1, MT_PC_SCAN2 + 1));
	});
});

describe('conversion', function () {
	it('copies scan by scan into a new table and swaps it in', function () {
		mt_pc_db();

		$r = mactrack_partition_convert();

		expect($r)->toBe(array('ok' => true, 'reason' => '', 'rows' => 30, 'partitions' => 3, 'granularity' => 'scan'));
		expect(mt_pc_statements())->toBe(array(
			"SET SESSION time_zone = '+00:00'",
			'DROP TABLE IF EXISTS `mac_track_ports_new`',
			'CREATE TABLE `mac_track_ports_new` LIKE `mac_track_ports`',
			'ALTER TABLE `mac_track_ports_new` PARTITION BY RANGE (UNIX_TIMESTAMP(scan_date)) (PARTITION `s20261112061507` VALUES LESS THAN (1794464108), PARTITION `s20261112063007` VALUES LESS THAN (1794465008), PARTITION `dMaxValue` VALUES LESS THAN MAXVALUE)',
			'INSERT INTO `mac_track_ports_new` SELECT * FROM `mac_track_ports` WHERE scan_date = ?',
			'INSERT INTO `mac_track_ports_new` SELECT * FROM `mac_track_ports` WHERE scan_date = ?',
			'INSERT INTO `mac_track_ports_new` SELECT * FROM `mac_track_ports` WHERE scan_date = ?',
			'RENAME TABLE `mac_track_ports` TO `mac_track_ports_old`, `mac_track_ports_new` TO `mac_track_ports`',
			'SET SESSION time_zone = ?',
			'DROP TABLE `mac_track_ports_old`',
		));
	});

	it('copies every scan date, zero dates included, as the server returned it', function () {
		mt_pc_db();

		mactrack_partition_convert();

		$copies = array_filter($GLOBALS['__test_db_calls'], fn ($c) => strpos($c['sql'], 'INSERT INTO `mac_track_ports_new`') === 0);
		expect(array_values(array_column($copies, 'params')))->toBe(array(array('0000-00-00 00:00:00'), array('2026-11-12 06:15:07'), array('2026-11-12 06:30:07')));
	});

	it('restores the session time zone', function () {
		mt_pc_db();

		mactrack_partition_convert();

		$restore = current(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['sql'] === 'SET SESSION time_zone = ?'));
		expect($restore['params'])->toBe(array('SYSTEM'));
	});

	it('removes the legacy partitioning from the copy', function () {
		mt_pc_db(array('layout' => 'legacy'));

		expect(mactrack_partition_convert()['ok'])->toBeTrue();
		expect(mt_pc_statements())->toContain('ALTER TABLE `mac_track_ports_new` REMOVE PARTITIONING');
	});

	it('makes a DATETIME scan_date a TIMESTAMP and reads it in the session zone', function () {
		mt_pc_db(array('type' => 'datetime'));

		expect(mactrack_partition_convert()['ok'])->toBeTrue();

		$statements = mt_pc_statements();
		expect($statements)->toContain("ALTER TABLE `mac_track_ports_new` MODIFY `scan_date` TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00'");
		expect(array_filter($statements, fn ($s) => strpos($s, 'time_zone') !== false))->toBe(array());
	});

	it('keeps the original table when asked', function () {
		mt_pc_db();

		mactrack_partition_convert(true);

		expect(mt_pc_statements())->not->toContain('DROP TABLE `mac_track_ports_old`');
	});

	it('reports each copied scan', function () {
		mt_pc_db();
		$seen = array();

		mactrack_partition_convert(false, function (int $done, int $total, string $scan) use (&$seen) {
			$seen[] = "$done/$total $scan";
		});

		expect($seen)->toBe(array('1/3 0000-00-00 00:00:00', '2/3 2026-11-12 06:15:07', '3/3 2026-11-12 06:30:07'));
	});

	it('aborts without the swap when the row counts differ', function () {
		mt_pc_db(array('copied' => '29'));

		$r = mactrack_partition_convert();

		expect($r['ok'])->toBeFalse();
		expect($r['reason'])->toContain('30 rows but 29 were copied');
		expect(implode("\n", mt_pc_statements()))->not->toContain('RENAME');
		expect(end($GLOBALS['__test_db_calls'])['fn'])->not->toBe('db_execute_prepared');
		expect(mt_pc_statements())->toContain('DROP TABLE IF EXISTS `mac_track_ports_new`');
		expect(array_slice(mt_pc_statements(), -2))->toBe(array('DROP TABLE IF EXISTS `mac_track_ports_new`', 'SET SESSION time_zone = ?'));
	});

	it('aborts without the swap when a copy fails', function () {
		mt_pc_db();
		mactrack_test_queue_return_for('db_execute_prepared', 'INSERT INTO `mac_track_ports_new`', false);

		$r = mactrack_partition_convert();

		expect($r['reason'])->toBe('Could not copy the scan of 0000-00-00 00:00:00.');
		expect(implode("\n", mt_pc_statements()))->not->toContain('RENAME');
	});

	it('aborts without the swap when a DDL step fails', function (string $fragment, string $reason) {
		mt_pc_db();
		mactrack_test_queue_return_for('db_execute_prepared', $fragment, false);

		$r = mactrack_partition_convert(false, null, 0);

		expect($r['ok'])->toBeFalse();
		expect($r['reason'])->toContain($reason);
		expect($GLOBALS['__test_config_options']['mt_partition_converting'])->toBe('');
	})->with(array(
		'create'    => array('CREATE TABLE `mac_track_ports_new` LIKE', 'Could not prepare the new table (CREATE TABLE'),
		'partition' => array('ALTER TABLE `mac_track_ports_new` PARTITION BY', 'Could not partition the new table.'),
		'swap'      => array('RENAME TABLE', 'Could not swap in the new table'),
	));

	it('reports a converted table whose original could not be dropped', function () {
		mt_pc_db();
		mactrack_test_queue_return_for('db_execute_prepared', 'DROP TABLE `mac_track_ports_old`', false);

		$r = mactrack_partition_convert(false, null, 0);

		expect($r['ok'])->toBeTrue();
		expect($r['reason'])->toBe('Converted, but mac_track_ports_old could not be dropped.');
	});

	it('aborts when even daily partitions would pass the cap', function () {
		$scans = array();

		for ($d = 0; $d < 1100; $d++) {
			$scans[] = array('scan_date' => gmdate('Y-m-d H:i:s', MT_PC_SCAN1 - $d * 86400), 'epoch' => (string) (MT_PC_SCAN1 - $d * 86400));
		}

		mt_pc_db(array('scans' => $scans));

		$r = mactrack_partition_convert();

		expect($r['ok'])->toBeFalse();
		expect($r['reason'])->toContain('needs 1101 daily partitions');
		expect(implode("\n", mt_pc_statements()))->not->toContain('INSERT');
	});

	it('records the outcome in the partition status', function () {
		mt_pc_db(array('copied' => '1'));

		mactrack_partition_convert();

		$status = json_decode($GLOBALS['__test_config_options']['mt_partition_status'], true);
		expect($status['conversion']['ok'])->toBeFalse();
		expect($status['conversion']['time'])->toBeInt();
	});

	it('clears the conversion flag and releases both locks on every path', function (array $opts) {
		mt_pc_db($opts);

		mactrack_partition_convert();

		expect($GLOBALS['__test_config_options']['mt_partition_converting'])->toBe('');
		expect(mt_pc_locks())->toBe(array(
			"SELECT GET_LOCK('mactrack_scan', 0)",
			"SELECT GET_LOCK('mactrack_partitions', 10)",
			"SELECT RELEASE_LOCK('mactrack_partitions')",
			"SELECT RELEASE_LOCK('mactrack_scan')",
		));
	})->with(array(
		'success'        => array(array()),
		'count mismatch' => array(array('copied' => '0')),
	));

	it('refuses before taking any lock or changing anything', function (array $opts, string $reason) {
		mt_pc_db($opts);

		$r = mactrack_partition_convert();

		expect($r['ok'])->toBeFalse();
		expect($r['reason'])->toContain($reason);
		expect(mt_pc_statements())->toBe(array());
		expect($GLOBALS['__test_config_options'])->not->toHaveKey('mt_partition_converting');
	})->with(array(
		'already converted'  => array(array('layout' => 'range'), 'already uses the scan-date layout'),
		'unknown partitions' => array(array('layout' => 'invalid'), 'does not recognize: Partition method is LIST'),
		'no partitioning'    => array(array('plugin' => 'DISABLED'), 'does not support partitioning'),
		'upgrade running'    => array(array('upgrade_started' => '90000'), 'database upgrade is running'),
		'upgrade left work'  => array(array('upgrade_table' => true), 'database upgrade is running'),
		'kept old table'     => array(array('old_exists' => true), 'mac_track_ports_old is left'),
		'scan running'       => array(array('scan_lock' => '0'), 'A scan is running'),
	));

	it('leaves the running conversion\'s status alone when a second one starts', function () {
		$GLOBALS['__test_config_options']['mt_partition_converting'] = '1794464107';
		mt_pc_db(array('scan_lock' => '0'));

		$r = mactrack_partition_convert();

		expect($r['ok'])->toBeFalse();
		expect($r['reason'])->toBe('Another conversion is running.');
		expect($GLOBALS['__test_config_options'])->not->toHaveKey('mt_partition_status');
		expect($GLOBALS['__test_config_options']['mt_partition_converting'])->toBe('1794464107');
		expect(mt_pc_statements())->toBe(array());
	});

	it('releases the scan lock when partition maintenance holds its lock', function () {
		mt_pc_db(array('partition_lock' => '0'));

		expect(mactrack_partition_convert()['reason'])->toContain('Partition maintenance is running');
		expect(mt_pc_locks())->toBe(array("SELECT GET_LOCK('mactrack_scan', 0)", "SELECT GET_LOCK('mactrack_partitions', 10)", "SELECT RELEASE_LOCK('mactrack_scan')"));
		expect(mt_pc_statements())->toBe(array());
	});
});

describe('scan lock', function () {
	it('lets a scan start when no conversion holds the lock', function () {
		mactrack_test_queue_return_for('db_fetch_cell_prepared', "GET_LOCK('mactrack_scan'", '1');

		expect(mactrack_partition_scan_begin())->toBeTrue();
		expect($GLOBALS['__test_cacti_log'])->toBe(array());
	});

	it('stops a scan while the lock is held', function () {
		$GLOBALS['__test_config_options']['mt_partition_converting'] = '1794464107';
		mactrack_test_queue_return_for('db_fetch_cell_prepared', "GET_LOCK('mactrack_scan'", '0');

		expect(mactrack_partition_scan_begin())->toBeFalse();
		expect($GLOBALS['__test_config_options']['mt_partition_converting'])->toBe('1794464107');
	});

	it('is released by a web action that took it', function () {
		mactrack_partition_scan_end();

		expect(array_column($GLOBALS['__test_db_calls'], 'sql'))->toBe(array("SELECT RELEASE_LOCK('mactrack_scan')"));
	});

	it('clears the flag of a conversion that died', function () {
		$GLOBALS['__test_config_options']['mt_partition_converting'] = '1794464107';
		mactrack_test_queue_return_for('db_fetch_cell_prepared', "GET_LOCK('mactrack_scan'", '1');

		expect(mactrack_partition_scan_begin())->toBeTrue();
		expect($GLOBALS['__test_config_options']['mt_partition_converting'])->toBe('');
		expect($GLOBALS['__test_cacti_log'][0])->toContain('conversion stopped without finishing');
	});

	it('is taken by the poller before any scan or maintenance', function () {
		$src  = file_get_contents(dirname(__DIR__, 2) . '/poller_mactrack.php');
		$lock = strpos($src, 'if (!mactrack_partition_scan_begin()) {');

		expect($lock)->not->toBeFalse();
		expect($lock)->toBeLessThan(strpos($src, "if (\$site_id > 0) {\n\t\tmactrack_debug('About to enter Mactrack Site Scan Processing');"));
	});

	it('pauses database maintenance during a conversion', function () {
		$GLOBALS['__test_config_options']['mt_partition_converting'] = '1794464107';

		perform_mactrack_db_maint();

		expect(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] !== 'cacti_log'))->toBe(array());
		expect($GLOBALS['__test_cacti_log'][0])->toContain('skipped while the port table is converted');
	});
});

describe('fresh install', function () {
	it('partitions a new table with dMaxValue only', function () {
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'VERSION()', '8.4.3');
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'lock_wait_timeout', '50');

		expect(mactrack_partition_fresh_install())->toBeTrue();
		expect(mt_pc_statements())->toBe(array('ALTER TABLE `mac_track_ports` PARTITION BY RANGE (UNIX_TIMESTAMP(scan_date)) (PARTITION `dMaxValue` VALUES LESS THAN MAXVALUE)'));
	});

	it('leaves the table unpartitioned without partitioning or a readable version', function (string $version, string $plugin) {
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'VERSION()', $version);
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'PLUGIN_STATUS', $plugin);

		expect(mactrack_partition_fresh_install())->toBeFalse();
		expect(mt_pc_statements())->toBe(array());
	})->with(array(
		'MariaDB without the plugin' => array('10.5.29-MariaDB', ''),
		'unreadable version'         => array('', 'ACTIVE'),
	));

	it('partitions the port table when setup creates it', function () {
		mactrack_test_load(dirname(__DIR__, 2) . '/includes/database.php');
		mactrack_test_queue_return_for('db_table_exists', 'mac_track_ports', false);
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'VERSION()', '10.5.29-MariaDB');
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'PLUGIN_STATUS', 'ACTIVE');

		mactrack_setup_database();

		expect(mt_pc_statements())->toContain('ALTER TABLE `mac_track_ports` PARTITION BY RANGE (UNIX_TIMESTAMP(scan_date)) (PARTITION `dMaxValue` VALUES LESS THAN MAXVALUE)');
	});

	it('partitions only a table setup has just created', function () {
		$src = file_get_contents(dirname(__DIR__, 2) . '/includes/database.php');

		expect($src)->toContain("\$ports_existed     = db_table_exists('mac_track_ports');\n\tapi_plugin_db_table_create('mactrack', 'mac_track_ports', \$data);\n\n\tif (!\$ports_existed) {");
	});
});

describe('entry points', function () {
	it('starts the conversion from Utilities only on a POST', function () {
		$src = file_get_contents(dirname(__DIR__, 2) . '/mactrack_utilities.php');

		expect(mactrack_utilities_may_run('mactrack_utilities_convert_ports', 'GET'))->toBeFalse();
		expect(mactrack_utilities_may_run('mactrack_utilities_convert_ports', 'POST'))->toBeTrue();
		expect($src)->toContain("mactrack_utilities_action_link('mactrack_utilities_convert_ports'");
	});

	it('runs the conversion from the CLI and exits 1 when it fails', function () {
		$src = file_get_contents(dirname(__DIR__, 2) . '/mactrack_convert.php');

		expect($src)->toContain('$result = mactrack_partition_convert($keep_old,');
		expect($src)->toContain("if (!\$result['ok']) {\n\tprint 'FATAL: ' . \$result['reason'] . \"\\n\";\n\n\texit(1);");
		expect($src)->not->toContain('function mactrack_create_partitioned_table');
	});

	it('accepts and ignores the options of the old CLI', function () {
		$src = file_get_contents(dirname(__DIR__, 2) . '/mactrack_convert.php');

		foreach (array("case '--days':", "case '--engine':", "case '--charset':", "case '--collate':") as $case) {
			expect($src)->toContain($case);
		}

		expect($src)->toContain('is no longer used and is ignored.');
	});
});
