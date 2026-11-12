#!/usr/bin/env php
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
 * cli/upgrade_database.php
 *
 * Runs the MacTrack database upgrade in the background. The upgrade rebuilds
 * indexes and rewrites the (potentially very large) mac_track_aggregated_ports
 * table, which takes far longer than a web request is allowed to run, so the
 * plugin launches this script with exec_background() instead of upgrading
 * inline. register_process_start() keeps two copies from running at once, and
 * the signal handlers let an operator or the poller stop it cleanly; a stopped
 * run simply resumes on the next launch.
 */

if (function_exists('pcntl_async_signals')) {
	pcntl_async_signals(true);
} else {
	declare(ticks = 100);
}

ini_set('output_buffering', 'Off');
ini_set('max_execution_time', '0');
ini_set('memory_limit', '-1');
set_time_limit(0);

chdir(__DIR__);
chdir('../../..');

include('./include/cli_check.php');

global $config;

include_once($config['base_path'] . '/lib/poller.php');
include_once($config['base_path'] . '/plugins/mactrack/setup.php');
include_once($config['base_path'] . '/plugins/mactrack/includes/database.php');
include_once($config['base_path'] . '/plugins/mactrack/lib/mactrack_functions.php');

// install signal handlers for UNIX only
if (function_exists('pcntl_signal')) {
	pcntl_signal(SIGTERM, 'sig_handler');
	pcntl_signal(SIGINT, 'sig_handler');
}

global $debug;

$debug = false;
$force = false;

// process calling arguments
$parms = $_SERVER['argv'];
array_shift($parms);

if (cacti_sizeof($parms)) {
	foreach ($parms as $parameter) {
		if (strpos($parameter, '=')) {
			[$arg, $value] = explode('=', $parameter, 2);
		} else {
			$arg   = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '-d':
			case '--debug':
				$debug = true;

				break;
			case '-f':
			case '--force':
				$force = true;

				break;
			case '-v':
			case '-V':
			case '--version':
				display_version();

				exit(0);
			case '-h':
			case '-H':
			case '--help':
				display_help();

				exit(0);

			default:
				print 'ERROR: Invalid Parameter ' . $parameter . PHP_EOL . PHP_EOL;
				display_help();

				exit(1);
		}
	}
}

// Prevent two upgrades from colliding on the same ALTERs. The 24h timeout
// covers the slowest installs; a run that crashes past it is taken over on the
// next launch. --force bypasses the guard for a manual re-run.
if (!$force) {
	if (!register_process_start('mtupgrade', 'database', 0, 86400)) {
		upgrade_debug('A MacTrack database upgrade is already running; exiting');

		exit(0);
	}
}

$start = microtime(true);

upgrade_debug('Starting the MacTrack background database upgrade');

$completed = mactrack_perform_upgrade();

$end = microtime(true);

if ($completed) {
	// Clear the operator notice flag so a later upgrade can raise it again. The
	// web hook stops relaunching this CLI once plugin_config records the new
	// version in mactrack_perform_upgrade().
	set_config_option('mt_db_upgrade_notified', 'off');

	if (!$force) {
		unregister_process('mtupgrade', 'database', 0);
	}

	upgrade_debug(sprintf('Finished the MacTrack background database upgrade in %0.2f seconds', $end - $start));

	exit(0);
}

// The upgrade did not finish (for example a large rebuild could not be
// completed this pass). Leave the operator notice and the recorded version
// untouched so the web hook relaunches us, and report failure.
if (!$force) {
	unregister_process('mtupgrade', 'database', 0);
}

cacti_log('WARNING: The MacTrack background database upgrade did not complete and will be retried', false, 'MACTRACK');

upgrade_debug(sprintf('MacTrack background database upgrade did not complete after %0.2f seconds; it will be retried', $end - $start));

exit(1);

/**
 * sig_handler - cleanly stops the upgrade on SIGTERM/SIGINT. The per-scan-date
 * rebuild commits each batch on its own, so an interrupted run leaves a
 * consistent, resumable state; the next launch continues where this one left
 * off.
 *
 * @param int $signo The signal that was delivered.
 *
 * @return void
 */
function sig_handler($signo) {
	switch ($signo) {
		case SIGTERM:
		case SIGINT:
			cacti_log('WARNING: The MacTrack background database upgrade was terminated by a signal; it will resume on the next launch', false, 'MACTRACK');

			unregister_process('mtupgrade', 'database', 0, (int) getmypid());

			exit(1);

		default:
			break;
	}
}

/**
 * upgrade_debug - logs an upgrade progress message to the Cacti log, and to the
 * console when --debug is set.
 *
 * @param string $message The message to record.
 *
 * @return void
 */
function upgrade_debug($message) {
	global $debug;

	if ($debug) {
		print 'DEBUG: ' . $message . PHP_EOL;
	}

	cacti_log('MACTRACK: ' . $message, false, 'MACTRACK', ($debug ? POLLER_VERBOSITY_NONE : POLLER_VERBOSITY_MEDIUM));
}

/**
 * display_version - prints the plugin version and copyright.
 *
 * @return void
 */
function display_version() {
	$version = plugin_mactrack_version();

	print 'MacTrack Background Database Upgrade, Version ' . $version['version'] . ', ' . COPYRIGHT_YEARS . PHP_EOL;
}

/**
 * display_help - prints the command-line usage for this script.
 *
 * @return void
 */
function display_help() {
	display_version();

	print PHP_EOL . 'usage: upgrade_database.php [--debug] [--force]' . PHP_EOL . PHP_EOL;
	print 'Runs the MacTrack database upgrade in the background. It is normally launched' . PHP_EOL;
	print 'automatically when a console page detects a pending upgrade, but it can also be' . PHP_EOL;
	print 'run by hand.' . PHP_EOL . PHP_EOL;
	print '    --debug  - Display verbose progress on the console.' . PHP_EOL;
	print '    --force  - Run even if another upgrade appears to be registered.' . PHP_EOL;
}
