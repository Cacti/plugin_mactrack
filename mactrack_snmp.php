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

chdir('../../');
include("./include/auth.php");
include_once("./lib/snmp.php");

define("MAX_DISPLAY_PAGES", 21);

$mactrack_snmp_actions = array(
	1 => "Delete",
	2 => "Duplicate",
);

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

/* correct for a cancel button */
if (isset($_REQUEST["cancel_x"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
	case 'save':
		form_mactrack_snmp_save();

		break;
	case 'actions':
		form_mactrack_snmp_actions();

		break;
	case 'item_movedown':
		mactrack_snmp_item_movedown();

		header("Location: mactrack_snmp.php?action=edit&id=" . $_REQUEST["id"]);
		break;
	case 'item_moveup':
		mactrack_snmp_item_moveup();

		header("Location: mactrack_snmp.php?action=edit&id=" . $_REQUEST["id"]);
		break;
	case 'item_remove':
		mactrack_snmp_item_remove();

		header("Location: mactrack_snmp.php?action=edit&id=" . $_REQUEST["id"]);
		break;
	case 'item_edit':
		top_header();

		mactrack_snmp_item_edit();

		bottom_footer();
		break;
	case 'edit':
		top_header();

		mactrack_snmp_edit();

		bottom_footer();
		break;
	default:
		top_header();
		print "<script type='text/javascript' src='" . $config["url_path"] . "plugins/mactrack/mactrack.js'></script>";

		mactrack_snmp();

		bottom_footer();
		break;
}

function form_mactrack_snmp_save() {

	if (isset($_POST["save_component_mactrack_snmp"])) {
		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post("id"));
		/* ==================================================== */

		$save["id"]     = $_POST["id"];
		$save["name"]   = sql_sanitize(form_input_validate($_POST["name"], "name", "", false, 3));

		if (!is_error_message()) {
			$id = sql_save($save, "mac_track_snmp");
			if ($id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}

		header("Location: mactrack_snmp.php?action=edit&id=" . (empty($id) ? $_POST["id"] : $id));

	}elseif (isset($_POST["save_component_mactrack_snmp_item"])) {
		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post("item_id"));
		input_validate_input_number(get_request_var_post("id"));
		/* ==================================================== */

		$save = array();
		$save["id"]						= form_input_validate($_POST["item_id"], "", "^[0-9]+$", false, 3);
		$save["snmp_id"] 				= form_input_validate($_POST["id"], "snmp_id", "^[0-9]+$", false, 3);
		$save["sequence"] 				= form_input_validate($_POST["sequence"], "sequence", "^[0-9]+$", false, 3);
		$save["snmp_readstring"] 		= form_input_validate($_POST["snmp_readstring"], "snmp_readstring", "", false, 3);
		$save["snmp_version"] 			= form_input_validate($_POST["snmp_version"], "snmp_version", "", false, 3);
		$save["snmp_username"]			= form_input_validate($_POST["snmp_username"], "snmp_username", "", true, 3);
		$save["snmp_password"]			= form_input_validate($_POST["snmp_password"], "snmp_password", "", true, 3);
		$save["snmp_auth_protocol"]		= form_input_validate($_POST["snmp_auth_protocol"], "snmp_auth_protocol", "", true, 3);
		$save["snmp_priv_passphrase"]	= form_input_validate($_POST["snmp_priv_passphrase"], "snmp_priv_passphrase", "", true, 3);
		$save["snmp_priv_protocol"]		= form_input_validate($_POST["snmp_priv_protocol"], "snmp_priv_protocol", "", true, 3);
		$save["snmp_context"]			= form_input_validate($_POST["snmp_context"], "snmp_context", "", true, 3);
		$save["snmp_port"]				= form_input_validate($_POST["snmp_port"], "snmp_port", "^[0-9]+$", false, 3);
		$save["snmp_timeout"]			= form_input_validate($_POST["snmp_timeout"], "snmp_timeout", "^[0-9]+$", false, 3);
		$save["snmp_retries"]			= form_input_validate($_POST["snmp_retries"], "snmp_retries", "^[0-9]+$", false, 3);
		$save["max_oids"]				= form_input_validate($_POST["max_oids"], "max_oids", "^[0-9]+$", false, 3);

		if (!is_error_message()) {
			$item_id = sql_save($save, "mac_track_snmp_items");

			if ($item_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}

		if (is_error_message()) {
			header("Location: mactrack_snmp.php?action=item_edit&id=" . $_POST["id"] . "&item_id=" . (empty($item_id) ? $_POST["id"] : $item_id));
		}else{
			header("Location: mactrack_snmp.php?action=edit&id=" . $_POST["id"]);
		}
	} else {
		raise_message(2);
		header("Location: mactrack_snmp.php");
	}
}


/* ------------------------
 The "actions" function
 ------------------------ */
function form_mactrack_snmp_actions() {
	global $config, $mactrack_snmp_actions;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_post('drp_action'));
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset($_POST["selected_items"])) {
		$selected_items = unserialize(stripslashes($_POST["selected_items"]));

		if ($_POST["drp_action"] == '1') { /* delete */
			db_execute("delete from mac_track_snmp where " . array_to_sql_or($selected_items, "id"));
			db_execute("delete from mac_track_snmp_items where " . str_replace("id", "snmp_id", array_to_sql_or($selected_items, "id")));
		}elseif ($_POST["drp_action"] == '2') { /* duplicate */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */
				duplicate_mactrack($selected_items[$i], $_POST["name_format"]);
			}
		}

		header("Location: mactrack_snmp.php");
		exit;
	}

	/* setup some variables */
	$snmp_groups = ""; $i = 0;
	/* loop through each of the graphs selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (ereg("^chk_([0-9]+)$", $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */
			$snmp_groups .= "<li>" . db_fetch_cell("select name from mac_track_snmp where id=" . $matches[1]) . "<br>";
			$mactrack_array[$i] = $matches[1];
			$i++;
		}
	}

	general_header();

	display_output_messages();

	?>
	<script type="text/javascript">
	<!--
	function goTo(location) {
		document.location = location;
	}
	-->
	</script>
	<?php

	print '<form name="mactrack" action="mactrack_snmp.php" method="post">';

	html_start_box("<strong>" . $mactrack_snmp_actions{$_POST["drp_action"]} . "</strong>", "60%", "", "3", "center", "");

	if (!isset($mactrack_array)) {
		print "<tr><td class='even'><span class='textError'>You must select at least one SNMP Option.</span></td></tr>\n";
		$save_html = "";
	}else{
		$save_html = "<input type='submit' value='Yes' name='save'>";

		if ($_POST["drp_action"] == '1') { /* delete */
			print "	<tr>
				<td class='textArea'>
					<p>Are you sure you want to delete the following SNMP Options?</p>
					<p><ul>$snmp_groups</ul></p>
				</td>
			</tr>\n
			";
		}elseif ($_POST["drp_action"] == '2') { /* duplicate */
			print "	<tr>
				<td class='textArea'>
					<p>When you click save, the following SNMP Options will be duplicated. You can
					optionally change the title format for the new SNMP Options.</p>
					<p><ul>$snmp_groups</ul></p>
					<p><strong>Name Format:</strong><br>"; form_text_box("name_format", "<name> (1)", "", "255", "30", "text"); print "</p>
				</td>
			</tr>\n
			";
		}
	}

	print "	<tr>
		<td align='right' class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($mactrack_array) ? serialize($mactrack_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . $_POST["drp_action"] . "'>
			<input type='button' onClick='goTo(\"" . "mactrack_snmp.php" . "\")' value='" . ($save_html == '' ? 'Return':'No') . "' name='cancel'>
			$save_html
		</td>
	</tr>";

	html_end_box();

	bottom_footer();
}

/* --------------------------
 mactrack Item Functions
 -------------------------- */
function mactrack_snmp_item_movedown() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("item_id"));
	input_validate_input_number(get_request_var_request("id"));
	/* ==================================================== */
	move_item_down("mac_track_snmp_items", get_request_var_request("item_id"), "snmp_id=" . get_request_var_request("id"));
}

function mactrack_snmp_item_moveup() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("item_id"));
	input_validate_input_number(get_request_var_request("id"));
	/* ==================================================== */
	move_item_up("mac_track_snmp_items", get_request_var_request("item_id"), "snmp_id=" . get_request_var_request("id"));
}

function mactrack_snmp_item_remove() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("item_id"));
	/* ==================================================== */
	db_execute("delete from mac_track_snmp_items where id=" . get_request_var_request("item_id"));
}

function mactrack_snmp_item_edit() {
	global $config;
	global $fields_mactrack_snmp_item_edit;
	include_once($config["base_path"]."/plugins/mactrack/lib/mactrack_functions.php");
	#print "<pre>Post: "; print_r($_POST); print "Get: "; print_r($_REQUEST); print "Request: ";  print_r($_REQUEST);  /*print "Session: ";  print_r($_SESSION);*/ print "</pre>";

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("id"));
	input_validate_input_number(get_request_var_request("item_id"));
	/* ==================================================== */

	# fetch the current mactrack snmp record
	$snmp_option = db_fetch_row("SELECT * " .
			"FROM mac_track_snmp " .
			"WHERE id=" . get_request_var_request("id"));

	# if an existing item was requested, fetch data for it
	if (get_request_var_request("item_id", '') !== '') {
		$mactrack_snmp_item = db_fetch_row("SELECT * " .
					"FROM mac_track_snmp_items" .
					" WHERE id=" . get_request_var_request("item_id"));
		$header_label = "[edit SNMP Options: " . $snmp_option['name'] . "]";
	}else{
		$header_label = "[new SNMP Options: " . $snmp_option['name'] . "]";
		$mactrack_snmp_item = array();
		$mactrack_snmp_item["snmp_id"] = get_request_var_request("id");
		$mactrack_snmp_item["sequence"] = get_sequence('', 'sequence', 'mac_track_snmp_items', 'snmp_id=' . get_request_var_request("id"));
	}

	print "<form method='post' action='" .  basename($_SERVER["PHP_SELF"]) . "' name='mactrack_item_edit'>\n";
	# ready for displaying the fields

	html_start_box("<strong>SNMP Options</strong> $header_label", "100%", "", "3", "center", "");

	draw_edit_form(array(
		"config" => array("no_form_tag" => true),
		"fields" => inject_form_variables($fields_mactrack_snmp_item_edit, (isset($mactrack_snmp_item) ? $mactrack_snmp_item : array()))
	));

	html_end_box();
	form_hidden_box("item_id", (isset($_REQUEST["item_id"]) ? $_REQUEST["item_id"] : "0"), "");
	form_hidden_box("id", (isset($mactrack_snmp_item["snmp_id"]) ? $mactrack_snmp_item["snmp_id"] : "0"), "");
	form_hidden_box("save_component_mactrack_snmp_item", "1", "");

	mactrack_save_button(htmlspecialchars("mactrack_snmp.php?action=edit&id=" . get_request_var_request("id")));

	print "<script type='text/javascript' src='" . CACTI_URL_PATH . "plugins/mactrack/mactrack_snmp.js'></script>";
}

/* ---------------------
 mactrack Functions
 --------------------- */
function mactrack_snmp_remove() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("id"));
	/* ==================================================== */

	if ((read_config_option("deletion_verification") == "on") && (!isset($_REQUEST["confirm"]))) {
		general_header();
		form_confirm("Are You Sure?", "Are you sure you want to delete the SNMP Option Set(s) <strong>'" . db_fetch_cell("select name from mactrack where id=" . $_REQUEST["id"]) . "'</strong>?", "mactrack_snmp.php", "mactrack_snmp.php?action=remove&id=" . $_REQUEST["id"]);
		bottom_footer();
		exit;
	}

	if ((read_config_option("deletion_verification") == "") || (isset($_REQUEST["confirm"]))) {
		db_execute("DELETE FROM mac_track_snmp_items WHERE snmp_id=" . $_REQUEST["id"]);
		db_execute("DELETE FROM mac_track_snmp WHERE id=" . $_REQUEST["id"]);
	}
}

function mactrack_snmp_edit() {
	global $config, $fields_mactrack_snmp_edit;
	#print "<pre>Post: "; print_r($_POST); print "Get: "; print_r($_REQUEST); print "Request: ";  print_r($_REQUEST);  print "Session: ";  print_r($_SESSION); print "</pre>";
	include_once($config["base_path"]."/plugins/mactrack/lib/mactrack_functions.php");

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("id"));
	input_validate_input_number(get_request_var_request("page"));
	/* ==================================================== */

	/* clean up rule name */
	if (isset($_REQUEST["name"])) {
		$_REQUEST["name"] = sanitize_search_string(get_request_var_request("name"));
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page", "sess_mactrack_edit_current_page", "1");
	load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));

	/* display the mactrack snmp option set */
	$snmp_group = array();
	if (!empty($_REQUEST["id"])) {
		$snmp_group = db_fetch_row("SELECT * FROM mac_track_snmp where id=" . $_REQUEST["id"]);
		# setup header
		$header_label = "[edit: " . $snmp_group["name"] . "]";
	}else{
		$header_label = "[new]";
	}

	print '<form name="mactrack_snmp_group" action="mactrack_snmp.php" method="post">';

	html_start_box("<strong>SNMP Option Set</strong> $header_label", "100%", "", "3", "center", "");

	draw_edit_form(array(
			"config" => array("no_form_tag" => true),
			"fields" => inject_form_variables($fields_mactrack_snmp_edit, $snmp_group)
	));

	html_end_box();
	form_hidden_box("id", (isset($_REQUEST["id"]) ? $_REQUEST["id"] : "0"), "");
	form_hidden_box("save_component_mactrack_snmp", "1", "");

	if (!empty($_REQUEST["id"])) {
		$items = db_fetch_assoc("SELECT * " .
		"FROM mac_track_snmp_items " .
		"WHERE snmp_id=" . $_REQUEST["id"] .
		" ORDER BY sequence");

		html_start_box("<strong>Mactrack SNMP Options</strong>", "100%", "", "3", "center", "mactrack_snmp.php?action=item_edit&id=" . $_REQUEST["id"]);

		print "<tr class='tableHeader'>";
		DrawMatrixHeaderItem("Item","",1);
		DrawMatrixHeaderItem("Version","",1);
		DrawMatrixHeaderItem("Community","",1);
		DrawMatrixHeaderItem("Port","",1);
		DrawMatrixHeaderItem("Timeout","",1);
		DrawMatrixHeaderItem("Retries","",1);
		DrawMatrixHeaderItem("Max OIDs","",1);
		DrawMatrixHeaderItem("Username","",1);
		DrawMatrixHeaderItem("Password","",1);
		DrawMatrixHeaderItem("Auth Proto","",1);
		DrawMatrixHeaderItem("Priv Passphrase","",1);
		DrawMatrixHeaderItem("Priv Proto","",1);
		DrawMatrixHeaderItem("Context","",1);
		DrawMatrixHeaderItem("&nbsp;","",2);
		print "</tr>";

		if (sizeof($items) > 0) {
			foreach ($items as $item) {
				form_alternate_row();
				$form_data = '<td><a class="linkEditMain" href="' . htmlspecialchars("mactrack_snmp.php?action=item_edit&item_id=" . $item["id"] . "&id=" . $item["snmp_id"]) . '">Item#' . $i . '</a></td>';
				#$form_data .= '<td>' . 	$item["sequence"] . '</td>';
				$form_data .= '<td>' . 	$item["snmp_version"] . '</td>';
				$form_data .= '<td>' . 	($item["snmp_version"] == 3 ? "none" : $item["snmp_readstring"]) . '</td>';
				$form_data .= '<td>' . 	$item["snmp_port"] . '</td>';
				$form_data .= '<td>' . 	$item["snmp_timeout"] . '</td>';
				$form_data .= '<td>' . 	$item["snmp_retries"] . '</td>';
				$form_data .= '<td>' . 	$item["max_oids"] . '</td>';
				$form_data .= '<td>' . 	($item["snmp_version"] == 3 ? $item["snmp_username"] : "none") . '</td>';
				$form_data .= '<td>' . 	($item["snmp_version"] == 3 ? $item["snmp_password"] : "none") . '</td>';
				$form_data .= '<td>' . 	($item["snmp_version"] == 3 ? $item["snmp_auth_protocol"] : "none") . '</td>';
				$form_data .= '<td>' . 	($item["snmp_version"] == 3 ? $item["snmp_priv_passphrase"] : "none") . '</td>';
				$form_data .= '<td>' . 	($item["snmp_version"] == 3 ? $item["snmp_priv_protocol"] : "none") . '</td>';
				$form_data .= '<td>' . 	($item["snmp_version"] == 3 ? $item["snmp_context"] : "none") . '</td>';
				$form_data .= '<td>' .
							'<a href="' . htmlspecialchars('mactrack_snmp.php?action=item_movedown&item_id=' . $item["id"] . '&id=' . $item["snmp_id"]) .
							'"><img src="../../images/move_down.gif" border="0" alt="Move Down"></a>' .
							'<a	href="' . htmlspecialchars('mactrack_snmp.php?action=item_moveup&item_id=' . $item["id"] .	'&id=' . $item["snmp_id"]) .
							'"><img src="../../images/move_up.gif" border="0" alt="Move Up"></a>' . '</td>';
				$form_data .= '<td align="right"><a href="' . htmlspecialchars('mactrack_snmp.php?action=item_remove&item_id=' . $item["id"] .	'&id=' . $item["snmp_id"]) .
							'"><img src="../../images/delete_icon.gif" border="0" width="10" height="10" alt="Delete"></a>' . '</td></tr>';
				print $form_data;
			}
		} else {
			print "<tr><td><em>No SNMP Items</em></td></tr>\n";
		}
		html_end_box();
	}
	mactrack_save_button("mactrack_snmp.php");
}

function mactrack_snmp() {
	global $config, $item_rows;
	global $mactrack_snmp_actions;
	#print "<pre>Post: "; print_r($_POST); print "Get: "; print_r($_REQUEST); print "Request: ";  print_r($_REQUEST);  print "Session: ";  print_r($_SESSION); print "</pre>";
	#include_once($config["base_path"]."/plugins/mactrack/mactrack_functions.php");

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("page"));
	input_validate_input_number(get_request_var_request("rows"));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var_request("filter"));
	}

	/* clean up sort_column string */
	if (isset($_REQUEST["sort_column"])) {
		$_REQUEST["sort_column"] = sanitize_search_string(get_request_var_request("sort_column"));
	}

	/* clean up sort_direction string */
	if (isset($_REQUEST["sort_direction"])) {
		$_REQUEST["sort_direction"] = sanitize_search_string(get_request_var_request("sort_direction"));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST["clear_x"])) {
		kill_session_var("sess_mactrack_current_page");
		kill_session_var("sess_mactrack_filter");
		kill_session_var("sess_mactrack_sort_column");
		kill_session_var("sess_mactrack_sort_direction");
		kill_session_var("sess_default_rows");

		unset($_REQUEST["page"]);
		unset($_REQUEST["filter"]);
		unset($_REQUEST["sort_column"]);
		unset($_REQUEST["sort_direction"]);
		unset($_REQUEST["rows"]);

	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page", "sess_mactrack_current_page", "1");
	load_current_session_value("filter", "sess_mactrack_filter", "");
	load_current_session_value("sort_column", "sess_mactrack_sort_column", "name");
	load_current_session_value("sort_direction", "sess_mactrack_sort_direction", "ASC");
	load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));

	/* if the number of rows is -1, set it to the default */
	if ($_REQUEST["rows"] == -1) {
		$_REQUEST["rows"] = read_config_option("num_rows_table");
	}

	print ('<form name="mactrack_snmp" action="mactrack_snmp.php" method="get">');

	html_start_box("<strong>Mactrack SNMP Options</strong>", "100%", "", "3", "center", "mactrack_snmp.php?action=edit");

	$filter_html = '<tr class="even">
					<td>
					<table cellpadding="2" cellspacing="0">
						<tr>
							<td width="55">
								Search:
							</td>
							<td>
								<input type="text" name="filter" size="20" value="' . get_request_var_request("filter") . '">
							</td>
							<td>
								Rows:
							</td>
							<td>
								<select name="rows" onChange="applyViewmactrackFilterChange(document.mactrack_snmp)">
									<option value="-1"';

	if (get_request_var_request("rows") == "-1") {
		$filter_html .= 'selected';
	}

	$filter_html .= '>Default</option>';

	if (sizeof($item_rows) > 0) {
		foreach ($item_rows as $key => $value) {
			$filter_html .= "<option value='" . $key . "'";
			if (get_request_var_request("rows") == $key) {
				$filter_html .= " selected";
			}
			$filter_html .= ">" . $value . "</option>\n";
		}
	}
	$filter_html .= '					</select>
							</td>
							<td>
								<input type="submit" value="Go" name="go">
							</td>
							<td>
								<input type="submit" value="Clear" name="clear_x">
							</td>
						</tr>
					</table>
					</td>
					<td><input type="hidden" name="page" value="1"></td>
				</tr>';

	print $filter_html;

	html_end_box();

	print "</form>\n";

	/* form the 'where' clause for our main sql query */
	if (strlen(get_request_var_request("filter"))) {
		$sql_where = "WHERE (mac_track_snmp.name LIKE '%%" . get_request_var_request("filter") . "%%')";
	}else{
		$sql_where = "";
	}

	html_start_box("", "100%", "", "3", "center", "");

	$total_rows = db_fetch_cell("SELECT
		COUNT(mac_track_snmp.id)
		FROM mac_track_snmp
		$sql_where");

	$snmp_groups = db_fetch_assoc("SELECT *
		FROM mac_track_snmp
		$sql_where
		ORDER BY " . get_request_var_request("sort_column") . " " . get_request_var_request("sort_direction") . "
		LIMIT " . (get_request_var_request("rows")*(get_request_var_request("page")-1)) . "," . get_request_var_request("rows"));

	$nav = html_nav_bar("mactrack_snmp.php?filter=" . $_REQUEST["filter"], MAX_DISPLAY_PAGES, get_request_var_request("page"), get_request_var_request("rows"), $total_rows, 12, 'SNMP Settings');

	print $nav;

	$display_text = array(
		"name" => array("Title of SNMP Option Set", "ASC"),
	);

	html_header_sort_checkbox($display_text, get_request_var_request("sort_column"), get_request_var_request("sort_direction"));

	if (sizeof($snmp_groups) > 0) {
		foreach ($snmp_groups as $snmp_group) {
			form_alternate_row('line' . $snmp_group["id"], true);

			form_selectable_cell("<a style='white-space:nowrap;' class='linkEditMain' href='" . htmlspecialchars("mactrack_snmp.php?action=edit&id=" . $snmp_group["id"] . "&page=1 ' title='" . $snmp_group["name"]) . "'>" . ((get_request_var_request("filter") != "") ? preg_replace("/(" . preg_quote(get_request_var_request("filter")) . ")/i", "<span class='filteredValue'>\\1</span>", title_trim($snmp_group["name"], read_config_option("max_title_graph"))) : title_trim($snmp_group["name"], read_config_option("max_title_graph"))) . "</a>", $snmp_group["id"]);
			form_checkbox_cell($snmp_group["name"], $snmp_group["id"]);

			form_end_row();
		}
	}else{
		print "<tr><td><em>No SNMP Option Sets</em></td></tr>\n";
	}
	print $nav;

	html_end_box(false);

	/* draw the dropdown containing a list of available actions for this form */
	mactrack_actions_dropdown($mactrack_snmp_actions);

	print "</form>\n";
	?>
<script type="text/javascript">
	<!--
	function applyViewmactrackFilterChange(objForm) {
		strURL = 'mactrack_snmp.php?rows=' + objForm.rows.value;
		strURL = strURL + '&filter=' + objForm.filter.value;
		document.location = strURL;
	}
	-->
	</script>
	<?php
}

function mactrack_actions_dropdown($actions_array) {
	global $config;

	?>
<table align='center' width='100%'>
	<tr>
		<td width='1' valign='top'><img
			src='<?php echo $config['url_path']; ?>images/arrow.gif' alt=''
			align='middle'>&nbsp;</td>
		<td align='right'>Choose an action: <?php form_dropdown("drp_action",$actions_array,"","","1","","");?>
		</td>
		<td width='1' align='right'><input type='submit' name='go' value='Go'>
		</td>
	</tr>
</table>

<input type='hidden' name='action'
	value='actions'>
	<?php
}

?>
