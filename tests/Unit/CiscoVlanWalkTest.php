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
	$GLOBALS['__test_snmp_walk_calls']                        = [];
	$GLOBALS['__test_config_options']['mt_ignorePorts_delim'] = '-1';
	$GLOBALS['scan_date']                                     = '2026-11-12 00:00:00';

	// two operational VLANs, two access ports; bridge port 1 is ifIndex 2
	mactrack_test_snmp_walk('.1.3.6.1.4.1.9.9.46.1.3.1.1.2', ['1.10' => '1', '1.20' => '1']);
	mactrack_test_snmp_walk('.1.3.6.1.4.1.9.9.46.1.3.1.1.4', ['1.10' => 'users', '1.20' => 'voice']);
	mactrack_test_snmp_walk('.1.3.6.1.4.1.9.9.46.1.6.1.1.14', [1 => '2', 2 => '2']);
	mactrack_test_snmp_walk('.1.3.6.1.2.1.2.2.1.1', [1 => '1', 2 => '2']);
	mactrack_test_snmp_walk('.1.3.6.1.2.1.2.2.1.3', [1 => '6', 2 => '6']);
	mactrack_test_snmp_walk('.1.3.6.1.2.1.2.2.1.8', [1 => '1', 2 => '1']);
	mactrack_test_snmp_walk('.1.3.6.1.2.1.31.1.1.1.1', [1 => 'Gi1/0/1', 2 => 'Gi1/0/2']);
	mactrack_test_snmp_walk('.1.3.6.1.2.1.17.1.4.1.2', [1 => '2']);
	mactrack_test_snmp_walk('.1.3.6.1.2.1.17.4.3.1.3', ['0.0.0.0.0.1' => '3']);
	mactrack_test_snmp_walk('.1.3.6.1.2.1.17.4.3.1.2', ['0.0.0.0.0.1' => '1']);
	mactrack_test_snmp_walk('.1.3.6.1.2.1.17.4.3.1.1', ['0.0.0.0.0.1' => "\0\0\0\0\0\1"]);

	// dot1dBaseNumPorts per VLAN context
	mactrack_test_queue_return('cacti_snmp_get', '2');
	mactrack_test_queue_return('cacti_snmp_get', '2');

	$this->device = [
		'device_id'          => 1, 'host_id' => 0, 'site_id' => 1, 'device_name' => 'sw1', 'hostname' => '192.0.2.1',
		'snmp_readstring'    => 'x', 'snmp_version' => 2, 'snmp_username' => '', 'snmp_password' => '',
		'snmp_auth_protocol' => '', 'snmp_priv_passphrase' => '', 'snmp_priv_protocol' => '',
		'snmp_context'       => '', 'snmp_engine_id' => '', 'snmp_port' => 161, 'snmp_timeout' => 500,
		'snmp_retries'       => 1, 'max_oids' => 10, 'ignorePorts' => '', 'snmp_sysObjectID' => '',
		'snmp_sysDescr'      => '', 'scan_type' => 1, 'scan_trunk_port' => '',
		'last_rundate'       => '2026-11-12 00:00:00',
	];
});

afterEach(function () {
	unset($GLOBALS['__test_snmp_walk'], $GLOBALS['__test_snmp_walk_calls'], $GLOBALS['__test_config_options']['mt_ignorePorts_delim']);
});

it('walks the bridge map once per VLAN and maps MACs through it', function () {
	get_IOS_dot1dTpFdbEntry_ports('site', $this->device);

	$walks = array_count_values($GLOBALS['__test_snmp_walk_calls']);

	expect($walks['.1.3.6.1.2.1.17.1.4.1.2'])->toBe(2);

	$replace = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => strpos($c['sql'], 'REPLACE INTO mac_track_temp_ports') !== false));

	expect($replace)->toHaveCount(1);
	// port_number is the ifName of ifIndex 2, behind bridge port 1
	expect(array_column(array_chunk($replace[0]['params'], 11), 7))->toBe(['Gi1/0/2', 'Gi1/0/2']);
});

it('walks interface status once for the scan, not once per VLAN', function () {
	get_IOS_dot1dTpFdbEntry_ports('site', $this->device);

	$walks        = array_count_values($GLOBALS['__test_snmp_walk_calls']);
	$before_vlans = count(array_filter(array_slice($GLOBALS['__test_snmp_walk_calls'], 0, (int) array_search('.1.3.6.1.2.1.17.4.3.1.3', $GLOBALS['__test_snmp_walk_calls'], true)), fn ($o) => $o === '.1.3.6.1.2.1.2.2.1.8'));

	// whatever the interface table reads, the VLAN passes add none
	expect($walks['.1.3.6.1.2.1.2.2.1.8'])->toBe($before_vlans);
});
