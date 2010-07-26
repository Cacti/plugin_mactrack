<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2010 The Cacti Group                                 |
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

$site_actions = array(
	1 => "Delete"
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

		mactrack_site_edit();

		include_once("./include/bottom_footer.php");
		break;
	default:
		if (isset($_REQUEST["export_x"])) {
			mactrack_site_export();
		}else{
			include_once("./include/top_header.php");

			mactrack_site();

			include_once("./include/bottom_footer.php");
		}
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if ((isset($_POST["save_component_site"])) && (empty($_POST["add_dq_y"]))) {
		$site_id = api_mactrack_site_save($_POST["site_id"], $_POST["site_name"], $_POST["customer_contact"],
		$_POST["netops_contact"], $_POST["facilities_contact"], $_POST["site_info"]);

		header("Location: mactrack_sites.php?action=edit&id=" . (empty($site_id) ? $_POST["site_id"] : $site_id));
	}
}

/* ------------------------
    The "actions" function
   ------------------------ */

function form_actions() {
	global $colors, $config, $site_actions, $fields_mactrack_site_edit;

	/* if we are to save this form, instead of display it */
	if (isset($_POST["selected_items"])) {
		$selected_items = unserialize(stripslashes($_POST["selected_items"]));

		if ($_POST["drp_action"] == "1") { /* delete */
			for ($i=0; $i<count($selected_items); $i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				api_mactrack_site_remove($selected_items[$i]);
			}
		}

		header("Location: mactrack_sites.php");
		exit;
	}

	/* setup some variables */
	$site_list = ""; $i = 0;

	/* loop through each of the host templates selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (ereg("^chk_([0-9]+)$", $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$site_info = db_fetch_cell("SELECT site_name FROM mac_track_sites WHERE site_id=" . $matches[1]);
			$site_list .= "<li>" . $site_info . "<br>";
			$site_array[$i] = $matches[1];
		}

		$i++;
	}

	include_once("./include/top_header.php");

	html_start_box("<strong>" . $site_actions{$_POST["drp_action"]} . "</strong>", "60%", $colors["header_panel"], "3", "center", "");

	print "<form action='mactrack_sites.php' method='post'>\n";

	if ($_POST["drp_action"] == "1") { /* delete */
		print "	<tr>
				<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>Are you sure you want to delete the following site(s)?</p>
					<p><ul>$site_list</ul></p>";
					print "</td></tr>
				</td>
			</tr>\n
			";
	}

	if (!isset($site_array)) {
		print "<tr><td bgcolor='#" . $colors["form_alternate1"]. "'><span class='textError'>You must select at least one site.</span></td></tr>\n";
		$save_html = "";
	}else{
		$save_html = "<input type='submit' name='save_x' value='Yes'>";
	}

	print "	<tr>
			<td colspan='2' align='right' bgcolor='#eaeaea'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='selected_items' value='" . (isset($site_array) ? serialize($site_array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . $_POST["drp_action"] . "'>" . (strlen($save_html) ? "
				<input type='submit' name='cancel_x' value='No'>
				$save_html" : "<input type='submit' name='cancel_x' value='Return'>") . "
			</td>
		</tr>
		";

	html_end_box();

	include_once("./include/bottom_footer.php");
}

function mactrack_site_export() {
	global $colors, $site_actions, $config;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("site_id"));
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
	load_current_session_value("page", "sess_mactrack_sites_current_page", "1");
	load_current_session_value("detail", "sess_mactrack_sites_detail", "false");
	load_current_session_value("device_type_id", "sess_mactrack_sites_device_type_id", "-1");
	load_current_session_value("site_id", "sess_mactrack_sites_site_id", "-1");
	load_current_session_value("filter", "sess_mactrack_sites_filter", "");
	load_current_session_value("sort_column", "sess_mactrack_sites_sort_column", "site_name");
	load_current_session_value("sort_direction", "sess_mactrack_sites_sort_direction", "ASC");

	$sql_where = "";

	$sites = mactrack_site_get_site_records($sql_where, 0, FALSE);

	if ($_REQUEST["detail"] == "false") {
		$xport_array = array();
		array_push($xport_array, '"site_name","total_devices","total_device_errors",' .
			'"total_macs","total_ips","total_oper_ports",' .
			'"total_user_ports"');

		if (sizeof($sites)) {
			foreach($sites as $site) {
				array_push($xport_array,'"' . $site['site_name'] . '","' .
				$site['total_devices'] . '","' .
				$site['total_device_errors'] . '","' .
				$site['total_macs'] . '","' .
				$site['total_ips'] . '","' .
				$site['total_oper_ports'] . '","' .
				$site['total_user_ports'] . '"');
			}
		}
	}else{
		$xport_array = array();
		array_push($xport_array, '"site_name","total_devices","vendor",' .
			'"device_name","sum_ips_total","sum_ports_total",' .
			'"sum_ports_active","sum_ports_trunk","sum_mac_active"');

		if (sizeof($sites)) {
			foreach($sites as $site) {
				array_push($xport_array,'"' . $site['site_name'] . '","' .
				$site['total_devices'] . '","' .
				$site['vendor'] . '","' .
				$site['device_name'] . '","' .
				$site['sum_ips_total'] . '","' .
				$site['sum_ports_total'] . '","' .
				$site['sum_ports_active'] . '","' .
				$site['sum_ports_trunk'] . '","' .
				$site['sum_macs_active'] . '"');
			}
		}
	}

	header("Content-type: application/csv");
	header("Content-Disposition: attachment; filename=cacti_site_xport.csv");
	foreach($xport_array as $xport_line) {
		print $xport_line . "\n";
	}
}

function api_mactrack_site_save($site_id, $site_name, $customer_contact, $netops_contact, $facilities_contact, $site_info) {
	$save["site_id"]            = $site_id;
	$save["site_name"]          = form_input_validate($site_name, "site_name", "", false, 3);
	$save["site_info"]          = form_input_validate($site_info, "site_info", "", true, 3);
	$save["customer_contact"]   = form_input_validate($customer_contact, "customer_contact", "", true, 3);
	$save["netops_contact"]     = form_input_validate($netops_contact, "netops_contact", "", true, 3);
	$save["facilities_contact"] = form_input_validate($facilities_contact, "facilities_contact", "", true, 3);

	$site_id = 0;
	if (!is_error_message()) {
		$site_id = sql_save($save, "mac_track_sites", "site_id");

		if ($site_id) {
			raise_message(1);
		}else{
			raise_message(2);
		}
	}

	return $site_id;
}

function api_mactrack_site_remove($site_id) {
	db_execute("DELETE FROM mac_track_sites WHERE site_id='" . $site_id . "'");
	db_execute("DELETE FROM mac_track_devices WHERE site_id=" . $site_id);
	db_execute("DELETE FROM mac_track_aggregated_ports WHERE site_id=" . $site_id);
	db_execute("DELETE FROM mac_track_interfaces WHERE site_id=" . $site_id);
	db_execute("DELETE FROM mac_track_ips WHERE site_id=" . $site_id);
	db_execute("DELETE FROM mac_track_ip_ranges WHERE site_id=" . $site_id);
	db_execute("DELETE FROM mac_track_ports WHERE site_id=" . $site_id);
	db_execute("DELETE FROM mac_track_temp_ports WHERE site_id=" . $site_id);
	db_execute("DELETE FROM mac_track_vlans WHERE site_id=" . $site_id);
}

/* ---------------------
    Site Functions
   --------------------- */

function mactrack_site_remove() {
	global $config;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("site_id"));
	/* ==================================================== */

	if ((read_config_option("remove_verification") == "on") && (!isset($_GET["confirm"]))) {
		include("./include/top_header.php");
		form_confirm("Are You Sure?", "Are you sure you want to delete the site <strong>'" . db_fetch_cell("select description from host where id=" . $_GET["device_id"]) . "'</strong>?", "mactrack_sites.php", "mactrack_sites.php?action=remove&site_id=" . $_GET["site_id"]);
		include("./include/bottom_footer.php");
		exit;
	}

	if ((read_config_option("remove_verification") == "") || (isset($_GET["confirm"]))) {
		api_mactrack_site_remove($_GET["site_id"]);
	}
}

function mactrack_site_get_site_records(&$sql_where, $row_limit, $apply_limits = TRUE) {
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
			$query_string .= " LIMIT " . ($row_limit*($_REQUEST["page"]-1)) . "," . $row_limit;
		}
	}else{
		$query_string ="SELECT
			mac_track_sites.site_id,
			mac_track_sites.site_name,
			Count(mac_track_device_types.device_type_id) AS total_devices,
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

function mactrack_site_edit() {
	global $colors, $fields_mactrack_site_edit;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("site_id"));
	/* ==================================================== */

	display_output_messages();

	if (!empty($_GET["site_id"])) {
		$site = db_fetch_row("select * from mac_track_sites where site_id=" . $_GET["site_id"]);
		$header_label = "[edit: " . $site["site_name"] . "]";
	}else{
		$header_label = "[new]";
	}

	html_start_box("<strong>MacTrack Site</strong> $header_label", "100%", $colors["header"], "3", "center", "");

	draw_edit_form(array(
		"config" => array("form_name" => "chk"),
		"fields" => inject_form_variables($fields_mactrack_site_edit, (isset($site) ? $site : array()))
		));

	html_end_box();

	if (isset($site)) {
		mactrack_save_button("mactrack_sites.php", "save", "", "site_id");
	}else{
		mactrack_save_button("cancel", "save", "", "site_id");
	}
}

function mactrack_site() {
	global $colors, $site_actions, $config, $item_rows;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("site_id"));
	input_validate_input_number(get_request_var_request("page"));
	input_validate_input_number(get_request_var_request("rows"));
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
	if (isset($_REQUEST["clear_x"])) {
		kill_session_var("sess_mactrack_sites_current_page");
		kill_session_var("sess_mactrack_sites_detail");
		kill_session_var("sess_mactrack_sites_device_type_id");
		kill_session_var("sess_mactrack_sites_site_id");
		kill_session_var("sess_mactrack_sites_rows");
		kill_session_var("sess_mactrack_sites_filter");
		kill_session_var("sess_mactrack_sites_sort_column");
		kill_session_var("sess_mactrack_sites_sort_direction");

		$_REQUEST["page"] = 1;
		unset($_REQUEST["filter"]);
		unset($_REQUEST["device_type_id"]);
		unset($_REQUEST["site_id"]);
		unset($_REQUEST["rows"]);
		unset($_REQUEST["detail"]);
		unset($_REQUEST["sort_column"]);
		unset($_REQUEST["sort_direction"]);
	}else{
		/* if any of the settings changed, reset the page number */
		$changed = 0;
		$changed += mactrack_check_changed("device_type_id", "sess_mactrack_sites_device_type_id");
		$changed += mactrack_check_changed("site_id", "sess_mactrack_sites_site_id");
		$changed += mactrack_check_changed("rows", "sess_mactrack_sites_rows");
		$changed += mactrack_check_changed("filter", "sess_mactrack_sites_filter");
		$changed += mactrack_check_changed("detail", "sess_mactrack_sites_detail");

		if ($changed) {
			$_REQUEST["page"] = "1";
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page", "sess_mactrack_sites_current_page", "1");
	load_current_session_value("detail", "sess_mactrack_sites_detail", "false");
	load_current_session_value("device_type_id", "sess_mactrack_sites_device_type_id", "-1");
	load_current_session_value("site_id", "sess_mactrack_sites_site_id", "-1");
	load_current_session_value("rows", "sess_mactrack_sites_rows", "-1");
	load_current_session_value("filter", "sess_mactrack_sites_filter", "");
	load_current_session_value("sort_column", "sess_mactrack_sites_sort_column", "site_name");
	load_current_session_value("sort_direction", "sess_mactrack_sites_sort_direction", "ASC");

	if ($_REQUEST["rows"] == -1) {
		$row_limit = read_config_option("num_rows_mactrack");
	}elseif ($_REQUEST["rows"] == -2) {
		$row_limit = 999999;
	}else{
		$row_limit = $_REQUEST["rows"];
	}

	html_start_box("<strong>MacTrack Site Filters</strong>", "100%", $colors["header"], "3", "center", "mactrack_sites.php?action=edit");

	mactrack_site_filter();

	html_end_box();

	html_start_box("", "100%", $colors["header"], "3", "center", "");

	$sql_where = "";

	$sites = mactrack_site_get_site_records($sql_where, $row_limit);

	if ($_REQUEST["detail"] == "false") {
		$total_rows = db_fetch_cell("SELECT
			COUNT(mac_track_sites.site_id)
			FROM mac_track_sites
			$sql_where");
	}else{
		$total_rows = db_fetch_cell("SELECT count(*)
			FROM (mac_track_device_types
			RIGHT JOIN mac_track_devices ON (mac_track_device_types.device_type_id = mac_track_devices.device_type_id))
			RIGHT JOIN mac_track_sites ON (mac_track_devices.site_id = mac_track_sites.site_id)
			$sql_where
			GROUP BY mac_track_sites.site_name, mac_track_device_types.device_type_id");
	}

	/* generate page list */
	$url_page_select = str_replace("&page", "?page", get_page_list($_REQUEST["page"], MAX_DISPLAY_PAGES, $row_limit, $total_rows, "mactrack_sites.php"));

	if ($_REQUEST["detail"] == "false") {
		if (defined("CACTI_VERSION")) {
			/* generate page list navigation */
			$nav = html_create_nav($_REQUEST["page"], MAX_DISPLAY_PAGES, $row_limit, $total_rows, 9, "mactrack_sites.php?filter=" . $_REQUEST["filter"]);
		}else{
			if ($total_rows > 0) {
				$nav = "<tr bgcolor='#" . $colors["header"] . "'>
						<td colspan='9'>
							<table width='100%' cellspacing='0' cellpadding='0' border='0'>
								<tr>
									<td align='left' class='textHeaderDark'>
										<strong>&lt;&lt; "; if ($_REQUEST["page"] > 1) { $nav .= "<a class='linkOverDark' href='mactrack_sites.php?page=" . ($_REQUEST["page"]-1) . "'>"; } $nav .= "Previous"; if ($_REQUEST["page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
									</td>\n
									<td align='center' class='textHeaderDark'>
										Showing Rows " . (($row_limit*($_REQUEST["page"]-1))+1) . " to " . ((($total_rows < $row_limit) || ($total_rows < ($row_limit*$_REQUEST["page"]))) ? $total_rows : ($row_limit*$_REQUEST["page"])) . " of $total_rows [$url_page_select]
									</td>\n
									<td align='right' class='textHeaderDark'>
										<strong>"; if (($_REQUEST["page"] * $row_limit) < $total_rows) { $nav .= "<a class='linkOverDark' href='mactrack_sites.php?page=" . ($_REQUEST["page"]+1) . "'>"; } $nav .= "Next"; if (($_REQUEST["page"] * $row_limit) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
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
		}

		print $nav;

		$display_text = array(
			"site_name" => array("Site Name", "ASC"),
			"total_devices" => array("Devices", "DESC"),
			"total_ips" => array("Total IP's", "DESC"),
			"total_user_ports" => array("User Ports", "DESC"),
			"total_oper_ports" => array("User Ports Up", "DESC"),
			"total_macs" => array("MACS Found", "DESC"),
			"total_device_errors" => array("Device Errors", "DESC"));

		html_header_sort_checkbox($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"]);

		$i = 0;
		if (sizeof($sites) > 0) {
			foreach ($sites as $site) {
				form_alternate_row_color($colors["alternate"],$colors["light"],$i, 'line' . $site["site_id"]); $i++;
				form_selectable_cell("<a class='linkEditMain' href='mactrack_sites.php?action=edit&site_id=" . $site["site_id"] . "'>". (strlen($_REQUEST["filter"]) ? preg_replace("/(" . preg_quote($_REQUEST["filter"]) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $site["site_name"]) : $site["site_name"]) . "</a>", $site["site_id"]);
				form_selectable_cell(number_format($site["total_devices"]), $site["site_id"]);
				form_selectable_cell(number_format($site["total_ips"]), $site["site_id"]);
				form_selectable_cell(number_format($site["total_user_ports"]), $site["site_id"]);
				form_selectable_cell(number_format($site["total_oper_ports"]), $site["site_id"]);
				form_selectable_cell(number_format($site["total_macs"]), $site["site_id"]);
				form_selectable_cell($site["total_device_errors"], $site["site_id"]);
				form_checkbox_cell($site["site_name"], $site["site_id"]);
				form_end_row();
			}

			/* put the nav bar on the bottom as well */
			print $nav;
		}else{
			print "<tr><td><em>No MacTrack Sites</em></td></tr>";
		}
		html_end_box(false);
	}else{
		if (defined("CACTI_VERSION")) {
			/* generate page list navigation */
			$nav = html_create_nav($_REQUEST["page"], MAX_DISPLAY_PAGES, $row_limit, $total_rows, 10, "mactrack_sites.php?filter=" . $_REQUEST["filter"]);
		}else{
			if ($total_rows > 0) {
				$nav = "<tr bgcolor='#" . $colors["header"] . "'>
						<td colspan='10'>
							<table width='100%' cellspacing='0' cellpadding='0' border='0'>
								<tr>
									<td align='left' class='textHeaderDark'>
										<strong>&lt;&lt; "; if ($_REQUEST["page"] > 1) { $nav .= "<a class='linkOverDark' href='mactrack_sites.php?page=" . ($_REQUEST["page"]-1) . "'>"; } $nav .= "Previous"; if ($_REQUEST["page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
									</td>\n
									<td align='center' class='textHeaderDark'>
										Showing Rows " . (($row_limit*($_REQUEST["page"]-1))+1) . " to " . ((($total_rows < $row_limit) || ($total_rows < ($row_limit*$_REQUEST["page"]))) ? $total_rows : ($row_limit*$_REQUEST["page"])) . " of $total_rows [$url_page_select]
									</td>\n
									<td align='right' class='textHeaderDark'>
										<strong>"; if (($_REQUEST["page"] * $row_limit) < $total_rows) { $nav .= "<a class='linkOverDark' href='mactrack_sites.php?page=" . ($_REQUEST["page"]+1) . "'>"; } $nav .= "Next"; if (($_REQUEST["page"] * $row_limit) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
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
		}

		print $nav;

		$display_text = array(
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

		$i = 0;
		if (sizeof($sites) > 0) {
			foreach ($sites as $site) {
				form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
					?>
					<td width=200>
						<a class='linkEditMain' href='mactrack_sites.php?action=edit&site_id=<?php print $site["site_id"];?>'><?php print (strlen($_REQUEST["filter"]) ? preg_replace("/(" . preg_quote($_REQUEST["filter"]) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $site["site_name"]) : $site["site_name"]);?></a>
					</td>
					<td><?php print (strlen($_REQUEST["filter"]) ? preg_replace("/(" . preg_quote($_REQUEST["filter"]) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $site["vendor"]) : $site["vendor"]);?></td>
					<td><?php print (strlen($_REQUEST["filter"]) ? preg_replace("/(" . preg_quote($_REQUEST["filter"]) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $site["description"]) : $site["description"]);?></td>
					<td><?php print number_format($site["total_devices"]);?></td>
					<td><?php print number_format($site["sum_ips_total"]);?></td>
					<td><?php print number_format($site["sum_ports_total"]);?></td>
					<td><?php print number_format($site["sum_ports_active"]);?></td>
					<td><?php print number_format($site["sum_ports_trunk"]);?></td>
					<td><?php print number_format($site["sum_macs_active"]);?></td>
				</tr>
				<?php
			}

			/* put the nav bar on the bottom as well */
			print $nav;
		}else{
			print "<tr><td><em>No MacTrack Sites</em></td></tr>";
		}
		html_end_box(false);
	}

	/* draw the dropdown containing a list of available actions for this form */
	if ($_REQUEST["detail"] == "false") {
		mactrack_draw_actions_dropdown($site_actions);
	}
}

?>
