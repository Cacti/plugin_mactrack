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

$guest_account = true;
chdir("../../");
include("./include/auth.php");
include("./lib/html_tree.php");

if (file_exists("./lib/timespan_settings.php")) {
	include("./lib/timespan_settings.php");
}else{
	include("./include/html/inc_timespan_settings.php");
}

if (!isset($_REQUEST["action"])) $_REQUEST["action"] = "";

include("./plugins/mactrack/general_header.php");
mactrack_view_graphs();
include_once("./include/bottom_footer.php");

function mactrack_view_graphs() {
	global $current_user, $colors, $config;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("rra_id"));
	input_validate_input_number(get_request_var("host"));
	input_validate_input_regex(get_request_var_request('graph_list'), "^([\,0-9]+)$");
	input_validate_input_regex(get_request_var_request('graph_add'), "^([\,0-9]+)$");
	input_validate_input_regex(get_request_var_request('graph_remove'), "^([\,0-9]+)$");
	/* ==================================================== */

	define("ROWS_PER_PAGE", read_graph_config_option("preview_graphs_per_page"));

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("graph_template_id"));
	input_validate_input_number(get_request_var_request("page"));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var_request("filter"));
	}

	$sql_or = ""; $sql_where = ""; $sql_join = "";

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST["clear_x"])) {
		kill_session_var("sess_mactrack_graph_current_page");
		kill_session_var("sess_mactrack_graph_filter");
		kill_session_var("sess_mactrack_graph_host");
		kill_session_var("sess_mactrack_graph_graph_template");

		unset($_REQUEST["page"]);
		unset($_REQUEST["filter"]);
		unset($_REQUEST["host"]);
		unset($_REQUEST["graph_template_id"]);
		unset($_REQUEST["graph_list"]);
		unset($_REQUEST["graph_add"]);
		unset($_REQUEST["graph_remove"]);
	}

	/* reset the page counter to '1' if a search in initiated */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["page"] = "1";
	}

	load_current_session_value("graph_template_id", "sess_mactrack_graph_graph_template", "0");
	load_current_session_value("host", "sess_mactrack_graph_host", "0");
	load_current_session_value("filter", "sess_mactrack_graph_filter", "");
	load_current_session_value("page", "sess_mactrack_graph_current_page", "1");

	/* graph permissions */
	if (read_config_option("auth_method") != 0) {
		$sql_where = "WHERE " . get_graph_permissions_sql($current_user["policy_graphs"], $current_user["policy_hosts"], $current_user["policy_graph_templates"]);

		$sql_join = "LEFT JOIN host ON (host.id=graph_local.host_id)
			LEFT JOIN graph_templates
			ON (graph_templates.id=graph_local.graph_template_id)
			LEFT JOIN user_auth_perms
			ON ((graph_templates_graph.local_graph_id=user_auth_perms.item_id
			AND user_auth_perms.type=1
			AND user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ")
			OR (host.id=user_auth_perms.item_id AND user_auth_perms.type=3 AND user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ")
			OR (graph_templates.id=user_auth_perms.item_id AND user_auth_perms.type=4 AND user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . "))";
	}else{
		$sql_where = "";
		$sql_join = "";
	}

	/* the user select a bunch of graphs of the 'list' view and wants them dsplayed here */
	if (isset($_REQUEST["style"])) {
		if ($_REQUEST["style"] == "selective") {

			/* process selected graphs */
			if (! empty($_REQUEST["graph_list"])) {
				foreach (explode(",",$_REQUEST["graph_list"]) as $item) {
					$graph_list[$item] = 1;
				}
			}else{
				$graph_list = array();
			}
			if (! empty($_REQUEST["graph_add"])) {
				foreach (explode(",",$_REQUEST["graph_add"]) as $item) {
					$graph_list[$item] = 1;
				}
			}
			/* remove items */
			if (! empty($_REQUEST["graph_remove"])) {
				foreach (explode(",",$_REQUEST["graph_remove"]) as $item) {
					unset($graph_list[$item]);
				}
			}

			$i = 0;
			foreach ($graph_list as $item => $value) {
				$graph_array[$i] = $item;
				$i++;
			}

			if ((isset($graph_array)) && (sizeof($graph_array) > 0)) {
				/* build sql string including each graph the user checked */
				$sql_or = "AND " . array_to_sql_or($graph_array, "graph_templates_graph.local_graph_id");

				/* clear the filter vars so they don't affect our results */
				$_REQUEST["filter"]  = "";

				$set_rra_id = empty($rra_id) ? read_graph_config_option("default_rra_id") : $_REQUEST["rra_id"];
			}
		}
	}

	$sql_base = "FROM (graph_templates_graph,graph_local)
		$sql_join
		$sql_where
		" . (empty($sql_where) ? "WHERE" : "AND") . "   graph_templates_graph.local_graph_id > 0
		AND graph_templates_graph.local_graph_id=graph_local.id
		" . (strlen($_REQUEST["filter"]) ? "AND graph_templates_graph.title_cache like '%%" . $_REQUEST["filter"] . "%%'":"") . "
		" . (empty($_REQUEST["graph_template_id"]) ? "" : " and graph_local.graph_template_id=" . $_REQUEST["graph_template_id"]) . "
		" . (empty($_REQUEST["host"]) ? "" : " and graph_local.host_id=" . $_REQUEST["host"]) . "
		$sql_or";

	$total_rows = count(db_fetch_assoc("SELECT
		graph_templates_graph.local_graph_id
		$sql_base"));

	/* reset the page if you have changed some settings */
	if (ROWS_PER_PAGE * ($_REQUEST["page"]-1) >= $total_rows) {
		$_REQUEST["page"] = "1";
	}

	$graphs = db_fetch_assoc("SELECT
		graph_templates_graph.local_graph_id,
		graph_templates_graph.title_cache
		$sql_base
		GROUP BY graph_templates_graph.local_graph_id
		ORDER BY graph_templates_graph.title_cache
		LIMIT " . (ROWS_PER_PAGE*($_REQUEST["page"]-1)) . "," . ROWS_PER_PAGE);

	?>
	<script type="text/javascript">
	<!--
	function applyGraphPreviewFilterChange(objForm) {
		strURL = '?report=graphs&graph_template_id=' + objForm.graph_template_id.value;
		strURL = strURL + '&host=' + objForm.host.value;
		strURL = strURL + '&filter=' + objForm.filter.value;
		document.location = strURL;
	}
	-->
	</script>
	<?php

	/* include graph view filter selector */
	display_output_messages();
	mactrack_tabs();
	html_start_box("<strong>Network Device Graphs</strong>", "100%", $colors["header"], "1", "center", "");
	mactrack_graph_view_filter();

	/* include time span selector */
	if (read_graph_config_option("timespan_sel") == "on") {
		mactrack_timespan_selector();
	}
	html_end_box();

	/* do some fancy navigation url construction so we don't have to try and rebuild the url string */
	if (ereg("page=[0-9]+",basename($_SERVER["QUERY_STRING"]))) {
		$nav_url = str_replace("page=" . $_REQUEST["page"], "page=<PAGE>", basename($_SERVER["PHP_SELF"]) . "?" . $_SERVER["QUERY_STRING"]);
	}else{
		$nav_url = basename($_SERVER["PHP_SELF"]) . "?" . $_SERVER["QUERY_STRING"] . "&page=<PAGE>";
	}

	$nav_url = ereg_replace("((\?|&)filter=[a-zA-Z0-9]*)", "", $nav_url);

	html_start_box("", "100%", $colors["header"], "3", "center", "");
	mactrack_nav_bar($_REQUEST["page"], ROWS_PER_PAGE, $total_rows, $nav_url);
	if (read_graph_config_option("thumbnail_section_preview") == "on") {
		html_graph_thumbnail_area($graphs, "","graph_start=" . get_current_graph_start() . "&graph_end=" . get_current_graph_end());
	}else{
		html_graph_area($graphs, "", "graph_start=" . get_current_graph_start() . "&graph_end=" . get_current_graph_end());
	}

	if ($total_rows) {
		mactrack_nav_bar($_REQUEST["page"], ROWS_PER_PAGE, $total_rows, $nav_url);
	}
	html_end_box();
}

function mactrack_graph_start_box() {
	print "<table width='100%' cellpadding='3' cellspacing='0' border='0' style='background-color: #f5f5f5; border: 1px solid #bbbbbb;' align='center'>\n";
}

function mactrack_graph_end_box() {
	print "</table>";
}

function mactrack_nav_bar($current_page, $rows_per_page, $total_rows, $nav_url) {
	global $config, $colors;

	if ($total_rows) {
		?>
		<tr bgcolor='#<?php print $colors["header"];?>' class='noprint'>
			<td colspan='<?php print read_graph_config_option("num_columns");?>'>
				<table width='100%' cellspacing='0' cellpadding='3' border='0'>
					<tr>
						<td align='left' class='textHeaderDark'>
							<strong>&lt;&lt; <?php if ($current_page > 1) { print "<a class='linkOverDark' href='" . str_replace("<PAGE>", ($current_page-1), $nav_url) . "'>"; } print "Previous"; if ($current_page > 1) { print "</a>"; } ?></strong>
						</td>
						<td align='center' class='textHeaderDark'>
							Showing Graphs <?php print (($rows_per_page*($current_page-1))+1);?> to <?php print ((($total_rows < $rows_per_page) || ($total_rows < ($rows_per_page*$current_page))) ? $total_rows : ($rows_per_page*$current_page));?> of <?php print $total_rows;?>
						</td>
						<td align='right' class='textHeaderDark'>
							<strong><?php if (($current_page * $rows_per_page) < $total_rows) { print "<a class='linkOverDark' href='" . str_replace("<PAGE>", ($current_page+1), $nav_url) . "'>"; } print "Next"; if (($current_page * $rows_per_page) < $total_rows) { print "</a>"; } ?> &gt;&gt;</strong>
						</td>
					</tr>
				</table>
			</td>
		</tr>
		<?php
	}else{
		?>
		<tr bgcolor='#<?php print $colors["header"];?>' class='noprint'>
			<td colspan='<?php print read_graph_config_option("num_columns");?>'>
				<table width='100%' cellspacing='0' cellpadding='3' border='0'>
					<tr>
						<td align='center' class='textHeaderDark'>
							No Graphs Found
						</td>
					</tr>
				</table>
			</td>
		</tr>
		<?php
	}
}

function mactrack_graph_view_filter() {
	global $config, $colors;

	?>
	<tr bgcolor="<?php print $colors["panel"];?>" class="noprint">
		<form name="form_graph_view" method="post">
		<td class="noprint">
			<table width="100%" cellpadding="0" cellspacing="0">
				<tr class="noprint">
					<td nowrap style='white-space: nowrap;' width="70">
						&nbsp;Host:&nbsp;
					</td>
					<td width="1">
						<select name="host" onChange="applyGraphPreviewFilterChange(document.form_graph_view)">
							<option value="0"<?php if ($_REQUEST["host"] == "0") {?> selected<?php }?>>Any</option>

							<?php
							$hosts = db_fetch_assoc("SELECT host_id, device_name
								FROM mac_track_devices
								WHERE host_id>0
								ORDER BY device_name");

							if (sizeof($hosts)) {
							foreach ($hosts as $host) {
								print "<option value='" . $host["host_id"] . "'"; if ($_REQUEST["host"] == $host["host_id"]) { print " selected"; } print ">" . $host["device_name"] . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td nowrap style='white-space: nowrap;' width="70">
						&nbsp;Template:&nbsp;
					</td>
					<td width="1">
						<select name="graph_template_id" onChange="applyGraphPreviewFilterChange(document.form_graph_view)">
							<option value="0"<?php if ($_REQUEST["graph_template_id"] == "0") {?> selected<?php }?>>Any</option>

							<?php
							if (read_config_option("auth_method") != 0) {
								$graph_templates = db_fetch_assoc("SELECT DISTINCT graph_templates.*
									FROM (graph_templates_graph,graph_local)
									LEFT JOIN host ON (host.id=graph_local.host_id)
									LEFT JOIN graph_templates ON (graph_templates.id=graph_local.graph_template_id)
									LEFT JOIN user_auth_perms ON ((graph_templates_graph.local_graph_id=user_auth_perms.item_id and user_auth_perms.type=1 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ") OR (host.id=user_auth_perms.item_id and user_auth_perms.type=3 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ") OR (graph_templates.id=user_auth_perms.item_id and user_auth_perms.type=4 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . "))
									WHERE graph_templates_graph.local_graph_id=graph_local.id
									" . (empty($sql_where) ? "" : "and $sql_where") . "
									ORDER BY name");
							}else{
								$graph_templates = db_fetch_assoc("SELECT DISTINCT graph_templates.*
									FROM graph_templates
									ORDER BY name");
							}

							if (sizeof($graph_templates) > 0) {
							foreach ($graph_templates as $template) {
								print "<option value='" . $template["id"] . "'"; if ($_REQUEST["graph_template_id"] == $template["id"]) { print " selected"; } print ">" . $template["name"] . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td nowrap style='white-space: nowrap;' width="50">
						&nbsp;Search:&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" size="40" value="<?php print $_REQUEST["filter"];?>">
					</td>
					<td>
						&nbsp;<input type="submit" name="go" value="Go">
						<input type="submit" name="clear_x" value="Clear">
						<input type="button" name="save" value="Save" onclick='saveGraphSettings()'>
						<input type="submit" name="defaults" value="Defaults">
					</td>
				</tr>
			</table>
		</td>
		</form>
	</tr>
	<?php
}

function mactrack_timespan_selector() {
	global $config, $colors, $graph_timespans, $graph_timeshifts;

	?>
	<script type='text/javascript'>
	<!--
	calendar=null;
	function showCalendar(id) {
		var el = document.getElementById(id);
		if (calendar != null) {
			calendar.hide();
		} else {
			var cal = new Calendar(true, null, selected, closeHandler);
			cal.weekNumbers = false;
			cal.showsTime = true;
			cal.time24 = true;
			cal.showsOtherMonths = false;
			calendar = cal;
			cal.setRange(1900, 2070);
			cal.create();
		}

		calendar.setDateFormat('%Y-%m-%d %H:%M');
		calendar.parseDate(el.value);
		calendar.sel = el;
		calendar.showAtElement(el, "Br");

		return false;
	}

	function selected(cal, date) {
		cal.sel.value = date;
	}

	function closeHandler(cal) {
		cal.hide();
		calendar = null;
	}
	-->
	</script>
	<script type="text/javascript">
	<!--
	function applyTimespanFilterChange(objForm) {
		strURL = '?predefined_timespan=' + objForm.predefined_timespan.value;
		strURL = strURL + '&predefined_timeshift=' + objForm.predefined_timeshift.value;
		document.location = strURL;
	}
	-->
	</script>
	<tr bgcolor="<?php print $colors["panel"];?>" class="noprint">
		<form name="form_timespan_selector" method="post">
		<td class="noprint">
			<table width="100%" cellpadding="0" cellspacing="0">
				<tr>
					<td nowrap style='white-space: nowrap;' width='55'>
						&nbsp;Presets:&nbsp;
					</td>
					<td nowrap style='white-space: nowrap;' width='130'>
						<select name='predefined_timespan' onChange="applyTimespanFilterChange(document.form_timespan_selector)">
							<?php
							if ($_SESSION["custom"]) {
								$graph_timespans[GT_CUSTOM] = "Custom";
								$start_val = 0;
								$end_val = sizeof($graph_timespans);
							} else {
								if (isset($graph_timespans[GT_CUSTOM])) {
									asort($graph_timespans);
									array_shift($graph_timespans);
								}
								$start_val = 1;
								$end_val = sizeof($graph_timespans)+1;
							}

							if (sizeof($graph_timespans) > 0) {
								for ($value=$start_val; $value < $end_val; $value++) {
									print "<option value='$value'"; if ($_SESSION["sess_current_timespan"] == $value) { print " selected"; } print ">" . title_trim($graph_timespans[$value], 40) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td nowrap style='white-space: nowrap;' width='30'>
						&nbsp;From:&nbsp;
					</td>
					<td width='155' nowrap style='white-space: nowrap;'>
						<input type='text' name='date1' id='date1' title='Graph Begin Timestamp' size='14' value='<?php print (isset($_SESSION["sess_current_date1"]) ? $_SESSION["sess_current_date1"] : "");?>'>
						&nbsp;<input style='padding-bottom: 4px;' type='image' src='<?php print $config["url_path"];?>images/calendar.gif' alt='Start date selector' title='Start date selector' border='0' align='absmiddle' onclick="return showCalendar('date1');">&nbsp;
					</td>
					<td nowrap style='white-space: nowrap;' width='20'>
						&nbsp;To:&nbsp;
					</td>
					<td width='155' nowrap style='white-space: nowrap;'>
						<input type='text' name='date2' id='date2' title='Graph End Timestamp' size='14' value='<?php print (isset($_SESSION["sess_current_date2"]) ? $_SESSION["sess_current_date2"] : "");?>'>
						&nbsp;<input style='padding-bottom: 4px;' type='image' src='<?php print $config["url_path"];?>images/calendar.gif' alt='End date selector' title='End date selector' border='0' align='absmiddle' onclick="return showCalendar('date2');">
					</td>
					<td width='130' nowrap style='white-space: nowrap;'>
						&nbsp;&nbsp;<input style='padding-bottom: 4px;' type='image' name='move_left' src='<?php print $config['url_path'];?>images/move_left.gif' alt='Left' border='0' align='absmiddle' title='Shift Left'>
						<select name='predefined_timeshift' title='Define Shifting Interval' onChange="applyTimespanFilterChange(document.form_timespan_selector)">
							<?php
							$start_val = 1;
							$end_val = sizeof($graph_timeshifts)+1;
							if (sizeof($graph_timeshifts) > 0) {
								for ($shift_value=$start_val; $shift_value < $end_val; $shift_value++) {
									print "<option value='$shift_value'"; if ($_SESSION["sess_current_timeshift"] == $shift_value) { print " selected"; } print ">" . title_trim($graph_timeshifts[$shift_value], 40) . "</option>\n";
								}
							}
							?>
						</select>
						<input style='padding-bottom: 4px;' type='image' name='move_right' src='<?php print $config['url_path'];?>images/move_right.gif' alt='Right' border='0' align='absmiddle' title='Shift Right'>
					</td>
					<td nowrap style='white-space: nowrap;'>
						&nbsp;&nbsp;<input type='submit' name='button_refresh' value='Refresh'>
						<input type='submit' name='button_clear_x' value='Clear'>
					</td>
				</tr>
			</table>
		</td>
		</form>
	</tr>
	<?php
}
?>