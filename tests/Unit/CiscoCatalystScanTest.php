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
require_once dirname(__DIR__, 2) . '/lib/mactrack_cisco.php';

beforeEach(function () {
	$GLOBALS['__test_db_calls']                               = [];
	$GLOBALS['__test_next_returns']                           = [];
	$GLOBALS['__test_snmp_walk']                              = [];
	$GLOBALS['__test_snmp_walk_calls']                        = [];
	$GLOBALS['__test_config_options']['mt_ignorePorts_delim'] = '-1';
	$GLOBALS['scan_date']                                     = '2026-11-12 00:00:00';

	$this->walk = function (string $base, array $rows): void {
		$GLOBALS['__test_snmp_walk'][$base] = [];

		foreach ($rows as $index => $value) {
			$GLOBALS['__test_snmp_walk'][$base][] = ['oid' => ltrim($base, '.') . '.' . $index, 'value' => $value];
		}
	};

	$this->device = [
		'device_id'          => 1, 'host_id' => 0, 'site_id' => 1, 'device_name' => 'sw1', 'hostname' => '192.0.2.1',
		'snmp_readstring'    => 'x', 'snmp_version' => 2, 'snmp_username' => '', 'snmp_password' => '',
		'snmp_auth_protocol' => '', 'snmp_priv_passphrase' => '', 'snmp_priv_protocol' => '',
		'snmp_context'       => '', 'snmp_engine_id' => '', 'snmp_port' => 161, 'snmp_timeout' => 500,
		'snmp_retries'       => 1, 'max_oids' => 10, 'ignorePorts' => '', 'snmp_sysObjectID' => '',
		'snmp_sysDescr'      => '', 'scan_type' => 1, 'scan_trunk_port' => '',
		'last_rundate'       => '2026-11-12 00:00:00', 'macs_active' => 42,
	];
});

afterEach(function () {
	unset($GLOBALS['__test_snmp_walk'], $GLOBALS['__test_snmp_walk_calls'], $GLOBALS['__test_config_options']['mt_ignorePorts_delim']);
});

it('scans a Catalyst VLAN once, maps ports through the base scan and keeps user ports only', function () {
	// two VLANs and the three reserved ones are a count of 2 once reduced
	($this->walk)('.1.3.6.1.4.1.9.9.46.1.3.1.1.2', ['1.1' => '1', '1.10' => '1', '1.1002' => '1', '1.1003' => '1', '1.1004' => '1']);
	($this->walk)('.1.3.6.1.4.1.9.9.46.1.3.1.1.4', ['1.1' => 'default', '1.10' => 'users', '1.1002' => 'fddi', '1.1003' => 'tr', '1.1004' => 'fddinet']);
	($this->walk)('.1.3.6.1.4.1.9.9.46.1.6.1.1.14', [1 => '2', 2 => '2']);
	($this->walk)('.1.3.6.1.2.1.2.2.1.1', [1 => '1', 2 => '2']);
	($this->walk)('.1.3.6.1.2.1.2.2.1.3', [1 => '6', 2 => '6']);
	($this->walk)('.1.3.6.1.2.1.2.2.1.8', [1 => '1', 2 => '1']);
	($this->walk)('.1.3.6.1.2.1.31.1.1.1.1', [1 => '1/1', 2 => '1/2']);
	// port 1/1 is an access port, 1/2 a trunk
	($this->walk)('.1.3.6.1.4.1.9.5.1.9.3.1.8', ['1.1' => '2', '1.2' => '1']);
	($this->walk)('.1.3.6.1.2.1.17.1.4.1.2', [1 => '1', 2 => '2']);
	($this->walk)('.1.3.6.1.2.1.17.4.3.1.3', ['0.0.0.0.0.1' => '3', '0.0.0.0.0.2' => '3']);
	($this->walk)('.1.3.6.1.2.1.17.4.3.1.2', ['0.0.0.0.0.1' => '1', '0.0.0.0.0.2' => '2']);
	($this->walk)('.1.3.6.1.2.1.17.4.3.1.1', ['0.0.0.0.0.1' => "\0\0\0\0\0\1", '0.0.0.0.0.2' => "\0\0\0\0\0\2"]);
	// dot1dBaseNumPorts of VLANs 1 and 10
	mactrack_test_queue_return('cacti_snmp_get', '2');
	mactrack_test_queue_return('cacti_snmp_get', '2');

	$result = get_catalyst_dot1dTpFdbEntry_ports('site', $this->device);

	// the reserved VLANs are not counted; 5 reported less 3
	expect($result['vlans_total'])->toBe(2);
	// interface status is read once, not once per VLAN
	expect(array_count_values($GLOBALS['__test_snmp_walk_calls'])['.1.3.6.1.2.1.2.2.1.8'])->toBe(2);
	expect($result['macs_active'])->toBe(2);

	$writes = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => strpos($c['sql'], 'REPLACE INTO mac_track_temp_ports') !== false));

	expect($writes)->toHaveCount(1);

	$rows = array_chunk($writes[0]['params'], 11);

	// vlan_id, port_number and mac_address per row, VLANs 1 and 10 each seeing the access port
	expect(array_map(fn ($r) => [(int) $r[4], $r[7], $r[6]], $rows))->toBe([
		[1, '1/1', '000000000001'],
		[10, '1/1', '000000000001'],
	]);
});

it('reports no VLANs rather than a negative count when the device has fewer than the reserved ones', function () {
	($this->walk)('.1.3.6.1.4.1.9.9.46.1.3.1.1.2', ['1.1' => '1']);
	($this->walk)('.1.3.6.1.4.1.9.9.46.1.3.1.1.4', ['1.1' => 'default']);

	$result = get_catalyst_dot1dTpFdbEntry_ports('site', $this->device);

	expect($result['vlans_total'])->toBe(0);
});

it('clears the previous scan when no VLAN trunk status comes back', function () {
	($this->walk)('.1.3.6.1.4.1.9.9.46.1.3.1.1.2', ['1.1' => '1']);
	($this->walk)('.1.3.6.1.4.1.9.9.46.1.3.1.1.4', ['1.1' => 'default']);

	$result = get_IOS_dot1dTpFdbEntry_ports('site', $this->device);

	expect($result['macs_active'])->toBe(0);
	expect($result['last_runmessage'])->toBe('WARNING: No VLAN trunk status found.');
});
