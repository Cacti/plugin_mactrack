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

if (function_exists('pcntl_async_signals')) {
	pcntl_async_signals(true);
} else {
	declare(ticks = 100);
}

ini_set('output_buffering', 'Off');
ini_set('max_runtime', '-1');
ini_set('memory_limit', '-1');

ini_set('max_execution_time', '-1');

set_time_limit(0);
ob_implicit_flush();

$dir = __DIR__;
chdir($dir);

require('../../include/cli_check.php');

global $config;

require_once($config['base_path'] . '/plugins/mactrack/lib/mactrack_functions.php');
require_once($config['base_path'] . '/plugins/mactrack/lib/mactrack_dns_resolution.php');
// Net_DNS2 autoloads its own classes with include paths relative to the library
// root, so that root has to be reachable independently of the current directory.
set_include_path($config['base_path'] . '/plugins/mactrack' . PATH_SEPARATOR . get_include_path());

$dns2 = $config['base_path'] . '/plugins/mactrack/Net/DNS2.php';

if (is_file($dns2)) {
	require_once($dns2);
}

if (!class_exists('Net_DNS2_Resolver')) {
	// The poller starts this script with exec_background(), so stderr goes nowhere.
	cacti_log('ERROR: Mactrack is missing its bundled Net_DNS2 library.  Reinstall the plugin.', false, 'MACTRACK');
	fwrite(STDERR, "Mactrack is missing its bundled Net_DNS2 library.  Reinstall the plugin.\n");

	exit(1);
}

// install signal handlers for UNIX only
if (function_exists('pcntl_signal')) {
	pcntl_signal(SIGTERM, 'sig_handler');
	pcntl_signal(SIGINT, 'sig_handler');
}

// get the mactrack polling cycle
$max_run_duration = read_config_option('mt_collection_timing');

// get the mactrack max script runtime - in minutes
$max_script_runtime = read_config_option('mt_script_runtime') * 60;

if (is_numeric($max_run_duration)) {
	// let PHP a 5 minutes less than the rerun frequency
	$max_run_duration = ($max_run_duration * 60) - 270;
	ini_set('max_execution_time', $max_run_duration);
} else {
	$max_run_duration = 3270;
	ini_set('max_execution_time', $max_run_duration);
}

// establish constants
define('DEVICE_HUB_SWITCH', 1);
define('DEVICE_SWITCH_ROUTER', 2);
define('DEVICE_ROUTER', 3);

// process calling arguments
$parms = $_SERVER['argv'];
array_shift($parms);

$debug   = false;
$site_id = '';
$start   = time();

if (cacti_sizeof($parms)) {
	foreach ($parms as $parameter) {
		if (strpos($parameter, '=') !== false) {
			[$arg, $value] = explode('=', $parameter);
		} else {
			$arg   = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '-sid':
				$site_id = $value;

				break;
			case '-d':
			case '--debug':
				$debug = true;

				break;
			case '--version':
			case '-V':
			case '-v':
				display_version();
				exit;
			case '--help':
			case '-H':
			case '-h':
				display_help();
				exit;
			default:
				print 'ERROR: Invalid Parameter ' . $parameter . PHP_EOL . PHP_EOL;
				display_help();
				exit;
		}
	}
}

// check if you need to run or not
if (read_config_option('mt_reverse_dns') == 'on') {
	$timeout        = read_config_option('mt_dns_timeout');
	$dns_primary    = read_config_option('mt_dns_primary');
	$dns_secondary  = read_config_option('mt_dns_secondary');
	$primary_down   = false;
	$secondary_down = false;
} else {
	mactrack_debug('Exiting due to Reverse DNS being disabled');
	exit;
}

// silently end if the registered process is still running
if (!register_process_start('mactrack_resolver', 'master', 0, $max_run_duration - 300)) {
	print 'FATAL: Detected an already running process.' . PHP_EOL;
	exit(0);
}

// place a process marker in the database for the ip resolver
db_process_add(0, true);

// a site scan resolves only that site's rows
if ($site_id != '') {
	$sql_where  = 'AND site_id = ?';
	$sql_params = [(int) $site_id];
} else {
	$sql_where  = '';
	$sql_params = [];
}

$nameservers = [];

if ($dns_primary != '') {
	$nameservers[] = $dns_primary;
}

if ($dns_secondary != '') {
	$nameservers[] = $dns_secondary;
}

if (cacti_sizeof($nameservers)) {
	$use_resolver = true;
	$resolver     = new Net_DNS2_Resolver(['nameservers' => $nameservers]);
} else {
	$use_resolver = false;
	$resolver     = false;
}

// Run while the poller is collecting: it fills in IP addresses as the
// scanners report and removes its own row (device_id -1) once they are
// done. Stop when that row is gone and nothing is left, or at the time
// limit.
while (1) {
	// check for pending signals
	pcntl_signal_dispatch();

	// read before the pass, so rows added before the poller finished are
	// resolved before the resolver stops
	$poller_running = db_fetch_cell('SELECT COUNT(*)
		FROM mac_track_processes
		WHERE device_id = -1') > 0;

	$break = (time() - $start) > $max_script_runtime;

	$unresolved_ips = db_fetch_assoc_prepared("SELECT *
		FROM mac_track_temp_ports
		WHERE ip_address != ''
		AND (dns_hostname = '' OR dns_hostname IS NULL)
		$sql_where", $sql_params);

	if (cacti_sizeof($unresolved_ips) == 0) {
		if (mactrack_resolver_idle_stop($poller_running, cacti_sizeof($unresolved_ips))) {
			mactrack_debug('Terminating DNS resolving, nothing to do');

			break;
		}

		mactrack_debug('No IP\'s require resolving this pass');
		sleep(3);
	} else {
		mactrack_debug(cacti_sizeof($unresolved_ips) . ' IP\'s require resolving this pass');

		foreach ($unresolved_ips as $key => $unresolved_ip) {
			$dns_hostname = mactrack_resolve_hostname($resolver, $use_resolver, $unresolved_ip['ip_address'], 'gethostbyaddr');

			if ($dns_hostname === $unresolved_ip['ip_address']) {
				mactrack_debug('Unable to resolve IP Address: ' . $unresolved_ip['ip_address']);
			}

			$unresolved_ips[$key]['dns_hostname'] = $dns_hostname;
		}

		mactrack_debug('DNS host association complete.');

		// Write the host name only. The poller fills vendor_mac after this
		// snapshot was read, so writing the snapshot back would clear it,
		// and a row the poller has already moved must not be re-created.
		foreach ($unresolved_ips as $unresolved_ip) {
			db_execute_prepared('UPDATE mac_track_temp_ports
				SET dns_hostname = ?
				WHERE port_number = ?
				AND scan_date = ?
				AND mac_address = ?
				AND device_id = ?',
				[
					$unresolved_ip['dns_hostname'],
					$unresolved_ip['port_number'],
					$unresolved_ip['scan_date'],
					$unresolved_ip['mac_address'],
					$unresolved_ip['device_id'],
				]);
		}

		mactrack_debug('Records updated with DNS information included.');
	}

	if ($break) {
		break;
	}
}

unregister_process('mactrack_resolver', 'master');

// allow parent to close by removing process and then exit
db_process_remove(0);

exit;

/**
 * sig_handler - provides a generic means to catch exceptions to the Cacti log.
 *
 * @param (int) $signo - the signal that was thrown by the interface.
 *
 * @return (void)
 */
function sig_handler($signo) {
	global $config;

	switch ($signo) {
		case SIGTERM:
		case SIGINT:
			cacti_log("WARNING: MacTrack Resolver 'master' is shutting down by signal!", false, 'MACTRACK');

			unregister_process('mactrack_resolver', 'master');

			exit(1);
		default:
			// ignore all other signals
	}
}

/**
 * Prints this script's name, version, and copyright banner to stdout.
 *
 * @return void
 *
 * @global array $config Cacti global configuration array (declared but
 *                       not used directly here).
 */
function display_version(): void {
	global $config;

	$info = plugin_mactrack_version();
	print 'Network Mactrack IP Resolver, Version ' . $info['version'] . ', ' . COPYRIGHT_YEARS . PHP_EOL;
}

/**
 * Prints this script's version banner followed by its command-line
 * usage instructions to stdout.
 *
 * @return void
 */
function display_help(): void {
	display_version();

	print PHP_EOL;
	print 'usage: mactrack_resolver.php [-sid=ID] [-d] [-h] [--help] [-v] [--version]' . PHP_EOL . PHP_EOL;

	print '-sid=ID       - The site id to resolve for' . PHP_EOL;
	print '-d | --debug  - Display verbose output during execution' . PHP_EOL;
	print '-v --version  - Display this help message' . PHP_EOL;
	print '-h --help     - display this help message' . PHP_EOL;
}

/**
 * Whether the resolver may stop: only once the poller has finished, which
 * it shows by removing its row, and no address is left to resolve. Rows the
 * poller adds while it runs are resolved before the resolver stops,
 * because $poller_running is read before the pass.
 *
 * @param bool $poller_running Whether the poller's row was present.
 * @param int  $unresolved     Addresses left to resolve in this pass.
 *
 * @return bool
 */
function mactrack_resolver_idle_stop(bool $poller_running, int $unresolved): bool {
	return !$poller_running && $unresolved === 0;
}
