<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2020 The Cacti Group                                 |
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

function mactrack_database_upgrade() {
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

	# new for 2.1.2
	# SNMP V3
	mactrack_add_column('mac_track_devices',
		'term_type',
		"ALTER TABLE `mac_track_devices` ADD COLUMN `term_type` tinyint(11) NOT NULL default '1' AFTER `scan_type`");

	mactrack_add_column('mac_track_devices',
		'user_name',
		"ALTER TABLE `mac_track_devices` ADD COLUMN `user_name` varchar(40) default NULL AFTER `term_type`");

	mactrack_add_column('mac_track_devices',
		'user_password',
		"ALTER TABLE `mac_track_devices` ADD COLUMN `user_password` varchar(40) default NULL AFTER `user_name`");

	mactrack_add_column('mac_track_devices',
		'private_key_path',
		"ALTER TABLE `mac_track_devices` ADD COLUMN `private_key_path` varchar(128) default '' AFTER `user_password`");

	mactrack_add_column('mac_track_devices',
		'snmp_options',
		"ALTER TABLE `mac_track_devices` ADD COLUMN `snmp_options` int(10) unsigned NOT NULL default '0' AFTER `private_key_path`");

	mactrack_add_column('mac_track_devices',
		'snmp_username',
		"ALTER TABLE `mac_track_devices` ADD COLUMN `snmp_username` varchar(50) default NULL AFTER `snmp_status`");

	mactrack_add_column('mac_track_devices',
		'snmp_password',
		"ALTER TABLE `mac_track_devices` ADD COLUMN `snmp_password` varchar(50) default NULL AFTER `snmp_username`");

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
		$data = array();
		$data['columns'][] = array('name' => 'id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'auto_increment' => true);
		$data['columns'][] = array('name' => 'name', 'type' => 'varchar(100)', 'NULL' => false);
		$data['primary'] = 'id';
		$data['type'] = 'InnoDB';
		$data['comment'] = 'Group of SNMP Option Sets';
		api_plugin_db_table_create ('mactrack', 'mac_track_snmp', $data);
	}

	if (!db_table_exists('mac_track_snmp_items')) {
		$data = array();
		$data['columns'][] = array('name' => 'id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'auto_increment' => true);
		$data['columns'][] = array('name' => 'snmp_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
		$data['columns'][] = array('name' => 'sequence', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
		$data['columns'][] = array('name' => 'snmp_version', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
		$data['columns'][] = array('name' => 'snmp_readstring', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
		$data['columns'][] = array('name' => 'snmp_port', 'type' => 'int(10)', 'NULL' => false, 'default' => '161');
		$data['columns'][] = array('name' => 'snmp_timeout', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '500');
		$data['columns'][] = array('name' => 'snmp_retries', 'unsigned' => true, 'type' => 'tinyint(11)', 'NULL' => false, 'default' => '3');
		$data['columns'][] = array('name' => 'max_oids', 'unsigned' => true, 'type' => 'int(12)', 'NULL' => true, 'default' => '10');
		$data['columns'][] = array('name' => 'snmp_username', 'type' => 'varchar(50)', 'NULL' => true);
		$data['columns'][] = array('name' => 'snmp_password', 'type' => 'varchar(50)', 'NULL' => true);
		$data['columns'][] = array('name' => 'snmp_auth_protocol', 'type' => 'char(5)', 'NULL' => true);
		$data['columns'][] = array('name' => 'snmp_priv_passphrase', 'type' => 'varchar(200)', 'NULL' => true);
		$data['columns'][] = array('name' => 'snmp_priv_protocol', 'type' => 'char(6)', 'NULL' => true);
		$data['columns'][] = array('name' => 'snmp_context', 'type' => 'varchar(64)', 'NULL' => true);
		$data['columns'][] = array('name' => 'snmp_engine_id', 'type' => 'varchar(64)', 'NULL' => true);
		$data['primary'] = 'id`,`snmp_id';
		$data['type'] = 'InnoDB';
		$data['comment'] = 'Set of SNMP Options';
		api_plugin_db_table_create ('mactrack', 'mac_track_snmp_items', $data);
	}

	mactrack_add_column('mac_track_snmp_items',
		'snmp_engine_id',
		"ALTER TABLE `mac_track_snmp_items` ADD COLUMN `snmp_engine_id` varchar(64) default '' AFTER `snmp_context`");

	if (!db_table_exists('mac_track_interface_graphs')) {
		$data = array();
		$data['columns'][] = array('name' => 'device_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
		$data['columns'][] = array('name' => 'ifIndex', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false);
		$data['columns'][] = array('name' => 'ifName', 'type' => 'varchar(20)', 'NULL' => false, 'default' => '');
		$data['columns'][] = array('name' => 'host_id', 'type' => 'int(11)', 'NULL' => false, 'default' => '0');
		$data['columns'][] = array('name' => 'local_graph_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false);
		$data['columns'][] = array('name' => 'snmp_query_id', 'type' => 'int(11)', 'NULL' => false, 'default' => '0');
		$data['columns'][] = array('name' => 'graph_template_id', 'type' => 'int(11)', 'NULL' => false, 'default' => '0');
		$data['columns'][] = array('name' => 'field_name', 'type' => 'varchar(20)', 'NULL' => false, 'default' => '');
		$data['columns'][] = array('name' => 'field_value', 'type' => 'varchar(25)', 'NULL' => false, 'default' => '');
		$data['columns'][] = array('name' => 'present', 'type' => 'tinyint(4)', 'NULL' => true, 'default' => '1');
		$data['primary'] = 'local_graph_id`,`device_id`,`ifIndex`,`host_id';
		$data['keys'][] = array('name' => 'host_id', 'columns' => 'host_id');
		$data['keys'][] = array('name' => 'device_id', 'columns' => 'device_id');
		$data['type'] = 'InnoDB';
		$data['comment'] = '';
		api_plugin_db_table_create ('mactrack', 'mac_track_interface_graphs', $data);
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
		"ALTER TABLE `mac_track_sites` ADD COLUMN `site_info` text AFTER `facilities_contact`");

	mactrack_add_column('mac_track_devices',
		'device_name',
		"ALTER TABLE `mac_track_devices` ADD COLUMN `device_name` varchar(100) default '' AFTER `host_id`");

	mactrack_add_column('mac_track_devices',
		'notes',
		"ALTER TABLE `mac_track_devices` ADD COLUMN `notes` text AFTER `hostname`");

	mactrack_add_column('mac_track_scanning_functions',
		'type',
		"ALTER TABLE `mac_track_scanning_functions` ADD COLUMN `type` int(10) unsigned NOT NULL default '0' AFTER `scanning_function`");

	mactrack_add_column('mac_track_temp_ports',
		'device_name',
		"ALTER TABLE `mac_track_temp_ports` ADD COLUMN `device_name` varchar(100) NOT NULL default '' AFTER `hostname`");

	mactrack_add_column('mac_track_temp_ports',
		'vendor_mac',
		"ALTER TABLE `mac_track_temp_ports` ADD COLUMN `vendor_mac` varchar(8) default NULL AFTER `mac_address`");

	mactrack_add_column('mac_track_temp_ports',
		'authorized',
		"ALTER TABLE `mac_track_temp_ports` ADD COLUMN `authorized` tinyint(3) unsigned NOT NULL default '0' AFTER `updated`");

	mactrack_add_column('mac_track_ports',
		'device_name',
		"ALTER TABLE `mac_track_ports` ADD COLUMN `device_name` varchar(100) NOT NULL default '' AFTER `hostname`");

	mactrack_add_column('mac_track_ports',
		'vendor_mac',
		"ALTER TABLE `mac_track_ports` ADD COLUMN `vendor_mac` varchar(8) default NULL AFTER `mac_address`");

	mactrack_add_column('mac_track_ports',
		'authorized',
		"ALTER TABLE `mac_track_ports` ADD COLUMN `authorized` tinyint(3) unsigned NOT NULL default '0' AFTER `scan_date`");

	mactrack_add_column('mac_track_ips',
		'device_name',
		"ALTER TABLE `mac_track_ips` ADD COLUMN `device_name` varchar(100) NOT NULL default '' AFTER `hostname`");

	db_execute("ALTER TABLE mac_track_ips MODIFY COLUMN port_number int(10) unsigned NOT NULL default '0'");

	db_execute("ALTER TABLE mac_track_ports MODIFY COLUMN port_number int(10) unsigned NOT NULL default '0'");

	db_execute("ALTER TABLE mac_track_temp_ports MODIFY COLUMN port_number int(10) unsigned NOT NULL default '0'");

	db_execute("ALTER TABLE mac_track_aggregated_ports MODIFY COLUMN port_number int(10) unsigned NOT NULL default '0'");

	db_execute("ALTER TABLE mac_track_dot1x MODIFY COLUMN port_number int(10) unsigned NOT NULL default '0'");

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

	$tables = db_fetch_assoc("SELECT DISTINCT TABLE_NAME
		FROM information_schema.COLUMNS
		WHERE TABLE_SCHEMA = SCHEMA()
		AND TABLE_NAME LIKE 'mac_track%'");

	if (sizeof($tables)) {
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
				foreach($columns as $column) {
					$alter .= ($i == 0 ? '': ', ') . ' MODIFY COLUMN `' . $column['COLUMN_NAME'] . '` ' . $column['COLUMN_TYPE'] . ($column['IS_NULLABLE'] == 'NO' ? ' NOT NULL' : '') . ' DEFAULT ""';
					$i++;
				}

				db_execute($alter);
			}
		}
	}
}

function mactrack_setup_database() {
	$data = array();
	$data['columns'][] = array('name' => 'row_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'site_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'device_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'hostname', 'type' => 'varchar(40)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'device_name', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'vlan_id', 'type' => 'varchar(5)', 'NULL' => false, 'default' => 'N/A');
	$data['columns'][] = array('name' => 'vlan_name', 'type' => 'varchar(50)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'mac_address', 'type' => 'varchar(20)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'vendor_mac', 'type' => 'varchar(8)', 'NULL' => true);
	$data['columns'][] = array('name' => 'ip_address', 'type' => 'varchar(20)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'dns_hostname', 'type' => 'varchar(200)', 'NULL' => true);
	$data['columns'][] = array('name' => 'port_number', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'port_name', 'type' => 'varchar(50)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'date_last', 'type' => 'timestamp', 'NULL' => false, 'default' => 'CURRENT_TIMESTAMP', 'on_update' => 'CURRENT_TIMESTAMP');
	$data['columns'][] = array('name' => 'first_scan_date', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00');
	$data['columns'][] = array('name' => 'count_rec', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'active_last', 'unsigned' => true, 'type' => 'tinyint(1)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'authorized', 'unsigned' => true, 'type' => 'tinyint(3)', 'NULL' => false, 'default' => '0');
	$data['primary'] = 'row_id';
	$data['keys'][] = array('name' => 'port_number', 'columns' => 'port_number`,`mac_address`,`ip_address`,`device_id`,`site_id`,`vlan_id`,`authorized');
	$data['keys'][] = array('name' => 'site_id', 'columns' => 'site_id');
	$data['keys'][] = array('name' => 'device_name', 'columns' => 'device_name');
	$data['keys'][] = array('name' => 'mac', 'columns' => 'mac_address');
	$data['keys'][] = array('name' => 'hostname', 'columns' => 'hostname');
	$data['keys'][] = array('name' => 'vlan_name', 'columns' => 'vlan_name');
	$data['keys'][] = array('name' => 'vlan_id', 'columns' => 'vlan_id');
	$data['keys'][] = array('name' => 'device_id', 'columns' => 'device_id');
	$data['keys'][] = array('name' => 'ip_address', 'columns' => 'ip_address');
	$data['keys'][] = array('name' => 'port_name', 'columns' => 'port_name');
	$data['keys'][] = array('name' => 'dns_hostname', 'columns' => 'dns_hostname');
	$data['keys'][] = array('name' => 'vendor_mac', 'columns' => 'vendor_mac');
	$data['keys'][] = array('name' => 'authorized', 'columns' => 'authorized');
	$data['keys'][] = array('name' => 'site_id_device_id', 'columns' => 'site_id`,`device_id');
	$data['type'] = 'InnoDB';
	$data['comment'] = 'Database for aggregated date for Tracking Device MACs';
	api_plugin_db_table_create ('mactrack', 'mac_track_aggregated_ports', $data);

	$data = array();
	$data['columns'][] = array('name' => 'mac_prefix', 'type' => 'varchar(20)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'vendor', 'type' => 'varchar(50)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'description', 'type' => 'varchar(255)', 'NULL' => false, 'default' => '');
	$data['primary'] = 'mac_prefix';
	$data['type'] = 'InnoDB';
	$data['comment'] = '';
	api_plugin_db_table_create ('mactrack', 'mac_track_approved_macs', $data);

	$data = array();
	$data['columns'][] = array('name' => 'device_type_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'description', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'vendor', 'type' => 'varchar(40)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'device_type', 'type' => 'varchar(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'sysDescr_match', 'type' => 'varchar(20)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'sysObjectID_match', 'type' => 'varchar(40)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'scanning_function', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'ip_scanning_function', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'dot1x_scanning_function', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'serial_number_oid', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'lowPort', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'highPort', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['primary'] = 'device_type_id';
	$data['unique_keys'] = array('name' => 'snmp_info', 'columns' => 'sysDescr_match`,`sysObjectID_match`,`device_type');
	$data['keys'][] = array('name' => 'device_type', 'columns' => 'device_type');
	$data['type'] = 'InnoDB';
	$data['comment'] = '';
	api_plugin_db_table_create ('mactrack', 'mac_track_device_types', $data);

	$data = array();
	$data['columns'][] = array('name' => 'site_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'device_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'host_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'device_name', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'device_type_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => true, 'default' => '0');
	$data['columns'][] = array('name' => 'hostname', 'type' => 'varchar(40)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'notes', 'type' => 'text', 'NULL' => true);
	$data['columns'][] = array('name' => 'disabled', 'type' => 'char(2)', 'NULL' => true);
	$data['columns'][] = array('name' => 'ignorePorts', 'type' => 'varchar(255)', 'NULL' => true);
	$data['columns'][] = array('name' => 'ips_total', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'vlans_total', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'ports_total', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'ports_active', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'ports_trunk', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'macs_active', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'scan_type', 'type' => 'tinyint(11)', 'NULL' => false, 'default' => '1');
	$data['columns'][] = array('name' => 'term_type', 'type' => 'tinyint(11)', 'NULL' => false, 'default' => '1');
	$data['columns'][] = array('name' => 'user_name', 'type' => 'varchar(40)', 'NULL' => true);
	$data['columns'][] = array('name' => 'user_password', 'type' => 'varchar(40)', 'NULL' => true);
	$data['columns'][] = array('name' => 'private_key_path', 'type' => 'varchar(128)', 'NULL' => true);
	$data['columns'][] = array('name' => 'snmp_options', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'snmp_readstring', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'snmp_readstrings', 'type' => 'varchar(255)', 'NULL' => true);
	$data['columns'][] = array('name' => 'snmp_version', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'snmp_port', 'type' => 'int(10)', 'NULL' => false, 'default' => '161');
	$data['columns'][] = array('name' => 'snmp_timeout', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '500');
	$data['columns'][] = array('name' => 'snmp_retries', 'unsigned' => true, 'type' => 'tinyint(11)', 'NULL' => false, 'default' => '3');
	$data['columns'][] = array('name' => 'snmp_sysName', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'snmp_sysLocation', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'snmp_sysContact', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'snmp_sysObjectID', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'snmp_sysDescr', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'snmp_sysUptime', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'snmp_status', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'snmp_username', 'type' => 'varchar(50)', 'NULL' => true);
	$data['columns'][] = array('name' => 'snmp_password', 'type' => 'varchar(50)', 'NULL' => true);
	$data['columns'][] = array('name' => 'snmp_auth_protocol', 'type' => 'char(5)', 'NULL' => true);
	$data['columns'][] = array('name' => 'snmp_priv_passphrase', 'type' => 'varchar(200)', 'NULL' => true);
	$data['columns'][] = array('name' => 'snmp_priv_protocol', 'type' => 'char(6)', 'NULL' => true);
	$data['columns'][] = array('name' => 'snmp_context', 'type' => 'varchar(64)', 'NULL' => true);
	$data['columns'][] = array('name' => 'snmp_engine_id', 'type' => 'varchar(64)', 'NULL' => true);
	$data['columns'][] = array('name' => 'max_oids', 'unsigned' => true, 'type' => 'int(12)', 'NULL' => true, 'default' => '10');
	$data['columns'][] = array('name' => 'last_runmessage', 'type' => 'varchar(100)', 'NULL' => true);
	$data['columns'][] = array('name' => 'last_rundate', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00');
	$data['columns'][] = array('name' => 'last_runduration', 'type' => 'decimal(10,5)', 'NULL' => false, 'default' => '0.00000');
	$data['primary'] = 'device_id';
	$data['unique_keys'] = array('name' => 'hostname_snmp_port_site_id', 'columns' => 'hostname`,`snmp_port`,`site_id');
	$data['keys'][] = array('name' => 'site_id', 'columns' => 'site_id');
	$data['keys'][] = array('name' => 'host_id', 'columns' => 'host_id');
	$data['keys'][] = array('name' => 'snmp_sysDescr', 'columns' => 'snmp_sysDescr');
	$data['keys'][] = array('name' => 'snmp_sysObjectID', 'columns' => 'snmp_sysObjectID');
	$data['keys'][] = array('name' => 'device_type_id', 'columns' => 'device_type_id');
	$data['keys'][] = array('name' => 'device_name', 'columns' => 'device_name');
	$data['type'] = 'InnoDB';
	$data['comment'] = 'Devices to be scanned for MAC addresses';
	api_plugin_db_table_create ('mactrack', 'mac_track_devices', $data);

	$data = array();
	$data['columns'][] = array('name' => 'site_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'device_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'hostname', 'type' => 'varchar(40)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'device_name', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'username', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'domain', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'status', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'port_number', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'mac_address', 'type' => 'varchar(20)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'ip_address', 'type' => 'varchar(20)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'dns_hostname', 'type' => 'varchar(200)', 'NULL' => true);
	$data['columns'][] = array('name' => 'scan_date', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00');
	$data['primary'] = 'scan_date`,`ip_address`,`mac_address`,`site_id';
	$data['keys'][] = array('name' => 'ip', 'columns' => 'ip_address');
	$data['keys'][] = array('name' => 'port_number', 'columns' => 'port_number');
	$data['keys'][] = array('name' => 'mac', 'columns' => 'mac_address');
	$data['keys'][] = array('name' => 'device_id', 'columns' => 'device_id');
	$data['keys'][] = array('name' => 'site_id', 'columns' => 'site_id');
	$data['keys'][] = array('name' => 'username', 'columns' => 'username');
	$data['keys'][] = array('name' => 'hostname', 'columns' => 'hostname');
	$data['keys'][] = array('name' => 'scan_date', 'columns' => 'scan_date');
	$data['type'] = 'InnoDB';
	$data['comment'] = '';
	api_plugin_db_table_create ('mactrack', 'mac_track_dot1x', $data);

	$data = array();
	$data['columns'][] = array('name' => 'device_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'ifIndex', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false);
	$data['columns'][] = array('name' => 'ifName', 'type' => 'varchar(20)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'host_id', 'type' => 'int(11)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'local_graph_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false);
	$data['columns'][] = array('name' => 'snmp_query_id', 'type' => 'int(11)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'graph_template_id', 'type' => 'int(11)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'field_name', 'type' => 'varchar(20)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'field_value', 'type' => 'varchar(25)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'present', 'type' => 'tinyint(4)', 'NULL' => true, 'default' => '1');
	$data['primary'] = 'local_graph_id`,`device_id`,`ifIndex`,`host_id';
	$data['keys'][] = array('name' => 'host_id', 'columns' => 'host_id');
	$data['keys'][] = array('name' => 'device_id', 'columns' => 'device_id');
	$data['type'] = 'InnoDB';
	$data['comment'] = '';
	api_plugin_db_table_create ('mactrack', 'mac_track_interface_graphs', $data);

	$data = array();
	$data['columns'][] = array('name' => 'site_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'device_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'sysUptime', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'ifIndex', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'ifName', 'type' => 'varchar(128)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'ifAlias', 'type' => 'varchar(255)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'ifDescr', 'type' => 'varchar(128)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'ifType', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'ifMtu', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'ifSpeed', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'ifHighSpeed', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'ifDuplex', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'ifMauAutoNegAdminStatus', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'ifMauAutoNegRemoteSignaling', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'ifPhysAddress', 'type' => 'varchar(20)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'ifAdminStatus', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'ifOperStatus', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'ifLastChange', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'linkPort', 'unsigned' => true, 'type' => 'tinyint(3)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'vlan_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false);
	$data['columns'][] = array('name' => 'vlan_name', 'type' => 'varchar(128)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'vlan_trunk', 'unsigned' => true, 'type' => 'tinyint(3)', 'NULL' => false);
	$data['columns'][] = array('name' => 'vlan_trunk_status', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false);
	$data['columns'][] = array('name' => 'ifInOctets', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'ifOutOctets', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'ifHCInOctets', 'unsigned' => true, 'type' => 'bigint(20)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'ifHCOutOctets', 'unsigned' => true, 'type' => 'bigint(20)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'ifInMulticastPkts', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'ifOutMulticastPkts', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'ifInBroadcastPkts', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'ifOutBroadcastPkts', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'ifInUcastPkts', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'ifOutUcastPkts', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'ifInDiscards', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'ifInErrors', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'ifInUnknownProtos', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'ifOutDiscards', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => true, 'default' => '0');
	$data['columns'][] = array('name' => 'ifOutErrors', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => true, 'default' => '0');
	$data['columns'][] = array('name' => 'inBound', 'type' => 'double', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'outBound', 'type' => 'double', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'int_ifInOctets', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'int_ifOutOctets', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'int_ifHCInOctets', 'unsigned' => true, 'type' => 'bigint(20)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'int_ifHCOutOctets', 'unsigned' => true, 'type' => 'bigint(20)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'int_ifInNUcastPkts', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'int_ifOutNUcastPkts', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'int_ifInMulticastPkts', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'int_ifOutMulticastPkts', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'int_ifInBroadcastPkts', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'int_ifOutBroadcastPkts', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'int_ifInUcastPkts', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'int_ifOutUcastPkts', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'int_ifInDiscards', 'unsigned' => true, 'type' => "float", 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'int_ifInErrors', 'unsigned' => true, 'type' => "float", 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'int_ifInUnknownProtos', 'unsigned' => true, 'type' => "float", 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'int_ifOutDiscards', 'unsigned' => true, 'type' => "float", 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'int_ifOutErrors', 'unsigned' => true, 'type' => "float", 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'last_up_time', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00');
	$data['columns'][] = array('name' => 'last_down_time', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00');
	$data['columns'][] = array('name' => 'stateChanges', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'int_discards_present', 'unsigned' => true, 'type' => 'tinyint(3)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'int_errors_present', 'unsigned' => true, 'type' => 'tinyint(3)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'present', 'unsigned' => true, 'type' => 'tinyint(3)', 'NULL' => false, 'default' => '0');
	$data['primary'] = 'site_id`,`device_id`,`ifIndex';
	$data['keys'][] = array('name' => 'ifDescr', 'columns' => 'ifDescr');
	$data['keys'][] = array('name' => 'ifType', 'columns' => 'ifType');
	$data['keys'][] = array('name' => 'ifSpeed', 'columns' => 'ifSpeed');
	$data['keys'][] = array('name' => 'ifMTU', 'columns' => 'ifMtu');
	$data['keys'][] = array('name' => 'ifAdminStatus', 'columns' => 'ifAdminStatus');
	$data['keys'][] = array('name' => 'ifOperStatus', 'columns' => 'ifOperStatus');
	$data['keys'][] = array('name' => 'ifInDiscards', 'columns' => 'ifInUnknownProtos');
	$data['keys'][] = array('name' => 'ifInErrors', 'columns' => 'ifInUnknownProtos');
	$data['type'] = 'InnoDB';
	$data['comment'] = '';
	api_plugin_db_table_create ('mactrack', 'mac_track_interfaces', $data);

	$data = array();
	$data['columns'][] = array('name' => 'ip_range', 'type' => 'varchar(20)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'site_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'ips_max', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'ips_current', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'ips_max_date', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00');
	$data['columns'][] = array('name' => 'ips_current_date', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00');
	$data['primary'] = 'ip_range`,`site_id';
	$data['keys'][] = array('name' => 'site_id', 'columns' => 'site_id');
	$data['type'] = 'InnoDB';
	$data['comment'] = '';
	api_plugin_db_table_create ('mactrack', 'mac_track_ip_ranges', $data);

	$data = array();
	$data['columns'][] = array('name' => 'site_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'device_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'hostname', 'type' => 'varchar(40)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'device_name', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'port_number', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'mac_address', 'type' => 'varchar(20)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'ip_address', 'type' => 'varchar(20)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'dns_hostname', 'type' => 'varchar(200)', 'NULL' => true);
	$data['columns'][] = array('name' => 'scan_date', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00');
	$data['primary'] = 'scan_date`,`ip_address`,`mac_address`,`site_id';
	$data['keys'][] = array('name' => 'ip', 'columns' => 'ip_address');
	$data['keys'][] = array('name' => 'port_number', 'columns' => 'port_number');
	$data['keys'][] = array('name' => 'mac', 'columns' => 'mac_address');
	$data['keys'][] = array('name' => 'device_id', 'columns' => 'device_id');
	$data['keys'][] = array('name' => 'site_id', 'columns' => 'site_id');
	$data['keys'][] = array('name' => 'hostname', 'columns' => 'hostname');
	$data['keys'][] = array('name' => 'scan_date', 'columns' => 'scan_date');
	$data['type'] = 'InnoDB';
	$data['comment'] = '';
	api_plugin_db_table_create ('mactrack', 'mac_track_ips', $data);

	$data = array();
	$data['columns'][] = array('name' => 'mac_address', 'type' => 'varchar(20)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'mac_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'description', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'added_date', 'type' => 'timestamp', 'NULL' => false, 'default' => 'CURRENT_TIMESTAMP', 'on_update' => 'CURRENT_TIMESTAMP');
	$data['columns'][] = array('name' => 'added_by', 'type' => 'varchar(20)', 'NULL' => false, 'default' => '');
	$data['primary'] = 'mac_address';
	$data['keys'][] = array('name' => 'mac_id', 'columns' => 'mac_id');
	$data['type'] = 'InnoDB';
	$data['comment'] = '';
	api_plugin_db_table_create ('mactrack', 'mac_track_macauth', $data);

	$data = array();
	$data['columns'][] = array('name' => 'mac_address', 'type' => 'varchar(20)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'mac_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'name', 'type' => 'varchar(45)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'description', 'type' => 'varchar(255)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'ticket_number', 'type' => 'varchar(45)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'notify_schedule', 'unsigned' => true, 'type' => 'tinyint(3)', 'NULL' => false);
	$data['columns'][] = array('name' => 'email_addresses', 'type' => 'varchar(255)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'discovered', 'unsigned' => true, 'type' => 'tinyint(3)', 'NULL' => false);
	$data['columns'][] = array('name' => 'date_first_seen', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00');
	$data['columns'][] = array('name' => 'date_last_seen', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00');
	$data['columns'][] = array('name' => 'date_last_notif', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00');
	$data['primary'] = 'mac_address';
	$data['keys'][] = array('name' => 'mac_id', 'columns' => 'mac_id');
	$data['type'] = 'InnoDB';
	$data['comment'] = '';
	api_plugin_db_table_create ('mactrack', 'mac_track_macwatch', $data);

	$data = array();
	$data['columns'][] = array('name' => 'vendor_mac', 'type' => 'varchar(8)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'vendor_name', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'vendor_address', 'type' => 'text', 'NULL' => false);
	$data['columns'][] = array('name' => 'present', 'unsigned' => true, 'type' => 'tinyint(3)', 'NULL' => false, 'default' => '1');
	$data['primary'] = 'vendor_mac';
	$data['keys'][] = array('name' => 'vendor_name', 'columns' => 'vendor_name');
	$data['type'] = 'InnoDB';
	$data['comment'] = '';
	api_plugin_db_table_create ('mactrack', 'mac_track_oui_database', $data);

	$data = array();
	$data['columns'][] = array('name' => 'site_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'device_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'hostname', 'type' => 'varchar(40)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'device_name', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'vlan_id', 'type' => 'varchar(5)', 'NULL' => false, 'default' => 'N/A');
	$data['columns'][] = array('name' => 'vlan_name', 'type' => 'varchar(50)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'mac_address', 'type' => 'varchar(20)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'vendor_mac', 'type' => 'varchar(8)', 'NULL' => true, 'default' => '');
	$data['columns'][] = array('name' => 'ip_address', 'type' => 'varchar(20)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'dns_hostname', 'type' => 'varchar(200)', 'NULL' => true);
	$data['columns'][] = array('name' => 'port_number', 'type' => 'int(10)', 'unsignend' => true, 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'port_name', 'type' => 'varchar(50)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'scan_date', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00');
	$data['columns'][] = array('name' => 'authorized', 'unsigned' => true, 'type' => 'tinyint(3)', 'NULL' => false, 'default' => '0');
	$data['primary'] = 'port_number`,`scan_date`,`mac_address`,`device_id';
	$data['keys'][] = array('name' => 'site_id', 'columns' => 'site_id');
	$data['keys'][] = array('name' => 'scan_date', 'columns' => 'scan_date');
	$data['keys'][] = array('name' => 'description', 'columns' => 'device_name');
	$data['keys'][] = array('name' => 'mac', 'columns' => 'mac_address');
	$data['keys'][] = array('name' => 'hostname', 'columns' => 'hostname');
	$data['keys'][] = array('name' => 'vlan_name', 'columns' => 'vlan_name');
	$data['keys'][] = array('name' => 'vlan_id', 'columns' => 'vlan_id');
	$data['keys'][] = array('name' => 'device_id', 'columns' => 'device_id');
	$data['keys'][] = array('name' => 'ip_address', 'columns' => 'ip_address');
	$data['keys'][] = array('name' => 'port_name', 'columns' => 'port_name');
	$data['keys'][] = array('name' => 'dns_hostname', 'columns' => 'dns_hostname');
	$data['keys'][] = array('name' => 'vendor_mac', 'columns' => 'vendor_mac');
	$data['keys'][] = array('name' => 'authorized', 'columns' => 'authorized');
	$data['type'] = 'InnoDB';
	$data['comment'] = 'Database for Tracking Device MACs';
	api_plugin_db_table_create ('mactrack', 'mac_track_ports', $data);

	$data = array();
	$data['columns'][] = array('name' => 'device_id', 'type' => 'int(11)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'process_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => true);
	$data['columns'][] = array('name' => 'status', 'type' => 'varchar(20)', 'NULL' => false, 'default' => 'Queued');
	$data['columns'][] = array('name' => 'start_date', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00');
	$data['primary'] = 'device_id';
	$data['type'] = 'InnoDB';
	$data['comment'] = '';
	api_plugin_db_table_create ('mactrack', 'mac_track_processes', $data);

	$data = array();
	$data['columns'][] = array('name' => 'scan_date', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00');
	$data['primary'] = 'scan_date';
	$data['type'] = 'InnoDB';
	$data['comment'] = '';
	api_plugin_db_table_create ('mactrack', 'mac_track_scan_dates', $data);

	$data = array();
	$data['columns'][] = array('name' => 'scanning_function', 'type' => 'varchar(100)', 'NULL' => false);
	$data['columns'][] = array('name' => 'type', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'description', 'type' => 'varchar(200)', 'NULL' => false);
	$data['primary'] = 'scanning_function';
	$data['type'] = 'InnoDB';
	$data['comment'] = 'Registered Scanning Functions';
	api_plugin_db_table_create ('mactrack', 'mac_track_scanning_functions', $data);

	$data = array();
	$data['columns'][] = array('name' => 'site_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'site_name', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'customer_contact', 'type' => 'varchar(150)', 'NULL' => true);
	$data['columns'][] = array('name' => 'netops_contact', 'type' => 'varchar(150)', 'NULL' => true);
	$data['columns'][] = array('name' => 'facilities_contact', 'type' => 'varchar(150)', 'NULL' => true);
	$data['columns'][] = array('name' => 'site_info', 'type' => 'text', 'NULL' => true);
	$data['columns'][] = array('name' => 'total_devices', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'total_device_errors', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'total_macs', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'total_ips', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'total_user_ports', 'type' => 'int(11)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'total_oper_ports', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'total_trunk_ports', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['primary'] = 'site_id';
	$data['type'] = 'InnoDB';
	$data['comment'] = '';
	api_plugin_db_table_create ('mactrack', 'mac_track_sites', $data);

	$data = array();
	$data['columns'][] = array('name' => 'id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'name', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
	$data['primary'] = 'id';
	$data['type'] = 'InnoDB';
	$data['comment'] = 'Group of SNMP Option Sets';
	api_plugin_db_table_create ('mactrack', 'mac_track_snmp', $data);

	$data = array();
	$data['columns'][] = array('name' => 'id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'snmp_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'sequence', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'snmp_version', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'snmp_readstring', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'snmp_port', 'type' => 'int(10)', 'NULL' => false, 'default' => '161');
	$data['columns'][] = array('name' => 'snmp_timeout', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '500');
	$data['columns'][] = array('name' => 'snmp_retries', 'unsigned' => true, 'type' => 'tinyint(11)', 'NULL' => false, 'default' => '3');
	$data['columns'][] = array('name' => 'max_oids', 'unsigned' => true, 'type' => 'int(12)', 'NULL' => true, 'default' => '10');
	$data['columns'][] = array('name' => 'snmp_username', 'type' => 'varchar(50)', 'NULL' => true);
	$data['columns'][] = array('name' => 'snmp_password', 'type' => 'varchar(50)', 'NULL' => true);
	$data['columns'][] = array('name' => 'snmp_auth_protocol', 'type' => 'char(5)', 'NULL' => true);
	$data['columns'][] = array('name' => 'snmp_priv_passphrase', 'type' => 'varchar(200)', 'NULL' => true);
	$data['columns'][] = array('name' => 'snmp_priv_protocol', 'type' => 'char(6)', 'NULL' => true);
	$data['columns'][] = array('name' => 'snmp_context', 'type' => 'varchar(64)', 'NULL' => true);
	$data['columns'][] = array('name' => 'snmp_engine_id', 'type' => 'varchar(64)', 'NULL' => true);
	$data['primary'] = 'id`,`snmp_id';
	$data['type'] = 'InnoDB';
	$data['comment'] = 'Set of SNMP Options';
	api_plugin_db_table_create ('mactrack', 'mac_track_snmp_items', $data);

	$data = array();
	$data['columns'][] = array('name' => 'site_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'device_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'hostname', 'type' => 'varchar(40)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'device_name', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'vlan_id', 'type' => 'varchar(5)', 'NULL' => false, 'default' => 'N/A');
	$data['columns'][] = array('name' => 'vlan_name', 'type' => 'varchar(50)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'mac_address', 'type' => 'varchar(20)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'vendor_mac', 'type' => 'varchar(8)', 'NULL' => true);
	$data['columns'][] = array('name' => 'ip_address', 'type' => 'varchar(20)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'dns_hostname', 'type' => 'varchar(200)', 'NULL' => true);
	$data['columns'][] = array('name' => 'port_number', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'port_name', 'type' => 'varchar(50)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'scan_date', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00');
	$data['columns'][] = array('name' => 'updated', 'unsigned' => true, 'type' => 'tinyint(3)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'authorized', 'unsigned' => true, 'type' => 'tinyint(3)', 'NULL' => false, 'default' => '0');
	$data['primary'] = 'port_number`,`scan_date`,`mac_address`,`device_id';
	$data['keys'][] = array('name' => 'site_id', 'columns' => 'site_id');
	$data['keys'][] = array('name' => 'device_name', 'columns' => 'device_name');
	$data['keys'][] = array('name' => 'ip_address', 'columns' => 'ip_address');
	$data['keys'][] = array('name' => 'hostname', 'columns' => 'hostname');
	$data['keys'][] = array('name' => 'vlan_name', 'columns' => 'vlan_name');
	$data['keys'][] = array('name' => 'vlan_id', 'columns' => 'vlan_id');
	$data['keys'][] = array('name' => 'device_id', 'columns' => 'device_id');
	$data['keys'][] = array('name' => 'mac', 'columns' => 'mac_address');
	$data['keys'][] = array('name' => 'updated', 'columns' => 'updated');
	$data['keys'][] = array('name' => 'vendor_mac', 'columns' => 'vendor_mac');
	$data['keys'][] = array('name' => 'authorized', 'columns' => 'authorized');
	$data['type'] = 'InnoDB';
	$data['comment'] = 'Database for Storing Temporary Results for Tracking Device MACS';
	api_plugin_db_table_create ('mactrack', 'mac_track_temp_ports', $data);

	$data = array();
	$data['columns'][] = array('name' => 'vlan_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false);
	$data['columns'][] = array('name' => 'site_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false);
	$data['columns'][] = array('name' => 'device_id', 'unsigned' => true, 'type' => 'int(10)', 'NULL' => false);
	$data['columns'][] = array('name' => 'vlan_name', 'type' => 'varchar(128)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'present', 'unsigned' => true, 'type' => 'tinyint(3)', 'NULL' => false, 'default' => '1');
	$data['primary'] = 'vlan_id`,`site_id`,`device_id';
	$data['keys'][] = array('name' => 'vlan_name', 'columns' => 'vlan_name');
	$data['type'] = 'InnoDB';
	$data['comment'] = '';
	api_plugin_db_table_create ('mactrack', 'mac_track_vlans', $data);
}

