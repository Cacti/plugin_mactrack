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
if (file_exists($config['base_path'] . "/include/global_arrays.php")) {
	include($config['base_path'] . "/include/global_arrays.php");
} else {
	include($config['base_path'] . "/include/config_arrays.php");
}
include_once($config['base_path'] . "/plugins/mactrack/lib/mactrack_functions.php");

define("MAX_DISPLAY_PAGES", 21);

$mactrack_view_macs_actions = array(
	1 => "Authorize",
	2 => "Revoke"
	);

load_current_session_value("report", "sess_mactrack_view_report", "macs");

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
case 'actions':
	form_actions();

	break;
default:
	if (isset($_REQUEST["export_macs_x"])) {
		mactrack_view_export_macs();
	}elseif (isset($_REQUEST["export_devices_x"])) {
		mactrack_view_export_devices();
	}elseif (isset($_REQUEST["export_sites_x"])) {
		mactrack_view_export_sites();
	}elseif (isset($_REQUEST["export_ips_x"])) {
		mactrack_view_export_ip_ranges();
	}else{
		switch ($_REQUEST["report"]) {
			case "sites":
				$title = "Device Tracking - Site Report View";
				include_once($config['base_path'] . "/plugins/mactrack/include/top_mactrack_header.php");
				mactrack_view_sites();
				include($config['base_path'] . "/include/bottom_footer.php");
				break;
			case "ips":
				$title = "Device Tracking - Site IP Range Report View";
				include_once($config['base_path'] . "/plugins/mactrack/include/top_mactrack_header.php");
				mactrack_view_ip_ranges();
				include_once($config['base_path'] . "/include/bottom_footer.php");
				break;
			case "devices":
				$title = "Device Tracking - Device Report View";
				include_once($config['base_path'] . "/plugins/mactrack/include/top_mactrack_header.php");
				mactrack_view_devices();
				include($config['base_path'] . "/include/bottom_footer.php");
				break;
			default:
				$title = "Device Tracking - MAC to IP Report View";
				include_once($config['base_path'] . "/plugins/mactrack/include/top_mactrack_header.php");
				mactrack_view_macs();
				include($config['base_path'] . "/include/bottom_footer.php");
		}
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
			for ($i=0; $i<count($selected_items); $i++) {
				/* clean up the mac_address */
				$selected_items[$i] = sanitize_search_string($selected_items[$i]);

				api_mactrack_authorize_mac_addresses($selected_items[$i]);
			}
		}elseif ($_POST["drp_action"] == "2") { /* Revoke */
			$errors = "";
			for ($i=0;($i<count($selected_items));$i++) {
				/* clean up the mac_address */
				$selected_items[$i] = sanitize_search_string($selected_items[$i]);

				$mac_found = db_fetch_cell("SELECT mac_address FROM mac_track_macauth WHERE mac_address='$selected_items[$i]'");

				if ($mac_found) {
					api_mactrack_revoke_mac_addresses($selected_items[$i], $i, $_POST["title_format"]);
				}else{
					$errors .= ", $selected_items[$i]";
				}
			}

			if ($errors) {
				$_SESSION["sess_messages"] = "The following MAC Addresses Could not be revoked because they are members of Group Authorizations" . $errors;
			}
		}

		header("Location: mactrack_view.php");
		exit;
	}

	/* setup some variables */
	$mac_address_list = ""; $i = 0;

	/* loop through each of the device types selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (substr($var,0,4) == "chk_") {
			$matches = substr($var,4);

			/* clean up the mac_address */
			if (isset($matches)) {
				$matches = sanitize_search_string($matches);
			}

			$mac_address_list .= "<li>" . $matches . "<br>";
			$mac_address_array[$i] = $matches;
		}

		$i++;
	}

	include_once("./include/top_header.php");

	html_start_box("<strong>" . $mactrack_view_macs_actions{$_POST["drp_action"]} . "</strong>", "60%", $colors["header_panel"], "3", "center", "");

	print "<form action='mactrack_view.php' method='post'>\n";

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
	}else if (!mactrack_check_user_realm(22)) {
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

function api_mactrack_authorize_mac_addresses($mac_address){
	db_execute("UPDATE mac_track_ports SET authorized='1' WHERE mac_address='$mac_address'");
	db_execute("REPLACE INTO mac_track_macauth SET mac_address='$mac_address', description='Added from MacView', added_by='" . $_SESSION["sess_user_id"] . "'");
}

function api_mactrack_revoke_mac_addresses($mac_address){
	db_execute("UPDATE mac_track_ports SET authorized='0' WHERE mac_address='$mac_address'");
	db_execute("DELETE FROM mac_track_macauth WHERE mac_address='$mac_address'");
}

function mactrack_check_changed($request, $session) {
	if ((isset($_REQUEST[$request])) && (isset($_SESSION[$session]))) {
		if ($_REQUEST[$request] != $_SESSION[$session]) {
			return 1;
		}
	}
}

function mactrack_view_export_sites() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("s_site_id"));
	input_validate_input_number(get_request_var_request("s_device_id"));
	input_validate_input_number(get_request_var_request("page"));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST["detail"])) {
		$_REQUEST["detail"] = sanitize_search_string(get_request_var("detail"));
	}

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
	load_current_session_value("s_page", "sess_mactrack_view_sites_current_page", "1");
	load_current_session_value("page", "sess_mactrack_view_sites_current_page", "1");
	load_current_session_value("detail", "sess_mactrack_view_sites_detail", "false");
	load_current_session_value("s_device_type_id", "sess_mactrack_view_sites_device_type_id", "-1");
	load_current_session_value("s_site_id", "sess_mactrack_view_sites_site_id", "-1");
	load_current_session_value("filter", "sess_mactrack_view_sites_filter", "");
	load_current_session_value("sort_column", "sess_mactrack_view_sites_sort_column", "site_name");
	load_current_session_value("sort_direction", "sess_mactrack_view_sites_sort_direction", "ASC");

	$sql_where = "";

	$sites = mactrack_view_get_site_records($sql_where, FALSE);

	$xport_array = array();

	if ($_REQUEST["detail"] == "false") {
		array_push($xport_array, '"site_id","site_name","total_devices",' .
				'"total_device_errors","total_macs","total_ips","total_oper_ports",' .
				'"total_user_ports"');

		foreach($sites as $site) {
			array_push($xport_array,'"'   .
				$site['site_id']          . '","' . $site['site_name']           . '","' .
				$site['total_devices']    . '","' . $site['total_device_errors'] . '","' .
				$site['total_macs']       . '","' . $site['total_ips']           . '","' .
				$site['total_oper_ports'] . '","' . $site['total_user_ports']    . '"');
		}
	}else{
		array_push($xport_array, '"site_name","vendor","device_name","total_devices",' .
				'"total_ips","total_user_ports","total_oper_ports","total_trunks",' .
				'"total_macs_found"');

		foreach($sites as $site) {
			array_push($xport_array,'"'   .
				$site['site_name']        . '","' . $site['vendor']          . '","' .
				$site['device_name']      . '","' . $site['total_devices']   . '","' .
				$site['sum_ips_total']    . '","' . $site['sum_ports_total'] . '","' .
				$site['sum_ports_active'] . '","' . $site['sum_ports_trunk'] . '","' .
				$site['sum_macs_active']  . '"');
		}
	}

	header("Content-type: application/csv");
	header("Content-Disposition: attachment; filename=cacti_site_xport.csv");
	foreach($xport_array as $xport_line) {
		print $xport_line . "\n";
	}
}

function mactrack_view_export_ip_ranges() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("i_site_id"));
	input_validate_input_number(get_request_var_request("page"));
	/* ==================================================== */

	/* clean up sort_column */
	if (isset($_REQUEST["sort_column"])) {
		$_REQUEST["sort_column"] = sanitize_search_string(get_request_var("sort_column"));
	}

	/* clean up search string */
	if (isset($_REQUEST["sort_direction"])) {
		$_REQUEST["sort_direction"] = sanitize_search_string(get_request_var("sort_direction"));
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("i_page", "sess_mactrack_view_ips_current_page", "1");
	load_current_session_value("page", "sess_mactrack_view_ips_current_page", "1");
	load_current_session_value("i_site_id", "sess_mactrack_view_ips_site_id", "-1");
	load_current_session_value("sort_column", "sess_mactrack_device_sort_column", "site_name");
	load_current_session_value("sort_direction", "sess_mactrack_device_sort_direction", "ASC");

	$sql_where = "";

	$ip_ranges = mactrack_view_get_ip_range_records($sql_where, FALSE);

	$xport_array = array();

	array_push($xport_array, '"site_id","site_name","ip_range",' .
			'"ips_current","ips_current_date","ips_max","ips_max_date"');

	if (is_array($ip_ranges)) {
		foreach($ip_ranges as $ip_range) {
			array_push($xport_array,'"'   .
				$ip_range['site_id']     . '","' . $ip_range['site_name']        . '","' .
				$ip_range['ip_range']    . '","' .
				$ip_range['ips_current'] . '","' . $ip_range['ips_current_date'] . '","' .
				$ip_range['ips_max']     . '","' . $ip_range['ips_max_date']     . '"');
		}
	}

	header("Content-type: application/csv");
	header("Content-Disposition: attachment; filename=cacti_ip_range_xport.csv");
	foreach($xport_array as $xport_line) {
		print $xport_line . "\n";
	}
}

function mactrack_view_export_devices() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("d_site_id"));
	input_validate_input_number(get_request_var_request("d_device_id"));
	input_validate_input_number(get_request_var_request("d_type_id"));
	input_validate_input_number(get_request_var_request("d_device_type_id"));
	input_validate_input_number(get_request_var_request("d_status"));
	input_validate_input_number(get_request_var_request("page"));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST["d_filter"])) {
		$_REQUEST["d_filter"] = sanitize_search_string(get_request_var("d_filter"));
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
	load_current_session_value("d_page", "sess_mactrack_view_device_current_page", "1");
	load_current_session_value("page", "sess_mactrack_view_device_current_page", "1");
	load_current_session_value("d_filter", "sess_mactrack_view_device_filter", "");
	load_current_session_value("d_site_id", "sess_mactrack_view_device_site_id", "-1");
	load_current_session_value("d_type_id", "sess_mactrack_view_device_type_id", "-1");
	load_current_session_value("d_device_type_id", "sess_mactrack_view_device_device_type_id", "-1");
	load_current_session_value("d_status", "sess_mactrack_view_device_status", "-1");
	load_current_session_value("sort_column", "sess_mactrack_view_device_sort_column", "site_name");
	load_current_session_value("sort_direction", "sess_mactrack_view_device_sort_direction", "ASC");

	$sql_where = "";

	$devices = mactrack_view_get_device_records($sql_where, FALSE);

	$xport_array = array();
	array_push($xport_array, '"site_id","site_name","device_id","device_name","notes",' .
		'"hostname","snmp_readstring","snmp_readstrings","snmp_version",' .
		'"snmp_port","snmp_timeout","snmp_retries","snmp_sysName","snmp_sysLocation",' .
		'"snmp_sysContact","snmp_sysObjectID","snmp_sysDescr","snmp_sysUptime",' .
		'"ignorePorts","scan_type","disabled","ports_total","ports_active",' .
		'"ports_trunk","macs_active","last_rundate","last_runduration"');

	if (sizeof($devices)) {
		foreach($devices as $device) {
			array_push($xport_array,'"' .
			$device['site_id']          . '","' . $device['site_name']        . '","' .
			$device['device_id']        . '","' . $device['device_name']      . '","' .
			$device['notes']            . '","' . $device['hostname']         . '","' .
			$device['snmp_readstring']  . '","' . $device['snmp_readstrings'] . '","' .
			$device['snmp_version']     . '","' . $device['snmp_port']        . '","' .
			$device['snmp_timeout']     . '","' . $device['snmp_retries']     . '","' .
			$device['snmp_sysName']     . '","' . $device['snmp_sysLocation'] . '","' .
			$device['snmp_sysContact']  . '","' . $device['snmp_sysObjectID'] . '","' .
			$device['snmp_sysDescr']    . '","' . $device['snmp_sysUptime']   . '","' .
			$device['ignorePorts']      . '","' . $device['scan_type']        . '","' .
			$device['disabled']         . '","' . $device['ports_total']      . '","' .
			$device['ports_active']     . '","' . $device['ports_trunk']      . '","' .
			$device['macs_active']      . '","' . $device['last_rundate']     . '","' .
			$device['last_runduration'] . '"');
		}
	}

	header("Content-type: application/csv");
	header("Content-Disposition: attachment; filename=cacti_device_xport.csv");
	foreach($xport_array as $xport_line) {
		print $xport_line . "\n";
	}
}

function mactrack_view_export_macs() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("m_site_id"));
	input_validate_input_number(get_request_var_request("m_device_id"));
	input_validate_input_number(get_request_var_request("m_mac_filter_type_id"));
	input_validate_input_number(get_request_var_request("m_ip_filter_type_id"));
	input_validate_input_number(get_request_var_request("m_rows_selector"));
	input_validate_input_number(get_request_var_request("page"));
	/* ==================================================== */

	/* clean up report string */
	if (isset($_REQUEST["report"])) {
		$_REQUEST["report"] = sanitize_search_string(get_request_var("report"));
	}

	/* clean up filter string */
	if (isset($_REQUEST["m_filter"])) {
		$_REQUEST["m_filter"] = sanitize_search_string(get_request_var("m_filter"));
	}

	/* clean up search string */
	if (isset($_REQUEST["m_mac_filter"])) {
		$_REQUEST["m_mac_filter"] = sanitize_search_string(get_request_var("m_mac_filter"));
	}

	/* clean up sort_column */
	if (isset($_REQUEST["sort_column"])) {
		$_REQUEST["sort_column"] = sanitize_search_string(get_request_var("sort_column"));
	}

	/* clean up search string */
	if (isset($_REQUEST["sort_direction"])) {
		$_REQUEST["sort_direction"] = sanitize_search_string(get_request_var("sort_direction"));
	}

	if (isset($_REQUEST["m_mac_filter_type_id"])) {
		if ($_REQUEST["m_mac_filter_type_id"] == 1) {
			unset($_REQUEST["m_mac_filter"]);
		}
	}

	/* clean up search string */
	if (isset($_REQUEST["m_rowstoshow"])) {
		$_REQUEST["m_rowstoshow"] = sanitize_search_string(get_request_var("m_rowstoshow"));
	}

	/* clean up search string */
	if (isset($_REQUEST["m_ip_filter"])) {
		$_REQUEST["m_ip_filter"] = sanitize_search_string(get_request_var("m_ip_filter"));
	}

	if (isset($_REQUEST["m_ip_filter_type_id"])) {
		if ($_REQUEST["m_ip_filter_type_id"] == 1) {
			unset($_REQUEST["m_ip_filter"]);
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("report", "sess_mactrack_view_report", "macs");
	load_current_session_value("page", "sess_mactrack_view_macs_current_page", "1");
	load_current_session_value("m_page", "sess_mactrack_view_macs_current_page", "1");
	load_current_session_value("m_rowstoshow", "sess_mactrack_view_macs_rowstoshow", "2");
	load_current_session_value("m_filter", "sess_mactrack_view_macs_filter", "");
	load_current_session_value("m_mac_filter_type_id", "sess_mactrack_view_macs_mac_filter_type_id", "1");
	load_current_session_value("m_mac_filter", "sess_mactrack_view_macs_mac_filter", "");
	load_current_session_value("m_ip_filter_type_id", "sess_mactrack_view_macs_ip_filter_type_id", "1");
	load_current_session_value("m_ip_filter", "sess_mactrack_view_macs_ip_filter", "");
	load_current_session_value("m_rows_selector", "sess_mactrack_view_macs_rows_selector", "-1");
	load_current_session_value("m_site_id", "sess_mactrack_view_macs_site_id", "-1");
	load_current_session_value("m_device_id", "sess_mactrack_view_macs_device_id", "-1");
	load_current_session_value("sort_column", "sess_mactrack_view_macs_sort_column", "device_name");
	load_current_session_value("sort_direction", "sess_mactrack_view_macs_sort_direction", "ASC");

	$sql_where = "";

	$port_results = mactrack_view_get_mac_records($sql_where, FALSE);

	$xport_array = array();
	array_push($xport_array, '"site_name","hostname","device_name",' .
		'"vlan_id","vlan_name","mac_address","vendor_name",' .
		'"ip_address","dns_hostname","port_number","port_name","scan_date"');

	if (sizeof($port_results)) {
		foreach($port_results as $port_result) {
			if ($_REQUEST["m_rowstoshow"] == 1) {
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

function mactrack_view_get_ip_range_records(&$sql_where, $apply_limits = TRUE) {
	if ($_REQUEST["i_site_id"] != "-1") {
		$sql_where = "WHERE mac_track_ip_ranges.site_id='" . $_REQUEST["i_site_id"] . "'";
	}else{
		$sql_where = "";
	}

	$ip_ranges = "SELECT
		mac_track_sites.site_id,
		mac_track_sites.site_name,
		mac_track_ip_ranges.ip_range,
		mac_track_ip_ranges.ips_max,
		mac_track_ip_ranges.ips_current,
		mac_track_ip_ranges.ips_max_date,
		mac_track_ip_ranges.ips_current_date
		FROM mac_track_ip_ranges
		INNER JOIN mac_track_sites ON (mac_track_ip_ranges.site_id=mac_track_sites.site_id)
		$sql_where
		ORDER BY " . $_REQUEST["sort_column"] . " " . $_REQUEST["sort_direction"];

	if ($apply_limits) {
		$ip_ranges .= " LIMIT " . (read_config_option("num_rows_mactrack")*($_REQUEST["i_page"]-1)) . "," . read_config_option("num_rows_mactrack");
	}

	return db_fetch_assoc($ip_ranges);
}

function mactrack_view_get_site_records(&$sql_where, $apply_limits = TRUE) {
	/* create SQL where clause */
	$device_type_info = db_fetch_row("SELECT * FROM mac_track_device_types WHERE device_type_id = '" . $_REQUEST["s_device_type_id"] . "'");

	$sql_where = "";

	/* form the 'where' clause for our main sql query */
	if (strlen($_REQUEST["filter"])) {
		if ($_REQUEST["detail"] == "false") {
			$sql_where = "WHERE (mac_track_sites.site_name LIKE '%%" . $_REQUEST["filter"] . "%%')";
		}else{
			$sql_where = "WHERE (mac_track_device_types.vendor LIKE '%%" . $_REQUEST["filter"] . "%%' OR " .
				"mac_track_device_types.description LIKE '%%" . $_REQUEST["filter"] . "%%' OR " .
				"mac_track_sites.site_name LIKE '%%" . $_REQUEST["filter"] . "%%')";
		}
	}

	if (sizeof($device_type_info)) {
		if (!strlen($sql_where)) {
			$sql_where = "WHERE (mac_track_devices.device_type_id=" . $device_type_info["device_type_id"] . ")";
		}else{
			$sql_where .= " AND (mac_track_devices.device_type_id=" . $device_type_info["device_type_id"] . ")";
		}
	}

	if (($_REQUEST["s_site_id"] != "-1") && ($_REQUEST["detail"])){
		if (!strlen($sql_where)) {
			$sql_where = "WHERE (mac_track_devices.site_id='" . $_REQUEST["s_site_id"] . "')";
		}else{
			$sql_where .= " AND (mac_track_devices.site_id='" . $_REQUEST["s_site_id"] . "')";
		}
	}

	if ($_REQUEST["detail"] == "false") {
		$query_string = "SELECT
			mac_track_sites.site_id,
			mac_track_sites.site_name,
			mac_track_sites.total_devices,
			mac_track_sites.total_device_errors,
			mac_track_sites.total_macs,
			mac_track_sites.total_ips,
			mac_track_sites.total_oper_ports,
			mac_track_sites.total_user_ports
			FROM mac_track_sites
			$sql_where
			ORDER BY " . $_REQUEST["sort_column"] . " " . $_REQUEST["sort_direction"];

		if ($apply_limits) {
			$query_string .= " LIMIT " . (read_config_option("num_rows_mactrack")*($_REQUEST["s_page"]-1)) . "," . read_config_option("num_rows_mactrack");
		}
	}else{
		$query_string ="SELECT mac_track_sites.site_name,
			Count(mac_track_device_types.device_type_id) AS total_devices,
			mac_track_device_types.device_type,
			mac_track_device_types.vendor,
			mac_track_device_types.description,
			Sum(mac_track_devices.ips_total) AS sum_ips_total,
			Sum(mac_track_devices.ports_total) AS sum_ports_total,
			Sum(mac_track_devices.ports_active) AS sum_ports_active,
			Sum(mac_track_devices.ports_trunk) AS sum_ports_trunk,
			Sum(mac_track_devices.macs_active) AS sum_macs_active
			FROM (mac_track_device_types
			RIGHT JOIN mac_track_devices ON (mac_track_device_types.device_type_id = mac_track_devices.device_type_id))
			RIGHT JOIN mac_track_sites ON (mac_track_devices.site_id = mac_track_sites.site_id)
			$sql_where
			GROUP BY mac_track_sites.site_name, mac_track_device_types.vendor, mac_track_device_types.description
			HAVING (((Count(mac_track_device_types.device_type_id))>0))
			ORDER BY " . $_REQUEST["sort_column"] . " " . $_REQUEST["sort_direction"];

		if ($apply_limits) {
			$query_string .= " LIMIT " . (read_config_option("num_rows_mactrack")*($_REQUEST["s_page"]-1)) . "," . read_config_option("num_rows_mactrack");
		}
	}

	return db_fetch_assoc($query_string);
}

function mactrack_view_get_mac_records(&$sql_where, $apply_limits = TRUE, $row_limit = -1) {
	/* form the 'where' clause for our main sql query */
	if (strlen($_REQUEST["m_mac_filter"]) > 0) {
		if (strlen($sql_where) > 0) {
			$sql_where .= " AND ";
		}else{
			$sql_where = " WHERE ";
		}

		switch ($_REQUEST["m_mac_filter_type_id"]) {
			case "1": /* do not filter */
				break;
			case "2": /* matches */
				$sql_where .= " mac_track_ports.mac_address='" . $_REQUEST["m_mac_filter"] . "'";
				break;
			case "3": /* contains */
				$sql_where .= " mac_track_ports.mac_address LIKE '%%" . $_REQUEST["m_mac_filter"] . "%%'";
				break;
			case "4": /* begins with */
				$sql_where .= " mac_track_ports.mac_address LIKE '" . $_REQUEST["m_mac_filter"] . "%%'";
				break;
			case "5": /* does not contain */
				$sql_where .= " mac_track_ports.mac_address NOT LIKE '" . $_REQUEST["m_mac_filter"] . "%%'";
				break;
			case "6": /* does not begin with */
				$sql_where .= " mac_track_ports.mac_address NOT LIKE '" . $_REQUEST["m_mac_filter"] . "%%'";
		}
	}

	if ((strlen($_REQUEST["m_ip_filter"]) > 0)||($_REQUEST["m_ip_filter_type_id"] > 5)) {
		if (strlen($sql_where) > 0) {
			$sql_where .= " AND ";
		}else{
			$sql_where = " WHERE ";
		}

		switch ($_REQUEST["m_ip_filter_type_id"]) {
			case "1": /* do not filter */
				break;
			case "2": /* matches */
				$sql_where .= " mac_track_ports.ip_address='" . $_REQUEST["m_ip_filter"] . "'";
				break;
			case "3": /* contains */
				$sql_where .= " mac_track_ports.ip_address LIKE '%%" . $_REQUEST["m_ip_filter"] . "%%'";
				break;
			case "4": /* begins with */
				$sql_where .= " mac_track_ports.ip_address LIKE '" . $_REQUEST["m_ip_filter"] . "%%'";
				break;
			case "5": /* does not contain */
				$sql_where .= " mac_track_ports.ip_address NOT LIKE '" . $_REQUEST["m_ip_filter"] . "%%'";
				break;
			case "6": /* does not begin with */
				$sql_where .= " mac_track_ports.ip_address NOT LIKE '" . $_REQUEST["m_ip_filter"] . "%%'";
				break;
			case "7": /* is null */
				$sql_where .= " mac_track_ports.ip_address = ''";
				break;
			case "8": /* is not null */
				$sql_where .= " mac_track_ports.ip_address != ''";
		}
	}

	if (strlen($_REQUEST["m_filter"])) {
		if (strlen($sql_where) > 0) {
			$sql_where .= " AND ";
		}else{
			$sql_where = " WHERE ";
		}

		if (strlen(read_config_option("mt_reverse_dns")) > 0) {
			$sql_where .= " (mac_track_ports.dns_hostname LIKE '%" . $_REQUEST["m_filter"] . "%' OR " .
				"mac_track_ports.port_name LIKE '%" . $_REQUEST["m_filter"] . "%' OR " .
				"mac_track_oui_database.vendor_name LIKE '%%" . $_REQUEST["m_filter"] . "%%' OR " .
				"mac_track_ports.vlan_name LIKE '%" . $_REQUEST["m_filter"] . "%')";
		}else{
			$sql_where .= " (mac_track_ports.port_name LIKE '%" . $_REQUEST["m_filter"] . "%' OR " .
				"mac_track_oui_database.vendor_name LIKE '%%" . $_REQUEST["m_filter"] . "%%' OR " .
				"mac_track_ports.vlan_name LIKE '%" . $_REQUEST["m_filter"] . "%')";
		}
	}

	if (!($_REQUEST["m_authorized"] == "-1")) {
		if (strlen($sql_where) > 0) {
			$sql_where .= " AND ";
		}else{
			$sql_where = " WHERE ";
		}

		$sql_where .= " mac_track_ports.authorized=" . $_REQUEST["m_authorized"];
	}

	if (!($_REQUEST["m_site_id"] == "-1")) {
		if (strlen($sql_where) > 0) {
			$sql_where .= " AND ";
		}else{
			$sql_where = " WHERE ";
		}

		$sql_where .= " mac_track_ports.site_id=" . $_REQUEST["m_site_id"];
	}

	if (!($_REQUEST["m_vlan_id"] == "-1")) {
		if (strlen($sql_where) > 0) {
			$sql_where .= " AND ";
		}else{
			$sql_where = " WHERE ";
		}

		$sql_where .= " mac_track_ports.vlan_id=" . $_REQUEST["m_vlan_id"];
	}

	if (!($_REQUEST["m_device_id"] == "-1")) {
		if (strlen($sql_where) > 0) {
			$sql_where .= " AND ";
		}else{
			$sql_where = " WHERE ";
		}

		$sql_where .= " mac_track_ports.device_id=" . $_REQUEST["m_device_id"];
	}

	if (($_REQUEST["m_rowstoshow"] != "1") && ($_REQUEST["m_rowstoshow"] != "2")) {
		if (strlen($sql_where) > 0) {
			$sql_where .= " AND ";
		}else{
			$sql_where = " WHERE ";
		}

		$sql_where .= " mac_track_ports.scan_date='" . $_REQUEST["m_rowstoshow"] . "'";
	}

	if ($_REQUEST["m_rowstoshow"] == 1) {
		$query_string = "SELECT
			site_name, device_name, hostname, mac_address, vendor_name, ip_address, dns_hostname, port_number,
			port_name, vlan_id, vlan_name, scan_date
			FROM mac_track_ports
			LEFT JOIN mac_track_sites ON (mac_track_ports.site_id = mac_track_sites.site_id)
			LEFT JOIN mac_track_oui_database ON (mac_track_oui_database.vendor_mac = mac_track_ports.vendor_mac)
			$sql_where
			ORDER BY " . $_REQUEST["sort_column"] . " " . $_REQUEST["sort_direction"];

		if (($apply_limits) && ($row_limit != 999999)) {
			$query_string .= " LIMIT " . ($row_limit*($_REQUEST["m_page"]-1)) . "," . $row_limit;
		}
	}else{
		$query_string = "SELECT
			site_name, device_name, hostname, mac_address, vendor_name, ip_address, dns_hostname, port_number,
			port_name, vlan_id, vlan_name, MAX(scan_date) as max_scan_date
			FROM mac_track_ports
			LEFT JOIN mac_track_sites ON (mac_track_ports.site_id = mac_track_sites.site_id)
			LEFT JOIN mac_track_oui_database ON (mac_track_oui_database.vendor_mac = mac_track_ports.vendor_mac)
			$sql_where
			GROUP BY device_id, mac_address, port_number, ip_address
			ORDER BY " . $_REQUEST["sort_column"] . " " . $_REQUEST["sort_direction"];

		if (($apply_limits) && ($row_limit != 999999)) {
			$query_string .= " LIMIT " . ($row_limit*($_REQUEST["m_page"]-1)) . "," . $row_limit;
		}
	}

	if (strlen($sql_where) == 0) {
		return array();
	}else{
		return db_fetch_assoc($query_string);
	}
}

function mactrack_view_get_device_records(&$sql_where, $apply_limits = TRUE) {
	$device_type_info = db_fetch_row("SELECT * FROM mac_track_device_types WHERE device_type_id = '" . $_REQUEST["d_device_type_id"] . "'");

	/* if the device type is not the same as the type_id, then reset it */
	if ((sizeof($device_type_info) > 0) && ($_REQUEST["d_type_id"] != -1)) {
		if ($device_type_info["device_type"] != $_REQUEST["d_type_id"]) {
			$device_type_info = array();
		}
	}else{
		if ($_REQUEST["d_device_type_id"] == 0) {
			$device_type_info = array("device_type_id" => 0, "description" => "Unknown Device Type");
		}
	}

	/* form the 'where' clause for our main sql query */
	$sql_where = "WHERE (mac_track_devices.hostname LIKE '%" . $_REQUEST["d_filter"] . "%' OR " .
					"mac_track_devices.notes LIKE '%" . $_REQUEST["d_filter"] . "%' OR " .
					"mac_track_devices.device_name LIKE '%" . $_REQUEST["d_filter"] . "%' OR " .
					"mac_track_sites.site_name LIKE '%" . $_REQUEST["d_filter"] . "%')";

	if (sizeof($device_type_info)) {
		$sql_where .= " AND (mac_track_devices.device_type_id=" . $device_type_info["device_type_id"] . ")";
	}

	if ($_REQUEST["d_status"] == "-1") {
		/* Show all items */
	}elseif ($_REQUEST["d_status"] == "-2") {
		$sql_where .= " AND (mac_track_devices.disabled='on')";
	}else {
		$sql_where .= " AND (mac_track_devices.snmp_status=" . $_REQUEST["d_status"] . ") AND (mac_track_devices.disabled = '')";
	}

	if ($_REQUEST["d_type_id"] == "-1") {
		/* Show all items */
	}else {
		$sql_where .= " AND (mac_track_devices.scan_type=" . $_REQUEST["d_type_id"] . ")";
	}

	if ($_REQUEST["d_site_id"] == "-1") {
		/* Show all items */
	}elseif ($_REQUEST["d_site_id"] == "-2") {
		$sql_where .= " AND (mac_track_sites.site_id IS NULL)";
	}elseif (!empty($_REQUEST["d_site_id"])) {
		$sql_where .= " AND (mac_track_devices.site_id=" . $_REQUEST["d_site_id"] . ")";
	}

	$sql_query = "SELECT
		mac_track_devices.site_id,
		mac_track_sites.site_name,
		mac_track_devices.device_id,
		mac_track_devices.device_type_id,
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
		mac_track_devices.snmp_sysName,
		mac_track_devices.snmp_sysLocation,
		mac_track_devices.snmp_sysContact,
		mac_track_devices.snmp_sysObjectID,
		mac_track_devices.snmp_sysDescr,
		mac_track_devices.snmp_sysUptime,
		mac_track_devices.ignorePorts,
		mac_track_devices.disabled,
		mac_track_devices.scan_type,
		mac_track_devices.ips_total,
		mac_track_devices.ports_total,
		mac_track_devices.vlans_total,
		mac_track_devices.ports_active,
		mac_track_devices.ports_trunk,
		mac_track_devices.macs_active,
		mac_track_devices.last_rundate,
		mac_track_devices.last_runmessage,
		mac_track_devices.last_runduration
		FROM mac_track_sites
		RIGHT JOIN mac_track_devices ON (mac_track_devices.site_id=mac_track_sites.site_id)
		$sql_where
		ORDER BY " . $_REQUEST["sort_column"] . " " . $_REQUEST["sort_direction"];

	if ($apply_limits) {
		$sql_query .= " LIMIT " . (read_config_option("num_rows_mactrack")*($_REQUEST["d_page"]-1)) . "," . read_config_option("num_rows_mactrack");
	}

	return db_fetch_assoc($sql_query);
}

function mactrack_view_header() {
	global $title, $colors;
?>
<script type="text/javascript">
<!--
function applyReportFilterChange(objForm) {
	strURL = '?report=' + objForm.report[objForm.report.selectedIndex].value;
	document.location = strURL;
}

function applySiteFilterChange(objForm) {
	strURL = '?report=sites';
	if (objForm.hidden_device_type_id) {
		strURL = strURL + '&s_device_type_id=-1';
		strURL = strURL + '&s_site_id=-1';
	}else{
		strURL = strURL + '&s_device_type_id=' + objForm.s_device_type_id[objForm.s_device_type_id.selectedIndex].value;
		strURL = strURL + '&s_site_id=' + objForm.s_site_id[objForm.s_site_id.selectedIndex].value;
	}
	strURL = strURL + '&detail=' + objForm.detail.checked;
	strURL = strURL + '&filter=' + objForm.filter.value;
	document.location = strURL;
}

function applyIPsFilterChange(objForm) {
	strURL = '?report=ips';
	strURL = strURL + '&i_site_id=' + objForm.i_site_id[objForm.i_site_id.selectedIndex].value;
	document.location = strURL;
}

function applyDeviceFilterChange(objForm) {
	strURL = '?report=devices';
	strURL = strURL + '&d_site_id=' + objForm.d_site_id[objForm.d_site_id.selectedIndex].value;
	strURL = strURL + '&d_status=' + objForm.d_status[objForm.d_status.selectedIndex].value;
	strURL = strURL + '&d_type_id=' + objForm.d_type_id[objForm.d_type_id.selectedIndex].value;
	strURL = strURL + '&d_device_type_id=' + objForm.d_device_type_id[objForm.d_device_type_id.selectedIndex].value;
	strURL = strURL + '&d_filter=' + objForm.d_filter.value;
	document.location = strURL;
}

function applyMacFilterChange(objForm) {
	strURL = '?report=macs';
	strURL = strURL + '&m_site_id=' + objForm.m_site_id[objForm.m_site_id.selectedIndex].value;
	strURL = strURL + '&m_device_id=' + objForm.m_device_id[objForm.m_device_id.selectedIndex].value;
	strURL = strURL + '&m_rowstoshow=' + objForm.m_rowstoshow[objForm.m_rowstoshow.selectedIndex].value;
	strURL = strURL + '&m_rows_selector=' + objForm.m_rows_selector[objForm.m_rows_selector.selectedIndex].value;
	strURL = strURL + '&m_mac_filter_type_id=' + objForm.m_mac_filter_type_id.value;
	strURL = strURL + '&m_mac_filter=' + objForm.m_mac_filter.value;
	strURL = strURL + '&m_authorized=' + objForm.m_authorized.value;
	strURL = strURL + '&m_filter=' + objForm.m_filter.value;
	strURL = strURL + '&m_vlan_id=' + objForm.m_vlan_id.value;
	strURL = strURL + '&m_ip_filter_type_id=' + objForm.m_ip_filter_type_id.value;
	strURL = strURL + '&m_ip_filter=' + objForm.m_ip_filter.value;
	document.location = strURL;
}
-->
</script>
<table align="center" width="100%" cellpadding=1 cellspacing=0 border=0 bgcolor="#<?php print $colors["header"];?>">
	<tr>
		<td>
			<table cellpadding=1 cellspacing=0 border=0 bgcolor="#<?php print $colors["form_background_dark"];?>" width="100%">
				<form name="form_mactrack_view_reports">
				<tr>
					<td bgcolor="#<?php print $colors["header"];?>" style="padding: 3px;" colspan="10">
						<table width="100%" cellpadding="0" cellspacing="0">
							<tr>
								<td bgcolor="#<?php print $colors["header"];?>" class="textHeaderDark"><strong><?php print $title;?></strong></td>
								<td width="1" align="right">
									<select style='font-size:11px;' name="report" onChange="applyReportFilterChange(document.form_mactrack_view_reports)">
									<option value="macs"<?php if ($_REQUEST["report"] == "macs") {?> selected<?php }?>>MAC/IP Report</option>
									<option value="sites"<?php if ($_REQUEST["report"] == "sites") {?> selected<?php }?>>Site Report</option>
									<option value="ips"<?php if ($_REQUEST["report"] == "ips") {?> selected<?php }?>>Site IP Range Report</option>
									<option value="devices"<?php if ($_REQUEST["report"] == "devices") {?> selected<?php }?>>Device Report</option>
									</select>
								</td>
							</tr>
						</table>
					</td>
				</tr>
				</form>
<?php
}

function mactrack_view_footer() {
?>
							</table>
						</td>
					</tr>
				</table>
<?php
}

function mactrack_view_ip_ranges() {
	global $title, $colors, $config;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("i_site_id"));
	input_validate_input_number(get_request_var_request("page"));
	/* ==================================================== */

	/* clean up sort_column */
	if (isset($_REQUEST["sort_column"])) {
		$_REQUEST["sort_column"] = sanitize_search_string(get_request_var("sort_column"));
	}

	/* clean up search string */
	if (isset($_REQUEST["sort_direction"])) {
		$_REQUEST["sort_direction"] = sanitize_search_string(get_request_var("sort_direction"));
	}

	/* if any of the settings changed, reset the page number */
	$changed = 0;
	$changed += mactrack_check_changed("i_site_id", "sess_mactrack_view_ips_site_id");
	if ($changed) {
		$_REQUEST["page"] = "1";
		$_REQUEST["i_page"] = $_REQUEST["page"];
	}else{
		if (isset($_REQUEST["page"])) {
			$_REQUEST["i_page"] = $_REQUEST["page"];
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("i_page", "sess_mactrack_view_ips_current_page", "1");
	load_current_session_value("page", "sess_mactrack_view_ips_current_page", "1");
	load_current_session_value("i_site_id", "sess_mactrack_view_ips_site_id", "-1");
	load_current_session_value("sort_column", "sess_mactrack_device_sort_column", "site_name");
	load_current_session_value("sort_direction", "sess_mactrack_device_sort_direction", "ASC");

	$_REQUEST["i_page"] = $_REQUEST["page"];

	mactrack_view_header();

	include($config['base_path'] . "/plugins/mactrack/html/inc_mactrack_view_ips_filter_table.php");

	mactrack_view_footer();

	html_start_box("", "100%", $colors["header"], "3", "center", "");

	$sql_where = "";

	$ip_ranges = mactrack_view_get_ip_range_records($sql_where);

	$total_rows = db_fetch_cell("SELECT
		COUNT(mac_track_ip_ranges.ip_range)
		FROM mac_track_ip_ranges
		INNER JOIN mac_track_sites ON (mac_track_ip_ranges.site_id=mac_track_sites.site_id)
		$sql_where");

	/* generate page list */
	$url_page_select = str_replace("&page", "?page", get_page_list($_REQUEST["i_page"], MAX_DISPLAY_PAGES, read_config_option("num_rows_mactrack"), $total_rows, "mactrack_view.php"));

	$nav = "<tr bgcolor='#" . $colors["header"] . "'>
			<td colspan='6'>
				<table width='100%' cellspacing='0' cellpadding='0' border='0'>
					<tr>
						<td align='left' class='textHeaderDark'>
							<strong>&lt;&lt; "; if ($_REQUEST["i_page"] > 1) { $nav .= "<a class='linkOverDark' href='mactrack_view.php?page=" . ($_REQUEST["i_page"]-1) . "'>"; } $nav .= "Previous"; if ($_REQUEST["i_page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
						</td>\n
						<td align='center' class='textHeaderDark'>
							Showing Rows " . ((read_config_option("num_rows_mactrack")*($_REQUEST["i_page"]-1))+1) . " to " . ((($total_rows < read_config_option("num_rows_mactrack")) || ($total_rows < (read_config_option("num_rows_mactrack")*$_REQUEST["i_page"]))) ? $total_rows : (read_config_option("num_rows_mactrack")*$_REQUEST["i_page"])) . " of $total_rows [$url_page_select]
						</td>\n
						<td align='right' class='textHeaderDark'>
							<strong>"; if (($_REQUEST["i_page"] * read_config_option("num_rows_mactrack")) < $total_rows) { $nav .= "<a class='linkOverDark' href='mactrack_view.php?page=" . ($_REQUEST["i_page"]+1) . "'>"; } $nav .= "Next"; if (($_REQUEST["i_page"] * read_config_option("num_rows_mactrack")) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
						</td>\n
					</tr>
				</table>
			</td>
		</tr>\n";

	print $nav;

	$display_text = array(
		"site_name" => array("<br>Site Name", "ASC"),
		"ip_range" => array("IP<br>Range", "ASC"),
		"ips_current" => array("Current<br>IP Addresses", "DESC"),
		"ips_current_date" => array("Current<br>Date", "DESC"),
		"ips_max" => array("Maximum<br>IP Addresses", "DESC"),
		"ips_max_date" => array("Maximum<br>Date", "DESC"));

	html_header_sort($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"]);

	$i = 0;
	if (sizeof($ip_ranges) > 0) {
		foreach ($ip_ranges as $ip_range) {
			form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
				?>
				<td width=200>
					<?php print "<p class='linkEditMain'><strong>" . $ip_range["site_name"] . "</strong></p>";?>
				</td>
				<td><?php print $ip_range["ip_range"];?></td>
				<td><?php print number_format($ip_range["ips_current"]);?></td>
				<td><?php print $ip_range["ips_current_date"];?></td>
				<td><?php print number_format($ip_range["ips_max"]);?></td>
				<td><?php print $ip_range["ips_max_date"];?></td>
			</tr>
			<?php
		}

		/* put the nav bar on the bottom as well */
		print $nav;
	}else{
		print "<tr><td><em>No Mac Track Site IP Ranges Found</em></td></tr>";
	}
	html_end_box(false);
}

function mactrack_view_sites() {
	global $title, $colors, $config;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("s_site_id"));
	input_validate_input_number(get_request_var_request("s_device_id"));
	input_validate_input_number(get_request_var_request("page"));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST["detail"])) {
		$_REQUEST["detail"] = sanitize_search_string(get_request_var("detail"));
	}

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
	if (isset($_REQUEST["clear_sites_x"])) {
		kill_session_var("sess_mactrack_view_sites_current_page");
		kill_session_var("sess_mactrack_view_sites_detail");
		kill_session_var("sess_mactrack_view_sites_device_type_id");
		kill_session_var("sess_mactrack_view_sites_site_id");
		kill_session_var("sess_mactrack_view_sites_filter");
		kill_session_var("sess_mactrack_view_sites_sort_column");
		kill_session_var("sess_mactrack_view_sites_sort_direction");

		$_REQUEST["page"] = 1;
		unset($_REQUEST["filter"]);
		unset($_REQUEST["s_device_type_id"]);
		unset($_REQUEST["s_site_id"]);
		unset($_REQUEST["detail"]);
		unset($_REQUEST["sort_column"]);
		unset($_REQUEST["sort_direction"]);
	}else{
		/* if any of the settings changed, reset the page number */
		$changed = 0;
		$changed += mactrack_check_changed("s_device_type_id", "sess_mactrack_view_sites_device_type_id");
		$changed += mactrack_check_changed("s_site_id", "sess_mactrack_view_sites_site_id");
		$changed += mactrack_check_changed("filter", "sess_mactrack_view_sites_filter");
		$changed += mactrack_check_changed("detail", "sess_mactrack_view_sites_detail");
		if ($changed) {
			$_REQUEST["page"] = "1";
			$_REQUEST["s_page"] = $_REQUEST["page"];
		}else{
			if (isset($_REQUEST["page"])) {
				$_REQUEST["s_page"] = $_REQUEST["page"];
			}
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("s_page", "sess_mactrack_view_sites_current_page", "1");
	load_current_session_value("page", "sess_mactrack_view_sites_current_page", "1");
	load_current_session_value("detail", "sess_mactrack_view_sites_detail", "false");
	load_current_session_value("s_device_type_id", "sess_mactrack_view_sites_device_type_id", "-1");
	load_current_session_value("s_site_id", "sess_mactrack_view_sites_site_id", "-1");
	load_current_session_value("filter", "sess_mactrack_view_sites_filter", "");
	load_current_session_value("sort_column", "sess_mactrack_view_sites_sort_column", "site_name");
	load_current_session_value("sort_direction", "sess_mactrack_view_sites_sort_direction", "ASC");

	mactrack_view_header();

	include($config['base_path'] . "/plugins/mactrack/html/inc_mactrack_view_site_filter_table.php");

	mactrack_view_footer();

	html_start_box("", "100%", $colors["header"], "3", "center", "");

	$sql_where = "";

	$sites = mactrack_view_get_site_records($sql_where);

	if ($_REQUEST["detail"] == "false") {
		$total_rows = db_fetch_cell("SELECT
			COUNT(mac_track_sites.site_id)
			FROM mac_track_sites
			$sql_where");
	}else{
		$total_rows = sizeof(db_fetch_assoc("SELECT
			mac_track_device_types.device_type_id, mac_track_sites.site_name
			FROM (mac_track_device_types
			RIGHT JOIN mac_track_devices ON (mac_track_device_types.device_type_id = mac_track_devices.device_type_id))
			RIGHT JOIN mac_track_sites ON (mac_track_devices.site_id = mac_track_sites.site_id)
			$sql_where
			GROUP BY mac_track_sites.site_name, mac_track_device_types.device_type_id"));
	}

	/* generate page list */
	$url_page_select = str_replace("&page", "?page", get_page_list($_REQUEST["s_page"], MAX_DISPLAY_PAGES, read_config_option("num_rows_mactrack"), $total_rows, "mactrack_view.php"));

	$nav = "<tr bgcolor='#" . $colors["header"] . "'>
			<td colspan='9'>
				<table width='100%' cellspacing='0' cellpadding='0' border='0'>
					<tr>
						<td align='left' class='textHeaderDark'>
							<strong>&lt;&lt; "; if ($_REQUEST["s_page"] > 1) { $nav .= "<a class='linkOverDark' href='mactrack_view.php?page=" . ($_REQUEST["s_page"]-1) . "'>"; } $nav .= "Previous"; if ($_REQUEST["s_page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
						</td>\n
						<td align='center' class='textHeaderDark'>
							Showing Rows " . ((read_config_option("num_rows_mactrack")*($_REQUEST["s_page"]-1))+1) . " to " . ((($total_rows < read_config_option("num_rows_mactrack")) || ($total_rows < (read_config_option("num_rows_mactrack")*$_REQUEST["s_page"]))) ? $total_rows : (read_config_option("num_rows_mactrack")*$_REQUEST["s_page"])) . " of $total_rows [$url_page_select]
						</td>\n
						<td align='right' class='textHeaderDark'>
							<strong>"; if (($_REQUEST["s_page"] * read_config_option("num_rows_mactrack")) < $total_rows) { $nav .= "<a class='linkOverDark' href='mactrack_view.php?page=" . ($_REQUEST["s_page"]+1) . "'>"; } $nav .= "Next"; if (($_REQUEST["s_page"] * read_config_option("num_rows_mactrack")) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
						</td>\n
					</tr>
				</table>
			</td>
		</tr>\n";

	print $nav;

	if ($_REQUEST["detail"] == "false") {
		$display_text = array(
			"site_name" => array("<br>Site Name", "ASC"),
			"total_devices" => array("<br>Devices", "DESC"),
			"total_ips" => array("Total<br>IP's", "DESC"),
			"total_user_ports" => array("User<br>Ports", "DESC"),
			"total_oper_ports" => array("User<br>Ports Up", "DESC"),
			"total_macs" => array("MACS<br>Found", "DESC"),
			"total_device_errors" => array("Device<br>Errors", "DESC"));

		html_header_sort($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"]);

		$i = 0;
		if (sizeof($sites) > 0) {
			foreach ($sites as $site) {
				form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
					?>
					<td width=200>
						<?php print "<p class='linkEditMain'><strong>" . eregi_replace("(" . preg_quote($_REQUEST["filter"]) . ")", "<span style='background-color: #F8D93D;'>\\1</span>", $site["site_name"]) . "</strong></p>";?>
					</td>
					<td><?php print number_format($site["total_devices"]);?></td>
					<td><?php print number_format($site["total_ips"]);?></td>
					<td><?php print number_format($site["total_user_ports"]);?></td>
					<td><?php print number_format($site["total_oper_ports"]);?></td>
					<td><?php print number_format($site["total_macs"]);?></td>
					<td><?php print ($site["total_device_errors"]);?></td>
				</tr>
				<?php
			}

			/* put the nav bar on the bottom as well */
			print $nav;
		}else{
			print "<tr><td><em>No Mac Track Sites</em></td></tr>";
		}
		html_end_box(false);
	}else{
		$display_text = array(
			"site_name" => array("<br>Site Name", "ASC"),
			"vendor" => array("<br>Vendor", "ASC"),
			"description" => array("<br>Device Type", "DESC"),
			"total_devices" => array("Total<br>Devices", "DESC"),
			"sum_ips_total" => array("Total<br>IP's", "DESC"),
			"sum_ports_total" => array("Total<br>User Ports", "DESC"),
			"sum_ports_active" => array("Total<br>Oper Ports", "DESC"),
			"sum_ports_trunk" => array("Total<br>Trunks", "DESC"),
			"sum_macs_active" => array("MACS<br>Found", "DESC"));

		html_header_sort($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"]);

		$i = 0;
		if (sizeof($sites) > 0) {
			foreach ($sites as $site) {
				form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
					?>
					<td width=200>
						<?php print "<p class='linkEditMain'><strong>" . eregi_replace("(" . preg_quote($_REQUEST["filter"]) . ")", "<span style='background-color: #F8D93D;'>\\1</span>", $site["site_name"]) . "</strong></p>";?>
					</td>
					<td><?php print eregi_replace("(" . preg_quote($_REQUEST["filter"]) . ")", "<span style='background-color: #F8D93D;'>\\1</span>", $site["vendor"]);?></td>
					<td><?php print eregi_replace("(" . preg_quote($_REQUEST["filter"]) . ")", "<span style='background-color: #F8D93D;'>\\1</span>", $site["description"]);?></td>
					<td><?php print number_format($site["total_devices"]);?></td>
					<td><?php print ($site["device_type"] == "1" ? "N/A" : number_format($site["sum_ips_total"]));?></td>
					<td><?php print ($site["device_type"] == "3" ? "N/A" : number_format($site["sum_ports_total"]));?></td>
					<td><?php print ($site["device_type"] == "3" ? "N/A" : number_format($site["sum_ports_active"]));?></td>
					<td><?php print ($site["device_type"] == "3" ? "N/A" : number_format($site["sum_ports_trunk"]));?></td>
					<td><?php print ($site["device_type"] == "3" ? "N/A" : number_format($site["sum_macs_active"]));?></td>
				</tr>
				<?php
			}

			/* put the nav bar on the bottom as well */
			print $nav;
		}else{
			print "<tr><td><em>No Mac Track Sites</em></td></tr>";
		}
		html_end_box(false);
	}
}

function mactrack_view_devices() {
	global $title, $report, $colors, $mactrack_search_types, $mactrack_device_types, $rows_selector, $config;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("d_site_id"));
	input_validate_input_number(get_request_var_request("d_device_id"));
	input_validate_input_number(get_request_var_request("d_type_id"));
	input_validate_input_number(get_request_var_request("d_device_type_id"));
	input_validate_input_number(get_request_var_request("d_status"));
	input_validate_input_number(get_request_var_request("page"));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST["d_filter"])) {
		$_REQUEST["d_filter"] = sanitize_search_string(get_request_var("d_filter"));
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
	if (isset($_REQUEST["clear_devices_x"])) {
		kill_session_var("sess_mactrack_view_device_current_page");
		kill_session_var("sess_mactrack_view_device_filter");
		kill_session_var("sess_mactrack_view_device_site_id");
		kill_session_var("sess_mactrack_view_device_type_id");
		kill_session_var("sess_mactrack_view_device_device_type_id");
		kill_session_var("sess_mactrack_view_device_status");
		kill_session_var("sess_mactrack_view_device_sort_column");
		kill_session_var("sess_mactrack_view_device_sort_direction");

		$_REQUEST["page"] = 1;
		unset($_REQUEST["d_filter"]);
		unset($_REQUEST["d_site_id"]);
		unset($_REQUEST["d_type_id"]);
		unset($_REQUEST["d_device_type_id"]);
		unset($_REQUEST["d_status"]);
		unset($_REQUEST["sort_column"]);
		unset($_REQUEST["sort_direction"]);
	}else{
		/* if any of the settings changed, reset the page number */
		$changed = 0;
		$changed += mactrack_check_changed("d_filter", "sess_mactrack_view_device_filter");
		$changed += mactrack_check_changed("d_site_id", "sess_mactrack_view_device_site_id");
		$changed += mactrack_check_changed("d_type_id", "sess_mactrack_view_device_type_id");
		$changed += mactrack_check_changed("d_device_type_id", "sess_mactrack_view_device_device_type_id");
		$changed += mactrack_check_changed("d_status", "sess_mactrack_view_device_status");
		if ($changed) {
			$_REQUEST["page"] = "1";
			$_REQUEST["d_page"] = $_REQUEST["page"];
		}else{
			if (isset($_REQUEST["page"])) {
				$_REQUEST["d_page"] = $_REQUEST["page"];
			}
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("d_page", "sess_mactrack_view_device_current_page", "1");
	load_current_session_value("page", "sess_mactrack_view_device_current_page", "1");
	load_current_session_value("d_filter", "sess_mactrack_view_device_filter", "");
	load_current_session_value("d_site_id", "sess_mactrack_view_device_site_id", "-1");
	load_current_session_value("d_type_id", "sess_mactrack_view_device_type_id", "-1");
	load_current_session_value("d_device_type_id", "sess_mactrack_view_device_device_type_id", "-1");
	load_current_session_value("d_status", "sess_mactrack_view_device_status", "-1");
	load_current_session_value("sort_column", "sess_mactrack_view_device_sort_column", "site_name");
	load_current_session_value("sort_direction", "sess_mactrack_view_device_sort_direction", "ASC");

	mactrack_view_header();

	include($config['base_path'] . "/plugins/mactrack/html/inc_mactrack_view_device_filter_table.php");

	mactrack_view_footer();

	$sql_where = "";

	$devices = mactrack_view_get_device_records($sql_where);

	$total_rows = db_fetch_cell("SELECT
		COUNT(mac_track_devices.device_id)
		FROM mac_track_sites
		RIGHT JOIN mac_track_devices ON mac_track_devices.site_id = mac_track_sites.site_id
		$sql_where");

	html_start_box("", "100%", $colors["header"], "3", "center", "");

	/* generate page list */
	$url_page_select = get_page_list($_REQUEST["d_page"], MAX_DISPLAY_PAGES, read_config_option("num_rows_mactrack"), $total_rows, "mactrack_view.php?report=devices&d_filter=" . $_REQUEST["d_filter"] . "&d_site_id=" . $_REQUEST["d_site_id"] . "&d_status=" . $_REQUEST["d_status"]);

	$nav = "<tr bgcolor='#" . $colors["header"] . "'>
			<td colspan='13'>
				<table width='100%' cellspacing='0' cellpadding='0' border='0'>
					<tr>
						<td align='left' class='textHeaderDark'>
							<strong>&lt;&lt; "; if ($_REQUEST["d_page"] > 1) { $nav .= "<a class='linkOverDark' href='mactrack_view.php?report=devices&d_filter=" . $_REQUEST["d_filter"] . "&d_site_id=" . $_REQUEST["d_site_id"] . "&d_status=" . $_REQUEST["d_status"] . "&page=" . ($_REQUEST["d_page"]-1) . "'>"; } $nav .= "Previous"; if ($_REQUEST["d_page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
						</td>\n
						<td align='center' class='textHeaderDark'>
							Showing Rows " . ((read_config_option("num_rows_mactrack")*($_REQUEST["d_page"]-1))+1) . " to " . ((($total_rows < read_config_option("num_rows_mactrack")) || ($total_rows < (read_config_option("num_rows_mactrack")*$_REQUEST["d_page"]))) ? $total_rows : (read_config_option("num_rows_mactrack")*$_REQUEST["d_page"])) . " of $total_rows [$url_page_select]
						</td>\n
						<td align='right' class='textHeaderDark'>
							<strong>"; if (($_REQUEST["d_page"] * read_config_option("num_rows_mactrack")) < $total_rows) { $nav .= "<a class='linkOverDark' href='mactrack_view.php?report=devices&d_filter=" . $_REQUEST["d_filter"] . "&d_site_id=" . $_REQUEST["d_site_id"] . "&d_status=" . $_REQUEST["d_status"] . "&page=" . ($_REQUEST["d_page"]+1) . "'>"; } $nav .= "Next"; if (($_REQUEST["d_page"] * read_config_option("num_rows_mactrack")) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
						</td>\n
					</tr>
				</table>
			</td>
		</tr>\n";

	print $nav;

	$display_text = array(
		"device_name" => array("Device<br>Name", "ASC"),
		"site_name" => array("Site<br>Name", "ASC"),
		"snmp_status" => array("<br>Status", "ASC"),
		"hostname" => array("<br>Hostname", "ASC"),
		"scan_type" => array("Device<br>Type", "ASC"),
		"ips_total" => array("Total<br>IP's", "DESC"),
		"ports_total" => array("User<br>Ports", "DESC"),
		"ports_active" => array("User<br>Ports Up", "DESC"),
		"ports_trunk" => array("Trunk<br>Ports", "DESC"),
		"macs_active" => array("Active<br>Macs", "DESC"),
		"vlans_total" => array("Total<br>VLAN's", "DESC"),
		"last_runduration" => array("Last<br>Duration", "DESC"));

	html_header_sort($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"]);

	$i = 0;
	if (sizeof($devices) > 0) {
		foreach ($devices as $device) {
			form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
				?>
				<td width=150>
					<?php print "<p class='linkEditMain'>" . eregi_replace("(" . preg_quote($_REQUEST["d_filter"]) . ")", "<span style='background-color: #F8D93D;'>\\1</span>", $device["device_name"]) . "</p>";?>
				</td>
				<td><?php print eregi_replace("(" . preg_quote($_REQUEST["d_filter"]) . ")", "<span style='background-color: #F8D93D;'>\\1</span>", $device["site_name"]);?></td>
				<td><?php print get_colored_device_status(($device["disabled"] == "on" ? true : false), $device["snmp_status"]);?></td>
				<td><?php print eregi_replace("(" . preg_quote($_REQUEST["d_filter"]) . ")", "<span style='background-color: #F8D93D;'>\\1</span>", $device["hostname"]);?></td>
				<td><?php print $mactrack_device_types[$device["scan_type"]];?></td>
				<td><?php print ($device["scan_type"] == "1" ? "N/A" : $device["ips_total"]);?></td>
				<td><?php print ($device["scan_type"] == "3" ? "N/A" : $device["ports_total"]);?></td>
				<td><?php print ($device["scan_type"] == "3" ? "N/A" : $device["ports_active"]);?></td>
				<td><?php print ($device["scan_type"] == "3" ? "N/A" : $device["ports_trunk"]);?></td>
				<td><?php print ($device["scan_type"] == "3" ? "N/A" : $device["macs_active"]);?></td>
				<td><?php print ($device["scan_type"] == "3" ? "N/A" : $device["vlans_total"]);?></td>
				<td><?php print number_format($device["last_runduration"], 1);?></td>
			</tr>
			<?php
		}

		/* put the nav bar on the bottom as well */
		print $nav;
	}else{
		print "<tr><td><em>No Mac Track Devices</em></td></tr>";
	}
	html_end_box(false);
}

function mactrack_get_vendor_name($mac) {
	$vendor_mac = substr($mac,0,8);

	$vendor_name = db_fetch_cell("SELECT vendor_name FROM mac_track_oui_database WHERE vendor_mac='$vendor_mac'");

	if (strlen($vendor_name)) {
		return $vendor_name;
	}else{
		return "Unknown";
	}
}

function mactrack_view_macs() {
	global $title, $report, $colors, $mactrack_search_types, $rows_selector, $config;
	global $mactrack_view_macs_actions;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("m_site_id"));
	input_validate_input_number(get_request_var_request("m_device_id"));
	input_validate_input_number(get_request_var_request("m_mac_filter_type_id"));
	input_validate_input_number(get_request_var_request("m_ip_filter_type_id"));
	input_validate_input_number(get_request_var_request("m_rows_selector"));
	input_validate_input_number(get_request_var_request("m_authorized"));
	input_validate_input_number(get_request_var_request("m_vlan_id"));
	input_validate_input_number(get_request_var_request("page"));
	/* ==================================================== */

	/* clean up report string */
	if (isset($_REQUEST["report"])) {
		$_REQUEST["report"] = sanitize_search_string(get_request_var("report"));
	}

	/* clean up filter string */
	if (isset($_REQUEST["m_filter"])) {
		$_REQUEST["m_filter"] = sanitize_search_string(get_request_var("m_filter"));
	}

	/* clean up search string */
	if (isset($_REQUEST["m_mac_filter"])) {
		$_REQUEST["m_mac_filter"] = sanitize_search_string(get_request_var("m_mac_filter"));
	}

	/* clean up sort_column */
	if (isset($_REQUEST["sort_column"])) {
		$_REQUEST["sort_column"] = sanitize_search_string(get_request_var("sort_column"));
	}

	/* clean up search string */
	if (isset($_REQUEST["sort_direction"])) {
		$_REQUEST["sort_direction"] = sanitize_search_string(get_request_var("sort_direction"));
	}

	if (isset($_REQUEST["m_mac_filter_type_id"])) {
		if ($_REQUEST["m_mac_filter_type_id"] == 1) {
			unset($_REQUEST["m_mac_filter"]);
		}
	}

	/* clean up search string */
	if (isset($_REQUEST["m_rowstoshow"])) {
		$_REQUEST["m_rowstoshow"] = sanitize_search_string(get_request_var("m_rowstoshow"));
	}

	/* clean up search string */
	if (isset($_REQUEST["m_ip_filter"])) {
		$_REQUEST["m_ip_filter"] = sanitize_search_string(get_request_var("m_ip_filter"));
	}

	if (isset($_REQUEST["m_ip_filter_type_id"])) {
		if ($_REQUEST["m_ip_filter_type_id"] == 1) {
			unset($_REQUEST["m_ip_filter"]);
		}
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST["clear_macs_x"])) {
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
		unset($_REQUEST["m_rowstoshow"]);
		unset($_REQUEST["m_filter"]);
		unset($_REQUEST["m_mac_filter_type_id"]);
		unset($_REQUEST["m_mac_filter"]);
		unset($_REQUEST["m_ip_filter_type_id"]);
		unset($_REQUEST["m_rows_selector"]);
		unset($_REQUEST["m_ip_filter"]);
		unset($_REQUEST["m_site_id"]);
		unset($_REQUEST["m_vlan_id"]);
		unset($_REQUEST["m_authorized"]);
		unset($_REQUEST["m_device_id"]);
		unset($_REQUEST["sort_column"]);
		unset($_REQUEST["sort_direction"]);
	}else{
		/* if any of the settings changed, reset the page number */
		$changed = 0;
		$changed += mactrack_check_changed("m_rowstoshow",         "sess_mactrack_view_macs_rowstoshow");
		$changed += mactrack_check_changed("m_filter",             "sess_mactrack_view_macs_filter");
		$changed += mactrack_check_changed("m_mac_filter_type_id", "sess_mactrack_view_macs_mac_filter_type_id");
		$changed += mactrack_check_changed("m_mac_filter",         "sess_mactrack_view_macs_mac_filter");
		$changed += mactrack_check_changed("m_ip_filter_type_id",  "sess_mactrack_view_macs_ip_filter_type_id");
		$changed += mactrack_check_changed("m_ip_filter",          "sess_mactrack_view_macs_ip_filter");
		$changed += mactrack_check_changed("m_rows_selector",      "sess_mactrack_view_macs_rows_selector");
		$changed += mactrack_check_changed("m_site_id",            "sess_mactrack_view_macs_site_id");
		$changed += mactrack_check_changed("m_vlan_id",            "sess_mactrack_view_macs_vlan_id");
		$changed += mactrack_check_changed("m_authorized",         "sess_mactrack_view_macs_authorized");
		$changed += mactrack_check_changed("m_device_id",          "sess_mactrack_view_macs_device_id");

		if ($changed) {
			$_REQUEST["page"] = "1";
			$_REQUEST["m_page"] = $_REQUEST["page"];
		}else{
			if (isset($_REQUEST["page"])) {
				$_REQUEST["m_page"] = $_REQUEST["page"];
			}
		}
	}

	/* reset some things if the user has made changes */
	if ((!empty($_REQUEST["m_site_id"]))&&(!empty($_SESSION["sess_mactrack_view_macs_site_id"]))) {
		if ($_REQUEST["m_site_id"] <> $_SESSION["sess_mactrack_view_macs_site_id"]) {
			$_REQUEST["m_device_id"] = "-1";
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("report", "sess_mactrack_view_report", "macs");
	load_current_session_value("page", "sess_mactrack_view_macs_current_page", "1");
	load_current_session_value("m_page", "sess_mactrack_view_macs_current_page", "1");
	load_current_session_value("m_rowstoshow", "sess_mactrack_view_macs_rowstoshow", "2");
	load_current_session_value("m_filter", "sess_mactrack_view_macs_filter", "");
	load_current_session_value("m_mac_filter_type_id", "sess_mactrack_view_macs_mac_filter_type_id", "1");
	load_current_session_value("m_mac_filter", "sess_mactrack_view_macs_mac_filter", "");
	load_current_session_value("m_ip_filter_type_id", "sess_mactrack_view_macs_ip_filter_type_id", "1");
	load_current_session_value("m_ip_filter", "sess_mactrack_view_macs_ip_filter", "");
	load_current_session_value("m_rows_selector", "sess_mactrack_view_macs_rows_selector", "-1");
	load_current_session_value("m_site_id", "sess_mactrack_view_macs_site_id", "-1");
	load_current_session_value("m_vlan_id", "sess_mactrack_view_macs_vlan_id", "-1");
	load_current_session_value("m_authorized", "sess_mactrack_view_macs_authorized", "-1");
	load_current_session_value("m_device_id", "sess_mactrack_view_macs_device_id", "-1");
	load_current_session_value("sort_column", "sess_mactrack_view_macs_sort_column", "device_name");
	load_current_session_value("sort_direction", "sess_mactrack_view_macs_sort_direction", "ASC");

	/* set m_page variable */
	$_REQUEST["m_page"] = $_REQUEST["page"];

	mactrack_view_header();

	include($config['base_path'] . "/plugins/mactrack/html/inc_mactrack_view_mac_filter_table.php");

	mactrack_view_footer();

	$sql_where = "";

	if ($_REQUEST["m_rows_selector"] == -1) {
		$row_limit = read_config_option("num_rows_mactrack");
	}elseif ($_REQUEST["m_rows_selector"] == -2) {
		$row_limit = 999999;
	}else{
		$row_limit = $_REQUEST["m_rows_selector"];
	}

	$port_results = mactrack_view_get_mac_records($sql_where, TRUE, $row_limit);

	html_start_box("", "100%", $colors["header"], "3", "center", "");

	if ($_REQUEST["m_rowstoshow"] == 1) {
		$rows_query_string = "SELECT
			COUNT(mac_track_ports.device_id)
			FROM mac_track_ports
			$sql_where";

		if (strlen($sql_where) == 0) {
			$total_rows = 0;
		}else{
			$total_rows = db_fetch_cell($rows_query_string);
		}
	}else{
		$rows_query_string = "SELECT
			COUNT(DISTINCT device_id, mac_address, port_number, ip_address)
			FROM mac_track_ports
			$sql_where";

		if (strlen($sql_where) == 0) {
			$total_rows = 0;
		}else{
			$total_rows = db_fetch_cell($rows_query_string);
		}
	}

	/* generate page list */
	$url_page_select = get_page_list($_REQUEST["m_page"], MAX_DISPLAY_PAGES, $row_limit, $total_rows, "mactrack_view.php?m_device_id=" . $_REQUEST["m_device_id"] . "&m_ip_filter_type_id=" . $_REQUEST["m_ip_filter_type_id"] . "&m_ip_filter=" . $_REQUEST["m_ip_filter"] . "&m_mac_filter_type_id=" . $_REQUEST["m_mac_filter_type_id"] . "&m_mac_filter=" . $_REQUEST["m_mac_filter"] . "&m_site_id=" . $_REQUEST["m_site_id"]);

	$nav = "<tr bgcolor='#" . $colors["header"] . "'>
				<td colspan='12'>
					<table width='100%' cellspacing='0' cellpadding='0' border='0'>
						<tr>
							<td align='left' class='textHeaderDark'>
								<strong>&lt;&lt; "; if ($_REQUEST["m_page"] > 1) { $nav .= "<a class='linkOverDark' href='mactrack_view.php?m_device_id=" . $_REQUEST["m_device_id"] . "&m_ip_filter_type_id=" . $_REQUEST["m_ip_filter_type_id"] . "&m_ip_filter=" . $_REQUEST["m_ip_filter"] . "&m_mac_filter_type_id=" . $_REQUEST["m_mac_filter_type_id"] . "&m_mac_filter=" . $_REQUEST["m_mac_filter"] . "&m_site_id=" . $_REQUEST["m_site_id"] . "&page=" . ($_REQUEST["m_page"]-1) . "'>"; } $nav .= "Previous"; if ($_REQUEST["m_page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
							</td>\n
							<td align='center' class='textHeaderDark'>
								Showing Rows " . (($row_limit*($_REQUEST["m_page"]-1))+1) . " to " . ((($total_rows < $row_limit) || ($total_rows < ($row_limit*$_REQUEST["m_page"]))) ? $total_rows : ($row_limit*$_REQUEST["m_page"])) . " of $total_rows [$url_page_select]
							</td>\n
							<td align='right' class='textHeaderDark'>
								<strong>"; if (($_REQUEST["m_page"] * $row_limit) < $total_rows) { $nav .= "<a class='linkOverDark' href='mactrack_view.php?m_device_id=" . $_REQUEST["m_device_id"] . "&m_ip_filter_type_id=" . $_REQUEST["m_ip_filter_type_id"] . "&m_ip_filter=" . $_REQUEST["m_ip_filter"] . "&m_mac_filter_type_id=" . $_REQUEST["m_mac_filter_type_id"] . "&m_mac_filter=" . $_REQUEST["m_mac_filter"] . "&m_site_id=" . $_REQUEST["m_site_id"] . "&page=" . ($_REQUEST["m_page"]+1) . "'>"; } $nav .= "Next"; if (($_REQUEST["m_page"] * $row_limit) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
							</td>\n
						</tr>
					</table>
				</td>
			</tr>\n";

	print $nav;

	if (strlen(read_config_option("mt_reverse_dns")) > 0) {
		if ($_REQUEST["m_rowstoshow"] == 1) {
			$display_text = array(
				"device_name" => array("Switch<br>Name", "ASC"),
				"hostname" => array("Switch<br>Hostname", "ASC"),
				"ip_address" => array("End Device<br>IP Address", "ASC"),
				"dns_hostname" => array("End Device<br>DNS Hostname", "ASC"),
				"mac_address" => array("End Device<br>MAC Address", "ASC"),
				"vendor_name" => array("Vendor<br>Name", "ASC"),
				"port_number" => array("Port<br>Number", "DESC"),
				"port_name" => array("Port<br>Name", "ASC"),
				"vlan_id" => array("VLAN<br>ID", "DESC"),
				"vlan_name" => array("VLAN<br>Name", "ASC"),
				"max_scan_date" => array("Last<br>Scan Date", "DESC"));
		}else{
			$display_text = array(
				"device_name" => array("Switch<br>Name", "ASC"),
				"hostname" => array("Switch<br>Hostname", "ASC"),
				"ip_address" => array("End Device<br>IP Address", "ASC"),
				"dns_hostname" => array("End Device<br>DNS Hostname", "ASC"),
				"mac_address" => array("End Device<br>MAC Address", "ASC"),
				"vendor_name" => array("Vendor<br>Name", "ASC"),
				"port_number" => array("Port<br>Number", "DESC"),
				"port_name" => array("Port<br>Name", "ASC"),
				"vlan_id" => array("VLAN<br>ID", "DESC"),
				"vlan_name" => array("VLAN<br>Name", "ASC"),
				"scan_date" => array("Last<br>Scan Date", "DESC"));
		}

		if (mactrack_check_user_realm(22)) {
			html_header_sort_checkbox($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"]);
		}else{
			html_header_sort($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"]);
		}
	}else{
		if ($_REQUEST["m_rowstoshow"] == 1) {
			$display_text = array(
				"device_name" => array("Switch<br>Name", "ASC"),
				"hostname" => array("Switch<br>Hostname", "ASC"),
				"ip_address" => array("End Device<br>IP Address", "ASC"),
				"mac_address" => array("End Device<br>MAC Address", "ASC"),
				"vendor_name" => array("Vendor<br>Name", "ASC"),
				"port_number" => array("Port<br>Number", "DESC"),
				"port_name" => array("Port<br>Name", "ASC"),
				"vlan_id" => array("VLAN<br>ID", "DESC"),
				"vlan_name" => array("VLAN<br>Name", "ASC"),
				"max_scan_date" => array("Last<br>Scan Date", "DESC"));
		}else{
			$display_text = array(
				"device_name" => array("Switch<br>Device", "ASC"),
				"hostname" => array("Switch<br>Hostname", "ASC"),
				"ip_address" => array("End Device<br>IP Address", "ASC"),
				"mac_address" => array("End Device<br>MAC Address", "ASC"),
				"vendor_name" => array("Vendor<br>Name", "ASC"),
				"port_number" => array("Port<br>Number", "DESC"),
				"port_name" => array("Port<br>Name", "ASC"),
				"vlan_id" => array("VLAN<br>ID", "DESC"),
				"vlan_name" => array("VLAN<br>Name", "ASC"),
				"scan_date" => array("Last<br>Scan Date", "DESC"));
		}

		if (mactrack_check_user_realm(22)) {
			html_header_sort_checkbox($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"]);
		}else{
			html_header_sort($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"]);
		}
	}

	$i = 0;
	if (sizeof($port_results) > 0) {
		foreach ($port_results as $port_result) {
			if ($_REQUEST["m_rowstoshow"] == 1) {
				$scan_date = $port_result["scan_date"];
			}else{
				$scan_date = $port_result["max_scan_date"];
			}

			form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
			?>
			<td><?php print $port_result["device_name"];?></td>
			<td><?php print $port_result["hostname"];?></td>
			<td><?php print eregi_replace("(" . preg_quote($_REQUEST["m_ip_filter"]) . ")", "<span style='background-color: #F8D93D;'>\\1</span>", $port_result["ip_address"]);?></td>
			<?php
			if (strlen(read_config_option("mt_reverse_dns")) > 0) {?>
			<td><?php print eregi_replace("(" . preg_quote($_REQUEST["m_filter"]) . ")", "<span style='background-color: #F8D93D;'>\\1</span>", $port_result["dns_hostname"]);?></td>
			<?php }?>
			<td><?php print eregi_replace("(" . preg_quote($_REQUEST["m_mac_filter"]) . ")", "<span style='background-color: #F8D93D;'>\\1</span>", $port_result["mac_address"]);?></td>
			<td><?php print eregi_replace("(" . preg_quote($_REQUEST["m_filter"]) . ")", "<span style='background-color: #F8D93D;'>\\1</span>", $port_result["vendor_name"]);?></td>
			<td><?php print $port_result["port_number"];?></td>
			<td><?php print eregi_replace("(" . preg_quote($_REQUEST["m_filter"]) . ")", "<span style='background-color: #F8D93D;'>\\1</span>", $port_result["port_name"]);?></td>
			<td><?php print $port_result["vlan_id"];?></td>
			<td><?php print eregi_replace("(" . preg_quote($_REQUEST["m_filter"]) . ")", "<span style='background-color: #F8D93D;'>\\1</span>", $port_result["vlan_name"]);?></td>
			<td><?php print $scan_date;?></td>
			<?php if (mactrack_check_user_realm(22)) { ?>
			<td style="<?php print get_checkbox_style();?>" width="1%" align="right">
				<input type='checkbox' style='margin: 0px;' name='chk_<?php print $port_result["mac_address"];?>' title="<?php print $port_result["mac_address"];?>">
			</td>
			<?php } ?>
			</tr>
			<?php
		}

		/* put the nav bar on the bottom as well */
		print $nav;
	}else{
		print "<tr><td><em>No Mac Track Port Results</em></td></tr>";
	}
	html_end_box(false);

	if (mactrack_check_user_realm(22)) {
		/* draw the dropdown containing a list of available actions for this form */
		mactrack_draw_actions_dropdown($mactrack_view_macs_actions);
	}
}

?>