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

/*
 * What MacTrack is doing right now, for the Process Status page.
 *
 * Long jobs (maintenance, scanning, the port table conversion, the
 * aggregated-ports rebuild) record their current step in Cacti config
 * options, so an administrator can see what is running when the system is
 * slow or after a crash. Nothing here counts rows: row figures come from
 * information_schema estimates, because the port tables can hold tens of
 * millions of rows.
 */

/**
 * Returns the translated label for a recorded activity step.
 *
 * Steps are stored as keys so the stored value does not depend on the
 * language of the process that wrote it.
 *
 * @param string $step Step key.
 *
 * @return string The label, or the key itself when it is not known.
 */
function mactrack_activity_step_label(string $step): string {
	switch ($step) {
		case 'scanning':
			return __('Scanning devices', 'mactrack');
		case 'dns_resolver':
			return __('Waiting for the DNS resolver', 'mactrack');
		case 'transferring':
			return __('Transferring and aggregating scan results', 'mactrack');
		case 'partition_maintenance':
			return __('Maintaining port table partitions', 'mactrack');
		case 'delete_ports':
			return __('Deleting expired port rows', 'mactrack');
		case 'prune_scan_dates':
			return __('Pruning scan dates', 'mactrack');
		case 'delete_ips':
			return __('Deleting expired IP rows', 'mactrack');
		case 'optimize_ips':
			return __('Optimizing the IP table', 'mactrack');
		default:
			return $step;
	}
}

/**
 * Records the step this process is starting.
 *
 * @param string $step   Step key (see mactrack_activity_step_label()).
 * @param array  $detail Optional facts about the step, such as ['devices' => 12].
 *
 * @return void
 */
function mactrack_activity_begin(string $step, array $detail = []): void {
	set_config_option('mt_activity', json_encode([
		'step'    => $step,
		'detail'  => $detail,
		'started' => time(),
		'pid'     => getmypid(),
	]));
}

/**
 * Clears the recorded step.
 *
 * @return void
 */
function mactrack_activity_end(): void {
	set_config_option('mt_activity', '');
}

/**
 * Starts a maintenance step, closing the one still open. The elapsed time of
 * a closed step is kept in $steps, which the caller saves once at the end.
 *
 * @param array  $steps  Step list owned by the caller.
 * @param string $step   Step key.
 * @param array  $detail Optional facts about the step.
 *
 * @return void
 */
function mactrack_maint_step_begin(array &$steps, string $step, array $detail = []): void {
	mactrack_maint_step_end($steps);

	$steps[] = ['step' => $step, 'seconds' => 0.0, 'started' => microtime(true)];

	mactrack_activity_begin($step, $detail);
}

/**
 * Closes the open maintenance step, if any, records its duration and clears
 * the recorded activity. Safe to call when nothing is open.
 *
 * @param array $steps Step list owned by the caller.
 *
 * @return void
 */
function mactrack_maint_step_end(array &$steps): void {
	$last = count($steps) - 1;

	if ($last < 0 || !isset($steps[$last]['started'])) {
		return;
	}

	$steps[$last]['seconds'] = round(microtime(true) - $steps[$last]['started'], 1);

	unset($steps[$last]['started']);

	mactrack_activity_end();
}

/**
 * Stores the durations of the maintenance run's steps for the status page.
 * An empty list leaves the previous run's figures in place.
 *
 * @param array $steps Step list from mactrack_maint_step_begin().
 *
 * @return void
 */
function mactrack_maint_steps_save(array $steps): void {
	$saved = [];

	foreach ($steps as $step) {
		if (!isset($step['started'])) {
			$saved[] = ['step' => $step['step'], 'seconds' => $step['seconds']];
		}
	}

	if (count($saved)) {
		set_config_option('mt_maint_steps_last', json_encode($saved));
	}
}

/**
 * Returns the step durations stored by the last maintenance run.
 *
 * @return array List of ['step' => key, 'seconds' => float].
 */
function mactrack_maint_steps_last(): array {
	$steps = json_decode((string) read_config_option('mt_maint_steps_last', true), true);

	return is_array($steps) ? $steps : [];
}

/**
 * Records why the poller exited without scanning this cycle.
 *
 * @param string $reason 'upgrade' for a running database upgrade, or
 *                       'rebuild_table' for a leftover aggregated-ports work table.
 *
 * @return void
 */
function mactrack_poller_deferred(string $reason): void {
	set_config_option('mt_poller_deferred', json_encode(['reason' => $reason, 'time' => time()]));
}

/**
 * Clears the deferral note once the poller runs normally. Reads before it
 * writes so the usual case costs no write.
 *
 * @return void
 */
function mactrack_poller_deferred_clear(): void {
	if ((string) read_config_option('mt_poller_deferred', true) !== '') {
		set_config_option('mt_poller_deferred', '');
	}
}

/**
 * Tells whether a process still exists. A process owned by another user
 * (the poller's user, seen from the web server's) counts as alive.
 *
 * @param int       $pid       Process id.
 * @param bool|null $has_posix Whether to use posix_kill(); null detects it.
 *
 * @return bool
 */
function mactrack_activity_pid_alive(int $pid, ?bool $has_posix = null): bool {
	if ($pid <= 0) {
		return false;
	}

	if ($has_posix ?? function_exists('posix_kill')) {
		// EPERM (1) means the process exists but belongs to another user.
		return posix_kill($pid, 0) || posix_get_last_error() === 1;
	}

	return file_exists("/proc/$pid");
}

/**
 * Returns the estimated row count of a table in the current database.
 *
 * @param string $table Table name.
 *
 * @return int|null The estimate, or null when the table is not listed.
 */
function mactrack_activity_table_rows_estimate(string $table): ?int {
	global $database_default;

	$rows = db_fetch_cell_prepared('SELECT TABLE_ROWS
		FROM information_schema.TABLES
		WHERE TABLE_SCHEMA = ?
		AND TABLE_NAME = ?',
		[$database_default, $table]);

	return is_numeric($rows) ? (int) $rows : null;
}

/**
 * Works out what MacTrack is doing now, in priority order: the aggregated
 * ports rebuild, the port table conversion, a recorded maintenance or scan
 * step, running scanner processes, then idle.
 *
 * @param int|null $now Current time; null uses time().
 *
 * @return array state: 'upgrading', 'converting', 'activity', 'scanning' or
 *               'idle'; text: what to show; since: start time or null;
 *               detail: extra text or ''; stopped: true when the recorded
 *               process is gone; deferred: null or ['reason' => text, 'time' => int].
 */
function mactrack_activity_state(?int $now = null): array {
	$now   = $now ?? time();
	$state = ['state' => 'idle', 'text' => __('Idle', 'mactrack'), 'since' => null, 'detail' => '', 'stopped' => false, 'deferred' => mactrack_activity_deferred($now)];

	$work_table = 'mac_track_aggregated_ports_upgrade';
	$upgrading  = function_exists('mactrack_background_upgrade_running') && mactrack_background_upgrade_running();
	$leftover   = db_table_exists($work_table, false);

	if ($upgrading || $leftover) {
		$rows = $leftover ? mactrack_activity_table_rows_estimate($work_table) : null;

		return array_merge($state, [
			'state'  => 'upgrading',
			'text'   => __('Upgrading: rebuilding aggregated ports', 'mactrack'),
			'detail' => $rows === null ? '' : __('About %s rows left to copy (estimate)', number_format($rows), 'mactrack'),
		]);
	}

	$converting = (string) read_config_option('mt_partition_converting', true);

	if ($converting !== '') {
		return array_merge($state, [
			'state' => 'converting',
			'text'  => __('Converting the port table to partitions', 'mactrack'),
			'since' => (int) $converting,
		]);
	}

	$activity = json_decode((string) read_config_option('mt_activity', true), true);

	if (is_array($activity) && isset($activity['step'])) {
		$label  = mactrack_activity_step_label((string) $activity['step']);
		$alive  = mactrack_activity_pid_alive((int) ($activity['pid'] ?? 0));
		$detail = '';

		if (isset($activity['detail']['devices'])) {
			$detail = __('%d devices in this scan', (int) $activity['detail']['devices'], 'mactrack');
		}

		return array_merge($state, [
			'state'   => 'activity',
			'text'    => $alive ? $label : __('The process stopped during: %s', $label, 'mactrack'),
			'since'   => (int) ($activity['started'] ?? 0),
			'detail'  => $detail,
			'stopped' => !$alive,
		]);
	}

	$running = db_fetch_row_prepared('SELECT COUNT(*) AS devices, MIN(UNIX_TIMESTAMP(start_date)) AS since
		FROM mac_track_processes
		WHERE device_id > ?',
		[0]);

	if (isset($running['devices']) && (int) $running['devices'] > 0) {
		return array_merge($state, [
			'state' => 'scanning',
			'text'  => __('Scanning %d devices', (int) $running['devices'], 'mactrack'),
			'since' => is_numeric($running['since'] ?? null) ? (int) $running['since'] : null,
		]);
	}

	return $state;
}

/**
 * Returns the poller's deferral note while it is recent: within two
 * collection intervals, so a note from a cycle long past is not shown.
 *
 * @param int $now Current time.
 *
 * @return array|null ['reason' => text, 'time' => int], or null.
 */
function mactrack_activity_deferred(int $now): ?array {
	$note     = json_decode((string) read_config_option('mt_poller_deferred', true), true);
	$timing   = read_config_option('mt_collection_timing', true);
	$interval = is_numeric($timing) ? (int) $timing * 60 : 0;

	if (!is_array($note) || !isset($note['time']) || $interval <= 0 || $now - (int) $note['time'] > 2 * $interval) {
		return null;
	}

	$reason = ($note['reason'] ?? '') === 'upgrade'
		? __('A database upgrade is running', 'mactrack')
		: __('The aggregated ports rebuild has not finished', 'mactrack');

	return ['reason' => $reason, 'time' => (int) $note['time']];
}

/**
 * Returns the size of every MacTrack table in the current database from one
 * information_schema query. Row counts are the storage engine's estimates.
 *
 * @return array Rows of table_name, table_rows (estimate), data_length,
 *               index_length, data_free and total (data plus index), largest first.
 */
function mactrack_table_sizes(): array {
	global $database_default;

	$tables = db_fetch_assoc_prepared('SELECT TABLE_NAME AS table_name,
		TABLE_ROWS AS table_rows,
		DATA_LENGTH AS data_length,
		INDEX_LENGTH AS index_length,
		DATA_FREE AS data_free
		FROM information_schema.TABLES
		WHERE TABLE_SCHEMA = ?
		AND TABLE_NAME LIKE ?',
		[$database_default, 'mac\_track\_%']);

	$tables = is_array($tables) ? $tables : [];

	foreach ($tables as $key => $table) {
		$tables[$key]['total'] = (int) $table['data_length'] + (int) $table['index_length'];
	}

	usort($tables, static function (array $a, array $b): int {
		return $b['total'] <=> $a['total'];
	});

	return $tables;
}

/**
 * Reports free disk space where the database stores its data. PHP can only
 * look at a disk on its own host, so a database on another host is unknown.
 *
 * @return array bytes: free bytes or null; reason: '' when known, otherwise
 *               'remote' (database on another host) or 'unreadable'.
 */
function mactrack_database_disk_free(): array {
	global $database_hostname;

	if (!in_array((string) $database_hostname, ['', 'localhost', '127.0.0.1', '::1'], true)) {
		return ['bytes' => null, 'reason' => 'remote'];
	}

	$datadir = db_fetch_cell_prepared('SELECT @@datadir', []);

	// The data directory is normally mode 0750 and owned by the database
	// user; free space needs only a path the web user can reach, not read.
	if (!is_string($datadir) || $datadir === '' || !is_dir($datadir)) {
		return ['bytes' => null, 'reason' => 'unreadable'];
	}

	$free = @disk_free_space($datadir);

	return $free === false ? ['bytes' => null, 'reason' => 'unreadable'] : ['bytes' => (int) $free, 'reason' => ''];
}

/**
 * Formats a byte count as a human-readable size.
 *
 * @param mixed $bytes Byte count.
 *
 * @return string
 */
function mactrack_activity_bytes($bytes): string {
	$bytes = (float) $bytes;
	$units = ['B', 'KB', 'MB', 'GB', 'TB'];
	$i     = 0;

	while ($bytes >= 1024 && $i < count($units) - 1) {
		$bytes /= 1024;
		$i++;
	}

	return round($bytes, $i < 2 ? 0 : 1) . ' ' . $units[$i];
}
