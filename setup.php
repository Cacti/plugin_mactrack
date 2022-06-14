<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2022 The Cacti Group                                 |
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
	api_plugin_register_hook('mactrack', 'top_header_tabs',       'mactrack_show_tab',             'setup.php');
	api_plugin_register_hook('mactrack', 'top_graph_header_tabs', 'mactrack_show_tab',             'setup.php');
	api_plugin_register_hook('mactrack', 'config_arrays',         'mactrack_config_arrays',        'setup.php');
	api_plugin_register_hook('mactrack', 'draw_navigation_text',  'mactrack_draw_navigation_text', 'setup.php');
	api_plugin_register_hook('mactrack', 'config_form',           'mactrack_config_form',          'setup.php');
	api_plugin_register_hook('mactrack', 'config_settings',       'mactrack_config_settings',      'setup.php');
	api_plugin_register_hook('mactrack', 'poller_bottom',         'mactrack_poller_bottom',        'setup.php');
	api_plugin_register_hook('mactrack', 'page_head',             'mactrack_page_head',            'setup.php');

	# device hook: intercept on device save
	api_plugin_register_hook('mactrack', 'api_device_save', 'sync_cacti_to_mactrack', 'mactrack_actions.php');
	# device hook: Add a new dropdown Action for Device Management
	api_plugin_register_hook('mactrack', 'device_action_array', 'mactrack_device_action_array', 'mactrack_actions.php');
	# device hook: Device Management Action dropdown selected: prepare the list of devices for a confirmation request
	api_plugin_register_hook('mactrack', 'device_action_prepare', 'mactrack_device_action_prepare', 'mactrack_actions.php');
	# device hook: Device Management Action dropdown selected: execute list of device
	api_plugin_register_hook('mactrack', 'device_action_execute', 'mactrack_device_action_execute', 'mactrack_actions.php');

	# Register our realms
	api_plugin_register_realm('mactrack', 'mactrack_view_ips.php,mactrack_view_arp.php,mactrack_view_macs.php,mactrack_view_dot1x.php,mactrack_view_sites.php,mactrack_view_devices.php,mactrack_view_interfaces.php,mactrack_view_graphs.php,mactrack_ajax.php', 'Device Tracking Viewer', 1);
	api_plugin_register_realm('mactrack', 'mactrack_ajax_admin.php,mactrack_devices.php,mactrack_snmp.php,mactrack_sites.php,mactrack_device_types.php,mactrack_utilities.php,mactrack_macwatch.php,mactrack_macauth.php,mactrack_vendormacs.php', 'Device Tracking Administrator', 1);

	mactrack_setup_table_new();
}

function plugin_mactrack_uninstall() {
	return true;
}

function plugin_mactrack_version() {
	global $config;
	$info = parse_ini_file($config['base_path'] . '/plugins/mactrack/INFO', true);
	return $info['info'];
}

function plugin_mactrack_check_config() {
	/* Here we will check to ensure everything is configured */
	mactrack_check_upgrade();
	return true;
}

function plugin_mactrack_upgrade() {
	/* Here we will upgrade to the newest version */
	mactrack_check_upgrade();
	return false;
}

function mactrack_check_upgrade() {
	global $config;

	$files = array('index.php', 'plugins.php', 'mactrack_devices.php');
	if (!in_array(get_current_page(), $files)) {
		return;
	}

	include_once($config['base_path'] . '/plugins/mactrack/includes/database.php');
	include_once($config['base_path'] . '/plugins/mactrack/lib/mactrack_functions.php');

	$current = plugin_mactrack_version();
	$current = $current['version'];

	$old     = db_fetch_row("SELECT * FROM plugin_config WHERE directory='mactrack'");
	if (!sizeof($old) || $current != $old['version']) {
		/* if the plugin is installed and/or active */
		if (!sizeof($old) || $old['status'] == 1 || $old['status'] == 4) {
			/* re-register the hooks */
			plugin_mactrack_install();
			if (api_plugin_is_enabled('mactrack')) {
				# may sound ridiculous, but enables new hooks
				api_plugin_enable_hooks('mactrack');
			}

			/* perform a database upgrade */
			mactrack_database_upgrade();
		}

		if (read_config_option('mt_convert_readstrings', true) != 'on') {
			convert_readstrings();
		}

		// If are realms are not present in plugin_realms recreate them with the old realm ids (minus 100) so that upgraded installs are not broken
		if (!db_fetch_cell("SELECT id FROM plugin_realms WHERE plugin = 'mactrack'")) {
			db_execute("INSERT INTO plugin_realms
				(id, plugin, file, display)
				VALUES (2020, 'mactrack', 'mactrack_view_ips.php,mactrack_view_arp.php,mactrack_view_macs.php,mactrack_view_sites.php,mactrack_view_devices.php,mactrack_view_interfaces.php,mactrack_view_dot1x.php,mactrack_view_graphs.php,mactrack_ajax.php', 'Device Tracking Viewer')");
			db_execute("INSERT INTO plugin_realms
				(id, plugin, file, display)
				VALUES (2021, 'mactrack', 'mactrack_ajax_admin.php,mactrack_devices.php,mactrack_snmp.php,mactrack_sites.php,mactrack_device_types.php,mactrack_utilities.php,mactrack_macwatch.php,mactrack_macauth.php,mactrack_vendormacs.php', 'Device Tracking Administrator')");
		}

		/* rebuild the scanning functions */
		mactrack_rebuild_scanning_funcs();

		/* update the plugin information */
		$info = plugin_mactrack_version();
		$id   = db_fetch_cell("SELECT id FROM plugin_config WHERE directory='mactrack'");

		db_execute("UPDATE plugin_config
			SET name='" . $info['longname'] . "',
			author='"   . $info['author']   . "',
			webpage='"  . $info['homepage'] . "',
			version='"  . $info['version']  . "'
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
		if (cacti_sizeof($columns)) {
			foreach($columns as $row) {
				if ($row['Field'] == $column) {
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
		if (cacti_sizeof($keys)) {
			foreach($keys as $key) {
				if ($key['Key_name'] == $index) {
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

function mactrack_check_dependencies() {
	global $plugins, $config;

	return true;
}

function mactrack_setup_table_new() {
	global $config;

	include_once($config['base_path'] . '/plugins/mactrack/includes/database.php');

	mactrack_setup_database();
}

function mactrack_page_head() {
	global $config;

	print "<script type='text/javascript' src='" . $config['url_path'] . "plugins/mactrack/mactrack.js'></script>\n";
	print "<script type='text/javascript' src='" . $config['url_path'] . "plugins/mactrack/mactrack_snmp.js'></script>\n";
	if (file_exists($config['base_path'] . '/plugins/mactrack/themes/' . get_selected_theme() . '/mactrack.css')) {
		print "<link type='text/css' href='" . $config['url_path'] . "plugins/mactrack/themes/" . get_selected_theme() . "/mactrack.css' rel='stylesheet'>\n";
	} else {
		print "<link type='text/css' href='" . $config['url_path'] . "plugins/mactrack/mactrack.css' rel='stylesheet'>\n";
	}
}

function mactrack_poller_bottom() {
	global $config;

	$command_string = read_config_option('path_php_binary');
	$extra_args = '-q ' . $config['base_path'] . '/plugins/mactrack/poller_mactrack.php';
	exec_background($command_string, $extra_args);
}

function mactrack_config_settings() {
	global $tabs, $settings, $settings_user, $tabs_graphs, $snmp_versions, $mactrack_poller_frequencies,
	$mactrack_data_retention, $mactrack_macauth_frequencies, $mactrack_update_policies;

	$tabs['mactrack'] = __('Device Tracking', 'mactrack');

	$settings['mactrack'] = array(
		'mactrack_hdr_timing' => array(
			'friendly_name' => __('General Settings', 'mactrack'),
			'method' => 'spacer',
			),
		'mt_collection_timing' => array(
			'friendly_name' => __('Scanning Frequency', 'mactrack'),
			'description' => __('Choose when to collect MAC and IP Addresses and Interface statistics from your network devices.', 'mactrack'),
			'method' => 'drop_array',
			'default' => 'disabled',
			'array' => $mactrack_poller_frequencies,
			),
		'mt_processes' => array(
			'friendly_name' => __('Concurrent Processes', 'mactrack'),
			'description' => __('Specify how many devices will be polled simultaneously until all devices have been polled.', 'mactrack'),
			'default' => '7',
			'method' => 'textbox',
			'max_length' => '10',
			'size' => '4'
			),
		'mt_script_runtime' => array(
			'friendly_name' => __('Scanner Max Runtime', 'mactrack'),
			'description' => __('Specify the number of minutes a device scanning function will be allowed to run prior to the system assuming it has been completed.  This setting will correct for abended scanning jobs.', 'mactrack'),
			'default' => '20',
			'method' => 'textbox',
			'max_length' => '10',
			'size' => '4'
			),
		'mt_base_time' => array(
			'friendly_name' => __('Start Time for Data Collection', 'mactrack'),
			'description' => __('When would you like the first data collection to take place.  All future data collection times will be based upon this start time.  A good example would be 12:00AM.', 'mactrack'),
			'default' => '1:00am',
			'method' => 'textbox',
			'max_length' => '10',
			'size' => '8'
			),
		'mt_maint_time' => array(
			'friendly_name' => __('Database Maintenance Time', 'mactrack'),
			'description' => __('When should old database records be removed from the database.  Please note that no access will be permitted to the port database while this action is taking place.', 'mactrack'),
			'default' => '12:00am',
			'method' => 'textbox',
			'max_length' => '10',
			'size' => '8'
			),
		'mt_maint_confirm' => array(
			'friendly_name' => __('Confirm Utilities Prompt', 'mactrack'),
			'description' => __('When using utilities, prompt for verification', 'mactrack'),
			'default' => read_config_option('deletion_verification'),
			'method' => 'checkbox',
			),
		'mt_data_retention' => array(
			'friendly_name' => __('Data Retention', 'mactrack'),
			'description' => __('How long should port MAC details be retained in the database.', 'mactrack'),
			'method' => 'drop_array',
			'default' => '2weeks',
			'array' => $mactrack_data_retention,
			),
		'mt_ignorePorts_delim' => array(
			'friendly_name' => __('Switch Level Ignore Ports Delimiter', 'mactrack'),
			'description' => __('What delimiter should Device Tracking use when parsing the Ignore Ports string for each switch.', 'mactrack'),
			'method' => 'drop_array',
			'default' => '-1',
			'array' => array(
				'-1' => __('Auto Detect', 'mactrack'),
				':' => __('Colon [:]', 'mactrack'),
				'|' => __('Pipe [|]', 'mactrack'),
				' ' => __('Space [ ]', 'mactrack')
				)
			),
		'mt_mac_delim' => array(
			'friendly_name' => __('Mac Address Delimiter', 'mactrack'),
			'description' => __('How should each octet of the MAC address be delimited.', 'mactrack'),
			'method' => 'drop_array',
			'default' => ':',
			'array' => array(':' => __('Colon [:]', 'mactrack'), '-' => __('Dash [-]', 'mactrack'))
			),
		'mt_ignorePorts' => array(
			'method' => 'textbox',
			'friendly_name' => __('Ports to Ignore', 'mactrack'),
			'description' => __('Provide a regular expression of ifNames or ifDescriptions of ports to ignore in the interface list.  For example, (Vlan|Loopback|Null).', 'mactrack'),
			'class' => 'textAreaNotes',
			'defaults' => '(Vlan|Loopback|Null)',
			'max_length' => '255',
			'size' => '80'
			),
		'mt_interface_high' => array(
			'friendly_name' => __('Bandwidth Usage Threshold', 'mactrack'),
			'description' => __('When reviewing network interface statistics, what bandwidth threshold do you want to view by default.', 'mactrack'),
			'method' => 'drop_array',
			'default' => '70',
			'array' => array(
				'-1' => __('N/A', 'mactrack'),
				'10' => __('%d Percent', 10, 'mactrack'),
				'20' => __('%d Percent', 20, 'mactrack'),
				'30' => __('%d Percent', 30, 'mactrack'),
				'40' => __('%d Percent', 40, 'mactrack'),
				'50' => __('%d Percent', 50, 'mactrack'),
				'60' => __('%d Percent', 60, 'mactrack'),
				'70' => __('%d Percent', 70, 'mactrack'),
				'80' => __('%d Percent', 80, 'mactrack'),
				'90' => __('%d Percent', 90, 'mactrack')
				)
			),
		'mt_hdr_rdns' => array(
			'friendly_name' => __('DNS Settings', 'mactrack'),
			'method' => 'spacer',
			),
		'mt_reverse_dns' => array(
			'friendly_name' => __('Perform Reverse DNS Name Resolution', 'mactrack'),
			'description' => __('Should Device Tracking perform reverse DNS lookup of the IP addresses associated with ports. CAUTION: If DNS is not properly setup, this will slow scan time significantly.', 'mactrack'),
			'default' => '',
			'method' => 'checkbox'
			),
		'mt_dns_primary' => array(
			'friendly_name' => __('Primary DNS IP Address', 'mactrack'),
			'description' => __('Enter the primary DNS IP Address to utilize for reverse lookups.', 'mactrack'),
			'method' => 'textbox',
			'default' => '',
			'max_length' => '30',
			'size' => '18'
			),
		'mt_dns_secondary' => array(
			'friendly_name' => __('Secondary DNS IP Address', 'mactrack'),
			'description' => __('Enter the secondary DNS IP Address to utilize for reverse lookups.', 'mactrack'),
			'method' => 'textbox',
			'default' => '',
			'max_length' => '30',
			'size' => '18'
			),
		'mt_dns_timeout' => array(
			'friendly_name' => __('DNS Timeout', 'mactrack'),
			'description' => __('Please enter the DNS timeout in milliseconds.  Device Tracking uses a PHP based DNS resolver.', 'mactrack'),
			'method' => 'textbox',
			'default' => '500',
			'max_length' => '10',
			'size' => '4'
			),
		'mt_dns_prime_interval' => array(
			'friendly_name' => __('DNS Prime Interval', 'mactrack'),
			'description' => __('How often, in seconds do Device Tracking scanning IP\'s need to be resolved to MAC addresses for DNS resolution.  Using a larger number when you have several thousand devices will increase performance.', 'mactrack'),
			'method' => 'textbox',
			'default' => '120',
			'max_length' => '10',
			'size' => '4'
			),
		'mactrack_hdr_notification' => array(
			'friendly_name' => __('Notification Settings', 'mactrack'),
			'method' => 'spacer',
			),
		'mt_from_email' => array(
			'friendly_name' => __('Source Address', 'mactrack'),
			'description' => __('The source Email address for Device Tracking Emails.', 'mactrack'),
			'method' => 'textbox',
			'default' => '',
			'max_length' => '100',
			'size' => '30'
			),
		'mt_from_name' => array(
			'friendly_name' => __('Source Email Name', 'mactrack'),
			'description' => __('The Source Email name for Device Tracking Emails.', 'mactrack'),
			'method' => 'textbox',
			'default' => __('MACTrack Administrator', 'mactrack'),
			'max_length' => '100',
			'size' => '30'
			),
		'mt_macwatch_description' => array(
			'friendly_name' => __('MacWatch Default Body', 'mactrack'),
			'description' => htmlspecialchars(__('The Email body preset for Device Tracking MacWatch Emails.  The body can contain ' .
			'any valid html tags.  It also supports replacement tags that will be processed when sending an Email.  ' .
			'Valid tags include <IP>, <MAC>, <TICKET>, <SITENAME>, <DEVICEIP>, <PORTNAME>, <PORTNUMBER>, <DEVICENAME>.', 'mactrack')),
			'method' => 'textarea',
			'default' => __('Mac Address <MAC> found at IP Address <IP> for Ticket Number: <TICKET>.<br>The device is located at<br>Site: <SITENAME>, Device <DEVICENAME>, IP <DEVICEIP>, Port <PORTNUMBER>, and Port Name <PORTNAME>', 'mactrack'),
			'class' => 'textAreaNotes',
			'max_length' => '512',
			'textarea_rows' => '5',
			'textarea_cols' => '80',
			),
		'mt_macauth_emails' => array(
			'friendly_name' => __('MacAuth Report Email Addresses', 'mactrack'),
			'description' => __('A comma delimited list of users to recieve the MacAuth Email notifications.', 'mactrack'),
			'method' => 'textarea',
			'default' => '',
			'class' => 'textAreaNotes',
			'max_length' => '255',
			'textarea_rows' => '5',
			'textarea_cols' => '80',
			),
		'mt_macauth_email_frequency' => array(
			'friendly_name' => __('MacAuth Report Frequency', 'mactrack'),
			'description' => __('How often will the MacAuth Reports be Emailed.', 'mactrack'),
			'method' => 'drop_array',
			'default' => 'disabled',
			'array' => $mactrack_macauth_frequencies,
			),
		'mactrack_hdr_arpwatch' => array(
			'friendly_name' => __('Device Tracking ArpWatch Settings', 'mactrack'),
			'method' => 'spacer',
			),
		'mt_arpwatch' => array(
			'friendly_name' => __('Enable ArpWatch', 'mactrack'),
			'description' => __('Should Device Tracking also use ArpWatch data to supplement Mac to IP/DNS resolution?', 'mactrack'),
			'default' => '',
			'method' => 'checkbox'
			),
		'mt_arpwatch_path' => array(
			'friendly_name' => __('ArpWatch Database Path', 'mactrack'),
			'description' => __('The location of the ArpWatch Database file on the Cacti server.', 'mactrack'),
			'method' => 'filepath',
			'default' => '',
			'max_length' => '255',
			'size' => '60'
			),
		'mactrack_hdr_general' => array(
			'friendly_name' => __('SNMP Presets', 'mactrack'),
			'method' => 'spacer',
			),
		'mt_update_policy' => array(
			'friendly_name' => __('Update Policy for SNMP Options', 'mactrack'),
			'description' => __('Policy for synchronization of SNMP Options between Cacti devices and Device Tracking Devices.', 'mactrack'),
			'method' => 'drop_array',
			'default' => 1,
			'array' => $mactrack_update_policies,
			),
		'mt_snmp_ver' => array(
			'friendly_name' => __('Version', 'mactrack'),
			'description' => __('Default SNMP version for all new hosts.', 'mactrack'),
			'method' => 'drop_array',
			'default' => '2',
			'array' => $snmp_versions,
			),
		'mt_snmp_community' => array(
			'friendly_name' => __('Community', 'mactrack'),
			'description' => __('Default SNMP read community for all new hosts.', 'mactrack'),
			'method' => 'textbox',
			'default' => 'public',
			'max_length' => '100',
			'size' => '20'
			),
		'mt_snmp_communities' => array(
			'friendly_name' => __('Communities', 'mactrack'),
			'description' => __('Fill in the list of available SNMP read strings to test for this device. Each read string must be separated by a colon \':\'.  These read strings will be tested sequentially if the primary read string is invalid.', 'mactrack'),
			'method' => 'textbox',
			'default' => 'public:private:secret',
			'max_length' => '255'
			),
		'mt_snmp_port' => array(
			'friendly_name' => __('Port', 'mactrack'),
			'description' => __('The UDP/TCP Port to poll the SNMP agent on.', 'mactrack'),
			'method' => 'textbox',
			'default' => '161',
			'max_length' => '10',
			'size' => '4'
			),
		'mt_snmp_timeout' => array(
			'friendly_name' => __('Timeout', 'mactrack'),
			'description' => __('Default SNMP timeout in milli-seconds.', 'mactrack'),
			'method' => 'textbox',
			'default' => '500',
			'max_length' => '10',
			'size' => '4'
			),
		'mt_snmp_retries' => array(
			'friendly_name' => __('Retries', 'mactrack'),
			'description' => __('The number times the SNMP poller will attempt to reach the host before failing.', 'mactrack'),
			'method' => 'textbox',
			'default' => '3',
			'max_length' => '10',
			'size' => '4'
			)
		);

	$ts = array();
	foreach ($settings['path'] as $t => $ta) {
		$ts[$t] = $ta;
		if ($t == 'path_snmpget') {
			$ts['path_snmpbulkwalk'] = array(
				'friendly_name' => __('snmpbulkwalk Binary Path', 'mactrack'),
				'description' => __('The path to your snmpbulkwalk binary.', 'mactrack'),
				'method' => 'textbox',
				'max_length' => '255'
			);
		}
	}
	$settings['path']=$ts;

	$tabs_graphs += array('mactrack' => __('MacTrack Settings', 'mactrack'));

	$settings_user += array(
		'mactrack' => array(
			'default_mactrack_tab' => array(
				'friendly_name' => __('Default Tab', 'mactrack'),
				'description' => __('Which MacTrack tab would you want to be your Default tab every time you goto the MacTrack second.', 'mactrack'),
				'method' => 'drop_array',
				'default' => 'sites',
				'array' => array(
					'sites' => __('Sites', 'mactrack'),
					'devices' => __('Devices', 'mactrack'),
					'ips' => __('IP Addresses', 'mactrack'),
					'macs' => __('Mac Addresses', 'mactrack'),
					'interfaces' => __('Interfaces', 'mactrack'),
					'dot1x' => __('dot1x Deta', 'mactrack')
				)
			)
		)
	);

	mactrack_check_upgrade();
}

function mactrack_draw_navigation_text($nav) {
	$nav['mactrack_devices.php:'] = array(
		'title' => __('Device Tracking Devices', 'mactrack'),
		'mapping' => 'index.php:',
		'url' => 'mactrack_devices.php',
		'level' => '1'
	);

	$nav['mactrack_devices.php:edit'] = array(
		'title' => __('(Edit)', 'mactrack'),
		'mapping' => 'index.php:,mactrack_devices.php:',
		'url' => '',
		'level' => '2'
	);

	$nav['mactrack_devices.php:import'] = array(
		'title' => __('(Import)', 'mactrack'),
		'mapping' => 'index.php:,mactrack_devices.php:',
		'url' => '',
		'level' => '2'
	);

	$nav['mactrack_devices.php:actions'] = array(
		'title' => __('Actions', 'mactrack'),
		'mapping' => 'index.php:,mactrack_devices.php:',
		'url' => '',
		'level' => '2'
	);

	$nav['mactrack_snmp.php:'] = array(
		'title' => __('Device Tracking SNMP Options', 'mactrack'),
		'mapping' => 'index.php:',
		'url' => 'mactrack_snmp.php',
		'level' => '1'
	);

	$nav['mactrack_snmp.php:actions'] = array(
		'title' => __('Actions', 'mactrack'),
		'mapping' => 'index.php:,mactrack_snmp.php:',
		'url' => '',
		'level' => '2'
	);

	$nav['mactrack_snmp.php:edit'] = array(
		'title' => __('(Edit)', 'mactrack'),
		'mapping' => 'index.php:,mactrack_snmp.php:',
		'url' => '',
		'level' => '2'
	);

	$nav['mactrack_snmp.php:item_edit'] = array(
		'title' => __('(Edit)', 'mactrack'),
		'mapping' => 'index.php:,mactrack_snmp.php:',
		'url' => '',
		'level' => '2'
	);

	$nav['mactrack_device_types.php:'] = array(
		'title' => __('Device Tracking Device Types', 'mactrack'),
		'mapping' => 'index.php:',
		'url' => 'mactrack_device_types.php',
		'level' => '1'
	);

	$nav['mactrack_device_types.php:edit'] = array(
		'title' => __('(Edit)', 'mactrack'),
		'mapping' => 'index.php:,mactrack_device_types.php:',
		'url' => '',
		'level' => '2'
	);

	$nav['mactrack_device_types.php:import'] = array(
		'title' => __('(Import)', 'mactrack'),
		'mapping' => 'index.php:,mactrack_device_types.php:',
		'url' => '',
		'level' => '2'
	);

	$nav['mactrack_device_types.php:actions'] = array(
		'title' => __('Actions', 'mactrack'),
		'mapping' => 'index.php:,mactrack_device_types.php:',
		'url' => '',
		'level' => '2'
	);

	$nav['mactrack_sites.php:'] = array(
		'title' => __('Device Tracking Sites', 'mactrack'),
		'mapping' => 'index.php:',
		'url' => 'mactrack_sites.php',
		'level' => '1'
	);

	$nav['mactrack_sites.php:edit'] = array(
		'title' => __('(Edit)', 'mactrack'),
		'mapping' => 'index.php:,mactrack_sites.php:',
		'url' => '',
		'level' => '2'
	);

	$nav['mactrack_sites.php:actions'] = array(
		'title' => __('Actions', 'mactrack'),
		'mapping' => 'index.php:,mactrack_sites.php:',
		'url' => '',
		'level' => '2'
	);

	$nav['mactrack_macwatch.php:'] = array(
		'title' => __('Mac Address Tracking Utility', 'mactrack'),
		'mapping' => 'index.php:',
		'url' => 'mactrack_macwatch.php',
		'level' => '1'
	);

	$nav['mactrack_macwatch.php:edit'] = array(
		'title' => __('(Edit)', 'mactrack'),
		'mapping' => 'index.php:,mactrack_macwatch.php:',
		'url' => '',
		'level' => '2'
	);

	$nav['mactrack_macwatch.php:actions'] = array(
		'title' => __('Actions', 'mactrack'),
		'mapping' => 'index.php:,mactrack_macwatch.php:',
		'url' => '',
		'level' => '2'
	);

	$nav['mactrack_macauth.php:'] = array(
		'title' => __('Mac Address Authorization Utility', 'mactrack'),
		'mapping' => 'index.php:',
		'url' => 'mactrack_macauth.php',
		'level' => '1'
	);

	$nav['mactrack_macauth.php:edit'] = array(
		'title' => __('(Edit)', 'mactrack'),
		'mapping' => 'index.php:,mactrack_macauth.php:',
		'url' => '',
		'level' => '2'
	);

	$nav['mactrack_macauth.php:actions'] = array(
		'title' => __('Actions', 'mactrack'),
		'mapping' => 'index.php:,mactrack_macauth.php:',
		'url' => '',
		'level' => '2'
	);

	$nav['mactrack_vendormacs.php:'] = array(
		'title' => __('Device Tracking Vendor Macs', 'mactrack'),
		'mapping' => 'index.php:',
		'url' => 'mactrack_vendormacs.php',
		'level' => '1'
	);

	$nav['mactrack_view_macs.php:'] = array(
		'title' => __('Device Tracking View Macs', 'mactrack'),
		'mapping' => '',
		'url' => 'mactrack_view_macs.php',
		'level' => '0'
	);

	$nav['mactrack_view_macs.php:actions'] = array(
		'title' => __('Actions', 'mactrack'),
		'mapping' => 'mactrack_view_macs.php:',
		'url' => '',
		'level' => '1'
	);

	$nav['mactrack_view_dot1x.php:'] = array(
		'title' => __('Device Tracking Dot1x View', 'mactrack'),
		'mapping' => '',
		'url' => 'mactrack_view_dot1x.php',
		'level' => '0'
	);

	$nav['mactrack_view_arp.php:'] = array(
		'title' => __('Device Tracking IP Address Viewer', 'mactrack'),
		'mapping' => '',
		'url' => 'mactrack_view_arp.php',
		'level' => '0'
	);

	$nav['mactrack_view_interfaces.php:'] = array(
		'title' => __('Device Tracking View Interfaces', 'mactrack'),
		'mapping' => '',
		'url' => 'mactrack_view_interfaces.php',
		'level' => '0'
	);

	$nav['mactrack_view_sites.php:'] = array(
		'title' => __('Device Tracking View Sites', 'mactrack'),
		'mapping' => '',
		'url' => 'mactrack_view_sites.php',
		'level' => '0'
	);

	$nav['mactrack_view_ips.php:'] = array(
		'title' => __('Device Tracking View IP Ranges', 'mactrack'),
		'mapping' => '',
		'url' => 'mactrack_view_ips.php',
		'level' => '0'
	);

	$nav['mactrack_view_devices.php:'] = array(
		'title' => __('Device Tracking View Devices', 'mactrack'),
		'mapping' => '',
		'url' => 'mactrack_view_devices.php',
		'level' => '0'
	);

	$nav['mactrack_utilities.php:'] = array(
		'title' => __('Device Tracking Utilities', 'mactrack'),
		'mapping' => 'index.php:',
		'url' => 'mactrack_utilities.php',
		'level' => '1'
	);

	$nav['mactrack_utilities.php:mactrack_utilities_perform_db_maint'] = array(
		'title' => __('Perform Database Maintenance', 'mactrack'),
		'mapping' => 'index.php:,mactrack_utilities.php:',
		'url' => 'mactrack_utilities.php',
		'level' => '2'
	);

	$nav['mactrack_utilities.php:mactrack_utilities_purge_scanning_funcs'] = array(
		'title' => __('Refresh Scanning Functions', 'mactrack'),
		'mapping' => 'index.php:,mactrack_utilities.php:',
		'url' => 'mactrack_utilities.php',
		'level' => '2'
	);

	$nav['mactrack_utilities.php:mactrack_utilities_truncate_ports_table'] = array(
		'title' => __('Truncate Port Results Table', 'mactrack'),
		'mapping' => 'index.php:,mactrack_utilities.php:',
		'url' => 'mactrack_utilities.php',
		'level' => '2'
	);

	$nav['mactrack_utilities.php:mactrack_utilities_purge_aggregated_data'] = array(
		'title' => __('Truncate Aggregated Port Results Table', 'mactrack'),
		'mapping' => 'index.php:,mactrack_utilities.php:',
		'url' => 'mactrack_utilities.php',
		'level' => '2'
	);

	$nav['mactrack_utilities.php:mactrack_utilities_recreate_aggregated_data'] = array(
		'title' => __('Truncate and Re-create Aggregated Port Results Table', 'mactrack'),
		'mapping' => 'index.php:,mactrack_utilities.php:',
		'url' => 'mactrack_utilities.php',
		'level' => '2'
	);

	$nav['mactrack_utilities.php:mactrack_proc_status'] = array(
		'title' => __('View Device Tracking Process Status', 'mactrack'),
		'mapping' => 'index.php:,mactrack_utilities.php:',
		'url' => 'mactrack_utilities.php',
		'level' => '2'
	);

	$nav['mactrack_utilities.php:mactrack_refresh_oui_database'] = array(
		'title' => __('Refresh/Update Vendor MAC Database from IEEE', 'mactrack'),
		'mapping' => 'index.php:,mactrack_utilities.php:',
		'url' => 'mactrack_utilities.php',
		'level' => '2'
	);

	$nav['mactrack_view_graphs.php:'] = array(
		'title' => __('Device Tracking Graph Viewer', 'mactrack'),
		'mapping' => '',
		'url' => 'mactrack_view_graphs.php',
		'level' => '0'
	);

	$nav['mactrack_view_graphs.php:preview'] = array(
		'title' => __('Device Tracking Graph Viewer', 'mactrack'),
		'mapping' => '',
		'url' => 'mactrack_view_graphs.php',
		'level' => '0'
	);

	return $nav;
}

function mactrack_show_tab() {
	global $config, $user_auth_realm_filenames;

	include_once($config['base_path'] . '/plugins/mactrack/lib/mactrack_functions.php');

	if (!isset_request_var('report')) {
		set_request_var('report', 'sites');
	}

	if (api_user_realm_auth('mactrack_view_macs.php')) {
		if (substr_count($_SERVER['REQUEST_URI'], 'mactrack_view_')) {
			print '<a href="' . html_escape($config['url_path'] . 'plugins/mactrack/mactrack_view_' . get_nfilter_request_var('report') . '.php') . '"><img src="' . $config['url_path'] . 'plugins/mactrack/images/tab_mactrack_down.png" alt="' . __('MacTrack', 'mactrack') . '"></a>';
		} else {
			print '<a href="' . html_escape($config['url_path'] . 'plugins/mactrack/mactrack_view_' . get_nfilter_request_var('report') . '.php') . '"><img src="' . $config['url_path'] . 'plugins/mactrack/images/tab_mactrack.png" alt="' . __('MacTrack', 'mactrack') . '"></a>';
		}
	}
}

function mactrack_config_arrays() {
	global $mactrack_device_types, $mactrack_search_types, $messages;
	global $menu, $menu_glyphs, $config, $rows_selector;
	global $mactrack_poller_frequencies, $mactrack_data_retention, $refresh_interval;
	global $mactrack_macauth_frequencies, $mactrack_duplexes, $mactrack_update_policies;

	if (isset($_SESSION['mactrack_message']) && $_SESSION['mactrack_message'] != '') {
		$messages['mactrack_message'] = array('message' => $_SESSION['mactrack_message'], 'type' => 'info');
		kill_session_var('mactrack_message');
	}

	$refresh_interval = array(
		5   => __('%d Seconds', 5, 'mactrack'),
		10  => __('%d Seconds', 10, 'mactrack'),
		20  => __('%d Seconds', 20, 'mactrack'),
		30  => __('%d Seconds', 30, 'mactrack'),
		60  => __('%d Minute', 1, 'mactrack'),
		300 => __('%d Minutes', 5, 'mactrack')
	);

	$mactrack_device_types = array(
		1 => __('Switch/Hub', 'mactrack'),
		2 => __('Switch/Router', 'mactrack'),
		3 => __('Router', 'mactrack')
	);

	$mactrack_search_types = array(
		1 => '',
		2 => __('Matches', 'mactrack'),
		3 => __('Contains', 'mactrack'),
		4 => __('Begins With', 'mactrack'),
		5 => __('Does Not Contain', 'mactrack'),
		6 => __('Does Not Begin With', 'mactrack'),
		7 => __('Is Null', 'mactrack'),
		8 => __('Is Not Null', 'mactrack')
	);

	$mactrack_duplexes = array(
		1 => __('Unknown', 'mactrack'),
		2 => __('Half', 'mactrack'),
		3 => __('Full', 'mactrack')
	);

	$mactrack_update_policies = array(
		1 => __('None', 'mactrack'),
		2 => __('Sync Cacti Device to Device Tracking Device', 'mactrack'),
		3 => __('Sync Device Tracking Device to Cacti Device', 'mactrack')
	);

	$rows_selector = array(
		-1   => __('Default', 'mactrack'),
		10   => '10',
		15   => '15',
		20   => '20',
		30   => '30',
		50   => '50',
		100  => '100',
		500  => '500',
		1000 => '1000',
		-2   => __('All', 'mactrack')
	);

	$mactrack_poller_frequencies = array(
		'disabled' => __('Disabled', 'mactrack'),
		'10'       => __('Every %d Minutes', 10, 'mactrack'),
		'15'       => __('Every %d Minutes', 15, 'mactrack'),
		'20'       => __('Every %d Minutes', 20, 'mactrack'),
		'30'       => __('Every %d Minutes', 30, 'mactrack'),
		'60'       => __('Every %d Hour', 1, 'mactrack'),
		'120'      => __('Every %d Hours', 2, 'mactrack'),
		'240'      => __('Every %d Hours', 4, 'mactrack'),
		'480'      => __('Every %d Hours', 8, 'mactrack'),
		'720'      => __('Every %d Hours', 12, 'mactrack'),
		'1440'     => __('Every Day', 'mactrack')
	);

	$mactrack_data_retention = array(
		'3'   => __('%d Days', 3, 'mactrack'),
		'7'   => __('%d Days', 7, 'mactrack'),
		'10'  => __('%d Days', 10, 'mactrack'),
		'14'  => __('%d Days', 14, 'mactrack'),
		'20'  => __('%d Days', 20, 'mactrack'),
		'30'  => __('%d Month', 1, 'mactrack'),
		'60'  => __('%d Months', 2, 'mactrack'),
		'120' => __('%d Months', 4, 'mactrack'),
		'240' => __('%d Months', 8, 'mactrack'),
		'365' => __('%d Year', 1, 'mactrack')
	);

	$mactrack_macauth_frequencies = array(
		'disabled' => __('Disabled', 'mactrack'),
		'0'        => __('On Scan Completion', 'mactrack'),
		'720'      => __('Every %d Hours', 12),
		'1440'     => __('Every Day', 'mactrack'),
		'2880'     => __('Every %d Days', 2),
		'10080'    => __('Every Week', 'mactrack')
	);

	$menu2 = array ();
	foreach ($menu as $temp => $temp2 ) {
		$menu2[$temp] = $temp2;
		if ($temp == __('Management')) {
			$menu2[__('Device Tracking', 'mactrack')]['plugins/mactrack/mactrack_sites.php']        = __('Sites', 'mactrack');
			$menu2[__('Device Tracking', 'mactrack')]['plugins/mactrack/mactrack_devices.php']      = __('Devices', 'mactrack');
			$menu2[__('Device Tracking', 'mactrack')]['plugins/mactrack/mactrack_snmp.php']         = __('SNMP Options', 'mactrack');
			$menu2[__('Device Tracking', 'mactrack')]['plugins/mactrack/mactrack_device_types.php'] = __('Device Types', 'mactrack');
			$menu2[__('Device Tracking', 'mactrack')]['plugins/mactrack/mactrack_vendormacs.php']   = __('Vendor Macs', 'mactrack');
			$menu2[__('Tracking Tools', 'mactrack')]['plugins/mactrack/mactrack_macwatch.php']      = __('Mac Watch', 'mactrack');
			$menu2[__('Tracking Tools', 'mactrack')]['plugins/mactrack/mactrack_macauth.php']       = __('Mac Authorizations', 'mactrack');
			$menu2[__('Tracking Tools', 'mactrack')]['plugins/mactrack/mactrack_utilities.php']     = __('Tracking Utilities', 'mactrack');
		}
	}
	$menu = $menu2;

	if (cacti_version_compare(CACTI_VERSION, '1.2', '<')) {
		$menu_glyphs[__('Device Tracking', 'mactrack')] = 'fa fa-shield';
	} else {
		$menu_glyphs[__('Device Tracking', 'mactrack')] = 'fa fa-shield-alt';
	}
	$menu_glyphs[__('Tracking Tools', 'mactrack')] = 'fa fa-bullhorn';
}

function mactrack_config_form () {
	global $fields_mactrack_device_type_edit, $fields_mactrack_device_edit, $fields_mactrack_site_edit;
	global $fields_mactrack_snmp_edit, $fields_mactrack_snmp_item, $fields_mactrack_snmp_item_edit;
	global $mactrack_device_types, $snmp_versions, $fields_mactrack_macw_edit, $fields_mactrack_maca_edit;
	global $snmp_priv_protocols, $snmp_auth_protocols;

	/* file: mactrack_device_types.php, action: edit */
	$fields_mactrack_device_type_edit = array(
	'spacer0' => array(
		'method' => 'spacer',
		'friendly_name' => __('Device Scanning Function Options', 'mactrack')
		),
	'description' => array(
		'method' => 'textbox',
		'friendly_name' => __('Description', 'mactrack'),
		'description' => __('Give this device type a meaningful description.', 'mactrack'),
		'value' => '|arg1:description|',
		'max_length' => '250'
		),
	'vendor' => array(
		'method' => 'textbox',
		'friendly_name' => __('Vendor', 'mactrack'),
		'description' => __('Fill in the name for the vendor of this device type.', 'mactrack'),
		'value' => '|arg1:vendor|',
		'max_length' => '250'
		),
	'device_type' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Device Type', 'mactrack'),
		'description' => __('Choose the type of device.', 'mactrack'),
		'value' => '|arg1:device_type|',
		'default' => 1,
		'array' => $mactrack_device_types
		),
	'sysDescr_match' => array(
		'method' => 'textbox',
		'friendly_name' => __('System Description Match', 'mactrack'),
		'description' => __('Provide key information to help Device Tracking detect the type of device.  The wildcard character is the \'&#37;\' sign.', 'mactrack'),
		'value' => '|arg1:sysDescr_match|',
		'max_length' => '250'
		),
	'sysObjectID_match' => array(
		'method' => 'textbox',
		'friendly_name' => __('Vendor SNMP Object ID Match', 'mactrack'),
		'description' => __('Provide key information to help Device Tracking detect the type of device.  The wildcard character is the \'&#37;\' sign.', 'mactrack'),
		'value' => '|arg1:sysObjectID_match|',
		'max_length' => '250'
		),
	'scanning_function' => array(
		'method' => 'drop_sql',
		'friendly_name' => __('MAC Address Scanning Function', 'mactrack'),
		'description' => __('The Device Tracking scanning function to call in order to obtain and store port details.  The function name is all that is required.  The following four parameters are assumed and will always be appended: \'my_function($site, &$device, $lowport, $highport)\'.  There is no function required for a pure router.', 'mactrack'),
		'value' => '|arg1:scanning_function|',
		'default' => 0,
		'none_value' => __('None', 'mactrack'),
		'sql' => 'select scanning_function as id, scanning_function as name from mac_track_scanning_functions where type="1" order by scanning_function'
		),
	'ip_scanning_function' => array(
		'method' => 'drop_sql',
		'friendly_name' => __('IP Address Scanning Function', 'mactrack'),
		'description' => __('The Device Tracking scanning function specific to Layer3 devices that track IP Addresses.', 'mactrack'),
		'value' => '|arg1:ip_scanning_function|',
		'default' => 0,
		'none_value' => __('None', 'mactrack'),
		'sql' => 'SELECT scanning_function AS id, scanning_function AS name FROM mac_track_scanning_functions WHERE type="2" ORDER BY scanning_function'
		),
	'dot1x_scanning_function' => array(
		'method' => 'drop_sql',
		'friendly_name' => __('802.1x Scanning Function', 'mactrack'),
		'description' => __('The Device Tracking scanning function specific to Switches with dot1x enabled.', 'mactrack'),
		'value' => '|arg1:dot1x_scanning_function|',
		'default' => '',
		'none_value' => __('None', 'mactrack'),
		'sql' => 'SELECT scanning_function AS id, scanning_function AS name FROM mac_track_scanning_functions WHERE type="3" ORDER BY scanning_function'
		),
	'serial_number_oid' => array(
		'method' => 'textbox',
		'friendly_name' => __('Serial Number Base OID', 'mactrack'),
		'description' => __('The SNMP OID used to obtain this device types serial number to be stored in the Device Tracking Asset Information table.', 'mactrack'),
		'value' => '|arg1:serial_number_oid|',
		'max_length' => '100',
		'default' => ''
		),
	'serial_number_oid_type' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Serial Number Collection Method', 'mactrack'),
		'description' => __('How is the serial number collected for this OID.  If \'SNMP Walk\', we assume multiple serial numbers.  If \'Get\', it will be only one..', 'mactrack'),
		'value' => '|arg1:serial_number_oid_method|',
		'default' => 'get',
		'array' => array('get' => __('SNMP Get', 'mactrack'), 'walk' => __('SNMP Walk', 'mactrack'))
		),
	'lowPort' => array(
		'method' => 'textbox',
		'friendly_name' => __('Low User Port Number', 'mactrack'),
		'description' => __('Provide the low user port number on this switch.  Leave 0 to allow the system to calculate it.', 'mactrack'),
		'value' => '|arg1:lowPort|',
		'default' => read_config_option('mt_port_lowPort'),
		'max_length' => '100',
		'size' => '10'
		),
	'highPort' => array(
		'method' => 'textbox',
		'friendly_name' => __('High User Port Number', 'mactrack'),
		'description' => __('Provide the low user port number on this switch.  Leave 0 to allow the system to calculate it.', 'mactrack'),
		'value' => '|arg1:highPort|',
		'default' => read_config_option('mt_port_highPort'),
		'max_length' => '100',
		'size' => '10'
		),
	'device_type_id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:device_type_id|'
		),
	'_device_type_id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:device_type_id|'
		),
	'save_component_device_type' => array(
		'method' => 'hidden',
		'value' => '1'
		)
	);

	/* file: mactrack_snmp.php, action: edit */
	$fields_mactrack_snmp_edit = array(
	'name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Name', 'mactrack'),
		'description' => __('Fill in the name of this SNMP option set.', 'mactrack'),
		'value' => '|arg1:name|',
		'default' => '',
		'max_length' => '100',
		'size' => '40'
		),
	);

	/* file: mactrack_snmp.php, action: item_edit */
	$fields_mactrack_snmp_item = array(
	'snmp_version' => array(
		'method' => 'drop_array',
		'friendly_name' => __('SNMP Version', 'mactrack'),
		'description' => __('Choose the SNMP version for this host.', 'mactrack'),
		'value' => '|arg1:snmp_version|',
		'default' => read_config_option('mt_snmp_ver'),
		'array' => $snmp_versions
		),
	'snmp_readstring' => array(
		'method' => 'textbox',
		'friendly_name' => __('SNMP Community String', 'mactrack'),
		'description' => __('Fill in the SNMP read community for this device.', 'mactrack'),
		'value' => '|arg1:snmp_readstring|',
		'default' => read_config_option('mt_snmp_community'),
		'max_length' => '100',
		'size' => '20'
		),
	'snmp_port' => array(
		'method' => 'textbox',
		'friendly_name' => __('SNMP Port', 'mactrack'),
		'description' => __('The UDP/TCP Port to poll the SNMP agent on.', 'mactrack'),
		'value' => '|arg1:snmp_port|',
		'max_length' => '8',
		'default' => read_config_option('mt_snmp_port'),
		'size' => '10'
		),
	'snmp_timeout' => array(
		'method' => 'textbox',
		'friendly_name' => __('SNMP Timeout', 'mactrack'),
		'description' => __('The maximum number of milliseconds Cacti will wait for an SNMP response (does not work with php-snmp support).', 'mactrack'),
		'value' => '|arg1:snmp_timeout|',
		'max_length' => '8',
		'default' => read_config_option('mt_snmp_timeout'),
		'size' => '10'
		),
	'snmp_retries' => array(
		'method' => 'textbox',
		'friendly_name' => __('SNMP Retries', 'mactrack'),
		'description' => __('The maximum number of attempts to reach a device via an SNMP readstring prior to giving up.', 'mactrack'),
		'value' => '|arg1:snmp_retries|',
		'max_length' => '8',
		'default' => read_config_option('mt_snmp_retries'),
		'size' => '10'
		),
	'max_oids' => array(
		'method' => 'textbox',
		'friendly_name' => __('Maximum OID\'s Per Get Request', 'mactrack'),
		'description' => __('Specified the number of OID\'s that can be obtained in a single SNMP Get request.', 'mactrack'),
		'value' => '|arg1:max_oids|',
		'max_length' => '8',
		'default' => read_config_option('max_get_size'),
		'size' => '15'
		),
	'snmp_username' => array(
		'method' => 'textbox',
		'friendly_name' => __('SNMP Username (v3)', 'mactrack'),
		'description' => __('SNMP v3 username for this device.', 'mactrack'),
		'value' => '|arg1:snmp_username|',
		'default' => read_config_option('snmp_username'),
		'max_length' => '50',
		'size' => '15'
		),
	'snmp_password' => array(
		'method' => 'textbox_password',
		'friendly_name' => __('SNMP Password (v3)', 'mactrack'),
		'description' => __('SNMP v3 password for this device.', 'mactrack'),
		'value' => '|arg1:snmp_password|',
		'default' => read_config_option('snmp_password'),
		'max_length' => '50',
		'size' => '15'
		),
	'snmp_auth_protocol' => array(
		'method' => 'drop_array',
		'friendly_name' => __('SNMP Auth Protocol (v3)', 'mactrack'),
		'description' => __('Choose the SNMPv3 Authorization Protocol.', 'mactrack'),
		'value' => '|arg1:snmp_auth_protocol|',
		'default' => read_config_option('snmp_auth_protocol'),
		'array' => $snmp_auth_protocols,
		),
	'snmp_priv_passphrase' => array(
		'method' => 'textbox',
		'friendly_name' => __('SNMP Privacy Passphrase (v3)', 'mactrack'),
		'description' => __('Choose the SNMPv3 Privacy Passphrase.', 'mactrack'),
		'value' => '|arg1:snmp_priv_passphrase|',
		'default' => read_config_option('snmp_priv_passphrase'),
		'max_length' => '200',
		'size' => '40'
		),
	'snmp_priv_protocol' => array(
		'method' => 'drop_array',
		'friendly_name' => __('SNMP Privacy Protocol (v3)', 'mactrack'),
		'description' => __('Choose the SNMPv3 Privacy Protocol.', 'mactrack'),
		'value' => '|arg1:snmp_priv_protocol|',
		'default' => read_config_option('snmp_priv_protocol'),
		'array' => $snmp_priv_protocols,
		),
	'snmp_context' => array(
		'method' => 'textbox',
		'friendly_name' => __('SNMP Context (v3)', 'mactrack'),
		'description' => __('Enter the SNMP v3 Context to use for this device.', 'mactrack'),
		'value' => '|arg1:snmp_context|',
		'default' => '',
		'max_length' => '64',
		'size' => '40'
		),
	'snmp_engine_id' => array(
		'method' => 'textbox',
		'friendly_name' => __('SNMP Engine ID (v3)', 'mactrack'),
		'description' => __('Enter the SNMP v3 Engine ID to use for this device.', 'mactrack'),
		'value' => '|arg1:snmp_engine_id|',
		'default' => '',
		'max_length' => '64',
		'size' => '40'
		),
	);

	/* file: mactrack_devices.php, action: edit */
	$fields_mactrack_device_edit = array(
	'spacer0' => array(
		'method' => 'spacer',
		'friendly_name' => __('General Device Settings', 'mactrack')
		),
	'device_name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Device Name', 'mactrack'),
		'description' => __('Give this device a meaningful name.', 'mactrack'),
		'value' => '|arg1:device_name|',
		'max_length' => '250'
		),
	'hostname' => array(
		'method' => 'textbox',
		'friendly_name' => __('Hostname', 'mactrack'),
		'description' => __('Fill in the fully qualified hostname for this device.', 'mactrack'),
		'value' => '|arg1:hostname|',
		'max_length' => '250'
		),
	'host_id' => array(
		'friendly_name' => __('Related Cacti Host', 'mactrack'),
		'description' => __('Given Device Tracking Host is connected to this Cacti Host.', 'mactrack'),
		#'method' => 'view',
		'method' => 'drop_sql',
		'value' => '|arg1:host_id|',
		'none_value' => __('None', 'mactrack'),
		'sql' => 'select id,CONCAT_WS("",description," (",hostname,")") as name from host order by description,hostname'
		),
	'scan_type' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Scan Type', 'mactrack'),
		'description' => __('Choose the scan type you wish to perform on this device.', 'mactrack'),
		'value' => '|arg1:scan_type|',
		'default' => 1,
		'array' => $mactrack_device_types
		),
	'site_id' => array(
		'method' => 'drop_sql',
		'friendly_name' => __('Site Name', 'mactrack'),
		'description' => __('Choose the site to associate with this device.', 'mactrack'),
		'value' => '|arg1:site_id|',
		'none_value' => __('None', 'mactrack'),
		'sql' => 'select site_id as id,site_name as name from mac_track_sites order by name'
		),
	'notes' => array(
		'method' => 'textarea',
		'friendly_name' => __('Device Notes', 'mactrack'),
		'description' => __('This field value is useful to save general information about a specific device.', 'mactrack'),
		'class' => 'textAreaNotes',
		'textarea_rows' => '3',
		'textarea_cols' => '80',
		'value' => '|arg1:notes|',
		'max_length' => '255'
		),
	'disabled' => array(
		'method' => 'checkbox',
		'friendly_name' => __('Disable Device', 'mactrack'),
		'description' => __('Check this box to disable all checks for this host.', 'mactrack'),
		'value' => '|arg1:disabled|',
		'default' => '',
		'form_id' => false
		),
	'spacer1' => array(
		'method' => 'spacer',
		'friendly_name' => __('Switch/Hub, Switch/Router Settings', 'mactrack')
		),
	'ignorePorts' => array(
		'method' => 'textarea',
		'friendly_name' => __('Ports to Ignore', 'mactrack'),
		'description' => __('Provide a list of ports on a specific switch/hub whose MAC results should be ignored.  Ports such as link/trunk ports that can not be distinguished from other user ports are examples.  Each port number must be separated by a colon, pipe, or a space \':\', \'|\', \' \'.  For example, \'Fa0/1: Fa1/23\' or \'Fa0/1 Fa1/23\' would be acceptable for some manufacturers switch types.', 'mactrack'),
		'value' => '|arg1:ignorePorts|',
		'default' => '',
		'class' => 'textAreaNotes',
		'textarea_rows' => '3',
		'textarea_cols' => '80',
		'max_length' => '255'
		),
	'spacer2' => array(
		'method' => 'spacer',
		'friendly_name' => __('SNMP Options', 'mactrack')
		),
	'snmp_options' => array(
		'method' => 'drop_sql',
		'friendly_name' => __('SNMP Options', 'mactrack'),
		'description' => __('Select a set of SNMP options to try.', 'mactrack'),
		'value' => '|arg1:snmp_options|',
		'none_value' => __('None', 'mactrack'),
		'sql' => 'select * from mac_track_snmp order by name'
		),
	'snmp_readstrings' => array(
		'method' => 'view',
		'friendly_name' => __('Read Strings', 'mactrack'),
		'description' => __('<strong>DEPRECATED:</strong> SNMP community strings', 'mactrack'),
		'value' => '|arg1:snmp_readstrings|',
		),
	'spacer3' => array(
		'method' => 'spacer',
		'friendly_name' => __('Specific SNMP Settings', 'mactrack')
		),
	);

	$fields_mactrack_device_edit += $fields_mactrack_snmp_item;

	$fields_mactrack_device_edit += array(
	'spacer4' => array(
		'method' => 'spacer',
		'friendly_name' => __('Connectivity Options', 'mactrack')
		),
	'term_type' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Terminal Type', 'mactrack'),
		'description' => __('Choose the terminal type that you use to connect to this device.', 'mactrack'),
		'value' => '|arg1:term_type|',
		'default' => 1,
		'array' => array(
			0 => __('None', 'mactrack'),
			1 => __('Telnet', 'mactrack'),
			2 => __('SSH', 'mactrack'),
			3 => __('HTTP', 'mactrack'),
			4 => __('HTTPS', 'mactrack'))
		),
	'user_name' => array(
		'method' => 'textbox',
		'friendly_name' => __('User Name', 'mactrack'),
		'description' => __('The user name to be used for your custom authentication method.  Examples include SSH, RSH, HTML, etc.', 'mactrack'),
		'value' => '|arg1:user_name|',
		'default' => '',
		'max_length' => '40',
		'size' => '20'
		),
	'user_password' => array(
		'method' => 'textbox_password',
		'friendly_name' => __('Password', 'mactrack'),
		'description' => __('The password to be used for your custom authentication.', 'mactrack'),
		'value' => '|arg1:user_password|',
		'default' => '',
		'max_length' => '40',
		'size' => '20'
		),
	'private_key_path' => array(
		'method' => 'filepath',
		'friendly_name' => __('Private Key Path', 'mactrack'),
		'description' => __('The path to the private key used for SSH authentication.', 'mactrack'),
		'value' => '|arg1:private_key_path|',
		'default' => '',
		'max_length' => '128',
		'size' => '40'
		),
	'device_id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:device_id|'
		),
	'_device_id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:device_id|'
		),
	'save_component_device' => array(
		'method' => 'hidden',
		'value' => '1'
		)
	);

	/* file: mactrack_snmp.php, action: item_edit */
	$fields_mactrack_snmp_item_edit = $fields_mactrack_snmp_item + array(
	'sequence' => array(
		'method' => 'view',
		'friendly_name' => __('Sequence', 'mactrack'),
		'description' => __('Sequence of Item.', 'mactrack'),
		'value' => '|arg1:sequence|'),
	);

	/* file: mactrack_sites.php, action: edit */
	$fields_mactrack_site_edit = array(
	'spacer0' => array(
		'method' => 'spacer',
		'friendly_name' => __('General Site Settings', 'mactrack')
		),
	'site_name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Site Name', 'mactrack'),
		'description' => __('Please enter a reasonable name for this site.', 'mactrack'),
		'value' => '|arg1:site_name|',
		'size' => '70',
		'max_length' => '250'
		),
	'customer_contact' => array(
		'method' => 'textbox',
		'friendly_name' => __('Primary Customer Contact', 'mactrack'),
		'description' => __('The principal customer contact name and number for this site.', 'mactrack'),
		'value' => '|arg1:customer_contact|',
		'size' => '70',
		'max_length' => '150'
		),
	'netops_contact' => array(
		'method' => 'textbox',
		'friendly_name' => __('NetOps Contact', 'mactrack'),
		'description' => __('Please principal network support contact name and number for this site.', 'mactrack'),
		'value' => '|arg1:netops_contact|',
		'size' => '70',
		'max_length' => '150'
		),
	'facilities_contact' => array(
		'method' => 'textbox',
		'friendly_name' => __('Facilities Contact', 'mactrack'),
		'description' => __('Please principal facilities/security contact name and number for this site.', 'mactrack'),
		'value' => '|arg1:facilities_contact|',
		'size' => '70',
		'max_length' => '150'
		),
	'site_info' => array(
		'method' => 'textarea',
		'friendly_name' => __('Site Information', 'mactrack'),
		'class' => 'textAreaNotes',
		'textarea_rows' => '3',
		'textarea_cols' => '80',
		'description' => __('Provide any site-specific information, in free form, that allows you to better manage this location.', 'mactrack'),
		'value' => '|arg1:site_info|',
		'max_length' => '255'
		),
	'site_id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:site_id|'
		),
	'_site_id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:site_id|'
		),
	'save_component_site' => array(
		'method' => 'hidden',
		'value' => '1'
		)
	);

	/* file: mactrack_macwatch.php, action: edit */
	$fields_mactrack_macw_edit = array(
	'spacer0' => array(
		'method' => 'spacer',
		'friendly_name' => __('General Mac Address Tracking Settings', 'mactrack')
		),
	'mac_address' => array(
		'method' => 'textbox',
		'friendly_name' => __('MAC Address', 'mactrack'),
		'description' => __('Please enter the MAC Address to be watched for.', 'mactrack'),
		'value' => '|arg1:mac_address|',
		'default' => '',
		'max_length' => '40'
		),
	'name' => array(
		'method' => 'textbox',
		'friendly_name' => __('MAC Tracking Name/Email Subject', 'mactrack'),
		'description' => __('Please enter a reasonable name for this MAC Tracking entry.  This information will be in the subject line of your Email', 'mactrack'),
		'value' => '|arg1:name|',
		'size' => '70',
		'max_length' => '250'
		),
	'description' => array(
		'friendly_name' => __('MacWatch Default Body', 'mactrack'),
		'description' => htmlspecialchars(__('The Email body preset for Device Tracking MacWatch Emails.  The body can contain any valid html tags.  It also supports replacement tags that will be processed when sending an Email.  Valid tags include <IP>, <MAC>, <TICKET>, <SITENAME>, <DEVICEIP>, <PORTNAME>, <PORTNUMBER>, <DEVICENAME>.', 'mactrack')),
		'method' => 'textarea',
		'class' => 'textAreaNotes',
		'value' => '|arg1:description|',
		'default' => __('Mac Address <MAC> found at IP Address <IP> for Ticket Number: <TICKET>.<br>The device is located at<br>Site: <SITENAME>, Device <DEVICENAME>, IP <DEVICEIP>, Port <PORTNUMBER>, and Port Name <PORTNAME>', 'mactrack'),
		'max_length' => '512',
		'textarea_rows' => '5',
		'textarea_cols' => '80',
		),
	'ticket_number' => array(
		'method' => 'textbox',
		'friendly_name' => __('Ticket Number', 'mactrack'),
		'description' => __('Ticket number for cross referencing with your corporate help desk system(s).', 'mactrack'),
		'value' => '|arg1:ticket_number|',
		'size' => '70',
		'max_length' => '150'
		),
	'notify_schedule' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Notification Schedule', 'mactrack'),
		'description' => __('Choose how often an Email should be generated for this Mac Watch item.', 'mactrack'),
		'value' => '|arg1:notify_schedule|',
		'default' => '1',
		'array' => array(
			1    => __('First Occurrence Only', 'mactrack'),
			2    => __('All Occurrences', 'mactrack'),
			60   => __('Every Hour', 'mactrack'),
			240  => __('Every %d Hours', 4, 'mactrack'),
			1800 => __('Every %d Hours', 12, 'mactrack'),
			3600 => __('Every Day', 'mactrack'))
		),
	'email_addresses' => array(
		'method' => 'textbox',
		'friendly_name' => __('Email Addresses', 'mactrack'),
		'description' => __('Enter a semicolon separated of Email addresses that will be notified where this MAC address is.', 'mactrack'),
		'value' => '|arg1:email_addresses|',
		'size' => '90',
		'max_length' => '255'
		),
	'mac_id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:mac_id|'
		),
	'_mac_id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:mac_id|'
		),
	'save_component_macw' => array(
		'method' => 'hidden',
		'value' => '1'
		)
	);

	/* file: mactrack_macwatch.php, action: edit */
	$fields_mactrack_maca_edit = array(
	'spacer0' => array(
		'method' => 'spacer',
		'friendly_name' => __('General Mac Address Authorization Settings', 'mactrack')
		),
	'mac_address' => array(
		'method' => 'textbox',
		'friendly_name' => __('MAC Address Match', 'mactrack'),
		'description' => __('Please enter the MAC Address or Mac Address Match string to be automatically authorized.  If you wish to authorize a group of MAC Addresses, you can use the wildcard character of \'&#37;\' anywhere in the MAC Address.', 'mactrack'),
		'value' => '|arg1:mac_address|',
		'default' => '',
		'max_length' => '40'
		),
	'description' => array(
		'method' => 'textarea',
		'friendly_name' => __('Reason', 'mactrack'),
		'class' => 'textAreaNotes',
		'description' => __('Please add a reason for this entry.', 'mactrack'),
		'value' => '|arg1:description|',
		'textarea_rows' => '4',
		'textarea_cols' => '80'
		),
	'mac_id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:mac_id|'
		),
	'_mac_id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:mac_id|'
		),
	'save_component_maca' => array(
		'method' => 'hidden',
		'value' => '1'
		)
	);
}

function convert_readstrings() {
	global $config;

	$sql = 'SELECT DISTINCT ' .
		'snmp_readstrings, ' .
		'snmp_version, ' .
		'snmp_port, ' .
		'snmp_timeout, ' .
		'snmp_retries ' .
		'FROM mac_track_devices';

	$devices = db_fetch_assoc($sql);

	if (cacti_sizeof($devices)) {
		$i = 0;
		foreach($devices as $device) {
			# create new SNMP Option Set
			unset($save);
			$save['id'] = 0;
			$save['name'] = 'Custom_' . $i++;
			$snmp_id = sql_save($save, 'mac_track_snmp');

			# add each single option derived from readstrings
			$read_strings = explode(':',$device['snmp_readstrings']);
			if (cacti_sizeof($read_strings)) {
				foreach($read_strings as $snmp_readstring) {
					unset($save);
					$save['id']						= 0;
					$save['snmp_id'] 				= $snmp_id;
					$save['sequence'] 				= get_sequence('', 'sequence', 'mac_track_snmp_items', 'snmp_id=' . $snmp_id);

					$save['snmp_readstring'] 		= $snmp_readstring;
					$save['snmp_version'] 			= $device['snmp_version'];
					$save['snmp_port']				= $device['snmp_port'];
					$save['snmp_timeout']			= $device['snmp_timeout'];
					$save['snmp_retries']			= $device['snmp_retries'];
					$save['snmp_username']			= '';
					$save['snmp_password']			= '';
					$save['snmp_auth_protocol']		= '';
					$save['snmp_priv_passphrase']	= '';
					$save['snmp_priv_protocol']		= '';
					$save['snmp_context']			= '';
					$save['snmp_engine_id']         = '';
					$save['max_oids']				= '';

					$item_id = sql_save($save, 'mac_track_snmp_items');
				}
			} # each readstring added as SNMP Option item

			# now, let's find all devices, that used this snmp_readstrings
			$sql = 'UPDATE mac_track_devices SET snmp_options=' . $snmp_id .
					" WHERE snmp_readstrings='" . $device['snmp_readstrings'] .
					"' AND snmp_version=" . $device['snmp_version'] .
					' AND snmp_port=' . $device['snmp_port'] .
					' AND snmp_timeout=' . $device['snmp_timeout'] .
					' AND snmp_retries=' . $device['snmp_retries'];

			$ok = db_execute($sql);
		}
	}
	db_execute("REPLACE INTO settings (name,value) VALUES ('mt_convert_readstrings', 'on')");
	# we keep the field:snmp_readstrings in mac_track_devices, it should be deprecated first
	# next mactrack release may delete that field, then
}
