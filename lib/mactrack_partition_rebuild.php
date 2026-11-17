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
 * Shared support for the scan-date partitioning of mac_track_ports and
 * mac_track_ips. The rebuild itself lives in cli/upgrade_partitioning.php:
 * it renames each table to <table>_rebuild, creates the table again with the
 * chosen layout and copies the history back one scan date at a time. These
 * helpers tell the poller, maintenance and the views whether such a rebuild
 * is running or was left unfinished.
 */

/**
 * Suffix of the table a rebuild renames the original table to.
 */
const MACTRACK_PARTITION_REBUILD_SUFFIX = '_rebuild';

/**
 * The process registry entry (tasktype, taskname) of a running rebuild.
 */
const MACTRACK_PARTITION_REBUILD_PROCESS = ['mtpartition', 'convert'];

/**
 * Tells whether the server can partition tables.
 *
 * @param array{mariadb: bool, version: string} $server From mactrack_partition_server().
 *
 * @return bool
 */
function mactrack_partition_available(array $server): bool {
	// Partitioning is part of InnoDB from MySQL 8.0 and has no plugin row.
	if (!$server['mariadb'] && version_compare($server['version'], '8.0.0', '>=')) {
		return true;
	}

	return strtoupper((string) db_fetch_cell_prepared('SELECT PLUGIN_STATUS
		FROM information_schema.PLUGINS
		WHERE PLUGIN_NAME = ?',
		['partition'])) === 'ACTIVE';
}

/**
 * Partitions a newly created, empty scan-date table into this UTC week
 * and the next, so the catch-all stays empty during normal collection.
 * Existing tables are never converted here; that is
 * cli/upgrade_partitioning.php, run by an operator.
 *
 * @param int|null $now   Current time, for tests.
 * @param string   $table mac_track_ports or mac_track_ips.
 *
 * @return bool True when the table was partitioned; false leaves it
 *              unpartitioned, which MacTrack fully supports.
 */
function mactrack_partition_fresh_install(?int $now = null, string $table = 'mac_track_ports'): bool {
	mactrack_partition_assert_table($table);

	try {
		$server = mactrack_partition_server((string) db_fetch_cell_prepared('SELECT VERSION()'));
	} catch (InvalidArgumentException $e) {
		return false;
	}

	if (!mactrack_partition_available($server)) {
		return false;
	}

	$bounds = [];
	$period = mactrack_partition_period('week', $now ?? time());

	for ($i = 0; $i < 2; $i++) {
		$bounds[mactrack_partition_name('week', $period['start'])] = $period['bound'];
		$period                                                    = mactrack_partition_period('week', $period['bound']);
	}

	if (!mactrack_partition_ddl('ALTER TABLE `' . $table . '` ' . mactrack_partition_create_sql($bounds))) {
		return false;
	}

	set_config_option('mt_partition_granularity', 'week');

	return true;
}

/**
 * Names the conversion scheme a layout already has: disabled for an
 * unpartitioned table, day or week when every bounded partition of the
 * scan-date layout is daily or weekly, otherwise null.
 *
 * @param array{state: string, partitions: array} $layout From mactrack_partition_layout().
 *
 * @return string|null
 */
function mactrack_partition_current_scheme(array $layout): ?string {
	if ($layout['state'] === 'none') {
		return 'disabled';
	}

	if ($layout['state'] !== 'range') {
		return null;
	}

	$prefixes = array_unique(array_map(fn ($p) => $p['name'][0], array_filter($layout['partitions'], fn ($p) => $p['bound'] !== null)));

	return ['d' => 'day', 'w' => 'week'][count($prefixes) === 1 ? reset($prefixes) : ''] ?? null;
}

/**
 * Names the table a rebuild moves a table's original rows to.
 *
 * @param string $table mac_track_ports or mac_track_ips.
 *
 * @return string
 */
function mactrack_partition_rebuild_table(string $table): string {
	mactrack_partition_assert_table($table);

	return $table . MACTRACK_PARTITION_REBUILD_SUFFIX;
}

/**
 * Tells whether a table exists in the current database. Cacti's
 * db_table_exists() remembers its answers for the life of the process, which
 * is wrong for tables a rebuild renames, creates and drops.
 *
 * @param string $table Table name.
 *
 * @return bool
 */
function mactrack_partition_table_exists(string $table): bool {
	global $database_default;

	return (int) db_fetch_cell_prepared('SELECT COUNT(*)
		FROM information_schema.TABLES
		WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
		[$database_default, $table]) > 0;
}

/**
 * Lists the partitioned tables whose rebuild table exists, meaning a
 * rebuild is running or stopped before it finished.
 *
 * @return string[]
 */
function mactrack_partition_rebuild_pending(): array {
	$pending = [];

	foreach (MACTRACK_PARTITION_TABLES as $table) {
		if (mactrack_partition_table_exists(mactrack_partition_rebuild_table($table))) {
			$pending[] = $table;
		}
	}

	return $pending;
}

/**
 * Reports whether a partitioning rebuild is under way. A converting flag
 * left by a run that died without clearing it is cleared here.
 *
 * @return string running while cli/upgrade_partitioning.php is registered
 *                and alive; interrupted when it left rebuild tables behind
 *                and is gone; otherwise an empty string.
 */
function mactrack_partition_rebuild_state(): string {
	// Defines mactrack_registered_process_running().
	include_once(__DIR__ . '/../includes/database.php');

	if (mactrack_registered_process_running(...MACTRACK_PARTITION_REBUILD_PROCESS)) {
		return 'running';
	}

	if ((string) read_config_option('mt_partition_converting', true) !== '') {
		set_config_option('mt_partition_converting', '');
	}

	return mactrack_partition_rebuild_pending() === [] ? '' : 'interrupted';
}

/**
 * Prints the maintenance-mode notice that the IP address and MAC address
 * views show while the port and IP tables are being repartitioned.
 *
 * @return void
 */
function mactrack_partition_rebuild_notice(): void {
	$state = mactrack_partition_rebuild_state();

	if ($state === '') {
		return;
	}

	if ($state === 'running') {
		$message = __('MacTrack is in maintenance mode for IP address and MAC address searching while its port and IP tables are repartitioned. Results are incomplete until the partitioning has finished.', 'mactrack');
	} else {
		$message = __('MacTrack is in maintenance mode for IP address and MAC address searching because the partitioning of its port and IP tables did not finish. Results are incomplete. Run cli/upgrade_partitioning.php again to resume it.', 'mactrack');
	}

	html_start_box(__('Maintenance Mode', 'mactrack'), '100%', '', '3', 'center', '');
	print '<tr class="even"><td class="textError">' . html_escape($message) . '</td></tr>';
	html_end_box();
}

/**
 * Returns how many days of history a table keeps. The retention settings
 * hold a number of days; older releases stored names such as 2weeks.
 *
 * @param string $table mac_track_ports or mac_track_ips.
 *
 * @return int Days; 0 keeps everything.
 */
function mactrack_partition_retention_days(string $table): int {
	mactrack_partition_assert_table($table);

	$value = read_config_option($table === 'mac_track_ips' ? 'mt_data_retention_ip' : 'mt_data_retention');

	if (is_numeric($value)) {
		return max(0, (int) $value);
	}

	$names = ['2days' => 2, '1week' => 7, '2weeks' => 14, '3weeks' => 21, '1month' => 30, '2months' => 60];

	return $names[(string) $value] ?? 2;
}
