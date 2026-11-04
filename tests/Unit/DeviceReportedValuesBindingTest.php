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
 * The scanners store strings the device reports (interface names, aliases,
 * descriptions, VLAN names, MACs, IPs, 802.1x user names). Each test runs a
 * scanner against canned SNMP walks and checks that those strings reach the
 * database as bound parameters, not as part of the statement text.
 */

require_once dirname(__DIR__, 2) . '/lib/mactrack_functions.php';

foreach (['cisco', 'aruba_oscx', 'enterasys_N7', 'h3c_3com', 'extreme'] as $vendor) {
	mactrack_test_load(dirname(__DIR__, 2) . '/lib/mactrack_' . $vendor . '.php');
}

beforeEach(function () {
	$GLOBALS['__test_db_calls']        = [];
	$GLOBALS['__test_next_returns']    = [];
	$GLOBALS['__test_matched_returns'] = [];
	$GLOBALS['__test_snmp_walk']       = [];
	$GLOBALS['scan_date']              = '2026-10-22 10:00:00';
	$GLOBALS['debug']                  = false;

	$this->device = [
		'site_id'              => 4,
		'device_id'            => 9,
		'host_id'              => 0,
		'hostname'             => 'sw-lab-01',
		'device_name'          => 'Lab switch',
		'snmp_readstring'      => 'public',
		'snmp_version'         => '2',
		'snmp_username'        => '',
		'snmp_password'        => '',
		'snmp_auth_protocol'   => '',
		'snmp_priv_passphrase' => '',
		'snmp_priv_protocol'   => '',
		'snmp_context'         => '',
		'snmp_port'            => 161,
		'snmp_timeout'         => 500,
		'snmp_retries'         => 1,
		'max_oids'             => 10,
		'snmp_engine_id'       => '',
		'snmp_sysUptime'       => '123456',
		'snmp_sysDescr'        => 'test agent',
		'last_rundate'         => '',
	];
});

/**
 * Canned walk rows for one OID, keyed by the index that follows it.
 *
 * @param string $oid  Base OID as the scanner passes it.
 * @param array  $rows index => value
 *
 * @return void
 */
function mactrack_test_walk($oid, $rows) {
	foreach ($rows as $index => $value) {
		$GLOBALS['__test_snmp_walk'][$oid][] = ['oid' => $oid . '.' . $index, 'value' => $value];
	}
}

/**
 * The single recorded call whose SQL starts with $prefix.
 *
 * @param string $prefix Leading SQL keywords, e.g. 'REPLACE INTO mac_track_ips'.
 *
 * @return array
 */
function mactrack_test_find_call($prefix) {
	$found = [];

	foreach ($GLOBALS['__test_db_calls'] as $call) {
		if (strpos(ltrim($call['sql']), $prefix) === 0) {
			$found[] = $call;
		}
	}

	expect($found)->toHaveCount(1);

	return $found[0];
}

/**
 * Assert the call is prepared, binds every value, and carries none of them in its text.
 *
 * @param array $call   Recorded stub call.
 * @param array $values Device-reported values that must be bound.
 *
 * @return void
 */
function mactrack_test_expect_bound($call, $values) {
	expect($call['fn'])->toBe('db_execute_prepared');
	expect(substr_count($call['sql'], '?'))->toBe(count($call['params']));

	foreach ($values as $value) {
		expect($call['params'])->toContain($value);
		expect($call['sql'])->not->toContain($value);
	}
}

test('interface rows bind the names, aliases, descriptions and MAC the device reports', function () {
	mactrack_test_walk('.1.3.6.1.2.1.2.2.1.1', ['5' => '5']);
	mactrack_test_walk('.1.3.6.1.2.1.2.2.1.3', ['5' => '6']);
	mactrack_test_walk('.1.3.6.1.2.1.31.1.1.1.1', ['5' => 'Gi1/0/5-lab']);
	mactrack_test_walk('.1.3.6.1.2.1.31.1.1.1.18', ['5' => 'printer room uplink']);
	mactrack_test_walk('.1.3.6.1.2.1.2.2.1.2', ['5' => 'GigabitEthernet1/0/5 descr']);
	mactrack_test_walk('.1.3.6.1.2.1.2.2.1.6', ['5' => '00:11:22:33:44:55']);
	mactrack_test_walk('.1.3.6.1.2.1.2.2.1.7', ['5' => 'up(1)']);
	mactrack_test_walk('.1.3.6.1.2.1.2.2.1.8', ['5' => 'down(2)']);

	$ifIndexes = [];

	build_InterfacesTable($this->device, $ifIndexes, false, true);

	$call = mactrack_test_find_call('INSERT INTO mac_track_interfaces');

	mactrack_test_expect_bound($call, ['Gi1/0/5-lab', 'printer room uplink', 'GigabitEthernet1/0/5 descr', '001122334455']);

	expect($call['params'])->toHaveCount(56);
	expect(array_slice($call['params'], 0, 7))->toBe([4, 9, '123456', '5', '6', 'Gi1/0/5-lab', 'printer room uplink']);
	expect($call['params'][14])->toBe('GigabitEthernet1/0/5 descr');
	expect($call['params'][16])->toBe('001122334455');
	expect($call['params'][17])->toBe(1);
	expect($call['params'][18])->toBe(0);
	expect($call['params'][55])->toBe('1');
});

test('a device with no stored uptime binds an empty sysUptime, not NULL', function () {
	mactrack_test_walk('.1.3.6.1.2.1.2.2.1.1', ['5' => '5']);

	$this->device['snmp_sysUptime'] = null;
	$ifIndexes                      = [];

	build_InterfacesTable($this->device, $ifIndexes, false, false);

	$call = mactrack_test_find_call('INSERT INTO mac_track_interfaces');

	expect($call['params'][2])->toBe('');
	expect($call['params'])->not->toContain(null);
});

test('interface graph links bind the interface name read back from the device', function () {
	mactrack_test_queue_return('db_fetch_assoc_prepared', [
		['id' => 31, 'field_value' => 'Gi1/0/5-lab', 'snmp_query_id' => 2],
	]);
	mactrack_test_queue_return('db_fetch_assoc_prepared', [
		['id' => 41, 'graph_template_id' => 7],
	]);
	mactrack_test_queue_return('db_fetch_assoc', [
		['device_id' => 9, 'ifIndex' => 5, 'ifName' => 'Gi1/0/5-lab'],
	]);

	mactrack_find_host_graphs(9, 12);

	$call = mactrack_test_find_call('INSERT INTO mac_track_interface_graphs');

	mactrack_test_expect_bound($call, ['Gi1/0/5-lab']);

	expect($call['params'])->toBe([5, 'Gi1/0/5-lab', 41, 9, 12, 2, 7, 'Gi1/0/5-lab', 'ifName']);
});

test('Cisco DHCP snooping rows bind the port, MAC and IP the device reports', function () {
	mactrack_test_walk('.1.3.6.1.4.1.9.9.380.1.4.1.1.5', ['10.0.17.34.51.68.85' => 'Gi2/0/7']);
	mactrack_test_walk('.1.3.6.1.4.1.9.9.380.1.4.1.1.4', ['10.0.17.34.51.68.85' => '192.0.2.10']);
	mactrack_test_walk('.1.3.6.1.4.1.9.9.46.1.3.1.1.2', ['10' => '1']);
	mactrack_test_walk('.1.3.6.1.4.1.9.9.46.1.3.1.1.4', ['10' => 'users']);
	mactrack_test_walk('.1.3.6.1.2.1.17.4.3.1.1', ['0.17.34.51.68.85' => '00 11 22 33 44 55']);
	mactrack_test_queue_return('cacti_snmp_get', '3');

	// xform_stripped_oid() strips the OID without its leading dot.
	foreach ($GLOBALS['__test_snmp_walk'] as $oid => $rows) {
		foreach ($rows as $i => $row) {
			$GLOBALS['__test_snmp_walk'][$oid][$i]['oid'] = ltrim($row['oid'], '.');
		}
	}

	get_cisco_dhcpsnooping_table([], $this->device);

	$call = mactrack_test_find_call('REPLACE INTO mac_track_ips');

	mactrack_test_expect_bound($call, ['Gi2/0/7', '001122334455', '192.0.2.10', 'sw-lab-01', 'Lab switch']);

	expect($call['params'])->toBe([4, 9, 'sw-lab-01', 'Lab switch', 'Gi2/0/7', '001122334455', '192.0.2.10', '2026-10-22 10:00:00']);
});

test('Cisco VRF ARP rows bind the IP and MAC the device reports', function () {
	mactrack_test_walk('.1.3.6.1.2.1.3.1.1.2', ['12.1.192.0.2.20' => '0 11 22 33 44 66']);

	get_cisco_vrf_arp_table([], $this->device);

	$call = mactrack_test_find_call('INSERT INTO mac_track_arp');

	mactrack_test_expect_bound($call, ['192.0.2.20', '0:11:22:33:44:66']);

	expect($call['params'])->toBe([4, '192.0.2.20', '0:11:22:33:44:66', '2026-10-22 10:00:00', '192.0.2.20', '2026-10-22 10:00:00']);
});

test('Cisco 802.1x rows bind the user name, domain and addresses the device reports', function () {
	$base = '1.3.6.1.4.1.9.9.656.1.4.1.1';

	foreach (['10' => 'lab-user-17', '2' => '00:11:22:33:44:77', '4' => '192.0.2.30', '6' => 'data', '5' => 'authorized'] as $column => $value) {
		$GLOBALS['__test_snmp_walk']['.' . $base . '.' . $column][] = ['oid' => $base . '.' . $column . '.10101.4', 'value' => $value];
	}

	get_cisco_dot1x_table([], $this->device);

	$call = mactrack_test_find_call('REPLACE INTO mac_track_dot1x');

	mactrack_test_expect_bound($call, ['lab-user-17', '001122334477', '192.0.2.30', 'authorized']);

	expect($call['params'])->toBe([4, 9, 'sw-lab-01', 'Lab switch', 'lab-user-17', '001122334477', '192.0.2.30', 'data', 'authorized', '10101', '2026-10-22 10:00:00']);
});

test('Aruba AOS-CX ARP rows bind the port, MAC and IP the device reports', function () {
	mactrack_test_walk('.1.3.6.1.2.1.17.7.1.2.2.1.2', ['1.0.17.34.51.68.85' => '28']);
	mactrack_test_walk('.1.3.6.1.2.1.4.35.1.4', ['16777415.1.4.192.0.2.40' => '00 11 22 33 44 55']);

	get_aruba_oscx_arp_table([], $this->device);

	$call = mactrack_test_find_call('REPLACE INTO mac_track_ips');

	mactrack_test_expect_bound($call, ['00:11:22:33:44:55', '192.0.2.40']);

	expect($call['params'])->toBe([4, 9, 'sw-lab-01', 'Lab switch', '28', '00:11:22:33:44:55', '192.0.2.40', '2026-10-22 10:00:00']);
});

test('Enterasys N7 alias rows bind the MAC and IP text the device reports', function () {
	mactrack_test_walk('.1.3.6.1.4.1.52.4.1.3.7.1.1.1.1.3', ['100.1' => '7']);
	mactrack_test_walk('.1.3.6.1.4.1.52.4.1.3.7.1.1.1.1.4', ['100.1' => '00:11:22:33:44:88']);
	mactrack_test_walk('.1.3.6.1.4.1.52.4.1.3.7.1.1.1.1.6', ['100.1' => '1']);
	mactrack_test_walk('.1.3.6.1.4.1.52.4.1.3.7.1.1.1.1.9', ['100.1' => '192.0.2.50']);

	get_CTAlias_table([], $this->device);

	$call = mactrack_test_find_call('REPLACE INTO mac_track_ips');

	mactrack_test_expect_bound($call, ['001122334488', '192.0.2.50']);

	expect($call['params'])->toBe([4, 9, 'sw-lab-01', 'Lab switch', '7', '001122334488', '192.0.2.50', '2026-10-22 10:00:00']);
});

test('H3C/3Com ARP rows bind the interface, MAC and IP the device reports', function () {
	mactrack_test_walk('.1.3.6.1.2.1.3.1.1.1', ['9.1.192.0.2.60' => 'Vlan-interface60']);
	mactrack_test_walk('.1.3.6.1.2.1.4.22.1.2', ['9.192.0.2.60' => '00:11:22:33:44:99']);
	mactrack_test_walk('.1.3.6.1.2.1.3.1.1.3', ['9.1.192.0.2.60' => '192.0.2.60']);

	get_h3c_3com_arp_table([], $this->device);

	$call = mactrack_test_find_call('REPLACE INTO mac_track_ips');

	mactrack_test_expect_bound($call, ['Vlan-interface60', '001122334499', '192.0.2.60']);

	expect($call['params'])->toBe([4, 9, 'sw-lab-01', 'Lab switch', 'Vlan-interface60', '001122334499', '192.0.2.60', '2026-10-22 10:00:00']);
});

test('Extreme ARP rows bind the interface description, MAC and IP the device reports', function () {
	$walks = [
		'.1.3.6.1.2.1.3.1.1.1' => ['9.1.192.0.2.70' => '9'],
		'.1.3.6.1.2.1.3.1.1.2' => ['9.1.192.0.2.70' => '00:11:22:33:44:aa'],
		'.1.3.6.1.2.1.3.1.1.3' => ['9.1.192.0.2.70' => 'C0 00 02 46'],
		'.1.3.6.1.2.1.2.2.1.2' => ['9' => 'vlan70 lab uplink'],
	];

	foreach ($walks as $oid => $rows) {
		foreach ($rows as $index => $value) {
			$GLOBALS['__test_snmp_walk'][$oid][] = ['oid' => ltrim($oid, '.') . '.' . $index, 'value' => $value];
		}
	}

	get_extreme_arp_table([], $this->device, true);

	$call = mactrack_test_find_call('REPLACE INTO mac_track_ips');

	mactrack_test_expect_bound($call, ['vlan70 lab uplink', '0011223344AA', '192.0.2.70']);

	expect($call['params'])->toBe([4, 9, 'sw-lab-01', 'Lab switch', 'vlan70 lab uplink', '0011223344AA', '192.0.2.70', '2026-10-22 10:00:00']);
});
