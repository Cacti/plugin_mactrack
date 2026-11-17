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
 * A MAC address whose first octet is 52 is locally administered, so no
 * vendor registers it. The views name it from the vendor name expression,
 * which the filters use too, so the label is shown and searchable.
 */

require_once dirname(__DIR__, 2) . '/lib/mactrack_functions.php';

it('labels an address that begins with 52', function (string $mac) {
	expect(mactrack_locally_administered_label($mac))->toBe('Locally Admined MAC');
})->with(['520000000001', '52:AA:BB:CC:DD:EE', '52-54-01-00-00-01']);

it('leaves other addresses unlabeled', function (string $mac) {
	expect(mactrack_locally_administered_label($mac))->toBe('');
})->with(['', '5', '001122334455', '152233445566', 'A25254000001']);

it('builds the vendor name from the OUI row and then the label', function () {
	$sql = mactrack_vendor_name_sql('mti.mac_address');

	expect($sql)->toBe("COALESCE(mtod.vendor_name, IF(SUBSTRING(mti.mac_address, 1, 2) = '52', 'Locally Admined MAC', NULL))");
});

it('names an unknown 52 address in the lookup helper only when the OUI table has no name', function () {
	$GLOBALS['__test_db_calls'] = [];
	$GLOBALS['__test_matched_returns'] = [];
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'mac_track_oui_database', 'Virtual/KVM Host');
	expect(mactrack_get_vendor_name('52:54:00:aa:bb:cc'))->toBe('Virtual/KVM Host');

	$GLOBALS['__test_matched_returns'] = [];
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'mac_track_oui_database', '');
	expect(mactrack_get_vendor_name('52:11:22:aa:bb:cc'))->toBe('Locally Admined MAC');
	expect(mactrack_get_vendor_name('5211.22aa.bbcc'))->toBe('Locally Admined MAC');
	expect(mactrack_get_vendor_name('00:11:22:aa:bb:cc'))->toBe('Unknown');
});

it('looks a full MAC address up by its six character OUI', function (string $mac) {
	$GLOBALS['__test_db_calls'] = [];
	mactrack_get_vendor_name($mac);

	$call = end($GLOBALS['__test_db_calls']);
	expect($call['params'])->toBe(['525400']);
})->with(['52:54:00:aa:bb:cc', '525400aabbcc', '52-54-00-aa-bb-cc', '5254.00aa.bbcc']);

it('uses the expression for the MAC and IP address views and their search', function (string $file, string $column) {
	$src = file_get_contents(dirname(__DIR__, 2) . '/' . $file);

	expect($src)->toContain("mactrack_vendor_name_sql('$column')");
	expect($src)->not->toContain("'mtod.vendor_name LIKE");
	expect($src)->not->toContain('(mtod.vendor_name LIKE');
	expect($src)->toContain('$vendor_sql AS vendor_name');
})->with([
	'MAC addresses' => ['mactrack_view_macs.php', 'mtp.mac_address'],
	'IP addresses'  => ['mactrack_view_arp.php', 'mti.mac_address'],
]);
