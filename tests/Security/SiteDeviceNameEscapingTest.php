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
 * Site and device names are free text, so they must be escaped wherever they
 * are printed, CSV cells must not start a spreadsheet formula, and the
 * Cabletron port scan must not build a command line.
 */

require_once dirname(__DIR__, 2) . '/lib/mactrack_functions.php';
require_once dirname(__DIR__, 2) . '/lib/mactrack_cabletron.php';

/**
 * Returns every PHP file of the plugin outside tests and vendored code.
 *
 * @return array<int, string> Relative file names.
 */
function mactrack_test_plugin_php_files(): array {
	$root  = dirname(__DIR__, 2);
	$files = array_merge(glob($root . '/*.php'), glob($root . '/lib/*.php'));

	return array_map(static fn ($f) => substr($f, strlen($root) + 1), $files);
}

test('CSV cells neutralise formula prefixes and double quotes', function ($input, $expected) {
	expect(mactrack_csv_cell($input))->toBe($expected);
})->with([
	'equals'          => ['=1+1', "'=1+1"],
	'plus'            => ['+cmd', "'+cmd"],
	'minus text'      => ['-cmd', "'-cmd"],
	'at'              => ['@SUM(A1)', "'@SUM(A1)"],
	'tab'             => ["\tx", "'\tx"],
	'carriage return' => ["\rx", "'\rx"],
	'quote'           => ['a"b', 'a""b'],
	'plain'           => ['core-sw1', 'core-sw1'],
	'negative number' => ['-5', '-5'],
	'integer'         => [42, '42'],
	'null'            => [null, ''],
	'empty'           => ['', ''],
]);

test('every CSV export row passes each field through mactrack_csv_cell', function () {
	$checked = 0;

	foreach (mactrack_test_plugin_php_files() as $file) {
		$source = file_get_contents(dirname(__DIR__, 2) . '/' . $file);

		if (!preg_match_all("/array_push\\(\\\$xport_array,\\s*'\"'\\s*\\..*?'\"'\\);/s", $source, $rows)) {
			continue;
		}

		foreach ($rows[0] as $row) {
			$checked++;
			// a field reference outside mactrack_csv_cell() would be written verbatim
			$unwrapped = preg_replace('/mactrack_csv_cell\((?:[^()]|\((?:[^()]|\([^()]*\))*\))*\)/', '', $row);

			expect($unwrapped)->not->toMatch('/\$\w+\[/', "$file has an unwrapped CSV field");
		}
	}

	expect($checked)->toBe(13);
});

test('site and device names are not printed raw in views, options or emails', function () {
	foreach (mactrack_test_plugin_php_files() as $file) {
		$source = file_get_contents(dirname(__DIR__, 2) . '/' . $file);

		expect($source)->not->toMatch('/form_selectable_cell\(\$\w+\[\'(?:site_name|device_name)\'\]/', "$file prints a name unescaped");
		expect($source)->not->toMatch("/'>' \\. \\\$\\w+\\['site_name'\\] \\. '<\\/option>'/", "$file prints a site option unescaped");
	}

	$poller = file_get_contents(dirname(__DIR__, 2) . '/poller_mactrack.php');

	expect($poller)->toContain("html_escape(\$port['site_name'])");
	expect($poller)->toContain("html_escape(\$port['device_name'])");
	expect($poller)->not->toContain("'<td>' . \$port['site_name'] . '</td>'");
});

test('the dot1x row escapes the device name', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/lib/mactrack_functions.php');

	expect($source)->toContain("html_escape(\$port_result['device_name'])");
	expect($source)->not->toContain("'<td><b>' . \$port_result['device_name'] . '</b></td>'");
});

test('import results escape the values taken from the uploaded CSV', function () {
	foreach (['mactrack_devices.php', 'mactrack_device_types.php'] as $file) {
		$source = file_get_contents(dirname(__DIR__, 2) . '/' . $file);

		preg_match_all('/array_push\(\$return_array, __\(.*$/m', $source, $lines);

		expect($lines[0])->not->toBeEmpty();

		foreach ($lines[0] as $line) {
			expect($line)->not->toMatch('/, \$\w+(?=,)/', "$file puts an unescaped value in an import result");
		}
	}
});

test('SNMP item changes require POST, are scoped to their set and are sent by the page script', function () {
	$page = file_get_contents(dirname(__DIR__, 2) . '/mactrack_snmp.php');
	$js   = file_get_contents(dirname(__DIR__, 2) . '/js/mactrack_snmp.js');

	foreach (['item_movedown', 'item_moveup', 'item_remove'] as $action) {
		expect($page)->toMatch("/case '$action':\\s+if \\(!mactrack_require_post\\(\\)\\) \\{\\s+break;/");
	}

	expect($page)->toContain('AND snmp_id = ?');
	expect($page)->not->toContain('mactrack_snmp.php?action=item_remove&item_id=');
	expect($page)->not->toContain('mactrack_snmp.php?action=item_moveup&item_id=');
	expect($page)->not->toContain('mactrack_snmp.php?action=item_movedown&item_id=');
	expect($js)->toContain('__csrf_magic: csrfMagicToken');
	expect($js)->toContain('$.post(');
});

test('the Cabletron port scan reads the port table through the SNMP API', function () {
	$oid = '.1.3.6.1.4.1.52.4.1.1.1.4.1.5.2.1.2';

	$GLOBALS['__test_snmp_walk'] = [
		$oid => [
			['oid' => $oid . '.1.1', 'value' => '1'],
			['oid' => $oid . '.1.2', 'value' => '2'],
			['oid' => $oid . '.1.3', 'value' => '3'],
			['oid' => $oid . '.1.4', 'value' => '1'],
		],
	];
	$GLOBALS['__test_snmp_walk_calls'] = [];
	$GLOBALS['__test_db_calls']        = [];

	$device = [
		'device_id' => 1, 'hostname' => 'sw1.example.com; id', 'snmp_version' => 2, 'snmp_username' => '',
		'snmp_password' => '', 'snmp_auth_protocol' => '', 'snmp_priv_passphrase' => '', 'snmp_priv_protocol' => '',
		'snmp_context' => '', 'snmp_port' => 161, 'snmp_timeout' => 500, 'snmp_retries' => 1, 'max_oids' => 10,
		'snmp_readstring' => 'public', 'ignorePorts' => '', 'snmp_sysDescr' => 'x', 'ports_active' => 0,
	];

	mactrack_test_queue_return('cacti_snmp_get', '4');
	mactrack_test_queue_return('cacti_snmp_get', '4');

	$result = get_repeater_rev4_ports('HQ', $device, 1, 2);

	expect($GLOBALS['__test_snmp_walk_calls'])->toContain($oid);
	// the walk stops when a port number is outside the range
	expect($result['macs_active'])->toBe(2);
});
