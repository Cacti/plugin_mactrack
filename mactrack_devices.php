<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2014 The Cacti Group                                 |
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
chdir('../../');
include("./include/auth.php");
include_once("./lib/snmp.php");
include_once("./plugins/mactrack/lib/mactrack_functions.php");
include_once("./plugins/mactrack/mactrack_actions.php");

define("MAX_DISPLAY_PAGES", 21);

$device_actions = array(
	1 => "Delete",
	2 => "Enable",
	3 => "Disable",
	4 => "Change SNMP Options",
	5 => "Change Device Port Values",
	6 => "Connect to Cacti Host via Hostname",
	7 => "Copy SNMP Settings from Cacti Host"
	);

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

/* correct for a cancel button */
if (isset($_REQUEST["cancel_x"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
	case 'save':
		form_mactrack_save();

		break;
	case 'actions':
		form_mactrack_actions();

		break;
	case 'edit':
		include_once("./include/top_header.php");

		mactrack_device_edit();

		include_once("./include/bottom_footer.php");
		break;
	case 'import':
		include_once("./include/top_header.php");

		mactrack_device_import();

		include_once("./include/bottom_footer.php");
		break;
	default:
		if (isset($_REQUEST["import_x"])) {
			header("Location: mactrack_devices.php?action=import");
		}elseif (isset($_REQUEST["export_x"])) {
			mactrack_device_export();
		}else{
			include_once("./include/top_header.php");
			print "<script type='text/javascript' src='" . $config["url_path"] . "plugins/mactrack/mactrack.js'></script>";

			mactrack_device();

			include_once("./include/bottom_footer.php");
		}
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_mactrack_save() {
	global $config;

	if ((isset($_POST["save_component_device"])) && (empty($_POST["add_dq_y"]))) {
		$device_id = api_mactrack_device_save($_POST["device_id"], $_POST["host_id"], $_POST["site_id"],
			$_POST["hostname"], $_POST["device_name"], $_POST["scan_type"],
			$_POST["snmp_options"], $_POST["snmp_readstring"],
			$_POST["snmp_version"], $_POST["snmp_username"], $_POST["snmp_password"], $_POST["snmp_auth_protocol"],
			$_POST["snmp_priv_passphrase"], $_POST["snmp_priv_protocol"], $_POST["snmp_context"],
			$_POST["snmp_port"], $_POST["snmp_timeout"],
			$_POST["snmp_retries"], $_POST["max_oids"], $_POST["ignorePorts"],
			$_POST["notes"], $_POST["user_name"], $_POST["user_password"], $_POST["term_type"], $_POST["private_key_path"],
			(isset($_POST["disabled"]) ? $_POST["disabled"] : ""));

		header("Location: mactrack_devices.php?action=edit&device_id=" . (empty($device_id) ? $_POST["device_id"] : $device_id));
	}

	if (isset($_POST["save_component_import"])) {
		if (($_FILES["import_file"]["tmp_name"] != "none") && ($_FILES["import_file"]["tmp_name"] != "")) {
			/* file upload */
			$csv_data = file($_FILES["import_file"]["tmp_name"]);

			/* obtain debug information if it's set */
			$debug_data = mactrack_device_import_processor($csv_data);
			if(sizeof($debug_data) > 0) {
				$_SESSION["import_debug_info"] = $debug_data;
			}
		}else{
			header("Location: mactrack_devices.php?action=import"); exit;
		}

		header("Location: mactrack_devices.php?action=import");
	}
}

/* ------------------------
    The "actions" function
   ------------------------ */

function form_mactrack_actions() {
	global $config, $device_actions, $fields_mactrack_device_edit, $fields_mactrack_snmp_item;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_post('drp_action'));
	/* ==================================================== */

	if (defined('CACTI_BASE_PATH')) {
		$config["base_path"] = CACTI_BASE_PATH;
	}

	include_once($config["base_path"] . "/lib/functions.php");

	/* if we are to save this form, instead of display it */
	if (isset($_POST["selected_items"])) {
		$selected_items = unserialize(stripslashes($_POST["selected_items"]));

		if ($_POST["drp_action"] == "2") { /* Enable Selected Devices */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				db_execute("update mac_track_devices set disabled='' where device_id='" . $selected_items[$i] . "'");
			}
		}elseif ($_POST["drp_action"] == "3") { /* Disable Selected Devices */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				db_execute("update mac_track_devices set disabled='on' where device_id='" . $selected_items[$i] . "'");
			}
		}elseif ($_POST["drp_action"] == "4") { /* change snmp options */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				reset($fields_mactrack_device_edit);
				while (list($field_name, $field_array) = each($fields_mactrack_device_edit)) {
					if (isset($_POST["t_$field_name"])) {
						db_execute("update mac_track_devices set $field_name = '" . $_POST[$field_name] . "' where device_id='" . $selected_items[$i] . "'");
					}
				}
			}
		}elseif ($_POST["drp_action"] == "5") { /* change port settings for multiple devices */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				reset($fields_mactrack_device_edit);
				while (list($field_name, $field_array) = each($fields_host_edit)) {
					if (isset($_POST["t_$field_name"])) {
						db_execute("update mac_track_devices set $field_name = '" . $_POST[$field_name] . "' where id='" . $selected_items[$i] . "'");
					}
				}
			}
		}elseif ($_POST["drp_action"] == "6") { /* Connect Selected Devices */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				$cacti_host = db_fetch_row("SELECT host.id, host.description FROM mac_track_devices " .
									"LEFT JOIN host ON (mac_track_devices.hostname=host.hostname) " .
									"WHERE mac_track_devices.device_id=" . $selected_items[$i]);
				db_execute("UPDATE mac_track_devices SET " .
							"host_id=" . $cacti_host["id"] .
							", device_name='" . $cacti_host["description"] .
							"' WHERE device_id='" . $selected_items[$i] . "'");
			}
		}elseif ($_POST["drp_action"] == "7") { /* Copy SNMP Settings */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				cacti_log("Item: " . $selected_items[$i], false, "MACTRACK");
				$sql = "SELECT " .
									"host.id, " .
									"host.snmp_version, " .
									"host.snmp_community as snmp_readstring, " .
									"host.snmp_port, " .
									"host.snmp_timeout, " .
									"host.ping_retries as snmp_retries, " .
									"host.max_oids, " .
									"host.snmp_username, " .
									"host.snmp_password, " .
									"host.snmp_auth_protocol, " .
									"host.snmp_priv_passphrase, " .
									"host.snmp_priv_protocol, " .
									"host.snmp_context " .
									"FROM mac_track_devices " .
									"LEFT JOIN host ON (mac_track_devices.hostname=host.hostname) " .
									"WHERE mac_track_devices.device_id=" . $selected_items[$i];
				$cacti_host = db_fetch_row($sql);
				cacti_log("SQL: " . $sql, false, "MACTRACK");
				if (isset($cacti_host["id"])) {
					reset($fields_mactrack_snmp_item);
					$updates = "";
					while (list($field_name, $field_array) = each($fields_mactrack_snmp_item)) {
						$updates .= (strlen($updates) ? ", " : " "). $field_name . "='" . $cacti_host[$field_name] . "'";
					}
					if(strlen($updates)) {
						cacti_log("UPDATE mac_track_devices SET " . $updates .	" WHERE device_id='" . $selected_items[$i] . "'", false, "MACTRACK");
						db_execute("UPDATE mac_track_devices SET " . $updates .	" WHERE device_id='" . $selected_items[$i] . "'");
					}
				} else {
					# skip silently; possible enhacement: tell the user what we did
				}
			}
		}elseif ($_POST["drp_action"] == "1") { /* delete */
			for ($i=0; $i<count($selected_items); $i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				api_mactrack_device_remove($selected_items[$i]);
			}
		}

		header("Location: mactrack_devices.php");
		exit;
	}

	/* setup some variables */
	$device_list = ""; $i = 0;

	/* loop through each of the host templates selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (ereg("^chk_([0-9]+)$", $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$device_info = db_fetch_row("SELECT hostname, device_name FROM mac_track_devices WHERE device_id=" . $matches[1]);
			$device_list .= "<li>" . $device_info["device_name"] . " (" . $device_info["hostname"] . ")<br>";
			$device_array[$i] = $matches[1];
		}

		$i++;
	}

	include_once("./include/top_header.php");

	html_start_box("<strong>" . $device_actions{$_POST["drp_action"]} . "</strong>", "60%", "", "3", "center", "");

	print "<form action='mactrack_devices.php' method='post'>\n";

	if ($_POST["drp_action"] == "2") { /* Enable Devices */
		print "	<tr>
				<td colspan='2' class='textArea'>
					<p>To enable the following devices, press the \"yes\" button below.</p>
					<p><ul>$device_list</ul></p>
				</td>
				</tr>";
	}elseif ($_POST["drp_action"] == "3") { /* Disable Devices */
		print "	<tr>
				<td colspan='2' class='textArea'>
					<p>To disable the following devices, press the \"yes\" button below.</p>
					<p><ul>$device_list</ul></p>
				</td>
				</tr>";
	}elseif ($_POST["drp_action"] == "4") { /* change snmp options */
		print "	<tr>
				<td colspan='2' class='textArea'>
					<p>To change SNMP parameters for the following devices, check the box next to the fields
					you want to update, fill in the new value, and click Save.</p>
					<p><ul>$device_list</ul></p>
				</td>
				</tr>";
				$form_array = array();
				while (list($field_name, $field_array) = each($fields_mactrack_device_edit)) {
					if (ereg("^snmp_", $field_name)) {
						$form_array += array($field_name => $fields_mactrack_device_edit[$field_name]);

						$form_array[$field_name]["value"] = "";
						$form_array[$field_name]["device_name"] = "";
						$form_array[$field_name]["form_id"] = 0;
						$form_array[$field_name]["sub_checkbox"] = array(
							"name" => "t_" . $field_name,
							"friendly_name" => "Update this Field<br/>",
							"value" => ""
							);
					}
				}

				draw_edit_form(
					array(
						"config" => array("no_form_tag" => true),
						"fields" => $form_array
						)
					);
	}elseif ($_POST["drp_action"] == "5") { /* change port settngs for multiple devices */
		print "	<tr>
				<td colspan='2' class='textArea'>
					<p>To change upper or lower port parameters for the following devices, check the box next to the fields
					you want to update, fill in the new value, and click Save.</p>
					<p><ul>$device_list</ul></p>
				</td>
				</tr>";
				$form_array = array();
				while (list($field_name, $field_array) = each($fields_mactrack_device_edit)) {
					if (ereg("^port_", $field_name)) {
						$form_array += array($field_name => $fields_mactrack_device_edit[$field_name]);

						$form_array[$field_name]["value"] = "";
						$form_array[$field_name]["device_name"] = "";
						$form_array[$field_name]["form_id"] = 0;
						$form_array[$field_name]["sub_checkbox"] = array(
							"name" => "t_" . $field_name,
							"friendly_name" => "Update this Field",
							"value" => ""
							);
					}
				}

				draw_edit_form(
					array(
						"config" => array("no_form_tag" => true),
						"fields" => $form_array
						)
					);
	}elseif ($_POST["drp_action"] == "6") { /* Connect Devices */
		print "	<tr>
				<td colspan='2' class='textArea'>
					<p>To connect the following devices to their respective Cacti Device, press the \"yes\" button below.</p>
					<p>The relation will be built on equal hostnames. Description will be updated as well.</p>
					<p><ul>$device_list</ul></p>
				</td>
				</tr>";
	}elseif ($_POST["drp_action"] == "7") { /* Copy SNMP Settings */
		print "	<tr>
				<td colspan='2' class='textArea'>
					<p>To copy SNMP Settings from connected Cacti Device to MacTrack Device, press the \"yes\" button below.</p>
					<p>All not connected Devices will silently be skipped. SNMP retries will be taken from Ping retries.</p>
					<p><ul>$device_list</ul></p>
				</td>
				</tr>";
	}elseif ($_POST["drp_action"] == "1") { /* delete */
		print "	<tr>
				<td class='textArea'>
					<p>Are you sure you want to delete the following devices?</p>
					<p><ul>$device_list</ul></p>
				</td>
			</tr>\n
			";
	}

	if (!isset($device_array)) {
		print "<tr><td class='even'><span class='textError'>You must select at least one device.</span></td></tr>\n";
		$save_html = "";
	}else{
		$save_html = "<input type='submit' value='Yes' name='save_x'>";
	}

	print "	<tr>
			<td colspan='2' align='right' class='saveRow'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='selected_items' value='" . (isset($device_array) ? serialize($device_array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . $_POST["drp_action"] . "'>" . (strlen($save_html) ? "
				<input type='submit' name='cancel_x' value='No'>
				$save_html" : "<input type='submit' name='cancel_x' value='Return'>") . "
			</td>
		</tr>
		";

	html_end_box();

	include_once("./include/bottom_footer.php");
}

/* ---------------------
    Mactrack Device Functions
   --------------------- */

function mactrack_device_export() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("site_id"));
	input_validate_input_number(get_request_var_request("type_id"));
	input_validate_input_number(get_request_var_request("device_type_id"));
	input_validate_input_number(get_request_var_request("page"));
	input_validate_input_number(get_request_var_request("status"));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var_request("filter"));
	}

	/* clean up sort_column */
	if (isset($_REQUEST["sort_column"])) {
		$_REQUEST["sort_column"] = sanitize_search_string(get_request_var_request("sort_column"));
	}

	/* clean up search string */
	if (isset($_REQUEST["sort_direction"])) {
		$_REQUEST["sort_direction"] = sanitize_search_string(get_request_var_request("sort_direction"));
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page", "sess_mactrack_device_current_page", "1");
	load_current_session_value("filter", "sess_mactrack_device_filter", "");
	load_current_session_value("site_id", "sess_mactrack_device_site_id", "-1");
	load_current_session_value("type_id", "sess_mactrack_device_type_id", "-1");
	load_current_session_value("device_type_id", "sess_mactrack_device_device_type_id", "-1");
	load_current_session_value("status", "sess_mactrack_device_status", "-1");
	load_current_session_value("sort_column", "sess_mactrack_device_sort_column", "site_name");
	load_current_session_value("sort_direction", "sess_mactrack_device_sort_direction", "ASC");

	$sql_where = "";

	$devices = mactrack_get_devices($sql_where, 0, FALSE);

	$xport_array = array();
	array_push($xport_array, 'site_id, site_name, device_id, device_name, notes, ' .
		'hostname, snmp_options, snmp_readstring, snmp_version, ' .
		'snmp_username, snmp_password, snmp_auth_protocol, snmp_priv_passphrase, ' .
		'snmp_priv_protocol, snmp_context, ' .
		'snmp_port, snmp_timeout, snmp_retries, max_oids, snmp_sysName, snmp_sysLocation, ' .
		'snmp_sysContact, snmp_sysObjectID, snmp_sysDescr, snmp_sysUptime, ' .
		'ignorePorts, scan_type, disabled, ports_total, ports_active, ' .
		'ports_trunk, macs_active, last_rundate, last_runduration');

	if (sizeof($devices)) {
		foreach($devices as $device) {
			array_push($xport_array,'"'     .
			$device['site_id']              . '","' . $device['site_name']            . '","' .
			$device['device_id']            . '","' . $device['device_name']          . '","' .
			$device['notes']                . '","' . $device['hostname']             . '","' .
			$device['snmp_options']         . '","' . $device['snmp_readstring']      . '","' .
			$device['snmp_version']         . '","' . $device['snmp_username']        . '","' .
			$device['snmp_password']        . '","' . $device['snmp_auth_protocol']   . '","' .
			$device['snmp_priv_passphrase'] . '","' . $device['snmp_priv_protocol']   . '","' .
			$device['snmp_context']         . '","' . $device['snmp_port']            . '","' .
			$device['snmp_timeout']         . '","' . $device['snmp_retries']         . '","' .
			$device['max_oids']             . '","' . $device['snmp_sysName']         . '","' .
			$device['snmp_sysLocation']     . '","' . $device['snmp_sysContact']      . '","' .
			$device['snmp_sysObjectID']     . '","' . $device['snmp_sysDescr']        . '","' .
			$device['snmp_sysUptime']       . '","' . $device['ignorePorts']          . '","' .
			$device['scan_type']            . '","' . $device['disabled']             . '","' .
			$device['ports_total']          . '","' . $device['ports_active']         . '","' .
			$device['ports_trunk']          . '","' . $device['macs_active']          . '","' .
			$device['last_rundate']         . '","' . $device['last_runduration']     . '"');
		}
	}

	header("Content-type: application/csv");
	header("Content-Disposition: attachment; filename=cacti_device_xport.csv");

	if (sizeof($xport_array)) {
	foreach($xport_array as $xport_line) {
		print $xport_line . "\n";
	}
	}
}

function mactrack_device_import() {
	global $config;

	?><form method="post" action="mactrack_devices.php?action=import" enctype="multipart/form-data"><?php

	if ((isset($_SESSION["import_debug_info"])) && (is_array($_SESSION["import_debug_info"]))) {
		html_start_box("<strong>Import Results</strong>", "100%", "aaaaaa", "3", "center", "");

		print "<tr class='even'><td><p class='textArea'>Cacti has imported the following items:</p>";
		if (sizeof($_SESSION["import_debug_info"])) {
		foreach($_SESSION["import_debug_info"] as $import_result) {
			print "<tr class='even'><td>" . $import_result . "</td>";
			print "</tr>";
		}
		}

		html_end_box();

		kill_session_var("import_debug_info");
	}

	html_start_box("<strong>Import MacTrack Devices</strong>", "100%", "", "3", "center", "");

	form_alternate_row();?>
		<td width='50%'><font class='textEditTitle'>Import Devices from Local File</font><br>
			Please specify the location of the CSV file containing your device information.
		</td>
		<td align='left'>
			<input type='file' name='import_file'>
		</td>
	</tr><?php
	form_alternate_row();?>
		<td width='50%'><font class='textEditTitle'>Overwrite Existing Data?</font><br>
			Should the import process be allowed to overwrite existing data?  Please note, this does not mean delete old row, only replace duplicate rows.
		</td>
		<td align='left'>
			<input type='checkbox' name='allow_update' id='allow_update'>Allow Existing Rows to be Updated?
		</td><?php

	html_end_box(FALSE);

	html_start_box("<strong>Required File Format Notes</strong>", "100%", "", "3", "center", "");

	form_alternate_row();?>
		<td><strong>The file must contain a header row with the following column headings.</strong>
			<br><br>
			<strong>site_id</strong> - The SiteID known to MacTrack for this device<br>
			<strong>device_name</strong> - A simple name for the device.  For example Cisco 6509 Switch<br>
			<strong>hostname</strong> - The IP Address or DNS Name for the device<br>
			<strong>notes</strong> - More detailed information about the device, including location, environmental conditions, etc.<br>
			<strong>ignorePorts</strong> - A list of ports that should not be scanned for user devices<br>
			<strong>scan_type</strong> - Redundant information indicating the intended device type.  See below for valid values.<br>
			<strong>snmp_options</strong> - Id of a set of SNMP options<br>
			<strong>snmp_readstring</strong> - The current snmp read string for the device<br>
			<strong>snmp_version</strong> - The snmp version you wish to scan this device with.  Valid values are 1, 2 and 3<br>
			<strong>snmp_port</strong> - The UDP port that the snmp agent is running on<br>
			<strong>snmp_timeout</strong> - The timeout in milliseconds to wait for an snmp response before trying again<br>
			<strong>snmp_retries</strong> - The number of times to retry a snmp request before giving up<br>
			<strong>max_oids</strong> - Specified the number of OID's that can be obtained in a single SNMP Get request<br>
			<strong>snmp_username</strong> - SNMP V3: SNMP username<br>
			<strong>snmp_password</strong> - SNMP V3: SNMP password<br>
			<strong>snmp_auth_protocol</strong> - SNMP V3: SNMP authentication protocol<br>
			<strong>snmp_priv_passphrase</strong> - SNMP V3: SNMP privacy passphrase<br>
			<strong>snmp_priv_protocol</strong> - SNMP V3: SNMP privacy protocol<br>
			<strong>snmp_context</strong> - SNMP V3: SNMP context<br>
			<br>
			<strong>The primary key for this table is a combination of the following three fields:</strong>
			<br><br>
			site_id, hostname, snmp_port
			<br><br>
			<strong>Therefore, if you attempt to import duplicate devices, only the data you specify will be updated.</strong>
			<br><br>
			<strong>scan_type</strong> is an integer field and must be one of the following:
			<br><br>
			1 - Switch/Hub<br>
			2 - Switch/Router<br>
			3 - Router<br>
			<br>
		</td>
	</tr><?php

	form_hidden_box("save_component_import","1","");

	html_end_box();

	mactrack_save_button("return", "import");
}

function mactrack_device_import_processor(&$devices) {
	$i = 0;
	$return_array = array();

	if (sizeof($devices)) {
	foreach($devices as $device_line) {
		/* parse line */
		$line_array = explode(",", $device_line);

		/* header row */
		if ($i == 0) {
			$save_order = "(";
			$j = 0;
			$first_column = TRUE;
			$required = 0;
			$save_site_id_id = -1;
			$save_snmp_port_id = -1;
			$save_host_id = -1;
			$save_device_name_id = -1;
			$update_suffix = "";

			if (sizeof($line_array)) {
			foreach($line_array as $line_item) {
				$line_item = trim(str_replace("'", "", $line_item));
				$line_item = trim(str_replace('"', '', $line_item));

				switch ($line_item) {
					case 'snmp_options':
					case 'snmp_readstring':
					case 'snmp_timeout':
					case 'snmp_retries':
					case 'ignorePorts':
					case 'scan_type':
					case 'snmp_version':
					case 'snmp_username':
					case 'snmp_password':
					case 'snmp_auth_protocol':
					case 'snmp_priv_passphrase':
					case 'snmp_priv_protocol':
					case 'snmp_context':
					case 'max_oids':
					case 'notes':
					case 'disabled':
						if (!$first_column) {
							$save_order .= ", ";
						}

						$save_order .= $line_item;

						$insert_columns[] = $j;
						$first_column = FALSE;

						if (strlen($update_suffix)) {
							$update_suffix .= ", $line_item=VALUES($line_item)";
						}else{
							$update_suffix .= " ON DUPLICATE KEY UPDATE $line_item=VALUES($line_item)";
						}

						break;
					case 'snmp_port':
						if (!$first_column) {
							$save_order .= ", ";
						}

						$save_order .= $line_item;
						$save_snmp_port_id = $j;
						$required++;

						$insert_columns[] = $j;
						$first_column = FALSE;

						if (strlen($update_suffix)) {
							$update_suffix .= ", $line_item=VALUES($line_item)";
						}else{
							$update_suffix .= " ON DUPLICATE KEY UPDATE $line_item=VALUES($line_item)";
						}

						break;
					case 'site_id':
						if (!$first_column) {
							$save_order .= ", ";
						}

						$save_order .= $line_item;
						$save_site_id_id = $j;
						$required++;

						$insert_columns[] = $j;
						$first_column = FALSE;

						if (strlen($update_suffix)) {
							$update_suffix .= ", $line_item=VALUES($line_item)";
						}else{
							$update_suffix .= " ON DUPLICATE KEY UPDATE $line_item=VALUES($line_item)";
						}

						break;
					case 'hostname':
						if (!$first_column) {
							$save_order .= ", ";
						}

						$save_order .= $line_item;
						$save_host_id = $j;
						$required++;

						$insert_columns[] = $j;
						$first_column = FALSE;

						if (strlen($update_suffix)) {
							$update_suffix .= ", $line_item=VALUES($line_item)";
						}else{
							$update_suffix .= " ON DUPLICATE KEY UPDATE $line_item=VALUES($line_item)";
						}

						break;
					case 'device_name':
						if (!$first_column) {
							$save_order .= ", ";
						}

						$save_order .= $line_item;
						$save_device_name_id = $j;

						$insert_columns[] = $j;
						$first_column = FALSE;

						if (strlen($update_suffix)) {
							$update_suffix .= ", $line_item=VALUES($line_item)";
						}else{
							$update_suffix .= " ON DUPLICATE KEY UPDATE $line_item=VALUES($line_item)";
						}

						break;
					default:
						/* ignore unknown columns */
				}

				$j++;

			}
			}

			$save_order .= ")";

			if ($required >= 3) {
				array_push($return_array, "<strong>HEADER LINE PROCESSED OK</strong>:  <br>Columns found where: " . $save_order . "<br>");
			}else{
				array_push($return_array, "<strong>HEADER LINE PROCESSING ERROR</strong>: Missing required field <br>Columns found where:" . $save_order . "<br>");
				break;
			}
		}else{
			$save_value = "(";
			$j = 0;
			$first_column = TRUE;
			$sql_where = "";

			if (sizeof($line_array)) {
			foreach($line_array as $line_item) {
				if (in_array($j, $insert_columns)) {
					$line_item = trim(str_replace("'", "", $line_item));
					$line_item = trim(str_replace('"', '', $line_item));

					if (!$first_column) {
						$save_value .= ",";
					}else{
						$first_column = FALSE;
					}

					if ($j == $save_site_id_id || $j == $save_snmp_port_id || $j == $save_host_id ) {
						if (strlen($sql_where)) {
							switch($j) {
							case $save_site_id_id:
								$sql_where .= " AND site_id='$line_item'";
								break;
							case $save_snmp_port_id:
								$sql_where .= " AND snmp_port='$line_item'";
								break;
							case $save_host_id:
								$sql_where .= " AND hostname='$line_item'";
								break;
							default:
								/* do nothing */
							}
						}else{
							switch($j) {
							case $save_site_id_id:
								$sql_where .= "WHERE site_id='$line_item'";
								break;
							case $save_snmp_port_id:
								$sql_where .= "WHERE snmp_port='$line_item'";
								break;
							case $save_host_id:
								$sql_where .= "WHERE hostname='$line_item'";
								break;
							default:
								/* do nothing */
							}
						}
					}

					if ($j == $save_snmp_port_id) {
						$snmp_port = $line_item;
					}

					if ($j == $save_site_id_id) {
						$site_id = $line_item;
					}

					if ($j == $save_host_id) {
						$hostname = $line_item;
					}

					if ($j == $save_device_name_id) {
						$device_name = $line_item;
					}

					$save_value .= "'" . $line_item . "'";
				}

				$j++;
			}
			}

			$save_value .= ")";

			if ($j > 0) {
				if (isset($_POST["allow_update"])) {
					$sql_execute = "INSERT INTO mac_track_devices " . $save_order .
						" VALUES" . $save_value . $update_suffix;

					if (db_execute($sql_execute)) {
						array_push($return_array,"INSERT SUCCEEDED: Hostname: SiteID: $site_id, Device Name: $device_name, Hostname $hostname, SNMP Port: $snmp_port");
					}else{
						array_push($return_array,"<strong>INSERT FAILED:</strong> SiteID: $site_id, Device Name: $device_name, Hostname $hostname, SNMP Port: $snmp_port");
					}
				}else{
					/* perform check to see if the row exists */
					$existing_row = db_fetch_row("SELECT * FROM mac_track_devices $sql_where");

					if (sizeof($existing_row)) {
						array_push($return_array,"<strong>INSERT SKIPPED, EXISTING:</strong> SiteID: $site_id, Device Name: $device_name, Hostname $hostname, SNMP Port: $snmp_port");
					}else{
						$sql_execute = "INSERT INTO mac_track_devices " . $save_order .
							" VALUES" . $save_value;

						if (db_execute($sql_execute)) {
							array_push($return_array,"INSERT SUCCEEDED: SiteID: $site_id, Device Name: $device_name, Hostname $hostname, SNMP Port: $snmp_port");
						}else{
							array_push($return_array,"<strong>INSERT FAILED:</strong> SiteID: $site_id, Device Name: $device_name, Hostname $hostname, SNMP Port: $snmp_port");
						}
					}
				}
			}
		}

		$i++;
	}
	}

	return $return_array;
}

function mactrack_device_remove() {
	global $config;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("device_id"));
	input_validate_input_number(get_request_var_request("type_id"));
	/* ==================================================== */

	if ((read_config_option("remove_verification") == "on") && (!isset($_REQUEST["confirm"]))) {
		include("./include/top_header.php");
		form_confirm("Are You Sure?", "Are you sure you want to delete the host <strong>'" . db_fetch_cell("select device_name from host where id=" . $_REQUEST["device_id"]) . "'</strong>?", "mactrack_devices.php", "mactrack_devices.php?action=remove&id=" . $_REQUEST["device_id"]);
		include("./include/bottom_footer.php");
		exit;
	}

	if ((read_config_option("remove_verification") == "") || (isset($_REQUEST["confirm"]))) {
		api_mactrack_device_remove($_REQUEST["device_id"]);
	}
}

function mactrack_device_edit() {
	global $config, $fields_mactrack_device_edit;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("device_id"));
	/* ==================================================== */

	display_output_messages();

	if (!empty($_REQUEST["device_id"])) {
		$device = db_fetch_row("select * from mac_track_devices where device_id=" . $_REQUEST["device_id"]);
		$header_label = "[edit: " . $device["device_name"] . "]";
	}else{
		$header_label = "[new]";
	}

	if (!empty($device["device_id"])) {
		?>
		<table width="100%" align="center">
			<tr>
				<td class="textInfo" colspan="2">
					<?php print $device["device_name"];?> (<?php print $device["hostname"];?>)
				</td>
			</tr>
			<tr>
				<td class="textHeader">
					SNMP Information<br>

					<span style="font-size: 10px; font-weight: normal; font-family: monospace;">
					<?php
					/* force php to return numeric oid's */
					if (function_exists("snmp_set_oid_numeric_print")) {
						snmp_set_oid_numeric_print(TRUE);
					}

					$snmp_system = cacti_snmp_get($device["hostname"], $device["snmp_readstring"], ".1.3.6.1.2.1.1.1.0", $device["snmp_version"], $device["snmp_username"], $device["snmp_password"], $device["snmp_auth_protocol"], $device["snmp_priv_passphrase"], $device["snmp_priv_protocol"], $device["snmp_context"], $device["snmp_port"], $device["snmp_timeout"], $device["snmp_retries"], SNMP_WEBUI);

					if ($snmp_system == "") {
						print "<span style='color: #ff0000; font-weight: bold;'>SNMP error</span>\n";
					}else{
						$snmp_uptime = cacti_snmp_get($device["hostname"], $device["snmp_readstring"], ".1.3.6.1.2.1.1.3.0", $device["snmp_version"], $device["snmp_username"], $device["snmp_password"], $device["snmp_auth_protocol"], $device["snmp_priv_passphrase"], $device["snmp_priv_protocol"], $device["snmp_context"], $device["snmp_port"], $device["snmp_timeout"], $device["snmp_retries"], SNMP_WEBUI);
						$snmp_hostname = cacti_snmp_get($device["hostname"], $device["snmp_readstring"], ".1.3.6.1.2.1.1.5.0", $device["snmp_version"], $device["snmp_username"], $device["snmp_password"], $device["snmp_auth_protocol"], $device["snmp_priv_passphrase"], $device["snmp_priv_protocol"], $device["snmp_context"], $device["snmp_port"], $device["snmp_timeout"], $device["snmp_retries"], SNMP_WEBUI);
						$snmp_objid = cacti_snmp_get($device["hostname"], $device["snmp_readstring"], ".1.3.6.1.2.1.1.2.0", $device["snmp_version"], $device["snmp_username"], $device["snmp_password"], $device["snmp_auth_protocol"], $device["snmp_priv_passphrase"], $device["snmp_priv_protocol"], $device["snmp_context"], $device["snmp_port"], $device["snmp_timeout"], $device["snmp_retries"], SNMP_WEBUI);

						$snmp_objid = str_replace("enterprises", ".1.3.6.1.4.1", $snmp_objid);
						$snmp_objid = str_replace("OID: ", "", $snmp_objid);
						$snmp_objid = str_replace(".iso", ".1", $snmp_objid);

						print "<strong>System:</strong> $snmp_system<br>\n";
						print "<strong>Uptime:</strong> $snmp_uptime<br>\n";
						print "<strong>Hostname:</strong> $snmp_hostname<br>\n";
						print "<strong>ObjectID:</strong> $snmp_objid<br>\n";
					}
					?>
					</span>
				</td>
			</tr>
		</table>
		<br>
		<?php
	}

	html_start_box("<strong>MacTrack Devices</strong> $header_label", "100%", "", "3", "center", "");

	/* preserve the devices site id between refreshes via a GET variable */
	if (!empty($_REQUEST["site_id"])) {
		$fields_host_edit["site_id"]["value"] = $_REQUEST["site_id"];
	}

	draw_edit_form(array(
		"config" => array("form_name" => "chk"),
		"fields" => inject_form_variables($fields_mactrack_device_edit, (isset($device) ? $device : array()))
		));

	html_end_box();

	if (isset($device)) {
		mactrack_save_button($config["url_path"] . "plugins/mactrack/mactrack_devices.php", "save", "", "device_id");
	}else{
		mactrack_save_button("cancel", "save", "", "device_id");
	}

	print "<script type='text/javascript' src='" . URL_PATH . "plugins/mactrack/mactrack_snmp.js'></script>";
}

function mactrack_get_devices(&$sql_where, $row_limit, $apply_limits = TRUE) {
	/* form the 'where' clause for our main sql query */
	if (strlen($_REQUEST["filter"])) {
		$sql_where = (strlen($sql_where) ? " AND ": "WHERE ") . "(mac_track_devices.hostname like '%%" . $_REQUEST["filter"] . "%%'
			OR mac_track_devices.device_name like '%%" . $_REQUEST["filter"] . "%%'
			OR mac_track_devices.notes like '%%" . $_REQUEST["filter"] . "%%')";
	}

	if ($_REQUEST["status"] == "-1") {
		/* Show all items */
	}elseif ($_REQUEST["status"] == "-2") {
		$sql_where .= (strlen($sql_where) ? " AND ": "WHERE ") . "(mac_track_devices.disabled='on')";
	}elseif ($_REQUEST["status"] == "5") {
		$sql_where .= (strlen($sql_where) ? " AND ": "WHERE ") . "(mac_track_devices.host_id=0)";
	}else {
		$sql_where .= (strlen($sql_where) ? " AND ": "WHERE ") . "(mac_track_devices.snmp_status=" . $_REQUEST["status"] . " AND mac_track_devices.disabled = '')";
	}

	if ($_REQUEST["type_id"] == "-1") {
		/* Show all items */
	}else {
		$sql_where .= (strlen($sql_where) ? " AND ": "WHERE ") . "(mac_track_devices.scan_type=" . $_REQUEST["type_id"] . ")";
	}

	if ($_REQUEST["device_type_id"] == "-1") {
		/* Show all items */
	}elseif ($_REQUEST["device_type_id"] == "-2") {
		$sql_where .= (strlen($sql_where) ? " AND ": "WHERE ") . "(mac_track_device_types.description='')";
	}else{
		$sql_where .= (strlen($sql_where) ? " AND ": "WHERE ") . "(mac_track_devices.device_type_id=" . $_REQUEST["device_type_id"] . ")";
	}

	if ($_REQUEST["site_id"] == "-1") {
		/* Show all items */
	}elseif ($_REQUEST["site_id"] == "-2") {
		$sql_where .= (strlen($sql_where) ? " AND ": "WHERE ") . "(mac_track_sites.site_id IS NULL)";
	}elseif (!empty($_REQUEST["site_id"])) {
		$sql_where .= (strlen($sql_where) ? " AND ": "WHERE ") . "(mac_track_devices.site_id=" . $_REQUEST["site_id"] . ")";
	}

	$query_string = "SELECT
		mac_track_device_types.description as device_type,
		mac_track_devices.*,
		mac_track_sites.site_name
		FROM mac_track_sites
		RIGHT JOIN mac_track_devices ON mac_track_devices.site_id = mac_track_sites.site_id
		LEFT JOIN mac_track_device_types ON mac_track_devices.device_type_id=mac_track_device_types.device_type_id
		$sql_where
		ORDER BY " . $_REQUEST["sort_column"] . " " . $_REQUEST["sort_direction"];

	if ($apply_limits) {
		$query_string .= " LIMIT " . ($row_limit*($_REQUEST["page"]-1)) . "," . $row_limit;
	}

	return db_fetch_assoc($query_string);
}

function mactrack_device() {
	global $device_actions, $mactrack_device_types, $config, $item_rows;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("site_id"));
	input_validate_input_number(get_request_var_request("type_id"));
	input_validate_input_number(get_request_var_request("device_type_id"));
	input_validate_input_number(get_request_var_request("page"));
	input_validate_input_number(get_request_var_request("rows"));
	input_validate_input_number(get_request_var_request("status"));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var_request("filter"));
	}

	/* clean up sort_column */
	if (isset($_REQUEST["sort_column"])) {
		$_REQUEST["sort_column"] = sanitize_search_string(get_request_var_request("sort_column"));
	}

	/* clean up search string */
	if (isset($_REQUEST["sort_direction"])) {
		$_REQUEST["sort_direction"] = sanitize_search_string(get_request_var_request("sort_direction"));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST["clear_x"])) {
		kill_session_var("sess_mactrack_device_current_page");
		kill_session_var("sess_mactrack_device_filter");
		kill_session_var("sess_mactrack_device_site_id");
		kill_session_var("sess_mactrack_device_type_id");
		kill_session_var("sess_default_rows");
		kill_session_var("sess_mactrack_device_device_type_id");
		kill_session_var("sess_mactrack_device_status");
		kill_session_var("sess_mactrack_device_sort_column");
		kill_session_var("sess_mactrack_device_sort_direction");

		unset($_REQUEST["page"]);
		unset($_REQUEST["filter"]);
		unset($_REQUEST["site_id"]);
		unset($_REQUEST["type_id"]);
		unset($_REQUEST["rows"]);
		unset($_REQUEST["device_type_id"]);
		unset($_REQUEST["status"]);
		unset($_REQUEST["sort_column"]);
		unset($_REQUEST["sort_direction"]);
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page", "sess_mactrack_device_current_page", "1");
	load_current_session_value("filter", "sess_mactrack_device_filter", "");
	load_current_session_value("site_id", "sess_mactrack_device_site_id", "-1");
	load_current_session_value("type_id", "sess_mactrack_device_type_id", "-1");
	load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));
	load_current_session_value("device_type_id", "sess_mactrack_device_device_type_id", "-1");
	load_current_session_value("status", "sess_mactrack_device_status", "-1");
	load_current_session_value("sort_column", "sess_mactrack_device_sort_column", "site_name");
	load_current_session_value("sort_direction", "sess_mactrack_device_sort_direction", "ASC");

	if ($_REQUEST["rows"] == -1) {
		$row_limit = read_config_option("num_rows_table");
	}elseif ($_REQUEST["rows"] == -2) {
		$row_limit = 999999;
	}else{
		$row_limit = $_REQUEST["rows"];
	}

	html_start_box("<strong>MacTrack Device Filters</strong>", "100%", "", "3", "center", "mactrack_devices.php?action=edit&status=" . $_REQUEST["status"]);
	mactrack_device_filter();
	html_end_box();

	$sql_where = "";

	$devices = mactrack_get_devices($sql_where, $row_limit);

	$total_rows = db_fetch_cell("SELECT
		COUNT(*)
		FROM mac_track_sites
		RIGHT JOIN mac_track_devices ON mac_track_devices.site_id = mac_track_sites.site_id
		LEFT JOIN mac_track_device_types ON mac_track_devices.device_type_id=mac_track_device_types.device_type_id
		$sql_where");

	html_start_box("", "100%", "", "3", "center", "");

	$nav = html_nav_bar("mactrack_devices.php?filter=" . $_REQUEST["filter"], MAX_DISPLAY_PAGES, get_request_var_request("page"), $row_limit, $total_rows, 13, 'Devices');

	print $nav;

	$display_text = array(
		"device_name" => array("Device Name", "ASC"),
		"site_name" => array("Site Name", "ASC"),
		"snmp_status" => array("Status", "ASC"),
		"hostname" => array("Hostname", "ASC"),
		"device_type" => array("Device Type", "ASC"),
		"ips_total" => array("Total IP's", "DESC"),
		"ports_total" => array("User Ports", "DESC"),
		"ports_active" => array("User Ports Up", "DESC"),
		"ports_trunk" => array("Trunk Ports", "DESC"),
		"macs_active" => array("Active Macs", "DESC"),
		"last_runduration" => array("Last Duration", "DESC"));

	html_header_sort_checkbox($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"]);

	if (sizeof($devices)) {
		foreach ($devices as $device) {
			form_alternate_row('line' . $device['device_id'], true);
			mactrack_format_device_row($device);
		}

		/* put the nav bar on the bottom as well */
		print $nav;
	}else{
		print "<tr><td colspan='10'><em>No MacTrack Devices</em></td></tr>";
	}
	html_end_box(false);

	/* draw the dropdown containing a list of available actions for this form */
	mactrack_draw_actions_dropdown($device_actions);
}

?>
