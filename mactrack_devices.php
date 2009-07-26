<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2009 The Cacti Group                                 |
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

define("MAX_DISPLAY_PAGES", 21);

$device_actions = array(
	1 => "Delete",
	2 => "Enable",
	3 => "Disable",
	4 => "Change SNMP Options",
	5 => "Change Device Port Values"
	);

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

/* correct for a cancel button */
if (isset($_REQUEST["cancel_x"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
	case 'save':
		form_save();

		break;
	case 'actions':
		form_actions();

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

function form_save() {
	if ((isset($_POST["save_component_device"])) && (empty($_POST["add_dq_y"]))) {
		$device_id = api_mactrack_device_save($_POST["device_id"], $_POST["site_id"],
			$_POST["hostname"], $_POST["device_name"], $_POST["scan_type"],
			$_POST["snmp_readstring"], $_POST["snmp_readstrings"],
			$_POST["snmp_version"],	$_POST["snmp_port"], $_POST["snmp_timeout"],
			$_POST["snmp_retries"], $_POST["port_ignorePorts"],
			$_POST["notes"], $_POST["user_name"], $_POST["user_password"],
			(isset($_POST["disabled"]) ? $_POST["disabled"] : ""));

		if ((is_error_message()) || ($_POST["device_id"] != $_POST["_device_id"])) {
			header("Location: mactrack_devices.php?action=edit&device_id=" . (empty($device_id) ? $_POST["device_id"] : $device_id));
		}else{
			header("Location: mactrack_devices.php");
		}
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

function api_mactrack_device_remove($device_id){
	db_execute("DELETE FROM mac_track_devices WHERE device_id=" . $device_id);
}

function api_mactrack_device_save($device_id, $site_id, $hostname,
			$device_name, $scan_type, $snmp_readstring, $snmp_readstrings,
			$snmp_version, $snmp_port, $snmp_timeout, $snmp_retries,
			$ignorePorts, $notes, $user_name, $user_password,
			$disabled) {

	$save["device_id"] = $device_id;
	$save["site_id"] = $site_id;
	$save["hostname"] = form_input_validate($hostname, "hostname", "", false, 3);
	$save["device_name"] = form_input_validate($device_name, "device_name", "", false, 3);
	$save["notes"] = form_input_validate($notes, "notes", "", true, 3);
	$save["scan_type"] = form_input_validate($scan_type, "scan_type", "", false, 3);
	$save["snmp_readstring"] = form_input_validate($snmp_readstring, "snmp_readstring", "", false, 3);
	$save["snmp_readstrings"] = form_input_validate($snmp_readstrings, "snmp_readstrings", "", true, 3);
	$save["snmp_version"] = form_input_validate($snmp_version, "snmp_version", "", false, 3);
	$save["snmp_port"] = form_input_validate($snmp_port, "snmp_port", "^[0-9]+$", false, 3);
	$save["snmp_timeout"] = form_input_validate($snmp_timeout, "snmp_timeout", "^[0-9]+$", false, 3);
	$save["snmp_retries"] = form_input_validate($snmp_retries, "snmp_retries", "^[0-9]+$", false, 3);
	$save["user_name"] = form_input_validate($user_name, "user_name", "", true, 3);
	$save["user_password"] = form_input_validate($user_password, "user_password", "", true, 3);
	$save["ignorePorts"] = form_input_validate($ignorePorts, "port_ignorePorts", "", true, 3);
	$save["disabled"] = form_input_validate($disabled, "disabled", "", true, 3);

	$device_id = 0;
	if (!is_error_message()) {
		$device_id = sql_save($save, "mac_track_devices", "device_id");

		if ($device_id) {
			raise_message(1);
		}else{
			raise_message(2);
		}
	}

	return $device_id;
}


/* ------------------------
    The "actions" function
   ------------------------ */

function form_actions() {
	global $colors, $config, $device_actions, $fields_mactrack_device_edit;

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
		}elseif ($_POST["drp_action"] == "5") { /* change port settngs for multiple devices */
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

	html_start_box("<strong>" . $device_actions{$_POST["drp_action"]} . "</strong>", "60%", $colors["header_panel"], "3", "center", "");

	print "<form action='mactrack_devices.php' method='post'>\n";

	if ($_POST["drp_action"] == "2") { /* Enable Devices */
		print "	<tr>
				<td colspan='2' class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>To enable the following devices, press the \"yes\" button below.</p>
					<p>$device_list</p>
				</td>
				</tr>";
	}elseif ($_POST["drp_action"] == "3") { /* Disable Devices */
		print "	<tr>
				<td colspan='2' class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>To disable the following devices, press the \"yes\" button below.</p>
					<p>$device_list</p>
				</td>
				</tr>";
	}elseif ($_POST["drp_action"] == "4") { /* change snmp options */
		print "	<tr>
				<td colspan='2' class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>To change SNMP parameters for the following devices, check the box next to the fields
					you want to update, fill in the new value, and click Save.</p>
					<p>$device_list</p>
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
	}elseif ($_POST["drp_action"] == "5") { /* change port settngs for multiple devices */
		print "	<tr>
				<td colspan='2' class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>To change upper or lower port parameters for the following devices, check the box next to the fields
					you want to update, fill in the new value, and click Save.</p>
					<p>$device_list</p>
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
	}elseif ($_POST["drp_action"] == "1") { /* delete */
		print "	<tr>
				<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>Are you sure you want to delete the following devices?</p>
					<p>$device_list</p>
				</td>
			</tr>\n
			";
	}

	if (!isset($device_array)) {
		print "<tr><td bgcolor='#" . $colors["form_alternate1"]. "'><span class='textError'>You must select at least one device.</span></td></tr>\n";
		$save_html = "";
	}else{
		$save_html = "<input type='submit' value='Yes' name='save_x'>";
	}

	print "	<tr>
			<td colspan='2' align='right' bgcolor='#eaeaea'>
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
		$_REQUEST["filter"] = sanitize_search_string(get_request_var("filter"));
	}

	/* clean up sort_column */
	if (isset($_REQUEST["sort_column"])) {
		$_REQUEST["sort_column"] = sanitize_search_string(get_request_var("sort_column"));
	}

	/* clean up search string */
	if (isset($_REQUEST["sort_direction"])) {
		$_REQUEST["sort_direction"] = sanitize_search_string(get_request_var("sort_direction"));
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
	array_push($xport_array, '"site_id","site_name","device_id","device_name",' .
		'"hostname","snmp_readstring","snmp_readstrings","snmp_version",' .
		'"snmp_port","snmp_timeout","snmp_retries","ignorePorts",' .
		'"notes","scan_type","disabled","ports_total","ports_active","ports_trunk",' .
		'"macs_active","last_rundate","last_runduration"');

	if (sizeof($devices)) {
		foreach($devices as $device) {
			array_push($xport_array,'"' . $device['site_id'] . '","' .
			$device['site_name'] . '","' . $device['device_id'] . '","' .
			$device['device_name'] . '","' . $device['hostname'] . '","' .
			$device['snmp_readstring'] . '","' . $device['snmp_readstrings'] . '","' .
			$device['snmp_version'] . '","' . $device['snmp_port'] . '","' .
			$device['snmp_timeout'] . '","' . $device['snmp_retries'] . '","' .
			$device['ignorePorts'] . '","' . $device['notes'] . '","' .
			$device['scan_type'] . '","' . $device['disabled'] . '","' .
			$device['ports_total'] . '","' . $device['ports_active'] . '","' .
			$device['ports_trunk'] . '","' . $device['macs_active'] . '","' .
			$device['last_rundate'] . '","' . $device['last_runduration'] . '"');
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
	global $colors, $config;

	?><form method="post" action="mactrack_devices.php?action=import" enctype="multipart/form-data"><?php

	if ((isset($_SESSION["import_debug_info"])) && (is_array($_SESSION["import_debug_info"]))) {
		html_start_box("<strong>Import Results</strong>", "100%", "aaaaaa", "3", "center", "");

		print "<tr bgcolor='#" . $colors["form_alternate1"] . "'><td><p class='textArea'>Cacti has imported the following items:</p>";
		if (sizeof($_SESSION["import_debug_info"])) {
		foreach($_SESSION["import_debug_info"] as $import_result) {
			print "<tr bgcolor='#" . $colors["form_alternate1"] . "'><td>" . $import_result . "</td>";
			print "</tr>";
		}
		}

		html_end_box();

		kill_session_var("import_debug_info");
	}

	html_start_box("<strong>Import MacTrack Devices</strong>", "100%", $colors["header"], "3", "center", "");

	form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],0);?>
		<td width='50%'><font class='textEditTitle'>Import Devices from Local File</font><br>
			Please specify the location of the CSV file containing your device information.
		</td>
		<td align='left'>
			<input type='file' name='import_file'>
		</td>
	</tr><?php
	form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],0);?>
		<td width='50%'><font class='textEditTitle'>Overwrite Existing Data?</font><br>
			Should the import process be allowed to overwrite existing data?  Please note, this does not mean delete old row, only replace duplicate rows.
		</td>
		<td align='left'>
			<input type='checkbox' name='allow_update' id='allow_update'>Allow Existing Rows to be Updated?
		</td><?php

	html_end_box(FALSE);

	html_start_box("<strong>Required File Format Notes</strong>", "100%", $colors["header"], "3", "center", "");

	form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],0);?>
		<td><strong>The file must contain a header row with the following column headings.</strong>
			<br><br>
			<strong>site_id</strong> - The SiteID known to MacTrack for this device<br>
			<strong>device_name</strong> - A simple name for the device.  For example Cisco 6509 Switch<br>
			<strong>hostname</strong> - The IP Address or DNS Name for the device<br>
			<strong>notes</strong> - More detailed information about the device, including location, environmental conditions, etc.<br>
			<strong>ignorePorts</strong> - A list of ports that should not be scanned for user devices<br>
			<strong>scan_type</strong> - Redundant information indicating the intended device type.  See below for valid values.<br>
			<strong>snmp_readstring</strong> - The current snmp read string for the device<br>
			<strong>snmp_readstrings</strong> - A list of know good readstrings for your network<br>
			<strong>snmp_version</strong> - The snmp version you wish to scan this device with.  Valid values are 1 and 2<br>
			<strong>snmp_port</strong> - The UDP port that the snmp agent is running on<br>
			<strong>snmp_timeout</strong> - The timeout in milliseconds to wait for an snmp response before trying again<br>
			<strong>snmp_retries</strong> - The number of times to retry a snmp request before giving up<br>
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
					case 'snmp_readstring':
					case 'snmp_readstrings':
					case 'snmp_timeout':
					case 'snmp_retries':
					case 'ignorePorts':
					case 'scan_type':
					case 'snmp_version':
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
	input_validate_input_number(get_request_var("device_id"));
	input_validate_input_number(get_request_var("type_id"));
	/* ==================================================== */

	if ((read_config_option("remove_verification") == "on") && (!isset($_GET["confirm"]))) {
		include("./include/top_header.php");
		form_confirm("Are You Sure?", "Are you sure you want to delete the host <strong>'" . db_fetch_cell("select device_name from host where id=" . $_GET["device_id"]) . "'</strong>?", "mactrack_devices.php", "mactrack_devices.php?action=remove&id=" . $_GET["device_id"]);
		include("./include/bottom_footer.php");
		exit;
	}

	if ((read_config_option("remove_verification") == "") || (isset($_GET["confirm"]))) {
		api_mactrack_device_remove($_GET["device_id"]);
	}
}

function mactrack_device_edit() {
	global $colors, $fields_mactrack_device_edit;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("device_id"));
	/* ==================================================== */

	display_output_messages();

	if (!empty($_GET["device_id"])) {
		$device = db_fetch_row("select * from mac_track_devices where device_id=" . $_GET["device_id"]);
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

					$snmp_system = cacti_snmp_get($device["hostname"], $device["snmp_readstring"], ".1.3.6.1.2.1.1.1.0", $device["snmp_version"], "", "", "", "", "", "", $device["snmp_port"], $device["snmp_timeout"], SNMP_WEBUI);

					if ($snmp_system == "") {
						print "<span style='color: #ff0000; font-weight: bold;'>SNMP error</span>\n";
					}else{
						$snmp_uptime = cacti_snmp_get($device["hostname"], $device["snmp_readstring"], ".1.3.6.1.2.1.1.3.0", $device["snmp_version"], "", "", "", "", "", "", $device["snmp_port"], $device["snmp_timeout"], SNMP_WEBUI);
						$snmp_hostname = cacti_snmp_get($device["hostname"], $device["snmp_readstring"], ".1.3.6.1.2.1.1.5.0", $device["snmp_version"], "", "", "", "", "", "", $device["snmp_port"], $device["snmp_timeout"], SNMP_WEBUI);
						$snmp_objid = cacti_snmp_get($device["hostname"], $device["snmp_readstring"], ".1.3.6.1.2.1.1.2.0", $device["snmp_version"], "", "", "", "", "", "", $device["snmp_port"], $device["snmp_timeout"], SNMP_WEBUI);

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

	html_start_box("<strong>MacTrack Devices</strong> $header_label", "100%", $colors["header"], "3", "center", "");

	/* preserve the devices site id between refreshes via a GET variable */
	if (!empty($_GET["site_id"])) {
		$fields_host_edit["site_id"]["value"] = $_GET["site_id"];
	}

	draw_edit_form(array(
		"config" => array("form_name" => "chk"),
		"fields" => inject_form_variables($fields_mactrack_device_edit, (isset($device) ? $device : array()))
		));

	html_end_box();

	if (isset($device)) {
		mactrack_save_button("return", "save", "", "device_id");
	}else{
		mactrack_save_button("cancel", "save", "", "device_id");
	}
}

function mactrack_get_devices(&$sql_where, $row_limit, $apply_limits = TRUE) {
	/* form the 'where' clause for our main sql query */
	$sql_where = "WHERE ((mac_track_devices.hostname like '%%" . $_REQUEST["filter"] . "%%'
		OR mac_track_devices.device_name like '%%" . $_REQUEST["filter"] . "%%'
		OR mac_track_devices.notes like '%%" . $_REQUEST["filter"] . "%%')";

	if ($_REQUEST["status"] == "-1") {
		/* Show all items */
	}elseif ($_REQUEST["status"] == "-2") {
		$sql_where .= " AND mac_track_devices.disabled='on'";
	}else {
		$sql_where .= " AND (mac_track_devices.snmp_status=" . $_REQUEST["status"] . " AND mac_track_devices.disabled = '')";
	}

	if ($_REQUEST["type_id"] == "-1") {
		/* Show all items */
	}else {
		$sql_where .= " AND mac_track_devices.scan_type=" . $_REQUEST["type_id"];
	}

	if ($_REQUEST["device_type_id"] == "-1") {
		/* Show all items */
	}else{
		$sql_where .= " AND (mac_track_devices.device_type_id=" . $_REQUEST["device_type_id"] . ")";
	}

	if ($_REQUEST["site_id"] == "-1") {
		$sql_where .= ")";
		/* Show all items */
	}elseif ($_REQUEST["site_id"] == "-2") {
		$sql_where .= " AND (mac_track_sites.site_id IS NULL))";
	}elseif (!empty($_REQUEST["site_id"])) {
		$sql_where .= " AND mac_track_devices.site_id=" . $_REQUEST["site_id"] . ")";
	}

	$query_string = "SELECT
		mac_track_devices.site_id,
		mac_track_sites.site_name,
		mac_track_devices.device_id,
		mac_track_devices.host_id,
		mac_track_devices.device_name,
		mac_track_devices.notes,
		mac_track_devices.hostname,
		mac_track_devices.snmp_readstring,
		mac_track_devices.snmp_readstrings,
		mac_track_devices.snmp_version,
		mac_track_devices.snmp_port,
		mac_track_devices.snmp_timeout,
		mac_track_devices.snmp_retries,
		mac_track_devices.snmp_status,
		mac_track_devices.ignorePorts,
		mac_track_devices.disabled,
		mac_track_devices.scan_type,
		mac_track_devices.ips_total,
		mac_track_devices.ports_total,
		mac_track_devices.ports_active,
		mac_track_devices.ports_trunk,
		mac_track_devices.macs_active,
		mac_track_devices.last_rundate,
		mac_track_devices.last_runmessage,
		mac_track_devices.last_runduration
		FROM mac_track_sites
		RIGHT JOIN mac_track_devices ON mac_track_devices.site_id = mac_track_sites.site_id
		$sql_where
		ORDER BY " . $_REQUEST["sort_column"] . " " . $_REQUEST["sort_direction"];

	if ($apply_limits) {
		$query_string .= " LIMIT " . ($row_limit*($_REQUEST["page"]-1)) . "," . $row_limit;
	}

	return db_fetch_assoc($query_string);
}

function mactrack_device() {
	global $colors, $device_actions, $mactrack_device_types, $config, $item_rows;

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
		$_REQUEST["filter"] = sanitize_search_string(get_request_var("filter"));
	}

	/* clean up sort_column */
	if (isset($_REQUEST["sort_column"])) {
		$_REQUEST["sort_column"] = sanitize_search_string(get_request_var("sort_column"));
	}

	/* clean up search string */
	if (isset($_REQUEST["sort_direction"])) {
		$_REQUEST["sort_direction"] = sanitize_search_string(get_request_var("sort_direction"));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST["clear_x"])) {
		kill_session_var("sess_mactrack_device_current_page");
		kill_session_var("sess_mactrack_device_filter");
		kill_session_var("sess_mactrack_device_site_id");
		kill_session_var("sess_mactrack_device_type_id");
		kill_session_var("sess_mactrack_device_rows");
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
	load_current_session_value("rows", "sess_mactrack_device_rows", "-1");
	load_current_session_value("device_type_id", "sess_mactrack_device_device_type_id", "-1");
	load_current_session_value("status", "sess_mactrack_device_status", "-1");
	load_current_session_value("sort_column", "sess_mactrack_device_sort_column", "site_name");
	load_current_session_value("sort_direction", "sess_mactrack_device_sort_direction", "ASC");

	if ($_REQUEST["rows"] == -1) {
		$row_limit = read_config_option("num_rows_mactrack");
	}elseif ($_REQUEST["rows"] == -2) {
		$row_limit = 999999;
	}else{
		$row_limit = $_REQUEST["rows"];
	}

	html_start_box("<strong>MacTrack Device Filters</strong>", "100%", $colors["header"], "3", "center", "mactrack_devices.php?action=edit&status=" . $_REQUEST["status"]);

	include("./plugins/mactrack/html/inc_mactrack_device_filter_table.php");

	html_end_box(FALSE);

	$sql_where = "";

	$devices = mactrack_get_devices($sql_where, $row_limit);

	$total_rows = db_fetch_cell("SELECT
		COUNT(*)
		FROM mac_track_sites
		RIGHT JOIN mac_track_devices ON mac_track_devices.site_id = mac_track_sites.site_id
		$sql_where");

	html_start_box("", "100%", $colors["header"], "3", "center", "");

	/* generate page list */
	$url_page_select = get_page_list($_REQUEST["page"], MAX_DISPLAY_PAGES, $row_limit, $total_rows, "mactrack_devices.php?filter=" . $_REQUEST["filter"]);

	$nav = "<tr bgcolor='#" . $colors["header"] . "'>
			<td colspan='13'>
				<table width='100%' cellspacing='0' cellpadding='0' border='0'>
					<tr>
						<td align='left' class='textHeaderDark'>
							<strong>&lt;&lt; "; if ($_REQUEST["page"] > 1) { $nav .= "<a class='linkOverDark' href='mactrack_devices.php?page=" . ($_REQUEST["page"]-1) . "'>"; } $nav .= "Previous"; if ($_REQUEST["page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
						</td>\n
						<td align='center' class='textHeaderDark'>
							Showing Rows " . (($row_limit*($_REQUEST["page"]-1))+1) . " to " . ((($total_rows < $row_limit) || ($total_rows < ($row_limit*$_REQUEST["page"]))) ? $total_rows : ($row_limit*$_REQUEST["page"])) . " of $total_rows [$url_page_select]
						</td>\n
						<td align='right' class='textHeaderDark'>
							<strong>"; if (($_REQUEST["page"] * $row_limit) < $total_rows) { $nav .= "<a class='linkOverDark' href='mactrack_devices.php?page=" . ($_REQUEST["page"]+1) . "'>"; } $nav .= "Next"; if (($_REQUEST["page"] * $row_limit) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
						</td>\n
					</tr>
				</table>
			</td>
		</tr>\n";

	if ($total_rows) {
		print $nav;
	}

	$display_text = array(
		"nosort" => array("Actions", ""),
		"device_name" => array("Device Name", "ASC"),
		"site_name" => array("Site Name", "ASC"),
		"snmp_status" => array("Status", "ASC"),
		"hostname" => array("Hostname", "ASC"),
		"scan_type" => array("Device Type", "ASC"),
		"ips_total" => array("Total IP's", "DESC"),
		"ports_total" => array("User Ports", "DESC"),
		"ports_active" => array("User Ports Up", "DESC"),
		"ports_trunk" => array("Trunk Ports", "DESC"),
		"macs_active" => array("Active Macs", "DESC"),
		"last_runduration" => array("Last Duration", "DESC"));

	html_header_sort_checkbox($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"]);

	$i = 0;
	if (sizeof($devices)) {
		foreach ($devices as $device) {
			form_alternate_row_color($colors["alternate"],$colors["light"],$i, 'line' . $device["device_id"]); $i++;
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