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

$parms = $_SERVER['argv'];
array_shift($parms);

foreach ($parms as $parameter) {
	[$arg] = explode('=', $parameter, 2);

	switch ($arg) {
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

print "NOTE: Converting mac_track_ports to the scan-date layout. Scheduled scans are skipped until this finishes.\n";

$result = mactrack_partition_convert($keep_old, function (int $done, int $total, string $scan_date) use ($debug): void {
	if ($debug || $done === $total || $done % 100 === 0) {
		print "NOTE: Copied scan $done of $total ($scan_date)\n";
	}
});

if (!$result['ok']) {
	print 'FATAL: ' . $result['reason'] . "\n";

	exit(1);
}

printf("NOTE: Converted %d rows into %d %s partitions.\n", $result['rows'], $result['partitions'], $result['granularity']);

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

	print "\nusage: mactrack_convert.php [--keep-old] [-d] [-h] [-v]\n\n";
	print "Converts mac_track_ports to the scan-date partition layout. The data is\n";
	print "copied into a new table, which then replaces the old one; until then the\n";
	print "old table stays in use. Scheduled scans are skipped while this runs.\n\n";
	print "--keep-old    - Keep the original table as mac_track_ports_old\n";
	print "-d | --debug  - Report every copied scan\n";
	print "-v --version  - Display the version\n";
	print "-h --help     - Display this help message\n";
}
