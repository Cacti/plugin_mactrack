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

$guest_account = true;

chdir('../../');
include("./include/auth.php");
include_once("./include/global_arrays.php");
include_once("./plugins/mactrack/lib/mactrack_functions.php");

define("MAX_DISPLAY_PAGES", 21);

$mactrack_view_macs_actions = array(
	1 => "Authorize",
	2 => "Revoke"
	);
$mactrack_view_agg_macs_actions = array(
	"01" => "Delete"
	);
/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

/* correct for a cancel button */
if (isset($_REQUEST["cancel_x"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
case 'actions':
	if ($_REQUEST["drp_action"] !== '01') {
	form_actions();
	}else{
		form_aggregated_actions();
	}

	break;
default:
	if (isset($_REQUEST["export_x"])) {
		mactrack_view_export_macs();
	}else{
		$_REQUEST["action"] = ""; # avoid index error in top_graph_header.php
		mactrack_redirect();
		$title = "Device Tracking - MAC to IP Report View";
		include_once("./include/top_graph_header.php");

		if (isset($_REQUEST["scan_date"]) && $_REQUEST["scan_date"] == 3) {
			mactrack_view_aggregated_macs();
		}elseif(isset($_REQUEST["scan_date"])){
			mactrack_view_macs();
		}else{
			if (isset($_SESSION["sess_mactrack_view_macs_rowstoshow"]) && ($_SESSION["sess_mactrack_view_macs_rowstoshow"] != 3)) {
				mactrack_view_macs();
			}else{
				mactrack_view_aggregated_macs();
			}
		}
		include("./include/bottom_footer.php");
	}

	break;
}

/* ------------------------
    The "actions" function
   ------------------------ */

function form_actions() {
	global $colors, $config, $mactrack_view_macs_actions;

	/* if we are to save this form, instead of display it */
	if (isset($_POST["selected_items"])) {
		$selected_items = unserialize(stripslashes($_POST["selected_items"]));

		if ($_POST["drp_action"] == "1") { /* Authorize */
			if (sizeof($selected_items)) {
			foreach($selected_items as $mac) {
				$mac = sanitize_search_string($mac);

				api_mactrack_authorize_mac_addresses($mac);
			}
			}
		}elseif ($_POST["drp_action"] == "2") { /* Revoke */
			$errors = "";
			if (sizeof($selected_items)) {
			foreach($selected_items as $mac) {
				/* clean up the mac_address */
				$mac = sanitize_search_string($mac);

				$mac_found = db_fetch_cell("SELECT mac_address FROM mac_track_macauth WHERE mac_address='$mac'");

				if ($mac_found) {
					api_mactrack_revoke_mac_addresses($mac);
				}else{
					$errors .= ", $mac";
				}
			}
			}

			if ($errors) {
				$_SESSION["sess_messages"] = "The following MAC Addresses Could not be revoked because they are members of Group Authorizations" . $errors;
			}
		}

		header("Location: mactrack_view_macs.php");
		exit;
	}

	/* setup some variables */
	$mac_address_list = "";
	$delim = read_config_option("mt_mac_delim");

	/* loop through each of the device types selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (substr($var,0,4) == "chk_") {
			$matches = substr($var,4);

			/* clean up the mac_address */
			if (isset($matches)) {
				$matches = sanitize_search_string($matches);
				$parts   = explode("-", $matches);
				$mac     = str_replace("_", $delim, $parts[0]);
			}

			if (!isset($mac_address_array[$mac])) {
				$mac_address_list .= "<li>" . $mac . "<br>";
				$mac_address_array[$mac] = $mac;
			}
		}
	}

	include_once("./include/top_header.php");

	html_start_box("<strong>" . $mactrack_view_macs_actions{$_POST["drp_action"]} . "</strong>", "60%", $colors["header_panel"], "3", "center", "");

	print "<form action='mactrack_view_macs.php' method='post'>\n";

	if ($_POST["drp_action"] == "1") { /* Authorize Macs */
		print "	<tr>
				<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>Are you sure you want to Authorize the following MAC Addresses?</p>
					<p>$mac_address_list</p>
				</td>
			</tr>\n
			";
	}elseif ($_POST["drp_action"] == "2") { /* Revoke Macs */
		print "	<tr>
				<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>Are you sure you want to Revoke the following MAC Addresses?</p>
					<p>$mac_address_list</p>
				</td>
			</tr>\n
			";
	}

	if (!isset($mac_address_array)) {
		print "<tr><td bgcolor='#" . $colors["form_alternate1"]. "'><span class='textError'>You must select at least one MAC Address.</span></td></tr>\n";
		$save_html = "";
	}else if (!mactrack_check_user_realm(2122)) {
		print "<tr><td bgcolor='#" . $colors["form_alternate1"]. "'><span class='textError'>You are not permitted to change Mac Authorizations.</span></td></tr>\n";
		$save_html = "";
	}else{
		$save_html = "<input type='submit' name='save_x' value='Yes'>";
	}

	print "	<tr>
			<td colspan='2' align='right' bgcolor='#eaeaea'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='selected_items' value='" . (isset($mac_address_array) ? serialize($mac_address_array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . $_POST["drp_action"] . "'>" . (strlen($save_html) ? "
				<input type='submit' name='cancel_x' value='No'>
				$save_html" : "<input type='submit' name='cancel_x' value='Return'>") . "
			</td>
		</tr>
		";

	html_end_box();

	include_once("./include/bottom_footer.php");
}

function form_aggregated_actions() {
	global $colors, $config, $mactrack_view_agg_macs_actions;

	/* if we are to save this form, instead of display it */
	if (isset($_POST["selected_items"])) {
		$selected_items = unserialize(stripslashes($_POST["selected_items"]));
 		$str_ids = '';
		 for ($i=0;($i<count($selected_items));$i++) {
			 /* ================= input validation ================= */
			 input_validate_input_number($selected_items[$i]);
			 /* ==================================================== */
			 $str_ids = $str_ids . "'" . $selected_items[$i] . "', ";
		 }
		 $str_ids = substr($str_ids, 0, strlen($str_ids) -2);

		if ($_POST["drp_action"] == "01") { /* Delete */
			if (sizeof($selected_items)) {
				db_execute("DELETE FROM mac_track_aggregated_ports WHERE row_id IN (" . $str_ids . ");");
			}
		}

		header("Location: mactrack_view_macs.php");
		exit;
	}

	/* setup some variables */
	$mac_address_list = "";$row_list = ""; $i = 0; $row_ids = "";

	/* loop through each of the ports selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (ereg("^chk_([0-9]+)$", $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$row_array[$i] = $matches[1];
			$row_ids = $row_ids . "'" . $matches[1] . "', ";
		}

		$i++;
	}

	$row_ids   = substr($row_ids, 0, strlen($row_ids) -2);
	$rows_info = db_fetch_assoc("SELECT device_name, mac_address, ip_address, port_number, count_rec FROM mac_track_aggregated_ports WHERE row_id IN (" . $row_ids . ");");
	if (isset($rows_info)) {
		foreach($rows_info as $row_info) {
			$row_list .= "<li> Dev.:" . $row_info["device_name"] . " IP.:" . $row_info["ip_address"] . " MAC.:" . $row_info["mac_address"] ." PORT.:" . $row_info["port_number"] . " Count.: [" . $row_info["count_rec"] . "]<br>";
		}
	}

	include_once("./include/top_header.php");

	html_start_box("<strong>" . $mactrack_view_agg_macs_actions{$_POST["drp_action"]} . "</strong>", "60%", $colors["header_panel"], "3", "center", "");

	print "<form action='mactrack_view_macs.php' method='post'>\n";

	if ($_POST["drp_action"] == "1") { /* Delete Macs */
		print "	<tr>
				<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>Are you sure you want to Delete the following rows from Aggregated table?</p>
					<p>$row_list</p>
				</td>
			</tr>\n
			";
	}

	if (!isset($row_array)) {
		print "<tr><td bgcolor='#" . $colors["form_alternate1"]. "'><span class='textError'>You must select at least one Row.</span></td></tr>\n";
		$save_html = "";
	}else if (!mactrack_check_user_realm(2122)) {
		print "<tr><td bgcolor='#" . $colors["form_alternate1"]. "'><span class='textError'>You are not permitted to delete rows.</span></td></tr>\n";
		$save_html = "";
	}else{
		$save_html = "<input type='submit' name='save_x' value='Yes'>";
	}

	print "	<tr>
			<td colspan='2' align='right' bgcolor='#eaeaea'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='selected_items' value='" . (isset($row_array) ? serialize($row_array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . $_POST["drp_action"] . "'>" . (strlen($save_html) ? "
				<input type='submit' name='cancel_x' value='No'>
				$save_html" : "<input type='submit' name='cancel_x' value='Return'>") . "
			</td>
		</tr>
		";

	html_end_box();

	include_once("./include/bottom_footer.php");
}

function api_mactrack_authorize_mac_addresses($mac_address){
	db_execute("UPDATE mac_track_ports SET authorized='1' WHERE mac_address='$mac_address'");
	db_execute("REPLACE INTO mac_track_macauth SET mac_address='$mac_address', description='Added from MacView', added_by='" . $_SESSION["sess_user_id"] . "'");
}

function api_mactrack_revoke_mac_addresses($mac_address){
	db_execute("UPDATE mac_track_ports SET authorized='0' WHERE mac_address='$mac_address'");
	db_execute("DELETE FROM mac_track_macauth WHERE mac_address='$mac_address'");
}

function mactrack_view_export_macs() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("site_id"));
	input_validate_input_number(get_request_var_request("device_id"));
	input_validate_input_number(get_request_var_request("mac_filter_type_id"));
	input_validate_input_number(get_request_var_request("ip_filter_type_id"));
	input_validate_input_number(get_request_var_request("rows"));
	input_validate_input_number(get_request_var_request("page"));
	/* ==================================================== */

	/* clean up filter string */
	if (isset($_REQUEST["ip_filter"])) {
		$_REQUEST["ip_filter"] = sanitize_search_string(get_request_var("ip_filter"));
	}

	/* clean up search string */
	if (isset($_REQUEST["mac_filter"])) {
		$_REQUEST["mac_filter"] = sanitize_search_string(get_request_var("mac_filter"));
	}

	/* clean up sort_column */
	if (isset($_REQUEST["sort_column"])) {
		$_REQUEST["sort_column"] = sanitize_search_string(get_request_var("sort_column"));
	}

	/* clean up search string */
	if (isset($_REQUEST["sort_direction"])) {
		$_REQUEST["sort_direction"] = sanitize_search_string(get_request_var("sort_direction"));
	}

	if (isset($_REQUEST["mac_filter_type_id"])) {
		if ($_REQUEST["mac_filter_type_id"] == 1) {
			unset($_REQUEST["mac_filter"]);
		}
	}

	/* clean up search string */
	if (isset($_REQUEST["scan_date"])) {
		$_REQUEST["scan_date"] = sanitize_search_string(get_request_var("scan_date"));
	}

	/* clean up search string */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var("filter"));
	}

	if (isset($_REQUEST["ip_filter_type_id"])) {
		if ($_REQUEST["ip_filter_type_id"] == 1) {
			unset($_REQUEST["ip_filter"]);
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page", "sess_mactrack_view_macs_current_page", "1");
	load_current_session_value("scan_date", "sess_mactrack_view_macs_scan_date", "2");
	load_current_session_value("filter", "sess_mactrack_view_macs_filter", "");
	load_current_session_value("mac_filter_type_id", "sess_mactrack_view_macs_mac_filter_type_id", "1");
	load_current_session_value("mac_filter", "sess_mactrack_view_macs_mac_filter", "");
	load_current_session_value("ip_filter_type_id", "sess_mactrack_view_macs_ip_filter_type_id", "1");
	load_current_session_value("ip_filter", "sess_mactrack_view_macs_ip_filter", "");
	load_current_session_value("rows", "sess_mactrack_view_macs_rows_selector", "-1");
	load_current_session_value("site_id", "sess_mactrack_view_macs_site_id", "-1");
	load_current_session_value("device_id", "sess_mactrack_view_macs_device_id", "-1");
	load_current_session_value("sort_column", "sess_mactrack_view_macs_sort_column", "device_name");
	load_current_session_value("sort_direction", "sess_mactrack_view_macs_sort_direction", "ASC");

	$sql_where = "";

	$port_results = mactrack_view_get_mac_records($sql_where, 0, FALSE);

	$xport_array = array();
	array_push($xport_array, '"site_name","hostname","device_name",' .
		'"vlan_id","vlan_name","mac_address","vendor_name",' .
		'"ip_address","dns_hostname","port_number","port_name","scan_date"');

	if (sizeof($port_results)) {
		foreach($port_results as $port_result) {
			if ($_REQUEST["scan_date"] == 1) {
				$scan_date = $port_result["scan_date"];
			}else{
				$scan_date = $port_result["max_scan_date"];
			}

			array_push($xport_array,'"' . $port_result['site_name'] . '","' .
			$port_result['hostname'] . '","' . $port_result['device_name'] . '","' .
			$port_result['vlan_id'] . '","' . $port_result['vlan_name'] . '","' .
			$port_result['mac_address'] . '","' . $port_result['vendor_name'] . '","' .
			$port_result['ip_address'] . '","' . $port_result['dns_hostname'] . '","' .
			$port_result['port_number'] . '","' . $port_result['port_name'] . '","' .
			$scan_date . '"');
		}
	}

	header("Content-type: application/csv");
	header("Content-Disposition: attachment; filename=cacti_port_macs_xport.csv");
	foreach($xport_array as $xport_line) {
		print $xport_line . "\n";
	}
}

function mactrack_view_get_mac_records(&$sql_where, $apply_limits = TRUE, $row_limit = -1) {
	/* form the 'where' clause for our main sql query */
	if (strlen($_REQUEST["mac_filter"])) {
		switch ($_REQUEST["mac_filter_type_id"]) {
			case "1": /* do not filter */
				break;
			case "2": /* matches */
				$sql_where .= (strlen($sql_where) ? " AND":"WHERE") . " mac_track_ports.mac_address='" . $_REQUEST["mac_filter"] . "'";
				break;
			case "3": /* contains */
				$sql_where .= (strlen($sql_where) ? " AND":"WHERE") . " mac_track_ports.mac_address LIKE '%%" . $_REQUEST["mac_filter"] . "%%'";
				break;
			case "4": /* begins with */
				$sql_where .= (strlen($sql_where) ? " AND":"WHERE") . " mac_track_ports.mac_address LIKE '" . $_REQUEST["mac_filter"] . "%%'";
				break;
			case "5": /* does not contain */
				$sql_where .= (strlen($sql_where) ? " AND":"WHERE") . " mac_track_ports.mac_address NOT LIKE '" . $_REQUEST["mac_filter"] . "%%'";
				break;
			case "6": /* does not begin with */
				$sql_where .= (strlen($sql_where) ? " AND":"WHERE") . " mac_track_ports.mac_address NOT LIKE '" . $_REQUEST["mac_filter"] . "%%'";
		}
	}

	if ((strlen($_REQUEST["ip_filter"]) > 0)||($_REQUEST["ip_filter_type_id"] > 5)) {
		switch ($_REQUEST["ip_filter_type_id"]) {
			case "1": /* do not filter */
				break;
			case "2": /* matches */
				$sql_where .= (strlen($sql_where) ? " AND":"WHERE") . " mac_track_ports.ip_address='" . $_REQUEST["ip_filter"] . "'";
				break;
			case "3": /* contains */
				$sql_where .= (strlen($sql_where) ? " AND":"WHERE") . " mac_track_ports.ip_address LIKE '%%" . $_REQUEST["ip_filter"] . "%%'";
				break;
			case "4": /* begins with */
				$sql_where .= (strlen($sql_where) ? " AND":"WHERE") . " mac_track_ports.ip_address LIKE '" . $_REQUEST["ip_filter"] . "%%'";
				break;
			case "5": /* does not contain */
				$sql_where .= (strlen($sql_where) ? " AND":"WHERE") . " mac_track_ports.ip_address NOT LIKE '" . $_REQUEST["ip_filter"] . "%%'";
				break;
			case "6": /* does not begin with */
				$sql_where .= (strlen($sql_where) ? " AND":"WHERE") . " mac_track_ports.ip_address NOT LIKE '" . $_REQUEST["ip_filter"] . "%%'";
				break;
			case "7": /* is null */
				$sql_where .= (strlen($sql_where) ? " AND":"WHERE") . " mac_track_ports.ip_address = ''";
				break;
			case "8": /* is not null */
				$sql_where .= (strlen($sql_where) ? " AND":"WHERE") . " mac_track_ports.ip_address != ''";
		}
	}

	if (strlen($_REQUEST["filter"])) {
		if (strlen(read_config_option("mt_reverse_dns")) > 0) {
			$sql_where .= (strlen($sql_where) ? " AND":"WHERE") .
				" (mac_track_ports.dns_hostname LIKE '%" . $_REQUEST["filter"] . "%' OR " .
				"mac_track_ports.device_name LIKE '%" . $_REQUEST["filter"] . "%' OR " .
				"mac_track_ports.hostname LIKE '%" . $_REQUEST["filter"] . "%' OR " .
				"mac_track_ports.port_name LIKE '%" . $_REQUEST["filter"] . "%' OR " .
				"mac_track_oui_database.vendor_name LIKE '%%" . $_REQUEST["filter"] . "%%' OR " .
				"mac_track_ports.vlan_name LIKE '%" . $_REQUEST["filter"] . "%')";
		}else{
			$sql_where .= (strlen($sql_where) ? " AND":"WHERE") .
				" (mac_track_ports.device_name LIKE '%" . $_REQUEST["filter"] . "%' OR " .
				"mac_track_ports.hostname LIKE '%" . $_REQUEST["filter"] . "%' OR " .
				"mac_track_ports.port_name LIKE '%" . $_REQUEST["filter"] . "%' OR " .
				"mac_track_oui_database.vendor_name LIKE '%%" . $_REQUEST["filter"] . "%%' OR " .
				"mac_track_ports.vlan_name LIKE '%" . $_REQUEST["filter"] . "%')";
		}
	}

	if ($_REQUEST["authorized"] != "-1") {
		$sql_where .= (strlen($sql_where) ? " AND":"WHERE") . " mac_track_ports.authorized=" . $_REQUEST["authorized"];
	}

	if (!($_REQUEST["site_id"] == "-1")) {
		$sql_where .= (strlen($sql_where) ? " AND":"WHERE") . " mac_track_ports.site_id=" . $_REQUEST["site_id"];
	}

	if (!($_REQUEST["vlan"] == "-1")) {
		$sql_where .= (strlen($sql_where) ? " AND":"WHERE") . " mac_track_ports.vlan_id=" . $_REQUEST["vlan"];
	}

	if (!($_REQUEST["device_id"] == "-1")) {
		$sql_where .= (strlen($sql_where) ? " AND":"WHERE") . " mac_track_ports.device_id=" . $_REQUEST["device_id"];
	}

	if (($_REQUEST["scan_date"] != "1") && ($_REQUEST["scan_date"] != "2") && ($_REQUEST["scan_date"] != "3")) {
		$sql_where .= (strlen($sql_where) ? " AND":"WHERE") . " mac_track_ports.scan_date='" . $_REQUEST["scan_date"] . "'";
	}

	/* prevent table scans, either a device or site must be selected */
	if ($_REQUEST["site_id"] == -1 && $_REQUEST["device_id"] == -1) {
		if (!strlen($sql_where)) return array();
	}

	if ($_REQUEST["scan_date"] == 3) {
		$query_string = "SELECT
			row_id, site_name, device_id, device_name, hostname, mac_address, vendor_name, ip_address, dns_hostname, port_number,
			port_name, vlan_id, vlan_name, date_last, count_rec, active_last
			FROM mac_track_aggregated_ports
			LEFT JOIN mac_track_sites
			ON (mac_track_aggregated_ports.site_id=mac_track_sites.site_id)
			LEFT JOIN mac_track_oui_database
			ON (mac_track_oui_database.vendor_mac=mac_track_aggregated_ports.vendor_mac) " .
			str_replace("mac_track_ports", "mac_track_aggregated_ports", $sql_where) .
			" ORDER BY " . $_REQUEST["sort_column"] . " " . $_REQUEST["sort_direction"];

		if (($apply_limits) && ($row_limit != 999999)) {
			$query_string .= " LIMIT " . ($row_limit*($_REQUEST["page"]-1)) . "," . $row_limit;
		}
	}elseif (($_REQUEST["scan_date"] != 2)) {
		$query_string = "SELECT
			site_name, device_id, device_name, hostname, mac_address, vendor_name, ip_address, dns_hostname, port_number,
			port_name, vlan_id, vlan_name, scan_date
			FROM mac_track_ports
			LEFT JOIN mac_track_sites
			ON (mac_track_ports.site_id = mac_track_sites.site_id)
			LEFT JOIN mac_track_oui_database
			ON (mac_track_oui_database.vendor_mac = mac_track_ports.vendor_mac)
			$sql_where
			ORDER BY " . $_REQUEST["sort_column"] . " " . $_REQUEST["sort_direction"];

		if (($apply_limits) && ($row_limit != 999999)) {
			$query_string .= " LIMIT " . ($row_limit*($_REQUEST["page"]-1)) . "," . $row_limit;
		}
	}else{
		$query_string = "SELECT
			site_name, device_id, device_name, hostname, mac_address, vendor_name, ip_address, dns_hostname, port_number,
			port_name, vlan_id, vlan_name, MAX(scan_date) as max_scan_date
			FROM mac_track_ports
			LEFT JOIN mac_track_sites
			ON (mac_track_ports.site_id = mac_track_sites.site_id)
			LEFT JOIN mac_track_oui_database
			ON (mac_track_oui_database.vendor_mac = mac_track_ports.vendor_mac)
			$sql_where
			GROUP BY device_id, mac_address, port_number, ip_address
			ORDER BY " . $_REQUEST["sort_column"] . " " . $_REQUEST["sort_direction"];

		if (($apply_limits) && ($row_limit != 999999)) {
			$query_string .= " LIMIT " . ($row_limit*($_REQUEST["page"]-1)) . "," . $row_limit;
		}
	}

	if (strlen($sql_where) == 0) {
		return array();
	}else{
		return db_fetch_assoc($query_string);
	}
}

function mactrack_view_macs() {
	global $title, $report, $colors, $mactrack_search_types, $rows_selector, $config;
	global $mactrack_view_macs_actions, $item_rows;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("site_id"));
	input_validate_input_number(get_request_var_request("device_id"));
	input_validate_input_number(get_request_var_request("mac_filter_type_id"));
	input_validate_input_number(get_request_var_request("ip_filter_type_id"));
	input_validate_input_number(get_request_var_request("rows"));
	input_validate_input_number(get_request_var_request("authorized"));
	input_validate_input_number(get_request_var_request("vlan"));
	input_validate_input_number(get_request_var_request("page"));
	/* ==================================================== */

	/* clean up filter string */
	if (isset($_REQUEST["ip_filter"])) {
		$_REQUEST["ip_filter"] = sanitize_search_string(get_request_var("ip_filter"));
	}

	/* clean up search string */
	if (isset($_REQUEST["mac_filter"])) {
		$_REQUEST["mac_filter"] = sanitize_search_string(get_request_var("mac_filter"));
	}

	/* clean up sort_column */
	if (isset($_REQUEST["sort_column"])) {
		$_REQUEST["sort_column"] = sanitize_search_string(get_request_var("sort_column"));
	}

	/* clean up search string */
	if (isset($_REQUEST["sort_direction"])) {
		$_REQUEST["sort_direction"] = sanitize_search_string(get_request_var("sort_direction"));
	}

	if (isset($_REQUEST["mac_filter_type_id"])) {
		if ($_REQUEST["mac_filter_type_id"] == 1) {
			unset($_REQUEST["mac_filter"]);
		}
	}

	/* clean up search string */
	if (isset($_REQUEST["scan_date"])) {
		$_REQUEST["scan_date"] = sanitize_search_string(get_request_var("scan_date"));
	}

	/* clean up search string */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var("filter"));
	}

	if (isset($_REQUEST["ip_filter_type_id"])) {
		if ($_REQUEST["ip_filter_type_id"] == 1) {
			unset($_REQUEST["ip_filter"]);
		}
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST["clear_x"]) || isset($_REQUEST["reset"])) {
		kill_session_var("sess_mactrack_view_macs_current_page");
		kill_session_var("sess_mactrack_view_macs_rowstoshow");
		kill_session_var("sess_mactrack_view_macs_filter");
		kill_session_var("sess_mactrack_view_macs_mac_filter_type_id");
		kill_session_var("sess_mactrack_view_macs_mac_filter");
		kill_session_var("sess_mactrack_view_macs_ip_filter_type_id");
		kill_session_var("sess_mactrack_view_macs_ip_filter");
		kill_session_var("sess_mactrack_view_macs_rows_selector");
		kill_session_var("sess_mactrack_view_macs_site_id");
		kill_session_var("sess_mactrack_view_macs_vlan_id");
		kill_session_var("sess_mactrack_view_macs_authorized");
		kill_session_var("sess_mactrack_view_macs_device_id");
		kill_session_var("sess_mactrack_view_macs_sort_column");
		kill_session_var("sess_mactrack_view_macs_sort_direction");

		$_REQUEST["page"] = 1;

		if (isset($_REQUEST["clear_x"])) {
			unset($_REQUEST["scan_date"]);
			unset($_REQUEST["mac_filter"]);
			unset($_REQUEST["mac_filter_type_id"]);
			unset($_REQUEST["ip_filter"]);
			unset($_REQUEST["ip_filter_type_id"]);
			unset($_REQUEST["rows"]);
			unset($_REQUEST["filter"]);
			unset($_REQUEST["site_id"]);
			unset($_REQUEST["vlan"]);
			unset($_REQUEST["authorized"]);
			unset($_REQUEST["device_id"]);
			unset($_REQUEST["sort_column"]);
			unset($_REQUEST["sort_direction"]);
		}
	}else{
		/* if any of the settings changed, reset the page number */
		$changed = 0;
		$changed += mactrack_check_changed("scan_date",          "sess_mactrack_view_macs_rowstoshow");
		$changed += mactrack_check_changed("mac_filter",         "sess_mactrack_view_macs_filter");
		$changed += mactrack_check_changed("mac_filter_type_id", "sess_mactrack_view_macs_mac_filter_type_id");
		$changed += mactrack_check_changed("ip_filter",          "sess_mactrack_view_macs_mac_ip_filter");
		$changed += mactrack_check_changed("ip_filter_type_id",  "sess_mactrack_view_macs_ip_filter_type_id");
		$changed += mactrack_check_changed("filter",             "sess_mactrack_view_macs_ip_filter");
		$changed += mactrack_check_changed("rows",               "sess_mactrack_view_macs_rows_selector");
		$changed += mactrack_check_changed("site_id",            "sess_mactrack_view_macs_site_id");
		$changed += mactrack_check_changed("vlan",               "sess_mactrack_view_macs_vlan_id");
		$changed += mactrack_check_changed("authorized",         "sess_mactrack_view_macs_authorized");
		$changed += mactrack_check_changed("device_id",          "sess_mactrack_view_macs_device_id");

		if ($changed) {
			$_REQUEST["page"] = "1";
		}
	}

	/* reset some things if the user has made changes */
	if ((!empty($_REQUEST["site_id"]))&&(!empty($_SESSION["sess_mactrack_view_macs_site_id"]))) {
		if ($_REQUEST["site_id"] <> $_SESSION["sess_mactrack_view_macs_site_id"]) {
			$_REQUEST["device_id"] = "-1";
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page",               "sess_mactrack_view_macs_current_page", "1");
	load_current_session_value("scan_date",          "sess_mactrack_view_macs_rowstoshow", "2");
	load_current_session_value("mac_filter",         "sess_mactrack_view_macs_mac_filter", "");
	load_current_session_value("mac_filter_type_id", "sess_mactrack_view_macs_mac_filter_type_id", "1");
	load_current_session_value("ip_filter",          "sess_mactrack_view_macs_ip_filter", "");
	load_current_session_value("ip_filter_type_id",  "sess_mactrack_view_macs_ip_filter_type_id", "1");
	load_current_session_value("filter",             "sess_mactrack_view_macs_filter", "");
	load_current_session_value("rows",               "sess_mactrack_view_macs_rows_selector", "-1");
	load_current_session_value("site_id",            "sess_mactrack_view_macs_site_id", "-1");
	load_current_session_value("vlan",               "sess_mactrack_view_macs_vlan_id", "-1");
	load_current_session_value("authorized",         "sess_mactrack_view_macs_authorized", "-1");
	load_current_session_value("device_id",          "sess_mactrack_view_macs_device_id", "-1");
	load_current_session_value("sort_column",        "sess_mactrack_view_macs_sort_column", "device_name");
	load_current_session_value("sort_direction",     "sess_mactrack_view_macs_sort_direction", "ASC");

	mactrack_tabs();
	mactrack_view_header();
	mactrack_mac_filter();
	mactrack_view_footer();
	html_start_box("", "100%", $colors["header"], "3", "center", "");

	$sql_where = "";

	if ($_REQUEST["rows"] == -1) {
		$row_limit = read_config_option("num_rows_mactrack");
	}elseif ($_REQUEST["rows"] == -2) {
		$row_limit = 999999;
	}else{
		$row_limit = $_REQUEST["rows"];
	}

	$port_results = mactrack_view_get_mac_records($sql_where, TRUE, $row_limit);

	/* prevent table scans, either a device or site must be selected */
	if ($_REQUEST["site_id"] == -1 && $_REQUEST["device_id"] == -1) {
		$total_rows = 0;
	}elseif ($_REQUEST["rows"] == 1) {
		$rows_query_string = "SELECT
			COUNT(mac_track_ports.device_id)
			FROM mac_track_ports
			LEFT JOIN mac_track_sites ON (mac_track_ports.site_id = mac_track_sites.site_id)
			LEFT JOIN mac_track_oui_database ON (mac_track_oui_database.vendor_mac = mac_track_ports.vendor_mac)
			$sql_where";

		$total_rows = db_fetch_cell($rows_query_string);
	}else{
		$rows_query_string = "SELECT
			COUNT(DISTINCT device_id, mac_address, port_number, ip_address)
			FROM mac_track_ports
			LEFT JOIN mac_track_sites ON (mac_track_ports.site_id = mac_track_sites.site_id)
			LEFT JOIN mac_track_oui_database ON (mac_track_oui_database.vendor_mac = mac_track_ports.vendor_mac)
			$sql_where";

		$total_rows = db_fetch_cell($rows_query_string);
	}

	/* generate page list */
	$url_page_select = get_page_list($_REQUEST["page"], MAX_DISPLAY_PAGES, $row_limit, $total_rows, "mactrack_view_macs.php?report=macs");

	if (isset($config["base_path"])) {
		if ($total_rows > 0) {
			$nav = "<tr bgcolor='#" . $colors["header"] . "'>
						<td colspan='13'>
							<table width='100%' cellspacing='0' cellpadding='0' border='0'>
								<tr>
									<td align='left' class='textHeaderDark'>
										<strong>&lt;&lt; "; if ($_REQUEST["page"] > 1) { $nav .= "<a class='linkOverDark' href='mactrack_view_macs.php?report=macs&page=" . ($_REQUEST["page"]-1) . "'>"; } $nav .= "Previous"; if ($_REQUEST["page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
									</td>\n
									<td align='center' class='textHeaderDark'>
										Showing Rows " . ($total_rows == 0 ? "None" : (($row_limit*($_REQUEST["page"]-1))+1) . " to " . ((($total_rows < $row_limit) || ($total_rows < ($row_limit*$_REQUEST["page"]))) ? $total_rows : ($row_limit*$_REQUEST["page"])) . " of $total_rows [$url_page_select]") . "
									</td>\n
									<td align='right' class='textHeaderDark'>
										<strong>"; if (($_REQUEST["page"] * $row_limit) < $total_rows) { $nav .= "<a class='linkOverDark' href='mactrack_view_macs.php?report=macs&page=" . ($_REQUEST["page"]+1) . "'>"; } $nav .= "Next"; if (($_REQUEST["page"] * $row_limit) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
									</td>\n
								</tr>
							</table>
						</td>
					</tr>\n";
		}else{
			$nav = "<tr bgcolor='#" . $colors["header"] . "' class='noprint'>
						<td colspan='22'>
							<table width='100%' cellspacing='0' cellpadding='0' border='0'>
								<tr>
									<td align='center' class='textHeaderDark'>
										No Rows Found
									</td>\n
								</tr>
							</table>
						</td>
					</tr>\n";
		}
	}else{
		$nav = html_create_nav($_REQUEST["page"], MAX_DISPLAY_PAGES, $_REQUEST["rows"], $total_rows, 13, "mactrack_view_macs.php?report=macs");
	}

	print $nav;

	if (strlen(read_config_option("mt_reverse_dns")) > 0) {
		if ($_REQUEST["rows"] == 1) {
			$display_text = array(
				"nosort" => array("Actions", ""),
				"device_name" => array("Switch Name", "ASC"),
				"hostname" => array("Switch Hostname", "ASC"),
				"ip_address" => array("ED IP Address", "ASC"),
				"dns_hostname" => array("ED DNS Hostname", "ASC"),
				"mac_address" => array("ED MAC Address", "ASC"),
				"vendor_name" => array("Vendor Name", "ASC"),
				"port_number" => array("Port Number", "DESC"),
				"port_name" => array("Port Name", "ASC"),
				"vlan_id" => array("VLAN ID", "DESC"),
				"vlan_name" => array("VLAN Name", "ASC"),
				"max_scan_date" => array("Last Scan Date", "DESC"));
		}else{
			$display_text = array(
				"nosort" => array("Actions", ""),
				"device_name" => array("Switch Name", "ASC"),
				"hostname" => array("Switch Hostname", "ASC"),
				"ip_address" => array("ED IP Address", "ASC"),
				"dns_hostname" => array("ED DNS Hostname", "ASC"),
				"mac_address" => array("ED MAC Address", "ASC"),
				"vendor_name" => array("Vendor Name", "ASC"),
				"port_number" => array("Port Number", "DESC"),
				"port_name" => array("Port Name", "ASC"),
				"vlan_id" => array("VLAN ID", "DESC"),
				"vlan_name" => array("VLAN Name", "ASC"),
				"scan_date" => array("Last Scan Date", "DESC"));
		}

		if (mactrack_check_user_realm(2122)) {
			html_header_sort_checkbox($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"]);
		}else{
			html_header_sort($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"]);
		}
	}else{
		if ($_REQUEST["rows"] == 1) {
			$display_text = array(
				"nosort" => array("Actions", ""),
				"device_name" => array("Switch Name", "ASC"),
				"hostname" => array("Switch Hostname", "ASC"),
				"ip_address" => array("ED IP Address", "ASC"),
				"mac_address" => array("ED MAC Address", "ASC"),
				"vendor_name" => array("Vendor Name", "ASC"),
				"port_number" => array("Port Number", "DESC"),
				"port_name" => array("Port Name", "ASC"),
				"vlan_id" => array("VLAN ID", "DESC"),
				"vlan_name" => array("VLAN Name", "ASC"),
				"max_scan_date" => array("Last Scan Date", "DESC"));
		}else{
			$display_text = array(
				"nosort" => array("Actions", ""),
				"device_name" => array("Switch Device", "ASC"),
				"hostname" => array("Switch Hostname", "ASC"),
				"ip_address" => array("ED IP Address", "ASC"),
				"mac_address" => array("ED MAC Address", "ASC"),
				"vendor_name" => array("Vendor Name", "ASC"),
				"port_number" => array("Port Number", "DESC"),
				"port_name" => array("Port Name", "ASC"),
				"vlan_id" => array("VLAN ID", "DESC"),
				"vlan_name" => array("VLAN Name", "ASC"),
				"scan_date" => array("Last Scan Date", "DESC"));
		}

		if (mactrack_check_user_realm(2122)) {
			html_header_sort_checkbox($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"]);
		}else{
			html_header_sort($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"]);
		}
	}

	$i = 0;
	$delim = read_config_option("mt_mac_delim");
	if (sizeof($port_results) > 0) {
		foreach ($port_results as $port_result) {
			if ($_REQUEST["scan_date"] != 2) {
				$scan_date = $port_result["scan_date"];
			}else{
				$scan_date = $port_result["max_scan_date"];
			}

			$key =  str_replace($delim, "_", $port_result["mac_address"]) . "-" . $port_result["device_id"] .
					$port_result["port_number"] . "-" . strtotime($scan_date);

			form_alternate_row_color($colors["alternate"], $colors["light"], $i, 'line' . $key); $i++;
			form_selectable_cell(mactrack_interface_actions($port_result["device_id"], $port_result["port_number"]), $key);
			form_selectable_cell($port_result["device_name"], $key);
			form_selectable_cell($port_result["hostname"], $key);
			form_selectable_cell((strlen($_REQUEST["filter"]) ? preg_replace("/(" . preg_quote($_REQUEST["filter"]) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $port_result["ip_address"]) : $port_result["ip_address"]), $key);
			if (strlen(read_config_option("mt_reverse_dns")) > 0) {
			form_selectable_cell((strlen($_REQUEST["filter"]) ? preg_replace("/(" . preg_quote($_REQUEST["filter"]) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $port_result["dns_hostname"]) : $port_result["dns_hostname"]), $key);
			}
			form_selectable_cell((strlen($_REQUEST["filter"]) ? preg_replace("/(" . preg_quote($_REQUEST["filter"]) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $port_result["mac_address"]) : $port_result["mac_address"]), $key);
			form_selectable_cell((strlen($_REQUEST["filter"]) ? preg_replace("/(" . preg_quote($_REQUEST["filter"]) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $port_result["vendor_name"]) : $port_result["vendor_name"]), $key);
			form_selectable_cell($port_result["port_number"], $key);
			form_selectable_cell((strlen($_REQUEST["filter"]) ? preg_replace("/(" . preg_quote($_REQUEST["filter"]) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $port_result["port_name"]) : $port_result["port_name"]), $key);
			form_selectable_cell($port_result["vlan_id"], $key);
			form_selectable_cell((strlen($_REQUEST["filter"]) ? preg_replace("/(" . preg_quote($_REQUEST["filter"]) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $port_result["vlan_name"]) : $port_result["vlan_name"]), $key);
			form_selectable_cell($scan_date, $key);
			if (mactrack_check_user_realm(2122)) {
			form_checkbox_cell($port_result["mac_address"], $key);
			}
			form_end_row();
		}
	}else{
		if ($_REQUEST["site_id"] == -1 && $_REQUEST["device_id"] == -1) {
			print "<tr><td colspan='10'><em>You must choose a Site, Device or other search criteria</em></td></tr>";
		}else{
			print "<tr><td colspan='10'><em>No MacTrack Port Results</em></td></tr>";
		}
	}

	print $nav;

	html_end_box(false);

	if (mactrack_check_user_realm(2122)) {
		/* draw the dropdown containing a list of available actions for this form */
		mactrack_draw_actions_dropdown($mactrack_view_macs_actions);
	}
}

function mactrack_view_aggregated_macs() {
	global $title, $report, $colors, $mactrack_search_types, $rows_selector, $config;
	global $mactrack_view_agg_macs_actions, $item_rows;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("site_id"));
	input_validate_input_number(get_request_var_request("device_id"));
	input_validate_input_number(get_request_var_request("mac_filter_type_id"));
	input_validate_input_number(get_request_var_request("ip_filter_type_id"));
	input_validate_input_number(get_request_var_request("rows"));
	input_validate_input_number(get_request_var_request("authorized"));
	input_validate_input_number(get_request_var_request("vlan"));
	input_validate_input_number(get_request_var_request("page"));
	/* ==================================================== */

	/* clean up filter string */
	if (isset($_REQUEST["ip_filter"])) {
		$_REQUEST["ip_filter"] = sanitize_search_string(get_request_var("ip_filter"));
	}

	/* clean up search string */
	if (isset($_REQUEST["mac_filter"])) {
		$_REQUEST["mac_filter"] = sanitize_search_string(get_request_var("mac_filter"));
	}

	/* clean up sort_column */
	if (isset($_REQUEST["sort_column"])) {
		$_REQUEST["sort_column"] = sanitize_search_string(get_request_var("sort_column"));
	}

	/* clean up search string */
	if (isset($_REQUEST["sort_direction"])) {
		$_REQUEST["sort_direction"] = sanitize_search_string(get_request_var("sort_direction"));
	}

	if (isset($_REQUEST["mac_filter_type_id"])) {
		if ($_REQUEST["mac_filter_type_id"] == 1) {
			unset($_REQUEST["mac_filter"]);
		}
	}

	/* clean up search string */
	if (isset($_REQUEST["scan_date"])) {
		$_REQUEST["scan_date"] = sanitize_search_string(get_request_var("scan_date"));
	}

	/* clean up search string */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var("filter"));
	}

	if (isset($_REQUEST["ip_filter_type_id"])) {
		if ($_REQUEST["ip_filter_type_id"] == 1) {
			unset($_REQUEST["ip_filter"]);
		}
	}

	if (isset($_REQUEST["reset"])) {
		kill_session_var("sess_mactrack_view_macs_current_page");
		kill_session_var("sess_mactrack_view_macs_rowstoshow");
		kill_session_var("sess_mactrack_view_macs_filter");
		kill_session_var("sess_mactrack_view_macs_mac_filter_type_id");
		kill_session_var("sess_mactrack_view_macs_mac_filter");
		kill_session_var("sess_mactrack_view_macs_ip_filter_type_id");
		kill_session_var("sess_mactrack_view_macs_ip_filter");
		kill_session_var("sess_mactrack_view_macs_rows_selector");
		kill_session_var("sess_mactrack_view_macs_site_id");
		kill_session_var("sess_mactrack_view_macs_vlan_id");
		kill_session_var("sess_mactrack_view_macs_authorized");
		kill_session_var("sess_mactrack_view_macs_device_id");
		kill_session_var("sess_mactrack_view_macs_sort_column");
		kill_session_var("sess_mactrack_view_macs_sort_direction");

		$_REQUEST["page"] = 1;
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST["clear_x"])) {
		kill_session_var("sess_mactrack_view_macs_current_page");
		kill_session_var("sess_mactrack_view_macs_rowstoshow");
		kill_session_var("sess_mactrack_view_macs_filter");
		kill_session_var("sess_mactrack_view_macs_mac_filter_type_id");
		kill_session_var("sess_mactrack_view_macs_mac_filter");
		kill_session_var("sess_mactrack_view_macs_ip_filter_type_id");
		kill_session_var("sess_mactrack_view_macs_ip_filter");
		kill_session_var("sess_mactrack_view_macs_rows_selector");
		kill_session_var("sess_mactrack_view_macs_site_id");
		kill_session_var("sess_mactrack_view_macs_vlan_id");
		kill_session_var("sess_mactrack_view_macs_authorized");
		kill_session_var("sess_mactrack_view_macs_device_id");
		kill_session_var("sess_mactrack_view_macs_sort_column");
		kill_session_var("sess_mactrack_view_macs_sort_direction");

		$_REQUEST["page"] = 1;
		unset($_REQUEST["scan_date"]);
		unset($_REQUEST["mac_filter"]);
		unset($_REQUEST["mac_filter_type_id"]);
		unset($_REQUEST["ip_filter"]);
		unset($_REQUEST["ip_filter_type_id"]);
		unset($_REQUEST["rows"]);
		unset($_REQUEST["filter"]);
		unset($_REQUEST["site_id"]);
		unset($_REQUEST["vlan"]);
		unset($_REQUEST["authorized"]);
		unset($_REQUEST["device_id"]);
		unset($_REQUEST["sort_column"]);
		unset($_REQUEST["sort_direction"]);
	}else{
		/* if any of the settings changed, reset the page number */
		$changed = 0;
		$changed += mactrack_check_changed("scan_date",          "sess_mactrack_view_macs_rowstoshow");
		$changed += mactrack_check_changed("mac_filter",         "sess_mactrack_view_macs_filter");
		$changed += mactrack_check_changed("mac_filter_type_id", "sess_mactrack_view_macs_mac_filter_type_id");
		$changed += mactrack_check_changed("ip_filter",          "sess_mactrack_view_macs_mac_ip_filter");
		$changed += mactrack_check_changed("ip_filter_type_id",  "sess_mactrack_view_macs_ip_filter_type_id");
		$changed += mactrack_check_changed("filter",             "sess_mactrack_view_macs_ip_filter");
		$changed += mactrack_check_changed("rows",               "sess_mactrack_view_macs_rows_selector");
		$changed += mactrack_check_changed("site_id",            "sess_mactrack_view_macs_site_id");
		$changed += mactrack_check_changed("vlan",               "sess_mactrack_view_macs_vlan_id");
		$changed += mactrack_check_changed("authorized",         "sess_mactrack_view_macs_authorized");
		$changed += mactrack_check_changed("device_id",          "sess_mactrack_view_macs_device_id");

		if ($changed) {
			$_REQUEST["page"] = "1";
		}
	}

	/* reset some things if the user has made changes */
	if ((!empty($_REQUEST["site_id"]))&&(!empty($_SESSION["sess_mactrack_view_macs_site_id"]))) {
		if ($_REQUEST["site_id"] <> $_SESSION["sess_mactrack_view_macs_site_id"]) {
			$_REQUEST["device_id"] = "-1";
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page",               "sess_mactrack_view_macs_current_page", "1");
	load_current_session_value("scan_date",          "sess_mactrack_view_macs_rowstoshow", "2");
	load_current_session_value("mac_filter",         "sess_mactrack_view_macs_mac_filter", "");
	load_current_session_value("mac_filter_type_id", "sess_mactrack_view_macs_mac_filter_type_id", "1");
	load_current_session_value("ip_filter",          "sess_mactrack_view_macs_ip_filter", "");
	load_current_session_value("ip_filter_type_id",  "sess_mactrack_view_macs_ip_filter_type_id", "1");
	load_current_session_value("filter",             "sess_mactrack_view_macs_filter", "");
	load_current_session_value("rows",               "sess_mactrack_view_macs_rows_selector", "-1");
	load_current_session_value("site_id",            "sess_mactrack_view_macs_site_id", "-1");
	load_current_session_value("vlan",               "sess_mactrack_view_macs_vlan_id", "-1");
	load_current_session_value("authorized",         "sess_mactrack_view_macs_authorized", "-1");
	load_current_session_value("device_id",          "sess_mactrack_view_macs_device_id", "-1");
	load_current_session_value("sort_column",        "sess_mactrack_view_macs_sort_column", "device_name");
	load_current_session_value("sort_direction",     "sess_mactrack_view_macs_sort_direction", "ASC");

	mactrack_tabs();
	mactrack_view_header();
	mactrack_mac_filter();
	mactrack_view_footer();
	html_start_box("", "100%", $colors["header"], "3", "center", "");

	$sql_where = "";

	if ($_REQUEST["rows"] == -1) {
		$row_limit = read_config_option("num_rows_mactrack");
	}elseif ($_REQUEST["rows"] == -2) {
		$row_limit = 999999;
	}else{
		$row_limit = $_REQUEST["rows"];
	}

	$port_results = mactrack_view_get_mac_records($sql_where, TRUE, $row_limit);

	/* prevent table scans, either a device or site must be selected */
	if ($_REQUEST["site_id"] == -1 && $_REQUEST["device_id"] == -1) {
		$total_rows = 0;
	}else{
		$rows_query_string = "SELECT
			COUNT(*)
			FROM mac_track_aggregated_ports
			LEFT JOIN mac_track_sites
			ON (mac_track_aggregated_ports.site_id=mac_track_sites.site_id)
			LEFT JOIN mac_track_oui_database
			ON (mac_track_oui_database.vendor_mac=mac_track_aggregated_ports.vendor_mac) " .
			str_replace("mac_track_ports", "mac_track_aggregated_ports", $sql_where) . ";";

		$total_rows = db_fetch_cell($rows_query_string);
	}

	/* generate page list */
	$url_page_select = get_page_list($_REQUEST["page"], MAX_DISPLAY_PAGES, $row_limit, $total_rows, "mactrack_view_macs.php?report=macs&scan_date=3");

	if (isset($config["base_path"])) {
		if ($total_rows > 0) {
			$nav = "<tr bgcolor='#" . $colors["header"] . "'>
						<td colspan='15'>
							<table width='100%' cellspacing='0' cellpadding='0' border='0'>
								<tr>
									<td align='left' class='textHeaderDark'>
										<strong>&lt;&lt; "; if ($_REQUEST["page"] > 1) { $nav .= "<a class='linkOverDark' href='mactrack_view_macs.php?report=macs&scan_date=3&page=" . ($_REQUEST["page"]-1) . "'>"; } $nav .= "Previous"; if ($_REQUEST["page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
									</td>\n
									<td align='center' class='textHeaderDark'>
										Showing Rows " . ($total_rows == 0 ? "None" : (($row_limit*($_REQUEST["page"]-1))+1) . " to " . ((($total_rows < $row_limit) || ($total_rows < ($row_limit*$_REQUEST["page"]))) ? $total_rows : ($row_limit*$_REQUEST["page"])) . " of $total_rows [$url_page_select]") . "
									</td>\n
									<td align='right' class='textHeaderDark'>
										<strong>"; if (($_REQUEST["page"] * $row_limit) < $total_rows) { $nav .= "<a class='linkOverDark' href='mactrack_view_macs.php?report=macs&scan_date=3&page=" . ($_REQUEST["page"]+1) . "'>"; } $nav .= "Next"; if (($_REQUEST["page"] * $row_limit) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
									</td>\n
								</tr>
							</table>
						</td>
					</tr>\n";
		}else{
			$nav = "<tr bgcolor='#" . $colors["header"] . "' class='noprint'>
						<td colspan='22'>
							<table width='100%' cellspacing='0' cellpadding='0' border='0'>
								<tr>
									<td align='center' class='textHeaderDark'>
										No Rows Found
									</td>\n
								</tr>
							</table>
						</td>
					</tr>\n";
		}
	}else{
		$nav = html_create_nav($_REQUEST["page"], MAX_DISPLAY_PAGES, $_REQUEST["rows"], $total_rows, 15, "mactrack_view_macs.php?report=macs&scan_date=3");
	}

	print $nav;

	$display_text = array(
		"device_name" => array("Switch Name", "ASC"),
		"hostname" => array("Switch Hostname", "ASC"),
		"ip_address" => array("ED IP Address", "ASC"));
	if (strlen(read_config_option("mt_reverse_dns")) > 0) {
		$display_text["dns_hostname"] = array("ED DNS Hostname", "ASC");
	}
	$display_text=array_merge($display_text,array("mac_address" => array("ED MAC Address", "ASC"),
		"vendor_name" => array("Vendor Name", "ASC"),
		"port_number" => array("Port Number", "DESC"),
		"port_name" => array("Port Name", "ASC"),
		"vlan_id" => array("VLAN ID", "DESC"),
		"vlan_name" => array("VLAN Name", "ASC")));
	if ($_REQUEST["rows"] == 1) {
		$display_text["max_scan_date"] = array("Last Scan Date", "DESC");
	}else{
		$display_text["scan_date"] = array("Last Scan Date", "DESC");
	}
	if ($_REQUEST["scan_date"] == 3) {
		$display_text["count_rec"] = array("Count", "ASC");
	}

	if (mactrack_check_user_realm(2122)) {
		html_header_sort_checkbox($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"]);
	}else{
		html_header_sort($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"]);
	}


	$i = 0;
	$delim = read_config_option("mt_mac_delim");
	if (sizeof($port_results) > 0) {
		foreach ($port_results as $port_result) {

				if ($port_result["active_last"] == 1)  {
					$color_line_date="<span style='font-weight: bold;'>";
				}else{
					$color_line_date="";
				}

			$key =  str_replace($delim, "_", $port_result["mac_address"]) . "-" . $port_result["device_id"] .
					$port_result["port_number"] . "-" . $port_result["date_last"];
			$key = $port_result["row_id"];

			form_alternate_row_color($colors["alternate"], $colors["light"], $i, 'line' . $key); $i++;
			form_selectable_cell((strlen($_REQUEST["filter"]) ? preg_replace("/(" . preg_quote($_REQUEST["filter"]) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $port_result["device_name"]) : $port_result["device_name"]), $key);
			form_selectable_cell((strlen($_REQUEST["filter"]) ? preg_replace("/(" . preg_quote($_REQUEST["filter"]) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $port_result["hostname"]) : $port_result["hostname"]), $key);
			form_selectable_cell((strlen($_REQUEST["filter"]) ? preg_replace("/(" . preg_quote($_REQUEST["filter"]) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $port_result["ip_address"]) : $port_result["ip_address"]), $key);
			if (strlen(read_config_option("mt_reverse_dns")) > 0) {
				form_selectable_cell((strlen($_REQUEST["filter"]) ? preg_replace("/(" . preg_quote($_REQUEST["filter"]) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $port_result["dns_hostname"]) : $port_result["dns_hostname"]), $key);
			}
			form_selectable_cell((strlen($_REQUEST["filter"]) ? preg_replace("/(" . preg_quote($_REQUEST["filter"]) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $port_result["mac_address"]) : $port_result["mac_address"]), $key);
			form_selectable_cell((strlen($_REQUEST["filter"]) ? preg_replace("/(" . preg_quote($_REQUEST["filter"]) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $port_result["vendor_name"]) : $port_result["vendor_name"]), $key);
			form_selectable_cell($port_result["port_number"], $key);
			form_selectable_cell((strlen($_REQUEST["filter"]) ? preg_replace("/(" . preg_quote($_REQUEST["filter"]) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $port_result["port_name"]) : $port_result["port_name"]), $key);
			form_selectable_cell($port_result["vlan_id"], $key);
			form_selectable_cell((strlen($_REQUEST["filter"]) ? preg_replace("/(" . preg_quote($_REQUEST["filter"]) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $port_result["vlan_name"]) : $port_result["vlan_name"]), $key);
			form_selectable_cell($color_line_date . $port_result["date_last"], $key);
			form_selectable_cell($port_result["count_rec"], $key);

			if (mactrack_check_user_realm(2122)) {
			form_checkbox_cell($port_result["mac_address"], $key);
			}
			form_end_row();
		}
	}else{
		if ($_REQUEST["site_id"] == -1 && $_REQUEST["device_id"] == -1) {
			print "<tr><td colspan='10'><em>You must choose a Site, Device or other search criteria</em></td></tr>";
		}else{
			print "<tr><td colspan='10'><em>No MacTrack Port Results</em></td></tr>";
		}
	}

	print $nav;

	html_end_box(false);

	mactrack_display_stats();

	if (mactrack_check_user_realm(2122)) {
		/* draw the dropdown containing a list of available actions for this form */
		mactrack_draw_actions_dropdown($mactrack_view_agg_macs_actions);
	}
}

?>
