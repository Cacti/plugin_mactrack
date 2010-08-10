<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2010 The Cacti Group                                 |
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

function plugin_mactrack_install() {
	api_plugin_register_hook('mactrack', 'top_header_tabs',       'mactrack_show_tab',             "setup.php");
	api_plugin_register_hook('mactrack', 'top_graph_header_tabs', 'mactrack_show_tab',             "setup.php");
	api_plugin_register_hook('mactrack', 'config_arrays',         'mactrack_config_arrays',        "setup.php");
	api_plugin_register_hook('mactrack', 'draw_navigation_text',  'mactrack_draw_navigation_text', "setup.php");
	api_plugin_register_hook('mactrack', 'config_form',           'mactrack_config_form',          "setup.php");
	api_plugin_register_hook('mactrack', 'config_settings',       'mactrack_config_settings',      "setup.php");
	api_plugin_register_hook('mactrack', 'poller_bottom',         'mactrack_poller_bottom',        "setup.php");
	api_plugin_register_hook('mactrack', 'page_head',             'mactrack_page_head',            "setup.php");

	# device hook: intercept on device save
	api_plugin_register_hook('mactrack', 'api_device_save', 'sync_cacti_to_mactrack', 'mactrack_actions.php');
	# device hook: Add a new dropdown Action for Device Management
	api_plugin_register_hook('mactrack', 'device_action_array', 'mactrack_device_action_array', 'mactrack_actions.php');
	# device hook: Device Management Action dropdown selected: prepare the list of devices for a confirmation request
	api_plugin_register_hook('mactrack', 'device_action_prepare', 'mactrack_device_action_prepare', 'mactrack_actions.php');
	# device hook: Device Management Action dropdown selected: execute list of device
	api_plugin_register_hook('mactrack', 'device_action_execute', 'mactrack_device_action_execute', 'mactrack_actions.php');

	mactrack_setup_table_new ();
}

function plugin_mactrack_uninstall () {
	if (mactrack_db_table_exists("mac_track_approved_macs")) {
		db_execute("DROP TABLE `mac_track_approved_macs`");
	}

	if (mactrack_db_table_exists("mac_track_device_types")) {
		db_execute("DROP TABLE `mac_track_device_types`");
	}

	if (mactrack_db_table_exists("mac_track_devices")) {
		db_execute("DROP TABLE `mac_track_devices`");
	}

	if (mactrack_db_table_exists("mac_track_interfaces")) {
		db_execute("DROP TABLE `mac_track_interfaces`");
	}

	if (mactrack_db_table_exists("mac_track_ip_ranges")) {
		db_execute("DROP TABLE `mac_track_ip_ranges`");
	}

	if (mactrack_db_table_exists("mac_track_ips")) {
		db_execute("DROP TABLE `mac_track_ips`");
	}

	if (mactrack_db_table_exists("mac_track_macauth")) {
		db_execute("DROP TABLE `mac_track_macauth`");
	}

	if (mactrack_db_table_exists("mac_track_macwatch")) {
		db_execute("DROP TABLE `mac_track_macwatch`");
	}

	if (mactrack_db_table_exists("mac_track_oui_database")) {
		db_execute("DROP TABLE `mac_track_oui_database`");
	}

	if (mactrack_db_table_exists("mac_track_ports")) {
		db_execute("DROP TABLE `mac_track_ports`");
	}

	if (mactrack_db_table_exists("mac_track_processes")) {
		db_execute("DROP TABLE `mac_track_processes`");
	}

	if (mactrack_db_table_exists("mac_track_scan_dates")) {
		db_execute("DROP TABLE `mac_track_scan_dates`");
	}

	if (mactrack_db_table_exists("mac_track_scanning_functions")) {
		db_execute("DROP TABLE `mac_track_scanning_functions`");
	}

	if (mactrack_db_table_exists("mac_track_sites")) {
		db_execute("DROP TABLE `mac_track_sites`");
	}

	if (mactrack_db_table_exists("mac_track_temp_ports")) {
		db_execute("DROP TABLE `mac_track_temp_ports`");
	}

	if (mactrack_db_table_exists("mac_track_vlans")) {
		db_execute("DROP TABLE `mac_track_vlans`");
	}

	if (mactrack_db_table_exists("mac_track_aggregated_ports")) {
		db_execute("DROP TABLE mac_track_aggregated_ports");
	}

	if (mactrack_db_table_exists("mac_track_snmp")) {
		db_execute("DROP TABLE mac_track_snmp");
	}

	if (mactrack_db_table_exists("mac_track_snmp_items")) {
		db_execute("DROP TABLE mac_track_snmp_items");
	}
}

function plugin_mactrack_check_config () {
	/* Here we will check to ensure everything is configured */
	mactrack_check_upgrade();
	return true;
}

function plugin_mactrack_upgrade () {
	/* Here we will upgrade to the newest version */
	mactrack_check_upgrade();
	return false;
}

function plugin_mactrack_version () {
	return mactrack_version();
}

function mactrack_check_upgrade () {
	global $config;

	if (defined('CACTI_BASE_PATH')) {
		$config["base_path"] = CACTI_BASE_PATH;
	}
	include_once($config["base_path"] . "/plugins/mactrack/lib/mactrack_functions.php");

	$files = array('index.php', 'plugins.php', 'mactrack_devices.php');
	if (isset($_SERVER['PHP_SELF']) && !in_array(basename($_SERVER['PHP_SELF']), $files)) {
		return;
	}

	$current = plugin_mactrack_version();
	$current = $current['version'];

	$old     = db_fetch_row("SELECT * FROM plugin_config WHERE directory='mactrack'");
	if (!sizeof($old) || $current != $old["version"]) {
		/* if the plugin is installed and/or active */
		if (!sizeof($old) || $old["status"] == 1 || $old["status"] == 4) {
			/* re-register the hooks */
			plugin_mactrack_install();
			if (api_plugin_is_enabled('mactrack')) {
				# may sound ridiculous, but enables new hooks
				api_plugin_enable_hooks('mactrack');
			}

			/* perform a database upgrade */
			mactrack_database_upgrade();
		}

		if (read_config_option("mt_convert_readstrings", true) != "on") {
			convert_readstrings();
		}

		/* rebuild the scanning functions */
		mactrack_rebuild_scanning_funcs();

		/* update the plugin information */
		$info = plugin_mactrack_version();
		$id   = db_fetch_cell("SELECT id FROM plugin_config WHERE directory='mactrack'");

		db_execute("UPDATE plugin_config
			SET name='" . $info["longname"] . "',
			author='"   . $info["author"]   . "',
			webpage='"  . $info["homepage"] . "',
			version='"  . $info["version"]  . "'
			WHERE id='$id'");
	}
}

function mactrack_db_table_exists($table) {
	return sizeof(db_fetch_assoc("SHOW TABLES LIKE '$table'"));
}

function mactrack_db_column_exists($table, $column) {
	$found = false;

	if (mactrack_db_table_exists($table)) {
		$columns  = db_fetch_assoc("SHOW COLUMNS FROM $table");
		if (sizeof($columns)) {
		foreach($columns as $row) {
			if ($row["Field"] == $column) {
				$found = true;
				break;
			}
		}
		}
	}

	return $found;
}

function mactrack_db_key_exists($table, $index) {
	$found = false;

	if (mactrack_db_table_exists($table)) {
		$keys  = db_fetch_assoc("SHOW INDEXES FROM $table");
		if (sizeof($keys)) {
		foreach($keys as $key) {
			if ($key["Key_name"] == $index) {
				$found = true;
				break;
			}
		}
		}
	}

	return $found;
}

function mactrack_execute_sql($message, $syntax) {
	$result = db_execute($syntax);
}

function mactrack_create_table($table, $syntax) {
	if (!mactrack_db_table_exists($table)) {
		db_execute($syntax);
	}
}

function mactrack_add_column($table, $column, $syntax) {
	if (!mactrack_db_column_exists($table, $column)) {
		db_execute($syntax);
	}
}

function mactrack_add_index($table, $index, $syntax) {
	if (!mactrack_db_key_exists($table, $index)) {
		db_execute($syntax);
	}
}

function mactrack_modify_column($table, $column, $syntax) {
	if (mactrack_db_column_exists($table, $column)) {
		db_execute($syntax);
	}
}

function mactrack_delete_column($table, $column, $syntax) {
	if (mactrack_db_column_exists($table, $column)) {
		db_execute($syntax);
	}
}

function mactrack_database_upgrade () {
	mactrack_add_column("mac_track_interfaces", "ifHighSpeed",           "ALTER TABLE `mac_track_interfaces` ADD COLUMN `ifHighSpeed` int(10) unsigned NOT NULL default '0' AFTER `ifSpeed`");
	mactrack_add_column("mac_track_interfaces", "ifDuplex",              "ALTER TABLE `mac_track_interfaces` ADD COLUMN `ifDuplex` int(10) unsigned NOT NULL default '0' AFTER `ifHighSpeed`");
	mactrack_add_column("mac_track_interfaces", "int_ifInDiscards",      "ALTER TABLE `mac_track_interfaces` ADD COLUMN `int_ifInDiscards` int(10) unsigned NOT NULL default '0' AFTER `ifOutErrors`");
	mactrack_add_column("mac_track_interfaces", "int_ifInErrors",        "ALTER TABLE `mac_track_interfaces` ADD COLUMN `int_ifInErrors` int(10) unsigned NOT NULL default '0' AFTER `int_ifInDiscards`");
	mactrack_add_column("mac_track_interfaces", "int_ifInUnknownProtos", "ALTER TABLE `mac_track_interfaces` ADD COLUMN `int_ifInUnknownProtos` int(10) unsigned NOT NULL default '0' AFTER `int_ifInErrors`");
	mactrack_add_column("mac_track_interfaces", "int_ifOutDiscards",     "ALTER TABLE `mac_track_interfaces` ADD COLUMN `int_ifOutDiscards` int(10) unsigned NOT NULL default '0' AFTER `int_ifInUnknownProtos`");
	mactrack_add_column("mac_track_interfaces", "int_ifOutErrors",       "ALTER TABLE `mac_track_interfaces` ADD COLUMN `int_ifOutErrors` int(10) unsigned NOT NULL default '0' AFTER `int_ifOutDiscards`");
	mactrack_add_column("mac_track_devices",    "host_id",               "ALTER TABLE `mac_track_devices` ADD COLUMN `host_id` int(10) unsigned NOT NULL default '0' AFTER `device_id`");
	mactrack_add_column("mac_track_macwatch",   "date_last_notif",       "ALTER TABLE `mac_track_macwatch` ADD COLUMN `date_last_notif` TIMESTAMP DEFAULT '0000-00-00 00:00:00' AFTER `date_last_seen`");
	mactrack_execute_sql("Add length to Device Types Match Fields", "ALTER TABLE `mac_track_device_types` MODIFY COLUMN `sysDescr_match` VARCHAR(100) NOT NULL default '', MODIFY COLUMN `sysObjectID_match` VARCHAR(100) NOT NULL default ''");
	mactrack_execute_sql("Correct a Scanning Function Bug", "DELETE FROM mac_track_scanning_functions WHERE scanning_function='Not Applicable - Hub/Switch'");
	mactrack_add_column("mac_track_devices", "host_id", "ALTER TABLE `mac_track_devices` ADD COLUMN `host_id` INTEGER UNSIGNED NOT NULL default '0' AFTER `device_id`");
	mactrack_add_index("mac_track_devices", "host_id", "ALTER TABLE `mac_track_devices` ADD INDEX `host_id`(`host_id`)");
	mactrack_add_index("mac_track_ports", "scan_date", "ALTER TABLE `mac_track_ports` ADD INDEX `scan_date` USING BTREE(`scan_date`)");

	if (!mactrack_db_column_exists("mac_track_interfaces", "sysUptime")) {
		db_execute("ALTER TABLE mac_track_interfaces
			ADD COLUMN `sysUptime` int(10) unsigned NOT NULL default '0' AFTER `device_id`,
			ADD COLUMN `ifInOctets` int(10) unsigned NOT NULL default '0' AFTER `vlan_trunk_status`,
			ADD COLUMN `ifOutOctets` int(10) unsigned NOT NULL default '0' AFTER `ifInOctets`,
			ADD COLUMN `ifHCInOctets` bigint(20) unsigned NOT NULL default '0' AFTER `ifOutOctets`,
			ADD COLUMN `ifHCOutOctets` bigint(20) unsigned NOT NULL default '0' AFTER `ifHCInOctets`,
			ADD COLUMN `ifInNUcastPkts` int(10) unsigned NOT NULL default '0' AFTER `ifHCOutOctets`,
			ADD COLUMN `ifOutNUcastPkts` int(10) unsigned NOT NULL default '0' AFTER `ifInNUcastPkts`,
			ADD COLUMN `ifInUcastPkts` int(10) unsigned NOT NULL default '0' AFTER `ifOutNUcastPkts`,
			ADD COLUMN `ifOutUcastPkts` int(10) unsigned NOT NULL default '0' AFTER `ifInUcastPkts`,
			ADD COLUMN `inBound` double NOT NULL default '0' AFTER `ifOutErrors`,
			ADD COLUMN `outBound` double NOT NULL default '0' AFTER `inBound`,
			ADD COLUMN `int_ifInOctets` int(10) unsigned NOT NULL default '0' AFTER `outBound`,
			ADD COLUMN `int_ifOutOctets` int(10) unsigned NOT NULL default '0' AFTER `int_ifInOctets`,
			ADD COLUMN `int_ifHCInOctets` bigint(20) unsigned NOT NULL default '0' AFTER `int_ifOutOctets`,
			ADD COLUMN `int_ifHCOutOctets` bigint(20) unsigned NOT NULL default '0' AFTER `int_ifHCInOctets`,
			ADD COLUMN `int_ifInNUcastPkts` int(10) unsigned NOT NULL default '0' AFTER `int_ifHCOutOctets`,
			ADD COLUMN `int_ifOutNUcastPkts` int(10) unsigned NOT NULL default '0' AFTER `int_ifInNUcastPkts`,
			ADD COLUMN `int_ifInUcastPkts` int(10) unsigned NOT NULL default '0' AFTER `int_ifOutNUcastPkts`,
			ADD COLUMN `int_ifOutUcastPkts` int(10) unsigned NOT NULL default '0' AFTER `int_ifInUcastPkts`");
	}

	if (!mactrack_db_key_exists("mac_track_ports", "site_id_device_id")) {
		db_execute("ALTER TABLE `mac_track_ports` ADD INDEX `site_id_device_id`(`site_id`, `device_id`);");
	}

	# new for 2.1.2
	# SNMP V3
	mactrack_add_column("mac_track_devices",    "snmp_options",         "ALTER TABLE `mac_track_devices` ADD COLUMN `snmp_options` int(10) unsigned NOT NULL default '0' AFTER `user_password`");
	mactrack_add_column("mac_track_devices",    "snmp_username",        "ALTER TABLE `mac_track_devices` ADD COLUMN `snmp_username` varchar(50) default NULL AFTER `snmp_status`");
	mactrack_add_column("mac_track_devices",    "snmp_password",        "ALTER TABLE `mac_track_devices` ADD COLUMN `snmp_password` varchar(50) default NULL AFTER `snmp_username`");
	mactrack_add_column("mac_track_devices",    "snmp_auth_protocol",   "ALTER TABLE `mac_track_devices` ADD COLUMN `snmp_auth_protocol` char(5) default '' AFTER `snmp_password`");
	mactrack_add_column("mac_track_devices",    "snmp_priv_passphrase", "ALTER TABLE `mac_track_devices` ADD COLUMN `snmp_priv_passphrase` varchar(200) default '' AFTER `snmp_auth_protocol`");
	mactrack_add_column("mac_track_devices",    "snmp_priv_protocol",   "ALTER TABLE `mac_track_devices` ADD COLUMN `snmp_priv_protocol` char(6) default '' AFTER `snmp_priv_passphrase`");
	mactrack_add_column("mac_track_devices",    "snmp_context",         "ALTER TABLE `mac_track_devices` ADD COLUMN `snmp_context` varchar(64) default '' AFTER `snmp_priv_protocol`");
	mactrack_add_column("mac_track_devices",    "max_oids",             "ALTER TABLE `mac_track_devices` ADD COLUMN `max_oids` int(12) unsigned default '10' AFTER `snmp_context`");

	if (!mactrack_db_table_exists("mac_track_snmp")) {
		mactrack_create_table("mac_track_snmp", "CREATE TABLE `mac_track_snmp` (
			`id` int(10) unsigned NOT NULL auto_increment,
			`name` varchar(100) NOT NULL default '',
			PRIMARY KEY  (`id`))
			ENGINE=MyISAM COMMENT='Group of SNMP Option Sets';");
	}

	if (!mactrack_db_table_exists("mac_track_snmp_items")) {
		mactrack_create_table("mac_track_snmp_items", "CREATE TABLE `mac_track_snmp_items` (
			`id` int(10) unsigned NOT NULL auto_increment,
			`snmp_id` int(10) unsigned NOT NULL default '0',
			`sequence` int(10) unsigned NOT NULL default '0',
			`snmp_version` varchar(100) NOT NULL default '',
			`snmp_readstring` varchar(100) NOT NULL,
			`snmp_port` int(10) NOT NULL default '161',
			`snmp_timeout` int(10) unsigned NOT NULL default '500',
			`snmp_retries` tinyint(11) unsigned NOT NULL default '3',
			`max_oids` int(12) unsigned default '10',
			`snmp_username` varchar(50) default NULL,
			`snmp_password` varchar(50) default NULL,
			`snmp_auth_protocol` char(5) default '',
			`snmp_priv_passphrase` varchar(200) default '',
			`snmp_priv_protocol` char(6) default '',
			`snmp_context` varchar(64) default '',
			PRIMARY KEY  (`id`,`snmp_id`))
			ENGINE=MyISAM COMMENT='Set of SNMP Options';");
	}

	if (!sizeof(db_fetch_row("SHOW TABLES LIKE 'mac_track_interface_graphs'"))) {
		db_execute("CREATE TABLE `mac_track_interface_graphs` (
			`device_id` int(10) unsigned NOT NULL default '0',
			`ifIndex` int(10) unsigned NOT NULL,
			`ifName` varchar(20) NOT NULL default '',
			`host_id` int(11) NOT NULL default '0',
			`local_graph_id` int(10) unsigned NOT NULL,
			`snmp_query_id` int(11) NOT NULL default '0',
			`graph_template_id` int(11) NOT NULL default '0',
			`field_name` varchar(20) NOT NULL default '',
			`field_value` varchar(25) NOT NULL default '',
			`present` tinyint(4) default '1',
			PRIMARY KEY  (`local_graph_id`,`device_id`,`ifIndex`, `host_id`),
			KEY `host_id` (`host_id`),
			KEY `device_id` (`device_id`)
			) ENGINE=MyISAM;"
		);
	}

	mactrack_add_column("mac_track_devices",
		"term_type",
		"ALTER TABLE `mac_track_devices` ADD COLUMN `term_type` tinyint(11) NOT NULL default '1' AFTER `scan_type`");
	mactrack_add_column("mac_track_devices",
		"private_key_path",
		"ALTER TABLE `mac_track_devices` ADD COLUMN `private_key_path` varchar(128) default '' AFTER `user_password`");
	mactrack_add_column("mac_track_interfaces",
		"ifMauAutoNegAdminStatus",
		"ALTER TABLE `mac_track_interfaces` ADD COLUMN `ifMauAutoNegAdminStatus` integer UNSIGNED NOT NULL default '0' AFTER `ifDuplex`");
	mactrack_add_column("mac_track_interfaces",
		"ifMauAutoNegRemoteSignaling",
		"ALTER TABLE `mac_track_interfaces` ADD COLUMN `ifMauAutoNegRemoteSignaling` integer UNSIGNED NOT NULL default '0' AFTER `ifMauAutoNegAdminStatus`");
}

function mactrack_check_dependencies() {
	global $plugins, $config;

	return true;
}

function mactrack_setup_table_new () {
	if (!mactrack_db_table_exists("mac_track_approved_macs")) {
		db_execute("CREATE TABLE `mac_track_approved_macs` (
			`mac_prefix` varchar(20) NOT NULL,
			`vendor` varchar(50) NOT NULL,
			`description` varchar(255) NOT NULL,
			PRIMARY KEY  (`mac_prefix`)) ENGINE=MyISAM;");
	}

	if (!mactrack_db_table_exists("mac_track_device_types")) {
		db_execute("CREATE TABLE `mac_track_device_types` (
			`device_type_id` int(10) unsigned NOT NULL auto_increment,
			`description` varchar(100) NOT NULL default '',
			`vendor` varchar(40) NOT NULL default '',
			`device_type` varchar(10) NOT NULL default '0',
			`sysDescr_match` varchar(20) NOT NULL default '',
			`sysObjectID_match` varchar(40) NOT NULL default '',
			`scanning_function` varchar(100) NOT NULL default '',
			`ip_scanning_function` varchar(100) NOT NULL,
			`serial_number_oid` varchar(100) default '',
			`lowPort` int(10) unsigned NOT NULL default '0',
			`highPort` int(10) unsigned NOT NULL default '0',
			PRIMARY KEY  (`sysDescr_match`,`sysObjectID_match`,`device_type`),
			KEY `device_type` (`device_type`),
			KEY `device_type_id` (`device_type_id`))
			ENGINE=MyISAM;");
	}

	if (!mactrack_db_table_exists("mac_track_devices")) {
		db_execute("CREATE TABLE `mac_track_devices` (
			`site_id` int(10) unsigned NOT NULL default '0',
			`device_id` int(10) unsigned NOT NULL auto_increment,
			`host_id` INTEGER UNSIGNED NOT NULL default '0',
			`device_name` varchar(100) default '',
			`device_type_id` int(10) unsigned default '0',
			`hostname` varchar(40) NOT NULL default '',
			`notes` text,
			`disabled` char(2) default '',
			`ignorePorts` varchar(255) default NULL,
			`ips_total` int(10) unsigned NOT NULL default '0',
			`vlans_total` int(10) unsigned NOT NULL default '0',
			`ports_total` int(10) unsigned NOT NULL default '0',
			`ports_active` int(10) unsigned NOT NULL default '0',
			`ports_trunk` int(10) unsigned NOT NULL default '0',
			`macs_active` int(10) unsigned NOT NULL default '0',
			`scan_type` tinyint(11) NOT NULL default '1',
			`term_type` tinyint(11) NOT NULL default '1',
			`user_name` varchar(40) default NULL,
			`user_password` varchar(40) default NULL,
			`private_key_path` varchar(128) default '',
			`snmp_options` int(10) unsigned NOT NULL default '0',
			`snmp_readstring` varchar(100) NOT NULL,
			`snmp_readstrings` varchar(255) default NULL,
			`snmp_version` varchar(100) NOT NULL default '',
			`snmp_port` int(10) NOT NULL default '161',
			`snmp_timeout` int(10) unsigned NOT NULL default '500',
			`snmp_retries` tinyint(11) unsigned NOT NULL default '3',
			`snmp_sysName` varchar(100) default '',
			`snmp_sysLocation` varchar(100) default '',
			`snmp_sysContact` varchar(100) default '',
			`snmp_sysObjectID` varchar(100) default NULL,
			`snmp_sysDescr` varchar(100) default NULL,
			`snmp_sysUptime` varchar(100) default NULL,
			`snmp_status` int(10) unsigned NOT NULL default '0',
			`snmp_username` varchar(50) default NULL,
			`snmp_password` varchar(50) default NULL,
			`snmp_auth_protocol` char(5) default '',
			`snmp_priv_passphrase` varchar(200) default '',
			`snmp_priv_protocol` char(6) default '',
			`snmp_context` varchar(64) default '',
			`max_oids` int(12) unsigned default '10',
			`last_runmessage` varchar(100) default '',
			`last_rundate` datetime NOT NULL default '0000-00-00 00:00:00',
			`last_runduration` decimal(10,5) NOT NULL default '0.00000',
			PRIMARY KEY  (`hostname`,`snmp_port`,`site_id`),
			KEY `site_id` (`site_id`),
			KEY `host_id`(`host_id`),
			KEY `device_id` (`device_id`),
			KEY `snmp_sysDescr` (`snmp_sysDescr`),
			KEY `snmp_sysObjectID` (`snmp_sysObjectID`),
			KEY `device_type_id` (`device_type_id`),
			KEY `device_name` (`device_name`))
			ENGINE=MyISAM COMMENT='Devices to be scanned for MAC addresses';");
	}

	if (!mactrack_db_table_exists("mac_track_interfaces")) {
		db_execute("CREATE TABLE `mac_track_interfaces` (
			`site_id` int(10) unsigned NOT NULL default '0',
			`device_id` int(10) unsigned NOT NULL default '0',
			`sysUptime` int(10) unsigned NOT NULL default '0',
			`ifIndex` int(10) unsigned NOT NULL default '0',
			`ifName` varchar(128) NOT NULL,
			`ifAlias` varchar(255) NOT NULL,
			`ifDescr` varchar(128) NOT NULL,
			`ifType` int(10) unsigned NOT NULL default '0',
			`ifMtu` int(10) unsigned NOT NULL default '0',
			`ifSpeed` int(10) unsigned NOT NULL default '0',
			`ifHighSpeed` int(10) unsigned NOT NULL default '0',
			`ifDuplex` int(10) unsigned NOT NULL default '0',
			`ifMauAutoNegAdminStatus` integer UNSIGNED NOT NULL default '0',
			`ifMauAutoNegRemoteSignaling` integer UNSIGNED NOT NULL default '0',
			`ifPhysAddress` varchar(20) NOT NULL,
			`ifAdminStatus` int(10) unsigned NOT NULL default '0',
			`ifOperStatus` int(10) unsigned NOT NULL default '0',
			`ifLastChange` int(10) unsigned NOT NULL default '0',
			`linkPort` tinyint(3) unsigned NOT NULL default '0',
			`vlan_id` int(10) unsigned NOT NULL,
			`vlan_name` varchar(128) NOT NULL,
			`vlan_trunk` tinyint(3) unsigned NOT NULL,
			`vlan_trunk_status` int(10) unsigned NOT NULL,
			`ifInOctets` int(10) unsigned NOT NULL default '0',
			`ifOutOctets` int(10) unsigned NOT NULL default '0',
			`ifHCInOctets` bigint(20) unsigned NOT NULL default '0',
			`ifHCOutOctets` bigint(20) unsigned NOT NULL default '0',
			`ifInNUcastPkts` int(10) unsigned NOT NULL default '0',
			`ifOutNUcastPkts` int(10) unsigned NOT NULL default '0',
			`ifInUcastPkts` int(10) unsigned NOT NULL default '0',
			`ifOutUcastPkts` int(10) unsigned NOT NULL default '0',
			`ifInDiscards` int(10) unsigned NOT NULL default '0',
			`ifInErrors` int(10) unsigned NOT NULL default '0',
			`ifInUnknownProtos` int(10) unsigned NOT NULL default '0',
			`ifOutDiscards` int(10) unsigned default '0',
			`ifOutErrors` int(10) unsigned default '0',
			`inBound` double NOT NULL default '0',
			`outBound` double NOT NULL default '0',
			`int_ifInOctets` int(10) unsigned NOT NULL default '0',
			`int_ifOutOctets` int(10) unsigned NOT NULL default '0',
			`int_ifHCInOctets` bigint(20) unsigned NOT NULL default '0',
			`int_ifHCOutOctets` bigint(20) unsigned NOT NULL default '0',
			`int_ifInNUcastPkts` int(10) unsigned NOT NULL default '0',
			`int_ifOutNUcastPkts` int(10) unsigned NOT NULL default '0',
			`int_ifInUcastPkts` int(10) unsigned NOT NULL default '0',
			`int_ifOutUcastPkts` int(10) unsigned NOT NULL default '0',
			`int_ifInDiscards` float unsigned NOT NULL default '0',
			`int_ifInErrors` float unsigned NOT NULL default '0',
			`int_ifInUnknownProtos` float unsigned NOT NULL default '0',
			`int_ifOutDiscards` float unsigned NOT NULL default '0',
			`int_ifOutErrors` float unsigned NOT NULL default '0',
			`last_up_time` timestamp NOT NULL default '0000-00-00 00:00:00',
			`last_down_time` timestamp NOT NULL default '0000-00-00 00:00:00',
			`stateChanges` int(10) unsigned NOT NULL default '0',
			`int_discards_present` tinyint(3) unsigned NOT NULL default '0',
			`int_errors_present` tinyint(3) unsigned NOT NULL default '0',
			`present` tinyint(3) unsigned NOT NULL default '0',
			PRIMARY KEY  (`site_id`,`device_id`,`ifIndex`),
			KEY `ifDescr` (`ifDescr`),
			KEY `ifType` (`ifType`),
			KEY `ifSpeed` (`ifSpeed`),
			KEY `ifMTU` (`ifMtu`),
			KEY `ifAdminStatus` (`ifAdminStatus`),
			KEY `ifOperStatus` (`ifOperStatus`),
			KEY `ifInDiscards` USING BTREE (`ifInUnknownProtos`),
			KEY `ifInErrors` USING BTREE (`ifInUnknownProtos`))
			ENGINE=MyISAM;");
	}

	if (!mactrack_db_table_exists("mac_track_ip_ranges")) {
		db_execute("CREATE TABLE `mac_track_ip_ranges` (
			`ip_range` varchar(20) NOT NULL default '',
			`site_id` int(10) unsigned NOT NULL default '0',
			`ips_max` int(10) unsigned NOT NULL default '0',
			`ips_current` int(10) unsigned NOT NULL default '0',
			`ips_max_date` datetime NOT NULL default '0000-00-00 00:00:00',
			`ips_current_date` datetime NOT NULL default '0000-00-00 00:00:00',
			PRIMARY KEY  (`ip_range`,`site_id`),
			KEY `site_id` (`site_id`))
			ENGINE=MyISAM;");
	}

	if (!mactrack_db_table_exists("mac_track_ips")) {
		db_execute("CREATE TABLE `mac_track_ips` (
			`site_id` int(10) unsigned NOT NULL default '0',
			`device_id` int(10) unsigned NOT NULL default '0',
			`hostname` varchar(40) NOT NULL default '',
			`device_name` varchar(100) NOT NULL default '',
			`port_number` varchar(10) NOT NULL default '',
			`mac_address` varchar(20) NOT NULL default '',
			`ip_address` varchar(20) NOT NULL default '',
			`dns_hostname` varchar(200) default '',
			`scan_date` datetime NOT NULL default '0000-00-00 00:00:00',
			PRIMARY KEY  (`scan_date`,`ip_address`,`mac_address`,`site_id`),
			KEY `ip` (`ip_address`),
			KEY `port_number` (`port_number`),
			KEY `mac` (`mac_address`),
			KEY `device_id` (`device_id`),
			KEY `site_id` (`site_id`),
			KEY `hostname` (`hostname`),
			KEY `scan_date` (`scan_date`))
			ENGINE=MyISAM;");
	}

	if (!mactrack_db_table_exists("mac_track_macauth")) {
		db_execute("CREATE TABLE `mac_track_macauth` (
			`mac_address` varchar(20) NOT NULL,
			`mac_id` int(10) unsigned NOT NULL auto_increment,
			`description` varchar(100) NOT NULL,
			`added_date` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
			`added_by` varchar(20) NOT NULL,
			PRIMARY KEY  (`mac_address`),
			KEY `mac_id` (`mac_id`))
			ENGINE=MyISAM;");
	}

	if (!mactrack_db_table_exists("mac_track_macwatch")) {
		db_execute("CREATE TABLE `mac_track_macwatch` (
			`mac_address` varchar(20) NOT NULL,
			`mac_id` int(10) unsigned NOT NULL auto_increment,
			`name` varchar(45) NOT NULL,
			`description` varchar(255) NOT NULL,
			`ticket_number` varchar(45) NOT NULL,
			`notify_schedule` tinyint(3) unsigned NOT NULL,
			`email_addresses` varchar(255) NOT NULL default '',
			`discovered` tinyint(3) unsigned NOT NULL,
			`date_first_seen` timestamp NOT NULL default '0000-00-00 00:00:00',
			`date_last_seen` timestamp NOT NULL default '0000-00-00 00:00:00',
			`date_last_notif` timestamp NOT NULL default '0000-00-00 00:00:00',
			PRIMARY KEY  (`mac_address`),
			KEY `mac_id` (`mac_id`))
			ENGINE=MyISAM;");
	}

	if (!mactrack_db_table_exists("mac_track_oui_database")) {
		db_execute("CREATE TABLE `mac_track_oui_database` (
			`vendor_mac` varchar(8) NOT NULL,
			`vendor_name` varchar(100) NOT NULL,
			`vendor_address` text NOT NULL,
			`present` tinyint(3) unsigned NOT NULL default '1',
			PRIMARY KEY  (`vendor_mac`),
			KEY `vendor_name` (`vendor_name`))
			ENGINE=MyISAM;");
	}

	if (!mactrack_db_table_exists("mac_track_ports")) {
		db_execute("CREATE TABLE `mac_track_ports` (
			`site_id` int(10) unsigned NOT NULL default '0',
			`device_id` int(10) unsigned NOT NULL default '0',
			`hostname` varchar(40) NOT NULL default '',
			`device_name` varchar(100) NOT NULL default '',
			`vlan_id` varchar(5) NOT NULL default 'N/A',
			`vlan_name` varchar(50) NOT NULL default '',
			`mac_address` varchar(20) NOT NULL default '',
			`vendor_mac` varchar(8) default NULL,
			`ip_address` varchar(20) NOT NULL default '',
			`dns_hostname` varchar(200) default '',
			`port_number` varchar(10) NOT NULL default '',
			`port_name` varchar(50) NOT NULL default '',
			`scan_date` datetime NOT NULL default '0000-00-00 00:00:00',
			`authorized` tinyint(3) unsigned NOT NULL default '0',
			PRIMARY KEY  (`port_number`,`scan_date`,`mac_address`,`device_id`),
			KEY `site_id` (`site_id`),
			KEY `scan_date` USING BTREE(`scan_date`),
			KEY `description` (`device_name`),
			KEY `mac` (`mac_address`),
			KEY `hostname` (`hostname`),
			KEY `vlan_name` (`vlan_name`),
			KEY `vlan_id` (`vlan_id`),
			KEY `device_id` (`device_id`),
			KEY `ip_address` (`ip_address`),
			KEY `port_name` (`port_name`),
			KEY `dns_hostname` (`dns_hostname`),
			KEY `vendor_mac` (`vendor_mac`),
			KEY `authorized` (`authorized`))
			ENGINE=MyISAM COMMENT='Database for Tracking Device MACs'");
	}

	if (!mactrack_db_table_exists("mac_track_processes")) {
		db_execute("CREATE TABLE `mac_track_processes` (
			`device_id` int(11) NOT NULL default '0',
			`process_id` int(10) unsigned default NULL,
			`status` varchar(20) NOT NULL default 'Queued',
			`start_date` datetime NOT NULL default '0000-00-00 00:00:00',
			PRIMARY KEY  (`device_id`))
			ENGINE=MyISAM");
	}

	if (!mactrack_db_table_exists("mac_track_scan_dates")) {
		db_execute("CREATE TABLE `mac_track_scan_dates` (
			`scan_date` datetime NOT NULL default '0000-00-00 00:00:00',
			PRIMARY KEY  (`scan_date`))
			ENGINE=MyISAM;");
	}

	if (!mactrack_db_table_exists("mac_track_scanning_functions")) {
		db_execute("CREATE TABLE `mac_track_scanning_functions` (
			`scanning_function` varchar(100) NOT NULL default '',
			`type` int(10) unsigned NOT NULL default '0',
			`description` varchar(200) NOT NULL default '',
			PRIMARY KEY  (`scanning_function`))
			ENGINE=MyISAM
			COMMENT='Registered Scanning Functions';");
	}

	if (!mactrack_db_table_exists("mac_track_sites")) {
		db_execute("CREATE TABLE `mac_track_sites` (
			`site_id` int(10) unsigned NOT NULL auto_increment,
			`site_name` varchar(100) NOT NULL default '',
			`customer_contact` varchar(150) default '',
			`netops_contact` varchar(150) default '',
			`facilities_contact` varchar(150) default '',
			`site_info` text,
			`total_devices` int(10) unsigned NOT NULL default '0',
			`total_device_errors` int(10) unsigned NOT NULL default '0',
			`total_macs` int(10) unsigned NOT NULL default '0',
			`total_ips` int(10) unsigned NOT NULL default '0',
			`total_user_ports` int(11) NOT NULL default '0',
			`total_oper_ports` int(10) unsigned NOT NULL default '0',
			`total_trunk_ports` int(10) unsigned NOT NULL default '0',
			PRIMARY KEY  (`site_id`))
			ENGINE=MyISAM;");
	}

	if (!mactrack_db_table_exists("mac_track_temp_ports")) {
		db_execute("CREATE TABLE `mac_track_temp_ports` (
			`site_id` int(10) unsigned NOT NULL default '0',
			`device_id` int(10) unsigned NOT NULL default '0',
			`hostname` varchar(40) NOT NULL default '',
			`device_name` varchar(100) NOT NULL default '',
			`vlan_id` varchar(5) NOT NULL default 'N/A',
			`vlan_name` varchar(50) NOT NULL default '',
			`mac_address` varchar(20) NOT NULL default '',
			`vendor_mac` varchar(8) default NULL,
			`ip_address` varchar(20) NOT NULL default '',
			`dns_hostname` varchar(200) default '',
			`port_number` varchar(10) NOT NULL default '',
			`port_name` varchar(50) NOT NULL default '',
			`scan_date` datetime NOT NULL default '0000-00-00 00:00:00',
			`updated` tinyint(3) unsigned NOT NULL default '0',
			`authorized` tinyint(3) unsigned NOT NULL default '0',
			PRIMARY KEY  (`port_number`,`scan_date`,`mac_address`,`device_id`),
			KEY `site_id` (`site_id`),
			KEY `description` (`device_name`),
			KEY `ip_address` (`ip_address`),
			KEY `hostname` (`hostname`),
			KEY `vlan_name` (`vlan_name`),
			KEY `vlan_id` (`vlan_id`),
			KEY `device_id` (`device_id`),
			KEY `mac` (`mac_address`),
			KEY `updated` (`updated`),
			KEY `vendor_mac` (`vendor_mac`),
			KEY `authorized` (`authorized`))
			ENGINE=MyISAM
			COMMENT='Database for Storing Temporary Results for Tracking Device MACS';");
	}

	if (!mactrack_db_table_exists("mac_track_vlans")) {
		db_execute("CREATE TABLE `mac_track_vlans` (
			`vlan_id` int(10) unsigned NOT NULL,
			`site_id` int(10) unsigned NOT NULL,
			`device_id` int(10) unsigned NOT NULL,
			`vlan_name` varchar(128) NOT NULL,
			`present` tinyint(3) unsigned NOT NULL default '1',
			PRIMARY KEY  (`vlan_id`,`site_id`,`device_id`),
			KEY `vlan_name` (`vlan_name`))
			ENGINE=MyISAM;");
	}

	if (!mactrack_db_table_exists("mac_track_aggregated_ports")) {
		db_execute("CREATE TABLE `mac_track_aggregated_ports` (
			`row_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
			`site_id` int(10) unsigned NOT NULL DEFAULT '0',
			`device_id` int(10) unsigned NOT NULL DEFAULT '0',
			`hostname` varchar(40) NOT NULL DEFAULT '',
			`device_name` varchar(100) NOT NULL DEFAULT '',
			`vlan_id` varchar(5) NOT NULL DEFAULT 'N/A',
			`vlan_name` varchar(50) NOT NULL DEFAULT '',
			`mac_address` varchar(20) NOT NULL DEFAULT '',
			`vendor_mac` varchar(8) DEFAULT NULL,
			`ip_address` varchar(20) NOT NULL DEFAULT '',
			`dns_hostname` varchar(200) DEFAULT '',
			`port_number` varchar(10) NOT NULL DEFAULT '',
			`port_name` varchar(50) NOT NULL DEFAULT '',
			`date_last` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			`first_scan_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			`count_rec` int(10) unsigned NOT NULL DEFAULT '0',
			`active_last` tinyint(1) unsigned NOT NULL DEFAULT '0',
			`authorized` tinyint(3) unsigned NOT NULL DEFAULT '0',
			PRIMARY KEY (`row_id`),
			UNIQUE KEY `port_number` USING BTREE (`port_number`,`mac_address`,`ip_address`,`device_id`,`site_id`,`vlan_id`,`authorized`),
			KEY `site_id` (`site_id`),
			KEY `description` (`device_name`),
			KEY `mac` (`mac_address`),
			KEY `hostname` (`hostname`),
			KEY `vlan_name` (`vlan_name`),
			KEY `vlan_id` (`vlan_id`),
			KEY `device_id` (`device_id`),
			KEY `ip_address` (`ip_address`),
			KEY `port_name` (`port_name`),
			KEY `dns_hostname` (`dns_hostname`),
			KEY `vendor_mac` (`vendor_mac`),
			KEY `authorized` (`authorized`),
			KEY `site_id_device_id` (`site_id`,`device_id`)
			) ENGINE=MyISAM COMMENT='Database for aggregated date for Tracking Device MAC''s';");
	}

	if (!mactrack_db_table_exists("mac_track_snmp")) {
		db_execute("CREATE TABLE `mac_track_snmp` (
			`id` int(10) unsigned NOT NULL auto_increment,
			`name` varchar(100) NOT NULL default '',
			PRIMARY KEY  (`id`))
			ENGINE=MyISAM COMMENT='Group of SNMP Option Sets';");
	}

	if (!mactrack_db_table_exists("mac_track_snmp_items")) {
		db_execute("CREATE TABLE `mac_track_snmp_items` (
			`id` int(10) unsigned NOT NULL auto_increment,
			`snmp_id` int(10) unsigned NOT NULL default '0',
			`sequence` int(10) unsigned NOT NULL default '0',
			`snmp_version` varchar(100) NOT NULL default '',
			`snmp_readstring` varchar(100) NOT NULL,
			`snmp_port` int(10) NOT NULL default '161',
			`snmp_timeout` int(10) unsigned NOT NULL default '500',
			`snmp_retries` tinyint(11) unsigned NOT NULL default '3',
			`max_oids` int(12) unsigned default '10',
			`snmp_username` varchar(50) default NULL,
			`snmp_password` varchar(50) default NULL,
			`snmp_auth_protocol` char(5) default '',
			`snmp_priv_passphrase` varchar(200) default '',
			`snmp_priv_protocol` char(6) default '',
			`snmp_context` varchar(64) default '',
			PRIMARY KEY  (`id`,`snmp_id`))
			ENGINE=MyISAM COMMENT='Set of SNMP Options';");
	}

	if (!sizeof(db_fetch_row("SHOW TABLES LIKE 'mac_track_interface_graphs'"))) {
		db_execute("CREATE TABLE `mac_track_interface_graphs` (
			`device_id` int(10) unsigned NOT NULL default '0',
			`ifIndex` int(10) unsigned NOT NULL,
			`ifName` varchar(20) NOT NULL default '',
			`host_id` int(11) NOT NULL default '0',
			`local_graph_id` int(10) unsigned NOT NULL,
			`snmp_query_id` int(11) NOT NULL default '0',
			`graph_template_id` int(11) NOT NULL default '0',
			`field_name` varchar(20) NOT NULL default '',
			`field_value` varchar(25) NOT NULL default '',
			`present` tinyint(4) default '1',
			PRIMARY KEY  (`local_graph_id`,`device_id`,`ifIndex`, `host_id`),
			KEY `host_id` (`host_id`),
			KEY `device_id` (`device_id`)
			) ENGINE=MyISAM;"
		);
	}
}

function mactrack_version () {
	return array(
		'name'      => 'mactrack',
		'version'   => '2.8',
		'longname'  => 'Device Tracking',
		'author'    => 'Larry Adams',
		'homepage'  => 'http://cacti.net',
		'email'     => 'larryjadams@comcast.net',
		'url'       => 'http://cactiusers.org/cacti/versions.php'
	);
}

function mactrack_page_head() {
	global $config;

	if (!isset($config["base_path"])) {
		print "<script type='text/javascript' src='" . URL_PATH . "plugins/mactrack/mactrack.js'></script>\n";
	}else{
		print "<link type='text/css' href='" . $config["url_path"] . "plugins/mactrack/mactrack.css' rel='stylesheet'>\n";
		print "<script type='text/javascript' src='" . $config["url_path"] . "plugins/mactrack/mactrack.js'></script>\n";
	}
}

function mactrack_poller_bottom () {
	global $config;

	if (defined('CACTI_BASE_PATH')) {
		$config["base_path"] = CACTI_BASE_PATH;
	}

	include_once($config["base_path"] . "/lib/poller.php");
	include_once($config["base_path"] . "/lib/data_query.php");
	include_once($config["base_path"] . "/lib/graph_export.php");
	include_once($config["base_path"] . "/lib/rrd.php");

	$command_string = read_config_option("path_php_binary");
	$extra_args = "-q " . $config["base_path"] . "/plugins/mactrack/poller_mactrack.php";
	exec_background($command_string, "$extra_args");
}

function mactrack_config_settings () {
	global $tabs, $settings, $snmp_versions, $mactrack_poller_frequencies,
	$mactrack_data_retention, $mactrack_macauth_frequencies, $mactrack_update_policies;

	$tabs["mactrack"] = "Device Tracking";

	$settings["mactrack"] = array(
		"mactrack_hdr_timing" => array(
			"friendly_name" => "General Settings",
			"method" => "spacer",
			),
		"mt_collection_timing" => array(
			"friendly_name" => "Scanning Frequency",
			"description" => "Choose when to collect MAC and IP Addresses and Interface statistics from your network devices.",
			"method" => "drop_array",
			"default" => "disabled",
			"array" => $mactrack_poller_frequencies,
			),
		"mt_processes" => array(
			"friendly_name" => "Concurrent Processes",
			"description" => "Specify how many devices will be polled simultaneously until all devices have been polled.",
			"default" => "7",
			"method" => "textbox",
			"max_length" => "10",
			"size" => "4"
			),
		"mt_script_runtime" => array(
			"friendly_name" => "Scanner Max Runtime",
			"description" => "Specify the number of minutes a device scanning function will allowed to run prior to the system assuming it has been completed.  This setting will correct for abended scanning jobs.",
			"default" => "20",
			"method" => "textbox",
			"max_length" => "10",
			"size" => "4"
			),
		"mt_base_time" => array(
			"friendly_name" => "Start Time for Data Collection",
			"description" => "When would you like the first data collection to take place.  All future data collection times will be based upon this start time.  A good example would be 12:00AM.",
			"default" => "1:00am",
			"method" => "textbox",
			"max_length" => "10",
			"size" => "8"
			),
		"mt_maint_time" => array(
			"friendly_name" => "Database Maintenance Time",
			"description" => "When should old database records be removed from the database.  Please note that no access will be permitted to the port database while this action is taking place.",
			"default" => "12:00am",
			"method" => "textbox",
			"max_length" => "10",
			"size" => "8"
			),
		"mt_data_retention" => array(
			"friendly_name" => "Data Retention",
			"description" => "How long should port MAC details be retained in the database.",
			"method" => "drop_array",
			"default" => "2weeks",
			"array" => $mactrack_data_retention,
			),
		"mt_ignorePorts_delim" => array(
			"friendly_name" => "Switch Level Ignore Ports Delimiter",
			"description" => "What delimiter should MacTrack use when parsing the Ignore Ports string for each switch.",
			"method" => "drop_array",
			"default" => "-1",
			"array" => array("-1" => "Auto Detect", ":" => "Colon [:]", "|" => "Pipe [|]", " " => "Space [ ]")
			),
		"mt_mac_delim" => array(
			"friendly_name" => "Mac Address Delimiter",
			"description" => "How should each octet of the MAC address be delimited.",
			"method" => "drop_array",
			"default" => ":",
			"array" => array(":" => "Colon [:]", "-" => "Dash [-]")
			),
		"mt_ignorePorts" => array(
			"method" => "textbox",
			"friendly_name" => "Ports to Ignore",
			"description" => "Provide a regular expression of ifNames or ifDescriptions of ports to ignore in the interface list.  For example (Vlan|Loopback|Null).",
			"class" => "textAreaNotes",
			"defaults" => "(Vlan|Loopback|Null)",
			"max_length" => "255",
			"size" => "80"
			),
		"mactrack_hdr_rdns" => array(
			"friendly_name" => "DNS Settings",
			"method" => "spacer",
			),
		"mt_reverse_dns" => array(
			"friendly_name" => "Perform Reverse DNS Name Resolution",
			"description" => "Should MacTrack perform reverse DNS lookup of the IP addresses associated with ports. CAUTION: If DNS is not properly setup, this will slow scan time significantly.",
			"default" => "",
			"method" => "checkbox"
			),
		"mt_dns_primary" => array(
			"friendly_name" => "Primary DNS IP Address",
			"description" => "Enter the primary DNS IP Address to utilize for reverse lookups.",
			"method" => "textbox",
			"default" => "",
			"max_length" => "30",
			"size" => "18"
			),
		"mt_dns_secondary" => array(
			"friendly_name" => "Secondary DNS IP Address",
			"description" => "Enter the secondary DNS IP Address to utilize for reverse lookups.",
			"method" => "textbox",
			"default" => "",
			"max_length" => "30",
			"size" => "18"
			),
		"mt_dns_timeout" => array(
			"friendly_name" => "DNS Timeout",
			"description" => "Please enter the DNS timeout in milliseconds.  MacTrack uses a PHP based DNS resolver.",
			"method" => "textbox",
			"default" => "500",
			"max_length" => "10",
			"size" => "4"
			),
		"mt_dns_prime_interval" => array(
			"friendly_name" => "DNS Prime Interval",
			"description" => "How often, in seconds do MacTrack scanning IP's need to be resolved to MAC addresses for DNS resolution.  Using a larger number when you have several thousand devices will increase performance.",
			"method" => "textbox",
			"default" => "120",
			"max_length" => "10",
			"size" => "4"
			),
		"mactrack_hdr_notification" => array(
			"friendly_name" => "Notification Settings",
			"method" => "spacer",
			),
		"mt_from_email" => array(
			"friendly_name" => "Source Address",
			"description" => "The source e-mail address for MacTrack e-mails.",
			"method" => "textbox",
			"default" => "thewitness@cacti.net",
			"max_length" => "100",
			"size" => "30"
			),
		"mt_from_name" => array(
			"friendly_name" => "Source E-Mail Name",
			"description" => "The Source E-Mail name for MacTrack e-mails.",
			"method" => "textbox",
			"default" => "MACTrack Administrator",
			"max_length" => "100",
			"size" => "30"
			),
		"mt_macwatch_description" => array(
			"friendly_name" => "MacWatch Default Body",
			"description" => htmlspecialchars("The e-mail body preset for MacTrack MacWatch e-mails.  The body can contain " .
			"any valid html tags.  It also supports replacement tags that will be processed when sending an e-mail.  " .
			"Valid tags include <IP>, <MAC>, <TICKET>, <SITENAME>, <DEVICEIP>, <PORTNAME>, <PORTNUMBER>, <DEVICENAME>."),
			"method" => "textarea",
			"default" => "Mac Address <MAC> found at IP Address <IP> for Ticket Number: <TICKET>.<br>" .
			"The device is located at<br>Site: <SITENAME>, Device <DEVICENAME>, IP <DEVICEIP>, Port <PORTNUMBER>, " .
			"and Port Name <PORTNAME>",
			"class" => "textAreaNotes",
			"max_length" => "512",
			"textarea_rows" => "5",
			"textarea_cols" => "80",
			),
		"mt_macauth_emails" => array(
			"friendly_name" => "MacAuth Report E-Mail Addresses",
			"description" => "A comma delimited list of users to recieve the MacAuth e-mail notifications.",
			"method" => "textarea",
			"default" => "",
			"class" => "textAreaNotes",
			"max_length" => "255",
			"textarea_rows" => "5",
			"textarea_cols" => "80",
			),
		"mt_macauth_email_frequency" => array(
			"friendly_name" => "MacAuth Report Frequency",
			"description" => "How often will the MacAuth Reports be e-mailed.",
			"method" => "drop_array",
			"default" => "disabled",
			"array" => $mactrack_macauth_frequencies,
			),
		"mactrack_hdr_arpwatch" => array(
			"friendly_name" => "MacTrack Arpwatch Settings",
			"method" => "spacer",
			),
		"mt_arpwatch" => array(
			"friendly_name" => "Enable ArpWatch",
			"description" => "Should MacTrack also use ArpWatch data to supplement Mac to IP/DNS resolution?",
			"default" => "",
			"method" => "checkbox"
			),
		"mt_arpwatch_path" => array(
			"friendly_name" => "ArpWatch Database Path",
			"description" => "The location of the ArpWatch Database file on the Cacti server.",
			"method" => "filepath",
			"default" => "",
			"max_length" => "255",
			"size" => "60"
			),
		"mactrack_hdr_general" => array(
			"friendly_name" => "SNMP Presets",
			"method" => "spacer",
			),
		"mt_update_policy" => array(
			"friendly_name" => "Update Policy for SNMP Options",
			"description" => "Policy for synchronization of SNMP Options between Cacti devices and Mactrack Devices.",
			"method" => "drop_array",
			"default" => 1,
			"array" => $mactrack_update_policies,
			),
		"mt_snmp_ver" => array(
			"friendly_name" => "SNMP Version",
			"description" => "Default SNMP version for all new hosts.",
			"method" => "drop_array",
			"default" => "Version 1",
			"array" => $snmp_versions,
			),
		"mt_snmp_community" => array(
			"friendly_name" => "SNMP Community",
			"description" => "Default SNMP read community for all new hosts.",
			"method" => "textbox",
			"default" => "public",
			"max_length" => "100",
			"size" => "20"
			),
		"mt_snmp_communities" => array(
			"friendly_name" => "SNMP Communities",
			"description" => "Fill in the list of available SNMP read strings to test for this device. Each read string must be separated by a colon ':'.  These read strings will be tested sequentially if the primary read string is invalid.",
			"method" => "textbox",
			"default" => "public:private:secret",
			"max_length" => "255"
			),
		"mt_snmp_port" => array(
			"friendly_name" => "SNMP Port",
			"description" => "The UDP/TCP Port to poll the SNMP agent on.",
			"method" => "textbox",
			"default" => "161",
			"max_length" => "10",
			"size" => "4"
			),
		"mt_snmp_timeout" => array(
			"friendly_name" => "SNMP Timeout",
			"description" => "Default SNMP timeout in milli-seconds.",
			"method" => "textbox",
			"default" => "500",
			"max_length" => "10",
			"size" => "4"
			),
		"mt_snmp_retries" => array(
			"friendly_name" => "SNMP Retries",
			"description" => "The number times the SNMP poller will attempt to reach the host before failing.",
			"method" => "textbox",
			"default" => "3",
			"max_length" => "10",
			"size" => "4"
			),
		"mt_interface_exception" => array(
			"friendly_name" => "MacTrack Interface Exception Background Colors",
			"method" => "spacer",
			),
		"mt_int_up_bgc" => array(
			"friendly_name" => "Interface Up",
			"description" => "The background color to use if interface is up.  Select 'None' to hide legend.",
			"method" => "drop_color",
			"default" => 0,
			),
		"mt_int_up_wo_alias_bgc" => array(
			"friendly_name" => "No ifAlias Background Color",
			"description" => "The background color to use if interface is up without either an ifAlias or PortName.  Select 'None' to hide legend.",
			"method" => "drop_color",
			"default" => 0,
			),
		"mt_int_errors_bgc" => array(
			"friendly_name" => "Interface with Errors Background Color",
			"description" => "The background color to use if interface has shown errors since the last scan.  Select 'None' to hide legend.",
			"method" => "drop_color",
			"default" => 0,
			),
		"mt_int_discards_bgc" => array(
			"friendly_name" => "Interface with Discards Background Color",
			"description" => "The background color to use if interface has show discards since the last scan.  Select 'None' to hide legend.",
			"method" => "drop_color",
			"default" => 0,
			),
		"mt_int_no_graph_bgc" => array(
			"friendly_name" => "Interface Without A Graph",
			"description" => "The background color to use if interface has no graphs.  Select 'None' to hide legend.",
			"method" => "drop_color",
			"default" => 0,
			),
		"mt_int_no_device_bgc" => array(
			"friendly_name" => "Interface That has no Cacti Device",
			"description" => "The background color to use if device owning this interface is not in Cacti.  Select 'None' to hide legend.",
			"method" => "drop_color",
			"default" => 0,
			),
		"mt_int_changed_bgc" => array(
			"friendly_name" => "Interface That Has Changed in Less than 24 Hours",
			"description" => "The background color to use if device owning this interface is not in Cacti.  Select 'None' to hide legend.",
			"method" => "drop_color",
			"default" => 0,
			),
		"mt_int_down_bgc" => array(
			"friendly_name" => "Interface That is Down",
			"description" => "The background color to use if the Interface was down during the last scan.  Select 'None' to hide legend.",
			"method" => "drop_color",
			"default" => 0,
			)
		);

	$settings["visual"]["mactrack_header"] = array(
		"friendly_name" => "Device Tracking",
		"method" => "spacer",
		);
	$settings["visual"]["num_rows_mactrack"] = array(
		"friendly_name" => "Rows Per Page",
		"description" => "The number of rows to display on a single page for Device Tracking sites, devices and reports.",
		"method" => "textbox",
		"default" => "30",
		"max_length" => "10"
		);
	$ts = array();
	foreach ($settings['path'] as $t => $ta) {
		$ts[$t] = $ta;
		if ($t == 'path_snmpget') {
			$ts["path_snmpbulkwalk"] = array(
				"friendly_name" => "snmpbulkwalk Binary Path",
				"description" => "The path to your snmpbulkwalk binary.",
				"method" => "textbox",
				"max_length" => "255"
				);
		}
	}
	$settings['path']=$ts;

	mactrack_check_upgrade();
}

function mactrack_draw_navigation_text ($nav) {
	$nav["mactrack_devices.php:"] = array("title" => "MacTrack Devices", "mapping" => "index.php:", "url" => "mactrack_devices.php", "level" => "1");
	$nav["mactrack_devices.php:edit"] = array("title" => "(Edit)", "mapping" => "index.php:,mactrack_devices.php:", "url" => "", "level" => "2");
	$nav["mactrack_devices.php:import"] = array("title" => "(Import)", "mapping" => "index.php:,mactrack_devices.php:", "url" => "", "level" => "2");
	$nav["mactrack_devices.php:actions"] = array("title" => "Actions", "mapping" => "index.php:,mactrack_devices.php:", "url" => "", "level" => "2");
	$nav["mactrack_snmp.php:"] = array("title" => "MacTrack SNMP Options", "mapping" => "index.php:", "url" => "mactrack_snmp.php", "level" => "1");
	$nav["mactrack_snmp.php:actions"] = array("title" => "Actions", "mapping" => "index.php:,mactrack_snmp.php:", "url" => "", "level" => "2");
	$nav["mactrack_snmp.php:edit"] = array("title" => "(Edit)", "mapping" => "index.php:,mactrack_snmp.php:", "url" => "", "level" => "2");
	$nav["mactrack_snmp.php:item_edit"] = array("title" => "(Edit)", "mapping" => "index.php:,mactrack_snmp.php:", "url" => "", "level" => "2");
	$nav["mactrack_device_types.php:"] = array("title" => "MacTrack Device Types", "mapping" => "index.php:", "url" => "mactrack_device_types.php", "level" => "1");
	$nav["mactrack_device_types.php:edit"] = array("title" => "(Edit)", "mapping" => "index.php:,mactrack_device_types.php:", "url" => "", "level" => "2");
	$nav["mactrack_device_types.php:import"] = array("title" => "(Import)", "mapping" => "index.php:,mactrack_device_types.php:", "url" => "", "level" => "2");
	$nav["mactrack_device_types.php:actions"] = array("title" => "Actions", "mapping" => "index.php:,mactrack_device_types.php:", "url" => "", "level" => "2");
	$nav["mactrack_sites.php:"] = array("title" => "MacTrack Sites", "mapping" => "index.php:", "url" => "mactrack_sites.php", "level" => "1");
	$nav["mactrack_sites.php:edit"] = array("title" => "(Edit)", "mapping" => "index.php:,mactrack_sites.php:", "url" => "", "level" => "2");
	$nav["mactrack_sites.php:actions"] = array("title" => "Actions", "mapping" => "index.php:,mactrack_sites.php:", "url" => "", "level" => "2");
	$nav["mactrack_macwatch.php:"] = array("title" => "Mac Address Tracking Utility", "mapping" => "index.php:", "url" => "mactrack_macwatch.php", "level" => "1");
	$nav["mactrack_macwatch.php:edit"] = array("title" => "(Edit)", "mapping" => "index.php:,mactrack_macwatch.php:", "url" => "", "level" => "2");
	$nav["mactrack_macwatch.php:actions"] = array("title" => "Actions", "mapping" => "index.php:,mactrack_macwatch.php:", "url" => "", "level" => "2");
	$nav["mactrack_macauth.php:"] = array("title" => "Mac Address Authorization Utility", "mapping" => "index.php:", "url" => "mactrack_macauth.php", "level" => "1");
	$nav["mactrack_macauth.php:edit"] = array("title" => "(Edit)", "mapping" => "index.php:,mactrack_macauth.php:", "url" => "", "level" => "2");
	$nav["mactrack_macauth.php:actions"] = array("title" => "Actions", "mapping" => "index.php:,mactrack_macauth.php:", "url" => "", "level" => "2");
	$nav["mactrack_vendormacs.php:"] = array("title" => "MacTrack Vendor Macs", "mapping" => "index.php:", "url" => "mactrack_vendormacs.php", "level" => "1");
	$nav["mactrack_view_macs.php:"] = array("title" => "MacTrack Viewer", "mapping" => "", "url" => "mactrack_view_macs.php", "level" => "0");
	$nav["mactrack_view_macs.php:actions"] = array("title" => "Actions", "mapping" => "mactrack_view_macs.php:", "url" => "", "level" => "1");
	$nav["mactrack_view_arp.php:"] = array("title" => "MacTrack IP Address Viewer", "mapping" => "", "url" => "mactrack_view_arp.php", "level" => "0");
	$nav["mactrack_view_interfaces.php:"] = array("title" => "MacTrack View Interfaces", "mapping" => "", "url" => "mactrack_view_interfaces.php", "level" => "0");
	$nav["mactrack_view_sites.php:"] = array("title" => "MacTrack View Sites", "mapping" => "", "url" => "mactrack_view_sites.php", "level" => "0");
	$nav["mactrack_view_ips.php:"] = array("title" => "MacTrack View IP Ranges", "mapping" => "", "url" => "mactrack_view_ips.php", "level" => "0");
	$nav["mactrack_view_devices.php:"] = array("title" => "MacTrack View Devices", "mapping" => "", "url" => "mactrack_view_devices.php", "level" => "0");
	$nav["mactrack_utilities.php:"] = array("title" => "Device Tracking Utilities", "mapping" => "index.php:", "url" => "mactrack_utilities.php", "level" => "1");
	$nav["mactrack_utilities.php:mactrack_utilities_perform_db_maint"] = array("title" => "Perform Database Maintenance", "mapping" => "index.php:,mactrack_utilities.php:", "url" => "mactrack_utilities.php", "level" => "2");
	$nav["mactrack_utilities.php:mactrack_utilities_purge_scanning_funcs"] = array("title" => "Refresh Scanning Functions", "mapping" => "index.php:,mactrack_utilities.php:", "url" => "mactrack_utilities.php", "level" => "2");
	$nav["mactrack_utilities.php:mactrack_utilities_truncate_ports_table"] = array("title" => "Truncate Port Results Table", "mapping" => "index.php:,mactrack_utilities.php:", "url" => "mactrack_utilities.php", "level" => "2");
	$nav["mactrack_utilities.php:mactrack_utilities_purge_aggregated_data"] = array("title" => "Truncate Aggregated Port Results Table", "mapping" => "index.php:,mactrack_utilities.php:", "url" => "mactrack_utilities.php", "level" => "2");
	$nav["mactrack_utilities.php:mactrack_utilities_recreate_aggregated_data"] = array("title" => "Truncate and ReCreate Aggregated Port Results Table", "mapping" => "index.php:,mactrack_utilities.php:", "url" => "mactrack_utilities.php", "level" => "2");
	$nav["mactrack_utilities.php:mactrack_proc_status"] = array("title" => "View MacTrack Process Status", "mapping" => "index.php:,mactrack_utilities.php:", "url" => "mactrack_utilities.php", "level" => "2");
	$nav["mactrack_utilities.php:mactrack_refresh_oui_database"] = array("title" => "Refresh/Update Vendor MAC Database from IEEE", "mapping" => "index.php:,mactrack_utilities.php:", "url" => "mactrack_utilities.php", "level" => "2");
	$nav["mactrack_view_graphs.php:"] = array("title" => "MacTrack Graph Viewer", "mapping" => "mactrack_view_macs.php:", "url" => "mactrack_view_graphs.php", "level" => "1");
	$nav["mactrack_view_graphs.php:preview"] = array("title" => "MacTrack Graph Viewer", "mapping" => "mactrack_view_macs.php:", "url" => "mactrack_view_graphs.php", "level" => "1");
	return $nav;
}

function mactrack_show_tab () {
	global $config, $user_auth_realm_filenames;

	$realm_id = 2120;
	if ((db_fetch_assoc("select user_auth_realm.realm_id
		from user_auth_realm where user_auth_realm.user_id='" . $_SESSION["sess_user_id"] . "'
		and user_auth_realm.realm_id='$realm_id'")) || (empty($realm_id))) {

		if (substr_count($_SERVER["REQUEST_URI"], "mactrack_view_")) {
			print '<a href="' . $config['url_path'] . 'plugins/mactrack/mactrack_view_macs.php"><img src="' . $config['url_path'] . 'plugins/mactrack/images/tab_mactrack_down.png" alt="MacTrack" align="absmiddle" border="0"></a>';
		}else{
			print '<a href="' . $config['url_path'] . 'plugins/mactrack/mactrack_view_macs.php"><img src="' . $config['url_path'] . 'plugins/mactrack/images/tab_mactrack.png" alt="MacTrack" align="absmiddle" border="0"></a>';
		}
	}
}

function mactrack_config_arrays () {
	global $mactrack_device_types, $mactrack_search_types, $messages;
	global $user_auth_realms, $user_auth_realm_filenames, $menu, $config, $rows_selector;
	global $mactrack_poller_frequencies, $mactrack_data_retention, $refresh_interval;
	global $mactrack_macauth_frequencies, $mactrack_duplexes, $mactrack_update_policies;

	$user_auth_realms[2120]='Plugin -> MacTrack Viewer';
	$user_auth_realms[2121]='Plugin -> MacTrack Administrator';
	$user_auth_realms[2122]='Plugin -> MacTrack Security';

	$user_auth_realm_filenames['mactrack_view_ips.php']        = 2120;
	$user_auth_realm_filenames['mactrack_view_arp.php']        = 2120;
	$user_auth_realm_filenames['mactrack_view_macs.php']       = 2120;
	$user_auth_realm_filenames['mactrack_view_sites.php']      = 2120;
	$user_auth_realm_filenames['mactrack_view_devices.php']    = 2120;
	$user_auth_realm_filenames['mactrack_view_interfaces.php'] = 2120;
	$user_auth_realm_filenames['mactrack_view_graphs.php']     = 2120;
	$user_auth_realm_filenames['mactrack_ajax.php']            = 2120;
	$user_auth_realm_filenames['mactrack_ajax_admin.php']      = 2121;
	$user_auth_realm_filenames['mactrack_devices.php']         = 2121;
	$user_auth_realm_filenames['mactrack_snmp.php']            = 2121;
	$user_auth_realm_filenames['mactrack_sites.php']           = 2121;
	$user_auth_realm_filenames['mactrack_device_types.php']    = 2121;
	$user_auth_realm_filenames['mactrack_utilities.php']       = 2121;
	$user_auth_realm_filenames['mactrack_macwatch.php']        = 2121;
	$user_auth_realm_filenames['mactrack_macauth.php']         = 2121;
	$user_auth_realm_filenames['mactrack_vendormacs.php']      = 2121;

	if (isset($_SESSION['mactrack_message']) && $_SESSION['mactrack_message'] != '') {
		$messages['mactrack_message'] = array('message' => $_SESSION['mactrack_message'], 'type' => 'info');
	}

	if (!function_exists("__")) {
		function __($text, $domain = "cacti") {
			return $text;
		}
	}

	$refresh_interval = array(
		5 => "5 Seconds",
		10 => "10 Seconds",
		20 => "20 Seconds",
		30 => "30 Seconds",
		60 => "1 Minute",
		300 => "5 Minutes");

	$mactrack_device_types = array(
		1 => "Switch/Hub",
		2 => "Switch/Router",
		3 => "Router");

	$mactrack_search_types = array(
		1 => "",
		2 => "Matches",
		3 => "Contains",
		4 => "Begins With",
		5 => "Does Not Contain",
		6 => "Does Not Begin With",
		7 => "Is Null",
		8 => "Is Not Null");

	$mactrack_duplexes = array(
		1 => "Unknown",
		2 => "Half",
		3 => "Full");

	$mactrack_update_policies = array(
		1 => "None",
		2 => "Sync Cacti Device to MacTrack Device",
		3 => "Sync MacTrack Device to Cacti Device");

	$rows_selector = array(
		-1 => "Default",
		10 => "10",
		15 => "15",
		20 => "20",
		30 => "30",
		50 => "50",
		100 => "100",
		500 => "500",
		1000 => "1000",
		-2 => "All");

	$mactrack_poller_frequencies = array(
		"disabled" => "Disabled",
		"10" => "Every 10 Minutes",
		"15" => "Every 15 Minutes",
		"20" => "Every 20 Minutes",
		"30" => "Every 30 Minutes",
		"60" => "Every 1 Hour",
		"120" => "Every 2 Hours",
		"240" => "Every 4 Hours",
		"480" => "Every 8 Hours",
		"720" => "Every 12 Hours",
		"1440" => "Every Day");

	$mactrack_data_retention = array(
		"2days" => "2 Days",
		"5days" => "5 Days",
		"1week" => "1 Week",
		"2weeks" => "2 Weeks",
		"3weeks" => "3 Weeks",
		"1month" => "1 Month",
		"2months" => "2 Months");

	$mactrack_macauth_frequencies = array(
		"disabled" => "Disabled",
		"0" => "On Scan Completion",
		"720" => "Every 12 Hours",
		"1440" => "Every Day",
		"2880" => "Every 2 Days",
		"10080" => "Every Week");

	$menu2 = array ();
	foreach ($menu as $temp => $temp2 ) {
		$menu2[$temp] = $temp2;
		if ($temp == __('Management')) {
			$menu2["Device Tracking"]["plugins/mactrack/mactrack_sites.php"] = "Sites";
			$menu2["Device Tracking"]["plugins/mactrack/mactrack_devices.php"] = "Devices";
			$menu2["Device Tracking"]["plugins/mactrack/mactrack_snmp.php"] = "SNMP Options";
			$menu2["Device Tracking"]["plugins/mactrack/mactrack_device_types.php"] = "Device Types";
			$menu2["Device Tracking"]["plugins/mactrack/mactrack_vendormacs.php"] = "Vendor Macs";
			$menu2["Device Tracking"]["plugins/mactrack/mactrack_utilities.php"] = "Tracking Utilities";
			$menu2["Tracking Tools"]["plugins/mactrack/mactrack_macwatch.php"] = "Mac Watch";
			$menu2["Tracking Tools"]["plugins/mactrack/mactrack_macauth.php"] = "Mac Authorizations";
		}
	}
	$menu = $menu2;
}

function mactrack_config_form () {
	global $fields_mactrack_device_type_edit, $fields_mactrack_device_edit, $fields_mactrack_site_edit;
	global $fields_mactrack_snmp_edit, $fields_mactrack_snmp_item, $fields_mactrack_snmp_item_edit;
	global $mactrack_device_types, $snmp_versions, $fields_mactrack_macw_edit, $fields_mactrack_maca_edit;
	global $snmp_priv_protocols, $snmp_auth_protocols;

	/* file: mactrack_device_types.php, action: edit */
	$fields_mactrack_device_type_edit = array(
	"spacer0" => array(
		"method" => "spacer",
		"friendly_name" => "Device Scanning Function Options"
		),
	"description" => array(
		"method" => "textbox",
		"friendly_name" => "Description",
		"description" => "Give this device type a meaningful description.",
		"value" => "|arg1:description|",
		"max_length" => "250"
		),
	"vendor" => array(
		"method" => "textbox",
		"friendly_name" => "Vendor",
		"description" => "Fill in the name for the vendor of this device type.",
		"value" => "|arg1:vendor|",
		"max_length" => "250"
		),
	"device_type" => array(
		"method" => "drop_array",
		"friendly_name" => "Device Type",
		"description" => "Choose the type of device.",
		"value" => "|arg1:device_type|",
		"default" => 1,
		"array" => $mactrack_device_types
		),
	"sysDescr_match" => array(
		"method" => "textbox",
		"friendly_name" => "System Description Match",
		"description" => "Provide key information to help MacTrack detect the type of device.  The wildcard character is the '%' sign.",
		"value" => "|arg1:sysDescr_match|",
		"max_length" => "250"
		),
	"sysObjectID_match" => array(
		"method" => "textbox",
		"friendly_name" => "Vendor snmp Object ID Match",
		"description" => "Provide key information to help MacTrack detect the type of device.  The wildcard character is the '%' sign.",
		"value" => "|arg1:sysObjectID_match|",
		"max_length" => "250"
		),
	"scanning_function" => array(
		"method" => "drop_sql",
		"friendly_name" => "MAC Address Scanning Function",
		"description" => "The MacTrack scanning function to call in order to obtain and store port details.  The function name is all that is required.  The following four parameters are assumed and will always be appended: 'my_function(\$site, &\$device, \$lowport, \$highport)'.  There is no function required for a pure router.",
		"value" => "|arg1:scanning_function|",
		"default" => 1,
		"sql" => "select scanning_function as id, scanning_function as name from mac_track_scanning_functions where type='1' order by scanning_function"
		),
	"ip_scanning_function" => array(
		"method" => "drop_sql",
		"friendly_name" => "IP Address Scanning Function",
		"description" => "The MacTrack scanning function specific to Layer3 devices that track IP Addresses.",
		"value" => "|arg1:ip_scanning_function|",
		"default" => 1,
		"sql" => "select scanning_function as id, scanning_function as name from mac_track_scanning_functions where type='2' order by scanning_function"
		),
	"serial_number_oid" => array(
		"method" => "textbox",
		"friendly_name" => "Serial Number Base OID",
		"description" => "The SNMP OID used to obtain this device types serial number to be stored in the MacTrack Asset Information table.",
		"value" => "|arg1:serial_number_oid|",
		"max_length" => "100",
		"default" => ""
		),
	"serial_number_oid_type" => array(
		"method" => "drop_array",
		"friendly_name" => "Serial Number Collection Method",
		"description" => "How is the serial number collected for this OID.  If 'SNMP Walk', we assume multiple serial numbers.  If 'Get', it will be only one..",
		"value" => "|arg1:serial_number_oid_method|",
		"default" => "get",
		"array" => array("get" => "SNMP Get", "walk" => "SNMP Walk")
		),
	"lowPort" => array(
		"method" => "textbox",
		"friendly_name" => "Low User Port Number",
		"description" => "Provide the low user port number on this switch.  Leave 0 to allow the system to calculate it.",
		"value" => "|arg1:lowPort|",
		"default" => read_config_option("mt_port_lowPort"),
		"max_length" => "100",
		"size" => "10"
		),
	"highPort" => array(
		"method" => "textbox",
		"friendly_name" => "High User Port Number",
		"description" => "Provide the low user port number on this switch.  Leave 0 to allow the system to calculate it.",
		"value" => "|arg1:highPort|",
		"default" => read_config_option("mt_port_highPort"),
		"max_length" => "100",
		"size" => "10"
		),
	"device_type_id" => array(
		"method" => "hidden_zero",
		"value" => "|arg1:device_type_id|"
		),
	"_device_type_id" => array(
		"method" => "hidden_zero",
		"value" => "|arg1:device_type_id|"
		),
	"save_component_device_type" => array(
		"method" => "hidden",
		"value" => "1"
		)
	);

	/* file: mactrack_snmp.php, action: edit */
	$fields_mactrack_snmp_edit = array(
	"name" => array(
		"method" => "textbox",
		"friendly_name" => "Name",
		"description" => "Fill in the name of this SNMP option set.",
		"value" => "|arg1:name|",
		"default" => "",
		"max_length" => "100",
		"size" => "40"
		),
	);

	/* file: mactrack_snmp.php, action: item_edit */
	$fields_mactrack_snmp_item = array(
	"snmp_version" => array(
		"method" => "drop_array",
		"friendly_name" => "SNMP Version",
		"description" => "Choose the SNMP version for this host.",
		"on_change" => "changeSNMPVersion()",
		"value" => "|arg1:snmp_version|",
		"default" => read_config_option("mt_snmp_ver"),
		"array" => $snmp_versions
		),
	"snmp_readstring" => array(
		"method" => "textbox",
		"friendly_name" => "SNMP Community String",
		"description" => "Fill in the SNMP read community for this device.",
		"value" => "|arg1:snmp_readstring|",
		"default" => read_config_option("mt_snmp_community"),
		"max_length" => "100",
		"size" => "20"
		),
	"snmp_port" => array(
		"method" => "textbox",
		"friendly_name" => "SNMP Port",
		"description" => "The UDP/TCP Port to poll the SNMP agent on.",
		"value" => "|arg1:snmp_port|",
		"max_length" => "8",
		"default" => read_config_option("mt_snmp_port"),
		"size" => "10"
		),
	"snmp_timeout" => array(
		"method" => "textbox",
		"friendly_name" => "SNMP Timeout",
		"description" => "The maximum number of milliseconds Cacti will wait for an SNMP response (does not work with php-snmp support).",
		"value" => "|arg1:snmp_timeout|",
		"max_length" => "8",
		"default" => read_config_option("mt_snmp_timeout"),
		"size" => "10"
		),
	"snmp_retries" => array(
		"method" => "textbox",
		"friendly_name" => "SNMP Retries",
		"description" => "The maximum number of attempts to reach a device via an SNMP readstring prior to giving up.",
		"value" => "|arg1:snmp_retries|",
		"max_length" => "8",
		"default" => read_config_option("mt_snmp_retries"),
		"size" => "10"
		),
	"max_oids" => array(
		"method" => "textbox",
		"friendly_name" => "Maximum OID's Per Get Request",
		"description" => "Specified the number of OID's that can be obtained in a single SNMP Get request.",
		"value" => "|arg1:max_oids|",
		"max_length" => "8",
		"default" => read_config_option("max_get_size"),
		"size" => "15"
		),
	"snmp_username" => array(
		"method" => "textbox",
		"friendly_name" => "SNMP Username (v3)",
		"description" => "SNMP v3 username for this device.",
		"value" => "|arg1:snmp_username|",
		"default" => read_config_option("snmp_username"),
		"max_length" => "50",
		"size" => "15"
		),
	"snmp_password" => array(
		"method" => "textbox_password",
		"friendly_name" => "SNMP Password (v3)",
		"description" => "SNMP v3 password for this device.",
		"value" => "|arg1:snmp_password|",
		"default" => read_config_option("snmp_password"),
		"max_length" => "50",
		"size" => "15"
		),
	"snmp_auth_protocol" => array(
		"method" => "drop_array",
		"friendly_name" => "SNMP Auth Protocol (v3)",
		"description" => "Choose the SNMPv3 Authorization Protocol.",
		"value" => "|arg1:snmp_auth_protocol|",
		"default" => read_config_option("snmp_auth_protocol"),
		"array" => $snmp_auth_protocols,
		),
	"snmp_priv_passphrase" => array(
		"method" => "textbox",
		"friendly_name" => "SNMP Privacy Passphrase (v3)",
		"description" => "Choose the SNMPv3 Privacy Passphrase.",
		"value" => "|arg1:snmp_priv_passphrase|",
		"default" => read_config_option("snmp_priv_passphrase"),
		"max_length" => "200",
		"size" => "40"
		),
	"snmp_priv_protocol" => array(
		"method" => "drop_array",
		"friendly_name" => "SNMP Privacy Protocol (v3)",
		"description" => "Choose the SNMPv3 Privacy Protocol.",
		"value" => "|arg1:snmp_priv_protocol|",
		"default" => read_config_option("snmp_priv_protocol"),
		"array" => $snmp_priv_protocols,
		),
	"snmp_context" => array(
		"method" => "textbox",
		"friendly_name" => "SNMP Context",
		"description" => "Enter the SNMP Context to use for this device.",
		"value" => "|arg1:snmp_context|",
		"default" => "",
		"max_length" => "64",
		"size" => "25"
		),
	);

	/* file: mactrack_devices.php, action: edit */
	$fields_mactrack_device_edit = array(
	"spacer0" => array(
		"method" => "spacer",
		"friendly_name" => "General Device Settings"
		),
	"device_name" => array(
		"method" => "textbox",
		"friendly_name" => "Device Name",
		"description" => "Give this device a meaningful name.",
		"value" => "|arg1:device_name|",
		"max_length" => "250"
		),
	"hostname" => array(
		"method" => "textbox",
		"friendly_name" => "Hostname",
		"description" => "Fill in the fully qualified hostname for this device.",
		"value" => "|arg1:hostname|",
		"max_length" => "250"
		),
	"host_id" => array(
		"friendly_name" => "Related Cacti Host",
		"description" => "Given MacTrack Host is connected to this Cacti Host.",
		#"method" => "view",
		"method" => "drop_sql",
		"value" => "|arg1:host_id|",
		"none_value" => "None",
		"sql" => "select id,CONCAT_WS('',description,' (',hostname,')') as name from host order by description,hostname"
		),
	"scan_type" => array(
		"method" => "drop_array",
		"friendly_name" => "Scan Type",
		"description" => "Choose the scan type you wish to perform on this device.",
		"value" => "|arg1:scan_type|",
		"default" => 1,
		"array" => $mactrack_device_types
		),
	"site_id" => array(
		"method" => "drop_sql",
		"friendly_name" => "Site Name",
		"description" => "Choose the site to associate with this device.",
		"value" => "|arg1:site_id|",
		"none_value" => "None",
		"sql" => "select site_id as id,site_name as name from mac_track_sites order by name"
		),
	"notes" => array(
		"method" => "textarea",
		"friendly_name" => "Device Notes",
		"class" => "textAreaNotes",
		"textarea_rows" => "3",
		"textarea_cols" => "80",
		"description" => "This field value is useful to save general information about a specific device.",
		"value" => "|arg1:notes|",
		"max_length" => "255"
		),
	"disabled" => array(
		"method" => "checkbox",
		"friendly_name" => "Disable Device",
		"description" => "Check this box to disable all checks for this host.",
		"value" => "|arg1:disabled|",
		"default" => "",
		"form_id" => false
		),
	"spacer1" => array(
		"method" => "spacer",
		"friendly_name" => "Switch/Hub, Switch/Router Settings"
		),
	"ignorePorts" => array(
		"method" => "textarea",
		"friendly_name" => "Ports to Ignore",
		"description" => "Provide a list of ports on a specific switch/hub whose MAC results should be ignored.  Ports such
		as link/trunk ports that can not be distinguished from other user ports are examples.  Each port number must be
		separated by a colon, pipe, or a space ':', '|', ' '.  For example, 'Fa0/1: Fa1/23' or 'Fa0/1 Fa1/23' would be
		acceptable for some manufacturers switch types.",
		"value" => "|arg1:ignorePorts|",
		"default" => "",
		"class" => "textAreaNotes",
		"textarea_rows" => "3",
		"textarea_cols" => "80",
		"max_length" => "255"
		),
	"spacer2" => array(
		"method" => "spacer",
		"friendly_name" => "SNMP Options"
		),
	"snmp_options" => array(
		"method" => "drop_sql",
		"friendly_name" => "SNMP Options",
		"description" => "Select a set of SNMP options to try.",
		"value" => "|arg1:snmp_options|",
		"none_value" => "None",
		"sql" => "select * from mac_track_snmp order by name"
		),
	"snmp_readstrings" => array(
		"method" => "view",
		"friendly_name" => "Read Strings",
		"description" => "<strong>DEPRECATED:</strong> SNMP community strings",
		"value" => "|arg1:snmp_readstrings|",
		#"default" => read_config_option("mt_snmp_readstrings"),
		#"max_length" => "40",
		#"size" => "20"
		),
	"spacer3" => array(
		"method" => "spacer",
		"friendly_name" => "Specific SNMP Settings"
		),
	);

	$fields_mactrack_device_edit += $fields_mactrack_snmp_item;

	$fields_mactrack_device_edit += array(
	"spacer4" => array(
		"method" => "spacer",
		"friendly_name" => "Connectivity Options"
		),
	"term_type" => array(
		"method" => "drop_array",
		"friendly_name" => "Terminal Type",
		"description" => "Choose the terminal type that you use to connect to this device.",
		"value" => "|arg1:term_type|",
		"default" => 1,
		"array" => array(0 => "None", 1 => "Telnet", 2 => "SSH", 3 => "HTTP", 4 => "HTTPS")
		),
	"user_name" => array(
		"method" => "textbox",
		"friendly_name" => "User Name",
		"description" => "The user name to be used for your custom authentication method.  Examples include SSH, RSH, HTML, etc.",
		"value" => "|arg1:user_name|",
		"default" => "",
		"max_length" => "40",
		"size" => "20"
		),
	"user_password" => array(
		"method" => "textbox_password",
		"friendly_name" => "Password",
		"description" => "The password to be used for your custom authentication.",
		"value" => "|arg1:user_password|",
		"default" => "",
		"max_length" => "40",
		"size" => "20"
		),
	"private_key_path" => array(
		"method" => "filepath",
		"friendly_name" => "Private Key Path",
		"description" => "The path to the private key used for SSH authentication.",
		"value" => "|arg1:private_key_path|",
		"default" => "",
		"max_length" => "128",
		"size" => "40"
		),
	"device_id" => array(
		"method" => "hidden_zero",
		"value" => "|arg1:device_id|"
		),
	"_device_id" => array(
		"method" => "hidden_zero",
		"value" => "|arg1:device_id|"
		),
	"save_component_device" => array(
		"method" => "hidden",
		"value" => "1"
		)
	);


	/* file: mactrack_snmp.php, action: item_edit */
	$fields_mactrack_snmp_item_edit = $fields_mactrack_snmp_item + array(
	"sequence" => array(
		'method' => 'view',
		'friendly_name' => 'Sequence',
		'description' => 'Sequence of Item.',
		'value' => '|arg1:sequence|'),
	);

	/* file: mactrack_sites.php, action: edit */
	$fields_mactrack_site_edit = array(
	"spacer0" => array(
		"method" => "spacer",
		"friendly_name" => "General Site Settings"
		),
	"site_name" => array(
		"method" => "textbox",
		"friendly_name" => "Site Name",
		"description" => "Please enter a reasonable name for this site.",
		"value" => "|arg1:site_name|",
		"size" => "70",
		"max_length" => "250"
		),
	"customer_contact" => array(
		"method" => "textbox",
		"friendly_name" => "Primary Customer Contact",
		"description" => "The principal customer contact name and number for this site.",
		"value" => "|arg1:customer_contact|",
		"size" => "70",
		"max_length" => "150"
		),
	"netops_contact" => array(
		"method" => "textbox",
		"friendly_name" => "NetOps Contact",
		"description" => "Please principal network support contact  name and number for this site.",
		"value" => "|arg1:netops_contact|",
		"size" => "70",
		"max_length" => "150"
		),
	"facilities_contact" => array(
		"method" => "textbox",
		"friendly_name" => "Facilities Contact",
		"description" => "Please principal facilities/security contact name and number for this site.",
		"value" => "|arg1:facilities_contact|",
		"size" => "70",
		"max_length" => "150"
		),
	"site_info" => array(
		"method" => "textarea",
		"friendly_name" => "Site Information",
		"class" => "textAreaNotes",
		"textarea_rows" => "3",
		"textarea_cols" => "80",
		"description" => "Provide any site specific information, in free form, that allows you to better manage this location.",
		"value" => "|arg1:site_info|",
		"max_length" => "255"
		),
	"site_id" => array(
		"method" => "hidden_zero",
		"value" => "|arg1:site_id|"
		),
	"_site_id" => array(
		"method" => "hidden_zero",
		"value" => "|arg1:site_id|"
		),
	"save_component_site" => array(
		"method" => "hidden",
		"value" => "1"
		)
	);

	/* file: mactrack_macwatch.php, action: edit */
	$fields_mactrack_macw_edit = array(
	"spacer0" => array(
		"method" => "spacer",
		"friendly_name" => "General Mac Address Tracking Settings"
		),
	"mac_address" => array(
		"method" => "textbox",
		"friendly_name" => "MAC Address",
		"description" => "Please enter the MAC Address to be watched for.",
		"value" => "|arg1:mac_address|",
		"default" => "",
		"max_length" => "40"
		),
	"name" => array(
		"method" => "textbox",
		"friendly_name" => "MAC Tracking Name/E-mail Subject",
		"description" => "Please enter a reasonable name for this MAC Tracking entry.  This information will be in the subject line of your e-mail",
		"value" => "|arg1:name|",
		"size" => "70",
		"max_length" => "250"
		),
	"description" => array(
		"friendly_name" => "MacWatch Default Body",
		"description" => htmlspecialchars("The e-mail body preset for MacTrack MacWatch e-mails.  The body can contain " .
			"any valid html tags.  It also supports replacement tags that will be processed when sending an e-mail.  " .
			"Valid tags include <IP>, <MAC>, <TICKET>, <SITENAME>, <DEVICEIP>, <PORTNAME>, <PORTNUMBER>, <DEVICENAME>."),
		"method" => "textarea",
		"class" => "textAreaNotes",
		"value" => "|arg1:description|",
		"default" => "Mac Address <MAC> found at IP Address <IP> for Ticket Number: <TICKET>.<br>" .
		"The device is located at<br>Site: <SITENAME>, Device <DEVICENAME>, IP <DEVICEIP>, Port <PORTNUMBER>, " .
		"and Port Name <PORTNAME>",
		"max_length" => "512",
		"textarea_rows" => "5",
		"textarea_cols" => "80",
		),
	"ticket_number" => array(
		"method" => "textbox",
		"friendly_name" => "Ticket Number",
		"description" => "Ticket number for cross referencing with your corporate help desk system(s).",
		"value" => "|arg1:ticket_number|",
		"size" => "70",
		"max_length" => "150"
		),
	"notify_schedule" => array(
		"method" => "drop_array",
		"friendly_name" => "Notification Schedule",
		"description" => "Choose how often an e-mail address should be generated for this Mac Watch item.",
		"value" => "|arg1:notify_schedule|",
		"default" => "1",
		"array" => array(
			1 => "First Occurance Only",
			2 => "All Occurances",
			60 => "Every Hour",
			240 => "Every 4 Hours",
			1800 => "Every 12 Hours",
			3600 => "Every Day")
		),
	"email_addresses" => array(
		"method" => "textbox",
		"friendly_name" => "E-mail Addresses",
		"description" => "Enter a semicolon separated of e-mail addresses who will be notified where this MAC address is.",
		"value" => "|arg1:email_addresses|",
		"size" => "90",
		"max_length" => "255"
		),
	"mac_id" => array(
		"method" => "hidden_zero",
		"value" => "|arg1:mac_id|"
		),
	"_mac_id" => array(
		"method" => "hidden_zero",
		"value" => "|arg1:mac_id|"
		),
	"save_component_macw" => array(
		"method" => "hidden",
		"value" => "1"
		)
	);

	/* file: mactrack_macwatch.php, action: edit */
	$fields_mactrack_maca_edit = array(
	"spacer0" => array(
		"method" => "spacer",
		"friendly_name" => "General Mac Address Authorization Settings"
		),
	"mac_address" => array(
		"method" => "textbox",
		"friendly_name" => "MAC Address Match",
		"description" => "Please enter the MAC Address or Mac Address Match string to be automatically authorized.  " .
		"If you wish to authorize a group of MAC Addresses, you can use the wildcard character of '%' anywhere in the " .
		"MAC Address.",
		"value" => "|arg1:mac_address|",
		"default" => "",
		"max_length" => "40"
		),
	"description" => array(
		"method" => "textarea",
		"friendly_name" => "Reason",
		"class" => "textAreaNotes",
		"description" => "Please add a reason for this entry.",
		"value" => "|arg1:description|",
		"textarea_rows" => "4",
		"textarea_cols" => "80"
		),
	"mac_id" => array(
		"method" => "hidden_zero",
		"value" => "|arg1:mac_id|"
		),
	"_mac_id" => array(
		"method" => "hidden_zero",
		"value" => "|arg1:mac_id|"
		),
	"save_component_maca" => array(
		"method" => "hidden",
		"value" => "1"
		)
	);
}

function convert_readstrings() {
	global $config;

	if (defined('CACTI_BASE_PATH')) {
		$config["base_path"] = CACTI_BASE_PATH;
	}

	include_once($config["base_path"] . "/lib/functions.php");

	$sql = "SELECT DISTINCT " .
			"snmp_readstrings, " .
			"snmp_version, " .
			"snmp_port, " .
			"snmp_timeout, " .
			"snmp_retries " .
			"FROM mac_track_devices";
	cacti_log($sql, false, "MACTRACK");
	$devices = db_fetch_assoc($sql);
	cacti_log(serialize($devices), false, "MACTRACK");

	if (sizeof($devices)) {
		$i = 0;
		foreach($devices as $device) {
			# create new SNMP Option Set
			unset($save);
			$save["id"] = 0;
			$save["name"] = "Custom_" . $i++;
			$snmp_id = sql_save($save, "mac_track_snmp");
			cacti_log("new option entry: " . $snmp_id, false, "MACTRACK");

			# add each single option derived from readstrings
			$read_strings = explode(":",$device["snmp_readstrings"]);
			if (sizeof($read_strings)) {
				foreach($read_strings as $snmp_readstring) {
					unset($save);
					$save["id"]						= 0;
					$save["snmp_id"] 				= $snmp_id;
					$save["sequence"] 				= get_sequence('', 'sequence', 'mac_track_snmp_items', 'snmp_id=' . $snmp_id);

					$save["snmp_readstring"] 		= $snmp_readstring;
					$save["snmp_version"] 			= $device["snmp_version"];
					$save["snmp_port"]				= $device["snmp_port"];
					$save["snmp_timeout"]			= $device["snmp_timeout"];
					$save["snmp_retries"]			= $device["snmp_retries"];
					$save["snmp_username"]			= "";
					$save["snmp_password"]			= "";
					$save["snmp_auth_protocol"]		= "";
					$save["snmp_priv_passphrase"]	= "";
					$save["snmp_priv_protocol"]		= "";
					$save["snmp_context"]			= "";
					$save["max_oids"]				= "";

					$item_id = sql_save($save, "mac_track_snmp_items");
					cacti_log("new option item entry: " . $item_id, false, "MACTRACK");
				}
			} # each readstring added as SNMP Option item

			# now, let's find all devices, that used this snmp_readstrings
			$sql = "UPDATE mac_track_devices SET snmp_options=" . $snmp_id .
					" WHERE snmp_readstrings='" . $device["snmp_readstrings"] .
					"' AND snmp_version=" . $device["snmp_version"] .
					" AND snmp_port=" . $device["snmp_port"] .
					" AND snmp_timeout=" . $device["snmp_timeout"] .
					" AND snmp_retries=" . $device["snmp_retries"];
			cacti_log($sql, false, "MACTRACK");
			$ok = db_execute($sql);
		}
	}
	db_execute("replace into settings (name,value) values ('mt_convert_readstrings', 'on')");
	# we keep the field:snmp_readstrings in mac_track_devices, it should be deprecated first
	# next mactrack release may delete that field, then
}
?>