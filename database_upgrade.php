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

/* do NOT run this script through a web browser */
if (!isset($_SERVER["argv"][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
   die("<br><strong>This script is only meant to run at the command line.</strong>");
}

$no_http_headers = true;
if (file_exists(dirname(__FILE__) . "/../../include/global.php")) {
	include(dirname(__FILE__) . "/../../include/global.php");
} else {
	include(dirname(__FILE__) . "/../../include/config.php");
}
include_once(dirname(__FILE__) . "/lib/mactrack_functions.php");

echo "This function is depricated.  Please goto Plugin Management from the WebUI to Upgrade\n";
exit(1);

/* this is only for old users.  We are not thinking about this now... */
if (0 == 1) {
	/* update first beta database to current standard */
	modify_column("mac_track_devices", "snmp_timeout", "ALTER TABLE `mac_track_devices` MODIFY COLUMN `snmp_timeout` INT(10) UNSIGNED NOT NULL DEFAULT 500;");
	modify_column("mac_track_devices", "snmp_retries", "ALTER TABLE `mac_track_devices` MODIFY COLUMN `snmp_retries` TINYINT(11) UNSIGNED NOT NULL DEFAULT 3;");

	add_column("mac_track_ports",      "dns_hostname", "ALTER TABLE `mac_track_ports` ADD COLUMN `dns_hostname` VARCHAR(200) DEFAULT '' AFTER `ip_address`;");
	add_column("mac_track_temp_ports", "dns_hostname", "ALTER TABLE `mac_track_temp_ports` ADD COLUMN `dns_hostname` VARCHAR(200) DEFAULT '' AFTER `ip_address`;");
	add_column("mac_track_ips",        "dns_hostname", "ALTER TABLE `mac_track_ips` ADD COLUMN `dns_hostname` VARCHAR(200) DEFAULT '' AFTER `ip_address`;");

	add_column("mac_track_devices", "snmp_port",        "ALTER TABLE `mac_track_devices` ADD COLUMN `snmp_port` INT(10) UNSIGNED NOT NULL DEFAULT '161' AFTER `snmp_version`;");
	add_column("mac_track_devices", "macs_active",      "ALTER TABLE `mac_track_devices` ADD COLUMN `macs_active` INT(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `ports_trunk`;");
	add_column("mac_track_devices", "snmp_sysName",     "ALTER TABLE `mac_track_devices` ADD COLUMN `snmp_sysName` VARCHAR(100) DEFAULT '' AFTER `snmp_retries`;");
	add_column("mac_track_devices", "snmp_sysLocation", "ALTER TABLE `mac_track_devices` ADD COLUMN `snmp_sysLocation` VARCHAR(100) DEFAULT '' AFTER `snmp_sysName`;");
	add_column("mac_track_devices", "snmp_sysContact",  "ALTER TABLE `mac_track_devices` ADD COLUMN `snmp_sysContact` VARCHAR(100) DEFAULT '' AFTER `snmp_sysLocation`;");

	create_table("mac_track_scanning_functions", "CREATE TABLE `mac_track_scanning_functions` (`scanning_function` VARCHAR(100) NOT NULL DEFAULT '', `description` VARCHAR(200) NOT NULL DEFAULT '', PRIMARY KEY(`scanning_function`));");

	execute_sql("Change Primary Key For 'mac_track_devices'", "ALTER TABLE `mac_track_devices` DROP PRIMARY KEY, ADD PRIMARY KEY(`hostname`, `snmp_port`, `site_id`);");

	add_index("mac_track_devices", "device_id", "ALTER TABLE `mac_track_devices` ADD INDEX `device_id`(`device_id`);");

	add_column("mac_track_sites", "total_oper_ports", "ALTER TABLE `mac_track_sites` ADD COLUMN `total_oper_ports` INT(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `total_user_ports`;");

	execute_sql("Change Primary Key For 'mac_track_device_types'", "ALTER TABLE `mac_track_device_types` DROP PRIMARY KEY, ADD PRIMARY KEY(`sysDescr_match`, `sysObjectID_match`, `device_type`);");

	add_index("mac_track_device_types", "device_type_id", "ALTER TABLE `mac_track_device_types` ADD INDEX `device_type_id`(`device_type_id`);");

	modify_column("mac_track_scanning_functions", "scanning_function", "ALTER TABLE `mac_track_scanning_functions` MODIFY COLUMN `scanning_function` VARCHAR(100) NOT NULL DEFAULT '';");
	modify_column("mac_track_scanning_functions", "description",       "ALTER TABLE `mac_track_scanning_functions` MODIFY COLUMN `description` VARCHAR(200) NOT NULL DEFAULT '';");

	create_table("mac_track_scan_dates",  "CREATE TABLE `mac_track_scan_dates` (`scan_date` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00', PRIMARY KEY(`scan_date`));");
	execute_sql("Addition of Scan Dates", "REPLACE INTO `mac_track_scan_dates` (SELECT DISTINCT scan_date from mac_track_ports);");

	add_index("mac_track_devices",  "snmp_sysDescr",    "ALTER TABLE `mac_track_devices` ADD INDEX `snmp_sysDescr`(`snmp_sysDescr`);");
	add_index("mac_track_devices",  "snmp_sysObjectID", "ALTER TABLE `mac_track_devices` ADD INDEX `snmp_sysObjectID`(`snmp_sysObjectID`);");
	add_column("mac_track_devices", "device_type_id",   "ALTER TABLE `mac_track_devices` ADD COLUMN `device_type_id` INT(10) UNSIGNED DEFAULT 0 AFTER `device_id`;");
	add_index("mac_track_devices",  "device_type_id",   "ALTER TABLE `mac_track_devices` ADD INDEX `device_type_id`(`device_type_id`);");

	add_index("mac_track_ports", "port_name",    "ALTER TABLE `mac_track_ports` ADD INDEX `port_name`(`port_name`);");
	add_index("mac_track_ports", "dns_hostname", "ALTER TABLE `mac_track_ports` ADD INDEX `dns_hostname`(`dns_hostname`);");

	modify_column("mac_track_devices", "ips_total",    "ALTER TABLE `mac_track_devices` MODIFY COLUMN `ips_total` INTEGER UNSIGNED NOT NULL DEFAULT 0");
	modify_column("mac_track_devices", "vlans_total",  "ALTER TABLE `mac_track_devices` MODIFY COLUMN `vlans_total` INTEGER UNSIGNED NOT NULL DEFAULT 0");
	modify_column("mac_track_devices", "ports_total",  "ALTER TABLE `mac_track_devices` MODIFY COLUMN `ports_total` INTEGER UNSIGNED NOT NULL DEFAULT 0");
	modify_column("mac_track_devices", "ports_active", "ALTER TABLE `mac_track_devices` MODIFY COLUMN `ports_active` INTEGER UNSIGNED NOT NULL DEFAULT 0");
	modify_column("mac_track_devices", "ports_trunk",  "ALTER TABLE `mac_track_devices` MODIFY COLUMN `ports_trunk` INTEGER UNSIGNED NOT NULL DEFAULT 0");
	modify_column("mac_track_devices", "macs_active",  "ALTER TABLE `mac_track_devices` MODIFY COLUMN `macs_active` INTEGER UNSIGNED NOT NULL DEFAULT 0");

	add_column("mac_track_temp_ports", "updated",    "ALTER TABLE `mac_track_temp_ports` ADD COLUMN `updated` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `scan_date`;");
	add_index("mac_track_temp_ports",  "updated",    "ALTER TABLE `mac_track_temp_ports` ADD INDEX `updated`(`updated`);");
	add_index("mac_track_temp_ports",  "ip_address", "ALTER TABLE `mac_track_temp_ports` ADD INDEX `ip_address`(`ip_address`);");

	create_table("mac_track_ip_ranges", "CREATE TABLE `mac_track_ip_ranges` (`ip_range` VARCHAR(20) NOT NULL, `site_id` INTEGER UNSIGNED NOT NULL, `ips_max` INTEGER UNSIGNED NOT NULL, `ips_current` INTEGER UNSIGNED NOT NULL, PRIMARY KEY(`ip_range`, `site_id`), INDEX `site_id`(`site_id`))");

	add_column("mac_track_ip_ranges", "ips_max_date",     "ALTER TABLE `mac_track_ip_ranges` ADD COLUMN `ips_max_date` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00' AFTER `ips_current`;");
	add_column("mac_track_ip_ranges", "ips_current_date", "ALTER TABLE `mac_track_ip_ranges` ADD COLUMN `ips_current_date` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00' AFTER `ips_max_date`;");

	add_column("mac_track_processes", "device_id",        "ALTER TABLE `mac_track_processes` CHANGE COLUMN `process_id` `device_id` INTEGER NOT NULL DEFAULT 0;");
	add_column("mac_track_processes",    "process_id",       "ALTER TABLE `mac_track_processes` ADD COLUMN `process_id` INTEGER UNSIGNED AFTER `device_id`;");
	modify_column("mac_track_devices",   "snmp_readstring",  "ALTER TABLE `mac_track_devices` MODIFY COLUMN `snmp_readstring` VARCHAR(30) NOT NULL DEFAULT ''");
	modify_column("mac_track_devices",   "snmp_readstrings", "ALTER TABLE `mac_track_devices` MODIFY COLUMN `snmp_readstrings` VARCHAR(150) DEFAULT ''");

	execute_sql("Change mac_track_temp_ports to Memory Table", "ALTER TABLE `mac_track_temp_ports` TYPE = HEAP");
	execute_sql("Change mac_track_ips to Memory Table", "ALTER TABLE `mac_track_ips` TYPE = HEAP");

	/* new for release 1.0 */
	create_table("mac_track_approved_macs", "CREATE TABLE `mac_track_approved_macs` (`mac_prefix` VARCHAR(20) NOT NULL, `vendor` VARCHAR(50) NOT NULL, `description` VARCHAR(255) NOT NULL, PRIMARY KEY(`mac_prefix`)) TYPE = MyISAM;");

	modify_column("mac_track_devices", "ignorePorts", "ALTER TABLE `mac_track_devices` MODIFY COLUMN `ignorePorts` VARCHAR(255), TYPE = MyISAM;");
	modify_column("mac_track_devices", "snmp_readstring", "ALTER TABLE `mac_track_devices` MODIFY COLUMN `snmp_readstring` VARCHAR(100) NOT NULL, TYPE = MyISAM;");
	modify_column("mac_track_devices", "snmp_readstrings", "ALTER TABLE `mac_track_devices` MODIFY COLUMN `snmp_readstrings` VARCHAR(255) DEFAULT NULL, TYPE = MyISAM;");

	create_table("mac_track_oui_database", "CREATE TABLE `mac_track_oui_database` (`vendor_mac` varchar(8) NOT NULL,`vendor_name` varchar(100) NOT NULL,`vendor_address` text NOT NULL,`present` tinyint(3) unsigned NOT NULL default '1', PRIMARY KEY  (`vendor_mac`), KEY `vendor_name` (`vendor_name`)) TYPE=MyISAM");

	add_column("mac_track_ports", "vendor_mac", "ALTER TABLE `mac_track_ports` ADD COLUMN `vendor_mac` VARCHAR(8) NULL AFTER `mac_address`, TYPE = MyISAM;");
	add_index("mac_track_ports",  "vendor_mac", "ALTER TABLE `mac_track_ports` ADD INDEX `vendor_mac`(`vendor_mac`), TYPE = MyISAM;");

	add_column("mac_track_temp_ports", "vendor_mac", "ALTER TABLE `mac_track_temp_ports` ADD COLUMN `vendor_mac` VARCHAR(8) NULL AFTER `mac_address`, TYPE = HEAP;");
	add_index("mac_track_temp_ports",  "vendor_mac", "ALTER TABLE `mac_track_temp_ports` ADD INDEX `vendor_mac`(`vendor_mac`), TYPE = HEAP;");

	execute_sql("Add Vendor Macs To 'mac_track_ports'",      "UPDATE mac_track_ports SET vendor_mac=SUBSTRING(mac_address,1,8) WHERE vendor_mac='' OR vendor_mac IS NULL;");
	execute_sql("Add Vendor Macs To 'mac_track_temp_ports'", "UPDATE mac_track_temp_ports SET vendor_mac=SUBSTRING(mac_address,1,8) WHERE vendor_mac='' OR vendor_mac IS NULL;");

	add_column("mac_track_temp_ports", "authorized", "ALTER TABLE `mac_track_temp_ports` ADD COLUMN `authorized` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `updated`, TYPE = HEAP;");
	add_index("mac_track_temp_ports",  "authorized", "ALTER TABLE `mac_track_temp_ports` ADD INDEX `authorized`(`authorized`), TYPE = HEAP;");

	add_column("mac_track_ports", "authorized", "ALTER TABLE `mac_track_ports` ADD COLUMN `authorized` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `scan_date`, TYPE = MyISAM;");
	add_index("mac_track_ports",  "authorized", "ALTER TABLE `mac_track_ports` ADD INDEX `authorized`(`authorized`), TYPE = MyISAM;");

	create_table("mac_track_macwatch", "CREATE TABLE `mac_track_macwatch` ( `mac_address` varchar(20) NOT NULL, `name` varchar(45) NOT NULL, `description` varchar(255) NOT NULL, `ticket_number` varchar(45) NOT NULL, `notify_schedule` tinyint(3) unsigned NOT NULL, `email_addresses` varchar(255) NOT NULL DEFAULT '', `discovered` tinyint(3) unsigned NOT NULL, `date_first_seen` timestamp NOT NULL default '0000-00-00 00:00:00', `data_last_seen` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP, PRIMARY KEY  (`mac_address`)) TYPE=MyISAM;");
	create_table("mac_track_macauth", "CREATE TABLE `mac_track_macauth` (`mac_address` varchar(20) NOT NULL, `description` varchar(100) NOT NULL, `added_date` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP, `added_by` varchar(20) NOT NULL, PRIMARY KEY  (`mac_address`)) TYPE=MyISAM;");
	create_table("mac_track_vlans", "CREATE TABLE  `mac_track_vlans` (`vlan_id` int(10) unsigned NOT NULL,`site_id` int(10) unsigned NOT NULL,`device_id` int(10) unsigned NOT NULL,`vlan_name` varchar(128) NOT NULL,`present` tinyint(3) unsigned NOT NULL default '1', PRIMARY KEY  (`vlan_id`,`site_id`,`device_id`), KEY `vlan_name` (`vlan_name`)) TYPE=MyISAM");

	execute_sql("Add VLANS to VLAN Table", "REPLACE INTO `mac_track_vlans` (vlan_id, site_id, device_id, vlan_name) SELECT DISTINCT vlan_id, site_id, device_id, vlan_name FROM mac_track_ports");

	modify_column("mac_track_devices", "description",   "ALTER TABLE `mac_track_devices` MODIFY COLUMN `description` TEXT DEFAULT NULL, TYPE = MyISAM;");
	add_column("mac_track_devices",    "device_name",   "ALTER TABLE `mac_track_devices` ADD COLUMN `device_name` VARCHAR(100) DEFAULT '' AFTER `device_id`, TYPE = MyISAM;");
	add_index("mac_track_devices",     "device_name",   "ALTER TABLE `mac_track_devices` ADD INDEX `device_name`(`device_name`), TYPE = MyISAM;");

	add_column("mac_track_sites", "customer_contact",   "ALTER TABLE `mac_track_sites` ADD COLUMN `customer_contact` VARCHAR(150) DEFAULT '' AFTER `site_name`, TYPE = MyISAM;");
	add_column("mac_track_sites", "netops_contact",     "ALTER TABLE `mac_track_sites` ADD COLUMN `netops_contact` VARCHAR(150) DEFAULT '' AFTER `customer_contact`, TYPE = MyISAM;");
	add_column("mac_track_sites", "facilities_contact", "ALTER TABLE `mac_track_sites` ADD COLUMN `facilities_contact` VARCHAR(150) DEFAULT '' AFTER `netops_contact`, TYPE = MyISAM;");
	add_column("mac_track_sites", "site_info",          "ALTER TABLE `mac_track_sites` ADD COLUMN `site_info` TEXT DEFAULT '' AFTER `facilities_contact`, TYPE = MyISAM;");

	add_column("mac_track_device_types", "serial_number_oid", "ALTER TABLE `mac_track_device_types` ADD COLUMN `serial_number_oid` VARCHAR(100) DEFAULT '' AFTER `scanning_function`, TYPE = MyISAM;");

	execute_sql("Move Device Names from the 'description' field to the 'device_name' field.", "UPDATE mac_track_devices SET device_name=description WHERE device_name IS NULL OR device_name=''");
	execute_sql("Blank out the 'description' field as it will now be used for something else", "UPDATE mac_track_devices SET description=''");

	add_column("mac_track_macwatch",   "email_addresses", "ALTER TABLE mac_track_macwatch CHANGE COLUMN `e-mail_addresses` `email_addresses` varchar(255) NOT NULL DEFAULT ''");
	add_column("mac_track_macwatch",   "mac_id",          "ALTER TABLE `mac_track_macwatch` ADD COLUMN `mac_id` INTEGER UNSIGNED NOT NULL DEFAULT NULL AUTO_INCREMENT AFTER `mac_address`, ADD INDEX `mac_id`(`mac_id`), TYPE=MyISAM;");
	add_column("mac_track_macwatch",   "date_last_seen",  "ALTER TABLE `mac_track_macwatch` CHANGE COLUMN `date_last_seen` `date_last_seen` TIMESTAMP DEFAULT '0000-00-00 00:00:00', ENGINE = MyISAM;");
	add_column("mac_track_macwatch",   "date_last_notif", "ALTER TABLE `mac_track_macwatch` ADD COLUMN `date_last_notif` TIMESTAMP DEFAULT '0000-00-00 00:00:00' AFTER `date_last_seen` , ENGINE = MyISAM;");
	add_column("mac_track_macauth",    "mac_id",          "ALTER TABLE `mac_track_macauth` ADD COLUMN `mac_id` INTEGER UNSIGNED NOT NULL DEFAULT NULL AUTO_INCREMENT AFTER `mac_address`, ADD INDEX `mac_id`(`mac_id`), TYPE=MyISAM;");
	add_column("mac_track_ports",      "device_name",     "ALTER TABLE `mac_track_ports` CHANGE COLUMN `description` `device_name` VARCHAR(100) NOT NULL default '', TYPE=MyISAM;");
	add_column("mac_track_temp_ports", "device_name",     "ALTER TABLE `mac_track_temp_ports` CHANGE COLUMN `description` `device_name` VARCHAR(100) NOT NULL default '', TYPE=MyISAM;");
	add_column("mac_track_devices",    "notes",           "ALTER TABLE `mac_track_devices` CHANGE COLUMN `description` `notes` TEXT default NULL, TYPE=MyISAM;");
	modify_column("mac_track_ips",     "description",     "ALTER TABLE `mac_track_ips` CHANGE COLUMN `description` `device_name` VARCHAR(100) NOT NULL default '', TYPE=MyISAM;");
	delete_column("mac_track_devices", "serial_number",   "ALTER TABLE `mac_track_devices` DROP COLUMN `serial_number`, TYPE=MyISAM;");
	delete_column("mac_track_devices", "asset_id",        "ALTER TABLE `mac_track_devices` DROP COLUMN `asset_id`, TYPE=MyISAM;");

	create_table("mac_track_interfaces", "CREATE TABLE  `mac_track_interfaces` (`site_id` int(10) unsigned NOT NULL default '0',
		`device_id` int(10) unsigned NOT NULL default '0', `ifIndex` int(10) unsigned NOT NULL default '0', `ifName` varchar(128) NOT NULL,
		`ifAlias` varchar(255) NOT NULL, `ifDescr` varchar(128) NOT NULL, `ifType` int(10) unsigned NOT NULL default '0',
		`ifMtu` int(10) unsigned NOT NULL default '0', `ifSpeed` int(10) unsigned NOT NULL default '0', `ifPhysAddress` varchar(20) NOT NULL,
		`ifAdminStatus` int(10) unsigned NOT NULL default '0', `ifOperStatus` int(10) unsigned NOT NULL default '0', `ifLastChange` int(10) unsigned NOT NULL default '0',
		`linkPort` tinyint(3) unsigned NOT NULL default '0', `vlan_id` int(10) unsigned NOT NULL, `vlan_name` varchar(128) NOT NULL,
		`vlan_trunk_status` int(10) unsigned NOT NULL, `ifInDiscards` int(10) unsigned NOT NULL default '0', `ifInErrors` int(10) unsigned NOT NULL default '0',
		`ifInUnknownProtos` int(10) unsigned NOT NULL default '0', `ifOutDiscards` int(10) unsigned default '0', `ifOutErrors` int(10) unsigned default '0',
		`last_up_time` timestamp NOT NULL default '0000-00-00 00:00:00', `last_down_time` timestamp NOT NULL default '0000-00-00 00:00:00', `stateChanges` int(10) unsigned NOT NULL default '0',
		`int_discards_present` tinyint(3) unsigned NOT NULL default '0', `int_errors_present` tinyint(3) unsigned NOT NULL default '0', `present` tinyint(3) unsigned NOT NULL default '0',
		PRIMARY KEY  (`site_id`,`device_id`,`ifIndex`), KEY `ifDescr` (`ifDescr`), KEY `ifType` (`ifType`), KEY `ifSpeed` (`ifSpeed`), KEY `ifMTU` (`ifMtu`),
		KEY `ifAdminStatus` (`ifAdminStatus`), KEY `ifOperStatus` (`ifOperStatus`), KEY `ifInDiscards` USING BTREE (`ifInUnknownProtos`),
		KEY `ifInErrors` USING BTREE (`ifInUnknownProtos`)) TYPE=MyISAM;");

	add_column("mac_track_scanning_functions", "type", "ALTER TABLE `mac_track_scanning_functions` ADD COLUMN `type` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `scanning_function`, DROP PRIMARY KEY, ADD PRIMARY KEY(`scanning_function`, `type`), TYPE=MyISAM;");
	add_column("mac_track_device_types", "ip_scanning_function", "ALTER TABLE `mac_track_device_types` ADD COLUMN `ip_scanning_function` VARCHAR(100) NOT NULL AFTER `scanning_function`, TYPE=MyISAM;");
	execute_sql("Update the Scanning Function Type to 'Mac' for undefined types", "UPDATE mac_track_scanning_functions SET type='1' WHERE type='0'");
	execute_sql("Set the IP Scanning function to N/A for Device Type 1", "UPDATE mac_track_device_types SET ip_scanning_function='Not Applicable - Switch/Hub' WHERE device_type=1 AND (ip_scanning_function='' OR ip_scanning_function IS NULL)");
	execute_sql("Set the IP Scanning function to 'get_standard_arp_table' for Routers and L3 Switches", "UPDATE mac_track_device_types SET ip_scanning_function='get_standard_arp_table' WHERE device_type>1 AND (ip_scanning_function='' OR ip_scanning_function IS NULL)");
	add_column("mac_track_interfaces", "vlan_trunk", "ALTER TABLE `mac_track_interfaces` ADD COLUMN `vlan_trunk` TINYINT UNSIGNED NOT NULL AFTER `vlan_name`, TYPE=MyISAM;");
	add_column("mac_track_devices", "user_name", "ALTER TABLE `mac_track_devices` ADD COLUMN `user_name` VARCHAR(40) NULL DEFAULT NULL AFTER `scan_type`, TYPE=MyISAM;");
	add_column("mac_track_devices", "user_password", "ALTER TABLE `mac_track_devices` ADD COLUMN `user_password` VARCHAR(40) NULL DEFAULT NULL AFTER `user_name`, TYPE=MyISAM;");

	/* update all known device device_types */
	print "\nUpdating Device Types in Devices Table.  Please be patient.\n";
	$devices      = db_fetch_assoc("SELECT * FROM mac_track_devices");
	$device_types = db_fetch_assoc("SELECT * FROM mac_track_device_types");
	$i = 0;
	$good_device_types = 0;
	$bad_device_types  = 0;
	foreach ($devices as $device) {
		$device_type = find_scanning_function($device, $device_types);
		if (sizeof($device_type) > 0) {
			db_execute("UPDATE mac_track_devices SET device_type_id='" . $device_type["device_type_id"] . "' WHERE device_id='" . $device["device_id"] . "'");
			$good_device_types++;
		}else{
			$bad_device_types++;
		}

		$i++;
		if (($i % 10) == 0) echo ".";
	}

	echo "\n\nDevice Types Updated, You have '$good_device_types' Good Device Type Mapping and '$bad_device_types' Bad Device Type Mapping.\nIf the Bad Device type mapping is greater than '0', you should inspect your devices for unmapped device types.\n";

	/* import the vendor mac database */
	echo "\nImporting the Vendor MAC Address Table from the IEEE\n\n";
	passthru(read_config_option("path_php_binary") . " -q " . read_config_option("path_webroot") . "/plugins/mactrack/mactrack_import_ouidb.php");
}

/* start 2.0 Changes */
add_column("mac_track_interfaces", "sysUptime",             "ALTER TABLE `mac_track_interfaces` ADD COLUMN `sysUptime` int(10) unsigned NOT NULL default '0' AFTER `device_id`");
add_column("mac_track_interfaces", "ifHighSpeed",           "ALTER TABLE `mac_track_interfaces` ADD COLUMN `ifHighSpeed` int(10) unsigned NOT NULL default '0' AFTER `ifSpeed`");
add_column("mac_track_interfaces", "ifDuplex",              "ALTER TABLE `mac_track_interfaces` ADD COLUMN `ifDuplex` int(10) unsigned NOT NULL default '0' AFTER `ifHighSpeed`");
add_column("mac_track_interfaces", "int_ifInDiscards",      "ALTER TABLE `mac_track_interfaces` ADD COLUMN `int_ifInDiscards` float unsigned NOT NULL default '0' AFTER `ifOutErrors`");
add_column("mac_track_interfaces", "int_ifInErrors",        "ALTER TABLE `mac_track_interfaces` ADD COLUMN `int_ifInErrors` float unsigned NOT NULL default '0' AFTER `int_ifInDiscards`");
add_column("mac_track_interfaces", "int_ifInUnknownProtos", "ALTER TABLE `mac_track_interfaces` ADD COLUMN `int_ifInUnknownProtos` float unsigned NOT NULL default '0' AFTER `int_ifInErrors`");
add_column("mac_track_interfaces", "int_ifOutDiscards",     "ALTER TABLE `mac_track_interfaces` ADD COLUMN `int_ifOutDiscards` float unsigned NOT NULL default '0' AFTER `int_ifInUnknownProtos`");
add_column("mac_track_interfaces", "int_ifOutErrors",       "ALTER TABLE `mac_track_interfaces` ADD COLUMN `int_ifOutErrors` float unsigned NOT NULL default '0' AFTER `int_ifOutDiscards`");
add_column("mac_track_devices",    "host_id",               "ALTER TABLE `mac_track_devices` ADD COLUMN `host_id` int(10) unsigned NOT NULL default '0' AFTER `device_id`");
execute_sql("Speed up queries", "ALTER TABLE `mac_track_ports` ADD INDEX `scan_date` USING BTREE(`scan_date`)");
execute_sql("Add length to Device Types Match Fields", "ALTER TABLE `mac_track_device_types` MODIFY COLUMN `sysDescr_match` VARCHAR(100) NOT NULL default '', MODIFY COLUMN `sysObjectID_match` VARCHAR(100) NOT NULL default ''");
execute_sql("Correct a Scanning Function Bug", "DELETE FROM mac_track_scanning_functions WHERE scanning_function='Not Applicable - Hub/Switch'");

/* start Aggregated Changes */
create_table("mac_track_aggregated_ports", "CREATE TABLE mac_track_aggregated_ports (
	`row_id` int(10) unsigned NOT NULL auto_increment,
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
	`date_last` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
	`first_scan_date` datetime NOT NULL default '0000-00-00 00:00:00',
	`count_rec` int(10) unsigned NOT NULL default '0',
	`active_last` tinyint(1) unsigned NOT NULL default '0',
	`authorized` tinyint(3) unsigned NOT NULL default '0',
	PRIMARY KEY  (`row_id`),
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
	KEY `site_id_device_id` (`site_id`,`device_id`))
	ENGINE=MyISAM COMMENT='Database for aggregated date for Tracking Device MAC''s';");

# new for 2.1.2
# SNMP V3
add_column("mac_track_devices",    "snmp_options",         "ALTER TABLE `mac_track_devices` ADD COLUMN `snmp_options` int(10) unsigned NOT NULL default '0' AFTER `user_password`");
add_column("mac_track_devices",    "snmp_username",        "ALTER TABLE `mac_track_devices` ADD COLUMN `snmp_username` varchar(50) default NULL AFTER `snmp_status`");
add_column("mac_track_devices",    "snmp_password",        "ALTER TABLE `mac_track_devices` ADD COLUMN `snmp_password` varchar(50) default NULL AFTER `snmp_username`");
add_column("mac_track_devices",    "snmp_auth_protocol",   "ALTER TABLE `mac_track_devices` ADD COLUMN `snmp_auth_protocol` char(5) default '' AFTER `snmp_password`");
add_column("mac_track_devices",    "snmp_priv_passphrase", "ALTER TABLE `mac_track_devices` ADD COLUMN `snmp_priv_passphrase` varchar(200) default '' AFTER `snmp_auth_protocol`");
add_column("mac_track_devices",    "snmp_priv_protocol",   "ALTER TABLE `mac_track_devices` ADD COLUMN `snmp_priv_protocol` char(6) default '' AFTER `snmp_priv_passphrase`");
add_column("mac_track_devices",    "snmp_context",         "ALTER TABLE `mac_track_devices` ADD COLUMN `snmp_context` varchar(64) default '' AFTER `snmp_priv_protocol`");
add_column("mac_track_devices",    "max_oids",             "ALTER TABLE `mac_track_devices` ADD COLUMN `max_oids` int(12) unsigned default '10' AFTER `snmp_context`");

create_table("mac_track_snmp", "CREATE TABLE `mac_track_snmp` (
			`id` int(10) unsigned NOT NULL auto_increment,
			`name` varchar(100) NOT NULL default '',
			PRIMARY KEY  (`id`))
			ENGINE=MyISAM COMMENT='Group of SNMP Option Sets';");

create_table("mac_track_snmp_items", "CREATE TABLE `mac_track_snmp_items` (
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

echo "\nDatabase Upgrade Complete\n";

function execute_sql($message, $syntax) {
	$result = db_execute($syntax);

	if ($result) {
		echo "SUCCESS: Execute SQL,   $message, Ok\n";
	}else{
		echo "ERROR: Execute SQL,   $message, Failed!\n";
	}
}

function create_table($table, $syntax) {
	$tables = db_fetch_assoc("SHOW TABLES LIKE '$table'");

	if (!sizeof($tables)) {
		$result = db_execute($syntax);
		if ($result) {
			echo "SUCCESS: Create Table,  Table -> $table, Ok\n";
		}else{
			echo "ERROR: Create Table,  Table -> $table, Failed!\n";
		}
	}else{
		echo "SUCCESS: Create Table,  Table -> $table, Already Exists!\n";
	}
}

function add_column($table, $column, $syntax) {
	$columns = db_fetch_assoc("SHOW COLUMNS FROM $table LIKE '$column'");

	if (sizeof($columns)) {
		echo "SUCCESS: Add Column,    Table -> $table, Column -> $column, Already Exists!\n";
	}else{
		$result = db_execute($syntax);

		if ($result) {
			echo "SUCCESS: Add Column,    Table -> $table, Column -> $column, Ok\n";
		}else{
			echo "ERROR: Add Column,    Table -> $table, Column -> $column, Failed!\n";
		}
	}
}

function add_index($table, $index, $syntax) {
	$tables = db_fetch_assoc("SHOW TABLES LIKE '$table'");

	if (sizeof($tables)) {
		$indexes = db_fetch_assoc("SHOW INDEXES FROM $table");

		$index_exists = FALSE;
		if (sizeof($indexes)) {
			foreach($indexes as $index_array) {
				if ($index == $index_array["Key_name"]) {
					$index_exists = TRUE;
					break;
				}
			}
		}

		if ($index_exists) {
			echo "SUCCESS: Add Index,     Table -> $table, Index -> $index, Already Exists!\n";
		}else{
			$result = db_execute($syntax);

			if ($result) {
				echo "SUCCESS: Add Index,     Table -> $table, Index -> $index, Ok\n";
			}else{
				echo "ERROR: Add Index,     Table -> $table, Index -> $index, Failed!\n";
			}
		}
	}else{
		echo "ERROR: Add Index,     Table -> $table, Index -> $index, Failed, Table Does NOT Exist!\n";
	}
}

function modify_column($table, $column, $syntax) {
	$tables = db_fetch_assoc("SHOW TABLES LIKE '$table'");

	if (sizeof($tables)) {
		$columns = db_fetch_assoc("SHOW COLUMNS FROM $table LIKE '$column'");

		if (sizeof($columns)) {
			$result = db_execute($syntax);

			if ($result) {
				echo "SUCCESS: Modify Column, Table -> $table, Column -> $column, Ok\n";
			}else{
				echo "ERROR: Modify Column, Table -> $table, Column -> $column, Failed!\n";
			}
		}else{
			echo "ERROR: Modify Column, Table -> $table, Column -> $column, Column Does NOT Exist!\n";
		}
	}else{
		echo "ERROR: Modify Column, Table -> $table, Column -> $column, Table Does NOT Exist!\n";
	}
}

function delete_column($table, $column, $syntax) {
	$tables = db_fetch_assoc("SHOW TABLES LIKE '$table'");

	if (sizeof($tables)) {
		$columns = db_fetch_assoc("SHOW COLUMNS FROM $table LIKE '$column'");

		if (sizeof($columns)) {
			$result = db_execute($syntax);

			if ($result) {
				echo "SUCCESS: Delete Column, Table -> $table, Column -> $column, Ok\n";
			}else{
				echo "ERROR: Delete Column, Table -> $table, Column -> $column, Failed!\n";
			}
		}else{
			echo "SUCCESS: Delete Column, Table -> $table, Column -> $column, Column Does NOT Exist!\n";
		}
	}else{
		echo "SUCCESS: Delete Column, Table -> $table, Column -> $column, Table Does NOT Exist!\n";
	}
}

?>
