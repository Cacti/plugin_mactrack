#!/usr/bin/php -q
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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

$dir = __DIR__;
chdir($dir);

if (substr_count(strtolower($dir), 'mactrack')) {
	chdir('../../');
}

require('./include/cli_check.php');

global $config;
require_once($config['base_path'] . '/plugins/mactrack/lib/mactrack_functions.php');

$keep_old = false;
$debug    = false;
$scheme   = null;

$parms = $_SERVER['argv'];
array_shift($parms);

foreach ($parms as $parameter) {
	[$arg, $value] = array_pad(explode('=', $parameter, 2), 2, '');

	switch ($arg) {
		case '--partition':
			$schemes = ['daily' => 'day', 'weekly' => 'week', 'disabled' => 'disabled'];

			if (!isset($schemes[$value])) {
				print "ERROR: --partition must be daily, weekly or disabled.\n";

				exit(1);
			}

			$scheme = $schemes[$value];

			break;
		case '-d':
		case '--debug':
			$debug = true;

			break;
		case '--keep-old':
			$keep_old = true;

			break;
		case '--days':
		case '-e':
		case '--engine':
		case '--charset':
		case '--collate':
			// The new table copies the engine, character set and collation of
			// the old one, and retention comes from the MacTrack settings.
			print "NOTE: $arg is no longer used and is ignored.\n";

			break;
		case '--version':
		case '-V':
		case '-v':
			display_version();

			exit(0);
		case '--help':
		case '-H':
		case '-h':
			display_help();

			exit(0);
		default:
			print 'ERROR: Invalid Parameter ' . $parameter . "\n\n";
			display_help();

			exit(1);
	}
}

print "WARNING: Run this maintenance off hours. It copies the full port history and may take a long time.\n";
print "WARNING: Allow disk space for a second copy of the table and its indexes.\n";
print "NOTE: Scheduled scans are skipped while this runs. The original table remains readable until the final swap.\n";

$result = mactrack_partition_convert($keep_old, function (int $done, int $total, string $scan_date) use ($debug): void {
	if ($debug || $done === $total || $done % 100 === 0) {
		print "NOTE: Copied range $done of $total (starting $scan_date)\n";
	}
}, 1, $scheme);

if (!$result['ok']) {
	print 'FATAL: ' . $result['reason'] . "\n";

	exit(1);
}

if ($result['rows'] === 0 && $result['partitions'] === 0 && $result['reason'] !== '') {
	print 'NOTE: ' . $result['reason'] . "\n";

	exit(0);
}

printf("NOTE: Converted %d rows; layout: %s; partitions: %d.\n", $result['rows'], $result['granularity'], $result['partitions']);

if ($result['reason'] !== '') {
	print 'WARNING: ' . $result['reason'] . "\n";
}

exit(0);

/**
 * Prints this script's name, version, and copyright banner to stdout.
 *
 * @return void
 */
function display_version(): void {
	$info = plugin_mactrack_version();

	print 'Mactrack Convert Partitioned, Version ' . $info['version'] . ', ' . COPYRIGHT_YEARS . "\n";
}

/**
 * Prints this script's version banner followed by its command-line
 * usage instructions to stdout.
 *
 * @return void
 */
function display_help(): void {
	display_version();

	print "\nusage: mactrack_convert.php [--partition=daily|weekly|disabled] [--keep-old] [-d] [-h] [-v]\n\n";
	print "Converts mac_track_ports between daily, weekly and unpartitioned layouts.\n";
	print "Without --partition, an unpartitioned table is converted to the Port\n";
	print "Partition Size setting (weekly by default) and a partitioned table is left\n";
	print "unchanged. Run off hours with space for a second table copy.\n";
	print "The full history is copied one range at a time, without deleting source rows.\n";
	print "If interrupted before the swap, rerun the command to restart the copy. Data is\n";
	print "copied into a new table, which then replaces the old one; until then the\n";
	print "old table stays in use. Scheduled scans are skipped while this runs.\n\n";
	print "--keep-old    - Keep the original table as mac_track_ports_old\n";
	print "-d | --debug  - Report every copied range\n";
	print "-v --version  - Display the version\n";
	print "-h --help     - Display this help message\n";
}
