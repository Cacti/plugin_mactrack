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
 * cli/upgrade_partitioning.php
 *
 * Checks how mac_track_ports (MAC addresses) and mac_track_ips (IP
 * addresses) are partitioned against the selected scheme and, when they have
 * drifted from it or the scheme is being changed, rebuilds them one after the
 * other:
 *
 *  1. the table is renamed to <table>_rebuild;
 *  2. the table is created again with the retention and partitioning scheme;
 *  3. each distinct scan date is copied from the renamed table into it;
 *  4. once every scan date is copied, the renamed table is dropped.
 *
 * Scanning is paused for the whole run (the scan lock), the run is registered
 * in the process registry so two copies cannot overlap, and a run that stops
 * early resumes from the scan date it reached. Nothing is deleted from the
 * renamed table until its rows are verified in the new one.
 */

/**
 * Seconds the rebuild may run before another run may take over its process
 * registration: well over the four hours a large history needs.
 */
const MACTRACK_UPART_TIMEOUT = 86400;

/**
 * Seconds between the progress lines printed outside --debug.
 */
const MACTRACK_UPART_PROGRESS = 30;

// The functions below are also loaded by the tests, which must not run the CLI.
// @codeCoverageIgnoreStart
if (mactrack_upart_invoked()) {
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
		pcntl_signal(SIGTERM, 'mactrack_upart_signal');
		pcntl_signal(SIGINT, 'mactrack_upart_signal');
	}

	exit(mactrack_upart_main($_SERVER['argv']));
}
// @codeCoverageIgnoreEnd

/**
 * Tells whether this file is the script PHP was started with.
 *
 * @return bool
 */
function mactrack_upart_invoked(): bool {
	return PHP_SAPI === 'cli'
		&& isset($_SERVER['argv'][0])
		&& realpath((string) $_SERVER['argv'][0]) === realpath(__FILE__);
}

/**
 * Prints one line of output.
 *
 * @param string $line The line, without a line ending.
 *
 * @return void
 */
function mactrack_upart_out(string $line): void {
	print $line . PHP_EOL;
}

/**
 * Formats a number of seconds as H:MM:SS.
 *
 * @param int $seconds Duration.
 *
 * @return string
 */
function mactrack_upart_duration(int $seconds): string {
	$seconds = max(0, $seconds);

	return sprintf('%d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
}

/**
 * Reads the command line. The action is run, help or version; the scheme is
 * day, week or disabled, or null to use the saved setting.
 *
 * @param array<int, string> $argv The script's arguments, program name first.
 *
 * @return array{action: string, scheme: string|null, debug: bool, error: string}
 */
function mactrack_upart_parse(array $argv): array {
	$options = ['action' => 'run', 'scheme' => null, 'debug' => false, 'error' => ''];

	array_shift($argv);

	foreach ($argv as $parameter) {
		[$arg, $value] = array_pad(explode('=', (string) $parameter, 2), 2, '');

		switch ($arg) {
			case '--partition':
				$schemes = ['daily' => 'day', 'weekly' => 'week', 'disabled' => 'disabled'];

				if (!isset($schemes[$value])) {
					$options['error'] = 'ERROR: --partition must be daily, weekly or disabled.';

					return $options;
				}

				$options['scheme'] = $schemes[$value];

				break;
			case '--debug':
				$options['debug'] = true;

				break;
			case '--version':
			case '-V':
			case '-v':
				$options['action'] = 'version';

				break;
			case '--help':
			case '-H':
			case '-h':
				$options['action'] = 'help';

				break;
			default:
				$options['error'] = 'ERROR: Invalid Parameter ' . $parameter;

				return $options;
		}
	}

	return $options;
}

/**
 * Prints the plugin version and copyright.
 *
 * @return void
 */
function display_version(): void {
	$info = plugin_mactrack_version();

	mactrack_upart_out('Mactrack Upgrade Partitioning, Version ' . $info['version'] . ', ' . COPYRIGHT_YEARS);
}

/**
 * Prints the version banner followed by the usage text.
 *
 * @return void
 */
function display_help(): void {
	display_version();

	mactrack_upart_out('');
	mactrack_upart_out('usage: upgrade_partitioning.php [--partition=daily|weekly|disabled] [--debug] [--version] [--help]');
	mactrack_upart_out('');
	mactrack_upart_out('Checks how the MAC address (mac_track_ports) and IP address (mac_track_ips)');
	mactrack_upart_out('tables are partitioned. Without --partition, the Port and IP Address Partition Size setting');
	mactrack_upart_out('is the scheme; a table that already follows it is left alone and reported.');
	mactrack_upart_out('A table that has drifted from the scheme, or is not partitioned as asked, is');
	mactrack_upart_out('rebuilt: renamed aside, created again with the retention and partitioning');
	mactrack_upart_out('scheme, copied back one scan date at a time and then dropped. The tables are');
	mactrack_upart_out('rebuilt one after the other, and a run that stops resumes when started again.');
	mactrack_upart_out('');
	mactrack_upart_out('Scans are paused and MAC and IP address searching shows a maintenance message');
	mactrack_upart_out('until it finishes, which can take hours on a large history. Run it off hours.');
	mactrack_upart_out('');
	mactrack_upart_out('--partition=SCHEME - Rebuild to daily, weekly or disabled (unpartitioned) and');
	mactrack_upart_out('                     save it as the Port and IP Address Partition Size setting');
	mactrack_upart_out('--debug            - Report every scan date copied');
	mactrack_upart_out('--version          - Display the version');
	mactrack_upart_out('--help             - Display this help message');
}

// @codeCoverageIgnoreStart
/**
 * Cleanly stops the rebuild on SIGTERM/SIGINT. The tables stay in the state
 * they were in; the next run resumes from there.
 *
 * @param int $signo The signal that was delivered.
 *
 * @return void
 */
function mactrack_upart_signal(int $signo): void {
	cacti_log('WARNING: The MacTrack partitioning rebuild was terminated by a signal; run it again to resume', false, 'MACTRACK');

	mactrack_upart_out('WARNING: Interrupted. Run cli/upgrade_partitioning.php again to resume.');

	set_config_option('mt_partition_converting', '');
	unregister_process(MACTRACK_PARTITION_REBUILD_PROCESS[0], MACTRACK_PARTITION_REBUILD_PROCESS[1], 0, (int) getmypid());

	exit(1);
}
// @codeCoverageIgnoreEnd

/**
 * Maps the Port and IP Address Partition Size setting to a rebuild scheme.
 *
 * @return string|null day, week or disabled; null for the per-scan and hourly
 *                     sizes, which the poller maintains and this tool cannot build.
 */
function mactrack_upart_setting_scheme(): ?string {
	$value = (string) read_config_option('mt_partition_granularity');

	return ['' => 'week', 'day' => 'day', 'week' => 'week', 'disabled' => 'disabled'][$value] ?? null;
}

/**
 * Names a scheme the way the command line and the messages do.
 *
 * @param string $scheme day, week or disabled.
 *
 * @return string
 */
function mactrack_upart_scheme_name(string $scheme): string {
	return ['day' => 'daily', 'week' => 'weekly', 'disabled' => 'disabled'][$scheme] ?? $scheme;
}

/**
 * Runs the tool.
 *
 * @param array<int, string> $argv The script's arguments, program name first.
 *
 * @return int Exit status.
 */
function mactrack_upart_main(array $argv): int {
	global $debug;

	$options = mactrack_upart_parse($argv);
	$debug   = $options['debug'];

	if ($options['error'] !== '') {
		mactrack_upart_out($options['error']);
		mactrack_upart_out('');
		display_help();

		return 1;
	}

	if ($options['action'] === 'version') {
		display_version();

		return 0;
	}

	if ($options['action'] === 'help') {
		display_help();

		return 0;
	}

	$scheme = $options['scheme'] ?? mactrack_upart_setting_scheme();

	if ($scheme === null) {
		mactrack_upart_out('NOTE: The Port and IP Address Partition Size setting is one per scan or hourly, which the poller maintains and this tool does not build. Nothing was changed; use --partition to choose daily, weekly or disabled.');

		return 0;
	}

	// Two runs must not overlap: the registration is refused while another
	// run is alive and is taken over from one that died or timed out.
	if (!register_process_start(MACTRACK_PARTITION_REBUILD_PROCESS[0], MACTRACK_PARTITION_REBUILD_PROCESS[1], 0, MACTRACK_UPART_TIMEOUT)) {
		mactrack_upart_out('NOTE: A MacTrack partitioning rebuild is already running; exiting.');

		return 0;
	}

	try {
		return mactrack_upart_run($scheme, $options['scheme'] !== null);
	} finally {
		unregister_process(MACTRACK_PARTITION_REBUILD_PROCESS[0], MACTRACK_PARTITION_REBUILD_PROCESS[1], 0);
	}
}

/**
 * Tells whether a MacTrack database upgrade is running or left its work
 * table behind. The upgrade rewrites mac_track_ports rows in place, which a
 * copy would miss.
 *
 * @return bool
 */
function mactrack_upart_upgrade_active(): bool {
	return mactrack_background_upgrade_running() || mactrack_partition_table_exists('mac_track_aggregated_ports_upgrade');
}

/**
 * Checks both tables against the scheme and rebuilds the ones that need it.
 *
 * @param string $scheme   day, week or disabled.
 * @param bool   $explicit True when --partition chose it, so it is saved as
 *                         the Port and IP Address Partition Size setting.
 * @param int    $pause    Seconds between DDL retries; tests pass 0.
 *
 * @return int Exit status.
 */
function mactrack_upart_run(string $scheme, bool $explicit, int $pause = 1): int {
	$server = mactrack_partition_state()['server'];

	if ($scheme !== 'disabled' && !mactrack_partition_available($server)) {
		mactrack_upart_out('FATAL: This database server does not support partitioning.');

		return 1;
	}

	// The upgrade rewrites mac_track_ports rows in place, which a copy would miss.
	if (mactrack_upart_upgrade_active()) {
		mactrack_upart_out('FATAL: A MacTrack database upgrade is running. Run this again when it has finished.');

		return 1;
	}

	mactrack_upart_out('NOTE: Checking ' . implode(' and ', MACTRACK_PARTITION_TABLES) . ' against the ' . mactrack_upart_scheme_name($scheme) . ' partitioning scheme');

	$assessed = mactrack_upart_assess_all($scheme);

	if ($explicit) {
		mactrack_upart_save_setting($scheme);
	}

	$todo = array_keys(array_filter($assessed, fn ($a) => in_array($a['action'], ['rebuild', 'resume'], true)));

	if ($todo === []) {
		mactrack_upart_out('NOTE: No partitioning drift found; nothing was changed.');

		return 0;
	}

	// The scan lock pauses scanning for the whole rebuild; the partition lock
	// keeps maintenance out. Both are released by the server if this dies.
	if (!mactrack_partition_scan_begin()) {
		mactrack_upart_out('FATAL: A scan is running. Run this again when it has finished.');

		return 1;
	}

	try {
		if (!mactrack_partition_lock()) {
			mactrack_upart_out('FATAL: Partition maintenance is running. Run this again when it has finished.');

			return 1;
		}

		try {
			return mactrack_upart_rebuild_all($todo, $scheme, $pause);
		} finally {
			mactrack_partition_unlock();
		}
	} finally {
		set_config_option('mt_partition_converting', '');
		mactrack_partition_scan_end();
	}
}

/**
 * Saves the scheme as the Port and IP Address Partition Size setting.
 *
 * @param string $scheme day, week or disabled.
 *
 * @return void
 */
function mactrack_upart_save_setting(string $scheme): void {
	if (mactrack_upart_setting_scheme() !== $scheme) {
		mactrack_upart_out('NOTE: Saving the Port and IP Address Partition Size setting as ' . mactrack_upart_scheme_name($scheme) . '.');
	}

	set_config_option('mt_partition_granularity', $scheme);
}

/**
 * Checks each table against the scheme and prints what it found.
 *
 * @param string $scheme day, week or disabled.
 *
 * @return array<string, array{action: string, reason: string}> By table.
 */
function mactrack_upart_assess_all(string $scheme): array {
	$assessed = [];

	foreach (MACTRACK_PARTITION_TABLES as $table) {
		$assessed[$table] = mactrack_upart_assess($table, $scheme);

		mactrack_upart_out(($assessed[$table]['action'] === 'ok' ? 'NOTE: ' : 'WARNING: ') . $table . ': ' . $assessed[$table]['reason']);
	}

	return $assessed;
}

/**
 * Compares one table with the scheme. The action is ok when the table already
 * follows the scheme, rebuild or resume when it must be rebuilt, and missing
 * when the table does not exist.
 *
 * @param string $table  mac_track_ports or mac_track_ips.
 * @param string $scheme day, week or disabled.
 *
 * @return array{action: string, reason: string}
 */
function mactrack_upart_assess(string $table, string $scheme): array {
	if (mactrack_partition_table_exists(mactrack_partition_rebuild_table($table))) {
		return ['action' => 'resume', 'reason' => 'an earlier rebuild did not finish and will resume'];
	}

	if (!mactrack_partition_table_exists($table)) {
		return ['action' => 'missing', 'reason' => 'the table does not exist'];
	}

	$layout  = mactrack_partition_state($table)['layout'];
	$name    = mactrack_upart_scheme_name($scheme);
	$current = mactrack_partition_current_scheme($layout);

	if ($scheme === 'disabled') {
		return $layout['state'] === 'none'
			? ['action' => 'ok', 'reason' => 'not partitioned, as selected; no drift']
			: ['action' => 'rebuild', 'reason' => 'partitioned (' . $layout['state'] . ') but the selection is disabled'];
	}

	switch ($layout['state']) {
		case 'none':
			return ['action' => 'rebuild', 'reason' => "not partitioned; the selection is $name"];
		case 'legacy':
			return ['action' => 'rebuild', 'reason' => "uses the earlier daily layout; the selection is $name"];
		case 'invalid':
			return ['action' => 'rebuild', 'reason' => 'has partitions MacTrack does not recognize (' . $layout['reason'] . ')'];
	}

	if ($current !== $scheme) {
		$prefixes = implode(', ', array_unique(array_map(fn ($p) => $p['name'][0], array_filter($layout['partitions'], fn ($p) => $p['bound'] !== null))));

		return ['action' => 'rebuild', 'reason' => "partitions are not all $name (name prefixes: $prefixes)"];
	}

	$stranded = db_fetch_cell_prepared('SELECT COUNT(*) FROM (SELECT 1 FROM `' . $table . '` PARTITION (`dMaxValue`) LIMIT 1) AS used');

	if ((int) $stranded > 0) {
		return ['action' => 'rebuild', 'reason' => 'rows sit in dMaxValue, outside any partition'];
	}

	$rows = array_sum(array_column($layout['partitions'], 'rows'));

	return ['action' => 'ok', 'reason' => sprintf('%s with %d partitions and about %s rows; no drift', $name, count($layout['partitions']), number_format($rows))];
}

/**
 * Rebuilds the tables one after the other and records the outcome.
 *
 * @param string[] $tables Tables to rebuild, in order.
 * @param string   $scheme day, week or disabled.
 * @param int      $pause  Seconds between DDL retries.
 *
 * @return int Exit status.
 */
function mactrack_upart_rebuild_all(array $tables, string $scheme, int $pause): int {
	// A database upgrade may have started since the first check, and it holds
	// no lock this script waits for; one that rewrites rows now would be
	// missed by the copy.
	if (mactrack_upart_upgrade_active()) {
		mactrack_upart_out('FATAL: A MacTrack database upgrade is running. Run this again when it has finished.');

		return 1;
	}

	if (in_array('mac_track_ports', $tables, true) && (string) read_config_option('mt_partition_pending', true) !== '') {
		mactrack_upart_out('FATAL: A partition exchange is pending. Let the poller finish loading it, then run this again.');

		return 1;
	}

	$started = time();

	set_config_option('mt_partition_converting', (string) $started);

	mactrack_upart_out('WARNING: Scanning is paused and MAC and IP address searching is in maintenance mode until this finishes.');
	mactrack_upart_out('WARNING: Allow disk space for a second copy of each table and its indexes; this can take hours.');

	$total  = ['ok' => true, 'reason' => '', 'rows' => 0, 'partitions' => 0, 'granularity' => $scheme];
	$report = function (string $table, int $done, int $count, string $scan_date, int $rows) use ($started): void {
		static $last = [];

		$now = time();

		if (!empty($GLOBALS['debug'])) {
			mactrack_upart_out("DEBUG: $table: copied $scan_date ($done of $count), $rows rows");

			return;
		}

		if ($done < $count && $now - ($last[$table] ?? $started) < MACTRACK_UPART_PROGRESS) {
			return;
		}

		$last[$table] = $now;

		mactrack_upart_out(sprintf('NOTE: %s: copied %s of %s scan dates (%d%%), %s rows, %s elapsed', $table, number_format($done), number_format($count), $count > 0 ? (int) floor(100 * $done / $count) : 100, number_format($rows), mactrack_upart_duration($now - $started)));
	};

	foreach ($tables as $table) {
		mactrack_upart_out("NOTE: Rebuilding $table (" . mactrack_upart_scheme_name($scheme) . ')');

		$result = mactrack_upart_rebuild($table, $scheme, $report, $pause);

		if (!$result['ok']) {
			$total['ok']     = false;
			$total['reason'] = "$table: " . $result['reason'];

			mactrack_upart_out('FATAL: ' . $total['reason']);
			mactrack_upart_out("FATAL: $table was left as it is; run this again to resume.");

			break;
		}

		$total['rows']       += $result['rows'];
		$total['partitions'] += $result['partitions'];

		mactrack_upart_out(sprintf('NOTE: %s rebuilt: %s rows, %d partitions, %s elapsed', $table, number_format($result['rows']), $result['partitions'], mactrack_upart_duration(time() - $started)));
	}

	mactrack_partition_status(['conversion' => $total + ['time' => time()]]);

	if ($total['ok']) {
		mactrack_upart_out('NOTE: Partitioning is complete. Scanning and searching are back to normal.');
	}

	return $total['ok'] ? 0 : 1;
}

/**
 * Tells whether a table is partitioned.
 *
 * @param string $table Table name.
 *
 * @return bool
 */
function mactrack_upart_is_partitioned(string $table): bool {
	global $database_default;

	return (int) db_fetch_cell_prepared('SELECT COUNT(*)
		FROM information_schema.PARTITIONS
		WHERE TABLE_SCHEMA = ?
		AND TABLE_NAME = ?
		AND PARTITION_NAME IS NOT NULL',
		[$database_default, $table]) > 0;
}

/**
 * Plans the partitions of a rebuilt table: complete UTC periods from the one
 * holding the start of the retention window through the partitions
 * maintenance keeps created ahead. Older scan dates are copied into the first
 * partition, which retention drops once it has expired, so no history is lost
 * to the rebuild and a long history costs no extra partitions.
 *
 * @param array<int, int> $epochs         Scan dates as epoch seconds; zero dates are ignored.
 * @param string          $scheme         day or week.
 * @param int             $retention_days Days kept; 0 keeps everything.
 * @param int             $cap            Partition limit including dMaxValue.
 * @param int             $now            Current epoch seconds.
 *
 * @return array{bounds: array<string, int>, reason: string}
 */
function mactrack_upart_plan(array $epochs, string $scheme, int $retention_days, int $cap, int $now): array {
	$epochs = array_values(array_filter(array_map('intval', $epochs), fn ($e) => $e > 0));
	$oldest = $epochs === [] ? $now : min($epochs);
	$first  = $retention_days > 0 ? max($oldest, $now - $retention_days * 86400) : $oldest;
	$first  = min($first, $now);
	$last   = max(array_merge($epochs, [$now + MACTRACK_PARTITION_AHEAD]));
	$bounds = [];

	for ($ts = $first; $ts <= $last; $ts = $period['bound']) {
		$period                                        = mactrack_partition_period($scheme, $ts);
		$bounds[mactrack_partition_name($scheme, $ts)] = $period['bound'];

		if (count($bounds) + 1 > $cap) {
			return ['bounds' => [], 'reason' => sprintf('The history needs more than the limit of %d partitions. Select weekly partitioning or lower the retention and run maintenance first.', $cap)];
		}
	}

	return ['bounds' => $bounds, 'reason' => ''];
}

/**
 * Rebuilds one table: renames it aside, creates it again with the scheme,
 * copies each scan date back and drops the renamed table. A table whose
 * rebuild table already exists resumes: the partly filled new table is kept
 * when it already has the scheme, and the copy restarts from the newest scan
 * date it holds.
 *
 * @param string        $table    mac_track_ports or mac_track_ips.
 * @param string        $scheme   day, week or disabled.
 * @param callable|null $progress Called as ($table, $done, $total, $scan_date, $rows) after each scan date.
 * @param int           $pause    Seconds between DDL retries; the rename waits twice as long.
 * @param int|null      $now      Current time, for tests.
 *
 * @return array{ok: bool, reason: string, rows: int, partitions: int, granularity: string}
 */
function mactrack_upart_rebuild(string $table, string $scheme, ?callable $progress = null, int $pause = 1, ?int $now = null): array {
	global $database_default;

	mactrack_partition_assert_table($table);

	$now    = $now ?? time();
	$src    = mactrack_partition_rebuild_table($table);
	$result = ['ok' => false, 'reason' => '', 'rows' => 0, 'partitions' => 0, 'granularity' => $scheme];
	$resume = mactrack_partition_table_exists($src);

	// 1. The original table moves aside. Readers of the live name see no rows
	// until the new table is created, which the maintenance message covers.
	if (!$resume) {
		if (!mactrack_partition_table_exists($table)) {
			return ['reason' => 'The table does not exist.'] + $result;
		}

		// The swap waits for readers of the table, so it gets more tries than other DDL.
		if (!mactrack_partition_ddl("RENAME TABLE `$table` TO `$src`", 30, 2 * $pause)) {
			return ['reason' => 'Could not rename the table; readers kept it busy.'] + $result;
		}

		cacti_log("MACTRACK: Renamed $table to $src for the partitioning rebuild", false, 'MACTRACK');
	} else {
		cacti_log("MACTRACK: Resuming the $table partitioning rebuild", false, 'MACTRACK');
		mactrack_upart_out("NOTE: $table: resuming an earlier rebuild");
	}

	$type = strtolower((string) db_fetch_cell_prepared('SELECT DATA_TYPE
		FROM information_schema.COLUMNS
		WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
		[$database_default, $src, 'scan_date']));
	$zone = (string) db_fetch_cell_prepared('SELECT @@SESSION.time_zone');

	// A TIMESTAMP is read in UTC so each scan date string names one epoch,
	// even in a DST fall-back hour. A DATETIME holds local times as the
	// poller wrote them, so it is read in the session's own zone.
	if ($type === 'timestamp') {
		db_execute_prepared("SET SESSION time_zone = '+00:00'");
	}

	try {
		return mactrack_upart_copy($table, $src, $scheme, $type, $resume, $progress, $pause, $now);
	} finally {
		if ($type === 'timestamp') {
			db_execute_prepared('SET SESSION time_zone = ?', [$zone]);
		}
	}
}

/**
 * Steps 2 to 4 of mactrack_upart_rebuild(): create the new table, copy the
 * scan dates and drop the renamed table.
 *
 * @param string        $table    Live table name.
 * @param string        $src      Renamed original table.
 * @param string        $scheme   day, week or disabled.
 * @param string        $type     DATA_TYPE of scan_date.
 * @param bool          $resume   True when the renamed table existed before this run.
 * @param callable|null $progress See mactrack_upart_rebuild().
 * @param int           $pause    See mactrack_upart_rebuild().
 * @param int           $now      Current time.
 *
 * @return array{ok: bool, reason: string, rows: int, partitions: int, granularity: string}
 */
function mactrack_upart_copy(string $table, string $src, string $scheme, string $type, bool $resume, ?callable $progress, int $pause, int $now): array {
	$result = ['ok' => false, 'reason' => '', 'rows' => 0, 'partitions' => 0, 'granularity' => $scheme];
	$server = mactrack_partition_state($table)['server'];

	$scans = db_fetch_assoc_prepared('SELECT scan_date, UNIX_TIMESTAMP(scan_date) AS epoch
		FROM (SELECT DISTINCT scan_date FROM `' . $src . '`) AS d
		ORDER BY scan_date');

	if (!is_array($scans)) {
		return ['reason' => 'Could not read the scan dates.'] + $result;
	}

	$plan = ['bounds' => [], 'reason' => ''];

	if ($scheme !== 'disabled') {
		$plan = mactrack_upart_plan(array_column($scans, 'epoch'), $scheme, mactrack_partition_retention_days($table), mactrack_partition_cap($server), $now);

		if ($plan['reason'] !== '') {
			return ['reason' => $plan['reason']] + $result;
		}
	}

	// 2. Create the table again, unless an earlier run already did with this scheme.
	$kept = false;

	if (mactrack_partition_table_exists($table)) {
		$kept = mactrack_partition_current_scheme(mactrack_partition_state($table)['layout']) === $scheme;

		if (!$kept && !mactrack_partition_ddl("DROP TABLE `$table`", 3, $pause)) {
			return ['reason' => 'Could not drop the partly filled table of an earlier rebuild.'] + $result;
		}
	}

	if (!$kept) {
		mactrack_upart_out("NOTE: $table: creating the new table" . ($scheme === 'disabled' ? '' : ' with ' . (count($plan['bounds']) + 1) . ' partitions'));

		$steps = ["CREATE TABLE `$table` LIKE `$src`"];

		if (mactrack_upart_is_partitioned($src)) {
			$steps[] = "ALTER TABLE `$table` REMOVE PARTITIONING";
		}

		// UNIX_TIMESTAMP() partitioning needs a TIMESTAMP column.
		if ($type !== 'timestamp' && $scheme !== 'disabled') {
			$steps[] = "ALTER TABLE `$table` MODIFY `scan_date` TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00'";
		}

		if ($scheme !== 'disabled') {
			$steps[] = "ALTER TABLE `$table` " . mactrack_partition_create_sql($plan['bounds']);
		}

		foreach ($steps as $sql) {
			if (!mactrack_partition_ddl($sql, 3, $pause)) {
				return ['reason' => "Could not create the new table ($sql)."] + $result;
			}
		}
	}

	$result['partitions'] = $scheme === 'disabled' ? 0 : count($plan['bounds']) + 1;

	// 3. Copy each distinct scan date, oldest first. A resumed run starts at
	// the newest scan date already in the new table and redoes it, because
	// that copy may have stopped half way.
	$count = count($scans);
	$first = 0;

	if ($kept) {
		$newest = db_fetch_cell_prepared('SELECT MAX(scan_date) FROM `' . $table . '`');

		// An empty new table starts from the first scan date.
		if ($newest !== null && $newest !== false && (string) $newest !== '') {
			$first = $count;

			foreach ($scans as $i => $scan) {
				if ((string) $scan['scan_date'] >= (string) $newest) {
					$first = $i;

					break;
				}
			}

			if ($first < $count && !db_execute_prepared('DELETE FROM `' . $table . '` WHERE scan_date = ?', [$scans[$first]['scan_date']])) {
				return ['reason' => 'Could not clear the last partly copied scan date.'] + $result;
			}
		}
	}

	$rows = $first > 0 ? (int) db_fetch_cell_prepared('SELECT COUNT(*) FROM `' . $table . '`') : 0;

	mactrack_upart_out(sprintf('NOTE: %s: copying %s scan dates', $table, number_format($count - $first)));

	for ($i = $first; $i < $count; $i++) {
		$scan_date = (string) $scans[$i]['scan_date'];

		if (db_execute_prepared('INSERT INTO `' . $table . '` SELECT * FROM `' . $src . '` WHERE scan_date = ?', [$scan_date]) === false) {
			return ['reason' => "Could not copy the scan date $scan_date."] + $result;
		}

		$rows += (int) db_affected_rows();

		heartbeat_process(MACTRACK_PARTITION_REBUILD_PROCESS[0], MACTRACK_PARTITION_REBUILD_PROCESS[1]);

		if ($progress !== null) {
			$progress($table, $i + 1, $count, $scan_date, $rows);
		}
	}

	mactrack_upart_out("NOTE: $table: verifying the row counts");

	$old = db_fetch_cell_prepared('SELECT COUNT(*) FROM `' . $src . '`');
	$new = db_fetch_cell_prepared('SELECT COUNT(*) FROM `' . $table . '`');

	if (!is_numeric($old) || !is_numeric($new)) {
		return ['reason' => 'Could not verify the copied row counts.'] + $result;
	}

	if ((int) $old !== (int) $new) {
		return ['reason' => "The original has $old rows but $new were copied; rows changed during the copy, or two local times share one UTC time. $src was kept."] + $result;
	}

	$result['rows'] = (int) $new;

	// 4. Every scan date is in the new table, so the renamed original can go.
	// If it cannot, it stays: the next run verifies and drops it.
	mactrack_upart_out("NOTE: $table: dropping the original table $src");

	if (!mactrack_partition_ddl("DROP TABLE `$src`", 3, $pause)) {
		return ['reason' => "Copied, but $src could not be dropped; run this again to finish."] + $result;
	}

	if ($table === 'mac_track_ports') {
		set_config_option('mt_partition_pending', '');
	}

	$result['ok'] = true;

	return $result;
}
