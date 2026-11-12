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

/*
 * A run must not time out the moment it starts, must count scanners it
 * has launched but that have not started, and must stop its scanners
 * before it loads and truncates their table.
 */
beforeEach(function () {
	$this->src  = file_get_contents(dirname(__DIR__, 2) . '/poller_mactrack.php');
	$this->flat = preg_replace('/\s+/', ' ', $this->src);
});

it('keeps at least 80% of the frequency as the run limit', function () {
	expect($this->flat)->toContain('$max_run_duration = max(($collect_frequency * 60) - $poller_interval, (int) ($collect_frequency * 48));');

	// 5 minutes on a 300 s poller used to leave 0 seconds
	$collect_frequency = 5;
	$poller_interval   = 300;
	$max_run_duration  = max(($collect_frequency * 60) - $poller_interval, (int) ($collect_frequency * 48));

	expect($max_run_duration)->toBe(240);
});

it('registers each device before launching its scanner', function () {
	$add  = strpos($this->src, "db_process_add(\$device_ids[\$i + \$j]['device_id']);");
	$exec = strpos($this->src, 'exec_background($command_string, $extra_args);');

	expect($add)->toBeInt();
	expect($add)->toBeLessThan($exec);
});

it('counts only scanners against the process limit', function () {
	expect($this->flat)->toContain("\$processes_running = db_fetch_cell('SELECT COUNT(*) FROM mac_track_processes WHERE device_id > 0'); \$processes_available = \$concurrent_processes - \$processes_running;");
});

it('stops the scanners before loading the ports after a timeout', function () {
	$stop     = strpos($this->src, "if (\$exit_mactrack) {\n\t\t\tmactrack_stop_scanners();");
	$transfer = strpos($this->src, '// transfer temp port results into permanent table');

	expect($stop)->toBeInt();
	expect($stop)->toBeLessThan($transfer);
});

it('removes every scanner row when it stops the scanners', function () {
	foreach (array('mactrack_kill_process', 'mactrack_stop_scanners') as $fn) {
		if (!function_exists($fn)) {
			$at = strpos($this->src, "function $fn(");
			eval(substr($this->src, $at, strpos($this->src, "\n}\n", $at) - $at + 2));
		}
	}

	$GLOBALS['__test_db_calls'] = array();
	// PID 0: launched but not started, so nothing to signal
	mactrack_test_queue_return('db_fetch_assoc', array(array('device_id' => 7, 'process_id' => 0)));

	mactrack_stop_scanners();

	expect(array_column($GLOBALS['__test_db_calls'], 'sql'))->toContain('DELETE FROM mac_track_processes WHERE device_id > 0');
});
