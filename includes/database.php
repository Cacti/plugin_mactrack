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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/**
 * Applies incremental schema migrations to bring an existing MacTrack
 * installation's tables up to the current expected structure (e.g.
 * replacing legacy auto-increment/unique-key definitions on
 * mac_track_devices and mac_track_device_types with the current
 * primary key/unique index layout). Idempotent: each change is guarded
 * by a check for the old structure via mactrack_db_key_exists().
 *
 * @return bool True when the schema migration completed, false when the
 *              mac_track_aggregated_ports rebuild could not finish this pass
 *              (so the caller can leave the plugin version unrecorded and
 *              retry later).
 *
 * @global string $database_default Reserved/declared for parity with
 *                                 other functions in this file; not
 *                                 used directly here.
 */
function mactrack_database_upgrade(): bool {
	global $database_default;

	if (mactrack_db_key_exists('mac_track_devices', 'device_id_UNIQUE')) {
		db_execute('ALTER TABLE `mac_track_devices` DROP KEY device_id_UNIQUE');
	}

	if (mactrack_db_key_exists('mac_track_device_types', 'device_type_id_UNIQUE')) {
		db_execute('ALTER TABLE `mac_track_device_types` DROP KEY `device_type_id_UNIQUE`');
	}

	if (mactrack_db_key_exists('mac_track_devices', 'device_id')) {
		db_execute('ALTER TABLE `mac_track_devices`
			DROP PRIMARY KEY,
			ADD PRIMARY KEY (device_id),
			DROP INDEX device_id,
			ADD UNIQUE INDEX hostname_snmp_port (hostname, snmp_port)');
	}

	if (mactrack_db_key_exists('mac_track_device_types', 'device_type_id')) {
		db_execute('ALTER TABLE `mac_track_device_types`
			DROP PRIMARY KEY,
			ADD PRIMARY KEY (device_type_id),
			DROP INDEX device_type_id,
			ADD UNIQUE INDEX snmp_info (`sysDescr_match`,`sysObjectID_match`,`device_type`)');
	}

	mactrack_add_column('mac_track_interfaces',
		'ifHighSpeed',
		"ALTER TABLE `mac_track_interfaces` ADD COLUMN `ifHighSpeed` int(10) unsigned NOT NULL default '0' AFTER `ifSpeed`");

	mactrack_add_column('mac_track_interfaces',
		'ifDuplex',
		"ALTER TABLE `mac_track_interfaces` ADD COLUMN `ifDuplex` int(10) unsigned NOT NULL default '0' AFTER `ifHighSpeed`");

	mactrack_add_column('mac_track_interfaces',
		'int_ifInDiscards',
		"ALTER TABLE `mac_track_interfaces` ADD COLUMN `int_ifInDiscards` int(10) unsigned NOT NULL default '0' AFTER `ifOutErrors`");

	mactrack_add_column('mac_track_interfaces',
		'int_ifInErrors',
		"ALTER TABLE `mac_track_interfaces` ADD COLUMN `int_ifInErrors` int(10) unsigned NOT NULL default '0' AFTER `int_ifInDiscards`");

	mactrack_add_column('mac_track_interfaces',
		'int_ifInUnknownProtos',
		"ALTER TABLE `mac_track_interfaces` ADD COLUMN `int_ifInUnknownProtos` int(10) unsigned NOT NULL default '0' AFTER `int_ifInErrors`");

	mactrack_add_column('mac_track_interfaces',
		'int_ifOutDiscards',
		"ALTER TABLE `mac_track_interfaces` ADD COLUMN `int_ifOutDiscards` int(10) unsigned NOT NULL default '0' AFTER `int_ifInUnknownProtos`");

	mactrack_add_column('mac_track_interfaces',
		'int_ifOutErrors',
		"ALTER TABLE `mac_track_interfaces` ADD COLUMN `int_ifOutErrors` int(10) unsigned NOT NULL default '0' AFTER `int_ifOutDiscards`");

	mactrack_add_column('mac_track_devices',
		'host_id',
		"ALTER TABLE `mac_track_devices` ADD COLUMN `host_id` int(10) unsigned NOT NULL default '0' AFTER `device_id`");

	mactrack_add_column('mac_track_macwatch',
		'date_last_notif',
		"ALTER TABLE `mac_track_macwatch` ADD COLUMN `date_last_notif` TIMESTAMP DEFAULT '0000-00-00 00:00:00' AFTER `date_last_seen`");

	mactrack_execute_sql('Add length to Device Types Match Fields', "ALTER TABLE `mac_track_device_types` MODIFY COLUMN `sysDescr_match` VARCHAR(100) NOT NULL default '', MODIFY COLUMN `sysObjectID_match` VARCHAR(100) NOT NULL default ''");

	mactrack_execute_sql('Correct a Scanning Function Bug', "DELETE FROM mac_track_scanning_functions WHERE scanning_function='Not Applicable - Hub/Switch'");

	mactrack_add_column('mac_track_devices',
		'host_id',
		"ALTER TABLE `mac_track_devices` ADD COLUMN `host_id` INTEGER UNSIGNED NOT NULL default '0' AFTER `device_id`");

	mactrack_add_index('mac_track_devices',
		'host_id',
		'ALTER TABLE `mac_track_devices` ADD INDEX `host_id`(`host_id`)');

	mactrack_add_index('mac_track_ports',
		'scan_date',
		'ALTER TABLE `mac_track_ports` ADD INDEX `scan_date` USING BTREE(`scan_date`)');

	mactrack_add_column('mac_track_interfaces',
		'sysUptime',
		"ALTER TABLE mac_track_interfaces ADD COLUMN `sysUptime` int(10) unsigned NOT NULL default '0' AFTER `device_id`");

	mactrack_add_column('mac_track_interfaces',
		'ifInOctets',
		"ALTER TABLE mac_track_interfaces ADD COLUMN `ifInOctets` int(10) unsigned NOT NULL default '0' AFTER `vlan_trunk_status`");

	mactrack_add_column('mac_track_interfaces',
		'ifOutOctets',
		"ALTER TABLE mac_track_interfaces ADD COLUMN `ifOutOctets` int(10) unsigned NOT NULL default '0' AFTER `ifInOctets`");

	mactrack_add_column('mac_track_interfaces',
		'ifHCInOctets',
		"ALTER TABLE mac_track_interfaces ADD COLUMN `ifHCInOctets` bigint(20) unsigned NOT NULL default '0' AFTER `ifOutOctets`");

	mactrack_add_column('mac_track_interfaces',
		'ifHCOutOctets',
		"ALTER TABLE mac_track_interfaces ADD COLUMN `ifHCOutOctets` bigint(20) unsigned NOT NULL default '0' AFTER `ifHCInOctets`");

	mactrack_add_column('mac_track_interfaces',
		'ifInUcastPkts',
		"ALTER TABLE mac_track_interfaces ADD COLUMN `ifInUcastPkts` int(10) unsigned NOT NULL default '0' AFTER `ifHCOutOctets`");

	mactrack_add_column('mac_track_interfaces',
		'ifOutUcastPkts',
		"ALTER TABLE mac_track_interfaces ADD COLUMN `ifOutUcastPkts` int(10) unsigned NOT NULL default '0' AFTER `ifInUcastPkts`");

	mactrack_add_column('mac_track_interfaces',
		'ifInMulticastPkts',
		"ALTER TABLE mac_track_interfaces ADD COLUMN `ifInMulticastPkts` int(10) unsigned NOT NULL default '0' AFTER `ifOutUcastPkts`");

	mactrack_add_column('mac_track_interfaces',
		'ifOutMulticastPkts',
		"ALTER TABLE mac_track_interfaces ADD COLUMN `ifOutMulticastPkts` int(10) unsigned NOT NULL default '0' AFTER `ifInMulticastPkts`");

	mactrack_add_column('mac_track_interfaces',
		'ifInBroadcastPkts',
		"ALTER TABLE mac_track_interfaces ADD COLUMN `ifInBroadcastPkts` int(10) unsigned NOT NULL default '0' AFTER `ifOutMulticastPkts`");

	mactrack_add_column('mac_track_interfaces',
		'ifOutBroadcastPkts',
		"ALTER TABLE mac_track_interfaces ADD COLUMN `ifOutBroadcastPkts` int(10) unsigned NOT NULL default '0' AFTER `ifInBroadcastPkts`");

	mactrack_add_column('mac_track_interfaces',
		'inBound',
		"ALTER TABLE mac_track_interfaces ADD COLUMN `inBound` double NOT NULL default '0' AFTER `ifOutErrors`");

	mactrack_add_column('mac_track_interfaces',
		'outBound',
		"ALTER TABLE mac_track_interfaces ADD COLUMN `outBound` double NOT NULL default '0' AFTER `inBound`");

	mactrack_add_column('mac_track_interfaces',
		'int_ifInOctets',
		"ALTER TABLE mac_track_interfaces ADD COLUMN `int_ifInOctets` int(10) unsigned NOT NULL default '0' AFTER `outBound`");

	mactrack_add_column('mac_track_interfaces',
		'int_ifOutOctets',
		"ALTER TABLE mac_track_interfaces ADD COLUMN `int_ifOutOctets` int(10) unsigned NOT NULL default '0' AFTER `int_ifInOctets`");

	mactrack_add_column('mac_track_interfaces',
		'int_ifHCInOctets',
		"ALTER TABLE mac_track_interfaces ADD COLUMN `int_ifHCInOctets` bigint(20) unsigned NOT NULL default '0' AFTER `int_ifOutOctets`");

	mactrack_add_column('mac_track_interfaces',
		'int_ifHCOutOctets',
		"ALTER TABLE mac_track_interfaces ADD COLUMN `int_ifHCOutOctets` bigint(20) unsigned NOT NULL default '0' AFTER `int_ifHCInOctets`");

	mactrack_add_column('mac_track_interfaces',
		'int_ifInUcastPkts',
		"ALTER TABLE mac_track_interfaces ADD COLUMN `int_ifInUcastPkts` int(10) unsigned NOT NULL default '0' AFTER `int_ifHCOutOctets`");

	mactrack_add_column('mac_track_interfaces',
		'int_ifOutUcastPkts',
		"ALTER TABLE mac_track_interfaces ADD COLUMN `int_ifOutUcastPkts` int(10) unsigned NOT NULL default '0' AFTER `int_ifInUcastPkts`");

	mactrack_add_column('mac_track_interfaces',
		'int_ifInMulticastPkts',
		"ALTER TABLE mac_track_interfaces ADD COLUMN `int_ifInMulticastPkts` int(10) unsigned NOT NULL default '0' AFTER `int_ifOutUcastPkts`");

	mactrack_add_column('mac_track_interfaces',
		'int_ifOutMulticastPkts',
		"ALTER TABLE mac_track_interfaces ADD COLUMN `int_ifOutMulticastPkts` int(10) unsigned NOT NULL default '0' AFTER `int_ifInMulticastPkts`");

	mactrack_add_column('mac_track_interfaces',
		'int_ifInBroadcastPkts',
		"ALTER TABLE mac_track_interfaces ADD COLUMN `int_ifInBroadcastPkts` int(10) unsigned NOT NULL default '0' AFTER `int_ifOutMulticastPkts`");

	mactrack_add_column('mac_track_interfaces',
		'int_ifOutBroadcastPkts',
		"ALTER TABLE mac_track_interfaces ADD COLUMN `int_ifOutBroadcastPkts` int(10) unsigned NOT NULL default '0' AFTER `int_ifInBroadcastPkts`");

	if (!mactrack_db_key_exists('mac_track_ports', 'site_id_device_id')) {
		db_execute('ALTER TABLE `mac_track_ports` ADD INDEX `site_id_device_id`(`site_id`, `device_id`);');
	}

	// new for 2.1.2
	// SNMP V3
	mactrack_add_column('mac_track_devices',
		'term_type',
		"ALTER TABLE `mac_track_devices` ADD COLUMN `term_type` tinyint(11) NOT NULL default '1' AFTER `scan_type`");

	mactrack_add_column('mac_track_devices',
		'user_name',
		'ALTER TABLE `mac_track_devices` ADD COLUMN `user_name` varchar(40) default NULL AFTER `term_type`');

	mactrack_add_column('mac_track_devices',
		'user_password',
		'ALTER TABLE `mac_track_devices` ADD COLUMN `user_password` varchar(40) default NULL AFTER `user_name`');

	mactrack_add_column('mac_track_devices',
		'private_key_path',
		"ALTER TABLE `mac_track_devices` ADD COLUMN `private_key_path` varchar(128) default '' AFTER `user_password`");

	mactrack_add_column('mac_track_devices',
		'snmp_options',
		"ALTER TABLE `mac_track_devices` ADD COLUMN `snmp_options` int(10) unsigned NOT NULL default '0' AFTER `private_key_path`");

	mactrack_add_column('mac_track_devices',
		'snmp_username',
		'ALTER TABLE `mac_track_devices` ADD COLUMN `snmp_username` varchar(50) default NULL AFTER `snmp_status`');

	mactrack_add_column('mac_track_devices',
		'snmp_password',
		'ALTER TABLE `mac_track_devices` ADD COLUMN `snmp_password` varchar(50) default NULL AFTER `snmp_username`');

	mactrack_add_column('mac_track_devices',
		'snmp_auth_protocol',
		"ALTER TABLE `mac_track_devices` ADD COLUMN `snmp_auth_protocol` char(5) default '' AFTER `snmp_password`");

	mactrack_add_column('mac_track_devices',
		'snmp_priv_passphrase',
		"ALTER TABLE `mac_track_devices` ADD COLUMN `snmp_priv_passphrase` varchar(200) default '' AFTER `snmp_auth_protocol`");

	mactrack_add_column('mac_track_devices',
		'snmp_priv_protocol',
		"ALTER TABLE `mac_track_devices` ADD COLUMN `snmp_priv_protocol` char(6) default '' AFTER `snmp_priv_passphrase`");

	mactrack_add_column('mac_track_devices',
		'snmp_context',
		"ALTER TABLE `mac_track_devices` ADD COLUMN `snmp_context` varchar(64) default '' AFTER `snmp_priv_protocol`");

	mactrack_add_column('mac_track_devices',
		'max_oids',
		"ALTER TABLE `mac_track_devices` ADD COLUMN `max_oids` int(12) unsigned default '10' AFTER `snmp_context`");

	mactrack_add_column('mac_track_devices',
		'snmp_engine_id',
		"ALTER TABLE `mac_track_devices` ADD COLUMN `snmp_engine_id` varchar(64) default '' AFTER `snmp_context`");

	mactrack_add_column('mac_track_devices',
		'term_type',
		"ALTER TABLE `mac_track_devices` ADD COLUMN `term_type` tinyint(11) NOT NULL default '1' AFTER `scan_type`");

	mactrack_add_column('mac_track_devices',
		'private_key_path',
		"ALTER TABLE `mac_track_devices` ADD COLUMN `private_key_path` varchar(128) default '' AFTER `user_password`");

	if (!db_table_exists('mac_track_snmp')) {
		$data              = [];
		$data['columns'][] = ['name' => 'id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'auto_increment' => true];
		$data['columns'][] = ['name' => 'name', 'type' => 'varchar(100)', 'NULL' => false];
		$data['primary']   = 'id';
		$data['type']      = 'InnoDB';
		$data['comment']   = 'Group of SNMP Option Sets';
		api_plugin_db_table_create('mactrack', 'mac_track_snmp', $data);
	}

	if (!db_table_exists('mac_track_snmp_items')) {
		$data              = [];
		$data['columns'][] = ['name' => 'id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'auto_increment' => true];
		$data['columns'][] = ['name' => 'snmp_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
		$data['columns'][] = ['name' => 'sequence', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
		$data['columns'][] = ['name' => 'snmp_version', 'type' => 'varchar(100)', 'NULL' => false, 'default' => ''];
		$data['columns'][] = ['name' => 'snmp_readstring', 'type' => 'varchar(100)', 'NULL' => false, 'default' => ''];
		$data['columns'][] = ['name' => 'snmp_port', 'type' => 'int(10)', 'NULL' => false, 'default' => '161'];
		$data['columns'][] = ['name' => 'snmp_timeout', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '500'];
		$data['columns'][] = ['name' => 'snmp_retries', 'unsigned' => true, 'type' => 'tinyint(11)', 'NULL' => false, 'default' => '3'];
		$data['columns'][] = ['name' => 'max_oids', 'unsigned' => true, 'type' => 'int(12)', 'NULL' => true, 'default' => '10'];
		$data['columns'][] = ['name' => 'snmp_username', 'type' => 'varchar(50)', 'NULL' => true];
		$data['columns'][] = ['name' => 'snmp_password', 'type' => 'varchar(50)', 'NULL' => true];
		$data['columns'][] = ['name' => 'snmp_auth_protocol', 'type' => 'char(5)', 'NULL' => true];
		$data['columns'][] = ['name' => 'snmp_priv_passphrase', 'type' => 'varchar(200)', 'NULL' => true];
		$data['columns'][] = ['name' => 'snmp_priv_protocol', 'type' => 'char(6)', 'NULL' => true];
		$data['columns'][] = ['name' => 'snmp_context', 'type' => 'varchar(64)', 'NULL' => true];
		$data['columns'][] = ['name' => 'snmp_engine_id', 'type' => 'varchar(64)', 'NULL' => true];
		$data['primary']   = 'id`,`snmp_id';
		$data['type']      = 'InnoDB';
		$data['comment']   = 'Set of SNMP Options';
		api_plugin_db_table_create('mactrack', 'mac_track_snmp_items', $data);
	}

	mactrack_add_column('mac_track_snmp_items',
		'snmp_engine_id',
		"ALTER TABLE `mac_track_snmp_items` ADD COLUMN `snmp_engine_id` varchar(64) default '' AFTER `snmp_context`");

	if (!db_table_exists('mac_track_interface_graphs')) {
		$data              = [];
		$data['columns'][] = ['name' => 'device_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
		$data['columns'][] = ['name' => 'ifIndex', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false];
		$data['columns'][] = ['name' => 'ifName', 'type' => 'varchar(20)', 'NULL' => false, 'default' => ''];
		$data['columns'][] = ['name' => 'host_id', 'type' => 'int(11)', 'NULL' => false, 'default' => '0'];
		$data['columns'][] = ['name' => 'local_graph_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false];
		$data['columns'][] = ['name' => 'snmp_query_id', 'type' => 'int(11)', 'NULL' => false, 'default' => '0'];
		$data['columns'][] = ['name' => 'graph_template_id', 'type' => 'int(11)', 'NULL' => false, 'default' => '0'];
		$data['columns'][] = ['name' => 'field_name', 'type' => 'varchar(20)', 'NULL' => false, 'default' => ''];
		$data['columns'][] = ['name' => 'field_value', 'type' => 'varchar(25)', 'NULL' => false, 'default' => ''];
		$data['columns'][] = ['name' => 'present', 'type' => 'tinyint(4)', 'NULL' => true, 'default' => '1'];
		$data['primary']   = 'local_graph_id`,`device_id`,`ifIndex`,`host_id';
		$data['keys'][]    = ['name' => 'host_id', 'columns' => 'host_id'];
		$data['keys'][]    = ['name' => 'device_id', 'columns' => 'device_id'];
		$data['type']      = 'InnoDB';
		$data['comment']   = '';
		api_plugin_db_table_create('mactrack', 'mac_track_interface_graphs', $data);
	}

	mactrack_add_column('mac_track_interfaces',
		'ifMauAutoNegAdminStatus',
		"ALTER TABLE `mac_track_interfaces` ADD COLUMN `ifMauAutoNegAdminStatus` integer UNSIGNED NOT NULL default '0' AFTER `ifDuplex`");

	mactrack_add_column('mac_track_interfaces',
		'ifMauAutoNegRemoteSignaling',
		"ALTER TABLE `mac_track_interfaces` ADD COLUMN `ifMauAutoNegRemoteSignaling` integer UNSIGNED NOT NULL default '0' AFTER `ifMauAutoNegAdminStatus`");

	mactrack_add_column('mac_track_device_types',
		'dot1x_scanning_function',
		"ALTER TABLE `mac_track_device_types` ADD COLUMN `dot1x_scanning_function` varchar(100) default '' AFTER `ip_scanning_function`");

	mactrack_add_column('mac_track_device_types',
		'serial_number_oid',
		"ALTER TABLE `mac_track_device_types` ADD COLUMN `serial_number_oid` varchar(100) default '' AFTER `dot1x_scanning_function`");

	mactrack_add_column('mac_track_sites',
		'customer_contact',
		"ALTER TABLE `mac_track_sites` ADD COLUMN `customer_contact` varchar(150) default '' AFTER `site_name`");

	mactrack_add_column('mac_track_sites',
		'netops_contact',
		"ALTER TABLE `mac_track_sites` ADD COLUMN `netops_contact` varchar(150) default '' AFTER `customer_contact`");

	mactrack_add_column('mac_track_sites',
		'facilities_contact',
		"ALTER TABLE `mac_track_sites` ADD COLUMN `facilities_contact` varchar(150) default '' AFTER `netops_contact`");

	mactrack_add_column('mac_track_sites',
		'site_info',
		'ALTER TABLE `mac_track_sites` ADD COLUMN `site_info` text AFTER `facilities_contact`');

	mactrack_add_column('mac_track_sites',
		'skip_vlans',
		"ALTER TABLE `mac_track_sites` ADD COLUMN `skip_vlans` text DEFAULT ''");

	mactrack_add_column('mac_track_sites',
		'scan_vlans',
		"ALTER TABLE `mac_track_sites` ADD COLUMN `scan_vlans` text DEFAULT ''");

	mactrack_add_column('mac_track_devices',
		'device_name',
		"ALTER TABLE `mac_track_devices` ADD COLUMN `device_name` varchar(100) default '' AFTER `host_id`");

	mactrack_add_column('mac_track_devices',
		'notes',
		'ALTER TABLE `mac_track_devices` ADD COLUMN `notes` text AFTER `hostname`');

	mactrack_add_column('mac_track_devices',
		'scan_trunk_port',
		"ALTER TABLE `mac_track_devices` ADD COLUMN `scan_trunk_port` text DEFAULT ''");

	mactrack_add_column('mac_track_scanning_functions',
		'type',
		"ALTER TABLE `mac_track_scanning_functions` ADD COLUMN `type` int(10) unsigned NOT NULL default '0' AFTER `scanning_function`");

	mactrack_add_column('mac_track_temp_ports',
		'device_name',
		"ALTER TABLE `mac_track_temp_ports` ADD COLUMN `device_name` varchar(100) NOT NULL default '' AFTER `hostname`");

	mactrack_add_column('mac_track_temp_ports',
		'vendor_mac',
		'ALTER TABLE `mac_track_temp_ports` ADD COLUMN `vendor_mac` varchar(8) default NULL AFTER `mac_address`');

	mactrack_add_column('mac_track_temp_ports',
		'authorized',
		"ALTER TABLE `mac_track_temp_ports` ADD COLUMN `authorized` tinyint(3) unsigned NOT NULL default '0' AFTER `updated`");

	mactrack_add_column('mac_track_ports',
		'device_name',
		"ALTER TABLE `mac_track_ports` ADD COLUMN `device_name` varchar(100) NOT NULL default '' AFTER `hostname`");

	mactrack_add_column('mac_track_ports',
		'vendor_mac',
		'ALTER TABLE `mac_track_ports` ADD COLUMN `vendor_mac` varchar(8) default NULL AFTER `mac_address`');

	mactrack_add_column('mac_track_ports',
		'authorized',
		"ALTER TABLE `mac_track_ports` ADD COLUMN `authorized` tinyint(3) unsigned NOT NULL default '0' AFTER `scan_date`");

	mactrack_add_column('mac_track_ips',
		'device_name',
		"ALTER TABLE `mac_track_ips` ADD COLUMN `device_name` varchar(100) NOT NULL default '' AFTER `hostname`");

	$columns = array_rekey(
		db_fetch_assoc('SHOW COLUMNS FROM mac_track_ips'),
		'Field', 'Type'
	);

	if (strpos($columns['port_number'], 'int(10)') !== false) {
		db_execute("ALTER TABLE mac_track_ips MODIFY COLUMN port_number varchar(20) NOT NULL default ''");

		db_execute("ALTER TABLE mac_track_ports MODIFY COLUMN port_number varchar(20) NOT NULL default ''");

		db_execute("ALTER TABLE mac_track_temp_ports MODIFY COLUMN port_number varchar(20) NOT NULL default ''");

		db_execute("ALTER TABLE mac_track_aggregated_ports MODIFY COLUMN port_number varchar(20) NOT NULL default ''");

		db_execute("ALTER TABLE mac_track_dot1x MODIFY COLUMN port_number varchar(20) NOT NULL default ''");
	} elseif (strpos($columns['port_number'], 'varchar(20)') !== false) {
		db_execute("ALTER TABLE mac_track_ports MODIFY COLUMN port_number varchar(30) NOT NULL default ''");
	} else {
		db_execute("ALTER TABLE mac_track_aggregated_ports MODIFY COLUMN first_scan_date TIMESTAMP NOT NULL DEFAULT '0000-00-00'");

		db_execute("ALTER TABLE mac_track_devices MODIFY COLUMN last_rundate TIMESTAMP NOT NULL DEFAULT '0000-00-00'");

		db_execute("ALTER TABLE mac_track_dot1x MODIFY COLUMN scan_date TIMESTAMP NOT NULL DEFAULT '0000-00-00'");

		db_execute("ALTER TABLE mac_track_ip_ranges MODIFY COLUMN ips_max_date TIMESTAMP NOT NULL DEFAULT '0000-00-00'");

		db_execute("ALTER TABLE mac_track_ip_ranges MODIFY COLUMN ips_current_date TIMESTAMP NOT NULL DEFAULT '0000-00-00'");

		db_execute("ALTER TABLE mac_track_ips MODIFY COLUMN scan_date TIMESTAMP NOT NULL DEFAULT '0000-00-00'");

		db_execute("ALTER TABLE mac_track_ports MODIFY COLUMN scan_date TIMESTAMP NOT NULL DEFAULT '0000-00-00'");

		db_execute("ALTER TABLE mac_track_processes MODIFY COLUMN start_date TIMESTAMP NOT NULL DEFAULT '0000-00-00'");

		db_execute("ALTER TABLE mac_track_scan_dates MODIFY COLUMN scan_date TIMESTAMP NOT NULL DEFAULT '0000-00-00'");

		db_execute("ALTER TABLE mac_track_temp_ports MODIFY COLUMN scan_date TIMESTAMP NOT NULL DEFAULT '0000-00-00'");
	}

	if (!db_table_exists('mac_track_arp')) {
		$data              = [];
		$data['columns'][] = ['name' => 'site_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false];
		$data['columns'][] = ['name' => 'ip_address', 'type' => 'varchar(20)', 'NULL' => true, 'default' => ''];
		$data['columns'][] = ['name' => 'mac_address', 'type' => 'varchar(20)', 'NULL' => false, 'default' => ''];
		$data['columns'][] = ['name' => 'scan_date', 'type' => 'datetime', 'NULL' => false, 'default' => '0000-00-00 00:00:00'];
		$data['primary']   = 'mac_address`, `ip_address';
		$data['type']      = 'InnoDB';
		$data['comment']   = 'Table for VRF ARP translation';
		api_plugin_db_table_create('mactrack', 'mac_track_arp', $data);
	}

	$tables = db_fetch_assoc("SELECT DISTINCT TABLE_NAME
		FROM information_schema.COLUMNS
		WHERE TABLE_SCHEMA = SCHEMA()
		AND TABLE_NAME LIKE 'mac_track%'");

	if (cacti_sizeof($tables)) {
		foreach ($tables as $table) {
			$columns = db_fetch_assoc("SELECT *
				FROM information_schema.COLUMNS
				WHERE TABLE_SCHEMA=SCHEMA()
				AND TABLE_NAME='" . $table['TABLE_NAME'] . "'
				AND DATA_TYPE LIKE '%char%'
				AND COLUMN_DEFAULT IS NULL");

			if (cacti_sizeof($columns)) {
				$alter = 'ALTER TABLE `' . $table['TABLE_NAME'] . '` ';

				$i = 0;

				foreach ($columns as $column) {
					$alter .= ($i == 0 ? '' : ', ') . ' MODIFY COLUMN `' . $column['COLUMN_NAME'] . '` ' . $column['COLUMN_TYPE'] . ($column['IS_NULLABLE'] == 'NO' ? ' NOT NULL' : '') . ' DEFAULT ""';
					$i++;
				}

				db_execute($alter);
			}
		}
	}

	// new for 4.6
	mactrack_add_column('mac_track_device_types',
		'disabled',
		"ALTER TABLE `mac_track_device_types` ADD COLUMN `disabled` varchar(2) default '' AFTER `highPort`");

	// add few device types examples if does not exist
	$count = db_fetch_cell("SELECT COUNT(*) FROM mac_track_device_types WHERE description LIKE '%-default'");

	if ($count == 0) {
		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('Comware 5130-default','H3C','2','','.1.3.6.1.4.1.25506.11.1.18*','get_h3c_3com_switch_ports','get_h3c_3com_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('Aruba 6300-default','Aruba','2','','.1.3.6.1.4.1.47196.4.1.1.1.109','get_aruba_oscx_switch_ports','get_aruba_oscx_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('Aruba 6200-default','Aruba','2','','.1.3.6.1.4.1.47196.4.1.1.1.300','get_aruba_oscx_switch_ports','get_aruba_oscx_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('Comware 1920-default','HPE','2','','.1.3.6.1.4.1.25506.11.1.164','get_h3c_3com_switch_ports','get_h3c_3com_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('Comware 5140-default','HPE','2','','.1.3.6.1.4.1.25506.11.1.297','get_h3c_3com_switch_ports','get_h3c_3com_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('Aruba 2530 8p-default','HPE','2','','.1.3.6.1.4.1.11.2.3.7.11.141','get_generic_switch_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('Aruba 2930 8p-default','Aruba','2','','.1.3.6.1.4.1.11.2.3.7.11.181.16','get_generic_switch_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('C2600 Router-default','Cisco','3','*C2600*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('C2960S Switch-default','Cisco','2','*C2960S*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('C3550 Switch-default','Cisco','2','*C3550*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('C3750 Switch-default','Cisco','2','*C3750*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('45xx Switch-default','Cisco','2','*cat4000*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('45xx Switch-default','Cisco','2','*4500*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('C2900 Router-default','Cisco','3','*C2900*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('ASR Router-default','Cisco','3','*ASR1000*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('6513 Switch/Router-default','Cisco','2','*s72033_rp*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('CE500 Switch-default','Cisco','1','*CE500*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('C2950 Switch-default','Cisco','2','*C2950*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('C2800 Router-default','Cisco','3','*C2800*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('BladeCenter2 10G-default','Cisco','2','*CBS31X0*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('DellBladeCenter-default','Dell','2','*Ethernet Switch*','','get_dell_dot1q_switch_ports','get_CTAlias_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('VG224-default','Cisco','3','*vg224*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('C870 Router-default','Cisco','3','*C870*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('Cisco ASA-default','Cisco','3','*Adaptive Security Appliance*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('C3x50 Switch-default','Cisco','2','*CAT3K*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('C3900 Router-default','Cisco','3','*C3900*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('Blade Center 10/100-default','Cisco','2','*CIGESM-I6K2L2Q4-M*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('CAT4500-default','Cisco','2','*cat4500*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('C3560 Switch-default','Cisco','2','*C3560*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('ProCurve Switch-default','HP','2','*ProCurve Switch*','','get_procurve_ngi_switch_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('CAT3K_CAA-UNIVERSALK9-M-default','Cisco','2','*CAT3K_CAA-UNIVERSALK9-M*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('IBM Flex System Fabric-default','IBM','2','*IBM Flex System Fabric*','','get_generic_dot1q_switch_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('C2960X Switch-default','Cisco','2','*C2960X*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('2960 Switch Lite-default','Cisco','2','*C2960-LANLITEK9-M*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('2960 Switch Base-default','Cisco','2','*C2960-LANBASEK9-M*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('VG3X0-default','Cisco','3','*VG3X0*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('93xx + 94xx Switch-default','Cisco','2','*CAT9K_IOSXE*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('43xx Router-default','Cisco','3','*ISR Software*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('Nexus Generic-default','Cisco','2','*nxos*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('Nexus 7K-default','Cisco','2','*n7000*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','.1.3.6.1.4.1.9.12.3.1.3.932',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('Nexus 5K-default','Cisco','2','*n5000*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('92xx Switch-default','Cisco','1','*CAT9K_LITE_IOSXE*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");
	}

	db_execute("ALTER TABLE mac_track_ips MODIFY COLUMN port_number varchar(30) NOT NULL default ''");
	db_execute("ALTER TABLE mac_track_ports MODIFY COLUMN port_number varchar(30) NOT NULL default ''");
	db_execute("ALTER TABLE mac_track_temp_ports MODIFY COLUMN port_number varchar(30) NOT NULL default ''");
	db_execute("ALTER TABLE mac_track_aggregated_ports MODIFY COLUMN port_number varchar(30) NOT NULL default ''");
	db_execute("ALTER TABLE mac_track_dot1x MODIFY COLUMN port_number varchar(30) NOT NULL default ''");
	db_execute("ALTER TABLE mac_track_devices MODIFY COLUMN scan_trunk_port text NULL default ''");

	// Change old realm names
	if (db_fetch_cell("SELECT count(*) FROM plugin_realms WHERE plugin = 'mactrack' and display='Device Tracking Viewer'")) {
		db_fetch_cell("UPDATE plugin_realms set display='Mactrack Viewer' WHERE plugin = 'mactrack' and display='Device Tracking Viewer'");
	}

	if (db_fetch_cell("SELECT count(*) FROM plugin_realms WHERE plugin = 'mactrack' and display='Device Tracking Administrator'")) {
		db_fetch_cell("UPDATE plugin_realms set display='Mactrack Administrator' WHERE plugin = 'mactrack' and display='Device Tracking Administrator'");
	}

	// 4.9: MAC addresses are stored without separators. The satellite tables
	// have no unique MAC key, so a plain full-table rewrite is safe; the
	// aggregated-ports table is handled separately by the rebuild below
	// because stripping the separators collapses rows onto one unique key.
	// Run once, gated by a persistent flag.
	if (read_config_option('mt_mac_separators_removed', true) != 'on') {
		db_execute("UPDATE mac_track_dot1x SET mac_address = REPLACE(mac_address, ':', '')");
		db_execute("UPDATE mac_track_dot1x SET mac_address = REPLACE(mac_address, '-', '')");

		db_execute("UPDATE mac_track_ips SET mac_address = REPLACE(mac_address, ':', '')");
		db_execute("UPDATE mac_track_ips SET mac_address = REPLACE(mac_address, '-', '')");

		db_execute("UPDATE mac_track_macauth SET mac_address = REPLACE(mac_address, ':', '')");
		db_execute("UPDATE mac_track_macauth SET mac_address = REPLACE(mac_address, '-', '')");

		db_execute("UPDATE mac_track_macwatch SET mac_address = REPLACE(mac_address, ':', '')");
		db_execute("UPDATE mac_track_macwatch SET mac_address = REPLACE(mac_address, '-', '')");

		db_execute("UPDATE mac_track_ports SET mac_address = REPLACE(mac_address, ':', '')");
		db_execute("UPDATE mac_track_ports SET mac_address = REPLACE(mac_address, '-', '')");

		db_execute("UPDATE mac_track_temp_ports SET mac_address = REPLACE(mac_address, ':', '')");
		db_execute("UPDATE mac_track_temp_ports SET mac_address = REPLACE(mac_address, '-', '')");

		db_execute("UPDATE mac_track_arp SET mac_address = REPLACE(mac_address, ':', '')");
		db_execute("UPDATE mac_track_arp SET mac_address = REPLACE(mac_address, '-', '')");

		db_execute("UPDATE mac_track_oui_database SET vendor_mac = REPLACE(vendor_mac, ':', '')");

		set_config_option('mt_mac_separators_removed', 'on');
	}

	// Rebuild mac_track_aggregated_ports (rename, recreate, copy per scan date,
	// drop the old table). This normalizes its MAC addresses, merges the rows
	// that normalization collapses, widens row_id to bigint, and restores the
	// unique key -- all in bite-sized per-scan-date statements so it can
	// complete inside the background upgrade and resume if interrupted. Its
	// result gates whether the caller may record the new plugin version.
	$rebuilt = mactrack_rebuild_aggregated_ports();

	mactrack_fix_aruba_oscx_device_type_functions();
	mactrack_ensure_default_site();

	return $rebuilt;
}

/**
 * Returns the ordered columns of the mac_track_aggregated_ports
 * `port_number` unique key. The poller's ON DUPLICATE KEY UPDATE
 * aggregation only merges scans while this key is unique.
 *
 * @return array<int, string>
 */
function mactrack_aggregated_ports_key_columns(): array {
	return ['port_number', 'mac_address', 'ip_address', 'device_id', 'site_id', 'vlan_id', 'authorized'];
}

/**
 * Checks whether a mac_track_aggregated_ports table has its `port_number`
 * key as a unique index over exactly the expected columns.
 *
 * @param string $table Table to inspect; defaults to the live table so
 *                      existing callers are unaffected.
 *
 * @return bool
 */
function mactrack_aggregated_ports_key_is_unique(string $table = 'mac_track_aggregated_ports'): bool {
	$rows = db_fetch_assoc_prepared("SHOW INDEX FROM $table WHERE Key_name = ?", ['port_number']);

	if (!cacti_sizeof($rows)) {
		return false;
	}

	$columns = [];

	foreach ($rows as $row) {
		if ((int) $row['Non_unique'] !== 0) {
			return false;
		}

		$columns[(int) $row['Seq_in_index']] = $row['Column_name'];
	}

	ksort($columns);

	return array_values($columns) === mactrack_aggregated_ports_key_columns();
}

/**
 * Brings a freshly created aggregated-ports destination table up to the
 * current target structure: a bigint row_id (the poller's ON DUPLICATE KEY
 * UPDATE consumes an auto-increment value per offered row, so int(10) runs
 * out on large installs), a varchar(30) port_number, and the `port_number`
 * key as UNIQUE so the per-scan-date copy below merges the rows that MAC
 * normalization collapses. Every change is guarded so a resumed run is cheap.
 *
 * @param string $table Destination table name.
 *
 * @return bool True when the destination ends up with the UNIQUE key the copy
 *              relies on, false when an ALTER left it without one.
 */
function mactrack_normalize_aggregated_ports_schema(string $table): bool {
	$row_id = db_fetch_row_prepared("SHOW COLUMNS FROM $table LIKE ?", ['row_id']);

	if (cacti_sizeof($row_id) && stripos((string) $row_id['Type'], 'bigint') === false) {
		db_execute("ALTER TABLE $table MODIFY COLUMN row_id bigint(20) unsigned NOT NULL auto_increment");
	}

	$port_number = db_fetch_row_prepared("SHOW COLUMNS FROM $table LIKE ?", ['port_number']);

	if (cacti_sizeof($port_number) && stripos((string) $port_number['Type'], 'varchar(30)') === false) {
		db_execute("ALTER TABLE $table MODIFY COLUMN port_number varchar(30) NOT NULL default ''");
	}

	if (!mactrack_aggregated_ports_key_is_unique($table)) {
		if (mactrack_db_key_exists($table, 'port_number')) {
			db_execute("ALTER TABLE $table DROP INDEX port_number");
		}

		db_execute("ALTER TABLE $table ADD UNIQUE INDEX `port_number` (`" . implode('`, `', mactrack_aggregated_ports_key_columns()) . '`)');
	}

	// The copy below relies on this UNIQUE key to merge the rows that MAC
	// normalization collapses. A failed ALTER (for example a conflicting
	// duplicate while adding the key) would otherwise let the copy proceed into
	// an invalid destination, so confirm the key is actually in place.
	return mactrack_aggregated_ports_key_is_unique($table);
}

/**
 * Rebuilds mac_track_aggregated_ports so its MAC addresses are stored
 * without separators, without the table-wide rewrite that was being killed
 * at the web-request timeout on large installs.
 *
 * The table is renamed aside, a modified copy is created with a bigint
 * row_id and a UNIQUE `port_number` key, and each distinct scan date
 * (date_last) is copied back with normalized MAC addresses using
 * INSERT ... SELECT ... ON DUPLICATE KEY UPDATE, which merges the rows that
 * normalization collapses onto one key. Scan dates are copied oldest-first,
 * so a merge's incoming row is the newer survivor: count_rec is summed, the
 * date bounds widen, and the descriptive fields take the newer values. Each
 * processed scan date is deleted from the source table inside the same
 * transaction, so the migration is resumable and the per-row count_rec
 * totals stay correct if the background upgrade restarts. When the source
 * table is confirmed empty it is dropped. The whole rebuild runs once, gated
 * by a persistent flag and an advisory lock.
 *
 * @return bool True when the rebuild has completed (or there was nothing to
 *              rebuild), false when it could not finish this pass.
 */
function mactrack_rebuild_aggregated_ports(): bool {
	global $database_default;

	$live   = 'mac_track_aggregated_ports';
	$source = 'mac_track_aggregated_ports_upgrade';

	if (read_config_option('mt_aggregated_ports_rebuilt', true) == 'on') {
		// The rebuild is recorded, but a later schema refresh on a Cacti core
		// that rewrites existing tables can drop the unique key. Repair it
		// idempotently so the web hook does not relaunch the upgrade forever.
		if (mactrack_db_table_exists($live) && !mactrack_aggregated_ports_key_is_unique($live)) {
			return mactrack_repair_aggregated_ports_key();
		}

		return true;
	}

	// Nothing to migrate: let a fresh install (table already in its target
	// shape) or an install with no aggregated table record completion.
	if (!mactrack_db_table_exists($live) && !mactrack_db_table_exists($source)) {
		set_config_option('mt_aggregated_ports_rebuilt', 'on');

		return true;
	}

	// Namespace the advisory lock per database. SHA-256 (truncated) keeps the
	// name well under MySQL's 64-character GET_LOCK limit and avoids the weak
	// hashing that SonarCloud flags; the value only has to be stable, not
	// cryptographically strong.
	$lock_name = 'mactrack.aggregated_rebuild.' . substr(hash('sha256', (string) $database_default), 0, 32);

	if ((string) db_fetch_cell_prepared('SELECT GET_LOCK(?, ?)', [$lock_name, 0]) !== '1') {
		return false;
	}

	try {
		// Another process may have finished the rebuild before the lock was free.
		if (read_config_option('mt_aggregated_ports_rebuilt', true) == 'on') {
			return true;
		}

		// (a) Rename the live table aside. A prior interrupted run leaves the
		// source table in place, so only rename when it is not there yet. The
		// rename is the one destructive step (the live table briefly disappears),
		// so defer it while a poller is mid-run rather than racing its aggregate
		// writes; the web hook relaunches us once the poller has finished.
		if (!mactrack_db_table_exists($source)) {
			if (mactrack_poller_running()) {
				cacti_log("MACTRACK: Deferring the $live rebuild while a poller is running", false, 'SYSTEM');

				return false;
			}

			db_execute("RENAME TABLE $live TO $source");
		}

		// An index on the batching column keeps the per-scan-date lookups and
		// copies from scanning the whole source table each pass.
		if (!mactrack_db_key_exists($source, 'date_last')) {
			db_execute("ALTER TABLE $source ADD INDEX `date_last` (`date_last`)");
		}

		// (b) Create the modified destination table and bring it to the target
		// structure (bigint row_id, varchar(30) port_number, UNIQUE key). Abort
		// before copying if the unique key could not be established.
		if (!mactrack_db_table_exists($live)) {
			db_execute("CREATE TABLE $live LIKE $source");
		}

		if (!mactrack_normalize_aggregated_ports_schema($live)) {
			cacti_log("MACTRACK: Could not establish the unique key on $live; deferring the rebuild", false, 'SYSTEM');

			return false;
		}

		$insert_columns = 'site_id, device_id, hostname, device_name, vlan_id, vlan_name,
			mac_address, vendor_mac, ip_address, dns_hostname, port_number, port_name,
			date_last, first_scan_date, count_rec, active_last, authorized';

		// REPLACE strips both separators from the stored MAC address.
		$select_columns = "site_id, device_id, hostname, device_name, vlan_id, vlan_name,
			REPLACE(REPLACE(mac_address, ':', ''), '-', ''), vendor_mac, ip_address, dns_hostname, port_number, port_name,
			date_last, first_scan_date, count_rec, active_last, authorized";

		// (c + d) Copy each distinct scan date in turn, merging normalized
		// duplicates, then drain that date from the source table. Dates are
		// processed oldest-first, so on a merge the incoming (VALUES) row is the
		// newer survivor whose descriptive fields should win.
		while (true) {
			$scan_date = db_fetch_cell("SELECT date_last FROM $source ORDER BY date_last LIMIT 1");

			if ($scan_date === false || $scan_date === null || $scan_date === '') {
				break;
			}

			$copied = db_begin_transaction()
				&& db_execute_prepared("INSERT INTO $live ($insert_columns)
					SELECT $select_columns
					FROM $source
					WHERE date_last = ?
					ON DUPLICATE KEY UPDATE
						count_rec       = $live.count_rec + VALUES(count_rec),
						first_scan_date = LEAST($live.first_scan_date, VALUES(first_scan_date)),
						date_last       = GREATEST($live.date_last, VALUES(date_last)),
						active_last     = GREATEST($live.active_last, VALUES(active_last)),
						hostname        = VALUES(hostname),
						device_name     = VALUES(device_name),
						vlan_name       = VALUES(vlan_name),
						port_name       = VALUES(port_name),
						dns_hostname    = VALUES(dns_hostname),
						vendor_mac      = VALUES(vendor_mac)",
					[$scan_date])
				&& db_execute_prepared("DELETE FROM $source WHERE date_last = ?", [$scan_date])
				&& db_commit_transaction();

			if (!$copied) {
				db_rollback_transaction();

				cacti_log("MACTRACK: Could not rebuild $live for scan date $scan_date", false, 'SYSTEM');

				return false;
			}

			// Keep the process registry alive during a long rebuild.
			heartbeat_process('mtupgrade', 'database');
		}

		// (e) Only drop the source once it is confirmed empty. db_fetch_cell()
		// returns false for both an empty result and a failed query, so the
		// loop above can exit early on a transient error; this COUNT makes sure
		// a non-empty or unreadable source is never discarded.
		if ((string) db_fetch_cell("SELECT COUNT(*) FROM $source") !== '0') {
			cacti_log("MACTRACK: $source is not empty after the copy; deferring the drop", false, 'SYSTEM');

			return false;
		}

		db_execute("DROP TABLE IF EXISTS $source");

		set_config_option('mt_aggregated_ports_rebuilt', 'on');

		cacti_log("MACTRACK: Rebuilt $live with normalized MAC addresses", false, 'SYSTEM');

		return true;
	} finally {
		db_fetch_cell_prepared('SELECT RELEASE_LOCK(?)', [$lock_name]);
	}
}

/**
 * Reports whether a background database-upgrade process is currently
 * registered and has not outlived its timeout. The web launcher uses it to
 * avoid spawning a second copy, and the poller uses it to defer its run while
 * the mac_track_aggregated_ports rebuild has the table renamed aside.
 *
 * @return bool True when a non-stale upgrade process is registered.
 */
function mactrack_background_upgrade_running(): bool {
	if (!db_table_exists('processes')) {
		return false;
	}

	$process = db_fetch_row_prepared('SELECT pid,
		UNIX_TIMESTAMP(started) AS started,
		UNIX_TIMESTAMP() AS `now`,
		timeout
		FROM processes
		WHERE tasktype = ?
		AND taskname = ?
		AND taskid = 0',
		['mtupgrade', 'database']);

	if (!cacti_sizeof($process)) {
		return false;
	}

	// A registration that has outlived its timeout is stale; let the caller
	// replace or ignore a crashed upgrade.
	return ((int) $process['now'] - (int) $process['started']) <= (int) $process['timeout'];
}

/**
 * Reports whether a MacTrack poller (`mactrack`/`master`) is currently
 * registered and has not outlived its timeout. The aggregated-ports rebuild
 * uses it to hold off the destructive table rename until a poller that was
 * already running finishes, closing the window the poller guard cannot.
 *
 * @return bool True when a non-stale poller process is registered.
 */
function mactrack_poller_running(): bool {
	if (!db_table_exists('processes')) {
		return false;
	}

	$processes = db_fetch_assoc_prepared('SELECT UNIX_TIMESTAMP(started) AS started,
		UNIX_TIMESTAMP() AS `now`,
		timeout
		FROM processes
		WHERE tasktype = ?
		AND taskname = ?',
		['mactrack', 'master']);

	foreach ((array) $processes as $process) {
		if (((int) $process['now'] - (int) $process['started']) <= (int) $process['timeout']) {
			return true;
		}
	}

	return false;
}

/**
 * Restores the mac_track_aggregated_ports `port_number` unique key.
 *
 * The 4.9 upgrade recreated it as a plain index, so each poller pass
 * inserted a new row instead of updating the existing one, and the
 * Cacti table refresh then fails with a duplicate-entry error. This
 * merges each duplicate group into its newest row (summed count_rec,
 * earliest first_scan_date, latest date_last), deletes the rest, and
 * rebuilds the key as UNIQUE. An advisory lock keeps concurrent page
 * loads from running it twice.
 *
 * @return bool True if the key is unique afterwards or the table does
 *              not exist yet, false if the repair did not complete.
 */
function mactrack_repair_aggregated_ports_key(): bool {
	global $database_default;

	if (!mactrack_db_table_exists('mac_track_aggregated_ports') || mactrack_aggregated_ports_key_is_unique()) {
		return true;
	}

	$lock_name = 'mactrack.aggregated.' . sha1((string) $database_default);

	if ((string) db_fetch_cell_prepared('SELECT GET_LOCK(?, ?)', [$lock_name, 0]) !== '1') {
		return false;
	}

	try {
		// Another request may have finished the repair before the lock was free.
		if (mactrack_aggregated_ports_key_is_unique()) {
			return true;
		}

		$key_columns = mactrack_aggregated_ports_key_columns();
		$group_by    = implode(', ', $key_columns);
		$join        = implode(' AND ', array_map(static fn ($c) => "a.$c = d.$c", $key_columns));

		$merge = db_begin_transaction()
			&& db_execute_prepared("UPDATE mac_track_aggregated_ports AS a
				INNER JOIN (
					SELECT MAX(row_id) AS row_id, SUM(count_rec) AS count_rec,
					MIN(first_scan_date) AS first_scan_date, MAX(date_last) AS date_last,
					MAX(active_last) AS active_last
					FROM mac_track_aggregated_ports
					GROUP BY $group_by
					HAVING COUNT(*) > 1
				) AS d
				ON a.row_id = d.row_id
				SET a.count_rec = d.count_rec, a.first_scan_date = d.first_scan_date,
				a.date_last = d.date_last, a.active_last = d.active_last")
			&& db_execute_prepared("DELETE a FROM mac_track_aggregated_ports AS a
				INNER JOIN (
					SELECT MAX(row_id) AS keep_id, $group_by
					FROM mac_track_aggregated_ports
					GROUP BY $group_by
					HAVING COUNT(*) > 1
				) AS d
				ON $join
				AND a.row_id < d.keep_id")
			&& db_commit_transaction();

		if (!$merge) {
			db_rollback_transaction();
			cacti_log('MACTRACK: Could not merge duplicate mac_track_aggregated_ports rows; the unique key was not restored', false, 'SYSTEM');

			return false;
		}

		$add = 'ADD UNIQUE INDEX `port_number` (`' . implode('`, `', $key_columns) . '`)';

		if (mactrack_db_key_exists('mac_track_aggregated_ports', 'port_number')) {
			$add = 'DROP INDEX `port_number`, ' . $add;
		}

		if (!db_execute_prepared('ALTER TABLE mac_track_aggregated_ports ' . $add)) {
			cacti_log('MACTRACK: Could not restore the mac_track_aggregated_ports unique key', false, 'SYSTEM');

			return false;
		}

		cacti_log('MACTRACK: Restored the mac_track_aggregated_ports unique key', false, 'SYSTEM');

		return true;
	} finally {
		db_fetch_cell_prepared('SELECT RELEASE_LOCK(?)', [$lock_name]);
	}
}

/**
 * Replaces the obsolete callback names on the built-in Aruba AOS-CX types.
 *
 * @return void
 */
function mactrack_fix_aruba_oscx_device_type_functions(): void {
	$types = ['Aruba 6300-default', 'Aruba 6200-default'];

	foreach ($types as $description) {
		db_execute_prepared('UPDATE mac_track_device_types
			SET scanning_function = ?
			WHERE description = ? AND scanning_function = ?',
			['get_aruba_oscx_switch_ports', $description, 'get_oscx_switch_ports']);

		db_execute_prepared('UPDATE mac_track_device_types
			SET ip_scanning_function = ?
			WHERE description = ? AND ip_scanning_function = ?',
			['get_aruba_oscx_arp_table', $description, 'get_oscx_arp_table']);
	}
}

/**
 * Checks whether at least one MacTrack site record already exists.
 *
 * @return bool True if one or more sites exist, false otherwise.
 */
function mactrack_site_configuration_exists(): bool {
	$site_count = db_fetch_cell_prepared('SELECT COUNT(*) FROM mac_track_sites');

	return is_numeric($site_count) && (int) $site_count > 0;
}

/**
 * Seeds a single "Default" MacTrack site when none exist yet, using a
 * MySQL advisory lock (GET_LOCK/RELEASE_LOCK) plus a NOT EXISTS guard
 * on the insert to reduce (though not fully eliminate, per issue #360)
 * the risk of a duplicate-site race between concurrent requests. A
 * no-op that returns true immediately if a site already exists.
 *
 * @param int|null $lock_timeout Seconds to wait for the advisory lock;
 *                              defaults to 10 for CLI callers or 2 for
 *                              web requests.
 *
 * @return bool True if a default site exists (either already present
 *              or successfully seeded), false if seeding failed (e.g.
 *              the lock could not be acquired or the insert failed).
 *
 * @global string $database_default Used to namespace the advisory lock
 *                                 name per database.
 */
function mactrack_seed_default_site(?int $lock_timeout = null): bool {
	global $database_default;

	if (mactrack_site_configuration_exists()) {
		return true;
	}

	$lock_timeout = $lock_timeout ?? (PHP_SAPI === 'cli' ? 10 : 2);
	$lock_timeout = max(0, $lock_timeout);
	// This advisory lock improves the legacy check-then-insert behavior, but a
	// reconnect can release it. Database-enforced name uniqueness needs the
	// duplicate-safe legacy migration tracked in #360.
	$lock_name = 'mactrack.default.' . sha1((string) $database_default);
	$locked    = db_fetch_cell_prepared('SELECT GET_LOCK(?, ?)', [$lock_name, $lock_timeout]);

	if ((string) $locked !== '1') {
		if (mactrack_site_configuration_exists()) {
			return true;
		}

		cacti_log('Unable to acquire the MacTrack Default-site setup lock', false, 'MACTRACK');

		return false;
	}

	try {
		$inserted = (bool) db_execute_prepared(
			'INSERT INTO mac_track_sites (site_name, site_info)
			SELECT ?, ? FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM mac_track_sites)',
			['Default', 'Default site']
		);

		if (mactrack_site_configuration_exists()) {
			return true;
		}

		cacti_log($inserted ? 'MacTrack site seeding completed without creating a site' : 'Unable to insert the default MacTrack site', false, 'MACTRACK');

		return false;
	} finally {
		$released = db_fetch_cell_prepared('SELECT RELEASE_LOCK(?)', [$lock_name]);

		if ((string) $released !== '1') {
			cacti_log('MacTrack Default-site setup lock was not owned when release was attempted', false, 'MACTRACK');
		}
	}
}

/**
 * Clears the persisted default-site seeding retry state (pending flag,
 * attempt count, and next-retry timestamp settings), used once seeding
 * has succeeded or the site is otherwise confirmed to exist.
 *
 * @return void
 */
function mactrack_reset_default_site_retry(): void {
	set_config_option('mt_default_site_seed_pending', 'off');
	set_config_option('mt_default_site_seed_attempts', '0');
	set_config_option('mt_default_site_seed_next_retry', '0');
}

/**
 * Raises a Cacti operator-facing error message about a failed default
 * MacTrack site initialization, with wording that escalates after
 * repeated failed attempts.
 *
 * @param int $attempts Number of seeding attempts made so far.
 *
 * @return void
 */
function mactrack_raise_default_site_error(int $attempts): void {
	if ($attempts >= 5) {
		$message = __('MacTrack could not initialize its Default site after repeated attempts. Review the Cacti log before continuing.', 'mactrack');
	} else {
		$message = __('MacTrack could not initialize its Default site. Review the Cacti log before continuing.', 'mactrack');
	}

	raise_message('mactrack_default_site_seed_failed', $message, MESSAGE_LEVEL_ERROR);
}

/**
 * Ensures a default MacTrack site exists, seeding one via
 * mactrack_seed_default_site() if needed, with exponential backoff
 * retry throttling (60s, 5m, 15m, 30m, 1h) tracked in Cacti settings
 * across repeated calls, and optional operator notification on
 * failure.
 *
 * @param bool|null $notify_operator    Whether to raise an operator
 *                                     error message on failure;
 *                                     defaults to true for web
 *                                     requests, false for CLI.
 * @param bool      $notify_immediately Whether to notify on the very
 *                                     first failure rather than only
 *                                     after repeated failures (default
 *                                     false).
 *
 * @return bool True if a default site exists (already present or
 *              successfully seeded), false otherwise.
 */
function mactrack_ensure_default_site(?bool $notify_operator = null, bool $notify_immediately = false): bool {
	$notify_operator = $notify_operator ?? (PHP_SAPI !== 'cli');
	$pending = read_config_option('mt_default_site_seed_pending', true) === 'on';
	$attempts = max(0, (int) read_config_option('mt_default_site_seed_attempts', true));
	$next_retry = max(0, (int) read_config_option('mt_default_site_seed_next_retry', true));

	if ($pending && $next_retry > time()) {
		// Another request or an administrator may have repaired the site while
		// this worker was throttled. Clear stale retry state without taking the
		// seed lock or attempting another insert.
		if (mactrack_site_configuration_exists()) {
			mactrack_reset_default_site_retry();

			return true;
		}

		if ($notify_operator && ($notify_immediately || $attempts >= 5)) {
			mactrack_raise_default_site_error($attempts);
		}

		return false;
	}

	try {
		$seeded = mactrack_seed_default_site();
	} catch (Throwable $exception) {
		cacti_log('MacTrack Default-site initialization failed: ' . $exception->getMessage(), false, 'MACTRACK');
		$seeded = false;
	}

	if ($seeded) {
		mactrack_reset_default_site_retry();

		return true;
	}

	$retry_delays = [60, 300, 900, 1800, 3600];
	$attempts = min(5, $attempts + 1);
	$delay = $retry_delays[$attempts - 1];
	set_config_option('mt_default_site_seed_pending', 'on');
	set_config_option('mt_default_site_seed_attempts', (string) $attempts);
	set_config_option('mt_default_site_seed_next_retry', (string) (time() + $delay));

	if ($notify_operator && ($notify_immediately || $attempts >= 5)) {
		mactrack_raise_default_site_error($attempts);
	}

	return false;
}

/**
 * Retries default-site seeding if a previous attempt is still marked
 * pending in Cacti settings; a no-op returning true if no retry is
 * pending.
 *
 * @return bool True if no retry was pending or the retry succeeded in
 *              confirming/seeding a default site, false if the retry
 *              attempt failed.
 */
function mactrack_retry_default_site(): bool {
	if (read_config_option('mt_default_site_seed_pending', true) !== 'on') {
		return true;
	}

	return mactrack_ensure_default_site();
}

/**
 * Creates (or updates, via Cacti's schema installer) all MacTrack
 * database tables, migrates legacy vendor MAC address formatting, and
 * ensures a default site exists. Invoked during plugin
 * installation/upgrade.
 *
 * @param bool $notify_seed_failure Whether to immediately raise an
 *                                 operator-facing error message if
 *                                 default-site seeding fails (default
 *                                 false).
 *
 * @return bool True when the default site exists (or was seeded),
 *              false when seeding failed.
 */
function mactrack_setup_database(bool $notify_seed_failure = false): bool {
	$data                  = [];
	$data['columns'][]     = ['name' => 'row_id', 'unsigned' => true, 'type' => 'bigint(20)', 'NULL' => false, 'auto_increment' => true];
	$data['columns'][]     = ['name' => 'site_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][]     = ['name' => 'device_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][]     = ['name' => 'hostname', 'type' => 'varchar(40)', 'NULL' => false, 'default' => ''];
	$data['columns'][]     = ['name' => 'device_name', 'type' => 'varchar(100)', 'NULL' => false, 'default' => ''];
	$data['columns'][]     = ['name' => 'vlan_id', 'type' => 'varchar(5)', 'NULL' => false, 'default' => 'N/A'];
	$data['columns'][]     = ['name' => 'vlan_name', 'type' => 'varchar(50)', 'NULL' => false, 'default' => ''];
	$data['columns'][]     = ['name' => 'mac_address', 'type' => 'varchar(20)', 'NULL' => false, 'default' => ''];
	$data['columns'][]     = ['name' => 'vendor_mac', 'type' => 'varchar(8)', 'NULL' => true];
	$data['columns'][]     = ['name' => 'ip_address', 'type' => 'varchar(20)', 'NULL' => false, 'default' => ''];
	$data['columns'][]     = ['name' => 'dns_hostname', 'type' => 'varchar(200)', 'NULL' => true];
	$data['columns'][]     = ['name' => 'port_number', 'type' => 'varchar(30)', 'NULL' => false, 'default' => ''];
	$data['columns'][]     = ['name' => 'port_name', 'type' => 'varchar(50)', 'NULL' => false, 'default' => ''];
	$data['columns'][]     = ['name' => 'date_last', 'type' => 'timestamp', 'NULL' => false, 'default' => 'CURRENT_TIMESTAMP', 'on_update' => 'CURRENT_TIMESTAMP'];
	$data['columns'][]     = ['name' => 'first_scan_date', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00'];
	$data['columns'][]     = ['name' => 'count_rec', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][]     = ['name' => 'active_last', 'unsigned' => true, 'type' => 'tinyint(1)', 'NULL' => false, 'default' => '0'];
	$data['columns'][]     = ['name' => 'authorized', 'unsigned' => true, 'type' => 'tinyint(3)', 'NULL' => false, 'default' => '0'];
	$data['primary']       = 'row_id';
	$data['unique_keys'][] = ['name' => 'port_number', 'columns' => 'port_number`,`mac_address`,`ip_address`,`device_id`,`site_id`,`vlan_id`,`authorized'];
	$data['keys'][]        = ['name' => 'site_id', 'columns' => 'site_id'];
	$data['keys'][]        = ['name' => 'device_name', 'columns' => 'device_name'];
	$data['keys'][]        = ['name' => 'mac', 'columns' => 'mac_address'];
	$data['keys'][]        = ['name' => 'hostname', 'columns' => 'hostname'];
	$data['keys'][]        = ['name' => 'vlan_name', 'columns' => 'vlan_name'];
	$data['keys'][]        = ['name' => 'vlan_id', 'columns' => 'vlan_id'];
	$data['keys'][]        = ['name' => 'device_id', 'columns' => 'device_id'];
	$data['keys'][]        = ['name' => 'ip_address', 'columns' => 'ip_address'];
	$data['keys'][]        = ['name' => 'port_name', 'columns' => 'port_name'];
	$data['keys'][]        = ['name' => 'dns_hostname', 'columns' => 'dns_hostname'];
	$data['keys'][]        = ['name' => 'vendor_mac', 'columns' => 'vendor_mac'];
	$data['keys'][]        = ['name' => 'authorized', 'columns' => 'authorized'];
	$data['keys'][]        = ['name' => 'site_id_device_id', 'columns' => 'site_id`,`device_id'];
	$data['type']          = 'InnoDB';
	$data['comment']       = 'Database for aggregated date for Tracking Device MACs';
	api_plugin_db_table_create('mactrack', 'mac_track_aggregated_ports', $data);

	$data              = [];
	$data['columns'][] = ['name' => 'mac_prefix', 'type' => 'varchar(20)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'vendor', 'type' => 'varchar(50)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'description', 'type' => 'varchar(255)', 'NULL' => false, 'default' => ''];
	$data['primary']   = 'mac_prefix';
	$data['type']      = 'InnoDB';
	$data['comment']   = '';
	api_plugin_db_table_create('mactrack', 'mac_track_approved_macs', $data);

	$data                  = [];
	$data['columns'][]     = ['name' => 'device_type_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'auto_increment' => true];
	$data['columns'][]     = ['name' => 'description', 'type' => 'varchar(100)', 'NULL' => false, 'default' => ''];
	$data['columns'][]     = ['name' => 'vendor', 'type' => 'varchar(40)', 'NULL' => false, 'default' => ''];
	$data['columns'][]     = ['name' => 'device_type', 'type' => 'varchar(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][]     = ['name' => 'sysDescr_match', 'type' => 'varchar(20)', 'NULL' => false, 'default' => ''];
	$data['columns'][]     = ['name' => 'sysObjectID_match', 'type' => 'varchar(40)', 'NULL' => false, 'default' => ''];
	$data['columns'][]     = ['name' => 'scanning_function', 'type' => 'varchar(100)', 'NULL' => false, 'default' => ''];
	$data['columns'][]     = ['name' => 'ip_scanning_function', 'type' => 'varchar(100)', 'NULL' => false, 'default' => ''];
	$data['columns'][]     = ['name' => 'dot1x_scanning_function', 'type' => 'varchar(100)', 'NULL' => false, 'default' => ''];
	$data['columns'][]     = ['name' => 'serial_number_oid', 'type' => 'varchar(100)', 'NULL' => true];
	$data['columns'][]     = ['name' => 'lowPort', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][]     = ['name' => 'highPort', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][]     = ['name' => 'disabled', 'type' => 'varchar(2)', 'NULL' => false, 'default' => ''];
	$data['primary']       = 'device_type_id';
	$data['unique_keys'][] = ['name' => 'snmp_info', 'columns' => 'sysDescr_match`,`sysObjectID_match`,`device_type'];
	$data['keys'][]        = ['name' => 'device_type', 'columns' => 'device_type'];
	$data['type']          = 'InnoDB';
	$data['comment']       = '';
	api_plugin_db_table_create('mactrack', 'mac_track_device_types', $data);

	$data                  = [];
	$data['columns'][]     = ['name' => 'site_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][]     = ['name' => 'device_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'auto_increment' => true];
	$data['columns'][]     = ['name' => 'host_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][]     = ['name' => 'device_name', 'type' => 'varchar(100)', 'NULL' => true];
	$data['columns'][]     = ['name' => 'device_type_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => true, 'default' => '0'];
	$data['columns'][]     = ['name' => 'hostname', 'type' => 'varchar(40)', 'NULL' => false, 'default' => ''];
	$data['columns'][]     = ['name' => 'notes', 'type' => 'text', 'NULL' => true];
	$data['columns'][]     = ['name' => 'disabled', 'type' => 'char(2)', 'NULL' => true];
	$data['columns'][]     = ['name' => 'ignorePorts', 'type' => 'varchar(255)', 'NULL' => true];
	$data['columns'][]     = ['name' => 'ips_total', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][]     = ['name' => 'vlans_total', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][]     = ['name' => 'ports_total', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][]     = ['name' => 'ports_active', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][]     = ['name' => 'ports_trunk', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][]     = ['name' => 'macs_active', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][]     = ['name' => 'scan_type', 'type' => 'tinyint(11)', 'NULL' => false, 'default' => '1'];
	$data['columns'][]     = ['name' => 'term_type', 'type' => 'tinyint(11)', 'NULL' => false, 'default' => '1'];
	$data['columns'][]     = ['name' => 'user_name', 'type' => 'varchar(40)', 'NULL' => true];
	$data['columns'][]     = ['name' => 'user_password', 'type' => 'varchar(40)', 'NULL' => true];
	$data['columns'][]     = ['name' => 'private_key_path', 'type' => 'varchar(128)', 'NULL' => true];
	$data['columns'][]     = ['name' => 'snmp_options', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][]     = ['name' => 'snmp_readstring', 'type' => 'varchar(100)', 'NULL' => false, 'default' => ''];
	$data['columns'][]     = ['name' => 'snmp_readstrings', 'type' => 'varchar(255)', 'NULL' => true];
	$data['columns'][]     = ['name' => 'snmp_version', 'type' => 'varchar(100)', 'NULL' => false, 'default' => ''];
	$data['columns'][]     = ['name' => 'snmp_port', 'type' => 'int(10)', 'NULL' => false, 'default' => '161'];
	$data['columns'][]     = ['name' => 'snmp_timeout', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '500'];
	$data['columns'][]     = ['name' => 'snmp_retries', 'unsigned' => true, 'type' => 'tinyint(11)', 'NULL' => false, 'default' => '3'];
	$data['columns'][]     = ['name' => 'snmp_sysName', 'type' => 'varchar(100)', 'NULL' => true];
	$data['columns'][]     = ['name' => 'snmp_sysLocation', 'type' => 'varchar(100)', 'NULL' => true];
	$data['columns'][]     = ['name' => 'snmp_sysContact', 'type' => 'varchar(100)', 'NULL' => true];
	$data['columns'][]     = ['name' => 'snmp_sysObjectID', 'type' => 'varchar(100)', 'NULL' => true];
	$data['columns'][]     = ['name' => 'snmp_sysDescr', 'type' => 'varchar(100)', 'NULL' => true];
	$data['columns'][]     = ['name' => 'snmp_sysUptime', 'type' => 'varchar(100)', 'NULL' => true];
	$data['columns'][]     = ['name' => 'snmp_status', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][]     = ['name' => 'snmp_username', 'type' => 'varchar(50)', 'NULL' => true];
	$data['columns'][]     = ['name' => 'snmp_password', 'type' => 'varchar(50)', 'NULL' => true];
	$data['columns'][]     = ['name' => 'snmp_auth_protocol', 'type' => 'char(5)', 'NULL' => true];
	$data['columns'][]     = ['name' => 'snmp_priv_passphrase', 'type' => 'varchar(200)', 'NULL' => true];
	$data['columns'][]     = ['name' => 'snmp_priv_protocol', 'type' => 'char(6)', 'NULL' => true];
	$data['columns'][]     = ['name' => 'snmp_context', 'type' => 'varchar(64)', 'NULL' => true];
	$data['columns'][]     = ['name' => 'snmp_engine_id', 'type' => 'varchar(64)', 'NULL' => true];
	$data['columns'][]     = ['name' => 'max_oids', 'unsigned' => true, 'type' => 'int(12)', 'NULL' => true, 'default' => '10'];
	$data['columns'][]     = ['name' => 'last_runmessage', 'type' => 'varchar(100)', 'NULL' => true];
	$data['columns'][]     = ['name' => 'last_rundate', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00'];
	$data['columns'][]     = ['name' => 'last_runduration', 'type' => 'decimal(10,5)', 'NULL' => false, 'default' => '0.00000'];
	$data['columns'][]     = ['name' => 'scan_trunk_port', 'type' => 'text', 'NULL' => true, 'default' => ''];
	$data['primary']       = 'device_id';
	$data['unique_keys'][] = ['name' => 'hostname_snmp_port_site_id', 'columns' => 'hostname`,`snmp_port`,`site_id'];
	$data['keys'][]        = ['name' => 'site_id', 'columns' => 'site_id'];
	$data['keys'][]        = ['name' => 'host_id', 'columns' => 'host_id'];
	$data['keys'][]        = ['name' => 'snmp_sysDescr', 'columns' => 'snmp_sysDescr'];
	$data['keys'][]        = ['name' => 'snmp_sysObjectID', 'columns' => 'snmp_sysObjectID'];
	$data['keys'][]        = ['name' => 'device_type_id', 'columns' => 'device_type_id'];
	$data['keys'][]        = ['name' => 'device_name', 'columns' => 'device_name'];
	$data['type']          = 'InnoDB';
	$data['comment']       = 'Devices to be scanned for MAC addresses';
	api_plugin_db_table_create('mactrack', 'mac_track_devices', $data);

	$data              = [];
	$data['columns'][] = ['name' => 'site_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'device_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'hostname', 'type' => 'varchar(40)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'device_name', 'type' => 'varchar(100)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'username', 'type' => 'varchar(100)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'domain', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'status', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'port_number', 'type' => 'varchar(30)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'mac_address', 'type' => 'varchar(20)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'ip_address', 'type' => 'varchar(20)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'dns_hostname', 'type' => 'varchar(200)', 'NULL' => true];
	$data['columns'][] = ['name' => 'scan_date', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00'];
	$data['primary']   = 'scan_date`,`ip_address`,`mac_address`,`site_id';
	$data['keys'][]    = ['name' => 'ip', 'columns' => 'ip_address'];
	$data['keys'][]    = ['name' => 'port_number', 'columns' => 'port_number'];
	$data['keys'][]    = ['name' => 'mac', 'columns' => 'mac_address'];
	$data['keys'][]    = ['name' => 'device_id', 'columns' => 'device_id'];
	$data['keys'][]    = ['name' => 'site_id', 'columns' => 'site_id'];
	$data['keys'][]    = ['name' => 'username', 'columns' => 'username'];
	$data['keys'][]    = ['name' => 'hostname', 'columns' => 'hostname'];
	$data['keys'][]    = ['name' => 'scan_date', 'columns' => 'scan_date'];
	$data['type']      = 'InnoDB';
	$data['comment']   = '';
	api_plugin_db_table_create('mactrack', 'mac_track_dot1x', $data);

	$data              = [];
	$data['columns'][] = ['name' => 'device_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'ifIndex', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false];
	$data['columns'][] = ['name' => 'ifName', 'type' => 'varchar(20)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'host_id', 'type' => 'int(11)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'local_graph_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false];
	$data['columns'][] = ['name' => 'snmp_query_id', 'type' => 'int(11)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'graph_template_id', 'type' => 'int(11)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'field_name', 'type' => 'varchar(20)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'field_value', 'type' => 'varchar(25)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'present', 'type' => 'tinyint(4)', 'NULL' => true, 'default' => '1'];
	$data['primary']   = 'local_graph_id`,`device_id`,`ifIndex`,`host_id';
	$data['keys'][]    = ['name' => 'host_id', 'columns' => 'host_id'];
	$data['keys'][]    = ['name' => 'device_id', 'columns' => 'device_id'];
	$data['type']      = 'InnoDB';
	$data['comment']   = '';
	api_plugin_db_table_create('mactrack', 'mac_track_interface_graphs', $data);

	$data              = [];
	$data['columns'][] = ['name' => 'site_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'device_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'sysUptime', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'ifIndex', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'ifName', 'type' => 'varchar(128)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'ifAlias', 'type' => 'varchar(255)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'ifDescr', 'type' => 'varchar(128)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'ifType', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'ifMtu', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'ifSpeed', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'ifHighSpeed', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'ifDuplex', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'ifMauAutoNegAdminStatus', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'ifMauAutoNegRemoteSignaling', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'ifPhysAddress', 'type' => 'varchar(20)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'ifAdminStatus', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'ifOperStatus', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'ifLastChange', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'linkPort', 'unsigned' => true, 'type' => 'tinyint(3)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'vlan_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false];
	$data['columns'][] = ['name' => 'vlan_name', 'type' => 'varchar(128)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'vlan_trunk', 'unsigned' => true, 'type' => 'tinyint(3)', 'NULL' => false];
	$data['columns'][] = ['name' => 'vlan_trunk_status', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false];
	$data['columns'][] = ['name' => 'ifInOctets', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'ifOutOctets', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'ifHCInOctets', 'unsigned' => true, 'type' => 'bigint(20)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'ifHCOutOctets', 'unsigned' => true, 'type' => 'bigint(20)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'ifInMulticastPkts', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'ifOutMulticastPkts', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'ifInBroadcastPkts', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'ifOutBroadcastPkts', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'ifInUcastPkts', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'ifOutUcastPkts', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'ifInDiscards', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'ifInErrors', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'ifInUnknownProtos', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'ifOutDiscards', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => true, 'default' => '0'];
	$data['columns'][] = ['name' => 'ifOutErrors', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => true, 'default' => '0'];
	$data['columns'][] = ['name' => 'inBound', 'type' => 'double', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'outBound', 'type' => 'double', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'int_ifInOctets', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'int_ifOutOctets', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'int_ifHCInOctets', 'unsigned' => true, 'type' => 'bigint(20)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'int_ifHCOutOctets', 'unsigned' => true, 'type' => 'bigint(20)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'int_ifInNUcastPkts', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'int_ifOutNUcastPkts', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'int_ifInMulticastPkts', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'int_ifOutMulticastPkts', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'int_ifInBroadcastPkts', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'int_ifOutBroadcastPkts', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'int_ifInUcastPkts', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'int_ifOutUcastPkts', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'int_ifInDiscards', 'unsigned' => true, 'type' => 'float', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'int_ifInErrors', 'unsigned' => true, 'type' => 'float', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'int_ifInUnknownProtos', 'unsigned' => true, 'type' => 'float', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'int_ifOutDiscards', 'unsigned' => true, 'type' => 'float', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'int_ifOutErrors', 'unsigned' => true, 'type' => 'float', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'last_up_time', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00'];
	$data['columns'][] = ['name' => 'last_down_time', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00'];
	$data['columns'][] = ['name' => 'stateChanges', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'int_discards_present', 'unsigned' => true, 'type' => 'tinyint(3)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'int_errors_present', 'unsigned' => true, 'type' => 'tinyint(3)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'present', 'unsigned' => true, 'type' => 'tinyint(3)', 'NULL' => false, 'default' => '0'];
	$data['primary']   = 'site_id`,`device_id`,`ifIndex';
	$data['keys'][]    = ['name' => 'ifDescr', 'columns' => 'ifDescr'];
	$data['keys'][]    = ['name' => 'ifType', 'columns' => 'ifType'];
	$data['keys'][]    = ['name' => 'ifSpeed', 'columns' => 'ifSpeed'];
	$data['keys'][]    = ['name' => 'ifMTU', 'columns' => 'ifMtu'];
	$data['keys'][]    = ['name' => 'ifAdminStatus', 'columns' => 'ifAdminStatus'];
	$data['keys'][]    = ['name' => 'ifOperStatus', 'columns' => 'ifOperStatus'];
	$data['keys'][]    = ['name' => 'ifInDiscards', 'columns' => 'ifInUnknownProtos'];
	$data['keys'][]    = ['name' => 'ifInErrors', 'columns' => 'ifInUnknownProtos'];
	$data['type']      = 'InnoDB';
	$data['comment']   = '';
	api_plugin_db_table_create('mactrack', 'mac_track_interfaces', $data);

	$data              = [];
	$data['columns'][] = ['name' => 'ip_range', 'type' => 'varchar(20)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'site_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'ips_max', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'ips_current', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'ips_max_date', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00'];
	$data['columns'][] = ['name' => 'ips_current_date', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00'];
	$data['primary']   = 'ip_range`,`site_id';
	$data['keys'][]    = ['name' => 'site_id', 'columns' => 'site_id'];
	$data['type']      = 'InnoDB';
	$data['comment']   = '';
	api_plugin_db_table_create('mactrack', 'mac_track_ip_ranges', $data);

	$data              = [];
	$data['columns'][] = ['name' => 'site_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'device_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'hostname', 'type' => 'varchar(40)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'device_name', 'type' => 'varchar(100)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'port_number', 'type' => 'varchar(30)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'mac_address', 'type' => 'varchar(20)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'ip_address', 'type' => 'varchar(20)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'dns_hostname', 'type' => 'varchar(200)', 'NULL' => true];
	$data['columns'][] = ['name' => 'scan_date', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00'];
	$data['primary']   = 'scan_date`,`ip_address`,`mac_address`,`site_id';
	$data['keys'][]    = ['name' => 'ip', 'columns' => 'ip_address'];
	$data['keys'][]    = ['name' => 'port_number', 'columns' => 'port_number'];
	$data['keys'][]    = ['name' => 'mac', 'columns' => 'mac_address'];
	$data['keys'][]    = ['name' => 'device_id', 'columns' => 'device_id'];
	$data['keys'][]    = ['name' => 'site_id', 'columns' => 'site_id'];
	$data['keys'][]    = ['name' => 'hostname', 'columns' => 'hostname'];
	$data['keys'][]    = ['name' => 'scan_date', 'columns' => 'scan_date'];
	$data['type']      = 'InnoDB';
	$data['comment']   = '';
	api_plugin_db_table_create('mactrack', 'mac_track_ips', $data);

	$data              = [];
	$data['columns'][] = ['name' => 'mac_address', 'type' => 'varchar(20)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'mac_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'auto_increment' => true];
	$data['columns'][] = ['name' => 'description', 'type' => 'varchar(100)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'added_date', 'type' => 'timestamp', 'NULL' => false, 'default' => 'CURRENT_TIMESTAMP', 'on_update' => 'CURRENT_TIMESTAMP'];
	$data['columns'][] = ['name' => 'added_by', 'type' => 'varchar(20)', 'NULL' => false, 'default' => ''];
	$data['primary']   = 'mac_address';
	$data['keys'][]    = ['name' => 'mac_id', 'columns' => 'mac_id'];
	$data['type']      = 'InnoDB';
	$data['comment']   = '';
	api_plugin_db_table_create('mactrack', 'mac_track_macauth', $data);

	$data              = [];
	$data['columns'][] = ['name' => 'mac_address', 'type' => 'varchar(20)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'mac_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'auto_increment' => true];
	$data['columns'][] = ['name' => 'name', 'type' => 'varchar(45)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'description', 'type' => 'varchar(255)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'ticket_number', 'type' => 'varchar(45)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'notify_schedule', 'unsigned' => true, 'type' => 'tinyint(3)', 'NULL' => false];
	$data['columns'][] = ['name' => 'email_addresses', 'type' => 'varchar(255)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'discovered', 'unsigned' => true, 'type' => 'tinyint(3)', 'NULL' => false];
	$data['columns'][] = ['name' => 'date_first_seen', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00'];
	$data['columns'][] = ['name' => 'date_last_seen', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00'];
	$data['columns'][] = ['name' => 'date_last_notif', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00'];
	$data['primary']   = 'mac_address';
	$data['keys'][]    = ['name' => 'mac_id', 'columns' => 'mac_id'];
	$data['type']      = 'InnoDB';
	$data['comment']   = '';
	api_plugin_db_table_create('mactrack', 'mac_track_macwatch', $data);

	$data              = [];
	$data['columns'][] = ['name' => 'vendor_mac', 'type' => 'varchar(8)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'vendor_name', 'type' => 'varchar(100)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'vendor_address', 'type' => 'text', 'NULL' => false];
	$data['columns'][] = ['name' => 'present', 'unsigned' => true, 'type' => 'tinyint(3)', 'NULL' => false, 'default' => '1'];
	$data['primary']   = 'vendor_mac';
	$data['keys'][]    = ['name' => 'vendor_name', 'columns' => 'vendor_name'];
	$data['type']      = 'InnoDB';
	$data['comment']   = '';
	api_plugin_db_table_create('mactrack', 'mac_track_oui_database', $data);

	$data              = [];
	$data['columns'][] = ['name' => 'site_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'device_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'hostname', 'type' => 'varchar(40)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'device_name', 'type' => 'varchar(100)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'vlan_id', 'type' => 'varchar(5)', 'NULL' => false, 'default' => 'N/A'];
	$data['columns'][] = ['name' => 'vlan_name', 'type' => 'varchar(50)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'mac_address', 'type' => 'varchar(20)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'vendor_mac', 'type' => 'varchar(8)', 'NULL' => true, 'default' => ''];
	$data['columns'][] = ['name' => 'ip_address', 'type' => 'varchar(20)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'dns_hostname', 'type' => 'varchar(200)', 'NULL' => true];
	$data['columns'][] = ['name' => 'port_number', 'type' => 'varchar(30)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'port_name', 'type' => 'varchar(50)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'scan_date', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00'];
	$data['columns'][] = ['name' => 'authorized', 'unsigned' => true, 'type' => 'tinyint(3)', 'NULL' => false, 'default' => '0'];
	$data['primary']   = 'port_number`,`scan_date`,`mac_address`,`device_id';
	$data['keys'][]    = ['name' => 'site_id', 'columns' => 'site_id'];
	$data['keys'][]    = ['name' => 'scan_date', 'columns' => 'scan_date'];
	$data['keys'][]    = ['name' => 'description', 'columns' => 'device_name'];
	$data['keys'][]    = ['name' => 'mac', 'columns' => 'mac_address'];
	$data['keys'][]    = ['name' => 'hostname', 'columns' => 'hostname'];
	$data['keys'][]    = ['name' => 'vlan_name', 'columns' => 'vlan_name'];
	$data['keys'][]    = ['name' => 'vlan_id', 'columns' => 'vlan_id'];
	$data['keys'][]    = ['name' => 'device_id', 'columns' => 'device_id'];
	$data['keys'][]    = ['name' => 'ip_address', 'columns' => 'ip_address'];
	$data['keys'][]    = ['name' => 'port_name', 'columns' => 'port_name'];
	$data['keys'][]    = ['name' => 'dns_hostname', 'columns' => 'dns_hostname'];
	$data['keys'][]    = ['name' => 'vendor_mac', 'columns' => 'vendor_mac'];
	$data['keys'][]    = ['name' => 'authorized', 'columns' => 'authorized'];
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Database for Tracking Device MACs';
	api_plugin_db_table_create('mactrack', 'mac_track_ports', $data);

	$data              = [];
	$data['columns'][] = ['name' => 'device_id', 'type' => 'int(11)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'process_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => true];
	$data['columns'][] = ['name' => 'status', 'type' => 'varchar(20)', 'NULL' => false, 'default' => 'Queued'];
	$data['columns'][] = ['name' => 'start_date', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00'];
	$data['primary']   = 'device_id';
	$data['type']      = 'InnoDB';
	$data['comment']   = '';
	api_plugin_db_table_create('mactrack', 'mac_track_processes', $data);

	$data              = [];
	$data['columns'][] = ['name' => 'scan_date', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00'];
	$data['primary']   = 'scan_date';
	$data['type']      = 'InnoDB';
	$data['comment']   = '';
	api_plugin_db_table_create('mactrack', 'mac_track_scan_dates', $data);

	$data              = [];
	$data['columns'][] = ['name' => 'scanning_function', 'type' => 'varchar(100)', 'NULL' => false];
	$data['columns'][] = ['name' => 'type', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'description', 'type' => 'varchar(200)', 'NULL' => false];
	$data['primary']   = 'scanning_function';
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Registered Scanning Functions';
	api_plugin_db_table_create('mactrack', 'mac_track_scanning_functions', $data);

	$data              = [];
	$data['columns'][] = ['name' => 'site_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'auto_increment' => true];
	$data['columns'][] = ['name' => 'site_name', 'type' => 'varchar(100)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'customer_contact', 'type' => 'varchar(150)', 'NULL' => true];
	$data['columns'][] = ['name' => 'netops_contact', 'type' => 'varchar(150)', 'NULL' => true];
	$data['columns'][] = ['name' => 'facilities_contact', 'type' => 'varchar(150)', 'NULL' => true];
	$data['columns'][] = ['name' => 'site_info', 'type' => 'text', 'NULL' => true];
	$data['columns'][] = ['name' => 'total_devices', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'total_device_errors', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'total_macs', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'total_ips', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'total_user_ports', 'type' => 'int(11)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'total_oper_ports', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'total_trunk_ports', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'skip_vlans', 'type' => 'text', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'scan_vlans', 'type' => 'text', 'NULL' => false, 'default' => ''];
	$data['primary']   = 'site_id';
	$data['type']      = 'InnoDB';
	$data['comment']   = '';
	api_plugin_db_table_create('mactrack', 'mac_track_sites', $data);

	$data              = [];
	$data['columns'][] = ['name' => 'id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'auto_increment' => true];
	$data['columns'][] = ['name' => 'name', 'type' => 'varchar(100)', 'NULL' => false, 'default' => ''];
	$data['primary']   = 'id';
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Group of SNMP Option Sets';
	api_plugin_db_table_create('mactrack', 'mac_track_snmp', $data);

	$data              = [];
	$data['columns'][] = ['name' => 'id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'auto_increment' => true];
	$data['columns'][] = ['name' => 'snmp_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'sequence', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'snmp_version', 'type' => 'varchar(100)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'snmp_readstring', 'type' => 'varchar(100)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'snmp_port', 'type' => 'int(10)', 'NULL' => false, 'default' => '161'];
	$data['columns'][] = ['name' => 'snmp_timeout', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '500'];
	$data['columns'][] = ['name' => 'snmp_retries', 'unsigned' => true, 'type' => 'tinyint(11)', 'NULL' => false, 'default' => '3'];
	$data['columns'][] = ['name' => 'max_oids', 'unsigned' => true, 'type' => 'int(12)', 'NULL' => true, 'default' => '10'];
	$data['columns'][] = ['name' => 'snmp_username', 'type' => 'varchar(50)', 'NULL' => true];
	$data['columns'][] = ['name' => 'snmp_password', 'type' => 'varchar(50)', 'NULL' => true];
	$data['columns'][] = ['name' => 'snmp_auth_protocol', 'type' => 'char(5)', 'NULL' => true];
	$data['columns'][] = ['name' => 'snmp_priv_passphrase', 'type' => 'varchar(200)', 'NULL' => true];
	$data['columns'][] = ['name' => 'snmp_priv_protocol', 'type' => 'char(6)', 'NULL' => true];
	$data['columns'][] = ['name' => 'snmp_context', 'type' => 'varchar(64)', 'NULL' => true];
	$data['columns'][] = ['name' => 'snmp_engine_id', 'type' => 'varchar(64)', 'NULL' => true];
	$data['primary']   = 'id`,`snmp_id';
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Set of SNMP Options';
	api_plugin_db_table_create('mactrack', 'mac_track_snmp_items', $data);

	$data              = [];
	$data['columns'][] = ['name' => 'site_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'device_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'hostname', 'type' => 'varchar(40)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'device_name', 'type' => 'varchar(100)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'vlan_id', 'type' => 'varchar(5)', 'NULL' => false, 'default' => 'N/A'];
	$data['columns'][] = ['name' => 'vlan_name', 'type' => 'varchar(50)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'mac_address', 'type' => 'varchar(20)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'vendor_mac', 'type' => 'varchar(8)', 'NULL' => true];
	$data['columns'][] = ['name' => 'ip_address', 'type' => 'varchar(20)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'dns_hostname', 'type' => 'varchar(200)', 'NULL' => true];
	$data['columns'][] = ['name' => 'port_number', 'type' => 'varchar(30)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'port_name', 'type' => 'varchar(50)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'scan_date', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00'];
	$data['columns'][] = ['name' => 'updated', 'unsigned' => true, 'type' => 'tinyint(3)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'authorized', 'unsigned' => true, 'type' => 'tinyint(3)', 'NULL' => false, 'default' => '0'];
	$data['primary']   = 'port_number`,`scan_date`,`mac_address`,`device_id';
	$data['keys'][]    = ['name' => 'site_id', 'columns' => 'site_id'];
	$data['keys'][]    = ['name' => 'device_name', 'columns' => 'device_name'];
	$data['keys'][]    = ['name' => 'ip_address', 'columns' => 'ip_address'];
	$data['keys'][]    = ['name' => 'hostname', 'columns' => 'hostname'];
	$data['keys'][]    = ['name' => 'vlan_name', 'columns' => 'vlan_name'];
	$data['keys'][]    = ['name' => 'vlan_id', 'columns' => 'vlan_id'];
	$data['keys'][]    = ['name' => 'device_id', 'columns' => 'device_id'];
	$data['keys'][]    = ['name' => 'mac', 'columns' => 'mac_address'];
	$data['keys'][]    = ['name' => 'updated', 'columns' => 'updated'];
	$data['keys'][]    = ['name' => 'vendor_mac', 'columns' => 'vendor_mac'];
	$data['keys'][]    = ['name' => 'authorized', 'columns' => 'authorized'];
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Database for Storing Temporary Results for Tracking Device MACS';
	api_plugin_db_table_create('mactrack', 'mac_track_temp_ports', $data);

	$data              = [];
	$data['columns'][] = ['name' => 'vlan_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false];
	$data['columns'][] = ['name' => 'site_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false];
	$data['columns'][] = ['name' => 'device_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false];
	$data['columns'][] = ['name' => 'vlan_name', 'type' => 'varchar(128)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'present', 'unsigned' => true, 'type' => 'tinyint(3)', 'NULL' => false, 'default' => '1'];
	$data['primary']   = 'vlan_id`,`site_id`,`device_id';
	$data['keys'][]    = ['name' => 'vlan_name', 'columns' => 'vlan_name'];
	$data['type']      = 'InnoDB';
	$data['comment']   = '';
	api_plugin_db_table_create('mactrack', 'mac_track_vlans', $data);

	$data              = [];
	$data['columns'][] = ['name' => 'site_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false];
	$data['columns'][] = ['name' => 'ip_address', 'type' => 'varchar(20)', 'NULL' => true, 'default' => ''];
	$data['columns'][] = ['name' => 'mac_address', 'type' => 'varchar(20)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'scan_date', 'type' => 'datetime', 'NULL' => false, 'default' => '0000-00-00 00:00:00'];
	$data['primary']   = 'mac_address`, `ip_address';
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Table for VRF ARP translation';
	api_plugin_db_table_create('mactrack', 'mac_track_arp', $data);

	// add few device types examples if does not exist
	$count = db_fetch_cell("SELECT count(*) FROM mac_track_device_types WHERE description LIKE '%-default'");

	if ($count == 0) {
		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('Comware 5130-default','H3C','2','','.1.3.6.1.4.1.25506.11.1.18*','get_h3c_3com_switch_ports','get_h3c_3com_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('Aruba 6300-default','Aruba','2','','.1.3.6.1.4.1.47196.4.1.1.1.109','get_aruba_oscx_switch_ports','get_aruba_oscx_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('Aruba 6200-default','Aruba','2','','.1.3.6.1.4.1.47196.4.1.1.1.300','get_aruba_oscx_switch_ports','get_aruba_oscx_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('Comware 1920-default','HPE','2','','.1.3.6.1.4.1.25506.11.1.164','get_h3c_3com_switch_ports','get_h3c_3com_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('Comware 5140-default','HPE','2','','.1.3.6.1.4.1.25506.11.1.297','get_h3c_3com_switch_ports','get_h3c_3com_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('Aruba 2530 8p-default','HPE','2','','.1.3.6.1.4.1.11.2.3.7.11.141','get_generic_switch_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('Aruba 2930 8p-default','Aruba','2','','.1.3.6.1.4.1.11.2.3.7.11.181.16','get_generic_switch_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('C2600 Router-default','Cisco','3','*C2600*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('C2960S Switch-default','Cisco','2','*C2960S*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('C3550 Switch-default','Cisco','2','*C3550*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('C3750 Switch-default','Cisco','2','*C3750*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('45xx Switch-default','Cisco','2','*cat4000*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('45xx Switch-default','Cisco','2','*4500*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('C2900 Router-default','Cisco','3','*C2900*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('ASR Router-default','Cisco','3','*ASR1000*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('6513 Switch/Router-default','Cisco','2','*s72033_rp*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('CE500 Switch-default','Cisco','1','*CE500*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('C2950 Switch-default','Cisco','2','*C2950*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('C2800 Router-default','Cisco','3','*C2800*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('BladeCenter2 10G-default','Cisco','2','*CBS31X0*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('DellBladeCenter-default','Dell','2','*Ethernet Switch*','','get_dell_dot1q_switch_ports','get_CTAlias_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('VG224-default','Cisco','3','*vg224*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('C870 Router-default','Cisco','3','*C870*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('Cisco ASA-default','Cisco','3','*Adaptive Security Appliance*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('C3x50 Switch-default','Cisco','2','*CAT3K*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('C3900 Router-default','Cisco','3','*C3900*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('Blade Center 10/100-default','Cisco','2','*CIGESM-I6K2L2Q4-M*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('CAT4500-default','Cisco','2','*cat4500*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('C3560 Switch-default','Cisco','2','*C3560*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('ProCurve Switch-default','HP','2','*ProCurve Switch*','','get_procurve_ngi_switch_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('CAT3K_CAA-UNIVERSALK9-M-default','Cisco','2','*CAT3K_CAA-UNIVERSALK9-M*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('IBM Flex System Fabric-default','IBM','2','*IBM Flex System Fabric*','','get_generic_dot1q_switch_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('C2960X Switch-default','Cisco','2','*C2960X*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('2960 Switch Lite-default','Cisco','2','*C2960-LANLITEK9-M*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('2960 Switch Base-default','Cisco','2','*C2960-LANBASEK9-M*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('VG3X0-default','Cisco','3','*VG3X0*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('93xx + 94xx Switch-default','Cisco','2','*CAT9K_IOSXE*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('43xx Router-default','Cisco','3','*ISR Software*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('Nexus Generic-default','Cisco','2','*nxos*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('Nexus 7K-default','Cisco','2','*n7000*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','.1.3.6.1.4.1.9.12.3.1.3.932',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('Nexus 5K-default','Cisco','2','*n5000*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");

		db_execute("INSERT INTO mac_track_device_types
			(description, vendor, device_type, sysDescr_match, sysObjectID_match, scanning_function, ip_scanning_function, dot1x_scanning_function, serial_number_oid, lowPort, highPort, disabled)
			VALUES ('92xx Switch-default','Cisco','1','*CAT9K_LITE_IOSXE*','','get_IOS_dot1dTpFdbEntry_ports','get_standard_arp_table','0','',0,0,'on')");
	}

	// Seed only after the complete schema and built-in device types exist.
	return mactrack_ensure_default_site($notify_seed_failure, $notify_seed_failure);
}
