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

/*
 * Core's PHP SNMP walk returns OIDs without the leading dot and the
 * snmpwalk binary returns them with it; the keys must not depend on which
 * one ran.
 */
function mt_os_device(): array {
	return [
		'hostname'             => '192.0.2.1', 'snmp_readstring' => 'x', 'snmp_version' => 2,
		'snmp_username'        => '', 'snmp_password' => '', 'snmp_auth_protocol' => '',
		'snmp_priv_passphrase' => '', 'snmp_priv_protocol' => '', 'snmp_context' => '',
		'snmp_port'            => 161, 'snmp_timeout' => 500, 'snmp_retries' => 1, 'max_oids' => 10,
		'snmp_engine_id'       => '',
	];
}

it('returns the same suffix with or without the leading dot', function (string $oid) {
	expect(mactrack_oid_suffix($oid, '.1.3.6.1.2.1.17.4.3.1.2'))->toBe('0.1.230.109.208.105');
})->with([
	'php walk'    => '1.3.6.1.2.1.17.4.3.1.2.0.1.230.109.208.105',
	'binary walk' => '.1.3.6.1.2.1.17.4.3.1.2.0.1.230.109.208.105',
	'iso prefix'  => 'iso.3.6.1.2.1.17.4.3.1.2.0.1.230.109.208.105',
]);

it('leaves an OID outside the base whole', function () {
	expect(mactrack_oid_suffix('.1.3.6.1.2.1.2.2.1.1.5', '.1.3.6.1.2.1.17.4.3.1.2'))->toBe('1.3.6.1.2.1.2.2.1.1.5');
});

it('keys stripped walks the same on both walk paths', function (string $prefix) {
	$device = mt_os_device();

	$GLOBALS['__test_snmp_walk']['.1.3.6.1.2.1.17.4.3.1.2'] = [
		['oid' => $prefix . '1.3.6.1.2.1.17.4.3.1.2.0.1.230.109.208.105', 'value' => '68'],
	];

	expect(xform_stripped_oid('.1.3.6.1.2.1.17.4.3.1.2', $device))->toBe(['0.1.230.109.208.105' => '68']);
})->with(['php walk' => '', 'binary walk' => '.']);

it('splits the dot1q VLAN from the MAC on both walk paths', function (string $prefix) {
	$device = mt_os_device();

	$GLOBALS['__test_snmp_walk']['.1.3.6.1.2.1.17.7.1.2.2.1.2'] = [
		['oid' => $prefix . '1.3.6.1.2.1.17.7.1.2.2.1.2.199.0.12.41.5.138.209', 'value' => '52'],
	];

	expect(xform_dot1q_vlan_associations($device))->toBe(['0.12.41.5.138.209' => '199']);
})->with(['php walk' => '', 'binary walk' => '.']);

afterEach(function () {
	unset($GLOBALS['__test_snmp_walk']);
});
