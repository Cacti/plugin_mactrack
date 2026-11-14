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
 * Device and site removal bind the id instead of concatenating it (#288),
 * and the stale-process sweep removes the rows it found in one statement
 * keyed on device_id (#295).
 */

require_once dirname(__DIR__, 2) . '/mactrack_actions.php';

/*
 * poller_mactrack.php runs the poller when it is included, so load only the
 * function under test from its source.
 */
require_once dirname(__DIR__) . '/Support/load_function.php';

mactrack_test_load_function('poller_mactrack.php', 'mactrack_kill_process');
mactrack_test_load_function('poller_mactrack.php', 'clear_old_processes');

beforeEach(function () {
	$GLOBALS['__test_db_calls']       = [];
	$GLOBALS['__test_next_returns']   = [];
	$GLOBALS['__test_config_options'] = ['mt_script_runtime' => '10'];
});

test('removing a device binds the device id in every delete', function () {
	api_mactrack_device_remove('7 OR 1=1');

	$calls = $GLOBALS['__test_db_calls'];

	expect($calls)->toHaveCount(10);
	expect(implode(' ', array_column($calls, 'sql')))->toContain('DELETE FROM mac_track_dot1x WHERE device_id = ?');

	foreach ($calls as $call) {
		expect($call['fn'])->toBe('db_execute_prepared')
			->and($call['sql'])->toEndWith('WHERE device_id = ?')
			->and($call['params'])->toBe(['7 OR 1=1']);
	}
});

test('removing a site binds the site id in every delete', function () {
	api_mactrack_site_remove(3);

	$calls = $GLOBALS['__test_db_calls'];

	expect($calls)->toHaveCount(11)
		->and(implode(' ', array_column($calls, 'sql')))->toContain('DELETE FROM mac_track_dot1x WHERE site_id = ?')
		->and(implode(' ', array_column($calls, 'sql')))->toContain('DELETE FROM mac_track_arp WHERE site_id = ?')
		->and(array_column($calls, 'fn'))->each->toBe('db_execute_prepared')
		->and(array_column($calls, 'params'))->each->toBe([3]);
});

test('stale process entries are removed in one delete keyed on device_id', function () {
	mactrack_test_queue_return('db_fetch_assoc_prepared', [
		['device_id' => '4', 'process_id' => null, 'status' => 'Queued'],
		['device_id' => '9', 'process_id' => '0', 'status' => 'Running'],
	]);

	clear_old_processes(0);

	$deletes = array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return stripos($call['sql'], 'DELETE') !== false;
	}));

	expect($deletes)->toHaveCount(1)
		->and(preg_replace('/\s+/', ' ', $deletes[0]['sql']))->toBe('DELETE FROM mac_track_processes WHERE device_id IN (?,?) AND start_date < ?')
		->and(array_slice($deletes[0]['params'], 0, 2))->toBe([4, 9])
		->and($deletes[0]['params'][2])->toBe($GLOBALS['__test_db_calls'][0]['params'][0]);
});

test('no delete runs when there are no stale processes', function () {
	clear_old_processes(2);

	$fns = array_column($GLOBALS['__test_db_calls'], 'fn');

	expect($fns)->toBe(['db_fetch_assoc_prepared']);
});

test('stale process cleanup also clears the poller and resolver rows', function () {
	mactrack_test_queue_return('db_fetch_assoc_prepared', []);

	clear_old_processes(0);

	expect(preg_replace('/\s+/', ' ', $GLOBALS['__test_db_calls'][0]['sql']))->toContain('LEFT JOIN mac_track_devices AS mtd');
});

test('a reused PID that is not a MacTrack worker is not signalled', function () {
	if (!is_dir('/proc/self')) {
		$this->markTestSkipped('needs /proc');
	}

	$child = proc_open(['sleep', '30'], [], $pipes);
	$pid   = proc_get_status($child)['pid'];

	try {
		mactrack_kill_process($pid);
		usleep(200000);

		expect(proc_get_status($child)['running'])->toBeTrue();
	} finally {
		proc_terminate($child);
		proc_close($child);
	}
});
