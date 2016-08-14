<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2016 The Cacti Group                                 |
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

include('./include/auth.php');
include_once('./plugins/mactrack/lib/mactrack_functions.php');

$macw_actions = array(
	1 => __('Delete'),
	2 => __('Disable')
);

set_default_action();

switch (get_request_var('action')) {
case 'save':
	form_save();

	break;
case 'actions':
	form_actions();

	break;
case 'edit':
	top_header();
	mactrack_macw_edit();
	bottom_footer();
	break;
default:
	top_header();
	mactrack_macw();
	bottom_footer();

	break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if ((isset_request_var('save_component_macw')) && (isempty_request_var('add_dq_y'))) {
		$mac_id = api_mactrack_macw_save(get_nfilter_request_var('mac_id'), 
			get_nfilter_request_var('mac_address'), get_nfilter_request_var('name'), 
			get_nfilter_request_var('ticket_number'), get_nfilter_request_var('description'),
			get_nfilter_request_var('notify_schedule'), get_nfilter_request_var('email_addresses'));

		header('Location: mactrack_macwatch.php?action=edit&id=' . (empty($mac_id) ? get_request_var('mac_id') : $mac_id));
	}
}

/* ------------------------
    The 'actions' function
   ------------------------ */

function form_actions() {
	global $config, $macw_actions, $fields_mactrack_macw_edit;

	/* ================= input validation ================= */
	get_filter_request_var('drp_action');
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
        $selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

        if ($selected_items != false) {
			if (get_request_var('drp_action') == '1') { /* delete */
				for ($i=0; $i<count($selected_items); $i++) {
					api_mactrack_macw_remove($selected_items[$i]);
				}
			}

			header('Location: mactrack_macwatch.php');
			exit;
		}
	}

	/* setup some variables */
	$macw_list = ''; $i = 0;

	/* loop through each of the mac watch items selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$macw_info = db_fetch_cell_prepared('SELECT name FROM mac_track_macwatch WHERE mac_id = ?', array($matches[1]));
			$macw_list .= '<li>' . $macw_info . '</li>';
			$macw_array[$i] = $matches[1];
		}

		$i++;
	}

	top_header();

	html_start_box($macw_actions[get_request_var('drp_action')], '60%', '', '3', 'center', '');

	form_start('mactrack_macwatch.php');

	if (!isset($macw_array)) {
		print "<tr><td class='even'><span class='textError'>" . __('You must select at least one watched Mac to delete.') . "</span></td></tr>\n";
		$save_html = "";
	}else{
		$save_html = "<input type='submit' name='save' value='" . __('Yes') . "'>";

		if (get_request_var('drp_action') == '1') { /* delete */
			print "<tr>
				<td class='textArea'>
					<p>" . __('Are you sure you want to delete the following watched Mac\'s?') . "</p>
					<p><ul>$macw_list</ul></p>
				</td>
			</tr>";
		}
	}

	print "<tr>
		<td colspan='2' align='right' class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($macw_array) ? serialize($macw_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_request_var('drp_action') . "'>" . (strlen($save_html) ? "
			<input type='button' name='cancel' onClick='cactiReturnTo()' value='" . __('No') . "'>
			$save_html" : "<input type='button' onClick='cactiReturnTo()' name='cancel' value='" . __('Return') . "'>") . "
		</td>
	</tr>";

	html_end_box();

	form_end();

	bottom_footer();
}

function api_mactrack_macw_save($mac_id, $mac_address, $name, $ticket_number, $description, $notify_schedule, $email_addresses) {
	$save['mac_id']          = $mac_id;
	$save['mac_address']     = form_input_validate($mac_address, 'mac_address', '', false, 3);
	$save['name']            = form_input_validate($name, 'name', '', false, 3);
	$save['ticket_number']   = form_input_validate($ticket_number, 'ticket_number', '', false, 3);
	$save['description']     = form_input_validate($description, 'description', '', false, 3);
	$save['notify_schedule'] = form_input_validate($notify_schedule, 'notify_schedule', '', false, 3);
	$save['email_addresses'] = form_input_validate($email_addresses, 'email_addresses', '', false, 3);

	$mac_id = 0;
	if (!is_error_message()) {
		$mac_id = sql_save($save, 'mac_track_macwatch', 'mac_address', false);

		if ($mac_id) {
			raise_message(1);
		}else{
			raise_message(2);
		}
	}

	return $mac_id;
}

function api_mactrack_macw_remove($mac_id) {
	db_execute_prepared('DELETE FROM mac_track_macwatch WHERE mac_id = ?', array($mac_id));
}

/* ---------------------
    MacWatch Functions
   --------------------- */

function mactrack_macw_remove() {
	global $config;

	/* ================= input validation ================= */
	get_filter_request_var('mac_id');
	/* ==================================================== */

	if ((read_config_option('remove_verification') == 'on') && (!isset_request_var('confirm'))) {
		top_header();
		form_confirm(__('Are You Sure?'), __('Are you sure you want to delete the watched Mac Address %s?', db_fetch_cell_prepared('SELECT name FROM mac_track_macwatch WHERE mac_id=?', array(get_request_var('mac_id')))), 'mactrack_macwatch.php', 'mactrack_macwatch.php?action=remove&mac_id=' . get_request_var('mac_id'));
		bottom_footer();
		exit;
	}

	if ((read_config_option('remove_verification') == '') || (isset_request_var('confirm'))) {
		api_mactrack_macw_remove(get_request_var('mac_id'));
	}
}

function mactrack_macw_get_macw_records(&$sql_where, $row_limit, $apply_limits = TRUE) {
	$sql_where = '';

	/* form the 'where' clause for our main sql query */
	if (get_request_var('filter') != '') {
		$sql_where = "WHERE (mac_address LIKE '%" . get_request_var('filter') . "%' OR " .
			"name LIKE '%" . get_request_var('filter') . "%' OR " .
			"ticket_number LIKE '%" . get_request_var('filter') . "%' OR " .
			"description LIKE '%" . get_request_var('filter') . "%')";
	}

	$query_string = "SELECT *
		FROM mac_track_macwatch
		$sql_where
		ORDER BY " . get_request_var('sort_column') . ' ' . get_request_var('sort_direction');

	if ($apply_limits) {
		$query_string .= ' LIMIT ' . ($row_limit*(get_request_var('page')-1)) . ',' . $row_limit;
	}

	return db_fetch_assoc($query_string);
}

function mactrack_macw_edit() {
	global $fields_mactrack_macw_edit;

	/* ================= input validation ================= */
	get_filter_request_var('mac_id');
	/* ==================================================== */

	display_output_messages();

	if (!isempty_request_var('mac_id')) {
		$mac_record = db_fetch_row_prepared('SELECT * FROM mac_track_macwatch WHERE mac_id = ?', array(get_request_var('mac_id')));
		$header_label = __('MacTrack MacWatch [edit: %s]', $mac_record['name']);
	}else{
		$header_label = __('MacTrack MacWatch [new]');
	}

	form_start('mactrack_macwatch.php', 'mactrack_macwatch');

	html_start_box($header_label, '100%', '', '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($fields_mactrack_macw_edit, (isset($mac_record) ? $mac_record : array()))
		)
	);

	html_end_box();

	form_save_button('mactrack_macwatch.php', 'return');
}

function mactrack_macw() {
	global $macw_actions, $config, $item_rows;

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'filter' => array(
			'filter' => FILTER_CALLBACK,
			'pageset' => true,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'name',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			)
	);

	validate_store_request_vars($filters, 'sess_mactrack_macw');
	/* ================= input validation ================= */

	if (get_request_var('rows') == -1) {
		$row_limit = read_config_option('num_rows_table');
	}elseif (get_request_var('rows') == -2) {
		$row_limit = 999999;
	}else{
		$row_limit = get_request_var('rows');
	}

	html_start_box(__('MacTrack MacWatch Filters'), '100%', '', '3', 'center', 'mactrack_macwatch.php?action=edit');
	mactrack_macw_filter();
	html_end_box();

	$sql_where = '';

	$macw = mactrack_macw_get_macw_records($sql_where, $row_limit);

	$total_rows = db_fetch_cell("SELECT count(*) FROM mac_track_macwatch $sql_where");

	$nav = html_nav_bar('mactrack_macwatch.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $row_limit, $total_rows, 9, __('Watches'));

	form_start('mactrack_macwatch.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'name'            => array(__('Watch Name'), 'ASC'),
		'mac_address'     => array(__('Mac Address'), 'ASC'),
		'ticket_number'   => array(__('Ticket Number'), 'ASC'),
		'nosort'          => array(__('Watch Description'), 'ASC'),
		'date_first_seen' => array(__('First Seen'), 'ASC'),
		'date_last_seen'  => array(__('Last Seen'), 'ASC'));

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'));

	$i = 0;
	if (sizeof($macw)) {
		foreach ($macw as $mac) {
			form_alternate_row('line' . $mac['mac_id'], true);
			form_selectable_cell(filter_value($mac['name'], get_request_var('filter'), 'mactrack_macwatch.php?action=edit&mac_id=' . $mac['mac_id']), $mac['mac_id']);
			form_selectable_cell(filter_value($mac['mac_address'], get_request_var('filter')), $mac['mac_id']);
			form_selectable_cell(filter_value($mac['ticket_number'], get_request_var('filter')), $mac['mac_id']);
			form_selectable_cell(filter_value($mac['description'], get_request_var('filter')), $mac['mac_id']);
			form_selectable_cell($mac['date_first_seen'] == '0000-00-00 00:00:00' ? __('N/A') : $mac['date_first_seen'], $mac['mac_id']);
			form_selectable_cell($mac['date_last_seen'] == '0000-00-00 00:00:00' ? __('N/A') : $mac['date_last_seen'], $mac['mac_id']);
			form_selectable_cell($mac['name'], $mac['mac_id']);
			form_end_row();
		}
	}else{
		print '<tr><td colspan="10"><em>' . __('No MacTrack Watched Macs') . '</em></td></tr>';
	}

	html_end_box(false);

	if (sizeof($macw)) {
		print $nav;
	}

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($macw_actions);

	form_end();
}

function mactrack_macw_filter() {
	global $item_rows;

	?>
	<tr class='even'>
		<td>
			<form id='mactrack'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' id='filter' size='25' value='<?php print get_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Watches');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('rows') == '-1') {?> selected<?php }?>><?php print __('Default');?></option>
							<?php
							if (sizeof($item_rows) > 0) {
							foreach ($item_rows as $key => $value) {
								print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . $value . '</option>';
							}
							}
							?>
						</select>
					</td>
					<td>
						<input type='submit' id='go' value='<?php print __('Go');?>'>
					</td>
					<td>
						<input type='button' id='clear' value='<?php print __('Clear');?>'>
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' value='<?php print get_request_var('page');?>'>
			</form>
			<script type='text/javascript'>

			function applyFilter() {
				strURL  = urlPath+'plugins/mactrack/mactrack_macwatch.php?header=false';
				strURL += '&filter=' + $('#filter').val();
				strURL += '&rows=' + $('#rows').val();
				loadPageNoHeader(strURL);
			}

			function clearFilter() {
				strURL  = urlPath+'plugins/mactrack/mactrack_macwatch.php?header=false&clear=true';
				loadPageNoHeader(strURL);
			}

			$(function() {
				$('#mactrack').submit(function(event) {
					event.preventDefault();
					applyFilter();
				});

				$('#clear').click(function() {
					clearFilter();
				});
			});

			</script>
		</td>
		</td>
	</tr>
	<?php
}
