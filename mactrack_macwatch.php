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

define("MAX_DISPLAY_PAGES", 21);

$macw_actions = array(
	1 => "Delete",
	2 => "Disable"
	);

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
	case 'save':
		form_save();

		break;
	case 'actions':
		form_actions();

		break;
	case 'edit':
		include_once("./include/top_header.php");

		mactrack_macw_edit();

		include_once("./include/bottom_footer.php");
		break;
	default:
		include_once("./include/top_header.php");

		mactrack_macw();

		include_once("./include/bottom_footer.php");

		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if ((isset($_POST["save_component_macw"])) && (empty($_POST["add_dq_y"]))) {
		$mac_id = api_mactrack_macw_save($_POST["mac_id"], $_POST["mac_address"], $_POST["name"], $_POST["ticket_number"], $_POST["description"],
			$_POST["notify_schedule"], $_POST["email_addresses"]);

		if ((is_error_message()) || ($_POST["mac_id"] != $_POST["_mac_id"])) {
			header("Location: mactrack_macwatch.php?action=edit&id=" . (empty($mac_id) ? $_POST["mac_id"] : $mac_id));
		}else{
			header("Location: mactrack_macwatch.php");
		}
	}
}

/* ------------------------
    The "actions" function
   ------------------------ */

function form_actions() {
	global $colors, $config, $macw_actions, $fields_mactrack_macw_edit;

	/* if we are to save this form, instead of display it */
	if (isset($_POST["selected_items"])) {
		$selected_items = unserialize(stripslashes($_POST["selected_items"]));

		if ($_POST["drp_action"] == "1") { /* delete */
			for ($i=0; $i<count($selected_items); $i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				api_mactrack_macw_remove($selected_items[$i]);
			}
		}

		header("Location: mactrack_macwatch.php");
		exit;
	}

	/* setup some variables */
	$macw_list = ""; $i = 0;

	/* loop through each of the mac watch items selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (ereg("^chk_([0-9]+)$", $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$macw_info = db_fetch_cell("SELECT name FROM mac_track_macwatch WHERE mac_id=" . $matches[1]);
			$macw_list .= "<li>" . $macw_info . "<br>";
			$macw_array[$i] = $matches[1];
		}

		$i++;
	}

	include_once("./include/top_header.php");

	html_start_box("<strong>" . $macw_actions{$_POST["drp_action"]} . "</strong>", "60%", $colors["header_panel"], "3", "center", "");

	print "<form action='mactrack_macwatch.php' method='post'>\n";

	if ($_POST["drp_action"] == "1") { /* delete */
		print "	<tr>
				<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>Are you sure you want to delete the following watched Mac's?</p>
					<p>$macw_list</p>";
					print "</td></tr>
				</td>
			</tr>\n
			";
	}

	if (!isset($macw_array)) {
		print "<tr><td bgcolor='#" . $colors["form_alternate1"]. "'><span class='textError'>You must select at least one watched Mac to delete.</span></td></tr>\n";
		$save_html = "";
	}else{
		$save_html = "<input type='submit' name='save_x' value='Yes'>";
	}

	print "	<tr>
			<td colspan='2' align='right' bgcolor='#eaeaea'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='selected_items' value='" . (isset($macw_array) ? serialize($macw_array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . $_POST["drp_action"] . "'>" . (strlen($save_html) ? "
				<input type='submit' name='cancel_x' value='No'>
				$save_html" : "<input type='submit' name='cancel_x' value='Return'>") . "
			</td>
		</tr>
		";

	html_end_box();

	include_once("./include/bottom_footer.php");
}

function api_mactrack_macw_save($mac_id, $mac_address, $name, $ticket_number, $description, $notify_schedule, $email_addresses) {
	$save["mac_id"] = $mac_id;
	$save["mac_address"] = form_input_validate($mac_address, $_POST["mac_address"], "", false, 3);
	$save["name"] = form_input_validate($name, $_POST["name"], "", false, 3);
	$save["ticket_number"] = form_input_validate($ticket_number, $_POST["ticket_number"], "", false, 3);
	$save["description"] = form_input_validate($description, $_POST["description"], "", false, 3);
	$save["notify_schedule"] = form_input_validate($notify_schedule, $_POST["notify_schedule"], "", false, 3);
	$save["email_addresses"] = form_input_validate($email_addresses, $_POST["email_addresses"], "", false, 3);

	$mac_id = 0;
	if (!is_error_message()) {
		$mac_id = sql_save($save, "mac_track_macwatch", "mac_address", false);

		if ($mac_id) {
			raise_message(1);
		}else{
			raise_message(2);
		}
	}

	return $mac_id;
}

function api_mactrack_macw_remove($mac_id) {
	db_execute("DELETE FROM mac_track_macwatch WHERE mac_id='" . $mac_id . "'");
}

/* ---------------------
    MacWatch Functions
   --------------------- */

function mactrack_check_changed($request, $session) {
	if ((isset($_REQUEST[$request])) && (isset($_SESSION[$session]))) {
		if ($_REQUEST[$request] != $_SESSION[$session]) {
			return 1;
		}
	}
}

function mactrack_macw_remove() {
	global $config;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("mac_address"));
	/* ==================================================== */

	if ((read_config_option("remove_verification") == "on") && (!isset($_GET["confirm"]))) {
		include("./include/top_header.php");
		form_confirm("Are You Sure?", "Are you sure you want to delete the watched Mac Address <strong>'" . db_fetch_cell("SELECT name FROM mac_track_macwatch WHERE mac_id" . $_GET["mac_id"]) . "'</strong>?", "mactrack_macwatch.php", "mactrack_macwatch.php?action=remove&mac_id=" . $_GET["mac_id"]);
		include("./include/bottom_footer.php");
		exit;
	}

	if ((read_config_option("remove_verification") == "") || (isset($_GET["confirm"]))) {
		api_mactrack_macw_remove($_GET["mac_id"]);
	}
}

function mactrack_macw_get_macw_records(&$sql_where, $apply_limits = TRUE) {
	$sql_where = "";

	/* form the 'where' clause for our main sql query */
	if (strlen($_REQUEST["filter"])) {
		$sql_where = "WHERE (mac_address LIKE '%%" . $_REQUEST["filter"] . "%%' OR " .
			"name LIKE '%%" . $_REQUEST["filter"] . "%%' OR " .
			"ticket_number LIKE '%%" . $_REQUEST["filter"] . "%%' OR " .
			"description LIKE '%%" . $_REQUEST["filter"] . "%%')";
	}

	$query_string = "SELECT *
		FROM mac_track_macwatch
		$sql_where
		ORDER BY " . $_REQUEST["sort_column"] . " " . $_REQUEST["sort_direction"];

	if ($apply_limits) {
		$query_string .= " LIMIT " . (read_config_option("num_rows_mactrack")*($_REQUEST["page"]-1)) . "," . read_config_option("num_rows_mactrack");
	}

	return db_fetch_assoc($query_string);
}

function mactrack_macw_edit() {
	global $colors, $fields_mactrack_macw_edit;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("mac_id"));
	/* ==================================================== */

	display_output_messages();

	if (!empty($_GET["mac_id"])) {
		$mac_record = db_fetch_row("SELECT * FROM mac_track_macwatch WHERE mac_id=" . $_GET["mac_id"]);
		$header_label = "[edit: " . $mac_record["name"] . "]";
	}else{
		$header_label = "[new]";
	}

	html_start_box("<strong>Mac Track MacWatch</strong> $header_label", "100%", $colors["header"], "3", "center", "");

	draw_edit_form(array(
		"config" => array("form_name" => "chk"),
		"fields" => inject_form_variables($fields_mactrack_macw_edit, (isset($mac_record) ? $mac_record : array()))
		));

	html_end_box();

	form_save_button("mactrack_macwatch.php", "", "mac_address");
}

function mactrack_macw() {
	global $colors, $macw_actions, $config;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("page"));
	input_validate_input_number(get_request_var("mac_id"));
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
	if (isset($_REQUEST["clear_macw_x"])) {
		kill_session_var("sess_mactrack_macw_current_page");
		kill_session_var("sess_mactrack_macw_filter");
		kill_session_var("sess_mactrack_macw_sort_column");
		kill_session_var("sess_mactrack_macw_sort_direction");

		$_REQUEST["page"] = 1;
		unset($_REQUEST["filter"]);
		unset($_REQUEST["sort_column"]);
		unset($_REQUEST["sort_direction"]);
	}else{
		/* if any of the settings changed, reset the page number */
		$changed = 0;
		$changed += mactrack_check_changed("filter", "sess_mactrack_macw_filter");
		$changed += mactrack_check_changed("detail", "sess_mactrack_macw_detail");
		if ($changed) {
			$_REQUEST["page"] = "1";
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page", "sess_mactrack_macw_current_page", "1");
	load_current_session_value("filter", "sess_mactrack_macw_filter", "");
	load_current_session_value("sort_column", "sess_mactrack_macw_sort_column", "name");
	load_current_session_value("sort_direction", "sess_mactrack_macw_sort_direction", "ASC");

	html_start_box("<strong>Mac Track MacWatch Filters</strong>", "100%", $colors["header"], "3", "center", "mactrack_macwatch.php?action=edit");

	include($config['base_path'] . "/plugins/mactrack/html/inc_mactrack_macw_filter_table.php");

	html_end_box();

	html_start_box("", "100%", $colors["header"], "3", "center", "");

	$sql_where = "";

	$macw = mactrack_macw_get_macw_records($sql_where);

	$total_rows = sizeof(db_fetch_assoc("SELECT count(*)
		FROM (mac_track_macwatch
		$sql_where"));

	/* generate page list */
	$url_page_select = str_replace("&page", "?page", get_page_list($_REQUEST["page"], MAX_DISPLAY_PAGES, read_config_option("num_rows_mactrack"), $total_rows, "mactrack_macwatch.php"));

	$nav = "<tr bgcolor='#" . $colors["header"] . "'>
			<td colspan='9'>
				<table width='100%' cellspacing='0' cellpadding='0' border='0'>
					<tr>
						<td align='left' class='textHeaderDark'>
							<strong>&lt;&lt; "; if ($_REQUEST["page"] > 1) { $nav .= "<a class='linkOverDark' href='mactrack_macwatch.php?page=" . ($_REQUEST["page"]-1) . "'>"; } $nav .= "Previous"; if ($_REQUEST["page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
						</td>\n
						<td align='center' class='textHeaderDark'>
							Showing Rows " . ((read_config_option("num_rows_mactrack")*($_REQUEST["page"]-1))+1) . " to " . ((($total_rows < read_config_option("num_rows_mactrack")) || ($total_rows < (read_config_option("num_rows_mactrack")*$_REQUEST["page"]))) ? $total_rows : (read_config_option("num_rows_mactrack")*$_REQUEST["page"])) . " of $total_rows [$url_page_select]
						</td>\n
						<td align='right' class='textHeaderDark'>
							<strong>"; if (($_REQUEST["page"] * read_config_option("num_rows_mactrack")) < $total_rows) { $nav .= "<a class='linkOverDark' href='mactrack_macwatch.php?page=" . ($_REQUEST["page"]+1) . "'>"; } $nav .= "Next"; if (($_REQUEST["page"] * read_config_option("num_rows_mactrack")) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
						</td>\n
					</tr>
				</table>
			</td>
		</tr>\n";

	print $nav;

	$display_text = array(
		"name" => array("Watch<br>Name", "ASC"),
		"mac_address" => array("Mac<br>Address", "ASC"),
		"ticket_number" => array("Ticket<br>Number", "ASC"),
		"" => array("Watch<br>Description", "ASC"),
		"date_first_seen" => array("First<br>Seen", "ASC"),
		"date_last_seen" => array("Last<br>Seen", "ASC"));

	html_header_sort_checkbox($display_text, $_REQUEST["sort_column"], $_REQUEST["sort_direction"]);

	$i = 0;
	if (sizeof($macw) > 0) {
		foreach ($macw as $mac) {
			form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
				?>
				<td width="20%">
					<a class="linkEditMain" href="mactrack_macwatch.php?action=edit&mac_id=<?php print $mac['mac_id'];?>"><?php print eregi_replace("(" . preg_quote($_REQUEST["filter"]) . ")", "<span style='background-color: #F8D93D;'>\\1</span>", $mac["name"]);?></a>
				</td>
				<td width="10%"><?php print eregi_replace("(" . preg_quote($_REQUEST["filter"]) . ")", "<span style='background-color: #F8D93D;'>\\1</span>", $mac["mac_address"]);?></td>
				<td width="10%"><?php print eregi_replace("(" . preg_quote($_REQUEST["filter"]) . ")", "<span style='background-color: #F8D93D;'>\\1</span>", $mac["ticket_number"]);?></td>
				<td width="40%"><?php print eregi_replace("(" . preg_quote($_REQUEST["filter"]) . ")", "<span style='background-color: #F8D93D;'>\\1</span>", $mac["description"]);?></td>
				<td width="10%"><?php print ($mac["date_first_seen"] == "0000-00-00 00:00:00" ? "N/A" : $mac["date_first_seen"]);?></td>
				<td width="10%"><?php print ($mac["date_last_seen"] == "0000-00-00 00:00:00" ? "N/A" : $mac["date_last_seen"]);?></td>

				<td style="<?php print get_checkbox_style();?>" width="1%" align="right">
					<input type='checkbox' style='margin: 0px;' name='chk_<?php print $mac["mac_id"];?>' title="<?php print $mac["name"];?>">
				</td>
			</tr>
			<?php
		}

		/* put the nav bar on the bottom as well */
		print $nav;
	}else{
		print "<tr><td colspan='10'><em>No Mac Track Watched Macs</em></td></tr>";
	}
	html_end_box(false);

	/* draw the dropdown containing a list of available actions for this form */
	mactrack_draw_actions_dropdown($macw_actions);
}

?>