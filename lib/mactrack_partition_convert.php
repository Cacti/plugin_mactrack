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

// @codeCoverageIgnoreStart
include_once(__DIR__ . '/../includes/database.php');
// @codeCoverageIgnoreEnd

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
 * Partitions a newly created, empty mac_track_ports by scan date: dMaxValue
 * only, which the poller splits at each scan. Existing tables are never
 * converted here; that is mactrack_partition_convert(), run by an operator.
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

	return mactrack_partition_ddl('ALTER TABLE `mac_track_ports` ' . mactrack_partition_create_sql([]));
}

/**
 * Chooses the partitions that hold an existing history: the wanted
 * granularity, or a coarser one when the history would pass the cap.
 *
 * @param array<int, int|string> $epochs      The history's scan dates as epoch
 *                                            seconds; values below 1 (zero
 *                                            dates) need no partition.
 * @param string                 $granularity Wanted granularity: scan, hour or day.
 * @param int                    $cap         Most partitions allowed, dMaxValue included.
 *
 * @return array{granularity: string, bounds: array<string, int>, reason: string}
 *         Bounds in increasing order; granularity is empty, with the reason,
 *         when even daily partitions pass the cap.
 */
function mactrack_partition_convert_bounds(array $epochs, string $granularity, int $cap): array {
	$order  = ['scan', 'hour', 'day'];
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
 *
 * @return array{ok: bool, reason: string, rows: int, partitions: int, granularity: string}
 */
function mactrack_partition_convert(bool $keep_old = false, ?callable $progress = null, int $pause = 1): array {
	$state  = mactrack_partition_state();
	$result = ['ok' => false, 'reason' => '', 'rows' => 0, 'partitions' => 0, 'granularity' => ''];

	switch ($state['layout']['state']) {
		case 'range':
			$result['reason'] = 'The port table already uses the scan-date layout.';

			break;
		case 'invalid':
			$result['reason'] = 'The port table has partitions MacTrack does not recognize: ' . $state['layout']['reason'];

			break;
		default:
			if (!mactrack_partition_available($state['server'])) {
				$result['reason'] = 'This database server does not support partitioning.';
			} elseif (mactrack_background_upgrade_running() || db_table_exists('mac_track_aggregated_ports_upgrade')) {
				// The upgrade rewrites mac_track_ports rows in place; a row it
				// changed after its scan was copied would be lost in the swap.
				$result['reason'] = 'A MacTrack database upgrade is running. Convert again when it has finished.';
			} elseif (db_table_exists('mac_track_ports_old')) {
				$result['reason'] = 'mac_track_ports_old is left from an earlier conversion. Drop it, then convert again.';
			} elseif ((string) db_fetch_cell_prepared("SELECT GET_LOCK('mactrack_scan', 0)") !== '1') {
				$result['reason'] = 'A scan is running. Convert again when it has finished.';
			} else {
				if (!mactrack_partition_lock()) {
					$result['reason'] = 'Partition maintenance is running. Convert again when it has finished.';
				} else {
					set_config_option('mt_partition_converting', (string) time());

					try {
						$result = mactrack_partition_convert_locked($state, $keep_old, $progress, $pause);
					} finally {
						set_config_option('mt_partition_converting', '');
						mactrack_partition_unlock();
					}
				}

				db_fetch_cell_prepared("SELECT RELEASE_LOCK('mactrack_scan')");
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
 *
 * @return array{ok: bool, reason: string, rows: int, partitions: int, granularity: string}
 */
function mactrack_partition_convert_locked(array $state, bool $keep_old, ?callable $progress, int $pause): array {
	global $database_default;

	$result = ['ok' => false, 'reason' => '', 'rows' => 0, 'partitions' => 0, 'granularity' => ''];
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

		if ($state['layout']['state'] === 'legacy') {
			$steps[] = 'ALTER TABLE `mac_track_ports_new` REMOVE PARTITIONING';
		}

		// UNIX_TIMESTAMP() partitioning needs a TIMESTAMP column.
		if ($type !== 'timestamp') {
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
		$scans = is_array($scans) ? $scans : [];

		$choice = mactrack_partition_convert_bounds(array_column($scans, 'epoch'), mactrack_partition_granularity($state['server'])['granularity'], mactrack_partition_cap($state['server']));

		if ($choice['granularity'] === '') {
			return $fail($choice['reason']);
		}

		$result['granularity'] = $choice['granularity'];
		$result['partitions']  = count($choice['bounds']) + 1;

		if (!mactrack_partition_ddl('ALTER TABLE `mac_track_ports_new` ' . mactrack_partition_create_sql($choice['bounds']), 3, $pause)) {
			return $fail('Could not partition the new table.');
		}

		foreach ($scans as $i => $scan) {
			// The server puts each row into its partition.
			if (db_execute_prepared('INSERT INTO `mac_track_ports_new` SELECT * FROM `mac_track_ports` WHERE scan_date = ?', [$scan['scan_date']]) === false) {
				return $fail("Could not copy the scan of {$scan['scan_date']}.");
			}

			if ($progress !== null) {
				$progress($i + 1, count($scans), (string) $scan['scan_date']);
			}
		}

		$old = (int) db_fetch_cell_prepared('SELECT COUNT(*) FROM `mac_track_ports`');
		$new = (int) db_fetch_cell_prepared('SELECT COUNT(*) FROM `mac_track_ports_new`');

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

	set_config_option('mt_partition_pending', '');
	$result['ok'] = true;

	if (!$keep_old && !mactrack_partition_ddl('DROP TABLE `mac_track_ports_old`', 3, $pause)) {
		$result['reason'] = 'Converted, but mac_track_ports_old could not be dropped.';
	}

	return $result;
}
