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

$src = file_get_contents(dirname(__DIR__, 2) . '/poller_mactrack.php');

if (!function_exists('mactrack_parse_arpwatch_line')) {
	$at = strpos($src, 'function mactrack_parse_arpwatch_line(');
	eval(substr($src, $at, strpos($src, "\n}\n", $at) - $at + 2));
}

it('reads the MAC, IP and host name from an arp.dat line', function () {
	expect(mactrack_parse_arpwatch_line("0:11:2a:33:44:5\t192.0.2.10\t1700000000\thost.example\n", ''))
		->toBe(['mac' => '00112A334405', 'ip' => '192.0.2.10', 'dns' => 'host.example']);
});

it('keeps an entry without a host name', function () {
	expect(mactrack_parse_arpwatch_line("0:11:22:33:44:55\t192.0.2.11\t1700000000\n", ''))
		->toBe(['mac' => '001122334455', 'ip' => '192.0.2.11', 'dns' => '']);
});

it('keeps the host name when an interface follows it', function () {
	expect(mactrack_parse_arpwatch_line("0:11:22:33:44:55\t192.0.2.12\t1700000000\thost\teth0\n", '')['dns'])->toBe('host');
});

it('skips blank and malformed lines', function (string $line) {
	expect(mactrack_parse_arpwatch_line($line, ''))->toBeNull();
})->with(['blank' => "\n", 'no ip' => "0:11:22:33:44:55\n", 'short mac' => "0:11:22\t192.0.2.1\n", 'not hex' => "zz:11:22:33:44:55\t192.0.2.1\n"]);
