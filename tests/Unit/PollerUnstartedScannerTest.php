<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__) . '/Support/load_function.php';

// poller_mactrack.php runs the poller when it is included, so load only the helper
mactrack_test_load_function('poller_mactrack.php', 'mactrack_reap_unstarted_scanners');

/*
 * The poller registers each scanner before launching it. A scanner that
 * never starts leaves that row at PID 0, and the poller must not wait for
 * it until the run times out.
 */
beforeEach(function () {
	$GLOBALS['__test_db_calls']        = [];
	$GLOBALS['__test_next_returns']    = [];
	$GLOBALS['__test_matched_returns'] = [];
	$GLOBALS['__test_cacti_log']       = [];
});

it('removes and logs scanner rows still at PID 0 after the grace period', function () {
	mactrack_test_queue_return_for('db_fetch_assoc_prepared', 'process_id = 0', [['device_id' => '7'], ['device_id' => '9']]);

	mactrack_reap_unstarted_scanners(60);

	$delete = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => strpos($c['sql'], 'DELETE FROM mac_track_processes') === 0));
	expect($delete)->toHaveCount(1);
	expect($delete[0]['sql'])->toContain('device_id > 0', 'process_id = 0', 'start_date < NOW() - INTERVAL ? SECOND');
	expect($delete[0]['params'])->toBe([60]);
	expect($GLOBALS['__test_cacti_log'][0])->toContain('device id(s) 7, 9');
});

it('leaves started scanners, the poller and the resolver alone', function () {
	mactrack_test_queue_return_for('db_fetch_assoc_prepared', 'process_id = 0', []);

	mactrack_reap_unstarted_scanners();

	expect(array_filter($GLOBALS['__test_db_calls'], fn ($c) => strpos($c['sql'], 'DELETE') === 0))->toBe([]);
	expect($GLOBALS['__test_cacti_log'])->toBe([]);
	expect($GLOBALS['__test_db_calls'][0]['params'])->toBe([60]);
});

it('checks for unstarted scanners in both wait loops before counting', function () {
	$src = file_get_contents(dirname(__DIR__, 2) . '/poller_mactrack.php');

	expect(substr_count($src, "mactrack_reap_unstarted_scanners();\n\n\t\t\t// scanners only"))->toBe(1);
	expect(substr_count($src, "mactrack_reap_unstarted_scanners();\n\n\t\t\t\$processes_running = db_fetch_cell("))->toBe(1);
});
