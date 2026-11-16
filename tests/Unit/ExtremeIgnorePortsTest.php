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
require_once dirname(__DIR__, 2) . '/lib/mactrack_extreme.php';

beforeEach(function () {
	$GLOBALS['__test_db_calls']                               = [];
	$GLOBALS['__test_next_returns']                           = [];
	$GLOBALS['__test_snmp_walk']                              = [];
	$GLOBALS['__test_config_options']['mt_ignorePorts_delim'] = '-1';
	$GLOBALS['scan_date']                                     = '2026-11-12 00:00:00';

	mactrack_test_snmp_walk('.1.3.6.1.4.1.1916.1.2.1.2.1.10', [1 => '10']);
	mactrack_test_snmp_walk('.1.3.6.1.4.1.1916.1.2.1.2.1.2', [1 => 'users']);
	mactrack_test_snmp_walk('.1.3.6.1.2.1.2.2.1.1', [5 => '5', 6 => '6']);
	mactrack_test_snmp_walk('.1.3.6.1.2.1.2.2.1.3', [5 => '6', 6 => '6']);
	mactrack_test_snmp_walk('.1.3.6.1.2.1.31.1.1.1.1', [5 => '1:5', 6 => '1:6']);
	mactrack_test_snmp_walk('.1.3.6.1.2.1.31.1.1.1.18', [5 => 'desk', 6 => 'phone']);
	// two learned MACs in VLAN 1, one behind 1:5 and one behind 1:6
	mactrack_test_snmp_walk('.1.3.6.1.4.1.1916.1.16.4.1.1', ['a' => '00:11:22:33:44:55', 'b' => '00:11:22:33:44:66']);
	mactrack_test_snmp_walk('.1.3.6.1.4.1.1916.1.16.4.1.2', ['a' => '1', 'b' => '1']);
	mactrack_test_snmp_walk('.1.3.6.1.4.1.1916.1.16.4.1.3', ['a' => '5', 'b' => '6']);
	mactrack_test_snmp_walk('.1.3.6.1.4.1.1916.1.16.4.1.4', ['a' => '3', 'b' => '3']);

	$this->device = [
		'device_id'          => 1, 'host_id' => 0, 'site_id' => 1, 'device_name' => 'sw1', 'hostname' => '192.0.2.1',
		'snmp_readstring'    => 'x', 'snmp_version' => 2, 'snmp_username' => '', 'snmp_password' => '',
		'snmp_auth_protocol' => '', 'snmp_priv_passphrase' => '', 'snmp_priv_protocol' => '',
		'snmp_context'       => '', 'snmp_engine_id' => '', 'snmp_port' => 161, 'snmp_timeout' => 500,
		'snmp_retries'       => 1, 'max_oids' => 10, 'ignorePorts' => '', 'snmp_sysDescr' => '',
		'last_rundate'       => '2026-11-12 00:00:00',
	];
});

afterEach(function () {
	unset($GLOBALS['__test_snmp_walk'], $GLOBALS['__test_config_options']['mt_ignorePorts_delim']);
});

function extreme_stored_ports(): array {
	$ports = [];

	foreach ($GLOBALS['__test_db_calls'] as $call) {
		if (strpos($call['sql'], 'REPLACE INTO mac_track_temp_ports') !== false) {
			// vlan_id, vlan_name, mac_address, port_number per row
			for ($i = 4; $i + 3 < count($call['params']); $i += 8) {
				$ports[] = $call['params'][$i + 3];
			}
		}
	}

	return $ports;
}

it('stores every learned port when no ports are ignored', function () {
	$result = get_extreme_switch_ports('site', $this->device);

	expect($result['macs_active'])->toBe(2);
	expect($result['ports_active'])->toBe(2);
});

it('skips ignored ports, including stacked names that contain a colon', function (string $ignore) {
	$this->device['ignorePorts'] = $ignore;

	$result = get_extreme_switch_ports('site', $this->device);

	expect($result['macs_active'])->toBe(1);
	expect($result['ports_active'])->toBe(1);
	expect(extreme_stored_ports())->not->toContain('1:5');
})->with([
	'single port'  => ['1:5'],
	'pipe list'    => ['1:5|3:9'],
	'space list'   => ['1:5 3:9'],
	'comma list'   => ['3:9, 1:5'],
]);

it('honours an explicitly configured delimiter', function () {
	$GLOBALS['__test_config_options']['mt_ignorePorts_delim'] = '|';
	$this->device['ignorePorts']                              = '1:5|1:6';

	$result = get_extreme_switch_ports('site', $this->device);

	expect($result['macs_active'])->toBe(0);
});

it('applies the ignore list to ExtremeWare devices as well', function () {
	mactrack_test_snmp_walk('.1.3.6.1.4.1.1916.1.16.1.1.3', ['1.1' => '00:11:22:33:44:55', '1.2' => '00:11:22:33:44:66']);
	mactrack_test_snmp_walk('.1.3.6.1.4.1.1916.1.16.1.1.4', ['1.1' => '5', '1.2' => '6']);
	mactrack_test_snmp_walk('.1.3.6.1.4.1.1916.1.16.1.1.5', ['1.1' => '3', '1.2' => '3']);
	$this->device['ignorePorts'] = '1:6';

	$result = get_extreme_extremeware_switch_ports('site', $this->device);

	expect($result['macs_active'])->toBe(1);
	expect(extreme_stored_ports())->not->toContain('1:6');
});
