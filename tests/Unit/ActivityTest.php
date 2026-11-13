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
 * The Process Status page's "Current Activity" figures: the recorded step,
 * the priority between the possible states, the table sizes and the
 * database disk space. Row figures are estimates from information_schema;
 * nothing here counts a large table.
 */

beforeAll(function () {
	mactrack_test_load(dirname(__DIR__, 2) . '/lib/mactrack_functions.php');
	mactrack_test_load(dirname(__DIR__, 2) . '/includes/database.php');
});

beforeEach(function () {
	$GLOBALS['__test_db_calls']        = [];
	$GLOBALS['__test_next_returns']    = [];
	$GLOBALS['__test_matched_returns'] = [];
	$GLOBALS['__test_config_options']  = ['mt_collection_timing' => '15', 'mt_data_retention' => '7'];
	$GLOBALS['__test_cacti_log']       = [];
	$GLOBALS['database_default']       = 'cacti';
	$GLOBALS['database_hostname']      = 'localhost';

	// No upgrade work table and no registered upgrade unless a test says so.
	mt_act_table('mac_track_aggregated_ports_upgrade', false);
	mt_act_table('processes', false);
});

const MT_ACT_NOW = 1794464107;

/**
 * Sets whether db_table_exists() finds a table, replacing an earlier answer.
 *
 * @param  string $table  Table name.
 * @param  bool   $exists Whether it exists.
 * @return void
 */
function mt_act_table(string $table, bool $exists): void {
	$GLOBALS['__test_matched_returns']['db_table_exists'] = array_values(array_filter(
		$GLOBALS['__test_matched_returns']['db_table_exists'] ?? [],
		fn ($entry) => $entry[0] !== $table
	));

	mactrack_test_queue_return_for('db_table_exists', $table, $exists);
}

/**
 * Stores a recorded activity as the maintenance or the poller would.
 *
 * @param  string $step   Step key.
 * @param  int    $pid    Process id that recorded it.
 * @param  array  $detail Step facts.
 * @return void
 */
function mt_act_record(string $step, int $pid, array $detail = []): void {
	set_config_option('mt_activity', json_encode(['step' => $step, 'detail' => $detail, 'started' => MT_ACT_NOW - 90, 'pid' => $pid]));
}

describe('recording', function () {
	it('stores the step with its start time and process id and clears it', function () {
		mactrack_activity_begin('scanning', ['devices' => 12]);

		$stored = json_decode($GLOBALS['__test_config_options']['mt_activity'], true);
		expect($stored['step'])->toBe('scanning');
		expect($stored['detail'])->toBe(['devices' => 12]);
		expect($stored['pid'])->toBe(getmypid());
		expect($stored['started'])->toBeGreaterThan(time() - 5);

		mactrack_activity_end();
		expect($GLOBALS['__test_config_options']['mt_activity'])->toBe('');
	});

	it('translates known steps and shows an unknown key as it is', function () {
		foreach (['scanning', 'dns_resolver', 'transferring', 'partition_maintenance', 'delete_ports', 'prune_scan_dates', 'delete_ips', 'optimize_ips'] as $step) {
			expect(mactrack_activity_step_label($step))->not->toBe($step);
		}

		expect(mactrack_activity_step_label('something_new'))->toBe('something_new');
	});

	it('times each step and closes the one before it', function () {
		$steps = [];

		mactrack_maint_step_begin($steps, 'delete_ips');
		expect(json_decode($GLOBALS['__test_config_options']['mt_activity'], true)['step'])->toBe('delete_ips');

		mactrack_maint_step_begin($steps, 'optimize_ips');
		expect($steps[0])->toHaveKeys(['step', 'seconds'])->not->toHaveKey('started');
		expect($steps[1])->toHaveKey('started');

		mactrack_maint_step_end($steps);
		expect($steps[1])->not->toHaveKey('started');
		expect($GLOBALS['__test_config_options']['mt_activity'])->toBe('');

		// Nothing open: no change and no error.
		mactrack_maint_step_end($steps);
		expect($steps)->toHaveCount(2);
	});

	it('does nothing when there are no steps to close', function () {
		$steps = [];
		mactrack_maint_step_end($steps);

		expect($steps)->toBe([]);
		expect($GLOBALS['__test_config_options'])->not->toHaveKey('mt_activity');
	});

	it('saves only finished steps and keeps the last list when nothing ran', function () {
		mactrack_maint_steps_save([['step' => 'delete_ips', 'seconds' => 1.5, 'started' => 1.0], ['step' => 'optimize_ips', 'seconds' => 2.5]]);
		expect(mactrack_maint_steps_last())->toBe([['step' => 'optimize_ips', 'seconds' => 2.5]]);

		mactrack_maint_steps_save([]);
		expect(mactrack_maint_steps_last())->toBe([['step' => 'optimize_ips', 'seconds' => 2.5]]);
	});

	it('reads no steps when none or garbage are stored', function () {
		expect(mactrack_maint_steps_last())->toBe([]);

		set_config_option('mt_maint_steps_last', 'not json');
		expect(mactrack_maint_steps_last())->toBe([]);
	});
});

describe('database maintenance', function () {
	it('records the step timings of a run and leaves no activity behind', function () {
		set_config_option('mt_data_retention_ip', '30');

		perform_mactrack_db_maint();

		$steps = array_column(mactrack_maint_steps_last(), 'step');
		expect(array_slice($steps, -2))->toBe(['delete_ips', 'optimize_ips']);
		expect($GLOBALS['__test_config_options']['mt_activity'])->toBe('');
	});

	it('times the port deletion and the scan-date pruning', function () {
		perform_mactrack_db_maint();

		expect(array_column(mactrack_maint_steps_last(), 'step'))->toContain('delete_ports', 'prune_scan_dates');
	});

	it('clears the activity and keeps the timings when a step throws', function () {
		// A table the stubs report as partitioned in the old layout, with no
		// answer for the day lookups: the missing key is raised as an error
		// while the partition step is open.
		mactrack_test_queue_return_for('db_fetch_assoc_prepared', 'information_schema.PARTITIONS', [
			['PARTITION_NAME'       => 'd20261111', 'PARTITION_ORDINAL_POSITION' => '1', 'PARTITION_METHOD' => 'RANGE',
				'PARTITION_EXPRESSION' => 'to_days(`scan_date`)', 'PARTITION_DESCRIPTION' => '740297', 'TABLE_ROWS' => '0'],
			['PARTITION_NAME'       => 'dMaxValue', 'PARTITION_ORDINAL_POSITION' => '2', 'PARTITION_METHOD' => 'RANGE',
				'PARTITION_EXPRESSION' => 'to_days(`scan_date`)', 'PARTITION_DESCRIPTION' => 'MAXVALUE', 'TABLE_ROWS' => '0'],
		]);

		set_error_handler(function (int $level, string $message): bool {
			throw new ErrorException($message, 0, $level);
		});

		try {
			expect(fn () => perform_mactrack_db_maint())->toThrow(ErrorException::class);
		} finally {
			restore_error_handler();
		}

		expect($GLOBALS['__test_config_options']['mt_activity'])->toBe('');
		expect(array_column(mactrack_maint_steps_last(), 'step'))->toBe(['partition_maintenance']);
	});

	it('turns each named retention into a number of days', function (string $retention, int $days) {
		set_config_option('mt_data_retention', $retention);

		perform_mactrack_db_maint();

		expect((int) $GLOBALS['__test_config_options']['mt_data_retention'])->toBe($days);
	})->with([
		['2days', 2],
		['5days', 5],
		['1week', 7],
		['2weeks', 14],
		['3weeks', 21],
		['1month', (int) ceil((time() - strtotime('-1 Month')) / 86400)],
		['2months', (int) ceil((time() - strtotime('-2 Months')) / 86400)],
		['unrecognized', 2],
	]);

	it('records nothing while a conversion pauses maintenance', function () {
		set_config_option('mt_partition_converting', '1794464107');

		perform_mactrack_db_maint();

		expect($GLOBALS['__test_config_options'])->not->toHaveKey('mt_activity');
		expect($GLOBALS['__test_config_options'])->not->toHaveKey('mt_maint_steps_last');
	});
});

describe('poller deferral', function () {
	it('records the reason and the time, then clears it on a normal run', function () {
		mactrack_poller_deferred('upgrade');

		$note = json_decode($GLOBALS['__test_config_options']['mt_poller_deferred'], true);
		expect($note['reason'])->toBe('upgrade');
		expect($note['time'])->toBeGreaterThan(time() - 5);

		mactrack_poller_deferred_clear();
		expect($GLOBALS['__test_config_options']['mt_poller_deferred'])->toBe('');
	});

	it('writes nothing when there is no note to clear', function () {
		mactrack_poller_deferred_clear();

		expect($GLOBALS['__test_config_options'])->not->toHaveKey('mt_poller_deferred');
	});
});

describe('process check', function () {
	it('finds this process and not an absent one', function () {
		expect(mactrack_activity_pid_alive(getmypid()))->toBeTrue();
		expect(mactrack_activity_pid_alive(getmypid(), true))->toBeTrue();
		expect(mactrack_activity_pid_alive(4194000))->toBeFalse();
		expect(mactrack_activity_pid_alive(0))->toBeFalse();
	});

	it('falls back to /proc without the posix extension', function () {
		expect(mactrack_activity_pid_alive(getmypid(), false))->toBe(file_exists('/proc/' . getmypid()));
		expect(mactrack_activity_pid_alive(4194000, false))->toBeFalse();
	});
});

describe('state', function () {
	it('is idle when nothing is recorded or running', function () {
		$state = mactrack_activity_state(MT_ACT_NOW);

		expect($state['state'])->toBe('idle');
		expect($state['since'])->toBeNull();
		expect($state['deferred'])->toBeNull();
	});

	it('reports the aggregated ports rebuild with an estimated row count and never counts rows', function () {
		mt_act_table('mac_track_aggregated_ports_upgrade', true);
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'information_schema.TABLES', '61234567');
		set_config_option('mt_partition_converting', '1794464000');
		mt_act_record('delete_ips', getmypid());

		$state = mactrack_activity_state(MT_ACT_NOW);

		expect($state['state'])->toBe('upgrading');
		expect($state['text'])->toContain('rebuilding aggregated ports');
		expect($state['detail'])->toContain('61,234,567');

		$calls = array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_fetch_cell_prepared');
		expect(array_values($calls)[0]['params'])->toBe(['cacti', 'mac_track_aggregated_ports_upgrade']);
		expect(array_filter(array_column($GLOBALS['__test_db_calls'], 'sql'), fn ($s) => stripos($s, 'COUNT(') !== false))->toBe([]);
	});

	it('reports a registered background upgrade before its work table exists', function () {
		mt_act_table('processes', true);
		mactrack_test_queue_return_for('db_fetch_row_prepared', 'FROM processes', ['pid' => '1', 'started' => '1794464000', 'now' => '1794464100', 'timeout' => '3600']);

		$state = mactrack_activity_state(MT_ACT_NOW);

		expect($state['state'])->toBe('upgrading');
		expect($state['detail'])->toBe('');
	});

	it('leaves the row count out when the work table is not in information_schema', function () {
		mt_act_table('mac_track_aggregated_ports_upgrade', true);
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'information_schema.TABLES', false);

		expect(mactrack_activity_state(MT_ACT_NOW)['detail'])->toBe('');
	});

	it('reports a port table conversion since its start, ahead of a recorded step', function () {
		set_config_option('mt_partition_converting', '1794464000');
		mt_act_record('delete_ips', getmypid());

		$state = mactrack_activity_state(MT_ACT_NOW);

		expect($state['state'])->toBe('converting');
		expect($state['since'])->toBe(1794464000);
	});

	it('reports a recorded step of a live process with its detail', function () {
		mt_act_record('scanning', getmypid(), ['devices' => 40]);

		$state = mactrack_activity_state(MT_ACT_NOW);

		expect($state['state'])->toBe('activity');
		expect($state['text'])->toBe(mactrack_activity_step_label('scanning'));
		expect($state['since'])->toBe(MT_ACT_NOW - 90);
		expect($state['detail'])->toContain('40');
		expect($state['stopped'])->toBeFalse();
	});

	it('says the process stopped when the recorded process is gone', function () {
		mt_act_record('optimize_ips', 4194000);

		$state = mactrack_activity_state(MT_ACT_NOW);

		expect($state['state'])->toBe('activity');
		expect($state['stopped'])->toBeTrue();
		expect($state['text'])->toContain('stopped');
		expect($state['text'])->toContain(mactrack_activity_step_label('optimize_ips'));
	});

	it('reports running scanner processes when no step is recorded', function () {
		mactrack_test_queue_return('db_fetch_row_prepared', ['devices' => '7', 'since' => '1794464000']);

		$state = mactrack_activity_state(MT_ACT_NOW);

		expect($state['state'])->toBe('scanning');
		expect($state['text'])->toContain('7');
		expect($state['since'])->toBe(1794464000);
	});

	it('copes with a scanner row that has no start time', function () {
		mactrack_test_queue_return('db_fetch_row_prepared', ['devices' => '1', 'since' => null]);

		expect(mactrack_activity_state(MT_ACT_NOW)['since'])->toBeNull();
	});

	it('ignores a recorded value that is not a step', function () {
		set_config_option('mt_activity', 'garbage');

		expect(mactrack_activity_state(MT_ACT_NOW)['state'])->toBe('idle');
	});

	it('defaults the time to now', function () {
		expect(mactrack_activity_state()['state'])->toBe('idle');
	});

	it('shows a skipped poller run within two collection intervals', function () {
		set_config_option('mt_poller_deferred', json_encode(['reason' => 'upgrade', 'time' => MT_ACT_NOW - 1790]));

		$deferred = mactrack_activity_state(MT_ACT_NOW)['deferred'];

		expect($deferred['time'])->toBe(MT_ACT_NOW - 1790);
		expect($deferred['reason'])->toContain('upgrade');
	});

	it('names the leftover rebuild table as the reason', function () {
		set_config_option('mt_poller_deferred', json_encode(['reason' => 'rebuild_table', 'time' => MT_ACT_NOW]));

		expect(mactrack_activity_state(MT_ACT_NOW)['deferred']['reason'])->toContain('rebuild');
	});

	it('drops a skipped poller run older than two collection intervals', function () {
		set_config_option('mt_poller_deferred', json_encode(['reason' => 'upgrade', 'time' => MT_ACT_NOW - 1801]));

		expect(mactrack_activity_state(MT_ACT_NOW)['deferred'])->toBeNull();
	});

	it('drops the note when collection is disabled or the note is malformed', function () {
		set_config_option('mt_poller_deferred', json_encode(['reason' => 'upgrade', 'time' => MT_ACT_NOW]));
		set_config_option('mt_collection_timing', 'disabled');
		expect(mactrack_activity_state(MT_ACT_NOW)['deferred'])->toBeNull();

		set_config_option('mt_collection_timing', '15');
		set_config_option('mt_poller_deferred', '{"reason":"upgrade"}');
		expect(mactrack_activity_state(MT_ACT_NOW)['deferred'])->toBeNull();
	});
});

describe('table sizes', function () {
	it('asks information_schema once for the MacTrack tables of this database', function () {
		mactrack_table_sizes();

		expect($GLOBALS['__test_db_calls'])->toHaveCount(1);
		$call = $GLOBALS['__test_db_calls'][0];
		expect($call['fn'])->toBe('db_fetch_assoc_prepared');
		expect($call['sql'])->toContain('information_schema.TABLES');
		expect($call['sql'])->toContain('TABLE_SCHEMA = ?');
		expect($call['sql'])->toContain('TABLE_NAME LIKE ?');
		expect($call['sql'])->not->toContain('COUNT(');
		expect($call['params'])->toBe(['cacti', 'mac\_track\_%']);
	});

	it('lists the largest table first by data plus index size', function () {
		$row = fn ($name, $data, $index) => ['table_name' => $name, 'table_rows' => '10', 'data_length' => (string) $data, 'index_length' => (string) $index, 'data_free' => '0'];
		mactrack_test_queue_return('db_fetch_assoc_prepared', [
			$row('mac_track_small', 100, 50),
			$row('mac_track_ports', 1000, 5000),
			$row('mac_track_ips', 4000, 500),
		]);

		$tables = mactrack_table_sizes();

		expect(array_column($tables, 'table_name'))->toBe(['mac_track_ports', 'mac_track_ips', 'mac_track_small']);
		expect(array_column($tables, 'total'))->toBe([6000, 4500, 150]);
	});

	it('returns an empty list when the query fails', function () {
		mactrack_test_queue_return('db_fetch_assoc_prepared', false);

		expect(mactrack_table_sizes())->toBe([]);
	});

	it('formats sizes for people', function () {
		expect(mactrack_activity_bytes(512))->toBe('512 B');
		expect(mactrack_activity_bytes(2048))->toBe('2 KB');
		expect(mactrack_activity_bytes(5 * 1024 * 1024))->toBe('5 MB');
		expect(mactrack_activity_bytes(1.5 * 1024 ** 3))->toBe('1.5 GB');
		expect(mactrack_activity_bytes('0'))->toBe('0 B');
		expect(mactrack_activity_bytes(3 * 1024 ** 5))->toBe('3072 TB');
	});
});

describe('database disk space', function () {
	it('reads the free space of a local data directory', function () {
		foreach (['localhost', '127.0.0.1', '::1', ''] as $host) {
			$GLOBALS['database_hostname'] = $host;
			mactrack_test_queue_return_for('db_fetch_cell_prepared', '@@datadir', sys_get_temp_dir());

			$disk = mactrack_database_disk_free();

			expect($disk['reason'])->toBe('');
			expect($disk['bytes'])->toBeInt()->toBeGreaterThan(0);
		}
	});

	it('reads free space from a data directory the web user cannot list', function () {
		$GLOBALS['database_hostname'] = 'localhost';
		$dir                          = sys_get_temp_dir() . '/mactrack-datadir-' . getmypid();
		mkdir($dir, 0700);
		chmod($dir, 0000);
		mactrack_test_queue_return_for('db_fetch_cell_prepared', '@@datadir', $dir);

		try {
			$disk = mactrack_database_disk_free();
		} finally {
			chmod($dir, 0700);
			rmdir($dir);
		}

		expect($disk['reason'])->toBe('');
		expect($disk['bytes'])->toBeInt()->toBeGreaterThan(0);
	});

	it('does not look at this host for a remote database', function () {
		$GLOBALS['database_hostname'] = 'db.example.net';

		$disk = mactrack_database_disk_free();

		expect($disk)->toBe(['bytes' => null, 'reason' => 'remote']);
		expect($GLOBALS['__test_db_calls'])->toBe([]);
	});

	it('reports unknown when the data directory cannot be read from here', function () {
		foreach ([false, '', '/nonexistent/mactrack/datadir/'] as $datadir) {
			$GLOBALS['__test_matched_returns'] = [];
			mactrack_test_queue_return_for('db_fetch_cell_prepared', '@@datadir', $datadir);

			expect(mactrack_database_disk_free())->toBe(['bytes' => null, 'reason' => 'unreadable']);
		}
	});
});
