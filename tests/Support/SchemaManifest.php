<?php

if (PHP_SAPI !== 'cli') {
	exit(1);
}
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

return [
	'tables' => [
		'mac_track_aggregated_ports',
		'mac_track_approved_macs',
		'mac_track_arp',
		'mac_track_device_types',
		'mac_track_devices',
		'mac_track_dot1x',
		'mac_track_interface_graphs',
		'mac_track_interfaces',
		'mac_track_ip_ranges',
		'mac_track_ips',
		'mac_track_macauth',
		'mac_track_macwatch',
		'mac_track_oui_database',
		'mac_track_ports',
		'mac_track_processes',
		'mac_track_scan_dates',
		'mac_track_scanning_functions',
		'mac_track_sites',
		'mac_track_snmp',
		'mac_track_snmp_items',
		'mac_track_temp_ports',
		'mac_track_vlans',
	],
	'critical_columns' => [
		'mac_track_devices' => ['device_id', 'host_id', 'hostname', 'snmp_port', 'device_type_id', 'scan_type'],
		'mac_track_interfaces' => ['site_id', 'device_id', 'ifIndex', 'ifPhysAddress', 'ifOperStatus', 'ifHighSpeed'],
		'mac_track_ports' => ['site_id', 'device_id', 'mac_address', 'ip_address', 'port_number', 'authorized'],
		'mac_track_processes' => ['device_id', 'process_id', 'status', 'start_date'],
		'mac_track_sites' => ['site_id', 'site_name'],
	],
	'critical_indexes' => [
		'mac_track_devices' => ['PRIMARY', 'hostname_snmp_port_site_id'],
		'mac_track_interfaces' => ['PRIMARY'],
		'mac_track_ports' => ['PRIMARY'],
		'mac_track_sites' => ['PRIMARY'],
	],
];
