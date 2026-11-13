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
require_once dirname(__DIR__, 2) . '/lib/mactrack_juniper.php';

beforeEach(function () {
	$GLOBALS['__test_db_calls']                               = [];
	$GLOBALS['__test_next_returns']                           = [];
	$GLOBALS['__test_snmp_walk']                              = [];
	$GLOBALS['__test_config_options']['mt_ignorePorts_delim'] = '-1';
	$GLOBALS['scan_date']                                     = '2026-11-12 00:00:00';

	$walk = function (string $base, array $rows): void {
		$GLOBALS['__test_snmp_walk'][$base] = [];

		foreach ($rows as $index => $value) {
			$GLOBALS['__test_snmp_walk'][$base][] = ['oid' => ltrim($base, '.') . '.' . $index, 'value' => $value];
		}
	};

	$walk('.1.3.6.1.4.1.2636.3.40.1.5.1.5.1.5', [10 => '10']);
	$walk('.1.3.6.1.4.1.2636.3.40.1.5.1.5.1.2', [10 => 'users']);
	$walk('.1.3.6.1.2.1.2.2.1.1', [5 => '5']);
	$walk('.1.3.6.1.2.1.2.2.1.3', [5 => '6']);
	$walk('.1.3.6.1.2.1.31.1.1.1.1', [5 => 'ge-0/0/5']);
	// MAC 00:11:22:33:44:55 in VLAN 10 sits behind bridge port 1, which is ifIndex 5
	$walk('.1.3.6.1.2.1.17.7.1.2.2.1.2', ['10.0.17.34.51.68.85' => '1']);
	$walk('.1.3.6.1.2.1.17.1.4.1.2', [1 => '5']);

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

it('reads the VLAN and MAC from the forwarding table index and the port through the bridge map', function () {
	$result = get_JEX_switch_ports('site', $this->device);

	expect($result['macs_active'])->toBe(1);

	$writes = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => strpos($c['sql'], 'REPLACE INTO mac_track_temp_ports') !== false));

	expect($writes)->toHaveCount(1);
	// vlan_id, vlan_name, mac_address, port_number
	expect(array_slice($writes[0]['params'], 4, 4))->toBe(['10', 'users', '001122334455', 'ge-0/0/5']);
});
