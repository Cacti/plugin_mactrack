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
 * Partition DDL waits at most 2 s for the metadata lock, because that wait
 * is also the longest stall every reader of mac_track_ports sees. It retries a few times instead of waiting longer,
 * always restores the session's lock_wait_timeout, and runs under a named
 * lock so the poller and maintenance never issue DDL at the same time.
 */

beforeAll(function () {
	mactrack_test_load(dirname(__DIR__, 2) . '/lib/mactrack_partitions.php');
	mactrack_test_load(dirname(__DIR__, 2) . '/lib/mactrack_partition_ops.php');
});

beforeEach(function () {
	$GLOBALS['__test_db_calls']        = [];
	$GLOBALS['__test_next_returns']    = [];
	$GLOBALS['__test_matched_returns'] = [];
	$GLOBALS['__test_config_options']  = [];
	$GLOBALS['__test_cacti_log']       = [];
});

/**
 * Returns the SQL of every recorded db_execute call.
 *
 * @return string[]
 */
function mt_ops_executed(): array {
	return array_values(array_map(fn ($c) => $c['sql'], array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_execute_prepared')));
}

/**
 * Returns the bound parameters of the last recorded db_execute_prepared call.
 *
 * @return array
 */
function mt_ops_last_params(): array {
	$calls = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_execute_prepared'));

	return end($calls)['params'];
}

it('runs DDL once with a 2 s lock wait and restores the old wait', function () {
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'lock_wait_timeout', '31536000');

	expect(mactrack_partition_ddl('ALTER TABLE x', 3, 0))->toBeTrue();
	expect(mt_ops_executed())->toBe([
		'SET SESSION lock_wait_timeout = 2',
		'ALTER TABLE x',
		'SET SESSION lock_wait_timeout = 31536000',
	]);
	expect(mt_ops_last_params())->toBe([]);
});

it('retries a failed statement up to the attempt limit', function () {
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'lock_wait_timeout', '50');
	mactrack_test_queue_return('db_execute_prepared', true);  // SET 2
	mactrack_test_queue_return('db_execute_prepared', false); // attempt 1
	mactrack_test_queue_return('db_execute_prepared', false); // attempt 2
	mactrack_test_queue_return('db_execute_prepared', true);  // attempt 3

	expect(mactrack_partition_ddl('ALTER TABLE x', 3, 0))->toBeTrue();
	expect(array_count_values(mt_ops_executed())['ALTER TABLE x'])->toBe(3);
	expect(array_slice(mt_ops_executed(), -1)[0])->toBe('SET SESSION lock_wait_timeout = 50');
	expect($GLOBALS['__test_cacti_log'])->toBe([]);
});

it('gives up after the last attempt, logs once and still restores the wait', function () {
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'lock_wait_timeout', '50');
	mactrack_test_queue_return('db_execute_prepared', true);
	mactrack_test_queue_return('db_execute_prepared', false);
	mactrack_test_queue_return('db_execute_prepared', false);

	expect(mactrack_partition_ddl('ALTER TABLE x', 2, 0))->toBeFalse();
	expect(array_count_values(mt_ops_executed())['ALTER TABLE x'])->toBe(2);
	expect(array_slice(mt_ops_executed(), -1)[0])->toBe('SET SESSION lock_wait_timeout = 50');
	expect($GLOBALS['__test_cacti_log'])->toHaveCount(1);
	expect($GLOBALS['__test_cacti_log'][0])->toContain('ALTER TABLE x');
});

it('falls back to the server default wait when it cannot read the old one', function () {
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'lock_wait_timeout', '');

	mactrack_partition_ddl('ALTER TABLE x', 1, 0);

	expect(array_slice(mt_ops_executed(), -1)[0])->toBe('SET SESSION lock_wait_timeout = DEFAULT');
});

it('takes and releases the named partition lock', function () {
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'GET_LOCK', '1');

	expect(mactrack_partition_lock())->toBeTrue();
	mactrack_partition_unlock();

	$calls = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] !== 'cacti_log'));
	expect($calls[0]['sql'])->toBe('SELECT GET_LOCK(?, 10)');
	expect($calls[1]['sql'])->toBe('SELECT RELEASE_LOCK(?)');
	expect($calls[0]['params'])->toBe([mactrack_lock_name('partitions')]);
	expect($calls[1]['params'])->toBe([mactrack_lock_name('partitions')]);
});

it('reports a lock it could not take', function ($result) {
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'GET_LOCK', $result);

	expect(mactrack_partition_lock())->toBeFalse();
})->with(['0', null, '']);

it('merges status fields and stamps the time', function () {
	$GLOBALS['__test_config_options']['mt_partition_status'] = json_encode(['granularity' => 'hour', 'reason' => 'old']);

	mactrack_partition_status(['reason' => 'lock']);

	$status = json_decode($GLOBALS['__test_config_options']['mt_partition_status'], true);
	expect($status['granularity'])->toBe('hour');
	expect($status['reason'])->toBe('lock');
	expect($status['time'])->toBeInt();
});

it('starts a fresh status when the stored one is not JSON', function () {
	$GLOBALS['__test_config_options']['mt_partition_status'] = 'garbage';

	mactrack_partition_status(['reason' => 'x']);

	expect(json_decode($GLOBALS['__test_config_options']['mt_partition_status'], true))->toHaveKeys(['reason', 'time']);
});

/**
 * Connection double for mactrack_partition_exec(): records statements and
 * fails the first $fail of them with the given error code.
 *
 * @param  int    $fail Statements to fail.
 * @param  int    $code MySQL error code to report on failure.
 * @return object
 */
function mt_ops_conn(int $fail = 0, int $code = 1205): object {
	return new class($fail, $code) {
		public array $sql = [];
		private int $last = 0;

		public function __construct(private int $fail, private int $code) {
		}

		public function exec(string $sql) {
			$this->sql[] = $sql;
			$this->last  = $this->fail-- > 0 ? $this->code : 0;

			return $this->last === 0 ? 0 : false;
		}

		public function errorInfo(): array {
			return [$this->last === 0 ? '00000' : 'HY000', $this->last === 0 ? null : $this->last, $this->last === 0 ? null : 'Lock wait timeout exceeded'];
		}
	};
}

it('runs DDL on Cacti\'s connection directly, so core does not retry lock waits', function () {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	$database_hostname = 'db';
	$database_port     = '3306';
	$database_default  = 'cacti';
	$conn              = mt_ops_conn(1);
	$database_sessions = ['db:3306:cacti' => $conn];

	try {
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'lock_wait_timeout', '50');

		expect(mactrack_partition_ddl('ALTER TABLE x', 3, 0))->toBeTrue();
		expect($conn->sql)->toBe(['ALTER TABLE x', 'ALTER TABLE x']);
		expect(in_array('ALTER TABLE x', mt_ops_executed(), true))->toBeFalse();
	} finally {
		$database_sessions = [];
	}
});

it('logs the server error of the last failed attempt', function () {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	$database_hostname = 'db';
	$database_port     = '3306';
	$database_default  = 'cacti';
	$database_sessions = ['db:3306:cacti' => mt_ops_conn(5)];

	try {
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'lock_wait_timeout', '50');

		expect(mactrack_partition_ddl('ALTER TABLE x', 2, 0))->toBeFalse();
		expect($GLOBALS['__test_cacti_log'][0])->toContain('1205');
	} finally {
		$database_sessions = [];
	}
});

it('treats a thrown PDOException as a failed attempt', function () {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	$database_hostname = 'db';
	$database_port     = '3306';
	$database_default  = 'cacti';
	$database_sessions = ['db:3306:cacti' => new class {
		public function exec(string $sql) {
			throw new PDOException('Lock wait timeout exceeded');
		}
	}];

	try {
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'lock_wait_timeout', '50');

		expect(mactrack_partition_ddl('ALTER TABLE x', 1, 0))->toBeFalse();
		expect($GLOBALS['__test_cacti_log'][0])->toContain('Lock wait timeout exceeded');
	} finally {
		$database_sessions = [];
	}
});

it('pauses between attempts when asked', function () {
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'lock_wait_timeout', '50');
	mactrack_test_queue_return('db_execute_prepared', true);
	mactrack_test_queue_return('db_execute_prepared', false);
	mactrack_test_queue_return('db_execute_prepared', true);

	$t = microtime(true);
	expect(mactrack_partition_ddl('ALTER TABLE x', 2, 1))->toBeTrue();
	expect(microtime(true) - $t)->toBeGreaterThan(0.9);
});
