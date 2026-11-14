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

require_once dirname(__DIR__, 2) . '/setup.php';
require_once dirname(__DIR__, 2) . '/includes/database.php';
require_once dirname(__DIR__, 2) . '/lib/mactrack_functions.php';

beforeEach(function () {
	$GLOBALS['__test_db_calls']        = [];
	$GLOBALS['__test_next_returns']    = [];
	$GLOBALS['__test_matched_returns'] = [];
	$GLOBALS['__test_config_options']  = ['mt_convert_readstrings' => 'on'];

	$this->device = [
		'device_id'          => 1, 'host_id' => 0, 'site_id' => 1, 'device_name' => 'sw1', 'hostname' => '192.0.2.1',
		'snmp_readstring'    => 'x', 'snmp_version' => 2, 'snmp_username' => '', 'snmp_password' => '',
		'snmp_auth_protocol' => '', 'snmp_priv_passphrase' => '', 'snmp_priv_protocol' => '',
		'snmp_context'       => '', 'snmp_engine_id' => '', 'snmp_port' => 161, 'snmp_timeout' => 500,
		'snmp_retries'       => 1, 'max_oids' => 10, 'ignorePorts' => '', 'snmp_sysDescr' => '',
	];
});

afterEach(function () {
	unset($GLOBALS['__test_snmp_walk'], $GLOBALS['__test_config_options']['mt_ignorePorts_delim']);
});

it('widens a tinyint notify_schedule column and reads the old 255 as daily', function () {
	mactrack_test_queue_return_for('db_fetch_assoc_prepared', 'SHOW COLUMNS FROM mac_track_macwatch', [
		['Field' => 'notify_schedule', 'Type' => 'tinyint(3) unsigned'],
	]);
	mactrack_test_queue_return('db_fetch_cell', '0');
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'COUNT(*) FROM mac_track_sites', '1');
	mactrack_test_queue_return_for('db_fetch_assoc', 'SHOW COLUMNS FROM mac_track_ips', [
		['Field' => 'port_number', 'Type' => 'varchar(30)'],
	]);

	mactrack_database_upgrade();

	$sql = array_column(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_execute_prepared'), 'sql');

	expect($sql)->toContain("ALTER TABLE mac_track_macwatch MODIFY COLUMN notify_schedule smallint(5) unsigned NOT NULL default '1'");
	expect($sql)->toContain('UPDATE mac_track_macwatch SET notify_schedule = 1440 WHERE notify_schedule = 255');
});

it('leaves an already widened notify_schedule column alone', function () {
	mactrack_test_queue_return_for('db_fetch_assoc_prepared', 'SHOW COLUMNS FROM mac_track_macwatch', [
		['Field' => 'notify_schedule', 'Type' => 'smallint(5) unsigned'],
	]);
	mactrack_test_queue_return('db_fetch_cell', '0');
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'COUNT(*) FROM mac_track_sites', '1');
	mactrack_test_queue_return_for('db_fetch_assoc', 'SHOW COLUMNS FROM mac_track_ips', [
		['Field' => 'port_number', 'Type' => 'varchar(30)'],
	]);

	mactrack_database_upgrade();

	$sql = array_column($GLOBALS['__test_db_calls'], 'sql');

	expect(array_filter($sql, fn ($s) => strpos($s, 'notify_schedule') !== false))->toBe([]);
});

it('builds the MacWatch form with schedules in minutes', function () {
	global $fields_mactrack_macw_edit;

	mactrack_config_form();

	$array = $fields_mactrack_macw_edit['notify_schedule']['array'];

	expect(array_keys($array))->toBe([1, 2, 60, 240, 720, 1440]);
});

it('stores ARP entries in one batch and looks up a missing IP in the ARP history', function () {
	$GLOBALS['scan_date'] = '2026-11-12 00:00:00';

	mactrack_test_snmp_walk('.1.3.6.1.2.1.3.1.1.1', ['1.10.0.0.1' => '1', '1.10.0.0.2' => '1']);
	mactrack_test_snmp_walk('.1.3.6.1.2.1.3.1.1.2', ['1.10.0.0.1' => '00:11:22:33:44:55', '1.10.0.0.2' => 'aa:bb:cc:dd:ee:ff']);
	mactrack_test_snmp_walk('.1.3.6.1.2.1.3.1.1.3', ['1.10.0.0.1' => '10.0.0.1']);
	mactrack_test_snmp_walk('.1.3.6.1.2.1.31.1.1.1.1', [1 => 'Gi1/0/1']);
	mactrack_test_queue_return('db_fetch_cell_prepared', '10.0.0.2');

	get_standard_arp_table([], $this->device);

	$writes  = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => strpos($c['sql'], 'REPLACE INTO mac_track_ips') !== false));
	$lookups = array_filter($GLOBALS['__test_db_calls'], fn ($c) => strpos($c['sql'], 'FROM mac_track_arp') !== false);

	expect($writes)->toHaveCount(1);
	expect($writes[0]['sql'])->toContain('VALUES (?, ?, ?, ?, ?, ?, ?, ?), (?, ?, ?, ?, ?, ?, ?, ?)');
	expect($writes[0]['params'])->toBe([
		1, 1, '192.0.2.1', 'sw1', 'Gi1/0/1', '001122334455', '10.0.0.1', '2026-11-12 00:00:00',
		1, 1, '192.0.2.1', 'sw1', 'Gi1/0/1', 'AABBCCDDEEFF', '10.0.0.2', '2026-11-12 00:00:00',
	]);
	expect(array_values($lookups)[0]['params'])->toBe(['AABBCCDDEEFF', 1]);
	expect($this->device['ips_total'])->toBe(2);
});

it('splits a large ARP table into batches of 500', function () {
	$GLOBALS['scan_date'] = '2026-11-12 00:00:00';

	$ifs = $macs = $ips = [];

	for ($i = 0; $i < 501; $i++) {
		$key        = '1.10.0.' . intdiv($i, 250) . '.' . ($i % 250);
		$ifs[$key]  = '1';
		$macs[$key] = sprintf('00:11:22:33:%02x:%02x', intdiv($i, 256), $i % 256);
		$ips[$key]  = '10.0.' . intdiv($i, 250) . '.' . ($i % 250);
	}

	mactrack_test_snmp_walk('.1.3.6.1.2.1.3.1.1.1', $ifs);
	mactrack_test_snmp_walk('.1.3.6.1.2.1.3.1.1.2', $macs);
	mactrack_test_snmp_walk('.1.3.6.1.2.1.3.1.1.3', $ips);

	get_standard_arp_table([], $this->device);

	$writes = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => strpos($c['sql'], 'REPLACE INTO mac_track_ips') !== false));

	expect(array_map(fn ($c) => count($c['params']) / 8, $writes))->toBe([500, 1]);
});

it('skips the bridge root port and ports outside the range of a dot1d scan', function () {
	$GLOBALS['__test_config_options']['mt_ignorePorts_delim'] = '-1';

	mactrack_test_snmp_walk('.1.3.6.1.2.1.17.1.4.1.2', [1 => '1', 2 => '2', 3 => '3', 9 => '9']);
	mactrack_test_snmp_walk('.1.3.6.1.2.1.2.2.1.8', [1 => '1', 2 => '1', 3 => '1', 9 => '1']);
	mactrack_test_snmp_walk('.1.3.6.1.2.1.17.4.3.1.3', ['0.0.0.0.0.1' => '3', '0.0.0.0.0.2' => '3', '0.0.0.0.0.3' => '3', '0.0.0.0.0.9' => '3']);
	mactrack_test_snmp_walk('.1.3.6.1.2.1.17.4.3.1.2', ['0.0.0.0.0.1' => '1', '0.0.0.0.0.2' => '2', '0.0.0.0.0.3' => '3', '0.0.0.0.0.9' => '9']);
	mactrack_test_snmp_walk('.1.3.6.1.2.1.17.4.3.1.1', [
		'0.0.0.0.0.1' => "\0\0\0\0\0\1", '0.0.0.0.0.2' => "\0\0\0\0\0\2",
		'0.0.0.0.0.3' => "\0\0\0\0\0\3", '0.0.0.0.0.9' => "\0\0\0\0\0\9",
	]);
	// bridge root port 1
	mactrack_test_queue_return('cacti_snmp_get', '1');

	$ifInterfaces = [];

	foreach ([1, 2, 3, 9] as $n) {
		$ifInterfaces[$n] = ['ifType' => 6];
	}

	$ports = get_base_dot1dTpFdbEntry_ports('site', $this->device, $ifInterfaces, '', false, 2, 5);

	expect(array_column($ports, 'mac_address'))->toBe(['000000000002', '000000000003']);
});
