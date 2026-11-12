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
 * The device CSV export leaves out the SNMP community string, auth password
 * and privacy passphrase (#317). The import matches columns by header name,
 * so re-importing an export keeps the stored values.
 */

/*
 * mactrack_devices.php handles the request when it is included, so load only
 * the export function from its source, with its two helpers stubbed.
 */
if (!function_exists('mactrack_device_export')) {
	$source = (string) file_get_contents(dirname(__DIR__, 2) . '/mactrack_devices.php');
	$start  = strpos($source, 'function mactrack_device_export(');
	$end    = strpos($source, "\n}\n", $start);

	eval(substr($source, $start, $end - $start + 2));
}

if (!function_exists('mactrack_device_request_validation')) {
	function mactrack_device_request_validation(): void {
	}
}

if (!function_exists('mactrack_get_devices')) {
	function mactrack_get_devices(&$sql_where, $rows, $apply_limits = true): array {
		return $GLOBALS['__test_export_devices'];
	}
}

test('the device export leaves out the SNMP secrets', function () {
	$device = array_fill_keys([
		'site_id', 'site_name', 'device_id', 'device_name', 'notes', 'hostname',
		'snmp_options', 'snmp_version', 'snmp_username', 'snmp_auth_protocol',
		'snmp_priv_protocol', 'snmp_context', 'snmp_engine_id', 'snmp_port',
		'snmp_timeout', 'snmp_retries', 'max_oids', 'snmp_sysName',
		'snmp_sysLocation', 'snmp_sysContact', 'snmp_sysObjectID', 'snmp_sysDescr',
		'snmp_sysUptime', 'ignorePorts', 'scan_type', 'disabled', 'ports_total',
		'ports_active', 'ports_trunk', 'macs_active', 'last_rundate', 'last_runduration',
	], 'x');

	$device['hostname']             = 'sw1.example.net';
	$device['snmp_readstring']      = 'community-secret';
	$device['snmp_password']        = 'auth-secret';
	$device['snmp_priv_passphrase'] = 'priv-secret';

	$GLOBALS['__test_export_devices'] = [$device];

	ob_start();
	mactrack_device_export();
	$lines = explode("\n", trim((string) ob_get_clean()));

	$header = array_map('trim', explode(',', $lines[0]));
	$row    = str_getcsv($lines[1], ',', '"', '');

	expect($header)->not->toContain('snmp_readstring')
		->and($header)->not->toContain('snmp_password')
		->and($header)->not->toContain('snmp_priv_passphrase')
		->and($header)->toContain('snmp_username')
		->and($row)->toHaveCount(count($header))
		->and($row[array_search('hostname', $header, true)])->toBe('sw1.example.net')
		->and($lines[1])->not->toContain('secret');
});
