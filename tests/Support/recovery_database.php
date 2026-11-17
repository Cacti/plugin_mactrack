<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/**
 * Runs the real migration/load SQL in a disposable database. The parent
 * test supplies the connection through MACTRACK_TEST_* environment values.
 * Query failures are injected only at the Cacti database adapter boundary.
 */
require __DIR__ . '/load_function.php';
foreach (['mactrack_ports_load', 'mactrack_scan_dates_rebuild'] as $function) {
	mactrack_test_load_function('lib/mactrack_functions.php', $function);
}
foreach (['mactrack_aggregated_ports_key_columns', 'mactrack_aggregated_ports_key_is_unique', 'mactrack_normalize_aggregated_ports_schema', 'mactrack_rebuild_aggregated_ports'] as $function) {
	mactrack_test_load_function('includes/database.php', $function);
}
foreach (['mactrack_db_table_exists', 'mactrack_db_key_exists'] as $function) {
	mactrack_test_load_function('setup.php', $function);
}
require dirname(__DIR__, 2) . '/lib/mactrack_partitions.php';
require dirname(__DIR__, 2) . '/lib/mactrack_partition_ops.php';

$db = new PDO(getenv('MACTRACK_TEST_DSN'), getenv('MACTRACK_TEST_DB_USER'), getenv('MACTRACK_TEST_DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database_default = 'mactrack_test_' . bin2hex(random_bytes(8));
$database_hostname = 'fixture';
$database_port = 3306;
$database_sessions = ["$database_hostname:$database_port:$database_default" => $db];
$settings = [];
$fault = '';
$reads = 0;

/**
 * Execute a fixture query on the disposable database.
 *
 * @param string $sql SQL to execute.
 * @param array $params Bound parameters.
 *
 * @return PDOStatement
 */
function fixture_query($sql, $params = []) {
	global $db;
	$query = $db->prepare($sql);
	$query->execute($params);

	return $query;
}
/**
 * Read rows through the failure-injecting adapter.
 *
 * @param string $sql SQL to execute.
 *
 * @return array|false
 */
function db_fetch_assoc($sql) {
	return db_fetch_assoc_prepared($sql);
}
/**
 * Inject a read failure or fetch real database rows.
 *
 * @param string $sql SQL to execute.
 * @param array $params Bound parameters.
 *
 * @return array|false
 */
function db_fetch_assoc_prepared($sql, $params = []) {
	global $fault, $reads;
	if ($fault === 'read' && str_contains($sql, 'FROM `mac_track_temp_ports`') && ++$reads === 2) {
		$fault = '';

		return false;
	}
	if ($fault === 'dates' && str_contains($sql, 'PARTITION (`dMaxValue`)')) {
		$fault = '';

		return false;
	}

	return fixture_query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
}
/**
 * Fetch a real schema row.
 *
 * @param string $sql SQL to execute.
 * @param array $params Bound parameters.
 *
 * @return array|false
 */
function db_fetch_row_prepared($sql, $params = []) {
	return fixture_query($sql, $params)->fetch(PDO::FETCH_ASSOC);
}
/**
 * Read a scalar from the fixture database.
 *
 * @param string $sql SQL to execute.
 *
 * @return mixed
 */
function db_fetch_cell($sql) {
	return db_fetch_cell_prepared($sql);
}
/**
 * Read a scalar using bound parameters.
 *
 * @param string $sql SQL to execute.
 * @param array $params Bound parameters.
 *
 * @return mixed
 */
function db_fetch_cell_prepared($sql, $params = []) {
	return fixture_query($sql, $params)->fetchColumn();
}
/**
 * Route writes through the failure-injecting adapter.
 *
 * @param string $sql SQL to execute.
 *
 * @return bool
 */
function db_execute($sql) {
	return db_execute_prepared($sql);
}
/**
 * Fail a selected write once or execute its real SQL.
 *
 * @param string $sql SQL to execute.
 * @param array $params Bound parameters.
 * @param bool $log Cacti logging flag; unused by this fixture.
 *
 * @return bool
 */
function db_execute_prepared($sql, $params = [], $log = true) {
	global $fault;
	if (($fault === 'copy' && str_starts_with($sql, 'INSERT INTO mac_track_aggregated_ports') && $params === ['2026-11-15 00:00:00']) ||
		($fault === 'drop' && str_starts_with($sql, 'DROP TABLE')) ||
		($fault === 'write' && str_starts_with($sql, 'INSERT IGNORE INTO `mac_track_'))) {
		$fault = '';

		return false;
	}
	fixture_query($sql, $params);

	return true;
}
/**
 * Read the fixture settings store.
 *
 * @param string $name Setting name.
 * @param bool $force Cacti cache flag; this store has no cache.
 *
 * @return mixed
 */
function read_config_option($name, $force = false) {
	return $GLOBALS['settings'][$name] ?? '';
}
/**
 * Persist a setting across calls within this scenario.
 *
 * @param string $name Setting name.
 * @param mixed $value Setting value.
 *
 * @return void
 */
function set_config_option($name, $value) {
	$GLOBALS['settings'][$name] = $value;
}
/**
 * Match Cacti's array size contract.
 *
 * @param mixed $value Value to inspect.
 *
 * @return int|false
 */
function cacti_sizeof($value) {
	return is_array($value) ? count($value) : false;
}
/**
 * Index rows by a named column.
 *
 * @param array $rows Source rows.
 * @param string $key Index column.
 * @param string $value Value column.
 *
 * @return array
 */
function array_rekey($rows, $key, $value) {
	return array_column($rows, $value, $key);
}
/**
 * Keep the disposable fixture free of background pollers.
 *
 * @return bool
 */
function mactrack_poller_running() {
	return false;
}
/**
 * Ignore process heartbeats in the isolated fixture.
 *
 * @param mixed $...args Cacti process identifiers.
 *
 * @return void
 */
function heartbeat_process(...$args) {
}
/**
 * Keep fixture stdout reserved for the JSON result.
 *
 * @param mixed $...args Cacti log arguments.
 *
 * @return void
 */
function cacti_log(...$args) {
}

$db->exec("CREATE DATABASE $database_default");
try {
	$db->exec("USE $database_default");
	$db->exec("SET time_zone = '+00:00'");
	$scenario = $argv[1];
	if (str_starts_with($scenario, 'aggregate-')) {
		$db->exec("CREATE TABLE mac_track_aggregated_ports (
			row_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, site_id INT NOT NULL, device_id INT NOT NULL,
			hostname VARCHAR(40) NOT NULL DEFAULT '', device_name VARCHAR(100) NOT NULL DEFAULT '',
			vlan_id VARCHAR(5) NOT NULL DEFAULT '', vlan_name VARCHAR(50) NOT NULL DEFAULT '',
			mac_address VARCHAR(20) NOT NULL, vendor_mac VARCHAR(8) DEFAULT NULL,
			ip_address VARCHAR(20) NOT NULL DEFAULT '', dns_hostname VARCHAR(200) DEFAULT NULL,
			port_number VARCHAR(30) NOT NULL, port_name VARCHAR(50) NOT NULL DEFAULT '',
			date_last TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			first_scan_date TIMESTAMP NULL DEFAULT NULL, count_rec INT NOT NULL DEFAULT 1,
			active_last TINYINT NOT NULL DEFAULT 1, authorized TINYINT NOT NULL DEFAULT 0,
			UNIQUE KEY port_number (port_number, mac_address, ip_address, device_id, site_id, vlan_id, authorized)
		) ENGINE=InnoDB");
		fixture_query('INSERT INTO mac_track_aggregated_ports (site_id, device_id, mac_address, port_number, date_last, first_scan_date, count_rec, port_name)
			VALUES (1, 1, ?, "1", ?, ?, ?, ?)', ['00:11:22:33:44:55', '2026-11-14 00:00:00', '2026-11-12 00:00:00', 10, 'old']);
		fixture_query('INSERT INTO mac_track_aggregated_ports (site_id, device_id, mac_address, port_number, date_last, first_scan_date, count_rec, port_name)
			VALUES (1, 1, ?, "1", ?, ?, ?, ?)', ['001122334455', '2026-11-15 00:00:00', '2026-11-13 00:00:00', 20, 'new']);
		$fault = substr($scenario, strlen('aggregate-'));
		$first = mactrack_rebuild_aggregated_ports();
		$partial = (int) db_fetch_cell('SELECT SUM(count_rec) FROM mac_track_aggregated_ports');
		$source = (int) db_fetch_cell('SELECT SUM(count_rec) FROM mac_track_aggregated_ports_upgrade');
		$retry = mactrack_rebuild_aggregated_ports();
		$result = ['first' => $first, 'partial' => $partial, 'source' => $source, 'retry' => $retry,
			'rows' => db_fetch_assoc('SELECT mac_address, count_rec, first_scan_date, date_last, port_name FROM mac_track_aggregated_ports'),
			'source_exists' => mactrack_db_table_exists('mac_track_aggregated_ports_upgrade'),
			'repeated' => mactrack_rebuild_aggregated_ports()];
	} else {
		$db->exec("CREATE TABLE mac_track_ports (
			site_id INT NOT NULL, device_id INT NOT NULL, hostname VARCHAR(40) DEFAULT '', dns_hostname VARCHAR(200) DEFAULT '', device_name VARCHAR(100) DEFAULT '',
			vlan_id VARCHAR(5) DEFAULT '', vlan_name VARCHAR(50) DEFAULT '', mac_address VARCHAR(20) NOT NULL, vendor_mac VARCHAR(8) DEFAULT '', ip_address VARCHAR(20) DEFAULT '',
			port_number VARCHAR(30) NOT NULL, port_name VARCHAR(50) DEFAULT '', scan_date TIMESTAMP NOT NULL, authorized TINYINT DEFAULT 0,
			PRIMARY KEY(port_number, scan_date, mac_address, device_id)
		) ENGINE=InnoDB");
		$db->exec('CREATE TABLE mac_track_temp_ports LIKE mac_track_ports');
		$scan = '2026-11-16 12:00:00';
		$epoch = strtotime($scan . ' UTC');
		$partition = mactrack_partition_name('scan', $epoch);
		$db->exec('ALTER TABLE mac_track_ports ' . mactrack_partition_create_sql([$partition => $epoch + 1]));
		$insert = $db->prepare('INSERT INTO mac_track_temp_ports (site_id, device_id, mac_address, port_number, scan_date) VALUES (1, 1, ?, ?, ?)');
		$db->beginTransaction();
		for ($i = 0; $i < 1001; $i++) {
			$insert->execute([sprintf('%012X', $i), (string) $i, $scan]);
		}
		$db->commit();
		$plan = ['mode' => str_starts_with($scenario, 'direct-') ? 'direct' : 'exchange', 'partition' => $partition, 'scan_date' => $scan];
		if ($scenario === 'dates') {
			mactrack_partition_load_scan($plan);
			$db->exec('CREATE TABLE mac_track_scan_dates (scan_date TIMESTAMP PRIMARY KEY)');
			fixture_query('INSERT INTO mac_track_scan_dates VALUES (?), (?)', [$scan, '2026-10-01 00:00:00']);
			$fault = 'dates';
			$first = mactrack_scan_dates_rebuild();
			$preserved = db_fetch_assoc('SELECT scan_date FROM mac_track_scan_dates ORDER BY scan_date');
			$retry = mactrack_scan_dates_rebuild();
			$result = ['first' => $first, 'preserved' => $preserved, 'retry' => $retry,
				'dates' => db_fetch_assoc('SELECT scan_date FROM mac_track_scan_dates')];
		} else {
			$fault = substr($scenario, strpos($scenario, '-') + 1);
			$first = mactrack_partition_load_scan($plan);
			$live = (int) db_fetch_cell('SELECT COUNT(*) FROM mac_track_ports');
			$temp = (int) db_fetch_cell('SELECT COUNT(*) FROM mac_track_temp_ports');
			$pending = read_config_option('mt_partition_pending');
			$retry = mactrack_partition_load_scan($plan);
			$result = ['first' => $first, 'partial' => $live, 'temp' => $temp, 'pending' => $pending, 'retry' => $retry,
				'complete' => (int) db_fetch_cell('SELECT COUNT(*) FROM mac_track_ports')];
		}
	}
	print json_encode($result, JSON_THROW_ON_ERROR) . PHP_EOL;
} finally {
	$db->exec("DROP DATABASE $database_default");
}
