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
	$GLOBALS['__test_db_calls']      = [];
	$GLOBALS['__test_next_returns']  = [];
	$GLOBALS['__test_cacti_log']     = [];
	$GLOBALS['__test_form_validate'] = [];

	$GLOBALS['__test_config_options']['mt_ignorePortsRegex']  = '';
	$GLOBALS['__test_config_options']['mt_ignorePorts_delim'] = '-1';

	$this->device = [
		'site_id'          => 1, 'device_id' => 2, 'hostname' => '192.0.2.1', 'device_name' => 'sw1',
		'ignorePortsRegex' => '', 'site_ignorePortsRegex' => '', 'last_runmessage' => 'Data collection completed ok',
	];

	$this->ports = [
		['mac_address' => '001122000001', 'port_number' => 'Gi1/0/1', 'port_name' => 'desk 12'],
		['mac_address' => '001122000002', 'port_number' => 'Gi1/0/48', 'port_name' => 'UPLINK to core'],
		['mac_address' => '001122000003', 'port_number' => 'Po1', 'port_name' => ''],
		['mac_address' => '001122000004', 'port_number' => 'Gi1/0/48', 'port_name' => 'UPLINK to core'],
	];
});

afterEach(function () {
	unset($GLOBALS['__test_config_options']['mt_ignorePortsRegex'], $GLOBALS['__test_config_options']['mt_ignorePorts_delim']);
});

// MAC addresses written to mac_track_temp_ports, in storage order.
function port_regex_stored_macs(): array {
	$macs = [];

	foreach ($GLOBALS['__test_db_calls'] as $call) {
		if (strpos($call['sql'], 'REPLACE INTO mac_track_temp_ports') !== false) {
			foreach (array_chunk($call['params'], 11) as $row) {
				$macs[] = $row[6];
			}
		}
	}

	return $macs;
}

// A malformed pattern makes preg_match() emit a compilation E_WARNING that the
// code under test suppresses with @, but PHPUnit's handler still reports it.
function port_regex_quiet(callable $call) {
	set_error_handler(static fn () => true, E_WARNING);

	try {
		return $call();
	} finally {
		restore_error_handler();
	}
}

it('collects every port while no expression is set', function () {
	db_store_device_port_results($this->device, $this->ports, '2026-11-12 00:00:00');

	expect(port_regex_stored_macs())->toHaveCount(4);
	expect($this->device['last_runmessage'])->toBe('Data collection completed ok');
});

it('skips ports whose alias matches, without regard to case', function (string $scope) {
	if ($scope === 'global') {
		$GLOBALS['__test_config_options']['mt_ignorePortsRegex'] = 'uplink';
	} elseif ($scope === 'site') {
		$this->device['site_ignorePortsRegex'] = 'uplink';
	} else {
		$this->device['ignorePortsRegex'] = 'uplink';
	}

	db_store_device_port_results($this->device, $this->ports, '2026-11-12 00:00:00');

	expect(port_regex_stored_macs())->toBe(['001122000001', '001122000003']);
})->with(['global', 'site', 'device']);

it('skips ports whose name matches', function () {
	$this->device['ignorePortsRegex'] = '^Po[0-9]+$';

	db_store_device_port_results($this->device, $this->ports, '2026-11-12 00:00:00');

	expect(port_regex_stored_macs())->toBe(['001122000001', '001122000002', '001122000004']);
});

it('combines the global, site and device expressions', function () {
	$GLOBALS['__test_config_options']['mt_ignorePortsRegex'] = 'desk';
	$this->device['site_ignorePortsRegex']                   = 'uplink';
	$this->device['ignorePortsRegex']                        = '^Po1$';

	db_store_device_port_results($this->device, $this->ports, '2026-11-12 00:00:00');

	expect(port_regex_stored_macs())->toBe([]);
});

it('reports how many MACs were skipped in the run message', function () {
	$this->device['ignorePortsRegex'] = 'uplink';

	db_store_device_port_results($this->device, $this->ports, '2026-11-12 00:00:00');

	expect($this->device['last_runmessage'])->toBe('Data collection completed ok (2 MACs skipped on ignored ports)');
});

it('leaves a warning run message alone', function () {
	$this->device['ignorePortsRegex'] = 'uplink';
	$this->device['last_runmessage']  = 'WARNING: Poller did not find active ports on this device.';

	db_store_device_port_results($this->device, $this->ports, '2026-11-12 00:00:00');

	expect($this->device['last_runmessage'])->toBe('WARNING: Poller did not find active ports on this device.');
});

it('skips an invalid expression with a log entry and still collects every port', function () {
	$this->device['ignorePortsRegex'] = '(uplink';

	port_regex_quiet(fn () => db_store_device_port_results($this->device, $this->ports, '2026-11-12 00:00:00'));

	expect(port_regex_stored_macs())->toHaveCount(4);
	expect(implode(' ', $GLOBALS['__test_cacti_log']))->toContain('invalid device Ignore Ports regular expression');
});

it('treats blank or whitespace-only expressions as unset', function () {
	$this->device['ignorePortsRegex'] = '   ';

	expect(mactrack_get_port_ignore_patterns($this->device))->toBe([]);
});

it('validates expressions for the settings forms', function () {
	expect(mactrack_port_regex_is_valid(''))->toBeTrue();
	expect(mactrack_port_regex_is_valid('(uplink|trunk)'))->toBeTrue();
	expect(port_regex_quiet(fn () => mactrack_port_regex_is_valid('(uplink')))->toBeFalse();
	expect(mactrack_compile_port_regex("bad\x01"))->toBe('');
});

it('trims a valid field and flags an invalid one through form validation', function () {
	expect(mactrack_validate_port_regex_field('  uplink ', 'ignorePortsRegex'))->toBe('uplink');
	expect($GLOBALS['__test_form_validate'])->toBe([]);

	port_regex_quiet(fn () => mactrack_validate_port_regex_field('(uplink', 'ignorePortsRegex'));
	expect($GLOBALS['__test_form_validate'])->toBe(['ignorePortsRegex' => '^$']);

	$GLOBALS['__test_form_validate'] = [];
	mactrack_validate_port_regex_field(str_repeat('a', 256), 'ignorePortsRegex');
	expect($GLOBALS['__test_form_validate'])->toBe(['ignorePortsRegex' => '^$']);
});

it('reads port lists whose names contain the colon delimiter', function (string $list, array $expected) {
	$ports = port_list_to_array($list);

	foreach ($expected as $port) {
		expect($ports)->toContain($port);
	}
})->with([
	'single stacked port'  => ['1:5', ['1:5']],
	'pipe list'            => ['1:5|1:6', ['1:5', '1:6']],
	'space list'           => ['Fa0/1 Fa1/23', ['Fa0/1', 'Fa1/23']],
	'colon and space list' => ['Fa0/1: Fa1/23', ['Fa0/1', 'Fa1/23']],
	'comma list'           => ['Gi1/1, Gi1/2', ['Gi1/1', 'Gi1/2']],
]);

it('returns no ports for an empty list', function () {
	expect(port_list_to_array(''))->toBe([]);
});

it('keeps an explicit delimiter strict', function () {
	$GLOBALS['__test_config_options']['mt_ignorePorts_delim'] = '|';

	expect(port_list_to_array('1:5|Port 2'))->toBe(['1:5', 'Port 2']);
});
