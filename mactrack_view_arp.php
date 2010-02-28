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
		mactrack_view_export_ips();
	}else{
		$_REQUEST["action"] = ""; # avoid index error in top_graph_header.php
		mactrack_redirect();
		$title = "Device Tracking - ARP/IP View";
		include_once("./include/top_graph_header.php");
		mactrack_view_ips();
		include("./include/bottom_footer.php");
	}

	break;
}

/* ------------------------
    The "actions" function
   ------------------------ */

function mactrack_view_export_ips() {
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
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var("filter"));
	}

	if (isset($_REQUEST["ip_filter_type_id"])) {
		if ($_REQUEST["ip_filter_type_id"] == 1) {
			unset($_REQUEST["ip_filter"]);
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page", "sess_mactrack_view_ipads_current_page", "1");
	load_current_session_value("filter", "sess_mactrack_view_ipads_filter", "");
	load_current_session_value("mac_filter_type_id", "sess_mactrack_view_ipads_mac_filter_type_id", "1");
	load_current_session_value("mac_filter", "sess_mactrack_view_ipads_mac_filter", "");
	load_current_session_value("ip_filter_type_id", "sess_mactrack_view_ipads_ip_filter_type_id", "1");
	load_current_session_value("ip_filter", "sess_mactrack_view_ipads_ip_filter", "");
	load_current_session_value("site_id", "sess_mactrack_view_ipads_site_id", "-1");
	load_current_session_value("device_id", "sess_mactrack_view_ipads_device_id", "-1");
	load_current_session_value("sort_column", "sess_mactrack_view_ipads_sort_column", "device_name");
	load_current_session_value("sort_direction", "sess_mactrack_view_ipads_sort_direction", "ASC");

	$sql_where = "";

	$port_results = mactrack_view_get_mac_records($sql_where, 0, FALSE);

	$xport_array = array();
	array_push($xport_array, '"site_name","hostname","device_name",' .
		'"mac_address","vendor_name",' .
		'"ip_address","dns_hostname","port_number","scan_date"');

	if (sizeof($port_results)) {
		foreach($port_results as $port_result) {
			if ($_REQUEST["scan_date"] == 1) {
				$scan_date = $port_result["scan_date"];
			}else{
				$scan_date = $port_result["max_scan_date"];
			}

			array_push($xport_array,'"' . $port_result['site_name'] . '","' .
			$port_result['hostname'] . '","' . $port_result['device_name'] . '","' .
			$port_result['mac_address'] . '","' . $port_result['vendor_name'] . '","' .
			$port_result['ip_address'] . '","' . $port_result['dns_hostname'] . '","' .
			$port_result['port_number'] . '","' . $scan_date . '"');
		}
	}

	header("Content-type: application/csv");
	header("Content-Disposition: attachment; filename=cacti_port_ipaddresses_xport.csv");
	foreach($xport_array as $xport_line) {
		print $xport_line . "\n";
	}
}

function mactrack_view_get_ip_records(&$sql_where, $apply_limits = TRUE, $row_limit = -1) {
	/* form the 'where' clause for our main sql query */
	if (strlen($_REQUEST["mac_filter"])) {
		if (strlen($sql_where) > 0) {
			$sql_where .= " AND ";
		}else{
			$sql_where = " WHERE ";
		}

		switch ($_REQUEST["mac_filter_type_id"]) {
			case "1": /* do not filter */
				break;
			case "2": /* matches */
				$sql_where .= " mac_track_ips.mac_address='" . $_REQUEST["mac_filter"] . "'";
				break;
			case "3": /* contains */
				$sql_where .= " mac_track_ips.mac_address LIKE '%%" . $_REQUEST["mac_filter"] . "%%'";
				break;
			case "4": /* begins with */
				$sql_where .= " mac_track_ips.mac_address LIKE '" . $_REQUEST["mac_filter"] . "%%'";
				break;
			case "5": /* does not contain */
				$sql_where .= " mac_track_ips.mac_address NOT LIKE '" . $_REQUEST["mac_filter"] . "%%'";
				break;
			case "6": /* does not begin with */
				$sql_where .= " mac_track_ips.mac_address NOT LIKE '" . $_REQUEST["mac_filter"] . "%%'";
		}
	}

	if ((strlen($_REQUEST["ip_filter"]) > 0)||($_REQUEST["ip_filter_type_id"] > 5)) {
		if (strlen($sql_where) > 0) {
			$sql_where .= " AND ";
		}else{
			$sql_where = " WHERE ";
		}

		switch ($_REQUEST["ip_filter_type_id"]) {
			case "1": /* do not filter */
				break;
			case "2": /* matches */
				$sql_where .= " mac_track_ips.ip_address='" . $_REQUEST["ip_filter"] . "'";
				break;
			case "3": /* contains */
				$sql_where .= " mac_track_ips.ip_address LIKE '%%" . $_REQUEST["ip_filter"] . "%%'";
				break;
			case "4": /* begins with */
				$sql_where .= " mac_track_ips.ip_address LIKE '" . $_REQUEST["ip_filter"] . "%%'";
				break;
			case "5": /* does not contain */
				$sql_where .= " mac_track_ips.ip_address NOT LIKE '" . $_REQUEST["ip_filter"] . "%%'";
				break;
			case "6": /* does not begin with */
				$sql_where .= " mac_track_ips.ip_address NOT LIKE '" . $_REQUEST["ip_filter"] . "%%'";
				break;
			case "7": /* is null */
				$sql_where .= " mac_track_ips.ip_address = ''";
				break;
			case "8": /* is not null */
				$sql_where .= " mac_track_ips.ip_address != ''";
		}
	}

	if (strlen($_REQUEST["filter"])) {
		if (strlen($sql_where) > 0) {
			$sql_where .= " AND ";
		}else{
			$sql_where = " WHERE ";
		}

		if (strlen(read_config_option("mt_reverse_dns")) > 0) {
			$sql_where .= " (mac_track_ips.dns_hostname LIKE '%" . $_REQUEST["filter"] . "%' OR " .
				"mac_track_oui_database.vendor_name LIKE '%%" . $_REQUEST["filter"] . "%%')";
		}else{
			$sql_where .= " (mac_track_oui_database.vendor_name LIKE '%%" . $_REQUEST["filter"] . "%%')";
		}
	}

	if (!($_REQUEST["site_id"] == "-1")) {
		if (strlen($sql_where) > 0) {
			$sql_where .= " AND ";
		}else{
			$sql_where = " WHERE ";
		}

		$sql_where .= " mac_track_ips.site_id=" . $_REQUEST["site_id"];
	}

	if (!($_REQUEST["device_id"] == "-1")) {
		if (strlen($sql_where) > 0) {
			$sql_where .= " AND ";
		}else{
			$sql_where = " WHERE ";
		}

		$sql_where .= " mac_track_ips.device_id=" . $_REQUEST["device_id"];
	}

	/* prevent table scans, either a device or site must be selected */
	if ($_REQUEST["site_id"] == -1 && $_REQUEST["device_id"] == -1) {
		if (!strlen($sql_where)) return array();
	}

	$query_string = "SELECT mac_track_ips.*, mac_track_sites.site_name, mac_track_oui_database.*
		FROM mac_track_ips
		LEFT JOIN mac_track_sites
		ON (mac_track_ips.site_id = mac_track_sites.site_id)
		LEFT JOIN mac_track_oui_database
		ON (mac_track_oui_database.vendor_mac=SUBSTRING(mac_track_ips.mac_address, 1, 8))
		$sql_where
		ORDER BY " . $_REQUEST["sort_column"] . " " . $_REQUEST["sort_direction"];

	if (($apply_limits) && ($row_limit != 999999)) {
		$query_string .= " LIMIT " . ($row_limit*($_REQUEST["page"]-1)) . "," . $row_limit;
	}

	//echo $query_string;

	return db_fetch_assoc($query_string);
}

function mactrack_view_ips() {
	global $title, $report, $colors, $mactrack_search_types, $rows_selector, $config;
	global $item_rows;

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
		kill_session_var("sess_mactrack_view_ipads_current_page");
		kill_session_var("sess_mactrack_view_ipads_filter");
		kill_session_var("sess_mactrack_view_ipads_mac_filter_type_id");
		kill_session_var("sess_mactrack_view_ipads_mac_filter");
		kill_session_var("sess_mactrack_view_ipads_ip_filter_type_id");
		kill_session_var("sess_mactrack_view_ipads_ip_filter");
		kill_session_var("sess_mactrack_view_ipads_rows_selector");
		kill_session_var("sess_mactrack_view_ipads_site_id");
		kill_session_var("sess_mactrack_view_ipads_device_id");
		kill_session_var("sess_mactrack_view_ipads_sort_column");
		kill_session_var("sess_mactrack_view_ipads_sort_direction");

		$_REQUEST["page"] = 1;

		if (isset($_REQUEST["clear_x"])) {
			unset($_REQUEST["mac_filter"]);
			unset($_REQUEST["mac_filter_type_id"]);
			unset($_REQUEST["ip_filter"]);
			unset($_REQUEST["ip_filter_type_id"]);
			unset($_REQUEST["rows"]);
			unset($_REQUEST["filter"]);
			unset($_REQUEST["site_id"]);
			unset($_REQUEST["device_id"]);
			unset($_REQUEST["sort_column"]);
			unset($_REQUEST["sort_direction"]);
		}
	}else{
		/* if any of the settings changed, reset the page number */
		$changed = 0;
		$changed += mactrack_check_changed("mac_filter",         "sess_mactrack_view_ipads_filter");
		$changed += mactrack_check_changed("mac_filter_type_id", "sess_mactrack_view_ipads_mac_filter_type_id");
		$changed += mactrack_check_changed("ip_filter",          "sess_mactrack_view_ipads_mac_ip_filter");
		$changed += mactrack_check_changed("ip_filter_type_id",  "sess_mactrack_view_ipads_ip_filter_type_id");
		$changed += mactrack_check_changed("filter",             "sess_mactrack_view_ipads_ip_filter");
		$changed += mactrack_check_changed("rows",               "sess_mactrack_view_ipads_rows_selector");
		$changed += mactrack_check_changed("site_id",            "sess_mactrack_view_ipads_site_id");
		$changed += mactrack_check_changed("device_id",          "sess_mactrack_view_ipads_device_id");

		if ($changed) {
			$_REQUEST["page"] = "1";
		}
	}

	/* reset some things if the user has made changes */
	if ((!empty($_REQUEST["site_id"]))&&(!empty($_SESSION["sess_mactrack_view_ipads_site_id"]))) {
		if ($_REQUEST["site_id"] <> $_SESSION["sess_mactrack_view_ipads_site_id"]) {
			$_REQUEST["device_id"] = "-1";
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page",               "sess_mactrack_view_ipads_current_page", "1");
	load_current_session_value("mac_filter",         "sess_mactrack_view_ipads_mac_filter", "");
	load_current_session_value("mac_filter_type_id", "sess_mactrack_view_ipads_mac_filter_type_id", "1");
	load_current_session_value("ip_filter",          "sess_mactrack_view_ipads_ip_filter", "");
	load_current_session_value("ip_filter_type_id",  "sess_mactrack_view_ipads_ip_filter_type_id", "1");
	load_current_session_value("filter",             "sess_mactrack_view_ipads_filter", "");
	load_current_session_value("rows",               "sess_mactrack_view_ipads_rows_selector", "-1");
	load_current_session_value("site_id",            "sess_mactrack_view_ipads_site_id", "-1");
	load_current_session_value("device_id",          "sess_mactrack_view_ipads_device_id", "-1");
	load_current_session_value("sort_column",        "sess_mactrack_view_ipads_sort_column", "device_name");
	load_current_session_value("sort_direction",     "sess_mactrack_view_ipads_sort_direction", "ASC");

	mactrack_tabs();
	mactrack_view_header();
	mactrack_ipsaddresses_filter();
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

	$port_results = mactrack_view_get_ip_records($sql_where, TRUE, $row_limit);

	/* prevent table scans, either a device or site must be selected */
	if ($_REQUEST["site_id"] == -1 && $_REQUEST["device_id"] == -1) {
		$total_rows = 0;
	}elseif ($_REQUEST["rows"] == 1) {
		$rows_query_string = "SELECT
			COUNT(mac_track_ips.device_id)
			FROM mac_track_ips
			LEFT JOIN mac_track_sites ON (mac_track_ips.site_id=mac_track_sites.site_id)
			LEFT JOIN mac_track_oui_database ON (mac_track_oui_database.vendor_mac=SUBSTRING(mac_track_ips.mac_address,1,8))
			$sql_where";

		$total_rows = db_fetch_cell($rows_query_string);
	}else{
		$rows_query_string = "SELECT
			COUNT(DISTINCT device_id, mac_address, port_number, ip_address)
			FROM mac_track_ips
			LEFT JOIN mac_track_sites ON (mac_track_ips.site_id=mac_track_sites.site_id)
			LEFT JOIN mac_track_oui_database ON (mac_track_oui_database.vendor_mac=SUBSTRING(mac_track_ips.mac_address,1,8))
			$sql_where";

		$total_rows = db_fetch_cell($rows_query_string);
	}

	/* generate page list */
	$url_page_select = get_page_list($_REQUEST["page"], MAX_DISPLAY_PAGES, $row_limit, $total_rows, "mactrack_view_arp.php?report=arp");

	if (isset($config["base_path"])) {
		if ($total_rows > 0) {
			$nav = "<tr bgcolor='#" . $colors["header"] . "'>
						<td colspan='13'>
							<table width='100%' cellspacing='0' cellpadding='0' border='0'>
								<tr>
									<td align='left' class='textHeaderDark'>
										<strong>&lt;&lt; "; if ($_REQUEST["page"] > 1) { $nav .= "<a class='linkOverDark' href='mactrack_view_arp.php?report=arp&page=" . ($_REQUEST["page"]-1) . "'>"; } $nav .= "Previous"; if ($_REQUEST["page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
									</td>\n
									<td align='center' class='textHeaderDark'>
										Showing Rows " . ($total_rows == 0 ? "None" : (($row_limit*($_REQUEST["page"]-1))+1) . " to " . ((($total_rows < $row_limit) || ($total_rows < ($row_limit*$_REQUEST["page"]))) ? $total_rows : ($row_limit*$_REQUEST["page"])) . " of $total_rows [$url_page_select]") . "
									</td>\n
									<td align='right' class='textHeaderDark'>
										<strong>"; if (($_REQUEST["page"] * $row_limit) < $total_rows) { $nav .= "<a class='linkOverDark' href='mactrack_view_arp.php?report=arp&page=" . ($_REQUEST["page"]+1) . "'>"; } $nav .= "Next"; if (($_REQUEST["page"] * $row_limit) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
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
		$nav = html_create_nav($_REQUEST["page"], MAX_DISPLAY_PAGES, $_REQUEST["rows"], $total_rows, 13, "mactrack_view_arp.php?report=arp");
	}

	print $nav;

	if (strlen(read_config_option("mt_reverse_dns")) > 0) {
		if ($_REQUEST["rows"] == 1) {
			$display_text = array(
				"device_name" => array("Switch Name", "ASC"),
				"hostname" => array("Switch Hostname", "ASC"),
				"ip_address" => array("ED IP Address", "ASC"),
				"dns_hostname" => array("ED DNS Hostname", "ASC"),
				"mac_address" => array("ED MAC Address", "ASC"),
				"vendor_name" => array("Vendor Name", "ASC"),
				"port_number" => array("Port Number", "DESC"));
		}else{
			$display_text = array(
				"device_name" => array("Switch Name", "ASC"),
				"hostname" => array("Switch Hostname", "ASC"),
				"ip_address" => array("ED IP Address", "ASC"),
				"dns_hostname" => array("ED DNS Hostname", "ASC"),
				"mac_address" => array("ED MAC Address", "ASC"),
				"vendor_name" => array("Vendor Name", "ASC"),
				"port_number" => array("Port Number", "DESC"));
		}

		if (mactrack_check_user_realm(2122)) {
			html_header_sort_checkbox($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"]);
		}else{
			html_header_sort($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"]);
		}
	}else{
		if ($_REQUEST["rows"] == 1) {
			$display_text = array(
				"device_name" => array("Switch Name", "ASC"),
				"hostname" => array("Switch Hostname", "ASC"),
				"ip_address" => array("ED IP Address", "ASC"),
				"mac_address" => array("ED MAC Address", "ASC"),
				"vendor_name" => array("Vendor Name", "ASC"),
				"port_number" => array("Port Number", "DESC"));
		}else{
			$display_text = array(
				"device_name" => array("Switch Device", "ASC"),
				"hostname" => array("Switch Hostname", "ASC"),
				"ip_address" => array("ED IP Address", "ASC"),
				"mac_address" => array("ED MAC Address", "ASC"),
				"vendor_name" => array("Vendor Name", "ASC"),
				"port_number" => array("Port Number", "DESC"));
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
			form_selectable_cell($port_result["device_name"], $key);
			form_selectable_cell($port_result["hostname"], $key);
			form_selectable_cell((strlen($_REQUEST["filter"]) ? preg_replace("/(" . preg_quote($_REQUEST["filter"]) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $port_result["ip_address"]) : $port_result["ip_address"]), $key);
			if (strlen(read_config_option("mt_reverse_dns")) > 0) {
			form_selectable_cell((strlen($_REQUEST["filter"]) ? preg_replace("/(" . preg_quote($_REQUEST["filter"]) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $port_result["dns_hostname"]) : $port_result["dns_hostname"]), $key);
			}
			form_selectable_cell((strlen($_REQUEST["filter"]) ? preg_replace("/(" . preg_quote($_REQUEST["filter"]) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $port_result["mac_address"]) : $port_result["mac_address"]), $key);
			form_selectable_cell((strlen($_REQUEST["filter"]) ? preg_replace("/(" . preg_quote($_REQUEST["filter"]) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $port_result["vendor_name"]) : $port_result["vendor_name"]), $key);
			form_selectable_cell($port_result["port_number"], $key);
			form_end_row();
		}
	}else{
		if ($_REQUEST["site_id"] == -1 && $_REQUEST["device_id"] == -1) {
			print "<tr><td colspan='10'><em>You Must Select Either a Site or a Device to Search</em></td></tr>";
		}else{
			print "<tr><td colspan='10'><em>No MacTrack IP Results</em></td></tr>";
		}
	}

	print $nav;

	html_end_box(false);

	mactrack_display_stats();
}

?>
