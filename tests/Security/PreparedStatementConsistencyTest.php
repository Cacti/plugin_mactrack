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
 * Ratchets the count of raw (non-prepared) db_execute()/db_fetch_*() calls
 * per file downward over time: a regression that adds a new raw call to any
 * tracked file fails, but the baseline below does not require every
 * pre-existing raw call to be migrated at once.
 */
describe('prepared statement consistency in mactrack', function () {
	$baseline = [
		'includes/database.php'        => 141,
		'lib/mactrack_3com.php'        => 1,
		'lib/mactrack_aruba_oscx.php'  => 1,
		'lib/mactrack_cisco.php'       => 5,
		'lib/mactrack_enterasys_N7.php' => 1,
		'lib/mactrack_extreme.php'     => 1,
		'lib/mactrack_functions.php'   => 28,
		'lib/mactrack_h3c_3com.php'    => 1,
		'mactrack_actions.php'         => 19,
		'mactrack_convert.php'         => 9,
		'mactrack_device_types.php'    => 11,
		'mactrack_devices.php'         => 7,
		'mactrack_macauth.php'         => 2,
		'mactrack_macwatch.php'        => 2,
		'mactrack_resolver.php'        => 3,
		'mactrack_scanner.php'         => 1,
		'mactrack_sites.php'           => 3,
		'mactrack_snmp.php'            => 4,
		'mactrack_utilities.php'       => 25,
		'mactrack_vendormacs.php'      => 2,
		'mactrack_view_arp.php'        => 7,
		'mactrack_view_devices.php'    => 4,
		'mactrack_view_dot1x.php'      => 6,
		'mactrack_view_graphs.php'     => 2,
		'mactrack_view_interfaces.php' => 3,
		'mactrack_view_ips.php'        => 3,
		'mactrack_view_macs.php'       => 9,
		'mactrack_view_sites.php'      => 3,
		'poller_mactrack.php'          => 39,
		'setup.php'                    => 20,
	];

	$root    = realpath(__DIR__ . '/../..');
	$pattern = '/\bdb_(?:execute|fetch_row|fetch_assoc|fetch_cell)\s*\(/i';

	it('never increases raw (non-prepared) database calls beyond the recorded baseline', function () use ($baseline, $root, $pattern) {
		$files = mactrack_test_production_php_files();

		foreach ($files as $file) {
			if (strpos($file, 'tests/') === 0) {
				continue;
			}

			$source = file_get_contents($root . '/' . $file);

			expect($source)->not->toBeFalse("Unable to read {$file}");

			$count = preg_match_all($pattern, $source);

			expect($count)->toBeLessThanOrEqual(
				$baseline[$file] ?? 0,
				"{$file} increased raw database calls from " . ($baseline[$file] ?? 0) . " to {$count}"
			);
		}
	});
});
