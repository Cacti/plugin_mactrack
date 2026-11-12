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

beforeEach(function () {
	$GLOBALS['__test_db_calls']     = array();
	$GLOBALS['__test_next_returns'] = array();

	$this->device = array('site_id' => 1, 'device_id' => 2, 'hostname' => '192.0.2.1', 'device_name' => 'sw1');
});

it('reads MacAuth once and writes ports in batches of 500', function () {
	// an empty entry must not authorize everything
	mactrack_test_queue_return('db_fetch_assoc', array(array('mac_address' => 'AABBCC'), array('mac_address' => '')));

	$ports = array();

	for ($i = 0; $i < 1200; $i++) {
		$ports[] = array(
			'mac_address' => ($i % 2 ? 'AABBCC' : '001122') . sprintf('%06X', $i),
			'port_number' => '1', 'port_name' => 'gi1', 'vlan_id' => '10', 'vlan_name' => 'users',
		);
	}

	db_store_device_port_results($this->device, $ports, '2026-11-12 00:00:00');

	$calls    = $GLOBALS['__test_db_calls'];
	$reads    = array_filter($calls, fn ($c) => strpos($c['sql'], 'mac_track_macauth') !== false);
	$replaces = array_values(array_filter($calls, fn ($c) => strpos($c['sql'], 'REPLACE INTO mac_track_temp_ports') !== false));

	expect($reads)->toHaveCount(1);
	expect(array_map(fn ($c) => count($c['params']) / 11, $replaces))->toBe(array(500, 500, 200));

	$authorized = array();

	foreach ($replaces as $c) {
		foreach (array_chunk($c['params'], 11) as $row) {
			$authorized[$row[6]] = $row[10];
		}
	}

	expect($authorized['AABBCC000001'])->toBe(1);
	expect($authorized['001122000000'])->toBe(0);
});

it('matches whole MACs and prefixes, longest or not', function () {
	$index = mactrack_macauth_index(array('AABBCC', '001122334455', ''));

	expect(mactrack_mac_authorized('AABBCC000001', $index))->toBeTrue();
	expect(mactrack_mac_authorized('001122334455', $index))->toBeTrue();
	expect(mactrack_mac_authorized('001122334456', $index))->toBeFalse();
	expect(mactrack_mac_authorized('0A1122334455', mactrack_macauth_index(array(''))))->toBeFalse();
});

it('skips non-user rows and writes nothing for an empty scan', function () {
	mactrack_test_queue_return('db_fetch_assoc', array());

	db_store_device_port_results($this->device, array(array('mac_address' => 'NOT USER', 'port_number' => 'NOT USER', 'port_name' => '')), '2026-11-12 00:00:00');
	db_store_device_port_results($this->device, array(), '2026-11-12 00:00:00');

	expect(array_filter($GLOBALS['__test_db_calls'], fn ($c) => strpos($c['sql'], 'REPLACE') !== false))->toBeEmpty();
});
