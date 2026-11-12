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
 * Database-facing partition steps for mac_track_ports: the named lock,
 * DDL with a short metadata-lock wait, the status record, creating each
 * scan's partition and exchanging the stage table into it. The SQL comes
 * from lib/mactrack_partitions.php, which validates every name.
 */

/**
 * Takes the named lock that serializes partition DDL between the poller,
 * maintenance and Utilities.
 *
 * @return bool True when the lock was taken within 10 seconds.
 */
function mactrack_partition_lock(): bool {
	return (string) db_fetch_cell_prepared("SELECT GET_LOCK('mactrack_partitions', 10)") === '1';
}

/**
 * Releases the named partition lock.
 *
 * @return void
 */
function mactrack_partition_unlock(): void {
	db_fetch_cell_prepared("SELECT RELEASE_LOCK('mactrack_partitions')");
}

/**
 * Takes the scan lock that keeps a scan and a table conversion apart.
 *
 * The poller calls this before any scan or maintenance and keeps the lock
 * until it exits; mactrack_partition_convert() holds it for its whole run.
 * The server releases a named lock when its connection ends, so when the
 * lock is free while mt_partition_converting is still set, the conversion
 * died, and the flag is cleared.
 *
 * @return bool False when another process holds the lock.
 */
function mactrack_partition_scan_begin(): bool {
	if ((string) db_fetch_cell_prepared("SELECT GET_LOCK('mactrack_scan', 0)") !== '1') {
		return false;
	}

	if ((string) read_config_option('mt_partition_converting', true) !== '') {
		cacti_log('MACTRACK: A port table conversion stopped without finishing; the original table is in use.', false, 'MACTRACK');
		set_config_option('mt_partition_converting', '');
	}

	return true;
}

/**
 * Releases the scan lock taken by mactrack_partition_scan_begin(). The
 * poller never calls it: its lock ends with its connection. A web request
 * that took the lock for one action releases it at once, because Cacti
 * may keep the connection open.
 *
 * @return void
 */
function mactrack_partition_scan_end(): void {
	db_fetch_cell_prepared("SELECT RELEASE_LOCK('mactrack_scan')");
}

/**
 * Runs one statement on Cacti's own connection without Cacti's retries.
 *
 * Cacti's database layer retries lock-wait timeouts (error 1205) up to 30
 * times, which would turn a 2 second metadata-lock wait into a minute of
 * stalled readers. Partition DDL therefore goes to the PDO connection
 * directly, and mactrack_partition_ddl() decides how often to retry.
 * Without a Cacti connection (the profiling harness), the Cacti API is used.
 *
 * @param string $sql Statement to run.
 *
 * @return string Empty on success, otherwise "<error code> <message>".
 */
function mactrack_partition_exec(string $sql): string {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	$conn = $database_sessions["$database_hostname:$database_port:$database_default"] ?? null;

	if (!is_object($conn)) {
		return db_execute_prepared($sql, [], false) !== false ? '' : 'error';
	}

	try {
		if ($conn->exec($sql) !== false) {
			return '';
		}

		$info = $conn->errorInfo();

		return trim(($info[1] ?? '') . ' ' . ($info[2] ?? ''));
	} catch (PDOException $e) {
		return $e->getCode() . ' ' . $e->getMessage();
	}
}

/**
 * Runs one partition DDL statement with a 2 second metadata-lock wait.
 *
 * While a DDL statement waits for the metadata lock, every new reader of
 * mac_track_ports queues behind it, so the wait is kept short and the
 * statement is retried instead. The session's previous lock_wait_timeout
 * is restored whatever happens.
 *
 * @param string $sql      Statement from a mactrack_partition_*_sql() builder.
 * @param int    $attempts Tries before giving up.
 * @param int    $pause    Seconds between tries.
 *
 * @return bool True when the statement succeeded.
 */
function mactrack_partition_ddl(string $sql, int $attempts = 3, int $pause = 1): bool {
	$old   = (string) db_fetch_cell_prepared('SELECT @@SESSION.lock_wait_timeout');
	$error = 'not run';

	db_execute_prepared('SET SESSION lock_wait_timeout = 2');

	for ($i = 1; $i <= $attempts && $error !== ''; $i++) {
		if ($i > 1 && $pause > 0) {
			sleep($pause);
		}

		$error = mactrack_partition_exec($sql);
	}

	// A system variable cannot take a bound string, so the checked integer
	// is written into the statement.
	if (ctype_digit($old)) {
		db_execute_prepared('SET SESSION lock_wait_timeout = ' . (int) $old);
	} else {
		db_execute_prepared('SET SESSION lock_wait_timeout = DEFAULT');
	}

	$ok = $error === '';

	if (!$ok) {
		cacti_log("MACTRACK: Partition DDL failed after $attempts attempts ($error): $sql", false, 'MACTRACK');
	}

	return $ok;
}

/**
 * Merges fields into the partition status record (config option
 * mt_partition_status, JSON) and stamps the time.
 *
 * @param array<string, mixed> $fields Fields to set.
 *
 * @return void
 */
function mactrack_partition_status(array $fields): void {
	$status = json_decode((string) read_config_option('mt_partition_status', true), true);

	if (!is_array($status)) {
		$status = [];
	}

	set_config_option('mt_partition_status', json_encode(array_merge($status, $fields, ['time' => time()])));
}

/**
 * Reads the partition layout of mac_track_ports and the server version.
 *
 * @return array{layout: array, server: array{mariadb: bool, version: string}}
 */
function mactrack_partition_state(): array {
	global $database_default;

	$rows = db_fetch_assoc_prepared('SELECT PARTITION_NAME, PARTITION_ORDINAL_POSITION, PARTITION_METHOD,
		PARTITION_EXPRESSION, PARTITION_DESCRIPTION, TABLE_ROWS
		FROM information_schema.PARTITIONS
		WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
		[$database_default, 'mac_track_ports']);

	$layout = mactrack_partition_layout(is_array($rows) ? $rows : []);

	try {
		$server = mactrack_partition_server((string) db_fetch_cell_prepared('SELECT VERSION()'));
	} catch (InvalidArgumentException $e) {
		// Without the version there is no safe cap or exchange syntax, so no DDL.
		$server = ['mariadb' => false, 'version' => '0.0.0'];

		if ($layout['state'] === 'range') {
			$layout = ['state' => 'invalid', 'reason' => $e->getMessage(), 'partitions' => $layout['partitions']];
		}
	}

	return ['layout' => $layout, 'server' => $server];
}

/**
 * Chooses the effective granularity from the settings: the wanted size,
 * or a coarser one when the wanted size would pass the server's cap.
 *
 * @param array{mariadb: bool, version: string} $server From mactrack_partition_server().
 *
 * @return array{granularity: string, projected: int, fell_back: bool}
 */
function mactrack_partition_granularity(array $server): array {
	$wanted   = (string) read_config_option('mt_partition_granularity');
	$wanted   = in_array($wanted, ['scan', 'hour', 'day'], true) ? $wanted : 'scan';
	$interval = (int) read_config_option('mt_collection_timing') * 60;
	$days     = (int) read_config_option('mt_data_retention');

	return mactrack_partition_choose($wanted, $interval >= 60 ? $interval : 900, $days >= 1 ? $days : 14, mactrack_partition_cap($server));
}

/**
 * Creates the partition for a scan before any of its rows are written.
 *
 * Runs only on the range layout. Rows that an earlier failure left in
 * dMaxValue are first moved into a recovery partition (named r<newest scan
 * date>), the only split allowed to move rows. Then the scan's period is
 * split off the now empty dMaxValue. Per-scan partitions are filled by
 * exchanging the stage table; hourly and daily ones hold several scans, so
 * their rows are loaded straight into mac_track_ports. Any failure also
 * sends the scan straight into mac_track_ports, where its rows land in the
 * partition that covers them or in dMaxValue, and the reason is recorded.
 *
 * @param string $scan_date The scan's scan_date, as the poller stores it.
 *
 * @return array{mode: string, partition: string|null, reason: string, granularity: string|null, scan_date: string}
 *         mode is exchange (load the stage table and exchange it into
 *         partition) or direct (load into mac_track_ports).
 */
function mactrack_partition_prepare_scan(string $scan_date): array {
	$direct = function (string $reason, ?string $granularity = null, ?string $partition = null) use ($scan_date): array {
		if ($reason !== '') {
			mactrack_partition_status(['reason' => $reason]);
		}

		return ['mode' => 'direct', 'partition' => $partition, 'reason' => $reason, 'granularity' => $granularity, 'scan_date' => $scan_date];
	};

	$state  = mactrack_partition_state();
	$layout = $state['layout'];

	if ($layout['state'] !== 'range') {
		return $direct(trim("layout {$layout['state']} {$layout['reason']}"));
	}

	// The server's own reading of the stored value, so the bound matches it.
	$epoch = (int) db_fetch_cell_prepared('SELECT UNIX_TIMESTAMP(?)', [$scan_date]);

	if ($epoch < 1) {
		return $direct("scan date $scan_date has no epoch");
	}

	$cap    = mactrack_partition_cap($state['server']);
	$choice = mactrack_partition_granularity($state['server']);
	$size   = $choice['granularity'];

	mactrack_partition_status(['layout' => 'range', 'granularity' => $size,
		'fell_back' => $choice['fell_back'], 'projected' => $choice['projected'], 'reason' => '']);

	$count = count($layout['partitions']);

	if ($count >= $cap) {
		return $direct("partition cap $cap reached", $size);
	}

	if (!mactrack_partition_lock()) {
		return $direct('partition lock busy', $size);
	}

	try {
		$bounded  = array_values(array_filter($layout['partitions'], fn ($p) => $p['bound'] !== null));
		$last     = $bounded === [] ? 0 : end($bounded)['bound'];
		$covering = null;

		foreach ($bounded as $p) {
			if ($p['bound'] > $epoch) {
				$covering = $p['name'];

				break;
			}
		}
		$stranded = db_fetch_cell_prepared('SELECT MAX(UNIX_TIMESTAMP(scan_date)) FROM `mac_track_ports` PARTITION (`dMaxValue`)');

		if (is_numeric($stranded) && (int) $stranded > 0) {
			$name = 'r' . gmdate('YmdHis', (int) $stranded);

			cacti_log("MACTRACK: Recovering rows in dMaxValue into partition '$name'", false, 'SYSTEM');

			if (!mactrack_partition_ddl(mactrack_partition_split_sql($name, (int) $stranded + 1))) {
				return $direct('recovery split failed', $size);
			}

			$count++;

			$last     = (int) $stranded + 1;
			$covering = $covering ?? ($last > $epoch ? $name : null);
		}

		if ($last > $epoch) {
			// A partition already covers this scan and may hold other rows.
			return $direct('', $size, $covering);
		}

		// Each split adds a partition, so the cap is checked before each one.
		if ($count >= $cap) {
			return $direct("partition cap $cap reached", $size);
		}

		$name   = mactrack_partition_name($size, $epoch);
		$period = mactrack_partition_period($size, $epoch);

		if (!mactrack_partition_ddl(mactrack_partition_split_sql($name, $period['bound']))) {
			return $direct('split failed', $size);
		}

		if ($size !== 'scan') {
			return $direct('', $size, $name);
		}

		return ['mode' => 'exchange', 'partition' => $name, 'reason' => '', 'granularity' => $size, 'scan_date' => $scan_date];
	} finally {
		mactrack_partition_unlock();
	}
}

/**
 * Makes sure mac_track_ports_stage can be exchanged with a partition of
 * mac_track_ports.
 *
 * An exchange needs both tables to have the same columns and indexes, and
 * the stage table must not be partitioned. The stage table is recreated
 * when it is missing or differs, for example after an upgrade changed
 * mac_track_ports. It only ever holds one scan, so nothing is lost.
 *
 * @return bool True when the stage table matches mac_track_ports.
 */
function mactrack_partition_stage_ensure(): bool {
	global $database_default;

	$shape = function (string $table) use ($database_default): array {
		// EXCHANGE needs identical definitions, collations included.
		$columns = db_fetch_assoc_prepared('SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT,
			CHARACTER_SET_NAME, COLLATION_NAME
			FROM information_schema.COLUMNS
			WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
			ORDER BY ORDINAL_POSITION',
			[$database_default, $table]);

		$indexes = db_fetch_assoc_prepared('SELECT INDEX_NAME, SEQ_IN_INDEX, COLUMN_NAME, NON_UNIQUE
			FROM information_schema.STATISTICS
			WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
			ORDER BY INDEX_NAME, SEQ_IN_INDEX',
			[$database_default, $table]);

		$table = db_fetch_assoc_prepared('SELECT ENGINE, TABLE_COLLATION
			FROM information_schema.TABLES
			WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
			[$database_default, $table]);

		return [is_array($columns) ? $columns : [], is_array($indexes) ? $indexes : [], is_array($table) ? $table : []];
	};

	$want = $shape('mac_track_ports');
	$have = $shape('mac_track_ports_stage');

	if ($have[0] !== [] && $have == $want) {
		return true;
	}

	return db_execute_prepared('DROP TABLE IF EXISTS `mac_track_ports_stage`') !== false
		&& db_execute_prepared('CREATE TABLE `mac_track_ports_stage` LIKE `mac_track_ports`') !== false
		&& db_execute_prepared('ALTER TABLE `mac_track_ports_stage` REMOVE PARTITIONING') !== false;
}

/**
 * Exchanges the stage table into an empty partition of mac_track_ports,
 * under the partition lock.
 *
 * @param string                                $partition Partition to fill.
 * @param array{mariadb: bool, version: string} $server    From mactrack_partition_server().
 *
 * @return bool True when the exchange succeeded.
 */
function mactrack_partition_exchange(string $partition, array $server): bool {
	$sql = mactrack_partition_exchange_sql($partition, mactrack_partition_without_validation($server));

	if (!mactrack_partition_lock()) {
		return false;
	}

	try {
		return mactrack_partition_ddl($sql);
	} finally {
		mactrack_partition_unlock();
	}
}

/**
 * Loads the scan's rows from mac_track_temp_ports as the plan from
 * mactrack_partition_prepare_scan() says.
 *
 * An exchange left pending by an earlier scan is retried first, because its
 * rows are still in the stage table and are not visible until it succeeds.
 * While it keeps failing, scans load straight into mac_track_ports and the
 * stage rows are kept for the next try.
 *
 * @param array{mode: string, partition: string|null} $plan From mactrack_partition_prepare_scan().
 *
 * @return int Rows read from mac_track_temp_ports.
 */
function mactrack_partition_load_scan(array $plan): int {
	$mode    = $plan['mode'] ?? 'direct';
	$pending = (string) read_config_option('mt_partition_pending', true);
	$server  = null;

	if ($pending !== '' && !mactrack_partition_name_valid($pending)) {
		cacti_log('MACTRACK: Ignoring an invalid pending partition exchange', false, 'MACTRACK');
		set_config_option('mt_partition_pending', '');
		$pending = '';
	}

	if ($pending !== '' || $mode === 'exchange') {
		$server = mactrack_partition_server((string) db_fetch_cell_prepared('SELECT VERSION()'));
	}

	if ($pending !== '') {
		if (mactrack_partition_exchange($pending, $server)) {
			set_config_option('mt_partition_pending', '');
			mactrack_partition_status(['pending' => '']);
		} else {
			// The retry failed too, so the exchange is not going to work: the
			// table changed, or retention dropped the partition. The rows go
			// in directly; they leave the stage table only once all are in.
			$failed = 0;

			mactrack_ports_load('mac_track_ports', 1000, null, true, 'mac_track_ports_stage', $failed);

			if ($failed === 0) {
				db_execute_prepared('TRUNCATE TABLE `mac_track_ports_stage`');
				set_config_option('mt_partition_pending', '');
				mactrack_partition_status(['pending' => '', 'reason' => "pending exchange $pending failed; its rows were inserted directly"]);
			} else {
				mactrack_partition_status(['pending' => $pending, 'reason' => "pending exchange $pending failed; its rows are still in the stage table"]);
			}

			$mode = 'direct';
		}
	}

	if ($mode === 'exchange' && !mactrack_partition_stage_ensure()) {
		mactrack_partition_status(['reason' => 'stage table unavailable']);
		$mode = 'direct';
	}

	if ($mode !== 'exchange') {
		return mactrack_ports_load('mac_track_ports');
	}

	db_execute_prepared('TRUNCATE TABLE `mac_track_ports_stage`');

	// Only this scan's rows go into its partition. Rows left in the temp
	// table with an earlier date (a device rescan, or a run that stopped
	// before the temp table was cleared) go straight into the partitions
	// that cover their dates, as they do without an exchange.
	$rows = mactrack_ports_load('mac_track_ports_stage', 1000, $plan['scan_date'], true);

	if (!mactrack_partition_exchange($plan['partition'], $server)) {
		set_config_option('mt_partition_pending', $plan['partition']);
		mactrack_partition_status(['pending' => $plan['partition'], 'reason' => 'exchange failed']);
	}

	return $rows + mactrack_ports_load('mac_track_ports', 1000, $plan['scan_date'], false);
}

/**
 * Retention and ahead creation for the scan-date layout.
 *
 * Drops every partition whose upper bound is at or before the retention
 * cutoff, except dMaxValue and the newest bounded partition, then prunes
 * the scan dates below the highest dropped bound. For hourly and daily
 * partitions, also creates the current and the next period, so the poller
 * seldom splits. Per-scan partitions are only created by the poller, and
 * nothing is created while dMaxValue holds rows: the next scan's recovery
 * split moves those first. Other layouts are left to their own maintenance.
 *
 * @param int      $days Retention in days; below 1 drops nothing.
 * @param int|null $now  Current time, for tests.
 *
 * @return array{dropped: string[], created: string[], reason: string}
 */
function mactrack_partition_maintain(int $days, ?int $now = null): array {
	$now    = $now ?? time();
	$result = ['dropped' => [], 'created' => [], 'reason' => ''];
	$state  = mactrack_partition_state();
	$layout = $state['layout'];

	if ($layout['state'] !== 'range') {
		$result['reason'] = trim("layout {$layout['state']} {$layout['reason']}");
	} elseif (!mactrack_partition_lock()) {
		$result['reason'] = 'partition lock busy';
	} else {
		try {
			$result = mactrack_partition_maintain_locked($layout['partitions'], $state['server'], $days, $now);
		} finally {
			mactrack_partition_unlock();
		}
	}

	mactrack_partition_status(['maintenance' => $result + ['time' => time()]]);

	return $result;
}

/**
 * The part of mactrack_partition_maintain() that runs under the lock.
 *
 * @param array<int, array{name: string, bound: int|null, rows: int}> $partitions From mactrack_partition_layout().
 * @param array{mariadb: bool, version: string}                        $server     From mactrack_partition_server().
 * @param int                                                          $days       Retention in days.
 * @param int                                                          $now        Current time.
 *
 * @return array{dropped: string[], created: string[], reason: string}
 */
function mactrack_partition_maintain_locked(array $partitions, array $server, int $days, int $now): array {
	$result  = ['dropped' => [], 'created' => [], 'reason' => ''];
	$bounded = array_values(array_filter($partitions, fn ($p) => $p['bound'] !== null));
	$count   = count($partitions);

	if ($days > 0 && count($bounded) > 1) {
		$cutoff  = $now - $days * 86400;
		$expired = array_values(array_filter(array_slice($bounded, 0, -1), fn ($p) => $p['bound'] <= $cutoff));

		if ($expired !== []) {
			$names = array_column($expired, 'name');

			if (!mactrack_partition_ddl(mactrack_partition_drop_sql($names))) {
				return ['reason' => 'drop failed'] + $result;
			}

			cacti_log('MACTRACK: Dropped expired partitions ' . implode(', ', $names), false, 'SYSTEM');

			$result['dropped'] = $names;
			$count -= count($names);

			// The server's own reading of the bound, as scan_date was stored.
			mactrack_scan_dates_prune((string) db_fetch_cell_prepared('SELECT FROM_UNIXTIME(?)', [max(array_column($expired, 'bound'))]));
		}
	}

	$size = mactrack_partition_granularity($server)['granularity'];

	if ($size === 'scan') {
		return $result;
	}

	$stranded = db_fetch_cell_prepared('SELECT MAX(UNIX_TIMESTAMP(scan_date)) FROM `mac_track_ports` PARTITION (`dMaxValue`)');

	if (is_numeric($stranded) && (int) $stranded > 0) {
		return ['reason' => 'dMaxValue holds rows'] + $result;
	}

	$cap  = mactrack_partition_cap($server);
	$last = $bounded === [] ? 0 : end($bounded)['bound'];

	for ($i = 0, $ts = $now; $i < 2; $i++) {
		$period = mactrack_partition_period($size, $ts);
		$ts     = $period['bound'];

		if ($period['bound'] <= $last) {
			continue;
		}

		if ($count >= $cap) {
			return ['reason' => "partition cap $cap reached"] + $result;
		}

		$name = mactrack_partition_name($size, $period['start']);

		if (!mactrack_partition_ddl(mactrack_partition_split_sql($name, $period['bound']))) {
			return ['reason' => 'split failed'] + $result;
		}

		$result['created'][] = $name;
		$last                = $period['bound'];
		$count++;
	}

	return $result;
}

/**
 * Gathers what the Utilities partition status page shows.
 *
 * @return array{layout: string, reason: string, partitions: int, cap: int,
 *               oldest: string|null, newest: string|null, covered_until: int|null,
 *               granularity: string, fell_back: bool, projected: int, pending: string,
 *               load_path: string, last_scan_reason: string, maintenance: array|null,
 *               conversion_needed: bool, converting: int|null, conversion: array|null}
 */
function mactrack_partition_report(): array {
	$state   = mactrack_partition_state();
	$layout  = $state['layout'];
	$bounded = array_values(array_filter($layout['partitions'], fn ($p) => $p['bound'] !== null));
	$choice  = mactrack_partition_granularity($state['server']);
	$status  = json_decode((string) read_config_option('mt_partition_status', true), true);
	$status  = is_array($status) ? $status : [];
	// Set to the start time while mactrack_partition_convert() runs.
	$converting = (string) read_config_option('mt_partition_converting', true);

	return [
		'layout'            => $layout['state'],
		'reason'            => $layout['reason'],
		'partitions'        => count($layout['partitions']),
		'cap'               => mactrack_partition_cap($state['server']),
		'oldest'            => $bounded === [] ? null : $bounded[0]['name'],
		'newest'            => $bounded === [] ? null : end($bounded)['name'],
		// TO_DAYS bounds of the legacy layout are not epoch seconds.
		'covered_until'     => $bounded === [] || $layout['state'] !== 'range' ? null : end($bounded)['bound'],
		'granularity'       => $choice['granularity'],
		'fell_back'         => $choice['fell_back'],
		'projected'         => $choice['projected'],
		'pending'           => (string) read_config_option('mt_partition_pending', true),
		'load_path'         => 'php',
		'last_scan_reason'  => (string) ($status['reason'] ?? ''),
		'maintenance'       => is_array($status['maintenance'] ?? null) ? $status['maintenance'] : null,
		'conversion_needed' => $layout['state'] === 'legacy',
		'converting'        => ctype_digit($converting) ? (int) $converting : null,
		'conversion'        => is_array($status['conversion'] ?? null) ? $status['conversion'] : null,
	];
}

/**
 * Names the partition of mac_track_ports that holds one scan date.
 *
 * Reads of one known scan name this partition as well as filtering on
 * scan_date, so they read a single partition even where the server's
 * pruning would not apply.
 *
 * @param string $scan_date A scan_date value.
 *
 * @return string|null The partition, or null when the table does not use
 *                     the scan-date layout or the server cannot read the date.
 */
function mactrack_partition_for_scan(string $scan_date): ?string {
	$layout = mactrack_partition_state()['layout'];

	if ($layout['state'] !== 'range') {
		return null;
	}

	$epoch = (int) db_fetch_cell_prepared('SELECT UNIX_TIMESTAMP(?)', [$scan_date]);

	if ($epoch < 1) {
		return null;
	}

	$name = null;

	// The range layout ends with dMaxValue, whose bound is null, so a
	// partition is always found.
	foreach ($layout['partitions'] as $p) {
		if ($p['bound'] === null || $p['bound'] > $epoch) {
			$name = $p['name'];

			break;
		}
	}

	return $name;
}
