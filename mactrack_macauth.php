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
include_once("./plugins/mactrack/lib/mactrack_functions.php");

define("MAX_DISPLAY_PAGES", 21);

$maca_actions = array(
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

		mactrack_maca_edit();

		include_once("./include/bottom_footer.php");
		break;
	default:
		include_once("./include/top_header.php");

		mactrack_maca();

		include_once("./include/bottom_footer.php");

		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if ((isset($_POST["save_component_maca"])) && (empty($_POST["add_dq_y"]))) {
		$mac_id = api_mactrack_maca_save($_POST["mac_id"], $_POST["mac_address"], $_POST["description"]);

		if ((is_error_message()) || ($_POST["mac_id"] != $_POST["_mac_id"])) {
			header("Location: mactrack_macauth.php?action=edit&id=" . (empty($mac_id) ? $_POST["mac_id"] : $mac_id));
		}else{
			header("Location: mactrack_macauth.php");
		}
	}
}

/* ------------------------
    The "actions" function
   ------------------------ */

function form_actions() {
	global $colors, $config, $maca_actions, $fields_mactrack_maca_edit;

	/* if we are to save this form, instead of display it */
	if (isset($_POST["selected_items"])) {
		$selected_items = unserialize(stripslashes($_POST["selected_items"]));

		if ($_POST["drp_action"] == "1") { /* delete */
			for ($i=0; $i<count($selected_items); $i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				api_mactrack_maca_remove($selected_items[$i]);
			}
		}

		header("Location: mactrack_macauth.php");
		exit;
	}

	/* setup some variables */
	$maca_list = ""; $i = 0;

	/* loop through each of the mac authorization items selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (ereg("^chk_([0-9]+)$", $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$maca_info = db_fetch_cell("SELECT mac_address FROM mac_track_macauth WHERE mac_id=" . $matches[1]);
			$maca_list .= "<li>" . $maca_info . "<br>";
			$maca_array[$i] = $matches[1];
		}

		$i++;
	}

	include_once("./include/top_header.php");

	html_start_box("<strong>" . $maca_actions{$_POST["drp_action"]} . "</strong>", "60%", $colors["header_panel"], "3", "center", "");

	print "<form action='mactrack_macauth.php' method='post'>\n";

	if ($_POST["drp_action"] == "1") { /* delete */
		print "	<tr>
				<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>Are you sure you want to delete the following Authorized Mac's?</p>
					<p>$maca_list</p>";
					print "</td></tr>
				</td>
			</tr>\n
			";
	}

	if (!isset($maca_array)) {
		print "<tr><td bgcolor='#" . $colors["form_alternate1"]. "'><span class='textError'>You must select at least one Authorized Mac to delete.</span></td></tr>\n";
		$save_html = "";
	}else{
		$save_html = "<input type='submit' name='save_x' value='Yes'>";
	}

	print "	<tr>
			<td colspan='2' align='right' bgcolor='#eaeaea'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='selected_items' value='" . (isset($maca_array) ? serialize($maca_array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . $_POST["drp_action"] . "'>" . (strlen($save_html) ? "
				<input type='submit' name='cancel_x' value='No'>
				$save_html" : "<input type='submit' name='cancel_x' value='Return'>") . "
			</td>
		</tr>
		";

	html_end_box();

	include_once("./include/bottom_footer.php");
}

function api_mactrack_maca_save($mac_id, $mac_address, $description) {
	$save["mac_id"] = $mac_id;
	$save["mac_address"] = form_input_validate($mac_address, $_POST["mac_address"], "", false, 3);
	$save["description"] = form_input_validate($description, $_POST["description"], "", false, 3);
	$save["added_date"] = date("Y-m-d h:i:s");
	$save["added_by"] = $_SESSION["sess_user_id"];

	$mac_id = 0;
	if (!is_error_message()) {
		$mac_id = sql_save($save, "mac_track_macauth", "mac_address", false);

		if ($mac_id) {
			db_execute("UPDATE mac_track_ports SET authorized='1' WHERE mac_address LIKE '$mac_address%'");
			raise_message(1);
		}else{
			raise_message(2);
		}
	}

	return $mac_id;
}

function api_mactrack_maca_remove($mac_id) {
	$mac_address = db_fetch_cell("SELECT mac_address WHERE mac_id='$mac_id'");
	db_execute("DELETE FROM mac_track_macauth WHERE mac_id='" . $mac_id . "'");
	db_execute("UPDATE mac_track_ports SET authorized='0' WHERE mac_address LIKE '$mac_address%'");
}

/* ---------------------
    MacAuth Functions
   --------------------- */

function mactrack_maca_remove() {
	global $config;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("mac_address"));
	/* ==================================================== */

	if ((read_config_option("remove_verification") == "on") && (!isset($_GET["confirm"]))) {
		include("./include/top_header.php");
		form_confirm("Are You Sure?", "Are you sure you want to delete the Authorized Mac Address(s)<strong>'" . db_fetch_cell("SELECT mac_address FROM mac_track_macauth WHERE mac_id" . $_GET["mac_id"]) . "'</strong>?", "mactrack_macauth.php", "mactrack_macauth.php?action=remove&mac_id=" . $_GET["mac_id"]);
		include("./include/bottom_footer.php");
		exit;
	}

	if ((read_config_option("remove_verification") == "") || (isset($_GET["confirm"]))) {
		api_mactrack_maca_remove($_GET["mac_id"]);
	}
}

function mactrack_maca_get_maca_records(&$sql_where, $row_limit, $apply_limits = TRUE) {
	$sql_where = "";

	/* form the 'where' clause for our main sql query */
	if (strlen($_REQUEST["filter"])) {
		$sql_where = "WHERE (mac_address LIKE '%%" . $_REQUEST["filter"] . "%%' OR " .
			"description LIKE '%%" . $_REQUEST["filter"] . "%%')";
	}

	$query_string = "SELECT *
		FROM mac_track_macauth
		$sql_where
		ORDER BY " . $_REQUEST["sort_column"] . " " . $_REQUEST["sort_direction"];

	if ($apply_limits) {
		$query_string .= " LIMIT " . ($row_limit*($_REQUEST["page"]-1)) . "," . $row_limit;
	}

	return db_fetch_assoc($query_string);
}

function mactrack_maca_edit() {
	global $colors, $fields_mactrack_maca_edit;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("mac_id"));
	/* ==================================================== */

	display_output_messages();

	if (!empty($_GET["mac_id"])) {
		$mac_record = db_fetch_row("SELECT * FROM mac_track_macauth WHERE mac_id=" . $_GET["mac_id"]);
		$header_label = "[edit: " . $mac_record["mac_address"] . "]";
	}else{
		$header_label = "[new]";
	}

	html_start_box("<strong>MacTrack MacAuth</strong> $header_label", "100%", $colors["header"], "3", "center", "");

	draw_edit_form(array(
		"config" => array("form_name" => "chk"),
		"fields" => inject_form_variables($fields_mactrack_maca_edit, (isset($mac_record) ? $mac_record : array()))
		));

	html_end_box();

	if (isset($mac_record)) {
		mactrack_save_button("return", "save", "", "mac_address");
	}else{
		mactrack_save_button("cancel", "save", "", "mac_address");
	}
}

function mactrack_maca() {
	global $colors, $maca_actions, $config, $item_rows;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("page"));
	input_validate_input_number(get_request_var_request("mac_id"));
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
		kill_session_var("sess_mactrack_maca_current_page");
		kill_session_var("sess_mactrack_maca_filter");
		kill_session_var("sess_mactrack_maca_rows");
		kill_session_var("sess_mactrack_maca_sort_column");
		kill_session_var("sess_mactrack_maca_sort_direction");

		$_REQUEST["page"] = 1;
		unset($_REQUEST["filter"]);
		unset($_REQUEST["rows"]);
		unset($_REQUEST["sort_column"]);
		unset($_REQUEST["sort_direction"]);
	}else{
		/* if any of the settings changed, reset the page number */
		$changed = 0;
		$changed += mactrack_check_changed("filter", "sess_mactrack_maca_filter");
		$changed += mactrack_check_changed("detail", "sess_mactrack_maca_detail");
		$changed += mactrack_check_changed("rows", "sess_mactrack_maca_rows");

		if ($changed) {
			$_REQUEST["page"] = "1";
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page", "sess_mactrack_maca_current_page", "1");
	load_current_session_value("rows", "sess_mactrack_maca_rows", "-1");
	load_current_session_value("filter", "sess_mactrack_maca_filter", "");
	load_current_session_value("sort_column", "sess_mactrack_maca_sort_column", "mac_address");
	load_current_session_value("sort_direction", "sess_mactrack_maca_sort_direction", "ASC");

	if ($_REQUEST["rows"] == -1) {
		$row_limit = read_config_option("num_rows_mactrack");
	}elseif ($_REQUEST["rows"] == -2) {
		$row_limit = 999999;
	}else{
		$row_limit = $_REQUEST["rows"];
	}

	html_start_box("<strong>MacTrack MacAuth Filters</strong>", "100%", $colors["header"], "3", "center", "mactrack_macauth.php?action=edit");

	include("./plugins/mactrack/html/inc_mactrack_maca_filter_table.php");

	html_end_box();

	html_start_box("", "100%", $colors["header"], "3", "center", "");

	$sql_where = "";

	$maca = mactrack_maca_get_maca_records($sql_where, $row_limit);

	$total_rows = db_fetch_cell("SELECT count(*)
		FROM mac_track_macauth
		$sql_where");

	/* generate page list */
	$url_page_select = str_replace("&page", "?page", get_page_list($_REQUEST["page"], MAX_DISPLAY_PAGES, $row_limit, $total_rows, "mactrack_macauth.php"));

	$nav = "<tr bgcolor='#" . $colors["header"] . "'>
			<td colspan='9'>
				<table width='100%' cellspacing='0' cellpadding='0' border='0'>
					<tr>
						<td align='left' class='textHeaderDark'>
							<strong>&lt;&lt; "; if ($_REQUEST["page"] > 1) { $nav .= "<a class='linkOverDark' href='mactrack_macauth.php?page=" . ($_REQUEST["page"]-1) . "'>"; } $nav .= "Previous"; if ($_REQUEST["page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
						</td>\n
						<td align='center' class='textHeaderDark'>
							Showing Rows " . (($row_limit*($_REQUEST["page"]-1))+1) . " to " . ((($total_rows < $row_limit) || ($total_rows < ($row_limit*$_REQUEST["page"]))) ? $total_rows : ($row_limit*$_REQUEST["page"])) . " of $total_rows [$url_page_select]
						</td>\n
						<td align='right' class='textHeaderDark'>
							<strong>"; if (($_REQUEST["page"] * $row_limit) < $total_rows) { $nav .= "<a class='linkOverDark' href='mactrack_macauth.php?page=" . ($_REQUEST["page"]+1) . "'>"; } $nav .= "Next"; if (($_REQUEST["page"] * $row_limit) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
						</td>\n
					</tr>
				</table>
			</td>
		</tr>\n";

	if ($total_rows) {
		print $nav;
	}

	$display_text = array(
		"mac_address" => array("Mac Address", "ASC"),
		"" => array("Reason", "ASC"),
		"added_date" => array("Added/Modified", "ASC"),
		"date_last_seen" => array("By", "ASC"));

	html_header_sort_checkbox($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"]);

	$i = 0;
	if (sizeof($maca) > 0) {
		foreach ($maca as $mac) {
			form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
				?>
				<td width="20%">
					<a class="linkEditMain" href="mactrack_macauth.php?action=edit&mac_id=<?php print $mac['mac_id'];?>"><?php print (strlen($_REQUEST["filter"]) ? eregi_replace("(" . preg_quote($_REQUEST["filter"]) . ")", "<span style='background-color: #F8D93D;'>\\1</span>", $mac["mac_address"]) : $mac["mac_address"]);?></a>
				</td>
				<td width="50%"><?php print (strlen($_REQUEST["filter"]) ? eregi_replace("(" . preg_quote($_REQUEST["filter"]) . ")", "<span style='background-color: #F8D93D;'>\\1</span>", $mac["description"]) : $mac["description"]);?></td>
				<td width="20%"><?php print $mac["added_date"];?></td>
				<td width="10%"><?php print db_fetch_cell("SELECT full_name FROM user_auth WHERE id='" . $mac["added_by"] . "'");?></td>

				<td style="<?php print get_checkbox_style();?>" width="1%" align="right">
					<input type='checkbox' style='margin: 0px;' name='chk_<?php print $mac["mac_id"];?>' title="<?php print $mac["name"];?>">
				</td>
			</tr>
			<?php
		}

		/* put the nav bar on the bottom as well */
		print $nav;
	}else{
		print "<tr><td colspan=10><em>No Authorized Mac Addresses</em></td></tr>";
	}
	html_end_box(false);

	/* draw the dropdown containing a list of available actions for this form */
	mactrack_draw_actions_dropdown($maca_actions);
}

?>