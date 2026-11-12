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
require_once dirname(__DIR__, 2) . '/lib/mactrack_tplink.php';

afterEach(function () {
	unset($GLOBALS['__test_snmp_walk']);
});

it('maps a MAC through its real bridge port number', function () {
	$w = function (string $base, array $rows): array {
		$out = array();

		foreach ($rows as $index => $value) {
			$out[] = array('oid' => ltrim($base, '.') . '.' . $index, 'value' => $value);
		}

		return $out;
	};

	// one interface, ifIndex 5, behind bridge port 49
	$GLOBALS['__test_snmp_walk'] = array(
		'.1.3.6.1.2.1.2.2.1.1'               => $w('.1.3.6.1.2.1.2.2.1.1', array(5 => '5')),
		'.1.3.6.1.2.1.2.2.1.3'               => $w('.1.3.6.1.2.1.2.2.1.3', array(5 => '6')),
		'.1.3.6.1.2.1.31.1.1.1.1'            => $w('.1.3.6.1.2.1.31.1.1.1.1', array(5 => 'gi1/0/5')),
		'.1.3.6.1.4.1.11863.6.14.1.2.1.1.1'  => $w('.1.3.6.1.4.1.11863.6.14.1.2.1.1.1', array(10 => '10')),
		'.1.3.6.1.4.1.11863.6.14.1.2.1.1.2'  => $w('.1.3.6.1.4.1.11863.6.14.1.2.1.1.2', array(10 => 'users')),
		'.1.3.6.1.2.1.17.1.4.1.2'            => $w('.1.3.6.1.2.1.17.1.4.1.2', array(49 => '5')),
		'.1.3.6.1.2.1.17.7.1.2.2.1.2'        => $w('.1.3.6.1.2.1.17.7.1.2.2.1.2', array('10.0.12.41.5.138.209' => '49')),
	);

	$device = array(
		'device_id' => 1, 'host_id' => 0, 'site_id' => 1, 'device_name' => 'sw1', 'hostname' => '192.0.2.1',
		'snmp_readstring' => 'x', 'snmp_version' => 2, 'snmp_username' => '', 'snmp_password' => '',
		'snmp_auth_protocol' => '', 'snmp_priv_passphrase' => '', 'snmp_priv_protocol' => '',
		'snmp_context' => '', 'snmp_engine_id' => '', 'snmp_port' => 161, 'snmp_timeout' => 500,
		'snmp_retries' => 1, 'max_oids' => 10, 'ignorePorts' => '', 'snmp_sysObjectID' => '',
		'snmp_sysDescr' => '', 'scan_type' => 1, 'last_rundate' => '2026-11-12 00:00:00',
	);

	$result = get_tplink_dot1q_switch_ports('site', $device);

	expect($result['macs_active'])->toBe(1);
});
