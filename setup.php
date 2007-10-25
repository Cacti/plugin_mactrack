<?php

function mactrack_version () {
	return array( 	'name' 		=> 'mactrack',
			'version' 	=> '1.1',
			'longname'	=> 'Device Tracking',
			'author'	=> 'Larry Adams (TheWitness)',
			'homepage'	=> 'http://cacti.net',
			'email'		=> 'larryjadams@comcast.net',
			'url'		=> 'http://cactiusers.org/cacti/versions.php'
			);
}

function plugin_init_mactrack() {
	global $plugin_hooks, $no_http_header_files;

	$plugin_hooks['top_header_tabs']['mactrack'] = 'mactrack_show_tab';
	$plugin_hooks['top_graph_header_tabs']['mactrack'] = 'mactrack_show_tab';
	$plugin_hooks['config_arrays']['mactrack'] = 'mactrack_config_arrays';
	$plugin_hooks['draw_navigation_text']['mactrack'] = 'mactrack_draw_navigation_text';
	$plugin_hooks['config_form']['mactrack'] = 'mactrack_config_form';
	$plugin_hooks['config_settings']['mactrack'] = 'mactrack_config_settings';
	$plugin_hooks['poller_bottom']['mactrack'] = 'mactrack_poller_bottom';

	$no_http_header_files[] = "poller_mactrack.php";
	$no_http_header_files[] = "mactrack_scanner.php";
	$no_http_header_files[] = "mactrack_resolver.php";
	$no_http_header_files[] = "mactrack_import_ouidb.php";
}

function mactrack_poller_bottom () {
	global $config;
	include_once($config["base_path"] . "/lib/poller.php");
	include_once($config["base_path"] . "/lib/data_query.php");
	include_once($config["base_path"] . "/lib/graph_export.php");
	include_once($config["base_path"] . "/lib/rrd.php");

	$command_string = read_config_option("path_php_binary");
	$extra_args = "-q " . $config["base_path"] . "/plugins/mactrack/poller_mactrack.php";
	exec_background($command_string, "$extra_args");
}

function mactrack_config_settings () {
	global $tabs, $settings, $mactrack_snmp_versions, $mactrack_poller_frequencies, $mactrack_data_retention;

	$tabs["mactrack"] = "Device Tracking";

	$settings["mactrack"] = array(
		"mactrack_hdr_timing" => array(
			"friendly_name" => "Mac Track General Settings",
			"method" => "spacer",
			),
		"mt_processes" => array(
			"friendly_name" => "Number of Concurrent Processes",
			"description" => "Specify how many devices will be polled simultaneously until all devices have been polled.",
			"default" => "7",
			"method" => "textbox",
			"max_length" => "10"
			),
		"mt_collection_timing" => array(
			"friendly_name" => "Data Collection Timing",
			"description" => "Choose when collect MAC and IP Address information from your network devices.",
			"method" => "drop_array",
			"default" => "disabled",
			"array" => $mactrack_poller_frequencies,
			),
		"mt_script_runtime" => array(
			"friendly_name" => "Device Scanner Max Runtime",
			"description" => "Specify the number of minutes a device scanning function will allowed to run prior to the system assuming it has been completed.  This setting will correct for abended scanning jobs.",
			"default" => "20",
			"method" => "textbox",
			"max_length" => "10"
			),
		"mt_base_time" => array(
			"friendly_name" => "Start Time for Data Collection",
			"description" => "When would you like the first data collection to take place.  All future data collection times will be based upon this start time.  A good example would be 12:00AM.",
			"default" => "1:00am",
			"method" => "textbox",
			"max_length" => "10"
			),
		"mt_maint_time" => array(
			"friendly_name" => "Database Maintenance Time",
			"description" => "When should old database records be removed from the database.  Please note that no access will be permitted to the port database while this action is taking place.",
			"default" => "12:00am",
			"method" => "textbox",
			"max_length" => "10"
			),
		"mt_data_retention" => array(
			"friendly_name" => "Data Retention Duration",
			"description" => "How long should port MAC details be retained in the database.",
			"method" => "drop_array",
			"default" => "2weeks",
			"array" => $mactrack_data_retention,
			),
		"mt_mac_delim" => array(
			"friendly_name" => "Mac Address Delimiter",
			"description" => "How should each octet of the MAC address be delimited.",
			"method" => "drop_array",
			"default" => ":",
			"array" => array(":" => ":", "-" => "-")
			),
		"mactrack_hdr_rdns" => array(
			"friendly_name" => "Mac Track DNS Settings",
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
			"max_length" => "30"
			),
		"mt_dns_secondary" => array(
			"friendly_name" => "Secondary DNS IP Address",
			"description" => "Enter the secondary DNS IP Address to utilize for reverse lookups.",
			"method" => "textbox",
			"default" => "",
			"max_length" => "30"
			),
		"mt_dns_timeout" => array(
			"friendly_name" => "DNS Timeout",
			"description" => "Please enter the DNS timeout in milliseconds.  MacTrack uses a PHP based DNS resolver.",
			"method" => "textbox",
			"default" => "500",
			"max_length" => "10"
			),
		"mt_dns_prime_interval" => array(
			"friendly_name" => "DNS Prime Interval",
			"description" => "How often, in seconds do MacTrack scanning IP's need to be resolved to MAC addresses for DNS resolution.  Using a larger number when you have several thousand devices will increase performance.",
			"method" => "textbox",
			"default" => "120",
			"max_length" => "10"
			),
		"mactrack_hdr_general" => array(
			"friendly_name" => "Mac Track SNMP Settings",
			"method" => "spacer",
			),
		"mt_snmp_ver" => array(
			"friendly_name" => "SNMP Version",
			"description" => "Default SNMP version for all new hosts.",
			"method" => "drop_array",
			"default" => "Version 1",
			"array" => $mactrack_snmp_versions,
			),
		"mt_snmp_community" => array(
			"friendly_name" => "SNMP Community",
			"description" => "Default SNMP read community for all new hosts.",
			"method" => "textbox",
			"default" => "public",
			"max_length" => "100"
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
			"max_length" => "100"
			),
		"mt_snmp_timeout" => array(
			"friendly_name" => "SNMP Timeout",
			"description" => "Default SNMP timeout in milli-seconds.",
			"method" => "textbox",
			"default" => "500",
			"max_length" => "100"
			),
		"mt_snmp_retries" => array(
			"friendly_name" => "SNMP Retries",
			"description" => "The number times the SNMP poller will attempt to reach the host before failing.",
			"method" => "textbox",
			"default" => "3",
			"max_length" => "100"
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
}

function mactrack_draw_navigation_text ($nav) {
	$nav["mactrack_devices.php:"] = array("title" => "Mac Track Devices", "mapping" => "index.php:", "url" => "mactrack_devices.php", "level" => "1");
	$nav["mactrack_devices.php:edit"] = array("title" => "(Edit)", "mapping" => "index.php:,mactrack_devices.php:", "url" => "", "level" => "2");
	$nav["mactrack_devices.php:import"] = array("title" => "(Import)", "mapping" => "index.php:,mactrack_devices.php:", "url" => "", "level" => "2");
	$nav["mactrack_devices.php:actions"] = array("title" => "Actions", "mapping" => "index.php:,mactrack_devices.php:", "url" => "", "level" => "2");
	$nav["mactrack_device_types.php:"] = array("title" => "Mac Track Device Types", "mapping" => "index.php:", "url" => "mactrack_device_types.php", "level" => "1");
	$nav["mactrack_device_types.php:edit"] = array("title" => "(Edit)", "mapping" => "index.php:,mactrack_device_types.php:", "url" => "", "level" => "2");
	$nav["mactrack_device_types.php:import"] = array("title" => "(Import)", "mapping" => "index.php:,mactrack_device_types.php:", "url" => "", "level" => "2");
	$nav["mactrack_device_types.php:actions"] = array("title" => "Actions", "mapping" => "index.php:,mactrack_device_types.php:", "url" => "", "level" => "2");
	$nav["mactrack_sites.php:"] = array("title" => "Mac Track Sites", "mapping" => "index.php:", "url" => "mactrack_sites.php", "level" => "1");
	$nav["mactrack_sites.php:edit"] = array("title" => "(Edit)", "mapping" => "index.php:,mactrack_sites.php:", "url" => "", "level" => "2");
	$nav["mactrack_sites.php:actions"] = array("title" => "Actions", "mapping" => "index.php:,mactrack_sites.php:", "url" => "", "level" => "2");
	$nav["mactrack_macwatch.php:"] = array("title" => "Mac Address Tracking Utility", "mapping" => "index.php:", "url" => "mactrack_macwatch.php", "level" => "1");
	$nav["mactrack_macwatch.php:edit"] = array("title" => "(Edit)", "mapping" => "index.php:,mactrack_macwatch.php:", "url" => "", "level" => "2");
	$nav["mactrack_macwatch.php:actions"] = array("title" => "Actions", "mapping" => "index.php:,mactrack_macwatch.php:", "url" => "", "level" => "2");
	$nav["mactrack_macauth.php:"] = array("title" => "Mac Address Authorization Utility", "mapping" => "index.php:", "url" => "mactrack_macauth.php", "level" => "1");
	$nav["mactrack_macauth.php:edit"] = array("title" => "(Edit)", "mapping" => "index.php:,mactrack_macauth.php:", "url" => "", "level" => "2");
	$nav["mactrack_macauth.php:actions"] = array("title" => "Actions", "mapping" => "index.php:,mactrack_macauth.php:", "url" => "", "level" => "2");
	$nav["mactrack_vendormacs.php:"] = array("title" => "Mac Track Vendor Macs", "mapping" => "index.php:", "url" => "mactrack_vendormacs.php", "level" => "1");
	$nav["mactrack_view.php:"] = array("title" => "Mac Track Viewer", "mapping" => "index.php:", "url" => "mactrack_view.php", "level" => "1");
	$nav["mactrack_view.php:actions"] = array("title" => "Actions", "mapping" => "index.php:,mactrack_view.php:", "url" => "", "level" => "2");
	$nav["mactrack_utilities.php:"] = array("title" => "Device Tracking Utilities", "mapping" => "index.php:", "url" => "mactrack_utilities.php", "level" => "1");
	$nav["mactrack_utilities.php:mactrack_utilities_perform_db_maint"] = array("title" => "Perform Database Maintenance", "mapping" => "index.php:,mactrack_utilities.php:", "url" => "mactrack_utilities.php", "level" => "2");
	$nav["mactrack_utilities.php:mactrack_utilities_purge_scanning_funcs"] = array("title" => "Refresh Scanning Functions", "mapping" => "index.php:,mactrack_utilities.php:", "url" => "mactrack_utilities.php", "level" => "2");
	$nav["mactrack_utilities.php:mactrack_utilities_truncate_ports_table"] = array("title" => "Truncate Port Results Table", "mapping" => "index.php:,mactrack_utilities.php:", "url" => "mactrack_utilities.php", "level" => "2");
	$nav["mactrack_utilities.php:mactrack_view_proc_status"] = array("title" => "View MacTrack Process Status", "mapping" => "index.php:,mactrack_utilities.php:", "url" => "mactrack_utilities.php", "level" => "2");
	$nav["mactrack_utilities.php:mactrack_refresh_oui_database"] = array("title" => "Refresh/Update Vendor MAC Database from IEEE", "mapping" => "index.php:,mactrack_utilities.php:", "url" => "mactrack_utilities.php", "level" => "2");
	return $nav;
}

function mactrack_show_tab () {
	global $config, $user_auth_realm_filenames;
	$realm_id = 0;
	if (isset($user_auth_realm_filenames{basename('mactrack_view.php')})) {
		$realm_id = $user_auth_realm_filenames{basename('mactrack_view.php')};
	}
	if ((db_fetch_assoc("select user_auth_realm.realm_id
		from user_auth_realm where user_auth_realm.user_id='" . $_SESSION["sess_user_id"] . "'
		and user_auth_realm.realm_id='$realm_id'")) || (empty($realm_id))) {
		print '<a href="' . $config['url_path'] . 'plugins/mactrack/mactrack_view.php"><img src="' . $config['url_path'] . 'plugins/mactrack/images/tab_mactrack.gif" alt="Mac Device Tracker" align="absmiddle" border="0"></a>';
	}
}

function mactrack_config_arrays () {
	global $mactrack_snmp_versions, $mactrack_device_types, $mactrack_search_types;
	global $user_auth_realms, $user_auth_realm_filenames, $menu, $config, $rows_selector;
	global $mactrack_poller_frequencies, $mactrack_data_retention, $refresh_interval;

	$user_auth_realms[20]='View MacTrack Data';
	$user_auth_realm_filenames['mactrack_view.php'] = 20;

	$user_auth_realms[21]='Administate MacTrack';
	$user_auth_realm_filenames['mactrack_devices.php'] = 21;
	$user_auth_realm_filenames['mactrack_sites.php'] = 21;
	$user_auth_realm_filenames['mactrack_device_types.php'] = 21;
	$user_auth_realm_filenames['mactrack_utilities.php'] = 21;
	$user_auth_realm_filenames['mactrack_macwatch.php'] = 21;
	$user_auth_realm_filenames['mactrack_macauth.php'] = 21;
	$user_auth_realm_filenames['mactrack_vendormacs.php'] = 21;

	$user_auth_realms[22]='Manage Mac Authorizations';

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

	$mactrack_snmp_versions = array(1 =>
		"Version 1",
		"Version 2");

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

	$menu2 = array ();
	foreach ($menu as $temp => $temp2 ) {
		$menu2[$temp] = $temp2;
		if ($temp == 'Management') {
			$menu2["Device Tracking Management"]["plugins/mactrack/mactrack_sites.php"] = "Sites";
			$menu2["Device Tracking Management"]["plugins/mactrack/mactrack_devices.php"] = "Devices";
			$menu2["Device Tracking Management"]["plugins/mactrack/mactrack_device_types.php"] = "Device Types";
			$menu2["Device Tracking Management"]["plugins/mactrack/mactrack_vendormacs.php"] = "Vendor Macs";
			$menu2["Device Tracking Management"]["plugins/mactrack/mactrack_utilities.php"] = "Tracking Utilities";
			$menu2["Tracking Tools"]["plugins/mactrack/mactrack_macwatch.php"] = "Mac Watch";
			$menu2["Tracking Tools"]["plugins/mactrack/mactrack_macauth.php"] = "Mac Authorizations";
		}
	}
	$menu = $menu2;
}

function mactrack_config_form () {
	global $fields_mactrack_device_type_edit, $fields_mactrack_device_edit, $fields_mactrack_site_edit;
	global $mactrack_device_types, $mactrack_snmp_versions, $fields_mactrack_macw_edit, $fields_mactrack_maca_edit;

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
		"max_length" => "100"
		),
	"highPort" => array(
		"method" => "textbox",
		"friendly_name" => "High User Port Number",
		"description" => "Provide the low user port number on this switch.  Leave 0 to allow the system to calculate it.",
		"value" => "|arg1:highPort|",
		"default" => read_config_option("mt_port_highPort"),
		"max_length" => "100"
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
		"textarea_rows" => "3",
		"textarea_cols" => "70",
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
		"friendly_name" => "Hub/Switch, Switch/Router Settings"
		),
	"port_ignorePorts" => array(
		"method" => "textbox",
		"friendly_name" => "Ports to Ignore",
		"description" => "Provide a list of ports on a specific switch/hub whose MAC results should be ignored.  Ports such as link/trunk ports that can not be distinguished from other user ports are examples.  Each port number must be separated by a colon ':'.  For example, 'Fa0/1: Fa1/23' would be acceptable for some manufacturers switch types.",
		"value" => "|arg1:ignorePorts|",
		"default" => read_config_option("mt_port_ignorePorts"),
		"max_length" => "255"
		),
	"spacer2" => array(
		"method" => "spacer",
		"friendly_name" => "SNMP Settings"
		),
	"snmp_readstring" => array(
		"method" => "textbox",
		"friendly_name" => "SNMP Readstring",
		"description" => "Fill in the SNMP read community for this device.",
		"value" => "|arg1:snmp_readstring|",
		"default" => read_config_option("mt_snmp_community"),
		"max_length" => "100"
		),
	"snmp_readstrings" => array(
		"method" => "textbox",
		"friendly_name" => "SNMP Rotation Readstrings",
		"description" => "Fill in the list of available SNMP read strings to test for this device. Each read string must be separated by a colon ':'.  These read strings will be tested sequentially if the primary read string is invalid.",
		"value" => "|arg1:snmp_readstrings|",
		"default" => read_config_option("mt_snmp_communities"),
		"max_length" => "255"
		),
	"snmp_version" => array(
		"method" => "drop_array",
		"friendly_name" => "SNMP Version",
		"description" => "Choose the SNMP version for this host.",
		"value" => "|arg1:snmp_version|",
		"default" => read_config_option("mt_snmp_ver"),
		"array" => $mactrack_snmp_versions
		),
	"snmp_port" => array(
		"method" => "textbox",
		"friendly_name" => "SNMP Port",
		"description" => "The UDP/TCP Port to poll the SNMP agent on.",
		"value" => "|arg1:snmp_port|",
		"max_length" => "8",
		"default" => read_config_option("mt_snmp_port"),
		"size" => "15"
		),
	"snmp_timeout" => array(
		"method" => "textbox",
		"friendly_name" => "SNMP Timeout",
		"description" => "The maximum number of milliseconds Cacti will wait for an SNMP response (does not work with php-snmp support).",
		"value" => "|arg1:snmp_timeout|",
		"max_length" => "8",
		"default" => read_config_option("mt_snmp_timeout"),
		"size" => "15"
		),
	"snmp_retries" => array(
		"method" => "textbox",
		"friendly_name" => "SNMP Retries",
		"description" => "The maximum number of attempts to reach a device via an SNMP readstring prior to giving up.",
		"value" => "|arg1:snmp_retries|",
		"max_length" => "8",
		"default" => read_config_option("mt_snmp_retries"),
		"size" => "15"
		),
	"spacer3" => array(
		"method" => "spacer",
		"friendly_name" => "Custom Authentication"
		),
	"user_name" => array(
		"method" => "textbox",
		"friendly_name" => "User Name",
		"description" => "The user name to be used for your custom authentication method.  Examples include SSH, RSH, HTML, etc.",
		"value" => "|arg1:user_name|",
		"default" => "",
		"max_length" => "40"
		),
	"user_password" => array(
		"method" => "textbox_password",
		"friendly_name" => "Password",
		"description" => "The password to be used for your custom authentication.",
		"value" => "|arg1:user_password|",
		"default" => "",
		"max_length" => "40"
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
		"textarea_rows" => "3",
		"textarea_cols" => "70",
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
		"method" => "textarea",
		"friendly_name" => "Description",
		"description" => "Please add a description for this entry.  This information will be placed in your e-mail.",
		"value" => "|arg1:description|",
		"textarea_rows" => "4",
		"textarea_cols" => "70"
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
			2 => "All Occurances")
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
		"description" => "Please enter the MAC Address or Mac Address Match string to be automatically authorized.",
		"value" => "|arg1:mac_address|",
		"default" => "",
		"max_length" => "40"
		),
	"description" => array(
		"method" => "textarea",
		"friendly_name" => "Reason",
		"description" => "Please add a reason for this entry.",
		"value" => "|arg1:description|",
		"textarea_rows" => "4",
		"textarea_cols" => "70"
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

?>