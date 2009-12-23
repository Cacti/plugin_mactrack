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
include_once("./plugins/mactrack/lib/mactrack_functions.php");
ini_set("memory_limit", "128M");

define("MAX_DISPLAY_PAGES", 21);

$title = "MacTrack Plugin -> View Interfaces";

/* check actions */
if (isset($_REQUEST["export_x"])) {
	mactrack_export_records();
}else{
	mactrack_redirect();
	mactrack_view();
}

function mactrack_get_records(&$sql_where, $apply_limits = TRUE, $row_limit = "30") {
	global $timespan, $group_function, $summary_stats;

	/* issues sql where */
	if ($_REQUEST["issues"] == "-2") { // All Interfaces
		/* do nothing all records */
	} elseif ($_REQUEST["issues"] == "-1") { // With Issues
		$sql_where .= (strlen($sql_where) ? " AND " : "WHERE ") . "((int_errors_present=1) OR (int_discards_present=1))";
		/* still thinking */
	} elseif ($_REQUEST["issues"] == "0") { // Up Interfaces
		$sql_where .= (strlen($sql_where) ? " AND " : "WHERE ") . "(ifOperStatus=1)";
	} elseif ($_REQUEST["issues"] == "1") { // Up w/o Alias
		$sql_where .= (strlen($sql_where) ? " AND " : "WHERE ") . "(ifOperStatus=1 AND ifAlias='')";
	} elseif ($_REQUEST["issues"] == "2") { // Errors Up
		$sql_where .= (strlen($sql_where) ? " AND " : "WHERE ") . "(int_errors_present=1)";
	} elseif ($_REQUEST["issues"] == "3") { // Discards Up
		$sql_where .= (strlen($sql_where) ? " AND " : "WHERE ") . "(int_discards_present=1)";
	} elseif ($_REQUEST["issues"] == "7") { // Change < 24 Hours
		$sql_where .= (strlen($sql_where) ? " AND " : "WHERE ") . "(mac_track_interfaces.sysUptime-ifLastChange < 8640000) AND ifLastChange > 0 AND (mac_track_interfaces.sysUptime-ifLastChange > 0)";
	} elseif ($_REQUEST["issues"] == "9") { // In/Out over 70%
		$sql_where .= (strlen($sql_where) ? " AND " : "WHERE ") . "(inBound>" . read_config_option("mactrack_interface_high") . " OR outBound>" . read_config_option("mactrack_interface_high") . ")";
	} elseif ($_REQUEST["issues"] == "10") { // In over 70%
		$sql_where .= (strlen($sql_where) ? " AND " : "WHERE ") . "(inBound>" . read_config_option("mactrack_interface_high") .")";
	} elseif ($_REQUEST["issues"] == "11") { // Out over 70%
		$sql_where .= (strlen($sql_where) ? " AND " : "WHERE ") . "(outBound>" . read_config_option("mactrack_interface_high") . ")";
	} else {
	}

	/* filter sql where */
	$filter_where = mactrack_create_sql_filter($_REQUEST["filter"], array('ifAlias', 'hostname', 'ifName', 'ifDescr'));

	if (strlen($filter_where)) {
		$sql_where .= (strlen($sql_where) ? " AND " : "WHERE ") . "$filter_where";
	}

	/* device_id sql where */
	if ($_REQUEST["device"] == "-1") {
		/* do nothing all states */
	} else {
		$sql_where .= (strlen($sql_where) ? " AND " : "WHERE ") . "(mac_track_interfaces.device_id='" . $_REQUEST["device"] . "')";
	}

	/* site sql where */
	if ($_REQUEST["site"] == "-1") {
		/* do nothing all sites */
	} else {
		$sql_where .= (strlen($sql_where) ? " AND " : "WHERE ") . "(mac_track_interfaces.site_id='" . $_REQUEST["site"] . "')";
	}

	/* type sql where */
	if ($_REQUEST["type"] == "-1") {
		/* do nothing all states */
	} else {
		$sql_where .= (strlen($sql_where) ? " AND " : "WHERE ") . "(mac_track_devices.device_type_id='" . $_REQUEST["type"] . "')";
	}

	$sql_query = "SELECT mac_track_interfaces.*,
		mac_track_device_types.description AS device_type,
		mac_track_devices.device_name,
		mac_track_devices.last_rundate
		FROM mac_track_interfaces
		INNER JOIN mac_track_devices
		ON mac_track_interfaces.device_id=mac_track_devices.device_id
		INNER JOIN mac_track_device_types
		ON mac_track_device_types.device_type_id=mac_track_devices.device_type_id
		$sql_where
		ORDER BY " . $_REQUEST["sort_column"] . " " . $_REQUEST["sort_direction"];

	if ($apply_limits) {
		$sql_query .= " LIMIT " . ($row_limit*($_REQUEST["page"]-1)) . "," . $row_limit;
	}

	//echo $sql_query;

	return db_fetch_assoc($sql_query);
}

function mactrack_export_records() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("issues"));
	input_validate_input_number(get_request_var_request("device_id"));
	/* ==================================================== */

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page", "sess_mactrack_int_current_page", "1");
	load_current_session_value("rows", "sess_mactrack_int_rows", read_config_option("num_rows_device"));
	load_current_session_value("site", "sess_mactrack_int_site", "-1");
	load_current_session_value("device", "sess_mactrack_int_device", "-1");
	load_current_session_value("issues", "sess_mactrack_int_issues", "-2");
	load_current_session_value("type", "sess_mactrack_int_type", "-1");
	load_current_session_value("filter", "sess_mactrack_int_filter", "");
	load_current_session_value("sort_column", "sess_mactrack_int_sort_column", "device_name");
	load_current_session_value("sort_direction", "sess_mactrack_int_sort_direction", "DESC");

	$sql_where  = "";

	$stats = mactrack_get_records($sql_where, TRUE, 10000);

	$xport_array = array();

	array_push($xport_array, '"device_name","interface_class",' .
		'"noIssues","noGraphs",' .
		'"ignoreInterface","sysUptime",' .
		'"ifIndex","ifName",' .
		'"ifAlias","ifDescr",' .
		'"ifType","ifMtu",' .
		'"ifSpeed","ifHighSpeed",' .
		'"ifPhysAddress","ifAdminStatus",' .
		'"ifOperStatus","ifLastChange",' .
		'"ifHCInOctets","ifHCOutOctets",' .
		'"ifInDiscards","ifInErrors",' .
		'"ifInUnknownProtos","ifOutDiscards",' .
		'"ifOutErrors","last_up_time",' .
		'"last_down_time","stateChanges",');

	if (sizeof($stats)) {
	foreach($stats as $stat) {
		array_push($xport_array,'"' .
			$stat['hostname']          . '","' . $stat['interface_class']   . '","' .
			$stat['noIssues']          . '","' . $stat['noGraphs']          . '","' .
			$stat['ignoreInterface']   . '","' . $stat['sysUptime']         . '","' .
			$stat['ifIndex']           . '","' . $stat['ifName']            . '","' .
			$stat['ifAlias']           . '","' . $stat['ifDescr']           . '","' .
			$stat['ifType']            . '","' . $stat['ifMtu']             . '","' .
			$stat['ifSpeed']           . '","' . $stat['ifHighSpeed']       . '","' .
			$stat['ifPhysAddress']     . '","' . $stat['ifAdminStatus']     . '","' .
			$stat['ifOperStatus']      . '","' . $stat['ifLastChange']      . '","' .
			$stat['ifHCInOctets']      . '","' . $stat['ifHCOutOctets']     . '","' .
			$stat['ifInDiscards']      . '","' . $stat['ifInErrors']        . '","' .
			$stat['ifInUnknownProtos'] . '","' . $stat['ifOutDiscards']     . '","' .
			$stat['ifOutErrors']       . '","' . $stat['last_up_time']      . '","' .
			$stat['last_down_time']    . '","' . $stat['stateChanges']      . '"');
	}
	}

	header("Content-type: application/csv");
	header("Cache-Control: max-age=15");
	header("Content-Disposition: attachment; filename=device_mactrack_xport.csv");
	foreach($xport_array as $xport_line) {
		print $xport_line . "\n";
	}
}

function mactrack_view() {
	global $title, $colors, $mactrack_rows, $config;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("rows"));
	input_validate_input_number(get_request_var_request("page"));
	input_validate_input_number(get_request_var_request("issues"));
	input_validate_input_number(get_request_var_request("device"));
	input_validate_input_number(get_request_var_request("site"));
	input_validate_input_number(get_request_var_request("type"));
	/* ==================================================== */

	/* clean up filter */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var("filter"));
	}

	/* clean up */
	if (isset($_REQUEST["sort_column"])) {
		$_REQUEST["sort_column"] = sanitize_search_string(get_request_var("sort_column"));
	}

	/* clean up */
	if (isset($_REQUEST["sort_direction"])) {
		$_REQUEST["sort_direction"] = sanitize_search_string(get_request_var("sort_direction"));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST["clear_x"])) {
		kill_session_var("sess_mactrack_int_current_page");
		kill_session_var("sess_mactrack_int_rows");
		kill_session_var("sess_mactrack_int_device_id");
		kill_session_var("sess_mactrack_int_state");
		kill_session_var("sess_mactrack_int_site");
		kill_session_var("sess_mactrack_int_device");
		kill_session_var("sess_mactrack_int_issues");
		kill_session_var("sess_mactrack_int_type");
		kill_session_var("sess_mactrack_int_period");
		kill_session_var("sess_mactrack_int_filter");
		kill_session_var("sess_mactrack_int_sort_column");
		kill_session_var("sess_mactrack_int_sort_direction");

		$_REQUEST["page"] = 1;
		unset($_REQUEST["device_id"]);
		unset($_REQUEST["state"]);
		unset($_REQUEST["site"]);
		unset($_REQUEST["device"]);
		unset($_REQUEST["issues"]);
		unset($_REQUEST["type"]);
		unset($_REQUEST["period"]);
		unset($_REQUEST["filter"]);
		unset($_REQUEST["rows"]);
		unset($_REQUEST["sort_column"]);
		unset($_REQUEST["sort_direction"]);
	}else{
		/* if any of the settings changed, reset the page number */
		$changed = 0;
		$changed += mactrack_check_changed("device_id", "sess_mactrack_int_device_id");
		$changed += mactrack_check_changed("site", "sess_mactrack_int_site");
		$changed += mactrack_check_changed("device", "sess_mactrack_int_device");
		$changed += mactrack_check_changed("issues", "sess_mactrack_int_issues");
		$changed += mactrack_check_changed("type", "sess_mactrack_int_type");
		$changed += mactrack_check_changed("period", "sess_mactrack_int_period");
		$changed += mactrack_check_changed("filter", "sess_mactrack_int_filter");
		$changed += mactrack_check_changed("rows", "sess_mactrack_int_rows");
		$changed += mactrack_check_changed("sort_column", "sess_mactrack_int_sort_column");
		$changed += mactrack_check_changed("sort_direction", "sess_mactrack_int_sort_direction");

		if ($changed) {
			$_REQUEST["page"] = "1";
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page", "sess_mactrack_int_current_page", "1");
	load_current_session_value("rows", "sess_mactrack_int_rows", read_config_option("num_rows_device"));
	load_current_session_value("device_id", "sess_mactrack_int_device_id", "-1");
	load_current_session_value("site", "sess_mactrack_int_site", "-1");
	load_current_session_value("issues", "sess_mactrack_int_issues", "-1");
	load_current_session_value("type", "sess_mactrack_int_type", "-1");
	load_current_session_value("device", "sess_mactrack_int_device", "-1");
	load_current_session_value("period", "sess_mactrack_int_period", "-2");
	load_current_session_value("filter", "sess_mactrack_int_filter", "");
	load_current_session_value("sort_column", "sess_mactrack_int_sort_column", "device_name");
	load_current_session_value("sort_direction", "sess_mactrack_int_sort_direction", "DESC");

	include_once("./include/top_graph_header.php");
	print "<script type='text/javascript' src='" . $config["url_path"] . "plugins/mactrack/mactrack.js'></script>";

	$sql_where  = "";

	if ($_REQUEST["rows"] == -1) {
		$row_limit = read_config_option("num_rows_device");
	}elseif ($_REQUEST["rows"] == -2) {
		$row_limit = 99999999;
	}else{
		$row_limit = $_REQUEST["rows"];
	}

	$stats = mactrack_get_records($sql_where, TRUE, $row_limit);

	/* add the mactrack tabs */
	mactrack_tabs();

	html_start_box("<strong>Scanned Device Interfaces</strong>", "100%", $colors["header"], "3", "center", "");
	mactrack_filter_table();
	html_end_box();

	html_start_box("", "100%", $colors["header"], "3", "center", "");

	$rows_query_string = "SELECT COUNT(*)
		FROM mac_track_interfaces
		INNER JOIN mac_track_devices
		ON mac_track_interfaces.device_id=mac_track_devices.device_id
		INNER JOIN mac_track_device_types
		ON mac_track_device_types.device_type_id=mac_track_devices.device_type_id
		$sql_where";

	//echo $rows_query_string;

	$total_rows = db_fetch_cell($rows_query_string);

	/* generate page list */
	$url_page_select = get_page_list($_REQUEST["page"], MAX_DISPLAY_PAGES, $row_limit, $total_rows, "mactrack_view_interfaces.php?action=view");

	$nav = "<tr bgcolor='#" . $colors["header"] . "' class='noprint'>
				<td colspan='22'>
					<table width='100%' cellspacing='0' cellpadding='0' border='0'>
						<tr>
						<td align='left' class='textHeaderDark'>
								<strong>&lt;&lt; "; if ($_REQUEST["page"] > 1) { $nav .= "<a class='linkOverDark' href='mactrack_view_interfaces.php?page=" . ($_REQUEST["page"]-1) . "'>"; } $nav .= "Previous"; if ($_REQUEST["page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
							</td>\n
							<td align='center' class='textHeaderDark'>
								Showing Rows " . (($row_limit*($_REQUEST["page"]-1))+1) . " to " . ((($total_rows < $row_limit) || ($total_rows < ($row_limit*$_REQUEST["page"]))) ? $total_rows : ($row_limit*$_REQUEST["page"])) . " of $total_rows [$url_page_select]
							</td>\n
							<td align='right' class='textHeaderDark'>
								<strong>"; if (($_REQUEST["page"] * $row_limit) < $total_rows) { $nav .= "<a class='linkOverDark' href='mactrack_view_interfaces.php?page=" . ($_REQUEST["page"]+1) . "'>"; } $nav .= "Next"; if (($_REQUEST["page"] * $row_limit) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
							</td>\n
						</tr>
					</table>
				</td>
			</tr>\n";

	print $nav;

	$display_text = mactrack_display_array();

	html_header_sort($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"]);

	$i = 0;
	if (sizeof($stats) > 0) {
		foreach ($stats as $stat) {
			/* find the background color and enclose it */
			$bgc = mactrack_int_row_color($stat);
			if ($bgc) {
				print "<tr id='row_" . $stat["device_id"] . "' style='background-color:#$bgc;'>\n"; $i++;
			}else{
				if (($i % 2) == 1) {
					$current_color = $colors["alternate"];
				}else{
					$current_color = $colors["light"];
				}
				print "<tr id='row_" . $stat["device_id"] . "' style='background-color:#$bgc;'>\n"; $i++;
			}
			print mactrack_format_interface_row($stat);
		}

		/* put the nav bar on the bottom as well */
		print $nav;
	}else{
		print "<tr><td colspan='7'><em>No Scanner Devices Found</em></td></tr>";
	}
	html_end_box(false);

	html_start_box("", "100%", $colors["header"], "3", "center", "");

	print "<tr>";

	mactrack_legend_row("mactrack_int_up_bgc", "Interface Up");
	mactrack_legend_row("mactrack_int_up_wo_alias_bgc", "Interface Up w/o Alias");
	mactrack_legend_row("mactrack_int_errors_bgc", "Errors Present");
	mactrack_legend_row("mactrack_int_discards_bgc", "Discards Present");
	mactrack_legend_row("mactrack_int_unmapped_bgc", "Unmapped to Tree");
	mactrack_legend_row("mactrack_int_no_graph_bgc", "No Graphs");
	mactrack_legend_row("mactrack_int_no_device_bgc", "Not Integrated");
	mactrack_legend_row("mactrack_int_down_bgc", "Interface Down");

	print "</tr>";

	html_end_box();

	mactrack_display_stats();

	print "<div id='response'></div>";
	include_once("./include/bottom_footer.php");
}

function mactrack_display_array() {
	$display_text = array();
	$display_text += array("nosort" => array("<br>Actions", "ASC"));
	$display_text += array("hostname" => array("<br>Hostname", "ASC"));
	$display_text += array("device_type" => array("<br>Type", "ASC"));
	$display_text += array("ifName" => array("<br>Name", "ASC"));
	$display_text += array("ifDescr" => array("<br>Description", "ASC"));
	$display_text += array("ifAlias" => array("<br>Alias", "ASC"));
	$display_text += array("inBound" => array("InBound<br>%", "DESC"));
	$display_text += array("outBound" => array("OutBound<br>%", "DESC"));
	$display_text += array("int_ifHCInOctets" => array("In Bytes<br>Second", "DESC"));
	$display_text += array("int_ifHCOutOctets" => array("Out Bytes<br>Second", "DESC"));
	$display_text += array("int_ifInErrors" => array("In Err<br>Second", "DESC"));
	$display_text += array("int_ifInDiscards" => array("In Disc<br>Second", "DESC"));
	$display_text += array("int_ifInUnknownProtos" => array("UProto<br>Second", "DESC"));
	$display_text += array("int_ifOutErrors" => array("Out Err<br>Second", "DESC"));
	$display_text += array("int_ifOutDiscards" => array("Out Disc<br>Second", "DESC"));
	$display_text += array("ifOperStatus" => array("<br>Status", "ASC"));
	$display_text += array("ifLastChange" => array("Last<br>Change", "ASC"));
	$display_text += array("last_rundate" => array("Last<br>Scanned", "ASC"));

	return $display_text;
}

function mactrack_filter_table() {
	global $config, $rows_selector, $colors;

	$filterChange = "applyInterfaceFilterChange(document.view_mactrack)";
	?>
	<tr id='filter' bgcolor="<?php print $colors["panel"];?>">
		<td>
			<form name="view_mactrack" style="margin:0px;">
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td width="50">
						Site:&nbsp;
					</td>
					<td width="1">
						<select name="site" onChange="<?php print $filterChange;?>">
						<option value="-1"<?php if ($_REQUEST["site"] == "-1") {?> selected<?php }?>>All</option>
						<?php
						$sites = db_fetch_assoc("SELECT site_id, site_name FROM mac_track_sites ORDER BY site_name");
						if (sizeof($sites) > 0) {
						foreach ($sites as $site) {
							print '<option value="' . $site["site_id"] .'"'; if ($_REQUEST["site"] == $site["site_id"]) { print " selected"; } print ">" . $site["site_name"] . "</option>";
						}
						}
						?>
						</select>
					</td>
					<td width="50">
						&nbsp;Filters:&nbsp;
					</td>
					<td width="1">
						<select name="issues" onChange="<?php print $filterChange;?>">
						<option value="-2"<?php if ($_REQUEST["issues"] == "-2") {?> selected<?php }?>>All Non-Ignored Interfaces</option>
						<option value="9"<?php if ($_REQUEST["issues"] == "9") {?> selected<?php }?>>High In/Out Utilization > <?php print read_config_option("mactrack_interface_high") . "%";?></option>
						<option value="10"<?php if ($_REQUEST["issues"] == "10") {?> selected<?php }?>>High In Utilization <?php print read_config_option("mactrack_interface_high") . "%";?></option>
						<option value="11"<?php if ($_REQUEST["issues"] == "11") {?> selected<?php }?>>High Out Utilization <?php print read_config_option("mactrack_interface_high") . "%";?></option>
						<option value="-1"<?php if ($_REQUEST["issues"] == "-1") {?> selected<?php }?>>With Issues</option>
						<option value="0"<?php if ($_REQUEST["issues"] == "0") {?> selected<?php }?>>Up Interfaces</option>
						<option value="1"<?php if ($_REQUEST["issues"] == "1") {?> selected<?php }?>>Up Interfaces No Alias</option>
						<option value="2"<?php if ($_REQUEST["issues"] == "2") {?> selected<?php }?>>Errors Accumulating</option>
						<option value="3"<?php if ($_REQUEST["issues"] == "3") {?> selected<?php }?>>Discards Accumulating</option>
						<option value="7"<?php if ($_REQUEST["issues"] == "7") {?> selected<?php }?>>Changed in Last Day</option>
						</select>
					</td>
					<td width="50">
						&nbsp;Timespan:&nbsp;
					</td>
					<td width="1">
						<select name="period" onChange="<?php print $filterChange;?>">
						<option value="-2"<?php if ($_REQUEST["period"] == "-2") {?> selected<?php }?>>Last Scan</option>
						<option value="-1"<?php if ($_REQUEST["period"] == "-1") {?> selected<?php }?>>Hourly</option>
						<option value="0"<?php if ($_REQUEST["period"] == "0") {?> selected<?php }?>>Daily</option>
						<option value="1"<?php if ($_REQUEST["period"] == "1") {?> selected<?php }?>>Weekly</option>
						<option value="2"<?php if ($_REQUEST["period"] == "2") {?> selected<?php }?>>Montly</option>
						<option value="3"<?php if ($_REQUEST["period"] == "3") {?> selected<?php }?>>Yearly</option>
						</select>
					</td>
				</tr>
				<tr>
					<td width="50">
						Type:&nbsp;
					</td>
					<td width="1">
						<select name="type" onChange="<?php print $filterChange;?>">
						<option value="-1"<?php if ($_REQUEST["type"] == "-1") {?> selected<?php }?>>All</option>
						<?php
						if ($_REQUEST["site"] != -1) {
							$sql_where .= " WHERE (mac_track_devices.site_id='" . $_REQUEST["site"] . "')";
						}else{
							$sql_where  = "";
						}
						$types = db_fetch_assoc("SELECT DISTINCT mac_track_device_types.device_type_id, mac_track_device_types.description AS device_type
							FROM mac_track_device_types
							INNER JOIN mac_track_devices
							ON mac_track_device_types.device_type_id=mac_track_devices.device_type_id
							$sql_where
							ORDER BY device_type");
						if (sizeof($types) > 0) {
						foreach ($types as $type) {
							print '<option value="' . $type["device_type"] .'"'; if ($_REQUEST["type"] == $type["device_type"]) { print " selected"; } print ">" . $type["device_type"] . "</option>";
						}
						}
						?>
						</select>
					</td>
					<td width="50">
						&nbsp;Device:&nbsp;
					</td>
					<td width="1">
						<select name="device" onChange="<?php print $filterChange;?>">
						<option value="-1"<?php if ($_REQUEST["device"] == "-1") {?> selected<?php }?>>All</option>
						<?php
						$sql_where = "";
						if ($_REQUEST["site"] != -1) {
							$sql_where .= (strlen($sql_where) ? " AND " : "WHERE ") . "(site_id='" . $_REQUEST["site"] . "')";
						}
						if ($_REQUEST["type"] != "-1") {
							$sql_where .= (strlen($sql_where) ? " AND " : "WHERE ") . "(device_type_id='" . $_REQUEST["type"] . "')";
						}
						$devices = array_rekey(db_fetch_assoc("SELECT device_id, device_name FROM mac_track_devices $sql_where"), "device_id", "device_name");
						if (sizeof($devices) > 0) {
						foreach ($devices as $device_id => $device_name) {
							print '<option value="' . $device_id .'"'; if ($_REQUEST["device"] == $device_id) { print " selected"; } print ">" . $device_name . "</option>";
						}
						}
						?>
						</select>
					</td>
					<td width="50">
						&nbsp;Records:&nbsp;
					</td>
					<td width="1">
						<select name="rows" onChange="<?php print $filterChange;?>">
						<?php
						if (sizeof($rows_selector) > 0) {
						foreach ($rows_selector as $key => $value) {
							print '<option value="' . $key . '"'; if ($_REQUEST["rows"] == $key) { print "selected"; } print ">" . $value . "</option>\n";
						}
						}
						?>
						</select>
					</td>
				</tr>
			</table>
			<table cellpadding=1 cellspacing=0>
				<tr>
					<td width="50">
						Search:&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" size="60" value="<?php print $_REQUEST["filter"];?>">
					</td>
					<td nowrap>
						&nbsp;<input type="submit" name="Go" value="Go" alt="Go" border="0" align="absmiddle">
						<input type="submit" name="clear_x" value="Clear" alt="Clear" border="0" align="absmiddle">
						<input type="submit" name="export_x" value="Export" alt="Export" border="0" align="absmiddle">
					</td>
				</tr>
			</table>
			<input type='hidden' name='page' value='1'>
			</form>
		</td>
	</tr><?php
}
?>

