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

require_once dirname(__DIR__, 2) . '/lib/mactrack_functions.php';
require_once dirname(__DIR__, 2) . '/lib/mactrack_3com.php';
require_once dirname(__DIR__, 2) . '/lib/mactrack_h3c_3com.php';
require_once dirname(__DIR__, 2) . '/lib/mactrack_enterasys_N7.php';
require_once dirname(__DIR__, 2) . '/lib/mactrack_aruba_oscx.php';
require_once dirname(__DIR__, 2) . '/lib/mactrack_linux.php';
require_once dirname(__DIR__, 2) . '/lib/mactrack_dlink.php';
require_once dirname(__DIR__, 2) . '/lib/mactrack_dell.php';
require_once dirname(__DIR__, 2) . '/lib/mactrack_trendnet.php';

/*
 * A bridge port that carries an IP address (linkPort) is an uplink, not a
 * user port, in every vendor scanner. Each scan below sees one user port and
 * one link port and must return only the user port's MAC address.
 */
beforeEach(function () {
	$GLOBALS['__test_next_returns']                           = [];
	$GLOBALS['__test_snmp_walk']                              = [];
	$GLOBALS['__test_config_options']['mt_ignorePorts_delim'] = '-1';
	$GLOBALS['scan_date']                                     = '2026-11-12 00:00:00';

	$this->device = [
		'device_id'          => 1, 'host_id' => 0, 'site_id' => 1, 'device_name' => 'sw1', 'hostname' => '192.0.2.1',
		'snmp_readstring'    => 'x', 'snmp_version' => 2, 'snmp_username' => '', 'snmp_password' => '',
		'snmp_auth_protocol' => '', 'snmp_priv_passphrase' => '', 'snmp_priv_protocol' => '',
		'snmp_context'       => '', 'snmp_engine_id' => '', 'snmp_port' => 161, 'snmp_timeout' => 500,
		'snmp_retries'       => 1, 'max_oids' => 10, 'ignorePorts' => '', 'snmp_sysDescr' => '',
	];

	$this->interfaces = [
		1 => ['ifType' => 6, 'ifName' => 'Gi1', 'ifAlias' => 'user', 'linkPort' => ''],
		2 => ['ifType' => 6, 'ifName' => 'Gi2', 'ifAlias' => 'uplink', 'linkPort' => true],
	];

	$this->walk = function (string $base, array $rows): void {
		$GLOBALS['__test_snmp_walk'][$base] = [];

		foreach ($rows as $index => $value) {
			$GLOBALS['__test_snmp_walk'][$base][] = ['oid' => ltrim($base, '.') . '.' . $index, 'value' => $value];
		}
	};

	// both interfaces up, bridge ports 1 and 2 map to them
	($this->walk)('.1.3.6.1.2.1.2.2.1.8', [1 => '1', 2 => '1']);
	($this->walk)('.1.3.6.1.2.1.17.1.4.1.2', [1 => '1', 2 => '2']);

	// a classic dot1dTpFdb table: MAC index => status, bridge port, address
	$this->dot1d = function (): void {
		$keys = ['0.0.0.0.0.1' => 1, '0.0.0.0.0.2' => 2];

		($this->walk)('.1.3.6.1.2.1.17.4.3.1.3', array_map(fn ($p) => '3', $keys));
		($this->walk)('.1.3.6.1.2.1.17.4.3.1.2', array_map(fn ($p) => (string) $p, $keys));
		($this->walk)('.1.3.6.1.2.1.17.4.3.1.1', [
			'0.0.0.0.0.1' => "\0\0\0\0\0\1", '0.0.0.0.0.2' => "\0\0\0\0\0\2",
		]);
	};

	// a dot1qTpFdb table: vlan.MAC index => status, bridge port
	$this->dot1q = function (string $status_oid): void {
		$keys = ['10.0.0.0.0.0.1' => 1, '10.0.0.0.0.0.2' => 2];

		($this->walk)($status_oid, array_map(fn ($p) => '3', $keys));
		($this->walk)('.1.3.6.1.2.1.17.7.1.2.2.1.2', array_map(fn ($p) => (string) $p, $keys));
		($this->walk)('.1.3.6.1.2.1.17.7.1.4.3.1.1', [10 => 'users']);
	};
});

afterEach(function () {
	unset($GLOBALS['__test_snmp_walk'], $GLOBALS['__test_config_options']['mt_ignorePorts_delim']);
});

it('skips link ports in the 3Com scanner', function () {
	($this->dot1d)();

	$ports = get_3Com_base_dot1dTpFdbEntry_ports('site', $this->device, $this->interfaces, '', false);

	expect(array_column($ports, 'mac_address'))->toBe(['000000000001']);
	expect(array_column($ports, 'port_name'))->toBe(['Gi1']);
});

it('skips link ports in the H3C scanner', function () {
	($this->dot1d)();
	($this->walk)('.1.3.6.1.4.1.25506.8.35.2.1.1.1.1', [10 => '10']);
	($this->walk)('.1.3.6.1.4.1.25506.8.35.2.1.1.1.2', [10 => 'users']);
	($this->walk)('.1.3.6.1.2.1.17.7.1.4.5.1.1', [1 => '10', 2 => '10']);

	$ports = get_h3c_3com_dot1dTpFdbEntry_ports('site', $this->device, $this->interfaces, '', false);

	expect(array_column($ports, 'mac_address'))->toBe(['000000000001']);
});

it('skips link ports in the Enterasys N7 scanner', function () {
	($this->dot1d)();

	$ports = get_enterasys_N7_dot1dTpFdbEntry_ports('site', $this->device, $this->interfaces, '', false);

	expect(array_column($ports, 'mac_address'))->toBe(['000000000001']);
});

it('skips link ports in the Aruba CX scanner and reads the MAC from the index', function () {
	($this->dot1d)();
	($this->walk)('.1.3.6.1.2.1.47.1.2.1.1.2', [10 => 'users']);
	($this->walk)('.1.3.6.1.2.1.17.7.1.2.2.1.2', ['10.0.0.0.0.0.1' => '1', '10.0.0.0.0.0.2' => '2']);

	$ports = get_aruba_oscx_dot1dTpFdbEntry_ports('site', $this->device, $this->interfaces, '', false);

	expect(array_column($ports, 'mac_address'))->toBe(['000000000001']);
});

it('skips link ports in the Linux scanner', function () {
	($this->walk)('.1.3.6.1.2.1.2.2.1.1', [1 => '1', 2 => '2']);
	($this->walk)('.1.3.6.1.2.1.4.22.1.4', ['1.10.0.0.1' => '3', '2.10.0.0.2' => '3']);
	($this->walk)('.1.3.6.1.2.1.4.22.1.1', ['1.10.0.0.1' => '1', '2.10.0.0.2' => '2']);
	($this->walk)('.1.3.6.1.2.1.4.22.1.2', ['1.10.0.0.1' => "\0\0\0\0\0\1", '2.10.0.0.2' => "\0\0\0\0\0\2"]);

	$ports = get_linux_dot1dTpFdbEntry_ports('site', $this->device, $this->interfaces, '', false);

	expect(array_column($ports, 'mac_address'))->toBe(['000000000001']);
});

it('skips link ports in the D-Link scanner', function () {
	($this->dot1q)('1.3.6.1.2.1.17.7.1.2.2.1.3');
	($this->walk)('.1.3.6.1.2.1.31.1.1.1.18', [1 => 'user', 2 => 'uplink']);

	$ports = get_dlink_l2_dot1dTpFdbEntry_ports('site', $this->device, $this->interfaces, '', false);

	expect(array_column($ports, 'mac_address'))->toBe(['00:00:00:00:00:01']);
	expect(array_column($ports, 'vlan_name'))->toBe(['users']);
});

it('reads the VLAN and key of each D-Link forwarding entry', function () {
	($this->walk)('.1.3.6.1.2.1.17.7.1.2.2.1.2', ['10.0.17.34.51.68.85' => '1']);
	mactrack_test_queue_return('cacti_snmp_get', 'users');

	$vlans = xform_dlink_vlan_associations($this->device);

	expect($vlans)->toBe([['vlan_id' => '10', 'key' => '0.17.34.51.68.85', 'vlan_name' => 'users']]);
});

it('reads the VLAN and key of each Enterasys N7 forwarding entry', function () {
	($this->walk)('.1.3.6.1.2.1.17.7.1.2.2.1.2', ['10.0.17.34.51.68.85' => '1']);
	mactrack_test_queue_return('cacti_snmp_get', 'users');

	expect(xform_enterasys_N7_vlan_associations($this->device))->toBe(['0.17.34.51.68.85' => '10']);
});

it('skips the bridge root port of a Dell scan inside a port range', function () {
	($this->dot1q)('.1.3.6.1.2.1.17.7.1.2.2.1.3');
	// bridge root port 1
	mactrack_test_queue_return('cacti_snmp_get', '1');

	$ports = get_base_dell_dot1qFdb_ports('site', $this->device, $this->interfaces, '', false, 1, 5);

	expect(array_column($ports, 'port_number'))->toBe(['2']);
});

it('skips the bridge root port of a TrendNet scan inside a port range', function () {
	($this->dot1q)('.1.3.6.1.2.1.17.7.1.2.2.1.3');
	mactrack_test_queue_return('cacti_snmp_get', '1');
	$this->device += ['vlans_total' => 0, 'ports_trunk' => 0];

	$ports = get_base_trendnet_dot1qFdb_ports('site', $this->device, $this->interfaces, '', false, 1, 5);

	expect(array_column($ports, 'port_number'))->toBe(['2']);
});

it('skips the bridge root port of a wireless scan inside a port range', function () {
	($this->dot1d)();
	mactrack_test_queue_return('cacti_snmp_get', '1');

	$ports = get_base_wireless_dot1dTpFdbEntry_ports('site', $this->device, $this->interfaces, '', false, 2, 5);

	expect(array_column($ports, 'mac_address'))->toBe(['000000000002']);
});
