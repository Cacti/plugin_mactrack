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
 * xform_net_address() turns the address forms SNMP agents return into IPv4
 * or IPv6 text. Raw binary values are converted before anything is trimmed
 * (#356), valid IP text is returned untouched (#355), and a missing value is
 * an empty string rather than a trim(null) deprecation (#351).
 */

require_once dirname(__DIR__, 2) . '/lib/mactrack_functions.php';

test('IP text is returned unchanged', function ($input) {
	expect(xform_net_address($input))->toBe($input);
})->with([
	'192.0.2.10',
	'2001:db8::1',
	'fe80::1',
	'::1',
	'2001:db8:85a3::1',
	'2001:db8:0:0:0:0:0:1',
]);

test('raw binary addresses are converted, whatever their last byte', function ($hex, $expected) {
	expect(xform_net_address(hex2bin($hex)))->toBe($expected);
})->with([
	['c0a8010a', '192.168.1.10'],
	['c0a80120', '192.168.1.32'],
	['0a000009', '10.0.0.9'],
	['c0a80100', '192.168.1.0'],
	['20010db8000000000000000000000001', '2001:db8::1'],
	['20010db800000000000000000000000a', '2001:db8::a'],
]);

test('hexadecimal IPv4 octets are converted', function ($input, $expected) {
	expect(xform_net_address($input))->toBe($expected);
})->with([
	['0A 09 15 72', '10.9.21.114'],
	['0A:09:15:72', '10.9.21.114'],
	['a:9:15:72', '10.9.21.114'],
]);

test('the Network Address prefix and surrounding whitespace are removed', function ($input, $expected) {
	expect(xform_net_address($input))->toBe($expected);
})->with([
	['Network Address: 192.0.2.10', '192.0.2.10'],
	['Network Address:0A:09:15:72', '10.9.21.114'],
	["  192.0.2.10\n", '192.0.2.10'],
	['Network Address:', ''],
]);

test('a missing value is an empty string', function ($input) {
	expect(xform_net_address($input))->toBe('');
})->with([[null], [''], ['   ']]);

test('other text is left as it is', function ($input) {
	expect(xform_net_address($input))->toBe($input);
})->with(['not-an-address', '1:2:3:4:5', 'GG:00:00:01']);
