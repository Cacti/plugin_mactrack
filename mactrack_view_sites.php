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

$guest_account = true;
chdir('../../');
include("./include/auth.php");
include_once("./include/global_arrays.php");
include_once("./plugins/mactrack/lib/mactrack_functions.php");

define("MAX_DISPLAY_PAGES", 21);

if (isset($_REQUEST["export_x"])) {
	mactrack_view_export_sites();
}else{
	mactrack_redirect();
	$title = "Device Tracking - Site Report View";
	general_header();
	mactrack_view_sites();
	bottom_footer();
}

function mactrack_view_export_sites() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("site_id"));
	input_validate_input_number(get_request_var_request("device_id"));
	input_validate_input_number(get_request_var_request("page"));
	/* ==================================================== */

	/* clean up report string */
	if (isset($_REQUEST["report"])) {
		$_REQUEST["report"] = sanitize_search_string(get_request_var_request("report"));
	}

	/* clean up search string */
	if (isset($_REQUEST["detail"])) {
		$_REQUEST["detail"] = sanitize_search_string(get_request_var_request("detail"));
	}

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
	load_current_session_value("page", "sess_mactrack_view_sites_current_page", "1");
	load_current_session_value("detail", "sess_mactrack_view_sites_detail", "false");
	load_current_session_value("device_type_id", "sess_mactrack_view_sites_device_type_id", "-1");
	load_current_session_value("site_id", "sess_mactrack_view_sites_site_id", "-1");
	load_current_session_value("filter", "sess_mactrack_view_sites_filter", "");
	load_current_session_value("sort_column", "sess_mactrack_view_sites_sort_column", "site_name");
	load_current_session_value("sort_direction", "sess_mactrack_view_sites_sort_direction", "ASC");

	$sql_where = "";

	$sites = mactrack_view_get_site_records($sql_where, 0, FALSE);

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

function mactrack_view_get_site_records(&$sql_where, $row_limit, $apply_limits = TRUE) {
	/* create SQL where clause */
	$device_type_info = db_fetch_row("SELECT * FROM mac_track_device_types WHERE device_type_id = '" . $_REQUEST["device_type_id"] . "'");

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

	if (($_REQUEST["site_id"] != "-1") && ($_REQUEST["detail"])){
		if (!strlen($sql_where)) {
			$sql_where = "WHERE (mac_track_devices.site_id='" . $_REQUEST["site_id"] . "')";
		}else{
			$sql_where .= " AND (mac_track_devices.site_id='" . $_REQUEST["site_id"] . "')";
		}
	}

	if ($_REQUEST["detail"] == "false") {
		$query_string = "SELECT *
			FROM mac_track_sites
			$sql_where
			ORDER BY " . $_REQUEST["sort_column"] . " " . $_REQUEST["sort_direction"];

		if ($apply_limits) {
			$query_string .= " LIMIT " . ($row_limit*($_REQUEST["page"]-1)) . "," . $row_limit;
		}
	}else{
		$query_string ="SELECT mac_track_sites.site_name, mac_track_sites.site_id,
			Count(mac_track_device_types.device_type_id) AS total_devices,
			mac_track_device_types.device_type_id,
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
			$query_string .= " LIMIT " . ($row_limit*($_REQUEST["page"]-1)) . "," . $row_limit;
		}
	}

	return db_fetch_assoc($query_string);
}

function mactrack_view_sites() {
	global $title, $config, $item_rows;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("site_id"));
	input_validate_input_number(get_request_var_request("device_id"));
	input_validate_input_number(get_request_var_request("page"));
	input_validate_input_number(get_request_var_request("rows"));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST["detail"])) {
		$_REQUEST["detail"] = sanitize_search_string(get_request_var_request("detail"));
	}

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
	if (isset($_REQUEST["clear_x"]) || isset($_REQUEST["reset"])) {
		kill_session_var("sess_mactrack_view_sites_current_page");
		kill_session_var("sess_mactrack_view_sites_detail");
		kill_session_var("sess_mactrack_view_sites_device_type_id");
		kill_session_var("sess_mactrack_view_sites_site_id");
		kill_session_var("sess_mactrack_view_sites_filter");
		kill_session_var("sess_default_rows");
		kill_session_var("sess_mactrack_view_sites_sort_column");
		kill_session_var("sess_mactrack_view_sites_sort_direction");

		$_REQUEST["page"] = 1;

		if (isset($_REQUEST["clear_x"])) {
			unset($_REQUEST["filter"]);
			unset($_REQUEST["rows"]);
			unset($_REQUEST["device_type_id"]);
			unset($_REQUEST["site_id"]);
			unset($_REQUEST["detail"]);
			unset($_REQUEST["sort_column"]);
			unset($_REQUEST["sort_direction"]);
		}
	}else{
		/* if any of the settings changed, reset the page number */
		$changed = 0;
		$changed += mactrack_check_changed("device_type_id", "sess_mactrack_view_sites_device_type_id");
		$changed += mactrack_check_changed("site_id", "sess_mactrack_view_sites_site_id");
		$changed += mactrack_check_changed("filter", "sess_mactrack_view_sites_filter");
		$changed += mactrack_check_changed("rows", "sess_default_rows");
		$changed += mactrack_check_changed("detail", "sess_mactrack_view_sites_detail");

		if ($changed) {
			$_REQUEST["page"] = "1";
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page", "sess_mactrack_view_sites_current_page", "1");
	load_current_session_value("detail", "sess_mactrack_view_sites_detail", "false");
	load_current_session_value("device_type_id", "sess_mactrack_view_sites_device_type_id", "-1");
	load_current_session_value("site_id", "sess_mactrack_view_sites_site_id", "-1");
	load_current_session_value("filter", "sess_mactrack_view_sites_filter", "");
	load_current_session_value("rows", "sess_default_rows", read_config_option("num_rows_table"));
	load_current_session_value("sort_column", "sess_mactrack_view_sites_sort_column", "site_name");
	load_current_session_value("sort_direction", "sess_mactrack_view_sites_sort_direction", "ASC");

	if ($_REQUEST["rows"] == -1) {
		$row_limit = read_config_option("num_rows_table");
	}elseif ($_REQUEST["rows"] == -2) {
		$row_limit = 999999;
	}else{
		$row_limit = $_REQUEST["rows"];
	}

	if (defined("URL_PATH")) {
		$webroot = URL_PATH;
	}else{
		$webroot = $config["url_path"];
	}

	mactrack_tabs();
	html_start_box("<strong>$title</strong>", "100%", "", "3", "center", "");
	mactrack_site_filter();
	html_end_box();

	html_start_box("", "100%", "", "3", "center", "");

	$sql_where = "";

	$sites = mactrack_view_get_site_records($sql_where, $row_limit);

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

	$nav = html_nav_bar("mactrack_view_sites.php", MAX_DISPLAY_PAGES, get_request_var_request("page"), $row_limit, $total_rows, 11, 'Sites');

	print $nav;

	if ($_REQUEST["detail"] == "false") {
		$display_text = array(
			"nosort" => array("Actions", ""),
			"site_name" => array("Site Name", "ASC"),
			"total_devices" => array("Devices", "DESC"),
			"total_ips" => array("Total IP's", "DESC"),
			"total_user_ports" => array("User Ports", "DESC"),
			"total_oper_ports" => array("User Ports Up", "DESC"),
			"total_macs" => array("MACS Found", "DESC"),
			"total_device_errors" => array("Device Errors", "DESC"));

		html_header_sort($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"]);

		if (sizeof($sites) > 0) {
			foreach ($sites as $site) {
				form_alternate_row('row_' . $site["site_id"], true);
					?>
					<td width=140>
						<?php
						if (api_user_realm_auth('mactrack_sites.php')) {
							echo "<a href='" . $webroot . "plugins/mactrack/mactrack_sites.php?action=edit&site_id=" . $site['site_id'] . "' title='Edit Site'><img border='0' src='" . $webroot . "plugins/mactrack/images/edit_object.png'></a>";
							echo "<a href='#'><img id='r_" . $site["site_id"] . "' src='" . $config['url_path'] . "plugins/mactrack/images/rescan_site.gif' alt='' onMouseOver='style.cursor=\"pointer\"' onClick='site_scan(" . $site["site_id"] . ")' title='Rescan Site' border='0'></a>";
						}
						?>
						<a href='<?php print $webroot . "plugins/mactrack/mactrack_view_devices.php?report=devices&reset&site_id=" . $site['site_id'];?>' title='View Devices'><img border='0' src='<?php print $webroot;?>plugins/mactrack/images/view_devices.gif'></a>
						<a href='<?php print $webroot . "plugins/mactrack/mactrack_view_ips.php?report=ips&reset&site_id=" . $site['site_id'];?>' title='View IP Ranges'><img border='0' src='<?php print $webroot;?>plugins/mactrack/images/view_networks.gif'></a>
						<a href='<?php print $webroot . "plugins/mactrack/mactrack_view_arp.php?report=arp&reset&site_id=" . $site['site_id'];?>' title='View IP Addresses'><img border='0' src='<?php print $webroot;?>plugins/mactrack/images/view_ipaddresses.gif'></a>
						<a href='<?php print $webroot . "plugins/mactrack/mactrack_view_macs.php?report=macs&reset&device_id=-1&scan_date=3&site_id=" . $site['site_id'];?>' title='View MAC Addresses'><img border='0' src='<?php print $webroot;?>plugins/mactrack/images/view_macs.gif'></a>
						<a href='<?php print $webroot . "plugins/mactrack/mactrack_view_interfaces.php?report=interfaces&reset&site=" . $site['site_id'];?>' title='View Interfaces'><img border='0' src='<?php print $webroot;?>plugins/mactrack/images/view_interfaces.gif'></a>
					</td>
					<td width=200>
						<?php print "<strong>" . (strlen($_REQUEST["filter"]) ? preg_replace("/(" . preg_quote($_REQUEST["filter"]) . ")/i", "<span class='filteredValue'>\\1</span>", $site["site_name"]) : $site["site_name"]) . "</strong>";?>
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
		}else{
			print "<tr><td colspan='10'><em>No MacTrack Sites</em></td></tr>";
		}

		print $nav;

		html_end_box(false);

		mactrack_display_stats();
	}else{
		$display_text = array(
			"nosort" => array("Actions", ""),
			"site_name" => array("Site Name", "ASC"),
			"vendor" => array("Vendor", "ASC"),
			"description" => array("Device Type", "DESC"),
			"total_devices" => array("Total Devices", "DESC"),
			"sum_ips_total" => array("Total IP's", "DESC"),
			"sum_ports_total" => array("Total User Ports", "DESC"),
			"sum_ports_active" => array("Total Oper Ports", "DESC"),
			"sum_ports_trunk" => array("Total Trunks", "DESC"),
			"sum_macs_active" => array("MACS Found", "DESC"));

		html_header_sort($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"]);

		if (sizeof($sites) > 0) {
			foreach ($sites as $site) {
				form_alternate_row();
					?>
					<td width=100>
						<?php
						if (api_user_realm_auth('mactrack_sites.php')) {
							echo "<a href='" . $webroot . "plugins/mactrack/mactrack_sites.php?action=edit&site_id=" . $site['site_id'] . "' title='Edit Site'><img border='0' src='" . $webroot . "plugins/mactrack/images/edit_object.png'></a>";
						}
						?>
						<a href='<?php print $webroot . "plugins/mactrack/mactrack_view_devices.php?report=devices&site_id=" . $site['site_id'] . "&device_type_id=" . $site['device_type_id'];?>&type_id=-1&status=-1&filter=' title='View Devices'><img border='0' src='<?php print $webroot;?>plugins/mactrack/images/view_devices.gif'></a>
						<a href='<?php print $webroot . "plugins/mactrack/mactrack_view_ips.php?report=ips&reset&site_id=" . $site['site_id'];?>' title='View IP Ranges'><img border='0' src='<?php print $webroot;?>plugins/mactrack/images/view_networks.gif'></a>
						<a href='<?php print $webroot . "plugins/mactrack/mactrack_view_macs.php?report=macs&reset&device_id=-1&scan_date=3&site_id=" . $site['site_id'];?>' title='View MAC Addresses'><img border='0' src='<?php print $webroot;?>plugins/mactrack/images/view_macs.gif'></a>
						<a href='<?php print $webroot . "plugins/mactrack/mactrack_view_interfaces.php?report=interfaces&reset&site=" . $site['site_id'];?>' title='View Interfaces'><img border='0' src='<?php print $webroot;?>plugins/mactrack/images/view_interfaces.gif'></a>
					</td>
					<td width=200>
						<?php print "<strong>" . (strlen($_REQUEST["filter"]) ? preg_replace("/(" . preg_quote($_REQUEST["filter"]) . ")/i", "<span class='filteredValue'>\\1</span>", $site["site_name"]) : $site["site_name"]) . "</strong>";?>
					</td>
					<td><?php print (strlen($_REQUEST["filter"]) ? preg_replace("/(" . preg_quote($_REQUEST["filter"]) . ")/i", "<span class='filteredValue'>\\1</span>", $site["vendor"]) : $site["vendor"]);?></td>
					<td><?php print (strlen($_REQUEST["filter"]) ? preg_replace("/(" . preg_quote($_REQUEST["filter"]) . ")/i", "<span class='filteredValue'>\\1</span>", $site["description"]) : $site["description"]);?></td>
					<td><?php print number_format($site["total_devices"]);?></td>
					<td><?php print ($site["device_type"] == "1" ? "N/A" : number_format($site["sum_ips_total"]));?></td>
					<td><?php print ($site["device_type"] == "3" ? "N/A" : number_format($site["sum_ports_total"]));?></td>
					<td><?php print ($site["device_type"] == "3" ? "N/A" : number_format($site["sum_ports_active"]));?></td>
					<td><?php print ($site["device_type"] == "3" ? "N/A" : number_format($site["sum_ports_trunk"]));?></td>
					<td><?php print ($site["device_type"] == "3" ? "N/A" : number_format($site["sum_macs_active"]));?></td>
				</tr>
				<?php
			}
		}else{
			print "<tr><td colspan='10'><em>No MacTrack Sites</em></td></tr>";
		}

		print $nav;

		html_end_box(false);

		mactrack_display_stats();
	}

	print "<div id='response'></div>";
}

?>
