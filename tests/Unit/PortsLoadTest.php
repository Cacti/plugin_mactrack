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

/*
 * Scan results move from mac_track_temp_ports in primary-key order, one
 * page at a time on Cacti's own connection, and land as multi-row
 * INSERT IGNORE statements. No INSERT ... SELECT, no ON DUPLICATE KEY.
 */

beforeAll(function () {
	mactrack_test_load(dirname(__DIR__, 2) . '/lib/mactrack_functions.php');
});

beforeEach(function () {
	$GLOBALS['__test_db_calls']        = [];
	$GLOBALS['__test_next_returns']    = [];
	$GLOBALS['__test_matched_returns'] = [];
	$GLOBALS['__test_config_options']  = [];
	$GLOBALS['__test_cacti_log']       = [];
});

/**
 * Builds one temp-table row with the 14 columns the loader copies.
 *
 * @param  int                  $n        Distinguishes rows; used in the key columns.
 * @param  array<string, mixed> $override Column values to replace.
 * @return array<string, mixed>
 */
function mt_load_row(int $n, array $override = []): array {
	return $override + [
		'site_id'      => 1,
		'device_id'    => $n,
		'hostname'     => "switch-$n.example.net",
		'dns_hostname' => "host-$n.example.net",
		'device_name'  => "switch-$n",
		'vlan_id'      => '10',
		'vlan_name'    => 'VLAN10',
		'mac_address'  => sprintf('00:11:22:33:44:%02X', $n),
		'vendor_mac'   => '00:11:22',
		'ip_address'   => "10.0.0.$n",
		'port_number'  => (string) $n,
		'port_name'    => "Gi0/$n",
		'scan_date'    => '2026-11-12 06:00:00',
		'authorized'   => 0,
	];
}

/**
 * Returns the recorded calls of one stubbed function.
 *
 * @param  string $fn Function name.
 * @return array
 */
function mt_load_calls(string $fn): array {
	return array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === $fn));
}

/**
 * Returns the recorded INSERT statements.
 *
 * @return array
 */
function mt_load_inserts(): array {
	return array_values(array_filter(mt_load_calls('db_execute_prepared'), fn ($c) => stripos($c['sql'], 'INSERT') !== false));
}

it('loads nothing from an empty temp table', function () {
	mactrack_test_queue_return('db_fetch_assoc_prepared', []);

	expect(mactrack_ports_load())->toBe(0);
	expect(mt_load_inserts())->toBe([]);
	expect(mt_load_calls('db_fetch_assoc_prepared'))->toHaveCount(1);
});

it('reads in primary-key order and writes one INSERT IGNORE per page', function () {
	mactrack_test_queue_return('db_fetch_assoc_prepared', [mt_load_row(1), mt_load_row(2)]);
	mactrack_test_queue_return('db_fetch_assoc_prepared', [mt_load_row(3)]);

	expect(mactrack_ports_load('mac_track_ports', 2))->toBe(3);

	$inserts = mt_load_inserts();
	expect($inserts)->toHaveCount(2);
	expect($inserts[0]['params'])->toHaveCount(28);
	expect($inserts[1]['params'])->toHaveCount(14);
	expect(substr_count($inserts[0]['sql'], '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'))->toBe(2);
	expect(substr_count($inserts[1]['sql'], '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'))->toBe(1);

	foreach (mt_load_calls('db_fetch_assoc_prepared') as $read) {
		expect(preg_replace('/\s+/', ' ', $read['sql']))->toContain('ORDER BY port_number, scan_date, mac_address, device_id');
		expect($read['sql'])->toContain('LIMIT 2');
		expect($read['sql'])->toContain('FROM `mac_track_temp_ports`');
	}
});

it('writes the columns in the target table order', function () {
	mactrack_test_queue_return('db_fetch_assoc_prepared', [mt_load_row(7)]);

	mactrack_ports_load();

	$insert = mt_load_inserts()[0];
	expect(preg_replace('/\s+/', ' ', $insert['sql']))->toContain('(site_id, device_id, hostname, dns_hostname, device_name, vlan_id, vlan_name, mac_address, vendor_mac, ip_address, port_number, port_name, scan_date, authorized)');
	expect($insert['params'])->toBe(array_values(mt_load_row(7)));
});

it('continues after the last key of the previous page', function () {
	$last = mt_load_row(2);
	mactrack_test_queue_return('db_fetch_assoc_prepared', [mt_load_row(1), $last]);
	mactrack_test_queue_return('db_fetch_assoc_prepared', []);

	mactrack_ports_load('mac_track_ports', 2);

	$reads = mt_load_calls('db_fetch_assoc_prepared');
	expect($reads)->toHaveCount(2);
	expect($reads[0]['params'])->toBe([]);
	expect($reads[1]['params'])->toBe([
		$last['port_number'], $last['port_number'],
		$last['scan_date'], $last['scan_date'],
		$last['mac_address'], $last['mac_address'],
		$last['device_id'],
	]);
	expect(preg_replace('/\s+/', ' ', $reads[1]['sql']))->toContain(
		'WHERE (port_number > ? OR (port_number = ? AND (scan_date > ? OR (scan_date = ? AND (mac_address > ? OR (mac_address = ? AND device_id > ?))))))'
	);
});

it('rejects an unknown source, or a source that is the target', function (string $table, string $source) {
	expect(fn () => mactrack_ports_load($table, 1000, null, true, $source))->toThrow(InvalidArgumentException::class);
})->with([
	'unknown'     => ['mac_track_ports', 'mac_track_ips'],
	'same table'  => ['mac_track_ports_stage', 'mac_track_ports_stage'],
]);

it('counts the INSERTs that fail', function () {
	mactrack_test_queue_return('db_fetch_assoc_prepared', [mt_load_row(1)]);
	mactrack_test_queue_return('db_execute_prepared', false);
	$failed = null;

	expect(mactrack_ports_load('mac_track_ports', 2, null, true, 'mac_track_temp_ports', $failed))->toBe(1);
	expect($failed)->toBe(1);
});

it('copies only one scan date, or only the other dates', function (bool $match, string $where) {
	$first = [mt_load_row(1), mt_load_row(2)];
	mactrack_test_queue_return('db_fetch_assoc_prepared', $first);
	mactrack_test_queue_return('db_fetch_assoc_prepared', []);

	mactrack_ports_load('mac_track_ports', 2, '2026-11-12 06:15:07', $match);

	$reads = mt_load_calls('db_fetch_assoc_prepared');
	$last  = $first[1];
	expect($reads[0]['params'])->toBe(['2026-11-12 06:15:07']);
	expect(preg_replace('/\s+/', ' ', $reads[0]['sql']))->toContain("FROM `mac_track_temp_ports` WHERE $where ORDER BY");
	expect($reads[1]['params'])->toBe([
		'2026-11-12 06:15:07',
		$last['port_number'], $last['port_number'],
		$last['scan_date'], $last['scan_date'],
		$last['mac_address'], $last['mac_address'],
		$last['device_id'],
	]);
	expect(preg_replace('/\s+/', ' ', $reads[1]['sql']))->toContain("WHERE $where AND (port_number > ?");
})->with([
	'this scan'   => [true, 'scan_date = ?'],
	'other dates' => [false, 'scan_date <> ?'],
]);

it('stops after a short page without another read', function () {
	mactrack_test_queue_return('db_fetch_assoc_prepared', [mt_load_row(1)]);

	expect(mactrack_ports_load('mac_track_ports', 2))->toBe(1);
	expect(mt_load_calls('db_fetch_assoc_prepared'))->toHaveCount(1);
});

it('stops after an empty page that follows a full one', function () {
	mactrack_test_queue_return('db_fetch_assoc_prepared', [mt_load_row(1), mt_load_row(2)]);
	mactrack_test_queue_return('db_fetch_assoc_prepared', []);

	expect(mactrack_ports_load('mac_track_ports', 2))->toBe(2);
	expect(mt_load_calls('db_fetch_assoc_prepared'))->toHaveCount(2);
	expect(mt_load_inserts())->toHaveCount(1);
});

it('passes values only as bound parameters', function () {
	$row = mt_load_row(1, ['port_name' => "Gi0/1'; DROP TABLE x; --"]);
	mactrack_test_queue_return('db_fetch_assoc_prepared', [$row]);

	mactrack_ports_load();

	$insert = mt_load_inserts()[0];
	expect($insert['params'])->toContain("Gi0/1'; DROP TABLE x; --");
	expect($insert['sql'])->not->toContain('DROP TABLE');
});

it('uses INSERT IGNORE and never ON DUPLICATE KEY or INSERT ... SELECT', function () {
	mactrack_test_queue_return('db_fetch_assoc_prepared', [mt_load_row(1)]);

	mactrack_ports_load();

	$sql = mt_load_inserts()[0]['sql'];
	expect($sql)->toStartWith('INSERT IGNORE INTO `mac_track_ports`');
	expect(stripos($sql, 'ON DUPLICATE KEY'))->toBeFalse();
	expect(stripos($sql, 'SELECT'))->toBeFalse();
});

it('writes to the stage table when asked', function () {
	mactrack_test_queue_return('db_fetch_assoc_prepared', [mt_load_row(1)]);

	mactrack_ports_load('mac_track_ports_stage');

	expect(mt_load_inserts()[0]['sql'])->toStartWith('INSERT IGNORE INTO `mac_track_ports_stage`');
});

it('refuses an unknown target table', function () {
	expect(fn () => mactrack_ports_load('mac_track_ips'))->toThrow(InvalidArgumentException::class);
	expect($GLOBALS['__test_db_calls'])->toBe([]);
});

it('refuses a batch size below one', function () {
	expect(fn () => mactrack_ports_load('mac_track_ports', 0))->toThrow(InvalidArgumentException::class);
	expect($GLOBALS['__test_db_calls'])->toBe([]);
});

it('transfers scan results with the batch loader', function () {
	$poller = file_get_contents(dirname(__DIR__, 2) . '/poller_mactrack.php');

	$load   = strpos($poller, 'mactrack_partition_load_scan(');
	$record = strpos($poller, 'mactrack_scan_dates_record(');

	expect($load)->not->toBeFalse();
	expect($load)->toBeLessThan($record);
	expect($poller)->not->toContain('INSERT INTO mac_track_ports');
	expect($poller)->not->toContain('ON DUPLICATE KEY UPDATE site_id=VALUES(site_id)');
});
