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
require_once dirname(__DIR__, 2) . '/lib/mactrack_aruba_oscx.php';

beforeEach(function () {
	$GLOBALS['__test_db_calls'] = [];
});

afterEach(function () {
	unset($GLOBALS['__test_snmp_walk']);
});

it('joins ARP entries to ports in the stored MAC form, IPv4 only', function () {
	$GLOBALS['__test_snmp_walk'] = [
		'.1.3.6.1.2.1.17.7.1.2.2.1.2' => [
			['oid' => '.1.3.6.1.2.1.17.7.1.2.2.1.2.1.76.174.163.100.90.203', 'value' => '28'],
		],
		'.1.3.6.1.2.1.4.35.1.4' => [
			['oid' => '.1.3.6.1.2.1.4.35.1.4.16777415.1.4.192.168.199.95', 'value' => '4c:ae:a3:64:5a:cb'],
			['oid' => '.1.3.6.1.2.1.4.35.1.4.16777415.2.16.254.128.0.0.0.0.0.0.78.174.163.255.254.100.90.203', 'value' => '4c:ae:a3:64:5a:cb'],
		],
	];

	$device = [
		'device_id'          => 1, 'site_id' => 1, 'device_name' => 'sw1', 'hostname' => '192.0.2.1',
		'snmp_readstring'    => 'x', 'snmp_version' => 2, 'snmp_username' => '', 'snmp_password' => '',
		'snmp_auth_protocol' => '', 'snmp_priv_passphrase' => '', 'snmp_priv_protocol' => '',
		'snmp_context'       => '', 'snmp_engine_id' => '', 'snmp_port' => 161, 'snmp_timeout' => 500,
		'snmp_retries'       => 1, 'max_oids' => 10,
	];

	$GLOBALS['scan_date'] = '2026-11-12 00:00:00';

	get_aruba_oscx_arp_table([], $device);

	$writes = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => strpos($c['sql'], 'REPLACE INTO mac_track_ips') !== false));

	expect($writes)->toHaveCount(1);
	expect($writes[0]['params'])->toBe([1, 1, '192.0.2.1', 'sw1', '28', '4CAEA3645ACB', '192.168.199.95', '2026-11-12 00:00:00']);
});
