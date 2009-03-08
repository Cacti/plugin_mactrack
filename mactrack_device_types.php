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
/* include cacti base functions */
include("./include/auth.php");
include_once("./lib/snmp.php");
include_once("./plugins/mactrack/lib/mactrack_functions.php");

/* include base and vendor functions to obtain a list of registered scanning functions */
include_once($config['base_path'] . "/plugins/mactrack/lib/mactrack_functions.php");
include_once($config['base_path'] . "/plugins/mactrack/lib/mactrack_vendors.php");

/* store the list of registered mactrack scanning functions */
db_execute("REPLACE INTO mac_track_scanning_functions (scanning_function, type) VALUES ('Not Applicable - Router', 1)");
if (isset($mactrack_scanning_functions)) {
foreach($mactrack_scanning_functions as $scanning_function) {
	db_execute("REPLACE INTO mac_track_scanning_functions (scanning_function, type) VALUES ('" . $scanning_function . "', '1')");
}
}

/* store the list of registered mactrack scanning functions */
db_execute("REPLACE INTO mac_track_scanning_functions (scanning_function, type) VALUES ('Not Applicable - Hub/Switch', '2')");
if (isset($mactrack_scanning_functions_ip)) {
foreach($mactrack_scanning_functions_ip as $scanning_function) {
	db_execute("REPLACE INTO mac_track_scanning_functions (scanning_function, type) VALUES ('" . $scanning_function . "', '2')");
}
}

define("MAX_DISPLAY_PAGES", 21);

$device_types_actions = array(
	1 => "Delete",
	2 => "Duplicate"
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

		mactrack_device_type_edit();

		include_once("./include/bottom_footer.php");
		break;
	case 'import':
		include_once("./include/top_header.php");

		mactrack_device_type_import();

		include_once("./include/bottom_footer.php");
		break;
	default:
		if (isset($_REQUEST["import_x"])) {
			header("Location: mactrack_device_types.php?action=import");
		}elseif (isset($_REQUEST["export_x"])) {
			mactrack_device_type_export();
		}else{
			include_once("./include/top_header.php");

			mactrack_device_type();

			include_once("./include/bottom_footer.php");
		}
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if ((isset($_POST["save_component_device_type"])) && (empty($_POST["add_dq_y"]))) {
		$device_type_id = api_mactrack_device_type_save($_POST["device_type_id"], $_POST["description"],
			$_POST["vendor"], $_POST["device_type"], $_POST["sysDescr_match"], $_POST["sysObjectID_match"],
			$_POST["scanning_function"], $_POST["ip_scanning_function"], $_POST["serial_number_oid"], $_POST["lowPort"], $_POST["highPort"]);

		if ((is_error_message()) || ($_POST["device_type_id"] != $_POST["_device_type_id"])) {
			header("Location: mactrack_device_types.php?action=edit&device_type_id=" . (empty($device_type_id) ? $_POST["device_type_id"] : $device_type_id));
		}else{
			header("Location: mactrack_device_types.php");
		}
	}

	if (isset($_POST["save_component_import"])) {
		if (($_FILES["import_file"]["tmp_name"] != "none") && ($_FILES["import_file"]["tmp_name"] != "")) {
			/* file upload */
			$csv_data = file($_FILES["import_file"]["tmp_name"]);

			/* obtain debug information if it's set */
			$debug_data = mactrack_device_type_import_processor($csv_data);
			if(sizeof($debug_data) > 0) {
				$_SESSION["import_debug_info"] = $debug_data;
			}
		}else{
			header("Location: mactrack_device_types.php?action=import"); exit;
		}

		header("Location: mactrack_device_types.php?action=import");
	}
}

function api_mactrack_device_type_remove($device_type_id){
	db_execute("DELETE FROM mac_track_device_types WHERE device_type_id='" . $device_type_id . "'");
}

function api_mactrack_device_type_save($device_type_id, $description,
			$vendor, $device_type, $sysDescr_match, $sysObjectID_match, $scanning_function,
			$ip_scanning_function, $serial_number_oid, $lowPort, $highPort) {

	$save["device_type_id"] = $device_type_id;
	$save["description"] = form_input_validate($description, "description", "", false, 3);
	$save["vendor"] = $vendor;
	$save["device_type"] = $device_type;
	$save["sysDescr_match"] = form_input_validate($sysDescr_match, "sysDescr_match", "", true, 3);
	$save["sysObjectID_match"] = form_input_validate($sysObjectID_match, "sysObjectID_match", "", true, 3);
	$save["serial_number_oid"] = form_input_validate($serial_number_oid, "serial_number_oid", "", true, 3);
	$save["scanning_function"] = form_input_validate($scanning_function, "scanning_function", "", true, 3);
	$save["ip_scanning_function"] = form_input_validate($ip_scanning_function, "ip_scanning_function", "", true, 3);
	$save["lowPort"] = form_input_validate($lowPort, "lowPort", "", true, 3);
	$save["highPort"] = form_input_validate($highPort, "highPort", "", true, 3);

	$device_type_id = 0;
	if (!is_error_message()) {
		$device_type_id = sql_save($save, "mac_track_device_types", "device_type_id");

		if ($device_type_id) {
			raise_message(1);
		}else{
			raise_message(2);
		}
	}

	return $device_type_id;
}

function api_mactrack_duplicate_device_type($device_type_id, $dup_id, $device_type_title) {
	if (!empty($device_type_id)) {
		$device_type = db_fetch_row("SELECT * FROM mac_track_device_types WHERE device_type_id=$device_type_id");

		/* create new entry: graph_local */
		$save["device_type_id"] = 0;

		if (substr_count($device_type_title, "<description>")) {
			$save["description"] = $device_type["description"] . "(1)";
		}else{
			$save["description"] = $device_type_title . "(" . $dup_id . ")";
		}

		$save["vendor"] = $device_type["vendor"];
		$save["device_type"] = $device_type["device_type"];
		$save["sysDescr_match"] = "--dup--" . $device_type["sysDescr_match"];
		$save["sysObjectID_match"] = "--dup--" . $device_type["sysObjectID_match"];
		$save["scanning_function"] = $device_type["scanning_function"];
		$save["lowPort"] = $device_type["lowPort"];
		$save["highPort"] = $device_type["highPort"];

		$device_type_id = sql_save($save, "mac_track_device_types", array("device_type_id"));
	}
}

/* ------------------------
    The "actions" function
   ------------------------ */

function form_actions() {
	global $colors, $config, $device_types_actions, $fields_mactrack_device_types_edit;

	/* if we are to save this form, instead of display it */
	if (isset($_POST["selected_items"])) {
		$selected_items = unserialize(stripslashes($_POST["selected_items"]));

		if ($_POST["drp_action"] == "1") { /* delete */
			for ($i=0; $i<count($selected_items); $i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				api_mactrack_device_type_remove($selected_items[$i]);
			}
		}elseif ($_POST["drp_action"] == "2") { /* duplicate */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				api_mactrack_duplicate_device_type($selected_items[$i], $i, $_POST["title_format"]);
			}
		}

		header("Location: mactrack_device_types.php");
		exit;
	}

	/* setup some variables */
	$device_types_list = ""; $i = 0;

	/* loop through each of the device types selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (ereg("^chk_([0-9]+)$", $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$device_types_info = db_fetch_row("SELECT description FROM mac_track_device_types WHERE device_type_id=" . $matches[1]);
			$device_types_list .= "<li>" . $device_types_info["description"] . "<br>";
			$device_types_array[$i] = $matches[1];
		}

		$i++;
	}

	include_once("./include/top_header.php");

	html_start_box("<strong>" . $device_types_actions{$_POST["drp_action"]} . "</strong>", "60%", $colors["header_panel"], "3", "center", "");

	print "<form action='mactrack_device_types.php' method='post'>\n";

	if ($_POST["drp_action"] == "1") { /* delete */
		print "	<tr>
				<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>Are you sure you want to delete the following device types?</p>
					<p>$device_types_list</p>
				</td>
			</tr>\n
			";
	}elseif ($_POST["drp_action"] == "2") { /* duplicate */
		print "	<tr>
				<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>When you click save, the following device types will be duplicated. You may optionally
					change the description for the new device types.  Otherwise, do not change value below and the original name will be replicated with a new suffix.</p>
					<p>$device_types_list</p>
					<p><strong>Device Type Prefix:</strong><br>"; form_text_box("title_format", "<description> (1)", "", "255", "30", "text"); print "</p>
				</td>
			</tr>\n
			";
	}

	if (!isset($device_types_array)) {
		print "<tr><td bgcolor='#" . $colors["form_alternate1"]. "'><span class='textError'>You must select at least one device type.</span></td></tr>\n";
		$save_html = "";
	}else{
		$save_html = "<input type='submit' value='Yes' name='save_x'>";
	}

	print "	<tr>
			<td colspan='2' align='right' bgcolor='#eaeaea'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='selected_items' value='" . (isset($device_types_array) ? serialize($device_types_array) : '') . "'>
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
    Mactrack Device Type Functions
   --------------------- */

function mactrack_device_type_export() {
	global $colors, $device_actions, $mactrack_device_types, $config;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("page"));
	input_validate_input_number(get_request_var_request("type_id"));
	/* ==================================================== */

	/* clean up the vendor string */
	if (isset($_REQUEST["vendor"])) {
		$_REQUEST["vendor"] = sanitize_search_string(get_request_var("vendor"));
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
	load_current_session_value("page", "sess_mactrack_device_type_current_page", "1");
	load_current_session_value("vendor", "sess_mactrack_device_type_vendor", "All");
	load_current_session_value("type_id", "sess_mactrack_device_type_type_id", "-1");
   	load_current_session_value("sort_column", "sess_mactrack_device_type_sort_column", "description");
	load_current_session_value("sort_direction", "sess_mactrack_device_type_sort_direction", "ASC");

	$sql_where = "";

	$device_types = mactrack_get_device_types($sql_where, FALSE);

	$xport_array = array();
	array_push($xport_array, '"vendor","description","device_type",' .
		'"sysDescr_match","sysObjectID_match","scanning_function",' .
		'"serial_number_oid","lowPort","highPort"');

	if (sizeof($device_types)) {
		foreach($device_types as $device_type) {
			array_push($xport_array,'"' . $device_type['vendor'] . '","' .
			$device_type['description'] . '","' .
			$device_type['device_type'] . '","' .
			$device_type['sysDescr_match'] . '","' .
			$device_type['sysObjectID_match'] . '","' .
			$device_type['scanning_function'] . '","' .
			$device_type['serial_number_oid'] . '","' .
			$device_type['lowPort'] . '","' .
			$device_type['highPort'] . '"');
		}
	}

	header("Content-type: application/csv");
	header("Content-Disposition: attachment; filename=cacti_device_type_xport.csv");
	foreach($xport_array as $xport_line) {
		print $xport_line . "\n";
	}
}

function mactrack_device_type_import() {
	global $colors, $config;

	?><form method="post" action="mactrack_device_types.php?action=import" enctype="multipart/form-data"><?php

	if ((isset($_SESSION["import_debug_info"])) && (is_array($_SESSION["import_debug_info"]))) {
		html_start_box("<strong>Import Results</strong>", "100%", "aaaaaa", "3", "center", "");

		print "<tr bgcolor='#" . $colors["form_alternate1"] . "'><td><p class='textArea'>Cacti has imported the following items:</p>";
		foreach($_SESSION["import_debug_info"] as $import_result) {
			print "<tr bgcolor='#" . $colors["form_alternate1"] . "'><td>" . $import_result . "</td>";
			print "</tr>";
		}

		html_end_box();

		kill_session_var("import_debug_info");
	}

	html_start_box("<strong>Import MacTrack Device Types</strong>", "100%", $colors["header"], "3", "center", "");

	form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],0);?>
		<td width='50%'><font class='textEditTitle'>Import Device Types from Local File</font><br>
			Please specify the location of the CSV file containing your device type information.
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
			<strong>description</strong> - A common name for the device.  For example Cisco 6509 Switch<br>
			<strong>vendor</strong> - The vendor who produces this device<br>
			<strong>device_type</strong> - The type of device this is.  See the notes below for this integer value<br>
			<strong>sysDescr_match</strong> - A unique set of characters from the snmp sysDescr that uniquely identify this device<br>
			<strong>sysObjectID_match</strong> - The vendor specific snmp sysObjectID that distinguishes this device from the next<br>
			<strong>scanning_function</strong> - The scanning function that will be used to scan this device type<br>
			<strong>serial_number_oid</strong> - If the Serial Number for this device type can be obtained via an SNMP Query, add it's OID here<br>
			<strong>lowPort</strong> - If your scanning function does not have the ability to isolate trunk ports or link ports, this is the starting port number for user ports<br>
			<strong>highPort</strong> - Same as the lowPort with the exception that this is the high numbered user port number<br>
			<br>
			<strong>The primary key for this table is a combination of the following three fields:</strong>
			<br><br>
			device_type, sysDescr_match, sysObjectID_match
			<br><br>
			<strong>Therefore, if you attempt to import duplicate device types, the existing data will be updated with the new information.</strong>
			<br><br>
			<strong>device_type</strong> is an integer field and must be one of the following:
			<br><br>
			1 - Switch/Hub<br>
			2 - Switch/Router<br>
			3 - Router<br>
			<br>
			<strong>The devices device type is determined by scanning it's snmp agent for the sysObjectID and sysDescription and comparing it against
			values in the device types database.  The first match that is found in the database is used direct MacTrack as to how to scan it.  Therefore,
			it is very important that you select valid sysObjectID_match, sysDescr_match, and scanning function for your devices.</strong>
			<br>
		</td>
	</tr><?php

	form_hidden_box("save_component_import","1","");

	html_end_box();

	mactrack_save_button("return", "import");
}

function mactrack_device_type_import_processor(&$device_types) {
	$i = 0;
	$return_array = array();
	$insert_columns = array();

	$device_type_array[1] = "Switch/Hub";
	$device_type_array[2] = "Switch/Router";
	$device_type_array[3] = "Router";

	foreach($device_types as $device_type) {
		/* parse line */
		$line_array = explode(",", $device_type);

		/* header row */
		if ($i == 0) {
			$save_order = "(";
			$j = 0;
			$first_column = TRUE;
			$update_suffix = "";
			$required = 0;
			$sysDescr_match_id = -1;
			$sysObjectID_match_id = -1;
			$device_type_id = -1;
			$save_vendor_id = -1;
			$save_description_id = -1;

			foreach($line_array as $line_item) {
				$line_item = trim(str_replace("'", "", $line_item));
				$line_item = trim(str_replace('"', '', $line_item));

				switch ($line_item) {
					case 'device_type':
						if (!$first_column) {
							$save_order .= ", ";
						}

						$device_type_id = $j;
						$required++;

						$save_order .= $line_item;
						$insert_columns[] = $j;
						$first_column = FALSE;

						if (strlen($update_suffix)) {
							$update_suffix .= ", $line_item=VALUES($line_item)";
						}else{
							$update_suffix .= " ON DUPLICATE KEY UPDATE $line_item=VALUES($line_item)";
						}


						break;
					case 'sysDescr_match':
						if (!$first_column) {
							$save_order .= ", ";
						}

						$sysDescr_match_id = $j;
						$required++;

						$save_order .= $line_item;
						$insert_columns[] = $j;
						$first_column = FALSE;

						if (strlen($update_suffix)) {
							$update_suffix .= ", $line_item=VALUES($line_item)";
						}else{
							$update_suffix .= " ON DUPLICATE KEY UPDATE $line_item=VALUES($line_item)";
						}


						break;
					case 'sysObjectID_match':
						if (!$first_column) {
							$save_order .= ", ";
						}

						$sysObjectID_match_id = $j;
						$required++;

						$save_order .= $line_item;
						$insert_columns[] = $j;
						$first_column = FALSE;

						if (strlen($update_suffix)) {
							$update_suffix .= ", $line_item=VALUES($line_item)";
						}else{
							$update_suffix .= " ON DUPLICATE KEY UPDATE $line_item=VALUES($line_item)";
						}

						break;
					case 'scanning_function':
					case 'serial_number_oid':
					case 'lowPort':
					case 'highPort':
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
					case 'vendor':
						if (!$first_column) {
							$save_order .= ", ";
						}

						$save_order .= $line_item;
						$insert_columns[] = $j;
						$save_vendor_id = $j;
						$first_column = FALSE;

						if (strlen($update_suffix)) {
							$update_suffix .= ", $line_item=VALUES($line_item)";
						}else{
							$update_suffix .= " ON DUPLICATE KEY UPDATE $line_item=VALUES($line_item)";
						}

						break;
					case 'description':
						if (!$first_column) {
							$save_order .= ", ";
						}

						$save_order .= $line_item;
						$insert_columns[] = $j;
						$save_description_id = $j;
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

			foreach($line_array as $line_item) {
				if (in_array($j, $insert_columns)) {
					$line_item = trim(str_replace("'", "", $line_item));
					$line_item = trim(str_replace('"', '', $line_item));

					if (!$first_column) {
						$save_value .= ",";
					}else{
						$first_column = FALSE;
					}

					if ($j == $device_type_id || $j == $sysDescr_match_id || $j == $sysObjectID_match_id ) {
						if (strlen($sql_where)) {
							switch($j) {
							case $device_type_id:
								$sql_where .= " AND device_type='$line_item'";
								break;
							case $sysDescr_match_id:
								$sql_where .= " AND sysDescr_match='$line_item'";
								break;
							case $sysObjectID_match_id:
								$sql_where .= " AND sysObjectID_match='$line_item'";
								break;
							default:
								/* do nothing */
							}
						}else{
							switch($j) {
							case $device_type_id:
								$sql_where .= "WHERE device_type='$line_item'";
								break;
							case $sysDescr_match_id:
								$sql_where .= "WHERE sysDescr_match='$line_item'";
								break;
							case $sysObjectID_match_id:
								$sql_where .= "WHERE sysObjectID_match='$line_item'";
								break;
							default:
								/* do nothing */
							}
						}
					}

					if ($j == $device_type_id) {
						if (isset($device_type_array[$line_item])) {
							$device_type = $device_type_array[$line_item];
						}else{
							$device_type = "Uknown Assume 'Switch/Hub'";
							$line_item = 1;
						}
					}

					if ($j == $sysDescr_match_id) {
						$sysDescr_match = $line_item;
					}

					if ($j == $sysObjectID_match_id) {
						$sysObjectID_match = $line_item;
					}

					if ($j == $save_vendor_id) {
						$vendor = $line_item;
					}

					if ($j == $save_description_id) {
						$description = $line_item;
					}

					$save_value .= "'" . $line_item . "'";
				}

				$j++;
			}

			$save_value .= ")";

			if ($j > 0) {
				if (isset($_POST["allow_update"])) {
					$sql_execute = "INSERT INTO mac_track_device_types " . $save_order .
						" VALUES" . $save_value . $update_suffix;

					if (db_execute($sql_execute)) {
						array_push($return_array,"INSERT SUCCEEDED: Vendor: $vendor, Description: $description, Type: $device_type, sysDescr: $sysDescr_match, sysObjectID: $sysObjectID_match");
					}else{
						array_push($return_array,"<strong>INSERT FAILED:</strong> Vendor: $vendor, Description: $description, Type: $device_type, sysDescr: $sysDescr_match, sysObjectID: $sysObjectID_match");
					}
				}else{
					/* perform check to see if the row exists */
					$existing_row = db_fetch_row("SELECT * FROM mac_track_device_types $sql_where");

					if (sizeof($existing_row)) {
						array_push($return_array,"<strong>INSERT SKIPPED, EXISTING:</strong> Vendor: $vendor, Description: $description, Type: $device_type, sysDescr: $sysDescr_match, sysObjectID: $sysObjectID_match");
					}else{
						$sql_execute = "INSERT INTO mac_track_device_types " . $save_order .
							" VALUES" . $save_value;

						if (db_execute($sql_execute)) {
							array_push($return_array,"INSERT SUCCEEDED: Vendor: $vendor, Description: $description, Type: $device_type, sysDescr: $sysDescr_match, sysObjectID: $sysObjectID_match");
						}else{
							array_push($return_array,"<strong>INSERT FAILED:</strong> Vendor: $vendor, Description: $description, Type: $device_type, sysDescr: $sysDescr_match, sysObjectID: $sysObjectID_match");
						}
					}
				}
			}
		}

		$i++;
	}

	return $return_array;
}

function mactrack_device_type_remove() {
	global $config;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("device_type_id"));
	/* ==================================================== */

	if ((read_config_option("remove_verification") == "on") && (!isset($_GET["confirm"]))) {
		include("./include/top_header.php");
		form_confirm("Are You Sure?", "Are you sure you want to delete the device type<strong>'" . db_fetch_cell("select description from host where id=" . $_GET["device_id"]) . "'</strong>?", "mactrack_device_types.php", "mactrack_device_types.php?action=remove&id=" . $_GET["device_type_id"]);
		include("./include/bottom_footer.php");
		exit;
	}

	if ((read_config_option("remove_verification") == "") || (isset($_GET["confirm"]))) {
		api_mactrack_device_type_remove($_GET["device_type_id"]);
	}
}

function mactrack_device_type_edit() {
	global $colors, $fields_mactrack_device_type_edit;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("device_type_id"));
	/* ==================================================== */

	display_output_messages();

	if (!empty($_GET["device_type_id"])) {
		$device_type = db_fetch_row("select * from mac_track_device_types where device_type_id=" . $_GET["device_type_id"]);
		$header_label = "[edit: " . $device_type["description"] . "]";
	}else{
		$header_label = "[new]";
	}

	html_start_box("<strong>MacTrack Device Types</strong> $header_label", "100%", $colors["header"], "3", "center", "");

	draw_edit_form(array(
		"config" => array("form_name" => "chk"),
		"fields" => inject_form_variables($fields_mactrack_device_type_edit, (isset($device_type) ? $device_type : array()))
		));

	html_end_box();

	if (isset($device_type)) {
		mactrack_save_button("return", "save", "", "device_type_id");
	}else{
		mactrack_save_button("cancel", "save", "", "device_type_id");
	}
}

function mactrack_get_device_types(&$sql_where, $apply_limits = TRUE) {
	if ($_REQUEST["vendor"] == "All") {
		/* Show all items */
	}else{
		$sql_where = " WHERE (mac_track_device_types.vendor='" . $_REQUEST["vendor"] . "')";
	}

	if ($_REQUEST["type_id"] == "-1") {
		/* Show all items */
	}else{
		if (strlen($sql_where) > 0) {
			$sql_where .= " AND (mac_track_device_types.device_type=" . $_REQUEST["type_id"] . ")";
		}else{
			$sql_where .= " WHERE (mac_track_device_types.device_type=" . $_REQUEST["type_id"] . ")";
		}
	}

	$query_string = "SELECT *
		FROM mac_track_device_types
		$sql_where
		ORDER BY " . $_REQUEST["sort_column"] . " " . $_REQUEST["sort_direction"];

	if ($apply_limits) {
		$query_string .= " LIMIT " . ($_REQUEST["rows"]*($_REQUEST["page"]-1)) . "," . $_REQUEST["rows"];
	}

	return db_fetch_assoc($query_string);
}

function mactrack_device_type() {
	global $colors, $device_types_actions, $mactrack_device_types, $config, $item_rows;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("page"));
	input_validate_input_number(get_request_var_request("type_id"));
	input_validate_input_number(get_request_var_request("rows"));
	/* ==================================================== */

	/* clean up the vendor string */
	if (isset($_REQUEST["vendor"])) {
		$_REQUEST["vendor"] = sanitize_search_string(get_request_var("vendor"));
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
		kill_session_var("sess_mactrack_device_rows");
		kill_session_var("sess_mactrack_device_type_vendor");
		kill_session_var("sess_mactrack_device_type_type_id");
		kill_session_var("sess_mactrack_device_type_sort_column");
		kill_session_var("sess_mactrack_device_type_sort_direction");

		unset($_REQUEST["page"]);
		unset($_REQUEST["vendor"]);
		unset($_REQUEST["type_id"]);
		unset($_REQUEST["rows"]);
		unset($_REQUEST["sort_column"]);
		unset($_REQUEST["sort_direction"]);
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page", "sess_mactrack_device_type_current_page", "1");
	load_current_session_value("vendor", "sess_mactrack_device_type_vendor", "All");
	load_current_session_value("type_id", "sess_mactrack_device_type_type_id", "-1");
	load_current_session_value("rows", "sess_mactrack_device_type_rows", "-1");
	load_current_session_value("sort_column", "sess_mactrack_device_type_sort_column", "description");
	load_current_session_value("sort_direction", "sess_mactrack_device_type_sort_direction", "ASC");

	if ($_REQUEST["rows"] == -1) {
		$row_limit = read_config_option("num_rows_mactrack");
	}elseif ($_REQUEST["rows"] == -2) {
		$row_limit = 999999;
	}else{
		$row_limit = $_REQUEST["rows"];
	}

	html_start_box("<strong>MacTrack Device Type Filters</strong>", "100%", $colors["header"], "3", "center", "mactrack_device_types.php?action=edit");

	include("plugins/mactrack/html/inc_mactrack_device_type_filter_table.php");

	html_end_box();

	$sql_where = "";

	$device_types = mactrack_get_device_types($sql_where);

	html_start_box("", "100%", $colors["header"], "3", "center", "");

	$total_rows = db_fetch_cell("SELECT
		COUNT(mac_track_device_types.device_type_id)
		FROM mac_track_device_types" . $sql_where);

	/* generate page list */
	$url_page_select = get_page_list($_REQUEST["page"], MAX_DISPLAY_PAGES, $_REQUEST["rows"], $total_rows, "mactrack_device_types.php?");

	$nav = "<tr bgcolor='#" . $colors["header"] . "'>
			<td colspan='7'>
				<table width='100%' cellspacing='0' cellpadding='0' border='0'>
					<tr>
						<td align='left' class='textHeaderDark'>
							<strong>&lt;&lt; "; if ($_REQUEST["page"] > 1) { $nav .= "<a class='linkOverDark' href='mactrack_device_types.php?page=" . ($_REQUEST["page"]-1) . "'>"; } $nav .= "Previous"; if ($_REQUEST["page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
						</td>\n
						<td align='center' class='textHeaderDark'>
							Showing Rows " . (($_REQUEST["rows"]*($_REQUEST["page"]-1))+1) . " to " . ((($total_rows < $_REQUEST["rows"]) || ($total_rows < ($_REQUEST["rows"]*$_REQUEST["page"]))) ? $total_rows : ($_REQUEST["rows"]*$_REQUEST["page"])) . " of $total_rows [$url_page_select]
						</td>\n
						<td align='right' class='textHeaderDark'>
							<strong>"; if (($_REQUEST["page"] * $_REQUEST["rows"]) < $total_rows) { $nav .= "<a class='linkOverDark' href='mactrack_device_types.php?page=" . ($_REQUEST["page"]+1) . "'>"; } $nav .= "Next"; if (($_REQUEST["page"] * $_REQUEST["rows"]) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
						</td>\n
					</tr>
				</table>
			</td>
		</tr>\n";

	if ($total_rows) {
		print $nav;
	}

	$display_text = array(
		"description" => array("Device Type<br>Description", "ASC"),
		"vendor" => array("<br>Devices", "DESC"),
		"device_type" => array("Device<br>Type", "DESC"),
		"sysDescr_match" => array("sysDescription<br>Match", "DESC"),
		"sysObjectID_match" => array("Vendor OID<br>Match", "DESC"));

	html_header_sort_checkbox($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"]);

	$i = 0;
	if (sizeof($device_types) > 0) {
		foreach ($device_types as $device_type) {
			form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
				?>
				<td width=170>
					<a class="linkEditMain" href="mactrack_device_types.php?action=edit&device_type_id=<?php print $device_type["device_type_id"];?>"><?php print $device_type["description"];?></a>
				</td>
				<td><?php print $device_type["vendor"];?></td>
				<td><?php print $mactrack_device_types[$device_type["device_type"]];?></td>
				<td><?php print $device_type["sysDescr_match"];?></td>
				<td><?php print $device_type["sysObjectID_match"];?></td>
				<td style="<?php print get_checkbox_style();?>" width="1%" align="right">
					<input type='checkbox' style='margin: 0px;' name='chk_<?php print $device_type["device_type_id"];?>' title="<?php print $device_type["description"];?>">
				</td>
			</tr>
			<?php
		}

		/* put the nav bar on the bottom as well */
		print $nav;
	}else{
		print "<tr><td><em>No MacTrack Device Types</em></td></tr>";
	}
	html_end_box(false);

	/* draw the dropdown containing a list of available actions for this form */
	mactrack_draw_actions_dropdown($device_types_actions);
}

?>