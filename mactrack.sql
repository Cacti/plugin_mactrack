--
-- Table structure for table `mac_track_approved_macs`
--

CREATE TABLE `mac_track_approved_macs` (
  `mac_prefix` varchar(20) NOT NULL,
  `vendor` varchar(50) NOT NULL,
  `description` varchar(255) NOT NULL,
  PRIMARY KEY  (`mac_prefix`)
) TYPE=MyISAM;

--
-- Table structure for table `mac_track_device_types`
--

CREATE TABLE `mac_track_device_types` (
  `device_type_id` int(10) unsigned NOT NULL auto_increment,
  `description` varchar(100) NOT NULL default '',
  `vendor` varchar(40) NOT NULL default '',
  `device_type` varchar(10) NOT NULL default '0',
  `sysDescr_match` varchar(20) NOT NULL default '',
  `sysObjectID_match` varchar(40) NOT NULL default '',
  `scanning_function` varchar(100) NOT NULL default '',
  `lowPort` int(10) unsigned NOT NULL default '0',
  `highPort` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (`sysDescr_match`,`sysObjectID_match`,`device_type`),
  KEY `device_type` (`device_type`),
  KEY `device_type_id` (`device_type_id`)
) TYPE=MyISAM;

--
-- Table structure for table `mac_track_devices`
--

CREATE TABLE `mac_track_devices` (
  `site_id` int(10) unsigned NOT NULL default '0',
  `device_id` int(10) unsigned NOT NULL auto_increment,
  `device_type_id` int(10) unsigned default '0',
  `hostname` varchar(40) NOT NULL default '',
  `description` varchar(100) NOT NULL default '',
  `disabled` char(2) default '',
  `ignorePorts` varchar(255) default NULL,
  `ips_total` int(10) unsigned NOT NULL default '0',
  `vlans_total` int(10) unsigned NOT NULL default '0',
  `ports_total` int(10) unsigned NOT NULL default '0',
  `ports_active` int(10) unsigned NOT NULL default '0',
  `ports_trunk` int(10) unsigned NOT NULL default '0',
  `macs_active` int(10) unsigned NOT NULL default '0',
  `scan_type` tinyint(11) NOT NULL default '1',
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
  `last_runmessage` varchar(100) default '',
  `last_rundate` datetime NOT NULL default '0000-00-00 00:00:00',
  `last_runduration` decimal(10,5) NOT NULL default '0.00000',
  PRIMARY KEY  (`hostname`,`snmp_port`,`site_id`),
  KEY `site_id` (`site_id`),
  KEY `device_id` (`device_id`),
  KEY `snmp_sysDescr` (`snmp_sysDescr`),
  KEY `snmp_sysObjectID` (`snmp_sysObjectID`),
  KEY `device_type_id` (`device_type_id`)
) TYPE=MyISAM COMMENT='Devices to be scanned for MAC addresses';

--
-- Table structure for table `mac_track_ip_ranges`
--

CREATE TABLE `mac_track_ip_ranges` (
  `ip_range` varchar(20) NOT NULL default '',
  `site_id` int(10) unsigned NOT NULL default '0',
  `ips_max` int(10) unsigned NOT NULL default '0',
  `ips_current` int(10) unsigned NOT NULL default '0',
  `ips_max_date` datetime NOT NULL default '0000-00-00 00:00:00',
  `ips_current_date` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`ip_range`,`site_id`),
  KEY `site_id` (`site_id`)
) TYPE=MyISAM;

--
-- Table structure for table `mac_track_ips`
--

CREATE TABLE `mac_track_ips` (
  `site_id` int(10) unsigned NOT NULL default '0',
  `device_id` int(10) unsigned NOT NULL default '0',
  `hostname` varchar(40) NOT NULL default '',
  `description` varchar(100) NOT NULL default '',
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
  KEY `scan_date` (`scan_date`)
) TYPE=MEMORY;

--
-- Table structure for table `mac_track_macauth`
--

CREATE TABLE `mac_track_macauth` (
  `mac_address` varchar(20) NOT NULL,
  `description` varchar(100) NOT NULL,
  `added_date` timestamp NOT NULL,
  `added_by` varchar(20) NOT NULL,
  PRIMARY KEY  (`mac_address`)
) TYPE=InnoDB;

--
-- Table structure for table `mac_track_macwatch`
--

CREATE TABLE `mac_track_macwatch` (
  `mac_address` varchar(20) NOT NULL,
  `name` varchar(45) NOT NULL,
  `description` varchar(255) NOT NULL,
  `ticket_number` varchar(45) NOT NULL,
  `notify_schedule` tinyint(3) unsigned NOT NULL,
  `e-mail_addresses` varchar(255) NOT NULL,
  `discovered` tinyint(3) unsigned NOT NULL,
  `date_first_seen` timestamp NOT NULL default '0000-00-00 00:00:00',
  `data_last_seen` timestamp NOT NULL,
  PRIMARY KEY  (`mac_address`)
) TYPE=MyISAM;

--
-- Table structure for table `mac_track_oui_database`
--

CREATE TABLE `mac_track_oui_database` (
  `vendor_mac` varchar(8) NOT NULL,
  `vendor_name` varchar(100) NOT NULL,
  `vendor_address` text NOT NULL,
  `present` tinyint(3) unsigned NOT NULL default '1',
  PRIMARY KEY  (`vendor_mac`),
  KEY `vendor_name` (`vendor_name`)
) TYPE=MyISAM PACK_KEYS=1;

--
-- Table structure for table `mac_track_ports`
--

CREATE TABLE `mac_track_ports` (
  `site_id` int(10) unsigned NOT NULL default '0',
  `device_id` int(10) unsigned NOT NULL default '0',
  `hostname` varchar(40) NOT NULL default '',
  `description` varchar(100) NOT NULL default '',
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
  KEY `description` (`description`),
  KEY `mac` (`mac_address`),
  KEY `hostname` (`hostname`),
  KEY `vlan_name` (`vlan_name`),
  KEY `vlan_id` (`vlan_id`),
  KEY `device_id` (`device_id`),
  KEY `ip_address` (`ip_address`),
  KEY `port_name` (`port_name`),
  KEY `dns_hostname` (`dns_hostname`),
  KEY `vendor_mac` (`vendor_mac`),
  KEY `authorized` (`authorized`)
) TYPE=MyISAM COMMENT='Database for Tracking Device MAC''s';

--
-- Table structure for table `mac_track_processes`
--

CREATE TABLE `mac_track_processes` (
  `device_id` int(11) NOT NULL default '0',
  `process_id` int(10) unsigned default NULL,
  `status` varchar(20) NOT NULL default 'Queued',
  `start_date` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`device_id`)
) TYPE=MyISAM;

--
-- Table structure for table `mac_track_scan_dates`
--

CREATE TABLE `mac_track_scan_dates` (
  `scan_date` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`scan_date`)
) TYPE=MyISAM;

--
-- Table structure for table `mac_track_scanning_functions`
--

CREATE TABLE `mac_track_scanning_functions` (
  `scanning_function` varchar(100) NOT NULL default '',
  `type` int(10) unsigned NOT NULL default '0',
  `description` varchar(200) NOT NULL default '',
  PRIMARY KEY  (`scanning_function`)
) TYPE=MyISAM COMMENT='Registered Scanning Functions';

--
-- Table structure for table `mac_track_sites`
--

CREATE TABLE `mac_track_sites` (
  `site_id` int(10) unsigned NOT NULL auto_increment,
  `site_name` varchar(100) NOT NULL default '',
  `total_devices` int(10) unsigned NOT NULL default '0',
  `total_device_errors` int(10) unsigned NOT NULL default '0',
  `total_macs` int(10) unsigned NOT NULL default '0',
  `total_ips` int(10) unsigned NOT NULL default '0',
  `total_user_ports` int(11) NOT NULL default '0',
  `total_oper_ports` int(10) unsigned NOT NULL default '0',
  `total_trunk_ports` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (`site_id`)
) TYPE=MyISAM;

--
-- Table structure for table `mac_track_temp_ports`
--

CREATE TABLE `mac_track_temp_ports` (
  `site_id` int(10) unsigned NOT NULL default '0',
  `device_id` int(10) unsigned NOT NULL default '0',
  `hostname` varchar(40) NOT NULL default '',
  `description` varchar(100) NOT NULL default '',
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
  KEY `description` (`description`),
  KEY `ip_address` (`ip_address`),
  KEY `hostname` (`hostname`),
  KEY `vlan_name` (`vlan_name`),
  KEY `vlan_id` (`vlan_id`),
  KEY `device_id` (`device_id`),
  KEY `mac` (`mac_address`),
  KEY `updated` (`updated`),
  KEY `vendor_mac` (`vendor_mac`),
  KEY `authorized` (`authorized`)
) TYPE=MEMORY COMMENT='Database for Storing Temporary Results for Tracking Device M';
