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
 * Conversion of an unpartitioned or legacy (daily TO_DAYS) mac_track_ports
 * to the scan-date layout. The live table is never altered: the data is
 * copied into mac_track_ports_new one scan at a time, the row counts are
 * compared, and one RENAME TABLE swaps the two. Until the rename, any
 * failure leaves the original in use.
 */

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
 * Partitions a newly created, empty mac_track_ports into this UTC week
 * and the next, so the catch-all stays empty during normal collection.
 * Existing tables are never converted here; that is
 * mactrack_partition_convert(), run by an operator.
 *
 * @return bool True when the table was partitioned; false leaves it
 *              unpartitioned, which MacTrack fully supports.
 */
function mactrack_partition_fresh_install(): bool {
	try {
		$server = mactrack_partition_server((string) db_fetch_cell_prepared('SELECT VERSION()'));
	} catch (InvalidArgumentException $e) {
		return false;
	}

	if (!mactrack_partition_available($server)) {
		return false;
	}

	$bounds = [];
	$period = mactrack_partition_period('week', time());

	for ($i = 0; $i < 2; $i++) {
		$bounds[mactrack_partition_name('week', $period['start'])] = $period['bound'];
		$period                                                    = mactrack_partition_period('week', $period['bound']);
	}

	if (!mactrack_partition_ddl('ALTER TABLE `mac_track_ports` ' . mactrack_partition_create_sql($bounds))) {
		return false;
	}

	set_config_option('mt_partition_granularity', 'week');

	return true;
}

/**
 * Chooses the partitions that hold an existing history: the wanted
 * granularity, or a coarser one when the history would pass the cap.
 * The bounds come in increasing order; when even daily partitions pass
 * the cap, granularity is empty and reason says why.
 *
 * @param array<int, int|string> $epochs      The history's scan dates as epoch
 *                                            seconds; values below 1 (zero
 *                                            dates) need no partition.
 * @param string                 $granularity Wanted granularity: scan, hour or day.
 * @param int                    $cap         Most partitions allowed, dMaxValue included.
 *
 * @return array{granularity: string, bounds: array<string, int>, reason: string}
 */
function mactrack_partition_convert_bounds(array $epochs, string $granularity, int $cap): array {
	$order  = $granularity === 'week' ? ['week'] : ['scan', 'hour', 'day'];
	$first  = array_search($granularity, $order, true);
	$bounds = [];

	foreach (array_slice($order, $first === false ? 0 : (int) $first) as $g) {
		$bounds = [];

		foreach ($epochs as $epoch) {
			if ((int) $epoch >= 1) {
				$bounds[mactrack_partition_name($g, (int) $epoch)] = mactrack_partition_period($g, (int) $epoch)['bound'];
			}
		}

		asort($bounds);

		if (count($bounds) + 1 <= $cap) {
			return ['granularity' => $g, 'bounds' => $bounds, 'reason' => ''];
		}
	}

	return [
		'granularity' => '',
		'bounds'      => [],
		'reason'      => sprintf('The history needs %d daily partitions, more than the limit of %d. Lower the retention and run maintenance first.', count($bounds) + 1, $cap),
	];
}

/**
 * Converts mac_track_ports to the scan-date layout.
 *
 * Takes the scan lock, so no scan starts or loads meanwhile, and the
 * partition lock, so no partition maintenance runs; sets
 * mt_partition_converting, which pauses database maintenance. The result
 * is recorded under conversion in mt_partition_status.
 *
 * @param bool          $keep_old Keep the original as mac_track_ports_old.
 * @param callable|null $progress Called as ($done, $total, $scan_date) after
 *                                each scan is copied.
 * @param int           $pause    Seconds between DDL retries; the table swap
 *                                waits twice as long.
 * @param string|null   $scheme   day, week or disabled; null preserves the legacy conversion API.
 *
 * @return array{ok: bool, reason: string, rows: int, partitions: int, granularity: string}
 */
function mactrack_partition_convert(bool $keep_old = false, ?callable $progress = null, int $pause = 1, ?string $scheme = null): array {
	// Defines mactrack_background_upgrade_running().
	include_once(__DIR__ . '/../includes/database.php');

	if ($scheme !== null && !in_array($scheme, ['day', 'week', 'disabled'], true)) {
		return ['ok' => false, 'reason' => 'Choose daily, weekly or disabled partitioning.', 'rows' => 0, 'partitions' => 0, 'granularity' => ''];
	}

	$state  = mactrack_partition_state();
	$result = ['ok' => false, 'reason' => '', 'rows' => 0, 'partitions' => 0, 'granularity' => ''];

	switch ($state['layout']['state'] === 'range' && $scheme !== null ? 'repartition' : $state['layout']['state']) {
		case 'range':
			$result['reason'] = 'The port table already uses the scan-date layout.';

			break;
		case 'invalid':
			$result['reason'] = 'The port table has partitions MacTrack does not recognize: ' . $state['layout']['reason'];

			break;
		default:
			if ($scheme !== 'disabled' && !mactrack_partition_available($state['server'])) {
				$result['reason'] = 'This database server does not support partitioning.';
			} elseif (mactrack_background_upgrade_running() || db_table_exists('mac_track_aggregated_ports_upgrade')) {
				// The upgrade rewrites mac_track_ports rows in place; a row it
				// changed after its scan was copied would be lost in the swap.
				$result['reason'] = 'A MacTrack database upgrade is running. Convert again when it has finished.';
			} elseif ((string) read_config_option('mt_partition_pending', true) !== '') {
				$result['reason'] = 'A partition exchange is pending. Let the poller finish loading it before converting.';
			} elseif (db_table_exists('mac_track_ports_old')) {
				$result['reason'] = 'mac_track_ports_old is left from an earlier conversion. Drop it, then convert again.';
			} elseif ((string) db_fetch_cell_prepared('SELECT GET_LOCK(?, 0)', [mactrack_lock_name('scan')]) !== '1') {
				if ((string) read_config_option('mt_partition_converting', true) !== '') {
					// Another conversion holds the lock; its own result is the
					// one the status page should show.
					cacti_log('MACTRACK: Port table conversion not started: another conversion is running.', false, 'MACTRACK');

					$result['reason'] = 'Another conversion is running.';

					return $result;
				}

				$result['reason'] = 'A scan is running. Convert again when it has finished.';
			} else {
				try {
					if (!mactrack_partition_lock()) {
						$result['reason'] = 'Partition maintenance is running. Convert again when it has finished.';
					} else {
						set_config_option('mt_partition_converting', (string) time());

						try {
							$result = mactrack_partition_convert_locked($state, $keep_old, $progress, $pause, $scheme);
						} finally {
							set_config_option('mt_partition_converting', '');
							mactrack_partition_unlock();
						}
					}
				} finally {
					db_fetch_cell_prepared('SELECT RELEASE_LOCK(?)', [mactrack_lock_name('scan')]);
				}
			}
	}

	if (!$result['ok']) {
		cacti_log('MACTRACK: Port table conversion failed: ' . $result['reason'], false, 'MACTRACK');
	}

	mactrack_partition_status(['conversion' => $result + ['time' => time()]]);

	return $result;
}

/**
 * Builds, fills and swaps in the new table, holding both locks.
 *
 * @param array         $state    From mactrack_partition_state(); layout none or legacy.
 * @param bool          $keep_old Keep the original as mac_track_ports_old.
 * @param callable|null $progress See mactrack_partition_convert().
 * @param int           $pause    See mactrack_partition_convert().
 * @param string|null   $scheme   See mactrack_partition_convert().
 *
 * @return array{ok: bool, reason: string, rows: int, partitions: int, granularity: string}
 */
function mactrack_partition_convert_locked(array $state, bool $keep_old, ?callable $progress, int $pause, ?string $scheme = null): array {
	global $database_default;

	$result = ['ok' => false, 'reason' => '', 'rows' => 0, 'partitions' => 0, 'granularity' => ''];

	// A scan may have finished between the preflight checks and taking its lock.
	if ((string) read_config_option('mt_partition_pending', true) !== '' || db_table_exists('mac_track_ports_old')) {
		return ['reason' => 'A pending exchange or retained backup appeared. Check Partition Status before retrying.'] + $result;
	}

	$type   = strtolower((string) db_fetch_cell_prepared('SELECT DATA_TYPE
		FROM information_schema.COLUMNS
		WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
		[$database_default, 'mac_track_ports', 'scan_date']));
	$zone   = (string) db_fetch_cell_prepared('SELECT @@SESSION.time_zone');

	// A TIMESTAMP is read in UTC so each scan date string names one epoch,
	// even in a DST fall-back hour. A DATETIME holds local times as the
	// poller wrote them, so it is read in the session's own zone.
	if ($type === 'timestamp') {
		db_execute_prepared("SET SESSION time_zone = '+00:00'");
	}

	$fail = function (string $reason) use (&$result, $pause): array {
		mactrack_partition_ddl('DROP TABLE IF EXISTS `mac_track_ports_new`', 3, $pause);

		return ['ok' => false, 'reason' => $reason] + $result;
	};

	try {
		$steps = ['DROP TABLE IF EXISTS `mac_track_ports_new`', 'CREATE TABLE `mac_track_ports_new` LIKE `mac_track_ports`'];

		if (in_array($state['layout']['state'], ['legacy', 'range'], true)) {
			$steps[] = 'ALTER TABLE `mac_track_ports_new` REMOVE PARTITIONING';
		}

		// UNIX_TIMESTAMP() partitioning needs a TIMESTAMP column.
		if ($type !== 'timestamp' && $scheme !== 'disabled') {
			$steps[] = "ALTER TABLE `mac_track_ports_new` MODIFY `scan_date` TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00'";
		}

		foreach ($steps as $sql) {
			if (!mactrack_partition_ddl($sql, 3, $pause)) {
				return $fail("Could not prepare the new table ($sql).");
			}
		}

		$scans = db_fetch_assoc_prepared('SELECT scan_date, UNIX_TIMESTAMP(scan_date) AS epoch
			FROM (SELECT DISTINCT scan_date FROM mac_track_ports) AS d
			ORDER BY scan_date');

		if (!is_array($scans)) {
			return $fail('Could not read the history scan dates.');
		}

		if ($scheme === null) {
			$choice = mactrack_partition_convert_bounds(array_column($scans, 'epoch'), mactrack_partition_granularity($state['server'])['granularity'], mactrack_partition_cap($state['server']));
		} else {
			$choice = mactrack_partition_repartition_plan($scans, $scheme, mactrack_partition_cap($state['server']), time());
		}

		if ($choice['granularity'] === '') {
			return $fail($choice['reason']);
		}

		$result['granularity'] = $choice['granularity'];
		$result['partitions']  = $scheme === 'disabled' ? 0 : count($choice['bounds']) + 1;

		if ($scheme !== 'disabled' && !mactrack_partition_ddl('ALTER TABLE `mac_track_ports_new` ' . mactrack_partition_create_sql($choice['bounds']), 3, $pause)) {
			return $fail('Could not partition the new table.');
		}

		$ranges = $choice['ranges'] ?? array_map(fn ($scan) => [$scan['scan_date'], $scan['scan_date']], $scans);

		foreach ($ranges as $i => $range) {
			// Bound the timestamp column directly so the source can prune partitions.
			if (db_execute_prepared('INSERT INTO `mac_track_ports_new` SELECT * FROM `mac_track_ports` WHERE scan_date >= ? AND scan_date <= ?', $range) === false) {
				return $fail("Could not copy the range {$range[0]} through {$range[1]}.");
			}

			if ($progress !== null) {
				$progress($i + 1, count($ranges), (string) $range[0]);
			}
		}

		$old = db_fetch_cell_prepared('SELECT COUNT(*) FROM `mac_track_ports`');
		$new = db_fetch_cell_prepared('SELECT COUNT(*) FROM `mac_track_ports_new`');

		if (!is_numeric($old) || !is_numeric($new)) {
			return $fail('Could not verify the copied row counts.');
		}

		$old = (int) $old;
		$new = (int) $new;

		if ($old !== $new) {
			return $fail("The port table has $old rows but $new were copied; rows changed during the copy, or two local times share one UTC time. Nothing was changed.");
		}

		$result['rows'] = $new;

		// The swap waits for readers of the live table, so it gets more tries
		// than other DDL: failing here would throw away the whole copy.
		if (!mactrack_partition_ddl('RENAME TABLE `mac_track_ports` TO `mac_track_ports_old`, `mac_track_ports_new` TO `mac_track_ports`', 30, 2 * $pause)) {
			return $fail('Could not swap in the new table; readers kept the old one busy.');
		}
	} finally {
		if ($type === 'timestamp') {
			db_execute_prepared('SET SESSION time_zone = ?', [$zone]);
		}
	}

	if ($scheme !== null) {
		set_config_option('mt_partition_granularity', $scheme);
	}

	set_config_option('mt_partition_pending', '');
	$result['ok'] = true;

	if (!$keep_old && !mactrack_partition_ddl('DROP TABLE `mac_track_ports_old`', 3, $pause)) {
		$result['reason'] = 'Converted, but mac_track_ports_old could not be dropped.';
	}

	return $result;
}

/**
 * Plans complete UTC periods, including empty gaps and at least a day ahead.
 * Copy ranges use the original scan_date strings to preserve legacy DATETIME
 * values in the database session's time zone. Zero dates get their own
 * copy range and remain in the first partition.
 *
 * @param array  $scans  Ordered scan_date/epoch pairs from the source table.
 * @param string $scheme day, week or disabled.
 * @param int    $cap    Partition limit including dMaxValue.
 * @param int    $now    Current epoch seconds.
 *
 * @return array{granularity: string, bounds: array<string, int>, ranges: array, reason: string}
 */
function mactrack_partition_repartition_plan(array $scans, string $scheme, int $cap, int $now): array {
	$size   = $scheme === 'disabled' ? 'day' : $scheme;
	$ranges = [];
	$epochs = [];

	foreach ($scans as $scan) {
		$epoch = (int) $scan['epoch'];
		$key   = $epoch > 0 ? mactrack_partition_period($size, $epoch)['start'] : 0;

		if (!isset($ranges[$key])) {
			$ranges[$key] = [$scan['scan_date'], $scan['scan_date']];
		}

		$ranges[$key][1] = $scan['scan_date'];

		if ($epoch > 0) {
			$epochs[] = $epoch;
		}
	}

	$result = ['granularity' => $scheme, 'bounds' => [], 'ranges' => array_values($ranges), 'reason' => ''];

	if ($scheme === 'disabled') {
		return $result;
	}

	$first = min(array_merge($epochs, [$now]));
	$last  = max(array_merge($epochs, [$now + 86400]));

	for ($ts = $first; $ts <= $last; $ts = $period['bound']) {
		$period                                                = mactrack_partition_period($size, $ts);
		$result['bounds'][mactrack_partition_name($size, $ts)] = $period['bound'];

		if (count($result['bounds']) + 1 > $cap) {
			return ['granularity' => '', 'reason' => 'The history exceeds the partition limit. Select weekly partitioning or lower retention and run maintenance first.'] + $result;
		}
	}

	return $result;
}
