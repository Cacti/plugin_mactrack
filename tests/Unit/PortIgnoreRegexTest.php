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
require_once dirname(__DIR__, 2) . '/setup.php';

beforeEach(function () {
	$GLOBALS['__test_matched_returns'] = [];
	$GLOBALS['__test_db_calls']        = [];
	$GLOBALS['__test_next_returns']    = [];
	$GLOBALS['__test_cacti_log']       = [];
	$GLOBALS['__test_form_validate']   = [];

	$GLOBALS['__test_config_options']['mt_ignorePortsRegex']  = '';
	$GLOBALS['__test_config_options']['mt_ignorePorts_delim'] = '-1';

	$this->device = [
		'site_id'          => 1, 'device_id' => 2, 'host_id' => 0, 'hostname' => '192.0.2.1', 'device_name' => 'sw1',
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

it('words the shared form field for a device and for a site', function () {
	$device = mactrack_ignore_regex_field(false);
	$site   = mactrack_ignore_regex_field(true);

	expect($device['value'])->toBe('|arg1:ignorePortsRegex|');
	expect($site['value'])->toBe('|arg1:ignorePortsRegex|');
	expect($device['description'])->toContain('this device')->toContain('site and global');
	expect($site['description'])->toContain('every device in this site')->toContain('device and global');
});

it('uses a text area like the Ports to Ignore of a device in every form', function () {
	global $settings, $settings_user, $tabs_graphs;

	$settings      = ['mail' => [], 'path' => []];
	$settings_user = [];
	$tabs_graphs   = [];
	mactrack_config_settings();

	foreach ([mactrack_ignore_regex_field(false), mactrack_ignore_regex_field(true), $settings['mactrack']['mt_ignorePortsRegex']] as $field) {
		expect($field['method'])->toBe('textarea');
		expect($field['class'])->toBe('textAreaNotes');
		expect($field['textarea_rows'])->toBe('3');
		expect($field['textarea_cols'])->toBe('80');
		expect($field['max_length'])->toBe('255');
		expect($field)->not->toHaveKey('size');
		expect($field['description'])->toContain('own line');
	}
});

it('treats each line of an expression as its own', function () {
	$compiled = mactrack_compile_port_regex("uplink\r\n  ^Po[0-9]+\$ \n\n");

	expect(preg_match($compiled, 'UPLINK-1'))->toBe(1);
	expect(preg_match($compiled, 'Po12'))->toBe(1);
	expect(preg_match($compiled, 'Gi1/1'))->toBe(0);
	expect(mactrack_compile_port_regex("uplink\n"))->toBe("\x01uplink\x01i");
	expect(port_regex_quiet(fn () => mactrack_compile_port_regex("uplink\n(bad")))->toBe('');
	expect(mactrack_port_regex_is_valid("uplink\ntrunk"))->toBeTrue();
	expect(mactrack_validate_port_regex_field("  uplink\ntrunk\n", 'ignorePortsRegex'))->toBe("uplink\ntrunk");
});

it('skips the ports of any line of a device expression', function () {
	$this->device['ignorePortsRegex'] = "uplink\n^Po[0-9]+\$";

	db_store_device_port_results($this->device, $this->ports, '2026-11-12 00:00:00');

	expect(port_regex_stored_macs())->toBe(['001122000001']);
});

it('flags a pending upgrade when an installed version lacks the regular expression columns', function (array $devices, array $sites, bool $pending) {
	mactrack_test_queue_return_for('db_fetch_assoc', "SHOW TABLES LIKE 'mac_track_aggregated_ports'", []);
	mactrack_test_queue_return_for('db_fetch_assoc', "SHOW TABLES LIKE 'mac_track_aggregated_ports_upgrade'", []);
	mactrack_test_queue_return_for('db_fetch_assoc', "SHOW TABLES LIKE 'mac_track_devices'", [['t' => 'mac_track_devices']]);
	mactrack_test_queue_return_for('db_fetch_assoc', "SHOW TABLES LIKE 'mac_track_sites'", [['t' => 'mac_track_sites']]);
	mactrack_test_queue_return_for('db_fetch_assoc', 'SHOW COLUMNS FROM mac_track_devices', $devices);
	mactrack_test_queue_return_for('db_fetch_assoc', 'SHOW COLUMNS FROM mac_track_sites', $sites);

	expect(mactrack_database_upgrade_pending('4.11', ['version' => '4.11']))->toBe($pending);
})->with([
	'devices lack the column' => [[['Field' => 'hostname']], [['Field' => 'ignorePortsRegex']], true],
	'sites lack the column'   => [[['Field' => 'ignorePortsRegex']], [['Field' => 'site_name']], true],
	'both have the column'    => [[['Field' => 'ignorePortsRegex']], [['Field' => 'ignorePortsRegex']], false],
]);

it('matches the interface name and alias of rows that store a bridge port number', function (string $field) {
	$this->device['ignorePortsRegex'] = 'uplink';

	$ports = [
		['mac_address' => '001122000001', 'port_number' => '1', 'port_name' => 'N/A', 'ifName' => 'Gi1/0/1', 'ifAlias' => 'desk 12'],
		['mac_address' => '001122000002', 'port_number' => '2', 'port_name' => 'N/A', 'ifName' => 'Gi1/0/2', 'ifAlias' => 'desk 13'],
	];
	$ports[1][$field] = 'core uplink';

	db_store_device_port_results($this->device, $ports, '2026-11-12 00:00:00');

	expect(port_regex_stored_macs())->toBe(['001122000001']);
})->with(['ifName', 'ifAlias']);

it('keeps rows with the same bridge port number apart when their interface names differ', function () {
	$this->device['ignorePortsRegex'] = 'uplink';

	$ports = [
		['mac_address' => '001122000001', 'port_number' => '1', 'port_name' => 'N/A', 'ifName' => 'Gi1/0/1', 'ifAlias' => 'uplink'],
		['mac_address' => '001122000002', 'port_number' => '1', 'port_name' => 'N/A', 'ifName' => 'Gi1/0/1', 'ifAlias' => 'desk'],
	];

	db_store_device_port_results($this->device, $ports, '2026-11-12 00:00:00');

	expect(port_regex_stored_macs())->toBe(['001122000002']);
});

it('takes skipped MACs out of the device MAC count that feeds the site totals', function () {
	$this->device['ignorePortsRegex'] = 'uplink';
	$this->device['macs_active']      = 4;

	db_store_device_port_results($this->device, $this->ports, '2026-11-12 00:00:00');

	expect($this->device['macs_active'])->toBe(2);
});

it('never lets the MAC count go below zero', function () {
	$this->device['ignorePortsRegex'] = '.';
	$this->device['macs_active']      = 1;

	db_store_device_port_results($this->device, $this->ports, '2026-11-12 00:00:00');

	expect($this->device['macs_active'])->toBe(0);
});

it('leaves the MAC count alone when nothing is skipped', function () {
	$this->device['macs_active'] = 4;

	db_store_device_port_results($this->device, $this->ports, '2026-11-12 00:00:00');

	expect($this->device['macs_active'])->toBe(4);
});

it('attaches the interface name and alias, and tolerates an unknown interface', function () {
	$interfaces = [7 => ['ifName' => 'Gi1/0/7', 'ifAlias' => 'core uplink'], 8 => ['ifName' => 'Gi1/0/8']];

	expect(mactrack_interface_names($interfaces, 7))->toBe(['ifName' => 'Gi1/0/7', 'ifAlias' => 'core uplink']);
	expect(mactrack_interface_names($interfaces, 8))->toBe(['ifName' => 'Gi1/0/8', 'ifAlias' => '']);
	expect(mactrack_interface_names($interfaces, 99))->toBe(['ifName' => '', 'ifAlias' => '']);
});

it('reports whether any expression is configured without compiling it', function () {
	expect(mactrack_port_ignore_configured($this->device))->toBeFalse();

	$this->device['site_ignorePortsRegex'] = '(unclosed';
	expect(mactrack_port_ignore_configured($this->device))->toBeTrue();

	$this->device['site_ignorePortsRegex'] = '   ';
	$GLOBALS['__test_config_options']['mt_ignorePortsRegex'] = 'uplink';
	expect(mactrack_port_ignore_configured($this->device))->toBeTrue();
});

it('skips MACs on an uplink found through the bridge tables, by interface alias', function () {
	$GLOBALS['__test_snmp_walk'] = [];
	$this->device += [
		'snmp_readstring'    => 'x', 'snmp_version' => 2, 'snmp_username' => '', 'snmp_password' => '',
		'snmp_auth_protocol' => '', 'snmp_priv_passphrase' => '', 'snmp_priv_protocol' => '',
		'snmp_context'       => '', 'snmp_engine_id' => '', 'snmp_port' => 161, 'snmp_timeout' => 500,
		'snmp_retries'       => 1, 'max_oids' => 10, 'ignorePorts' => '', 'snmp_sysDescr' => '',
	];
	$this->device['ignorePortsRegex'] = 'uplink';

	mactrack_test_snmp_walk('.1.3.6.1.2.1.17.1.4.1.2', [1 => '1', 2 => '2']);
	mactrack_test_snmp_walk('.1.3.6.1.2.1.2.2.1.8', [1 => '1', 2 => '1']);
	mactrack_test_snmp_walk('.1.3.6.1.2.1.17.4.3.1.3', ['0.0.0.0.0.1' => '3', '0.0.0.0.0.2' => '3']);
	mactrack_test_snmp_walk('.1.3.6.1.2.1.17.4.3.1.2', ['0.0.0.0.0.1' => '1', '0.0.0.0.0.2' => '2']);
	mactrack_test_snmp_walk('.1.3.6.1.2.1.17.4.3.1.1', ['0.0.0.0.0.1' => "\0\0\0\0\0\1", '0.0.0.0.0.2' => "\0\0\0\0\0\2"]);

	$ifInterfaces = [
		1 => ['ifType' => 6, 'ifName' => 'Gi1/0/1', 'ifAlias' => 'core uplink'],
		2 => ['ifType' => 6, 'ifName' => 'Gi1/0/2', 'ifAlias' => 'desk 13'],
	];

	get_base_dot1dTpFdbEntry_ports('site', $this->device, $ifInterfaces, '', true, 0, 0);

	expect(port_regex_stored_macs())->toBe(['000000000002']);
	expect($this->device['macs_active'])->toBe(1);
});

// Walks a switch through one of the generic scanners: two ports up, MACs 1 and 2 behind them,
// and the first port's alias is "core uplink".
function port_regex_generic_scan(string $scanner, array $device, string $bridge): array {
	$GLOBALS['__test_snmp_walk']       = [];
	$GLOBALS['__test_snmp_walk_calls'] = [];

	$device += [
		'snmp_readstring'    => 'x', 'snmp_version' => 2, 'snmp_username' => '', 'snmp_password' => '',
		'snmp_auth_protocol' => '', 'snmp_priv_passphrase' => '', 'snmp_priv_protocol' => '',
		'snmp_context'       => '', 'snmp_engine_id' => '', 'snmp_port' => 161, 'snmp_timeout' => 500,
		'snmp_retries'       => 1, 'max_oids' => 10, 'ignorePorts' => '', 'snmp_sysDescr' => '',
		'last_rundate'       => '2026-11-12 00:00:00',
	];

	mactrack_test_snmp_walk('.1.3.6.1.2.1.2.2.1.1', [1 => '1', 2 => '2']);
	mactrack_test_snmp_walk('.1.3.6.1.2.1.2.2.1.3', [1 => '6', 2 => '6']);
	mactrack_test_snmp_walk('.1.3.6.1.2.1.2.2.1.8', [1 => '1', 2 => '1']);
	mactrack_test_snmp_walk('.1.3.6.1.2.1.31.1.1.1.1', [1 => 'Gi1/0/1', 2 => 'Gi1/0/2']);
	mactrack_test_snmp_walk('.1.3.6.1.2.1.31.1.1.1.18', [1 => 'core uplink', 2 => 'desk 13']);
	mactrack_test_snmp_walk('.1.3.6.1.2.1.17.1.4.1.2', [1 => '1', 2 => '2']);

	if ($bridge === 'dot1q') {
		mactrack_test_snmp_walk('.1.3.6.1.2.1.17.7.1.2.2.1.3', ['1.0.0.0.0.0.1' => '3', '1.0.0.0.0.0.2' => '3']);
		mactrack_test_snmp_walk('.1.3.6.1.2.1.17.7.1.2.2.1.2', ['1.0.0.0.0.0.1' => '1', '1.0.0.0.0.0.2' => '2']);
	} else {
		mactrack_test_snmp_walk('.1.3.6.1.2.1.17.4.3.1.3', ['0.0.0.0.0.1' => '3', '0.0.0.0.0.2' => '3']);
		mactrack_test_snmp_walk('.1.3.6.1.2.1.17.4.3.1.2', ['0.0.0.0.0.1' => '1', '0.0.0.0.0.2' => '2']);
		mactrack_test_snmp_walk('.1.3.6.1.2.1.17.4.3.1.1', ['0.0.0.0.0.1' => "\0\0\0\0\0\1", '0.0.0.0.0.2' => "\0\0\0\0\0\2"]);
	}

	$scanner('site', $device, 0, 0);

	return $device;
}

it('reads interface aliases in each generic scanner only when an expression is configured', function (string $scanner, string $bridge) {
	port_regex_generic_scan($scanner, $this->device, $bridge);

	expect($GLOBALS['__test_snmp_walk_calls'])->not->toContain('.1.3.6.1.2.1.31.1.1.1.18');
	expect(port_regex_stored_macs())->toBe(['000000000001', '000000000002']);

	$GLOBALS['__test_db_calls'] = [];
	$this->device['ignorePortsRegex'] = 'uplink';

	$device = port_regex_generic_scan($scanner, $this->device, $bridge);

	expect($GLOBALS['__test_snmp_walk_calls'])->toContain('.1.3.6.1.2.1.31.1.1.1.18');
	expect(port_regex_stored_macs())->toBe(['000000000002']);
	expect($device['macs_active'])->toBe(1);
})->with([
	'dot1d'    => ['get_generic_switch_ports', 'dot1d'],
	'dot1q'    => ['get_generic_dot1q_switch_ports', 'dot1q'],
	'wireless' => ['get_generic_wireless_ports', 'dot1d'],
]);
