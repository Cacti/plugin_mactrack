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
 * These exercise the real find_scanning_function() and its matcher helper
 * mactrack_snmp_pattern_match(). Device-type auto-detection must match a device
 * type when it supplies only a sysDescr match, only a sysObjectID match, or
 * both (in which case the device has to satisfy both). Neither function touches
 * the database or the network, so they can be exercised directly.
 */

require_once dirname(__DIR__, 2) . '/lib/mactrack_functions.php';

/**
 * Build a device-type row with the fields find_scanning_function() consults.
 */
function fsf_type(int $id, string $sysDescr, string $sysObjectID): array {
	return [
		'device_type_id'    => $id,
		'device_type'       => $id,
		'scanning_function' => 'get_generic_switch_ports',
		'sysDescr_match'    => $sysDescr,
		'sysObjectID_match' => $sysObjectID,
	];
}

/**
 * Build a scanned device row with the sysDescr/sysObjectID that were read.
 */
function fsf_device(string $sysDescr, string $sysObjectID): array {
	return [
		'snmp_sysDescr'    => $sysDescr,
		'snmp_sysObjectID' => $sysObjectID,
	];
}

test('a device type with only a sysObjectID match is detected on an OID match', function () {
	$types  = [fsf_type(1, '', '.1.3.6.1.4.1.9.1.516')];
	$device = fsf_device('Cisco IOS Software', '.1.3.6.1.4.1.9.1.516');

	$result = find_scanning_function($device, $types);

	expect($result)->not->toBe([]);
	expect($device['device_type_id'])->toBe(1);
	expect($device['scan_type'])->toBe(1);
});

test('a device type with only a sysDescr match is detected on a sysDescr match', function () {
	$types  = [fsf_type(2, 'C2960-LANBASEK9-M', '')];
	$device = fsf_device('Cisco IOS C2960-LANBASEK9-M build 42', '.1.3.6.1.4.1.9.1.516');

	$result = find_scanning_function($device, $types);

	expect($result)->not->toBe([]);
	expect($device['device_type_id'])->toBe(2);
});

test('a single-criterion device type is skipped when its one criterion does not match', function ($type, $device) {
	$types   = [$type];
	$scanned = $device;

	expect(find_scanning_function($scanned, $types))->toBe([]);
})->with([
	'only sysObjectID, OID differs' => [fsf_type(1, '', '.1.3.6.1.4.1.9'), fsf_device('Cisco IOS', '.1.3.6.1.4.1.11')],
	'only sysDescr, descr differs'  => [fsf_type(2, 'C2960', ''), fsf_device('Juniper JunOS', '.1.3.6.1.4.1.2636')],
]);

test('a device type with both matches requires both to match', function () {
	$types = [fsf_type(3, 'Cisco', '.1.3.6.1.4.1.9')];

	$both = fsf_device('Cisco IOS Software', '.1.3.6.1.4.1.9.1.1');
	expect(find_scanning_function($both, $types))->not->toBe([]);

	// sysDescr matches but the OID does not -> no match.
	$descrOnly = fsf_device('Cisco IOS Software', '.1.3.6.1.4.1.11.2.3');
	expect(find_scanning_function($descrOnly, $types))->toBe([]);

	// OID matches but the sysDescr does not -> no match.
	$oidOnly = fsf_device('Juniper JunOS', '.1.3.6.1.4.1.9.1.1');
	expect(find_scanning_function($oidOnly, $types))->toBe([]);
});

test('a device type with no match criteria never matches and does not steal devices', function () {
	// An empty device type must not act as a catch-all: the specific OID-only
	// type later in the list has to win.
	$types  = [fsf_type(99, '', ''), fsf_type(7, '', '.1.3.6.1.4.1.9.1.516')];
	$device = fsf_device('Cisco IOS Software', '.1.3.6.1.4.1.9.1.516');

	$result = find_scanning_function($device, $types);

	expect($result)->not->toBe([]);
	expect($device['device_type_id'])->toBe(7);
});

test('a wildcard pattern requires every token to be present', function () {
	// "foo*bar" means the value has to contain both "foo" and "bar".
	expect(mactrack_snmp_pattern_match('foo*bar', 'xx foo yy bar zz'))->toBeTrue();
	expect(mactrack_snmp_pattern_match('foo*bar', 'xx bar zz'))->toBeFalse();
	expect(mactrack_snmp_pattern_match('foo*bar', 'xx foo zz'))->toBeFalse();

	// Leading/trailing '*' produce empty tokens that are skipped; only the
	// concrete token has to be present.
	expect(mactrack_snmp_pattern_match('*Cisco*', 'IOS Cisco build 42'))->toBeTrue();
	expect(mactrack_snmp_pattern_match('*Cisco*', 'Juniper JunOS'))->toBeFalse();
});

test('the matcher treats an empty pattern as no requirement and a plain pattern as a substring', function () {
	expect(mactrack_snmp_pattern_match('', 'anything'))->toBeTrue();
	expect(mactrack_snmp_pattern_match('Cisco', 'Cisco IOS'))->toBeTrue();
	expect(mactrack_snmp_pattern_match('Cisco', 'Juniper'))->toBeFalse();
});

test('no device types produces no match', function () {
	$types  = [];
	$device = fsf_device('Cisco IOS Software', '.1.3.6.1.4.1.9.1.516');

	expect(find_scanning_function($device, $types))->toBe([]);
});
