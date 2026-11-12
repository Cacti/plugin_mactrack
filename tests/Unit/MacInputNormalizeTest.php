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

it('puts an entered MAC in the stored form', function (string $typed) {
	expect(mactrack_normalize_mac($typed))->toBe('00112233AABB');
})->with(array('colons' => '00:11:22:33:aa:bb', 'dashes' => '00-11-22-33-AA-BB', 'cisco dots' => '0011.2233.aabb', 'padded' => ' 00112233aabb '));

it('normalizes and validates MacAuth and MacWatch entries before saving', function () {
	$auth  = file_get_contents(dirname(__DIR__, 2) . '/mactrack_macauth.php');
	$watch = file_get_contents(dirname(__DIR__, 2) . '/mactrack_macwatch.php');

	expect($auth)->toContain("form_input_validate(\$mac_address, 'mac_address', '^[0-9A-F]{1,12}\$', false, 3);");
	expect($watch)->toContain("form_input_validate(\$mac_address, 'mac_address', '^[0-9A-F]{12}\$', false, 3);");

	foreach (array($auth, $watch) as $src) {
		expect(strpos($src, 'mactrack_normalize_mac((string) $mac_address);'))->toBeLessThan(strpos($src, "form_input_validate(\$mac_address, 'mac_address'"));
	}
});

it('lets a MacAuth prefix authorize the MACs under it, longest first', function () {
	$GLOBALS['__test_db_calls'] = array();

	db_check_auth('AABBCCDDEEFF');

	$call = end($GLOBALS['__test_db_calls']);

	expect(preg_replace('/\s+/', ' ', $call['sql']))->toBe("SELECT mac_id FROM mac_track_macauth WHERE mac_address != '' AND LEFT(?, LENGTH(mac_address)) = mac_address ORDER BY LENGTH(mac_address) DESC LIMIT 1");
	expect($call['params'])->toBe(array('AABBCCDDEEFF'));
});
