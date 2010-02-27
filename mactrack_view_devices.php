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

if (isset($_REQUEST["export_x"])) {
	mactrack_view_export_devices();
}else{
	$_REQUEST["action"] = ""; # avoid index error in top_graph_header.php
	mactrack_redirect();
	$title = "Device Tracking - Device Report View";
	include_once("./include/top_graph_header.php");
	mactrack_view_devices();
	include("./include/bottom_footer.php");
}

function mactrack_view_export_devices() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("site_id"));
	input_validate_input_number(get_request_var_request("device_id"));
	input_validate_input_number(get_request_var_request("type_id"));
	input_validate_input_number(get_request_var_request("device_type_id"));
	input_validate_input_number(get_request_var_request("status"));
	input_validate_input_number(get_request_var_request("page"));
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
	load_current_session_value("page", "sess_mactrack_view_device_current_page", "1");
	load_current_session_value("filter", "sess_mactrack_view_device_filter", "");
	load_current_session_value("site_id", "sess_mactrack_view_device_site_id", "-1");
	load_current_session_value("type_id", "sess_mactrack_view_device_type_id", "-1");
	load_current_session_value("device_type_id", "sess_mactrack_view_device_device_type_id", "-1");
	load_current_session_value("status", "sess_mactrack_view_device_status", "-1");
	load_current_session_value("sort_column", "sess_mactrack_view_device_sort_column", "site_name");
	load_current_session_value("sort_direction", "sess_mactrack_view_device_sort_direction", "ASC");

	$sql_where = "";

	$devices = mactrack_view_get_device_records($sql_where, 0, FALSE);

	$xport_array = array();
	array_push($xport_array, '"site_id","site_name","device_id","device_name","notes",' .
		'"hostname","snmp_readstring","snmp_readstrings","snmp_version",' .
		'"snmp_username","snmp_password","snmp_auth_protocol","snmp_priv_passphrase","snmp_priv_protocol","snmp_context"' .
		'"snmp_port","snmp_timeout","snmp_retries","max_oids","snmp_sysName","snmp_sysLocation",' .
		'"snmp_sysContact","snmp_sysObjectID","snmp_sysDescr","snmp_sysUptime",' .
		'"ignorePorts","scan_type","disabled","ports_total","ports_active",' .
		'"ports_trunk","macs_active","last_rundate","last_runduration"');

	if (sizeof($devices)) {
		foreach($devices as $device) {
			array_push($xport_array,'"'     .
			$device['site_id']              . '","' . $device['site_name']            . '","' .
			$device['device_id']            . '","' . $device['device_name']          . '","' .
			$device['notes']                . '","' . $device['hostname']             . '","' .
			$device['snmp_readstring']      . '","' . $device['snmp_readstrings']     . '","' .
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
	foreach($xport_array as $xport_line) {
		print $xport_line . "\n";
	}
}

function mactrack_view_get_device_records(&$sql_where, $row_limit, $apply_limits = TRUE) {
	$device_type_info = db_fetch_row("SELECT * FROM mac_track_device_types WHERE device_type_id = '" . $_REQUEST["device_type_id"] . "'");

	/* if the device type is not the same as the type_id, then reset it */
	if ((sizeof($device_type_info) > 0) && ($_REQUEST["type_id"] != -1)) {
		if ($device_type_info["device_type"] != $_REQUEST["type_id"]) {
			$device_type_info = array();
		}
	}else{
		if ($_REQUEST["device_type_id"] == 0) {
			$device_type_info = array("device_type_id" => 0, "description" => "Unknown Device Type");
		}
	}

	/* form the 'where' clause for our main sql query */
	$sql_where = "WHERE (mac_track_devices.hostname LIKE '%" . $_REQUEST["filter"] . "%' OR " .
					"mac_track_devices.notes LIKE '%" . $_REQUEST["filter"] . "%' OR " .
					"mac_track_devices.device_name LIKE '%" . $_REQUEST["filter"] . "%' OR " .
					"mac_track_sites.site_name LIKE '%" . $_REQUEST["filter"] . "%')";

	if (sizeof($device_type_info)) {
		$sql_where .= " AND (mac_track_devices.device_type_id=" . $device_type_info["device_type_id"] . ")";
	}

	if ($_REQUEST["status"] == "-1") {
		/* Show all items */
	}elseif ($_REQUEST["status"] == "-2") {
		$sql_where .= " AND (mac_track_devices.disabled='on')";
	}else {
		$sql_where .= " AND (mac_track_devices.snmp_status=" . $_REQUEST["status"] . ") AND (mac_track_devices.disabled = '')";
	}

	/* scan types matching */
	if ($_REQUEST["type_id"] == "-1") {
		/* Show all items */
	}else {
		$sql_where .= " AND (mac_track_devices.scan_type=" . $_REQUEST["type_id"] . ")";
	}

	/* device types matching */
	if ($_REQUEST["device_type_id"] == "-1") {
		/* Show all items */
	}elseif ($_REQUEST["device_type_id"] == "-2") {
		$sql_where .= " AND (mac_track_device_types.description='')";
	}else {
		$sql_where .= " AND (mac_track_devices.device_type_id=" . $_REQUEST["device_type_id"] . ")";
	}

	if ($_REQUEST["site_id"] == "-1") {
		/* Show all items */
	}elseif ($_REQUEST["site_id"] == "-2") {
		$sql_where .= " AND (mac_track_sites.site_id IS NULL)";
	}elseif (!empty($_REQUEST["site_id"])) {
		$sql_where .= " AND (mac_track_devices.site_id=" . $_REQUEST["site_id"] . ")";
	}

	$sql_query = "SELECT
		mac_track_device_types.description AS device_type,
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
		mac_track_devices.snmp_username,
		mac_track_devices.snmp_password,
		mac_track_devices.snmp_auth_protocol,
		mac_track_devices.snmp_priv_passphrase,
		mac_track_devices.snmp_priv_protocol,
		mac_track_devices.snmp_context,
		mac_track_devices.snmp_port,
		mac_track_devices.snmp_timeout,
		mac_track_devices.snmp_retries,
		mac_track_devices.max_oids,
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
		LEFT JOIN mac_track_device_types ON (mac_track_device_types.device_type_id=mac_track_devices.device_type_id)
		$sql_where
		ORDER BY " . $_REQUEST["sort_column"] . " " . $_REQUEST["sort_direction"];

	if ($apply_limits) {
		$sql_query .= " LIMIT " . ($row_limit*($_REQUEST["page"]-1)) . "," . $row_limit;
	}

	return db_fetch_assoc($sql_query);
}

function mactrack_view_devices() {
	global $title, $report, $colors, $mactrack_search_types, $mactrack_device_types, $rows_selector, $config, $item_rows;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("site_id"));
	input_validate_input_number(get_request_var_request("device_id"));
	input_validate_input_number(get_request_var_request("type_id"));
	input_validate_input_number(get_request_var_request("device_type_id"));
	input_validate_input_number(get_request_var_request("status"));
	input_validate_input_number(get_request_var_request("page"));
	input_validate_input_number(get_request_var_request("rows"));
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
		kill_session_var("sess_mactrack_view_device_current_page");
		kill_session_var("sess_mactrack_view_device_filter");
		kill_session_var("sess_mactrack_view_device_site_id");
		kill_session_var("sess_mactrack_view_device_type_id");
		kill_session_var("sess_mactrack_view_device_rows");
		kill_session_var("sess_mactrack_view_device_device_type_id");
		kill_session_var("sess_mactrack_view_device_status");
		kill_session_var("sess_mactrack_view_device_sort_column");
		kill_session_var("sess_mactrack_view_device_sort_direction");

		$_REQUEST["page"] = 1;
		unset($_REQUEST["filter"]);
		unset($_REQUEST["site_id"]);
		unset($_REQUEST["type_id"]);
		unset($_REQUEST["rows"]);
		unset($_REQUEST["device_type_id"]);
		unset($_REQUEST["status"]);
		unset($_REQUEST["sort_column"]);
		unset($_REQUEST["sort_direction"]);
	}else{
		/* if any of the settings changed, reset the page number */
		$changed = 0;
		$changed += mactrack_check_changed("filter", "sess_mactrack_view_device_filter");
		$changed += mactrack_check_changed("site_id", "sess_mactrack_view_device_site_id");
		$changed += mactrack_check_changed("rows", "sess_mactrack_view_device_rows");
		$changed += mactrack_check_changed("type_id", "sess_mactrack_view_device_type_id");
		$changed += mactrack_check_changed("device_type_id", "sess_mactrack_view_device_device_type_id");
		$changed += mactrack_check_changed("status", "sess_mactrack_view_device_status");

		if ($changed) {
			$_REQUEST["page"] = "1";
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page",           "sess_mactrack_view_device_current_page", "1");
	load_current_session_value("filter",         "sess_mactrack_view_device_filter", "");
	load_current_session_value("site_id",        "sess_mactrack_view_device_site_id", "-1");
	load_current_session_value("type_id",        "sess_mactrack_view_device_type_id", "-1");
	load_current_session_value("device_type_id", "sess_mactrack_view_device_device_type_id", "-1");
	load_current_session_value("status",         "sess_mactrack_view_device_status", "-1");
	load_current_session_value("rows",           "sess_mactrack_view_device_rows", "-1");
	load_current_session_value("sort_column",    "sess_mactrack_view_device_sort_column", "site_name");
	load_current_session_value("sort_direction", "sess_mactrack_view_device_sort_direction", "ASC");

	if ($_REQUEST["rows"] == -1) {
		$row_limit = read_config_option("num_rows_mactrack");
	}elseif ($_REQUEST["rows"] == -2) {
		$row_limit = 999999;
	}else{
		$row_limit = $_REQUEST["rows"];
	}

	mactrack_tabs();
	mactrack_view_header();
	mactrack_device_filter2();
	mactrack_view_footer();
	html_start_box("", "100%", $colors["header"], "3", "center", "");

	$sql_where = "";

	$devices = mactrack_view_get_device_records($sql_where, $row_limit);

	$total_rows = db_fetch_cell("SELECT
		COUNT(mac_track_devices.device_id)
		FROM mac_track_sites
		RIGHT JOIN mac_track_devices ON mac_track_devices.site_id = mac_track_sites.site_id
		LEFT JOIN mac_track_device_types ON (mac_track_device_types.device_type_id=mac_track_devices.device_type_id)
		$sql_where");

	/* generate page list */
	$url_page_select = get_page_list($_REQUEST["page"], MAX_DISPLAY_PAGES, $row_limit, $total_rows, "mactrack_view_devices.php?report=devices");

	if (isset($config["base_path"])) {
		$nav = "<tr bgcolor='#" . $colors["header"] . "'>
				<td colspan='13'>
					<table width='100%' cellspacing='0' cellpadding='0' border='0'>
						<tr>
							<td align='left' class='textHeaderDark'>
								<strong>&lt;&lt; "; if ($_REQUEST["page"] > 1) { $nav .= "<a class='linkOverDark' href='mactrack_view_devices.php?report=devices&page=" . ($_REQUEST["page"]-1) . "'>"; } $nav .= "Previous"; if ($_REQUEST["page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
							</td>\n
							<td align='center' class='textHeaderDark'>
								Showing Rows " . ($total_rows == 0 ? "None" : (($row_limit*($_REQUEST["page"]-1))+1) . " to " . ((($total_rows < $row_limit) || ($total_rows < ($row_limit*$_REQUEST["page"]))) ? $total_rows : ($row_limit*$_REQUEST["page"])) . " of $total_rows [$url_page_select]") . "
							</td>\n
							<td align='right' class='textHeaderDark'>
								<strong>"; if (($_REQUEST["page"] * $row_limit) < $total_rows) { $nav .= "<a class='linkOverDark' href='mactrack_view_devices.php?report=devices&page=" . ($_REQUEST["page"]+1) . "'>"; } $nav .= "Next"; if (($_REQUEST["page"] * $row_limit) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
							</td>\n
						</tr>
					</table>
				</td>
			</tr>\n";
	}else{
		$nav = html_create_nav($_REQUEST["page"], MAX_DISPLAY_PAGES, $row_limit, $total_rows, 13, "mactrack_view_sites.php");
	}

	print $nav;

	$display_text = array(
		"nosort" => array("Actions", ""),
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
		"vlans_total" => array("Total VLAN's", "DESC"),
		"last_runduration" => array("Last Duration", "DESC"));

	html_header_sort($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"]);

	$i = 0;
	if (sizeof($devices) > 0) {
		foreach ($devices as $device) {
			form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
				?>
				<td width=60></td>
				<td width=150>
					<?php print "<p class='linkEditMain'>" . (strlen($_REQUEST["filter"]) ? preg_replace("/(" . preg_quote($_REQUEST["filter"]) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $device["device_name"]) : $device["device_name"]) . "</p>";?>
				</td>
				<td><?php print (strlen($_REQUEST["filter"]) ? preg_replace("/(" . preg_quote($_REQUEST["filter"]) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $device["site_name"]) : $device["site_name"]);?></td>
				<td><?php print get_colored_device_status(($device["disabled"] == "on" ? true : false), $device["snmp_status"]);?></td>
				<td><?php print (strlen($_REQUEST["filter"]) ? preg_replace("/(" . preg_quote($_REQUEST["filter"]) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $device["hostname"]) : $device["hostname"]);?></td>
				<td><?php print $device["device_type"];?></td>
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
	}else{
		print "<tr><td colspan='10'><em>No MacTrack Devices</em></td></tr>";
	}

	print $nav;

	html_end_box(false);
}

?>
