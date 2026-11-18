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
 * cli/upgrade_partitioning.php checks mac_track_ports and mac_track_ips
 * against the selected scheme and rebuilds the ones that drifted: each table
 * is renamed to <table>_rebuild, created again, filled one scan date at a
 * time and the renamed table dropped, serially, under the scan lock.
 */

beforeAll(function () {
	require_once dirname(__DIR__) . '/Support/load_function.php';
	mactrack_test_load(dirname(__DIR__, 2) . '/lib/mactrack_functions.php');
	mactrack_test_load(dirname(__DIR__, 2) . '/includes/database.php');
	mactrack_test_load(dirname(__DIR__, 2) . '/setup.php');
	mactrack_test_load(dirname(__DIR__, 2) . '/cli/upgrade_partitioning.php');

	if (!defined('COPYRIGHT_YEARS')) {
		define('COPYRIGHT_YEARS', '2004-2026');
	}
});

beforeEach(function () {
	// The CLI prints its progress; keep it out of the test report.
	$GLOBALS['__mt_ur_level'] = ob_get_level();
	ob_start();

	$GLOBALS['__test_db_calls']        = [];
	$GLOBALS['__test_next_returns']    = [];
	$GLOBALS['__test_matched_returns'] = [];
	$GLOBALS['__test_config_options']  = [
		'mt_collection_timing'     => '15',
		'mt_data_retention'        => '7',
		'mt_data_retention_ip'     => '3',
		'mt_partition_granularity' => 'week',
	];
	$GLOBALS['__test_cacti_log']  = [];
	$GLOBALS['database_default']  = 'cacti';
	$GLOBALS['database_sessions'] = [];
	$GLOBALS['debug']             = false;
});

afterEach(function () {
	while (ob_get_level() > $GLOBALS['__mt_ur_level']) {
		ob_end_clean();
	}
});

// 2026-11-12 06:15:07 UTC, a Thursday; its week starts Monday 2026-11-09.
const MT_UR_NOW   = 1794464107;
const MT_UR_SCANS = [
	['scan_date' => '2026-11-12 06:15:07', 'epoch' => '1794464107'],
	['scan_date' => '2026-11-12 06:30:07', 'epoch' => '1794465007'],
];

/**
 * Builds one information_schema.PARTITIONS row.
 *
 * @param string $name Partition name.
 * @param int    $pos  Ordinal position.
 * @param string $desc VALUES LESS THAN bound, or MAXVALUE.
 * @param string $rows Estimated rows.
 *
 * @return array
 */
function mt_ur_part(string $name, int $pos, string $desc, string $rows = '0'): array {
	return [
		'PARTITION_NAME'       => $name, 'PARTITION_ORDINAL_POSITION' => (string) $pos, 'PARTITION_METHOD' => 'RANGE',
		'PARTITION_EXPRESSION' => 'unix_timestamp(`scan_date`)', 'PARTITION_DESCRIPTION' => $desc, 'TABLE_ROWS' => $rows,
	];
}

/**
 * Returns the PARTITIONS rows of a layout.
 *
 * @param string $kind none, weekly, daily, mixed, legacy or invalid.
 *
 * @return array
 */
function mt_ur_layout(string $kind): array {
	$max = fn (int $pos) => mt_ur_part('dMaxValue', $pos, 'MAXVALUE');

	return [
		'none'    => [],
		'weekly'  => [mt_ur_part('w20261102', 1, '1794182400', '500'), mt_ur_part('w20261109', 2, '1794787200', '700'), $max(3)],
		'daily'   => [mt_ur_part('d20261112', 1, '1794528000'), $max(2)],
		'mixed'   => [mt_ur_part('d20261112', 1, '1794528000'), mt_ur_part('w20261109', 2, '1794787200'), $max(3)],
		'legacy'  => [['PARTITION_NAME' => 'd20261111', 'PARTITION_ORDINAL_POSITION' => '1', 'PARTITION_METHOD' => 'RANGE', 'PARTITION_EXPRESSION' => 'to_days(`scan_date`)', 'PARTITION_DESCRIPTION' => '740297', 'TABLE_ROWS' => '0'],
			['PARTITION_NAME' => 'dMaxValue', 'PARTITION_ORDINAL_POSITION' => '2', 'PARTITION_METHOD' => 'RANGE', 'PARTITION_EXPRESSION' => 'to_days(`scan_date`)', 'PARTITION_DESCRIPTION' => 'MAXVALUE', 'TABLE_ROWS' => '0']],
		'invalid' => [['PARTITION_NAME' => 'p0', 'PARTITION_ORDINAL_POSITION' => '1', 'PARTITION_METHOD' => 'LIST', 'PARTITION_EXPRESSION' => 'device_id', 'PARTITION_DESCRIPTION' => '1', 'TABLE_ROWS' => '0']],
	][$kind];
}

/**
 * Queues the database answers a rebuild asks for. Table existence checks all
 * share one query, so they are answered in order by mt_ur_exists().
 *
 * @param array $opts type, src_partitioned, scans, layout, old, new, newest,
 *                    scan_lock, partition_lock, plugin.
 *
 * @return void
 */
function mt_ur_db(array $opts = []): void {
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'VERSION()', '10.5.29-MariaDB');
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'PLUGIN_STATUS', $opts['plugin'] ?? 'ACTIVE');
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'DATA_TYPE', $opts['type'] ?? 'timestamp');
	mactrack_test_queue_return_for('db_fetch_cell_prepared', '@@SESSION.time_zone', 'SYSTEM');
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'lock_wait_timeout', '50');
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'COUNT(*) FROM information_schema.PARTITIONS', ($opts['src_partitioned'] ?? false) ? '3' : '0');
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'PARTITION (`dMaxValue`)', $opts['stranded'] ?? '0');
	$old = array_key_exists('old', $opts) ? $opts['old'] : '2';

	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'MAX(scan_date)', $opts['newest'] ?? null);
	mactrack_test_queue_return_for('db_fetch_cell_prepared', '_rebuild`', $old);
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'COUNT(*) FROM `mac_track_ports`', $opts['new'] ?? '2');
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'COUNT(*) FROM `mac_track_ips`', $opts['new'] ?? '2');
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'COUNT(*) FROM `mac_track_dot1x`', $opts['new'] ?? '2');
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'GET_LOCK(?, 0)', $opts['scan_lock'] ?? '1');
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'GET_LOCK(?, 10)', $opts['partition_lock'] ?? '1');
	mactrack_test_queue_return_for('db_fetch_assoc_prepared', 'SELECT DISTINCT scan_date', $opts['scans'] ?? MT_UR_SCANS);

	if (isset($opts['layout'])) {
		mactrack_test_queue_return_for('db_fetch_assoc_prepared', 'information_schema.PARTITIONS', mt_ur_layout($opts['layout']));
	}
}

/**
 * Answers the next table existence checks, in order.
 *
 * @param int[] $answers 1 for a table that exists, 0 for one that does not.
 *
 * @return void
 */
function mt_ur_exists(array $answers): void {
	foreach ($answers as $answer) {
		mactrack_test_queue_return('db_fetch_cell_prepared', (string) $answer);
	}
}

/**
 * Returns the statements run through db_execute_prepared(), lock-wait
 * session settings left out.
 *
 * @return string[]
 */
function mt_ur_sql(): array {
	$calls = array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_execute_prepared' && strpos($c['sql'], 'SET SESSION lock_wait_timeout') !== 0);

	return array_values(array_map(fn ($c) => preg_replace('/\s+/', ' ', $c['sql']), $calls));
}

/**
 * Returns the statements that start with, or contain, a fragment.
 *
 * @param string $fragment Text to look for.
 *
 * @return string[]
 */
function mt_ur_find(string $fragment): array {
	return array_values(array_filter(mt_ur_sql(), fn ($sql) => strpos($sql, $fragment) !== false));
}

/**
 * Runs a callable and returns what it printed.
 *
 * @param callable $fn Code to run.
 *
 * @return array{0: mixed, 1: string} Its result and its output.
 */
function mt_ur_capture(callable $fn): array {
	ob_start();
	$result = $fn();

	return [$result, ob_get_clean()];
}

describe('command line', function () {
	it('is not run when the file is only loaded', function () {
		expect(mactrack_upart_invoked())->toBeFalse();
	});

	it('uses the saved setting when no option is given', function () {
		expect(mactrack_upart_parse(['upgrade_partitioning.php']))->toBe(['action' => 'run', 'scheme' => null, 'debug' => false, 'error' => '']);
	});

	it('maps the --partition values to schemes', function (string $value, string $scheme) {
		expect(mactrack_upart_parse(['x', '--partition=' . $value, '--debug']))->toBe(['action' => 'run', 'scheme' => $scheme, 'debug' => true, 'error' => '']);
	})->with([['daily', 'day'], ['weekly', 'week'], ['disabled', 'disabled']]);

	it('still accepts the short and upper-case help and version options', function (string $option, string $action) {
		expect(mactrack_upart_parse(['x', $option])['action'])->toBe($action);
	})->with([['--help', 'help'], ['-h', 'help'], ['-H', 'help'], ['--version', 'version'], ['-v', 'version'], ['-V', 'version']]);

	it('rejects an unknown scheme and options that no longer exist', function (string $option) {
		expect(mactrack_upart_parse(['x', $option])['error'])->toStartWith('ERROR:');
	})->with(['--partition=monthly', '--partition', '--keep-old', '-d', '--days=7', '--bogus']);

	it('lists only the long options in the help', function () {
		[, $out] = mt_ur_capture('display_help');

		expect($out)->toContain('--partition=daily|weekly|disabled', '--debug', '--version', '--help', 'Mactrack Upgrade Partitioning, Version');
		expect($out)->not->toContain('-h ', '-v ', '-d ', '--keep-old', '[-h]', '[-v]');
	});

	it('maps the setting to a scheme and leaves the sizes it cannot build', function (string $saved, ?string $scheme) {
		$GLOBALS['__test_config_options']['mt_partition_granularity'] = $saved;

		expect(mactrack_upart_setting_scheme())->toBe($scheme);
	})->with([['', 'week'], ['week', 'week'], ['day', 'day'], ['disabled', 'disabled'], ['scan', null], ['hour', null]]);

	it('formats durations and scheme names', function () {
		expect(mactrack_upart_duration(3725))->toBe('1:02:05');
		expect(mactrack_upart_duration(-5))->toBe('0:00:00');
		expect(mactrack_upart_scheme_name('day'))->toBe('daily');
		expect(mactrack_upart_scheme_name('disabled'))->toBe('disabled');
	});

	it('prints help and version without registering a run', function (string $option, string $text) {
		[$rc, $out] = mt_ur_capture(fn () => mactrack_upart_main(['x', $option]));

		expect($rc)->toBe(0);
		expect($out)->toContain($text);
		expect(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'register_process_start'))->toBe([]);
	})->with([['--help', 'usage: upgrade_partitioning.php'], ['--version', 'Mactrack Upgrade Partitioning, Version']]);

	it('exits 1 with the help for an invalid option', function () {
		[$rc, $out] = mt_ur_capture(fn () => mactrack_upart_main(['x', '--keep-old']));

		expect($rc)->toBe(1);
		expect($out)->toContain('ERROR: Invalid Parameter --keep-old', 'usage:');
	});

	it('changes nothing for a per-scan or hourly setting', function () {
		$GLOBALS['__test_config_options']['mt_partition_granularity'] = 'scan';

		[$rc, $out] = mt_ur_capture(fn () => mactrack_upart_main(['x']));

		expect($rc)->toBe(0);
		expect($out)->toContain('Nothing was changed');
		expect(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'register_process_start'))->toBe([]);
	});

	it('does not run while another run is registered', function () {
		mactrack_test_queue_return('register_process_start', false);

		[$rc, $out] = mt_ur_capture(fn () => mactrack_upart_main(['x', '--partition=weekly']));

		expect($rc)->toBe(0);
		expect($out)->toContain('already running');
		expect(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'unregister_process'))->toBe([]);
		expect(mt_ur_sql())->toBe([]);
	});

	it('registers the run for at least four hours and unregisters it afterwards', function () {
		$GLOBALS['__test_config_options']['mt_partition_granularity'] = 'disabled';
		mt_ur_db();
		mt_ur_exists([0, 0, 1, 0, 1]);

		[$rc, $out] = mt_ur_capture(fn () => mactrack_upart_main(['x']));

		$register = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'register_process_start'));

		expect($rc)->toBe(0);
		expect($register)->toHaveCount(1);
		expect($register[0]['sql'])->toBe('mtpartition:convert:0');
		expect($register[0]['params'][0])->toBeGreaterThanOrEqual(4 * 3600);
		expect(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'unregister_process'))->toHaveCount(1);
		expect($out)->toContain('No partitioning drift found');
	});
});

describe('plan', function () {
	it('plans the days from the retention window through two days ahead', function () {
		$plan = mactrack_upart_plan([MT_UR_NOW - 3 * 86400, MT_UR_NOW], 'day', 7, 1024, MT_UR_NOW);

		expect(array_keys($plan['bounds']))->toBe(['d20261109', 'd20261110', 'd20261111', 'd20261112', 'd20261113', 'd20261114']);
		expect($plan['bounds']['d20261109'])->toBe(1794268800);
		expect($plan['reason'])->toBe('');
	});

	it('plans weeks aligned to Monday', function () {
		expect(mactrack_upart_plan([MT_UR_NOW], 'week', 7, 1024, MT_UR_NOW)['bounds'])->toBe(['w20261109' => 1794787200]);
	});

	it('folds history older than the retention window into the first partition', function () {
		$plan = mactrack_upart_plan([MT_UR_NOW - 30 * 86400, MT_UR_NOW], 'week', 7, 1024, MT_UR_NOW);

		expect($plan['bounds'])->toBe(['w20261102' => 1794182400, 'w20261109' => 1794787200]);
	});

	it('plans from the oldest scan when nothing expires', function () {
		$plan = mactrack_upart_plan([MT_UR_NOW - 30 * 86400, MT_UR_NOW], 'week', 0, 1024, MT_UR_NOW);

		expect(array_keys($plan['bounds']))->toBe(['w20261012', 'w20261019', 'w20261026', 'w20261102', 'w20261109']);
	});

	it('ignores zero dates and plans an empty table', function () {
		expect(array_keys(mactrack_upart_plan([0], 'week', 7, 1024, MT_UR_NOW)['bounds']))->toBe(['w20261109']);
		expect(array_keys(mactrack_upart_plan([], 'day', 7, 1024, MT_UR_NOW)['bounds']))->toBe(['d20261112', 'd20261113', 'd20261114']);
	});

	it('plans through a scan date that lies ahead', function () {
		expect(array_keys(mactrack_upart_plan([MT_UR_NOW + 9 * 86400], 'week', 7, 1024, MT_UR_NOW)['bounds']))->toBe(['w20261109', 'w20261116']);
		expect(array_keys(mactrack_upart_plan([MT_UR_NOW + 12 * 86400], 'week', 7, 1024, MT_UR_NOW)['bounds']))->toBe(['w20261109', 'w20261116', 'w20261123']);
	});

	it('refuses a plan beyond the partition limit', function () {
		$plan = mactrack_upart_plan([MT_UR_NOW - 30 * 86400], 'day', 0, 5, MT_UR_NOW);

		expect($plan['bounds'])->toBe([]);
		expect($plan['reason'])->toContain('limit of 5');
	});
});

describe('assessment', function () {
	it('resumes a table whose rebuild table exists', function () {
		mt_ur_exists([1]);

		expect(mactrack_upart_assess('mac_track_ports', 'week')['action'])->toBe('resume');
	});

	it('reports a missing table', function () {
		mt_ur_exists([0, 0]);

		expect(mactrack_upart_assess('mac_track_ips', 'week'))->toBe(['action' => 'missing', 'reason' => 'the table does not exist']);
	});

	it('finds no drift in a table that follows the scheme', function () {
		mt_ur_db(['layout' => 'weekly']);
		mt_ur_exists([0, 1]);

		$a = mactrack_upart_assess('mac_track_ports', 'week');

		expect($a['action'])->toBe('ok');
		expect($a['reason'])->toBe('weekly with 3 partitions and about 1,200 rows; no drift');
	});

	it('finds drift', function (string $layout, string $scheme, string $reason, string $stranded) {
		mt_ur_db(['layout' => $layout, 'stranded' => $stranded]);
		mt_ur_exists([0, 1]);

		$a = mactrack_upart_assess('mac_track_ports', $scheme);

		expect($a['action'])->toBe('rebuild');
		expect($a['reason'])->toContain($reason);
	})->with([
		'unpartitioned'                   => ['none', 'week', 'not partitioned', '0'],
		'earlier layout'                  => ['legacy', 'day', 'earlier daily layout', '0'],
		'unknown'                         => ['invalid', 'week', 'MacTrack does not recognize', '0'],
		'mixed sizes'                     => ['mixed', 'week', 'not all weekly (name prefixes: d, w)', '0'],
		'other size'                      => ['weekly', 'day', 'not all daily (name prefixes: w)', '0'],
		'rows in dMaxValue'               => ['weekly', 'week', 'dMaxValue', '3'],
		'partitioned, selection disabled' => ['weekly', 'disabled', 'selection is disabled', '0'],
	]);

	it('finds no drift in an unpartitioned table when partitioning is disabled', function () {
		mt_ur_db(['layout' => 'none']);
		mt_ur_exists([0, 1]);

		expect(mactrack_upart_assess('mac_track_ports', 'disabled')['action'])->toBe('ok');
	});

	it('assesses all history tables and says what it found', function () {
		mt_ur_db(['layout' => 'none']);
		mt_ur_exists([0, 1, 0, 1, 0, 1]);

		[$assessed, $out] = mt_ur_capture(fn () => mactrack_upart_assess_all('week'));

		expect(array_keys($assessed))->toBe(['mac_track_ports', 'mac_track_ips', 'mac_track_dot1x']);
		expect($out)->toContain('WARNING: mac_track_ports: not partitioned', 'WARNING: mac_track_ips: not partitioned', 'WARNING: mac_track_dot1x: not partitioned');
	});
});

describe('rebuild', function () {
	it('renames, creates, copies each scan date and drops the original, in that order', function () {
		mt_ur_db();
		mt_ur_exists([0, 1, 0]);
		mactrack_test_queue_return('db_affected_rows', 4);
		mactrack_test_queue_return('db_affected_rows', 6);
		$seen = [];

		$r = mactrack_upart_rebuild('mac_track_ports', 'week', function ($table, $done, $count, $scan_date, $rows) use (&$seen) {
			$seen[] = [$table, $done, $count, $scan_date, $rows];
		}, 0, MT_UR_NOW);

		$sql = mt_ur_sql();

		expect($r)->toBe(['ok' => true, 'reason' => '', 'rows' => 2, 'partitions' => 2, 'granularity' => 'week']);
		expect($sql[0])->toBe('RENAME TABLE `mac_track_ports` TO `mac_track_ports_rebuild`');
		expect($sql[1])->toBe("SET SESSION time_zone = '+00:00'");
		expect($sql[2])->toBe('CREATE TABLE `mac_track_ports` LIKE `mac_track_ports_rebuild`');
		expect($sql[3])->toContain('ALTER TABLE `mac_track_ports` PARTITION BY RANGE (UNIX_TIMESTAMP(scan_date))', 'PARTITION `w20261109` VALUES LESS THAN (1794787200)', 'PARTITION `dMaxValue` VALUES LESS THAN MAXVALUE');
		expect($sql[4])->toBe('INSERT INTO `mac_track_ports` SELECT * FROM `mac_track_ports_rebuild` WHERE scan_date = ?');
		expect($sql[5])->toBe($sql[4]);
		expect($sql[6])->toBe('DROP TABLE `mac_track_ports_rebuild`');
		expect($sql[7])->toBe('SET SESSION time_zone = ?');
		expect($seen)->toBe([['mac_track_ports', 1, 2, '2026-11-12 06:15:07', 4], ['mac_track_ports', 2, 2, '2026-11-12 06:30:07', 10]]);
		expect(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'heartbeat_process'))->toHaveCount(2);
		expect($GLOBALS['__test_config_options']['mt_partition_pending'])->toBe('');
	});

	it('copies each scan date with its own value, oldest first', function () {
		mt_ur_db();
		mt_ur_exists([0, 1, 0]);

		mactrack_upart_rebuild('mac_track_ports', 'week', null, 0, MT_UR_NOW);

		$inserts = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => strpos($c['sql'], 'INSERT INTO') !== false));

		expect(array_column($inserts, 'params'))->toBe([['2026-11-12 06:15:07'], ['2026-11-12 06:30:07']]);
	});

	it('rebuilds the IP table the same way without the port pending state', function () {
		mt_ur_db();
		mt_ur_exists([0, 1, 0]);

		$r = mactrack_upart_rebuild('mac_track_ips', 'day', null, 0, MT_UR_NOW);

		expect($r['ok'])->toBeTrue();
		expect($r['partitions'])->toBe(4);
		expect(mt_ur_sql()[0])->toBe('RENAME TABLE `mac_track_ips` TO `mac_track_ips_rebuild`');
		expect(mt_ur_find('PARTITION BY RANGE')[0])->toContain('ALTER TABLE `mac_track_ips`', 'PARTITION `d20261112`');
		expect($GLOBALS['__test_config_options'])->not->toHaveKey('mt_partition_pending');
	});

	it('rebuilds 802.1x history without changing port exchange state', function () {
		mt_ur_db();
		mt_ur_exists([0, 1, 0]);

		$r = mactrack_upart_rebuild('mac_track_dot1x', 'day', null, 0, MT_UR_NOW);

		expect($r['ok'])->toBeTrue();
		expect(mt_ur_sql()[0])->toBe('RENAME TABLE `mac_track_dot1x` TO `mac_track_dot1x_rebuild`');
		expect(mt_ur_find('PARTITION BY RANGE')[0])->toContain('ALTER TABLE `mac_track_dot1x`', 'PARTITION `d20261112`');
		expect(mt_ur_find('INSERT INTO `mac_track_dot1x`'))->toHaveCount(2);
		expect($GLOBALS['__test_config_options'])->not->toHaveKey('mt_partition_pending');
	});

	it('removes the partitioning the original had before partitioning again', function () {
		mt_ur_db(['src_partitioned' => true]);
		mt_ur_exists([0, 1, 0]);

		mactrack_upart_rebuild('mac_track_ports', 'week', null, 0, MT_UR_NOW);

		expect(mt_ur_find('REMOVE PARTITIONING'))->toBe(['ALTER TABLE `mac_track_ports` REMOVE PARTITIONING']);
	});

	it('makes a DATETIME scan_date a TIMESTAMP and reads it in the session zone only for a TIMESTAMP', function () {
		mt_ur_db(['type' => 'datetime']);
		mt_ur_exists([0, 1, 0]);

		mactrack_upart_rebuild('mac_track_ports', 'week', null, 0, MT_UR_NOW);

		expect(mt_ur_find('MODIFY `scan_date` TIMESTAMP'))->toHaveCount(1);
		expect(mt_ur_find('SET SESSION time_zone'))->toBe([]);
	});

	it('leaves the table unpartitioned for the disabled scheme', function () {
		mt_ur_db(['src_partitioned' => true, 'type' => 'datetime']);
		mt_ur_exists([0, 1, 0]);

		$r = mactrack_upart_rebuild('mac_track_ports', 'disabled', null, 0, MT_UR_NOW);

		expect($r['partitions'])->toBe(0);
		expect(mt_ur_find('PARTITION BY'))->toBe([]);
		expect(mt_ur_find('MODIFY `scan_date`'))->toBe([]);
		expect(mt_ur_find('REMOVE PARTITIONING'))->toHaveCount(1);
	});

	it('rejects a table MacTrack does not partition', function () {
		expect(fn () => mactrack_upart_rebuild('mac_track_devices', 'week'))->toThrow(InvalidArgumentException::class);
	});

	it('does nothing to a table that does not exist', function () {
		mt_ur_exists([0, 0]);

		$r = mactrack_upart_rebuild('mac_track_ips', 'week', null, 0, MT_UR_NOW);

		expect($r['ok'])->toBeFalse();
		expect($r['reason'])->toBe('The table does not exist.');
		expect(mt_ur_sql())->toBe([]);
	});

	it('keeps everything when the table cannot be renamed', function () {
		mt_ur_db();
		mt_ur_exists([0, 1]);
		mactrack_test_queue_return_for('db_execute_prepared', 'RENAME TABLE', false);

		$r = mactrack_upart_rebuild('mac_track_ports', 'week', null, 0, MT_UR_NOW);

		expect($r['ok'])->toBeFalse();
		expect($r['reason'])->toContain('Could not rename');
		expect(mt_ur_find('CREATE TABLE'))->toBe([]);
		expect(mt_ur_find('DROP TABLE'))->toBe([]);
	});

	it('keeps the renamed table when the new one cannot be created', function (string $fragment) {
		mt_ur_db();
		mt_ur_exists([0, 1, 0]);
		mactrack_test_queue_return_for('db_execute_prepared', $fragment, false);

		$r = mactrack_upart_rebuild('mac_track_ports', 'week', null, 0, MT_UR_NOW);

		expect($r['ok'])->toBeFalse();
		expect($r['reason'])->toContain('Could not create the new table');
		expect(mt_ur_find('INSERT INTO'))->toBe([]);
		expect(mt_ur_find('DROP TABLE'))->toBe([]);
	})->with(['CREATE TABLE', 'PARTITION BY RANGE']);

	it('stops without dropping the original when a scan date cannot be copied', function () {
		mt_ur_db();
		mt_ur_exists([0, 1, 0]);
		mactrack_test_queue_return_for('db_execute_prepared', 'INSERT INTO', false);

		$r = mactrack_upart_rebuild('mac_track_ports', 'week', null, 0, MT_UR_NOW);

		expect($r['ok'])->toBeFalse();
		expect($r['reason'])->toBe('Could not copy the scan date 2026-11-12 06:15:07.');
		expect(mt_ur_find('DROP TABLE'))->toBe([]);
		expect(mt_ur_find('SET SESSION time_zone = ?'))->toHaveCount(1);
	});

	it('keeps the original when the row counts differ', function () {
		mt_ur_db(['old' => '100', 'new' => '99']);
		mt_ur_exists([0, 1, 0]);

		$r = mactrack_upart_rebuild('mac_track_ports', 'week', null, 0, MT_UR_NOW);

		expect($r['ok'])->toBeFalse();
		expect($r['reason'])->toContain('has 100 rows but 99 were copied', 'was kept');
		expect(mt_ur_find('DROP TABLE'))->toBe([]);
	});

	it('does not treat an unreadable row count as zero', function () {
		mt_ur_db(['old' => null]);
		mt_ur_exists([0, 1, 0]);

		$r = mactrack_upart_rebuild('mac_track_ports', 'week', null, 0, MT_UR_NOW);

		expect($r['reason'])->toBe('Could not verify the copied row counts.');
		expect(mt_ur_find('DROP TABLE'))->toBe([]);
	});

	it('reports the copy as unfinished when the original cannot be dropped', function () {
		mt_ur_db();
		mt_ur_exists([0, 1, 0]);
		mactrack_test_queue_return_for('db_execute_prepared', 'DROP TABLE `mac_track_ports_rebuild`', false);

		$r = mactrack_upart_rebuild('mac_track_ports', 'week', null, 0, MT_UR_NOW);

		expect($r['ok'])->toBeFalse();
		expect($r['rows'])->toBe(2);
		expect($r['reason'])->toContain('could not be dropped', 'run this again');
		expect($GLOBALS['__test_config_options'])->not->toHaveKey('mt_partition_pending');
	});

	it('does nothing when the scan dates cannot be read', function () {
		mt_ur_db(['scans' => false]);
		mt_ur_exists([0, 1]);

		$r = mactrack_upart_rebuild('mac_track_ports', 'week', null, 0, MT_UR_NOW);

		expect($r['reason'])->toBe('Could not read the scan dates.');
		expect(mt_ur_find('CREATE TABLE'))->toBe([]);
	});

	it('refuses a history that needs more partitions than the server allows', function () {
		$GLOBALS['__test_config_options']['mt_data_retention'] = '0';
		mt_ur_db(['scans' => [['scan_date' => '1970-01-11 00:00:00', 'epoch' => '864000'], MT_UR_SCANS[0]]]);
		mt_ur_exists([0, 1]);

		$r = mactrack_upart_rebuild('mac_track_ports', 'day', null, 0, MT_UR_NOW);

		expect($r['reason'])->toContain('partitions');
		expect(mt_ur_find('CREATE TABLE'))->toBe([]);
	});

	it('resumes a partly filled table that already has the scheme from its newest scan date', function () {
		mt_ur_db(['layout' => 'weekly', 'newest' => '2026-11-12 06:30:07', 'old' => '2', 'new' => '2']);
		mt_ur_exists([1, 1]);

		[$r, $out] = mt_ur_capture(fn () => mactrack_upart_rebuild('mac_track_ports', 'week', null, 0, MT_UR_NOW));

		$inserts = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => strpos($c['sql'], 'INSERT INTO') !== false));

		expect($r['ok'])->toBeTrue();
		expect($out)->toContain('resuming an earlier rebuild');
		expect(mt_ur_find('RENAME TABLE'))->toBe([]);
		expect(mt_ur_find('CREATE TABLE'))->toBe([]);
		expect(mt_ur_find('DELETE FROM `mac_track_ports` WHERE scan_date = ?'))->toHaveCount(1);
		expect(array_column($inserts, 'params'))->toBe([['2026-11-12 06:30:07']]);
		expect(mt_ur_find('DROP TABLE `mac_track_ports_rebuild`'))->toHaveCount(1);
	});

	it('resumes from the first scan date when the new table is empty', function () {
		mt_ur_db(['layout' => 'weekly']);
		mt_ur_exists([1, 1]);

		mt_ur_capture(fn () => mactrack_upart_rebuild('mac_track_ports', 'week', null, 0, MT_UR_NOW));

		$inserts = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => strpos($c['sql'], 'INSERT INTO') !== false));

		expect($inserts)->toHaveCount(2);
	});

	it('starts the copy again when the partly filled table has another scheme', function () {
		mt_ur_db(['layout' => 'daily', 'newest' => '2026-11-12 06:30:07']);
		mt_ur_exists([1, 1]);

		mt_ur_capture(fn () => mactrack_upart_rebuild('mac_track_ports', 'week', null, 0, MT_UR_NOW));

		$sql = mt_ur_sql();

		expect(mt_ur_find('DROP TABLE `mac_track_ports`'))->toHaveCount(1);
		expect(mt_ur_find('CREATE TABLE `mac_track_ports` LIKE'))->toHaveCount(1);
		expect(array_search('DROP TABLE `mac_track_ports`', $sql, true))->toBeLessThan(array_search('CREATE TABLE `mac_track_ports` LIKE `mac_track_ports_rebuild`', $sql, true));
		expect(mt_ur_find('INSERT INTO'))->toHaveCount(2);
	});

	it('creates the table again when an earlier run stopped right after the rename', function () {
		mt_ur_db();
		mt_ur_exists([1, 0]);

		mt_ur_capture(fn () => mactrack_upart_rebuild('mac_track_ports', 'week', null, 0, MT_UR_NOW));

		expect(mt_ur_find('RENAME TABLE'))->toBe([]);
		expect(mt_ur_find('CREATE TABLE `mac_track_ports` LIKE'))->toHaveCount(1);
		expect(mt_ur_find('INSERT INTO'))->toHaveCount(2);
	});

	it('stops when the partly filled table of an earlier run cannot be dropped', function () {
		mt_ur_db(['layout' => 'daily']);
		mt_ur_exists([1, 1]);
		mactrack_test_queue_return_for('db_execute_prepared', 'DROP TABLE `mac_track_ports`', false);

		[$r] = mt_ur_capture(fn () => mactrack_upart_rebuild('mac_track_ports', 'week', null, 0, MT_UR_NOW));

		expect($r['reason'])->toContain('Could not drop the partly filled table');
	});

	it('stops when the last partly copied scan date cannot be cleared', function () {
		mt_ur_db(['layout' => 'weekly', 'newest' => '2026-11-12 06:30:07']);
		mt_ur_exists([1, 1]);
		mactrack_test_queue_return_for('db_execute_prepared', 'DELETE FROM `mac_track_ports`', false);

		[$r] = mt_ur_capture(fn () => mactrack_upart_rebuild('mac_track_ports', 'week', null, 0, MT_UR_NOW));

		expect($r['reason'])->toBe('Could not clear the last partly copied scan date.');
		expect(mt_ur_find('INSERT INTO'))->toBe([]);
	});
});

describe('run', function () {
	/**
	 * Existence answers for a full run over three unpartitioned tables.
	 */
	function mt_ur_full_run_exists(): void {
		mt_ur_exists([0, 0, 1, 0, 1, 0, 1, 0, 0, 1, 0, 0, 1, 0, 0, 1, 0]);
	}

	it('refuses to run without partitioning support unless the tables are to be unpartitioned', function () {
		mt_ur_db(['plugin' => '']);

		[$rc, $out] = mt_ur_capture(fn () => mactrack_upart_run('week', false, 0));

		expect($rc)->toBe(1);
		expect($out)->toContain('does not support partitioning');
		expect(mt_ur_sql())->toBe([]);
	});

	it('refuses to run during a database upgrade', function () {
		mt_ur_db();
		mt_ur_exists([1]);

		[$rc, $out] = mt_ur_capture(fn () => mactrack_upart_run('week', false, 0));

		expect($rc)->toBe(1);
		expect($out)->toContain('database upgrade is running');
	});

	it('tells the user when there is no drift and changes nothing', function () {
		mt_ur_db();
		mt_ur_exists([0, 0, 1, 0, 1]);

		[$rc, $out] = mt_ur_capture(fn () => mactrack_upart_run('disabled', false, 0));

		expect($rc)->toBe(0);
		expect($out)->toContain('NOTE: mac_track_ports: not partitioned, as selected; no drift', 'No partitioning drift found; nothing was changed.');
		expect(mt_ur_sql())->toBe([]);
		expect($GLOBALS['__test_config_options']['mt_partition_granularity'])->toBe('week');
		expect(implode("\n", array_column($GLOBALS['__test_db_calls'], 'sql')))->not->toContain('GET_LOCK');
	});

	it('saves the scheme chosen with --partition even when nothing needs rebuilding', function () {
		mt_ur_db();
		mt_ur_exists([0, 0, 1, 0, 1]);

		[$rc, $out] = mt_ur_capture(fn () => mactrack_upart_run('disabled', true, 0));

		expect($rc)->toBe(0);
		expect($out)->toContain('Saving the Port and IP Address Partition Size setting as disabled');
		expect($GLOBALS['__test_config_options']['mt_partition_granularity'])->toBe('disabled');
	});

	it('does not announce a saved setting that does not change', function () {
		$GLOBALS['__test_config_options']['mt_partition_granularity'] = 'disabled';
		mt_ur_db();
		mt_ur_exists([0, 0, 1, 0, 1]);

		[, $out] = mt_ur_capture(fn () => mactrack_upart_run('disabled', true, 0));

		expect($out)->not->toContain('Saving the Port and IP Address Partition Size');
		expect($GLOBALS['__test_config_options']['mt_partition_granularity'])->toBe('disabled');
	});

	it('finds no drift in weekly tables that follow the scheme', function () {
		mt_ur_db(['layout' => 'weekly']);
		mt_ur_exists([0, 0, 1, 0, 1]);

		[$rc, $out] = mt_ur_capture(fn () => mactrack_upart_run('week', false, 0));

		expect($rc)->toBe(0);
		expect($out)->toContain('weekly with 3 partitions', 'No partitioning drift found');
	});

	it('waits for a scan instead of rebuilding under it', function () {
		mt_ur_db(['scan_lock' => '0']);
		mt_ur_full_run_exists();

		[$rc, $out] = mt_ur_capture(fn () => mactrack_upart_run('week', false, 0));

		expect($rc)->toBe(1);
		expect($out)->toContain('A scan is running');
		expect(mt_ur_sql())->toBe([]);
	});

	it('waits for partition maintenance and releases the scan lock', function () {
		mt_ur_db(['partition_lock' => '0']);
		mt_ur_full_run_exists();

		[$rc, $out] = mt_ur_capture(fn () => mactrack_upart_run('week', false, 0));

		expect($rc)->toBe(1);
		expect($out)->toContain('Partition maintenance is running');
		expect(mt_ur_find('RENAME TABLE'))->toBe([]);
		expect(implode("\n", array_column($GLOBALS['__test_db_calls'], 'sql')))->toContain('RELEASE_LOCK');
	});

	it('leaves a pending partition exchange for the poller to finish', function () {
		$GLOBALS['__test_config_options']['mt_partition_pending'] = 'p20261112061507';
		mt_ur_db();
		mt_ur_full_run_exists();

		[$rc, $out] = mt_ur_capture(fn () => mactrack_upart_run('week', false, 0));

		expect($rc)->toBe(1);
		expect($out)->toContain('partition exchange is pending');
		expect(mt_ur_find('RENAME TABLE'))->toBe([]);
		expect($GLOBALS['__test_config_options']['mt_partition_converting'] ?? '')->toBe('');
	});

	it('rebuilds all history tables in order with scanning paused', function () {
		mt_ur_db();
		mt_ur_full_run_exists();

		[$rc, $out] = mt_ur_capture(fn () => mactrack_upart_run('week', true, 0));

		$sql   = mt_ur_sql();
		$ports = array_search('RENAME TABLE `mac_track_ports` TO `mac_track_ports_rebuild`', $sql, true);
		$ips   = array_search('RENAME TABLE `mac_track_ips` TO `mac_track_ips_rebuild`', $sql, true);
		$dot1x = array_search('RENAME TABLE `mac_track_dot1x` TO `mac_track_dot1x_rebuild`', $sql, true);
		$drops = array_keys(array_filter($sql, fn ($s) => strpos($s, 'DROP TABLE') === 0));

		expect($rc)->toBe(0);
		expect($ports)->not->toBeFalse();
		expect($ips)->toBeGreaterThan($ports);
		expect($dot1x)->toBeGreaterThan($ips);
		expect($sql[$drops[0]])->toBe('DROP TABLE `mac_track_ports_rebuild`');
		expect($drops[0])->toBeLessThan($ips);
		expect($sql[$drops[1]])->toBe('DROP TABLE `mac_track_ips_rebuild`');
		expect($sql[$drops[2]])->toBe('DROP TABLE `mac_track_dot1x_rebuild`');
		expect($out)->toContain('Scanning is paused', 'maintenance mode', 'Rebuilding mac_track_ports (weekly)', 'Rebuilding mac_track_ips (weekly)', 'Rebuilding mac_track_dot1x (weekly)', 'Partitioning is complete');
		expect($GLOBALS['__test_config_options']['mt_partition_granularity'])->toBe('week');
		expect($GLOBALS['__test_config_options']['mt_partition_converting'])->toBe('');

		$status = json_decode($GLOBALS['__test_config_options']['mt_partition_status'], true)['conversion'];

		expect($status['ok'])->toBeTrue();
		expect($status['rows'])->toBe(6);
		expect($status['reason'])->toBe('');

		$locks = array_column($GLOBALS['__test_db_calls'], 'sql');

		expect(count(array_filter($locks, fn ($s) => strpos($s, 'GET_LOCK') !== false)))->toBe(2);
		expect(count(array_filter($locks, fn ($s) => strpos($s, 'RELEASE_LOCK') !== false)))->toBe(2);
	});

	it('shows progress without --debug only at the end of a short copy', function () {
		mt_ur_db();
		mt_ur_full_run_exists();

		[, $out] = mt_ur_capture(fn () => mactrack_upart_run('week', false, 0));
		expect($out)->toContain('mac_track_ports: copying 2 scan dates', 'mac_track_ports: copied 2 of 2 scan dates (100%)', 'verifying the row counts', 'dropping the original table mac_track_ports_rebuild', 'mac_track_ports rebuilt: 2 rows, ');
		expect($out)->not->toContain('copied 1 of 2');
		expect($out)->not->toContain('DEBUG:');
	});

	it('reports every scan date with --debug', function () {
		$GLOBALS['debug'] = true;
		mt_ur_db();
		mt_ur_full_run_exists();

		[, $out] = mt_ur_capture(fn () => mactrack_upart_run('week', false, 0));

		expect($out)->toContain('DEBUG: mac_track_ports: copied 2026-11-12 06:15:07 (1 of 2)', 'DEBUG: mac_track_ips: copied 2026-11-12 06:30:07 (2 of 2)');
	});

	it('stops at the first table that fails and leaves it to resume', function () {
		mt_ur_db();
		mt_ur_full_run_exists();
		mactrack_test_queue_return_for('db_execute_prepared', 'INSERT INTO `mac_track_ports`', false);

		[$rc, $out] = mt_ur_capture(fn () => mactrack_upart_run('week', false, 0));

		$status = json_decode($GLOBALS['__test_config_options']['mt_partition_status'], true)['conversion'];

		expect($rc)->toBe(1);
		expect($out)->toContain('FATAL: mac_track_ports: Could not copy the scan date', 'run this again to resume');
		expect(mt_ur_find('RENAME TABLE `mac_track_ips`'))->toBe([]);
		expect($status['ok'])->toBeFalse();
		expect($status['reason'])->toContain('mac_track_ports');
		expect($GLOBALS['__test_config_options']['mt_partition_converting'])->toBe('');
		expect(implode("\n", array_column($GLOBALS['__test_db_calls'], 'sql')))->toContain('RELEASE_LOCK');
	});

	it('records a drop that could not finish as a warning on a table that was copied', function () {
		mt_ur_db();
		mt_ur_full_run_exists();
		mactrack_test_queue_return_for('db_execute_prepared', 'DROP TABLE `mac_track_ports_rebuild`', false);

		[$rc, $out] = mt_ur_capture(fn () => mactrack_upart_run('week', false, 0));

		expect($rc)->toBe(1);
		expect($out)->toContain('could not be dropped');
	});

	it('resumes an unfinished rebuild without another rename', function () {
		mt_ur_db(['layout' => 'weekly']);
		mt_ur_exists([0, 1, 0, 1, 0, 1, 0, 1, 1, 0, 1, 0]);

		[$rc, $out] = mt_ur_capture(fn () => mactrack_upart_run('week', false, 0));

		expect($rc)->toBe(0);
		expect($out)->toContain('an earlier rebuild did not finish and will resume', 'resuming an earlier rebuild');
		expect(mt_ur_find('RENAME TABLE `mac_track_ports`'))->toBe([]);
	});
});

describe('rebuild state', function () {
	it('names the table a rebuild moves the original rows to', function () {
		expect(mactrack_partition_rebuild_table('mac_track_ports'))->toBe('mac_track_ports_rebuild');
		expect(mactrack_partition_rebuild_table('mac_track_ips'))->toBe('mac_track_ips_rebuild');
		expect(fn () => mactrack_partition_rebuild_table('mac_track_devices'))->toThrow(InvalidArgumentException::class);
	});

	it('lists the tables whose rebuild table exists', function () {
		mt_ur_exists([1, 0]);

		expect(mactrack_partition_rebuild_pending())->toBe(['mac_track_ports']);
	});

	it('detects an interrupted 802.1x rebuild', function () {
		mt_ur_exists([0, 0, 1]);

		expect(mactrack_partition_rebuild_pending())->toBe(['mac_track_dot1x']);
	});

	it('asks the database instead of Cacti\'s remembered answer about tables', function () {
		mt_ur_exists([0]);

		mactrack_partition_table_exists('mac_track_ports_rebuild');

		$call = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_fetch_cell_prepared'))[0];

		expect($call['params'])->toBe(['cacti', 'mac_track_ports_rebuild']);
		expect(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_table_exists'))->toBe([]);
	});

	it('is running while the registered process is alive', function () {
		$GLOBALS['__test_config_options']['mt_partition_converting'] = '1794464107';
		mactrack_test_queue_return_for('db_fetch_row_prepared', 'FROM processes', ['pid' => (string) getmypid(), 'started' => '99000', 'now' => '100000', 'timeout' => '86400']);

		expect(mactrack_partition_rebuild_state())->toBe('running');
		expect($GLOBALS['__test_config_options']['mt_partition_converting'])->toBe('1794464107');

		$call = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_fetch_row_prepared'))[0];

		expect($call['params'])->toBe(['mtpartition', 'convert']);
	});

	it('is interrupted when the process is gone and a rebuild table is left', function () {
		mt_ur_exists([0, 1]);

		expect(mactrack_partition_rebuild_state())->toBe('interrupted');
	});

	it('clears a converting flag that a dead run left behind', function () {
		$GLOBALS['__test_config_options']['mt_partition_converting'] = '1794464107';
		mt_ur_exists([0, 1]);

		expect(mactrack_partition_rebuild_state())->toBe('interrupted');
		expect($GLOBALS['__test_config_options']['mt_partition_converting'])->toBe('');
	});

	it('is idle when nothing is running and no rebuild table exists', function () {
		mt_ur_exists([0, 0]);

		expect(mactrack_partition_rebuild_state())->toBe('');
	});

	it('shows the maintenance message for IP and MAC address searching only during a rebuild', function () {
		mt_ur_exists([0, 0]);
		[, $none] = mt_ur_capture('mactrack_partition_rebuild_notice');

		expect($none)->toBe('');

		mt_ur_exists([0, 1]);
		[, $interrupted] = mt_ur_capture('mactrack_partition_rebuild_notice');

		expect($interrupted)->toContain('maintenance mode for IP address, MAC address and 802.1x searching', 'did not finish', 'cli/upgrade_partitioning.php');

		mactrack_test_queue_return_for('db_fetch_row_prepared', 'FROM processes', ['pid' => (string) getmypid(), 'started' => '99000', 'now' => '100000', 'timeout' => '86400']);
		[, $running] = mt_ur_capture('mactrack_partition_rebuild_notice');

		expect($running)->toContain('maintenance mode for IP address, MAC address and 802.1x searching', 'until the partitioning has finished');
	});

	it('shows the notice on the IP and MAC address views', function (string $file, string $function) {
		$src = file_get_contents(dirname(__DIR__, 2) . '/' . $file);
		$pos = strpos($src, "function $function(");

		expect($pos)->not->toBeFalse();
		expect(substr($src, $pos, 600))->toContain("mactrack_tabs();\n\tmactrack_partition_rebuild_notice();");
	})->with([
		['mactrack_view_arp.php', 'mactrack_view_ips'],
		['mactrack_view_macs.php', 'mactrack_view_macs'],
		['mactrack_view_macs.php', 'mactrack_view_aggregated_macs'],
		['mactrack_view_dot1x.php', 'mactrack_view_dot1x'],
	]);

	it('reads the retention of each table in days', function (string $setting, string $table, string $value, int $days) {
		$GLOBALS['__test_config_options'][$setting] = $value;

		expect(mactrack_partition_retention_days($table))->toBe($days);
	})->with([
		['mt_data_retention', 'mac_track_ports', '14', 14],
		['mt_data_retention', 'mac_track_ports', '0', 0],
		['mt_data_retention', 'mac_track_ports', '-3', 0],
		['mt_data_retention', 'mac_track_ports', '2weeks', 14],
		['mt_data_retention', 'mac_track_ports', 'forever', 2],
		['mt_data_retention_ip', 'mac_track_ips', '30', 30],
		['mt_data_retention', 'mac_track_dot1x', '14', 14],
		['mt_data_retention', 'mac_track_dot1x', '0', 0],
	]);

	it('pauses maintenance and the poller while a rebuild is unfinished', function () {
		mt_ur_exists([1]);

		perform_mactrack_db_maint();

		expect(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_execute_prepared'))->toBe([]);
		expect($GLOBALS['__test_cacti_log'][0])->toContain('skipped while the port and IP tables are repartitioned');

		$src   = file_get_contents(dirname(__DIR__, 2) . '/poller_mactrack.php');
		$defer = strpos($src, "mactrack_poller_deferred('partition_rebuild');");

		expect($defer)->not->toBeFalse();
		expect($defer)->toBeLessThan(strpos($src, 'if (!mactrack_partition_scan_begin()) {'));
	});
});

describe('statements for both tables', function () {
	it('builds partition statements for the IP table and for no other', function () {
		expect(mactrack_partition_split_sql('d20261112', 1794528000, 'mac_track_ips'))->toStartWith('ALTER TABLE `mac_track_ips` REORGANIZE PARTITION `dMaxValue`');
		expect(mactrack_partition_drop_sql(['d20261101'], 'mac_track_ips'))->toBe('ALTER TABLE `mac_track_ips` DROP PARTITION `d20261101`');
		expect(mactrack_partition_split_sql('d20261112', 1794528000))->toStartWith('ALTER TABLE `mac_track_ports` REORGANIZE');
		expect(fn () => mactrack_partition_split_sql('d20261112', 1794528000, 'mac_track_devices'))->toThrow(InvalidArgumentException::class);
		expect(fn () => mactrack_partition_drop_sql(['d20261101'], 'mac_track_ports`; DROP TABLE x; --'))->toThrow(InvalidArgumentException::class);
	});

	it('reads the layout of the table it is asked about', function () {
		mt_ur_db(['layout' => 'weekly']);

		expect(mactrack_partition_state('mac_track_ips')['layout']['state'])->toBe('range');

		$call = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_fetch_assoc_prepared'))[0];

		expect($call['params'])->toBe(['cacti', 'mac_track_ips']);
		expect(fn () => mactrack_partition_state('users'))->toThrow(InvalidArgumentException::class);
	});
});

describe('scan lock', function () {
	it('lets a scan start when no rebuild holds the lock', function () {
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'GET_LOCK(?, 0)', '1');

		expect(mactrack_partition_scan_begin())->toBeTrue();
		expect($GLOBALS['__test_cacti_log'])->toBe([]);
	});

	it('stops a scan while the lock is held', function () {
		$GLOBALS['__test_config_options']['mt_partition_converting'] = '1794464107';
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'GET_LOCK(?, 0)', '0');

		expect(mactrack_partition_scan_begin())->toBeFalse();
		expect($GLOBALS['__test_config_options']['mt_partition_converting'])->toBe('1794464107');
	});

	it('clears the flag of a rebuild that died', function () {
		$GLOBALS['__test_config_options']['mt_partition_converting'] = '1794464107';
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'GET_LOCK(?, 0)', '1');

		expect(mactrack_partition_scan_begin())->toBeTrue();
		expect($GLOBALS['__test_config_options']['mt_partition_converting'])->toBe('');
		expect($GLOBALS['__test_cacti_log'][0])->toContain('partitioning rebuild stopped without finishing', 'cli/upgrade_partitioning.php');
	});

	it('is released by a web action that took it', function () {
		mactrack_partition_scan_end();

		expect(array_column($GLOBALS['__test_db_calls'], 'sql'))->toContain('SELECT RELEASE_LOCK(?)');
	});

	it('is taken by the poller before any scan or maintenance', function () {
		$src  = file_get_contents(dirname(__DIR__, 2) . '/poller_mactrack.php');
		$lock = strpos($src, 'if (!mactrack_partition_scan_begin()) {');

		expect($lock)->not->toBeFalse();
		expect($lock)->toBeLessThan(strpos($src, "if (\$site_id > 0) {\n\t\tmactrack_debug('About to enter Mactrack Site Scan Processing');"));
	});

	it('pauses database maintenance during a rebuild', function () {
		$GLOBALS['__test_config_options']['mt_partition_converting'] = '1794464107';
		mt_ur_exists([0, 0]);

		perform_mactrack_db_maint();

		expect(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_execute_prepared'))->toBe([]);
		expect($GLOBALS['__test_cacti_log'][0])->toContain('skipped while the port and IP tables are repartitioned');
		expect($GLOBALS['__test_config_options']['mt_db_maint_missed'])->toBe('on');
	});

	it('runs the maintenance that came due during a rebuild once the poller is free', function () {
		$src = file_get_contents(dirname(__DIR__, 2) . '/poller_mactrack.php');

		expect($src)->toContain("\$maint_missed = (string) read_config_option('mt_db_maint_missed', true) === 'on';");
		expect($src)->toContain('if ($time_till_next_db_maint < 0 || $maint_missed) {');
		expect($src)->toContain("mactrack_poller_deferred('partition_rebuild');\n\n\t// A maintenance window that passes meanwhile is run after the rebuild.\n\tmactrack_partition_rebuild_note_maintenance();");
	});

	it('notes a maintenance window that passes during a rebuild', function (string $last, bool $missed) {
		$GLOBALS['__test_config_options']['mt_maint_time']          = '12:00am';
		$GLOBALS['__test_config_options']['mt_last_db_maint_time'] = $last;

		mactrack_partition_rebuild_note_maintenance(MT_UR_NOW);

		expect(($GLOBALS['__test_config_options']['mt_db_maint_missed'] ?? '') === 'on')->toBe($missed);
	})->with([
		'last run before the window' => [(string) (MT_UR_NOW - 2 * 86400), true],
		'never run'                  => ['', true],
		'already run after it'       => [(string) MT_UR_NOW, false],
	]);

	it('falls back to the default maintenance time when the setting is unreadable', function () {
		$GLOBALS['__test_config_options']['mt_maint_time']          = 'whenever';
		$GLOBALS['__test_config_options']['mt_last_db_maint_time'] = (string) (MT_UR_NOW - 2 * 86400);

		mactrack_partition_rebuild_note_maintenance(MT_UR_NOW);

		expect($GLOBALS['__test_config_options']['mt_db_maint_missed'])->toBe('on');
	});

	it('leaves a note that was already made', function () {
		$GLOBALS['__test_config_options']['mt_maint_time']         = '12:00am';
		$GLOBALS['__test_config_options']['mt_last_db_maint_time'] = '';
		$GLOBALS['__test_config_options']['mt_db_maint_missed']    = 'on';

		mactrack_partition_rebuild_note_maintenance(MT_UR_NOW);

		expect($GLOBALS['__test_config_options']['mt_db_maint_missed'])->toBe('on');
	});
});

describe('fresh install', function () {
	it('partitions a new table with this week and next week', function (string $table) {
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'VERSION()', '8.4.3');
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'lock_wait_timeout', '50');

		expect(mactrack_partition_fresh_install(MT_UR_NOW, $table))->toBeTrue();
		expect(mt_ur_sql()[0])->toContain('ALTER TABLE `' . $table . '` PARTITION BY RANGE (UNIX_TIMESTAMP(scan_date))', 'PARTITION `w20261109` VALUES LESS THAN (1794787200)', 'PARTITION `w20261116` VALUES LESS THAN (1795392000)');
		expect($GLOBALS['__test_config_options']['mt_partition_granularity'])->toBe('week');
	})->with(['mac_track_ports', 'mac_track_ips', 'mac_track_dot1x']);

	it('only partitions the tables MacTrack partitions', function () {
		expect(fn () => mactrack_partition_fresh_install(MT_UR_NOW, 'mac_track_devices'))->toThrow(InvalidArgumentException::class);
	});

	it('follows the saved Daily size instead of overwriting it', function () {
		$GLOBALS['__test_config_options']['mt_partition_granularity'] = 'day';
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'VERSION()', '8.4.3');
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'lock_wait_timeout', '50');

		expect(mactrack_partition_fresh_install(MT_UR_NOW, 'mac_track_ips'))->toBeTrue();
		expect(mt_ur_sql()[0])->toContain('ALTER TABLE `mac_track_ips` PARTITION BY RANGE', 'PARTITION `d20261112`', 'PARTITION `d20261113`', 'PARTITION `d20261114`');
		expect($GLOBALS['__test_config_options']['mt_partition_granularity'])->toBe('day');
	});

	it('leaves a table unpartitioned when partitioning is disabled', function () {
		$GLOBALS['__test_config_options']['mt_partition_granularity'] = 'disabled';
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'VERSION()', '8.4.3');

		expect(mactrack_partition_fresh_install(MT_UR_NOW, 'mac_track_ips'))->toBeFalse();
		expect(mt_ur_sql())->toBe([]);
		expect($GLOBALS['__test_config_options']['mt_partition_granularity'])->toBe('disabled');
	});

	it('records weekly only when no size was saved', function () {
		unset($GLOBALS['__test_config_options']['mt_partition_granularity']);
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'VERSION()', '8.4.3');
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'lock_wait_timeout', '50');

		expect(mactrack_partition_fresh_install(MT_UR_NOW))->toBeTrue();
		expect($GLOBALS['__test_config_options']['mt_partition_granularity'])->toBe('week');
	});

	it('leaves the table unpartitioned without partitioning or a readable version', function (string $version, string $plugin) {
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'VERSION()', $version);
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'PLUGIN_STATUS', $plugin);

		expect(mactrack_partition_fresh_install())->toBeFalse();
		expect(mt_ur_sql())->toBe([]);
	})->with([
		'MariaDB without the plugin' => ['10.5.29-MariaDB', ''],
		'unreadable version'         => ['', 'ACTIVE'],
	]);

	it('leaves a failed fresh install unpartitioned without saving a scheme', function () {
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'VERSION()', '8.4.3');
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'lock_wait_timeout', '50');
		mactrack_test_queue_return_for('db_execute_prepared', 'PARTITION BY RANGE', false);

		expect(mactrack_partition_fresh_install(MT_UR_NOW))->toBeFalse();
		expect($GLOBALS['__test_config_options']['mt_partition_granularity'])->toBe('week');
	});

	it('partitions the port and IP tables when setup creates them', function (string $created) {
		mactrack_test_load(dirname(__DIR__, 2) . '/includes/database.php');
		mactrack_test_queue_return_for('db_table_exists', $created, false);
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'VERSION()', '10.5.29-MariaDB');
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'PLUGIN_STATUS', 'ACTIVE');

		mactrack_setup_database();

		$alter = implode(' ', mt_ur_find('PARTITION BY RANGE'));

		expect($alter)->toContain('ALTER TABLE `' . $created . '` PARTITION BY RANGE (UNIX_TIMESTAMP(scan_date)) (PARTITION `w');

		foreach (array_diff(['mac_track_ports', 'mac_track_ips', 'mac_track_dot1x'], [$created]) as $existing) {
			expect($alter)->not->toContain('`' . $existing . '`');
		}
	})->with(['mac_track_ports', 'mac_track_ips', 'mac_track_dot1x']);

	it('partitions only the tables setup has just created', function () {
		$src = file_get_contents(dirname(__DIR__, 2) . '/includes/database.php');

		expect($src)->toContain("\$ports_existed     = db_table_exists('mac_track_ports');\n\tapi_plugin_db_table_create('mactrack', 'mac_track_ports', \$data);\n\n\tif (!\$ports_existed) {");
		expect($src)->toContain("\$ips_existed       = db_table_exists('mac_track_ips');\n\tapi_plugin_db_table_create('mactrack', 'mac_track_ips', \$data);\n\n\tif (!\$ips_existed) {");
	});
});

describe('history changes during a rebuild', function () {
	it('runs a change under the partition lock and lets go of it', function () {
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'GET_LOCK(?, 2)', '1');
		mt_ur_exists([0, 0]);
		$ran = false;

		expect(mactrack_partition_history_run(function () use (&$ran) {
			$ran = true;
		}))->toBeTrue();
		expect($ran)->toBeTrue();
		expect(count(array_filter(array_column($GLOBALS['__test_db_calls'], 'sql'), fn ($s) => strpos($s, 'RELEASE_LOCK') !== false)))->toBe(1);
		expect(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'raise_message'))->toBe([]);
	});

	it('refuses while a rebuild holds the lock', function () {
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'GET_LOCK(?, 2)', '0');
		$ran = false;

		expect(mactrack_partition_history_run(function () use (&$ran) {
			$ran = true;
		}))->toBeFalse();
		expect($ran)->toBeFalse();

		$message = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'raise_message'));

		expect($message)->toHaveCount(1);
		expect($message[0]['params'][0])->toContain('repartitioned', 'cli/upgrade_partitioning.php');
	});

	it('refuses and lets go of the lock while a rebuild is unfinished', function () {
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'GET_LOCK(?, 2)', '1');
		mt_ur_exists([0, 1]);

		expect(mactrack_partition_history_begin())->toBeFalse();
		expect(count(array_filter(array_column($GLOBALS['__test_db_calls'], 'sql'), fn ($s) => strpos($s, 'RELEASE_LOCK') !== false)))->toBe(1);
	});

	it('lets go of the lock when the change throws', function () {
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'GET_LOCK(?, 2)', '1');
		mt_ur_exists([0, 0]);

		expect(fn () => mactrack_partition_history_run(function () {
			throw new RuntimeException('boom');
		}))->toThrow(RuntimeException::class);
		expect(count(array_filter(array_column($GLOBALS['__test_db_calls'], 'sql'), fn ($s) => strpos($s, 'RELEASE_LOCK') !== false)))->toBe(1);
	});

	it('does not wait for a rebuild that holds the lock for hours', function () {
		mactrack_partition_history_begin();

		$locks = array_values(array_filter(array_column($GLOBALS['__test_db_calls'], 'sql'), fn ($s) => strpos($s, 'GET_LOCK') !== false));

		expect($locks)->toBe(['SELECT GET_LOCK(?, 2)']);
	});

	it('refuses to remove devices and sites, authorize and revoke MACs, or save MAC authorizations while a rebuild is unfinished', function (string $file, string $function, array $args) {
		mactrack_test_load_function($file, $function);
		$_SESSION['sess_user_id'] = 1;
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'GET_LOCK(?, 2)', '0');

		$function(...$args);

		expect(array_filter($GLOBALS['__test_db_calls'], fn ($c) => in_array($c['fn'], ['db_execute_prepared', 'sql_save'], true) && strpos($c['sql'], 'GET_LOCK') === false))->toBe([]);
		expect(array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'raise_message')))->toHaveCount(1);
	})->with([
		'device removal'    => ['mactrack_actions.php', 'api_mactrack_device_remove', [7]],
		'site removal'      => ['mactrack_actions.php', 'api_mactrack_site_remove', [3]],
		'MAC removal'       => ['mactrack_macauth.php', 'api_mactrack_maca_remove', [5]],
		'MAC authorization' => ['mactrack_view_macs.php', 'api_mactrack_authorize_mac_addresses', ['001122334455', '10.0.0.1']],
		'MAC revoke'        => ['mactrack_view_macs.php', 'api_mactrack_revoke_mac_addresses', ['001122334455']],
	]);

	it('changes the history when no rebuild is running or unfinished', function (string $file, string $function, array $args, string $statement) {
		mactrack_test_load_function($file, $function);
		$_SESSION['sess_user_id'] = 1;
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'GET_LOCK(?, 2)', '1');
		mt_ur_exists([0, 0]);

		$function(...$args);

		expect(array_filter(array_column($GLOBALS['__test_db_calls'], 'sql'), fn ($s) => strpos(preg_replace('/\s+/', ' ', (string) $s), $statement) !== false))->not->toBe([]);
		expect(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'raise_message'))->toBe([]);
	})->with([
		'device removal'    => ['mactrack_actions.php', 'api_mactrack_device_remove', [7], 'DELETE FROM mac_track_ports WHERE device_id = ?'],
		'site removal'      => ['mactrack_actions.php', 'api_mactrack_site_remove', [3], 'DELETE FROM mac_track_ips WHERE site_id = ?'],
		'MAC removal'       => ['mactrack_macauth.php', 'api_mactrack_maca_remove', [5], 'DELETE FROM mac_track_ports WHERE mac_address = ?'],
		'MAC authorization' => ['mactrack_view_macs.php', 'api_mactrack_authorize_mac_addresses', ['001122334455', '10.0.0.1'], 'UPDATE mac_track_ports SET authorized=1'],
		'MAC revoke'        => ['mactrack_view_macs.php', 'api_mactrack_revoke_mac_addresses', ['001122334455'], 'UPDATE mac_track_ports SET authorized=0'],
	]);

	it('guards saving a MAC authorization, the Utilities that change the history and the database upgrade', function () {
		$root = dirname(__DIR__, 2);
		$auth = file_get_contents($root . '/mactrack_macauth.php');
		$util = file_get_contents($root . '/mactrack_utilities.php');
		$upgr = file_get_contents($root . '/cli/upgrade_database.php');

		expect($auth)->toContain('mactrack_partition_history_run(function () use (&$mac_id, $save, $mac_address): void {');
		expect($util)->toContain("if (!mactrack_partition_history_begin()) {");

		foreach (['mactrack_utilities_truncate_ports_table', 'mactrack_utilities_rebuild_scan_dates', 'mactrack_utilities_purge_aggregated_data', 'mactrack_utilities_recreate_aggregated_data'] as $action) {
			expect(substr($util, (int) strpos($util, 'mactrack_partition_history_begin') - 400, 400))->toContain("'" . $action . "'");
		}

		expect($util)->toContain("if (\$history_locked) {\n\tmactrack_partition_history_end();");
		expect($upgr)->toContain('mactrack_partition_rebuild_pending() !== []');
		expect(strpos($upgr, 'mactrack_partition_rebuild_pending'))->toBeGreaterThan(strpos($upgr, "mactrack_lock_name('scan')"));
		expect(strpos($upgr, 'mactrack_partition_rebuild_pending'))->toBeLessThan(strpos($upgr, 'mactrack_perform_upgrade()'));
	});

	it('rechecks for a database upgrade once the locks are held', function () {
		mt_ur_exists([1]);

		$out = mt_ur_capture(fn () => mactrack_upart_rebuild_all(['mac_track_ports'], 'week', 0));

		expect($out[0])->toBe(1);
		expect($out[1])->toContain('database upgrade is running');
		expect(mt_ur_sql())->toBe([]);
	});
});

describe('retirement of mactrack_convert.php', function () {
	it('ships the CLI in cli/ and tombstones the old script', function () {
		$root     = dirname(__DIR__, 2);
		$manifest = json_decode(file_get_contents($root . '/manifest.json'), true);

		expect($manifest['tombstones'])->toContain('mactrack_convert.php');
		expect($manifest['expected'])->not->toContain('mactrack_convert.php');
		expect($manifest['expected'])->toContain('cli/');
		expect(file_exists($root . '/mactrack_convert.php'))->toBeFalse();
		expect(file_exists($root . '/cli/upgrade_partitioning.php'))->toBeTrue();
	});

	it('leaves no reference to the old script outside the history', function () {
		$root = dirname(__DIR__, 2);

		foreach (['README.md', 'mactrack_utilities.php', 'lib/mactrack_functions.php', 'lib/mactrack_partitions.php', 'cli/upgrade_database.php'] as $file) {
			expect(file_get_contents($root . '/' . $file))->not->toContain('mactrack_convert.php');
		}
	});
});
