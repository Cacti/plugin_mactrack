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
 * Partition naming, layout checks and DDL for mac_track_ports, which is
 * partitioned by RANGE (UNIX_TIMESTAMP(scan_date)). Nothing here touches
 * the database: callers pass information_schema rows and VERSION()
 * strings in and run the SQL these functions return. Every partition name
 * that reaches SQL is checked by mactrack_partition_name_valid().
 */

/**
 * Returns the period of a partition granularity that contains a time.
 *
 * Periods are aligned in UTC, so the session time zone and DST cannot
 * shift them. A scan partition holds the one second of its scan_date.
 *
 * @param string $granularity scan, hour or day.
 * @param int    $ts          Epoch seconds.
 *
 * @throws InvalidArgumentException For another granularity or $ts below 1.
 *
 * @return array{start: int, bound: int} First second of the period and the
 *                                       partition's VALUES LESS THAN bound.
 */
function mactrack_partition_period(string $granularity, int $ts): array {
	if ($ts < 1) {
		throw new InvalidArgumentException('Timestamp must be positive');
	}

	switch ($granularity) {
		case 'scan':
			return ['start' => $ts, 'bound' => $ts + 1];
		case 'hour':
			$start = $ts - $ts % 3600;

			return ['start' => $start, 'bound' => $start + 3600];
		case 'day':
			$start = $ts - $ts % 86400;

			return ['start' => $start, 'bound' => $start + 86400];
	}

	throw new InvalidArgumentException("Unknown partition granularity '$granularity'");
}

/**
 * Returns the name of the partition that holds a time, built in UTC:
 * sYYYYMMDDHHMMSS, hYYYYMMDDHH or dYYYYMMDD.
 *
 * @param string $granularity scan, hour or day.
 * @param int    $ts          Epoch seconds.
 *
 * @throws InvalidArgumentException For another granularity or $ts below 1.
 *
 * @return string Partition name.
 */
function mactrack_partition_name(string $granularity, int $ts): string {
	$start = mactrack_partition_period($granularity, $ts)['start'];

	switch ($granularity) {
		case 'scan':
			return 's' . gmdate('YmdHis', $start);
		case 'hour':
			return 'h' . gmdate('YmdH', $start);
	}

	return 'd' . gmdate('Ymd', $start);
}

/**
 * Checks that a partition name is one MacTrack builds: a lower-case
 * letter followed by digits, or dMaxValue, at most 64 characters.
 *
 * @param string $name Partition name.
 *
 * @return bool True when the name is safe to put into DDL.
 */
function mactrack_partition_name_valid(string $name): bool {
	return strlen($name) <= 64 && preg_match('/^(?:[a-z][0-9]+|dMaxValue)$/D', $name) === 1;
}

/**
 * Reads the vendor and version from a VERSION() string.
 *
 * MariaDB reports itself as "10.5.29-MariaDB-..." and, through some
 * clients, with a "5.5.5-" prefix for old MySQL replication clients;
 * MySQL reports a bare "8.4.13".
 *
 * @param string $version VERSION() result.
 *
 * @throws InvalidArgumentException When no x.y.z version is found.
 *
 * @return array{mariadb: bool, version: string}
 */
function mactrack_partition_server(string $version): array {
	$mariadb = stripos($version, 'mariadb') !== false;

	if ($mariadb) {
		$version = (string) preg_replace('/^5\.5\.5-/', '', $version);
	}

	if (!preg_match('/^(\d+\.\d+\.\d+)/', $version, $m)) {
		throw new InvalidArgumentException("Unreadable server version '$version'");
	}

	return ['mariadb' => $mariadb, 'version' => $m[1]];
}

/**
 * Returns the most partitions MacTrack keeps on mac_track_ports.
 *
 * MariaDB keeps split, exchange and drop flat to 2,048 partitions; MySQL
 * 8.0 and 8.4 slow by about 2 ms per partition for each of them, and every
 * one holds the metadata lock for that long, so MySQL gets half the cap.
 *
 * @param array{mariadb: bool, version: string} $server From mactrack_partition_server().
 *
 * @return int Partition cap, dMaxValue included.
 */
function mactrack_partition_cap(array $server): int {
	return $server['mariadb'] ? 1024 : 512;
}

/**
 * Checks whether EXCHANGE PARTITION ... WITHOUT VALIDATION is accepted:
 * MariaDB 11.4 and later, MySQL 5.7.5 and later. Older MariaDB releases
 * reject it as a syntax error.
 *
 * @param array{mariadb: bool, version: string} $server From mactrack_partition_server().
 *
 * @return bool
 */
function mactrack_partition_without_validation(array $server): bool {
	return version_compare($server['version'], $server['mariadb'] ? '11.4.0' : '5.7.5', '>=');
}

/**
 * Chooses the finest granularity, starting at the one asked for, whose
 * projected partition count fits under the cap.
 *
 * The projected count is the periods per day times the retention days,
 * plus dMaxValue. When not even daily partitions fit, daily is returned
 * anyway with fell_back set, and the caller reports it.
 *
 * @param string $wanted         scan, hour or day.
 * @param int    $scan_interval  Seconds between scans (at least 60).
 * @param int    $retention_days Days of history kept (at least 1).
 * @param int    $cap            From mactrack_partition_cap().
 *
 * @throws InvalidArgumentException For another granularity or an out-of-range input.
 *
 * @return array{granularity: string, projected: int, fell_back: bool}
 */
function mactrack_partition_choose(string $wanted, int $scan_interval, int $retention_days, int $cap): array {
	$periods = ['scan' => $scan_interval, 'hour' => 3600, 'day' => 86400];

	if (!isset($periods[$wanted])) {
		throw new InvalidArgumentException("Unknown partition granularity '$wanted'");
	}

	if ($scan_interval < 60 || $retention_days < 1) {
		throw new InvalidArgumentException('Scan interval must be at least 60 seconds and retention at least 1 day');
	}

	$names     = array_keys($periods);
	$projected = 0;

	foreach (array_slice($names, (int) array_search($wanted, $names, true)) as $granularity) {
		// Expired partitions go only at the daily maintenance run, so up to
		// one more day builds up. Reserve another day for hourly partitions
		// created ahead, plus dMaxValue and a partial period.
		$projected = (int) ceil(86400 / $periods[$granularity]) * ($retention_days + ($granularity === 'hour' ? 2 : 1)) + 2;

		if ($projected <= $cap) {
			return ['granularity' => $granularity, 'projected' => $projected, 'fell_back' => $granularity !== $wanted];
		}
	}

	return ['granularity' => 'day', 'projected' => $projected, 'fell_back' => true];
}

/**
 * Reads and checks the partition layout of mac_track_ports.
 *
 * States: none (not partitioned); legacy (the daily TO_DAYS layout that
 * mactrack_convert.php builds, which is reported but never altered here);
 * range (a layout MacTrack builds: RANGE on UNIX_TIMESTAMP(scan_date),
 * valid names, strictly increasing integer bounds, dMaxValue last with
 * MAXVALUE); invalid, with the first rule broken in reason. Callers run
 * partition DDL only on range. The rows are the table's
 * information_schema.PARTITIONS rows (PARTITION_NAME,
 * PARTITION_ORDINAL_POSITION, PARTITION_METHOD, PARTITION_EXPRESSION,
 * PARTITION_DESCRIPTION, TABLE_ROWS).
 *
 * @param array<int, array<string, string|null>> $rows The partition rows.
 *
 * @return array{state: string, reason: string, partitions: array<int, array{name: string, bound: int|null, rows: int}>}
 */
function mactrack_partition_layout(array $rows): array {
	$rows = array_values(array_filter($rows, fn ($r) => ($r['PARTITION_NAME'] ?? null) !== null));

	if ($rows === []) {
		return ['state' => 'none', 'reason' => '', 'partitions' => []];
	}

	usort($rows, fn ($a, $b) => (int) $a['PARTITION_ORDINAL_POSITION'] <=> (int) $b['PARTITION_ORDINAL_POSITION']);

	$partitions = [];

	foreach ($rows as $r) {
		$description  = (string) $r['PARTITION_DESCRIPTION'];
		$partitions[] = [
			'name'  => (string) $r['PARTITION_NAME'],
			'bound' => ctype_digit($description) ? (int) $description : null,
			'rows'  => (int) ($r['TABLE_ROWS'] ?? 0),
		];
	}

	$invalid    = fn (string $reason) => ['state' => 'invalid', 'reason' => $reason, 'partitions' => $partitions];
	$method     = strtoupper((string) $rows[0]['PARTITION_METHOD']);
	$expression = strtolower(str_replace(['`', ' '], '', (string) $rows[0]['PARTITION_EXPRESSION']));

	if ($method !== 'RANGE') {
		return $invalid("Partition method is $method, not RANGE");
	}

	if (strpos($expression, 'to_days') !== false) {
		return ['state' => 'legacy', 'reason' => '', 'partitions' => $partitions];
	}

	if ($expression !== 'unix_timestamp(scan_date)') {
		return $invalid("Partition expression is $expression, not unix_timestamp(scan_date)");
	}

	$last = count($rows) - 1;

	if ($partitions[$last]['name'] !== 'dMaxValue' || strtoupper((string) $rows[$last]['PARTITION_DESCRIPTION']) !== 'MAXVALUE') {
		return $invalid('The last partition must be dMaxValue with MAXVALUE');
	}

	$previous = 0;

	foreach (array_slice($partitions, 0, $last) as $p) {
		if (!mactrack_partition_name_valid($p['name']) || $p['name'] === 'dMaxValue') {
			return $invalid("Partition name '{$p['name']}' is not one MacTrack builds");
		}

		if ($p['bound'] === null) {
			return $invalid("Partition {$p['name']} has no integer bound");
		}

		if ($p['bound'] <= $previous) {
			return $invalid("Partition bounds do not increase at {$p['name']}");
		}

		$previous = $p['bound'];
	}

	return ['state' => 'range', 'reason' => '', 'partitions' => $partitions];
}

/**
 * Throws unless a name may be used for a bounded partition: valid, and not
 * dMaxValue.
 *
 * @param string $name Partition name.
 *
 * @throws InvalidArgumentException
 *
 * @return void
 */
function mactrack_partition_assert_name(string $name): void {
	if (!mactrack_partition_name_valid($name) || $name === 'dMaxValue') {
		throw new InvalidArgumentException("Invalid partition name '$name'");
	}
}

/**
 * Builds the PARTITION BY clause for a new mac_track_ports, ending in
 * dMaxValue.
 *
 * @param array<string, int> $bounds Partition name => VALUES LESS THAN bound,
 *                                   in strictly increasing bound order.
 *
 * @throws InvalidArgumentException For an invalid name or a bound that is
 *                                  below 1 or does not increase.
 *
 * @return string Clause to append to CREATE TABLE.
 */
function mactrack_partition_create_sql(array $bounds): string {
	$parts    = [];
	$previous = 0;

	foreach ($bounds as $name => $bound) {
		mactrack_partition_assert_name((string) $name);

		if ($bound <= $previous) {
			throw new InvalidArgumentException("Partition bound for $name must be positive and increasing");
		}

		$parts[]  = 'PARTITION `' . $name . '` VALUES LESS THAN (' . $bound . ')';
		$previous = $bound;
	}

	$parts[] = 'PARTITION `dMaxValue` VALUES LESS THAN MAXVALUE';

	return 'PARTITION BY RANGE (UNIX_TIMESTAMP(scan_date)) (' . implode(', ', $parts) . ')';
}

/**
 * Builds the REORGANIZE that splits a new partition off the front of
 * dMaxValue. On an empty dMaxValue it moves no rows.
 *
 * @param string $name  New partition's name.
 * @param int    $bound Its VALUES LESS THAN bound.
 *
 * @throws InvalidArgumentException For an invalid name or a bound below 1.
 *
 * @return string ALTER TABLE statement.
 */
function mactrack_partition_split_sql(string $name, int $bound): string {
	mactrack_partition_assert_name($name);

	if ($bound < 1) {
		throw new InvalidArgumentException('Partition bound must be positive');
	}

	return 'ALTER TABLE `mac_track_ports` REORGANIZE PARTITION `dMaxValue` INTO ('
		. 'PARTITION `' . $name . '` VALUES LESS THAN (' . $bound . '), '
		. 'PARTITION `dMaxValue` VALUES LESS THAN MAXVALUE)';
}

/**
 * Builds the EXCHANGE that swaps a partition with mac_track_ports_stage.
 *
 * @param string $name               Partition to exchange.
 * @param bool   $without_validation Append WITHOUT VALIDATION; pass the
 *                                   result of mactrack_partition_without_validation().
 *
 * @throws InvalidArgumentException For an invalid name.
 *
 * @return string ALTER TABLE statement.
 */
function mactrack_partition_exchange_sql(string $name, bool $without_validation): string {
	mactrack_partition_assert_name($name);

	return 'ALTER TABLE `mac_track_ports` EXCHANGE PARTITION `' . $name . '` WITH TABLE `mac_track_ports_stage`'
		. ($without_validation ? ' WITHOUT VALIDATION' : '');
}

/**
 * Builds one DROP PARTITION for a list of partitions. dMaxValue is never
 * dropped.
 *
 * @param string[] $names Partitions to drop.
 *
 * @throws InvalidArgumentException For an empty list or an invalid name.
 *
 * @return string ALTER TABLE statement.
 */
function mactrack_partition_drop_sql(array $names): string {
	if ($names === []) {
		throw new InvalidArgumentException('No partitions to drop');
	}

	foreach ($names as $name) {
		mactrack_partition_assert_name((string) $name);
	}

	return 'ALTER TABLE `mac_track_ports` DROP PARTITION `' . implode('`, `', $names) . '`';
}
