<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../include/cli_check.php';

$action = $argv[1] ?? '';

if ($action === 'seed') {
	set_config_option('mt_collection_timing', '5');
	set_config_option('mt_processes', '1');
	set_config_option('mt_reverse_dns', '');

	$siteId = (int) db_fetch_cell("SELECT site_id FROM mac_track_sites WHERE site_name = 'Default'");

	db_execute_prepared('DELETE FROM mac_track_devices WHERE hostname = ?', ['snmp-agent']);
	db_execute_prepared('DELETE FROM mac_track_device_types WHERE description = ?', ['Mactrack production-path Linux']);
	db_execute_prepared(
		'INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match,
			scanning_function, ip_scanning_function, dot1x_scanning_function,
			serial_number_oid, lowPort, highPort, disabled)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
		[
			'Mactrack production-path Linux', 'Net-SNMP', '1', '*Linux*', '',
			'get_linux_switch_ports', '', '', '', 0, 0, '',
		]
	);

	db_execute_prepared(
		'INSERT INTO mac_track_devices
			(site_id, device_name, hostname, disabled, scan_type, snmp_readstring,
			snmp_version, snmp_port, snmp_timeout, snmp_retries, max_oids)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
		[$siteId, 'Production path SNMP agent', 'snmp-agent', '', 1, 'public', '2', 161, 1000, 1, 10]
	);

	print db_fetch_insert_id();
	exit(0);
}

if ($action === 'assert') {
	$deviceId = filter_var($argv[2] ?? null, FILTER_VALIDATE_INT);

	if ($deviceId === false) {
		fwrite(STDERR, "Production probe requires a numeric device id\n");
		exit(2);
	}

	$device = db_fetch_row_prepared(
		'SELECT snmp_status, snmp_sysDescr, device_type_id, last_rundate, last_runmessage
		FROM mac_track_devices WHERE device_id = ?',
		[$deviceId]
	);

	$passed = (int) ($device['snmp_status'] ?? 0) === HOST_UP
		&& str_contains((string) ($device['snmp_sysDescr'] ?? ''), 'Linux')
		&& (int) ($device['device_type_id'] ?? 0) > 0
		&& ($device['last_rundate'] ?? '0000-00-00 00:00:00') !== '0000-00-00 00:00:00';

	if (!$passed) {
		fwrite(STDERR, 'Production scanner assertion failed: ' . json_encode($device, JSON_THROW_ON_ERROR) . "\n");
		exit(1);
	}

	print "Mactrack production scanner passed against the SNMP agent\n";
	exit(0);
}

fwrite(STDERR, "Usage: mactrack_production_probe.php seed|assert [device-id]\n");
exit(2);
