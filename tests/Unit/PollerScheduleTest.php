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

$src = file_get_contents(dirname(__DIR__, 2) . '/poller_mactrack.php');

if (!function_exists('mactrack_run_slot')) {
	$at = strpos($src, 'function mactrack_run_slot(');
	eval(substr($src, $at, strpos($src, "\n}\n", $at) - $at + 2));
}

it('keeps the slot when the run starts on time or a little early', function () {
	expect(mactrack_run_slot(6000, 6000, 600))->toBe(6000);
	expect(mactrack_run_slot(6000, 5999, 600))->toBe(6000);
});

it('stays on the grid when the run starts late', function () {
	expect(mactrack_run_slot(6000, 6002, 600))->toBe(6000);
});

it('steps to the latest passed slot after missed runs', function () {
	expect(mactrack_run_slot(6000, 7900, 600))->toBe(7800);
});

it('runs a slot that is due within half a poller interval', function () use ($src) {
	$flat = preg_replace('/\s+/', ' ', $src);

	expect($flat)->toContain('$run_due = $time_till_next_run <= $poller_interval / 2;');
	expect($flat)->toContain('if ($run_due || $force == true) {');
	expect($flat)->not->toContain('if ($time_till_next_run < 0 || $force == true) {');
});

it('schedules tomorrow with strtotime, not plus 86400', function () use ($src) {
	expect($src)->toContain("\$next_run_time = strtotime('tomorrow ' . \$base_start_time);");
	expect($src)->not->toContain("' ' . \$base_start_time) + 3600 * 24;");
});

it('lands each 10-minute run on its slot over a day of poller jitter', function () {
	// poller every 300 s, firing up to 2 s early or late; base slot 0
	$last = 0;
	$runs = [];

	for ($k = 1; $k <= 288; $k++) {
		$now  = $k * 300 + (($k % 3) - 1) * 2;
		$next = $last + 600;

		if ($next - $now <= 150) {
			$last   = mactrack_run_slot($next, $now, 600);
			$runs[] = $last;
		}
	}

	expect($runs)->toBe(range(600, 86400, 600));
});
