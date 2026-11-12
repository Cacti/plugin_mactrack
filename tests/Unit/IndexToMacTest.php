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

it('reads the MAC at the end of a dot1q index', function () {
	expect(mactrack_index_to_mac('199.0.12.41.5.138.209'))->toBe('000C29058AD1');
	expect(mactrack_index_to_mac('.0.1.230.109.208.105'))->toBe('0001E66DD069');
});

it('matches the format xform_mac_address() returns', function () {
	expect(mactrack_index_to_mac('1.0.12.41.5.138.209'))->toBe(xform_mac_address("\x00\x0c\x29\x05\x8a\xd1"));
});

it('rejects an index that does not end in six octets', function (string $index) {
	expect(mactrack_index_to_mac($index))->toBe('');
})->with(array('too short' => '1.2.3.4.5', 'not a byte' => '1.0.12.41.5.138.256', 'not digits' => '1.0.12.41.5.a.209', 'empty' => ''));

it('falls back to the index when the dot1q address column is not returned', function () {
	$src = file_get_contents(dirname(__DIR__, 2) . '/lib/mactrack_functions.php');

	expect($src)->toContain("\$mac = \$port_macs[\$port_key['key']] ?? mactrack_index_to_mac((string) \$port_key['key']);");
});
