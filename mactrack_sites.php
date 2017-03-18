<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2017 The Cacti Group                                 |
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
include_once('./lib/snmp.php');
include_once('./plugins/mactrack/lib/mactrack_functions.php');
include_once('./plugins/mactrack/mactrack_actions.php');

$site_actions = array(
	1 => __('Delete')
);

/* set default action */
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
		mactrack_site_edit();
		bottom_footer();
		break;
	default:
		if (isset_request_var('export')) {
			mactrack_site_export();
		}else{
			top_header();
			mactrack_site();
			bottom_footer();
		}
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if ((isset_request_var('save_component_site')) && (isempty_request_var('add_dq_y'))) {
		$site_id = api_mactrack_site_save(get_filter_request_var('site_id'), get_nfilter_request_var('site_name'), 
			get_nfilter_request_var('customer_contact'), get_nfilter_request_var('netops_contact'), 
			get_nfilter_request_var('facilities_contact'), get_nfilter_request_var('site_info'));

		header('Location: mactrack_sites.php?action=edit&site_id=' . (empty($site_id) ? get_filter_request_var('site_id') : $site_id));
	}
}

/* ------------------------
    The 'actions' function
   ------------------------ */

function form_actions() {
	global $config, $site_actions, $fields_mactrack_site_edit;

	/* ================= input validation ================= */
	get_filter_request_var('drp_action');
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_request_var('drp_action') == '1') { /* delete */
				for ($i=0; $i<count($selected_items); $i++) {
					api_mactrack_site_remove($selected_items[$i]);
				}
			}

			header('Location: mactrack_sites.php');
			exit;
		}
	}

	/* setup some variables */
	$site_list = ''; $i = 0;

	/* loop through each of the host templates selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$site_info = db_fetch_cell('SELECT site_name FROM mac_track_sites WHERE site_id=' . $matches[1]);
			$site_list .= '<li>' . $site_info . '</li>';
			$site_array[$i] = $matches[1];
		}

		$i++;
	}

	top_header();

	html_start_box($site_actions[get_request_var('drp_action')], '60%', '', '3', 'center', '');

	form_start('mactrack_sites.php');

	if (get_request_var('drp_action') == '1') { /* delete */
		print "<tr>
			<td class='textArea'>
				<p>" . __('Are you sure you want to delete the following site(s)?') . "</p>
				<p><ul>$site_list</ul></p>
			</td>
		</tr>\n";
	}

	if (!isset($site_array)) {
		print "<tr><td class='even'><span class='textError'>" . __('You must select at least one site.') . "</span></td></tr>\n";
		$save_html = '';
	}else{
		$save_html = "<input type='submit' name='save_x' value='Yes'>";
	}

	print "<tr>
		<td colspan='2' class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($site_array) ? serialize($site_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_request_var('drp_action') . "'>" . (strlen($save_html) ? "
			<input type='submit' name='cancel_x' value='No'>
			$save_html" : "<input type='submit' name='cancel_x' value='" . __('Return') . "'>") . "
		</td>
	</tr>";

	html_end_box();

	bottom_footer();
}

function mactrack_site_validate_req_vars() {
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
		'site_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '-1',
			'pageset' => true,
			),
		'device_type_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '-1',
			'pageset' => true,
			),
		'filter' => array(
			'filter' => FILTER_CALLBACK,
			'pageset' => true,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'site_name',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			),
		'detail' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(true|false)')),
			'pageset' => true,
			'default' => 'false'
			)
	);

	validate_store_request_vars($filters, 'sess_mactrack_sites');
	/* ================= input validation ================= */

}

function mactrack_site_export() {
	global $site_actions, $config;

	mactrack_site_validate_req_vars();

	$sql_where = '';

	$sites = mactrack_site_get_site_records($sql_where, 0, FALSE);

	if (get_request_var('detail') == 'false') {
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

	header('Content-type: application/csv');
	header('Content-Disposition: attachment; filename=cacti_site_xport.csv');
	foreach($xport_array as $xport_line) {
		print $xport_line . "\n";
	}
}

/* ---------------------
    Site Functions
   --------------------- */

function mactrack_site_remove() {
	global $config;

	/* ================= input validation ================= */
	get_filter_request_var('site_id');
	/* ==================================================== */

	if ((read_config_option('remove_verification') == 'on') && (!isset_request_var('confirm'))) {
		top_header();
		form_confirm(__('Are You Sure?'), __("Are you sure you want to delete the site <strong>'%s'</strong>?", db_fetch_cell('SELECT description FROM host WHERE id=' . get_request_var('device_id'))), 'mactrack_sites.php', 'mactrack_sites.php?action=remove&site_id=' . get_request_var('site_id'));
		bottom_footer();
		exit;
	}

	if ((read_config_option('remove_verification') == '') || (isset_request_var('confirm'))) {
		api_mactrack_site_remove(get_request_var('site_id'));
	}
}

function mactrack_site_get_site_records(&$sql_where, $rows, $apply_limits = TRUE) {
	/* create SQL where clause */
	$device_type_info = db_fetch_row_prepared('SELECT * FROM mac_track_device_types WHERE device_type_id = ?', array(get_request_var('device_type_id')));

	$sql_where = '';

	/* form the 'where' clause for our main sql query */
	if (get_request_var('filter') != '') {
		if (get_request_var('detail') == 'false') {
			$sql_where = "WHERE (mac_track_sites.site_name LIKE '%" . get_request_var('filter') . "%')";
		}else{
			$sql_where = "WHERE (mac_track_device_types.vendor LIKE '%" . get_request_var('filter') . "%' OR " .
				"mac_track_device_types.description LIKE '%" . get_request_var('filter') . "%' OR " .
				"mac_track_sites.site_name LIKE '%" . get_request_var('filter') . "%')";
		}
	}

	if (sizeof($device_type_info)) {
		if (!strlen($sql_where)) {
			$sql_where = 'WHERE (mac_track_devices.device_type_id=' . $device_type_info['device_type_id'] . ')';
		}else{
			$sql_where .= ' AND (mac_track_devices.device_type_id=' . $device_type_info['device_type_id'] . ')';
		}
	}

	if ((get_request_var('site_id') != '-1') && (get_request_var('detail'))) {
		if (!strlen($sql_where)) {
			$sql_where = 'WHERE (mac_track_devices.site_id=' . get_request_var('site_id') . ')';
		}else{
			$sql_where .= ' AND (mac_track_devices.site_id=' . get_request_var('site_id') . ')';
		}
	}

	$sql_order = get_order_string();
	if ($apply_limits) {
		$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;
	}else{
		$sql_limit = '';
	}

	if (get_request_var('detail') == 'false') {
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
			$sql_order
			$sql_limit";
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
			$sql_order
			$sql_limit";
	}

	return db_fetch_assoc($query_string);
}

function mactrack_site_edit() {
	global $fields_mactrack_site_edit;

	/* ================= input validation ================= */
	get_filter_request_var('site_id');
	/* ==================================================== */

	display_output_messages();

	if (!isempty_request_var('site_id')) {
		$site = db_fetch_row('SELECT * FROM mac_track_sites WHERE site_id=' . get_request_var('site_id'));
		$header_label = __('MacTrack Site [edit: %s]', $site['site_name']);
	}else{
		$header_label = __('MacTrack Site [new]');
	}

	form_start('mactrack_sites.php');

	html_start_box($header_label, '100%', '', '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($fields_mactrack_site_edit, (isset($site) ? $site : array()))
		)
	);

	html_end_box();

	form_save_button('mactrack_sites.php', 'return', 'site_id');
}

function mactrack_site() {
	global $site_actions, $config, $item_rows;

	mactrack_site_validate_req_vars();

	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	}elseif (get_request_var('rows') == -2) {
		$rows = 999999;
	}else{
		$rows = get_request_var('rows');
	}

	html_start_box(__('MacTrack Site Filters'), '100%', '', '3', 'center', 'mactrack_sites.php?action=edit');

	mactrack_site_filter();

	html_end_box();

	$sql_where = '';

	$sites = mactrack_site_get_site_records($sql_where, $rows);

	if (get_request_var('detail') == 'false') {
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

	if (get_request_var('detail') == 'false') {
		$nav = html_nav_bar('mactrack_sites.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_filter_request_var('page'), $rows, $total_rows, 9, __('Sites'), 'page', 'main');

		print $nav;

		html_start_box('', '100%', '', '3', 'center', '');

		$display_text = array(
			'site_name'           => array(__('Site Name'), 'ASC'),
			'total_devices'       => array(__('Devices'), 'DESC'),
			'total_ips'           => array(__('Total IP\'s'), 'DESC'),
			'total_user_ports'    => array(__('User Ports'), 'DESC'),
			'total_oper_ports'    => array(__('User Ports Up'), 'DESC'),
			'total_macs'          => array(__('MACS Found'), 'DESC'),
			'total_device_errors' => array(__('Device Errors'), 'DESC')
		);

		html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'));

		$i = 0;
		if (sizeof($sites)) {
			foreach ($sites as $site) {
				form_alternate_row('line' . $site['site_id'], true);
				form_selectable_cell("<a class='linkEditMain' href='mactrack_sites.php?action=edit&site_id=" . $site['site_id'] . "'>". (get_request_var('filter') != '' ? preg_replace('/(' . preg_quote(get_request_var('filter')) . ')/i', "<span class='filteredValue'>\\1</span>", $site['site_name']) : $site['site_name']) . '</a>', $site['site_id']);
				form_selectable_cell(number_format_i18n($site['total_devices']), $site['site_id']);
				form_selectable_cell(number_format_i18n($site['total_ips']), $site['site_id']);
				form_selectable_cell(number_format_i18n($site['total_user_ports']), $site['site_id']);
				form_selectable_cell(number_format_i18n($site['total_oper_ports']), $site['site_id']);
				form_selectable_cell(number_format_i18n($site['total_macs']), $site['site_id']);
				form_selectable_cell($site['total_device_errors'], $site['site_id']);
				form_checkbox_cell($site['site_name'], $site['site_id']);
				form_end_row();
			}
		}else{
			print '<tr><td><em>' . __('No MacTrack Sites') . '</em></td></tr>';
		}

		html_end_box(false);

		if (sizeof($sites)) {
			print $nav;
		}
	}else{
		$nav = html_nav_bar('mactrack_sites.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_filter_request_var('page'), $rows, $total_rows, 10, __('Sites'), 'page', 'main');

		print $nav;

		html_start_box('', '100%', '', '3', 'center', '');

		$display_text = array(
			'site_name'        => array(__('Site Name'), 'ASC'),
			'vendor'           => array(__('Vendor'), 'ASC'),
			'description'      => array(__('Device Type'), 'DESC'),
			'total_devices'    => array(__('Total Devices'), 'DESC'),
			'sum_ips_total'    => array(__('Total IP\'s'), 'DESC'),
			'sum_ports_total'  => array(__('Total User Ports'), 'DESC'),
			'sum_ports_active' => array(__('Total Oper Ports'), 'DESC'),
			'sum_ports_trunk'  => array(__('Total Trunks'), 'DESC'),
			'sum_macs_active'  => array(__('MACS Found'), 'DESC'));

		html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'));

		if (sizeof($sites)) {
			foreach ($sites as $site) {
				form_alternate_row();
					?>
					<td width=200>
						<a class='linkEditMain' href='mactrack_sites.php?action=edit&site_id=<?php print $site['site_id'];?>'><?php print (get_request_var('filter') != '' ? preg_replace('/(' . preg_quote(get_request_var('filter')) . ')/i', "<span class='filteredValue'>\\1</span>", $site['site_name']) : $site['site_name']);?></a>
					</td>
					<td><?php print (get_request_var('filter') != '' ? preg_replace('/(' . preg_quote(get_request_var('filter')) . ')/i', "<span class='filteredValue'>\\1</span>", $site['vendor']) : $site['vendor']);?></td>
					<td><?php print (get_request_var('filter') != '' ? preg_replace('/(' . preg_quote(get_request_var('filter')) . ')/i', "<span class='filteredValue'>\\1</span>", $site['description']) : $site['description']);?></td>
					<td><?php print number_format_i18n($site['total_devices']);?></td>
					<td><?php print number_format_i18n($site['sum_ips_total']);?></td>
					<td><?php print number_format_i18n($site['sum_ports_total']);?></td>
					<td><?php print number_format_i18n($site['sum_ports_active']);?></td>
					<td><?php print number_format_i18n($site['sum_ports_trunk']);?></td>
					<td><?php print number_format_i18n($site['sum_macs_active']);?></td>
				</tr>
				<?php
			}
		}else{
			print '<tr><td><em>' . __('No MacTrack Sites') . '</em></td></tr>';
		}

		html_end_box(false);

		if (sizeof($sites)) {
			print $nav;
		}
	}

	/* draw the dropdown containing a list of available actions for this form */
	if (get_request_var('detail') == 'false') {
		draw_actions_dropdown($site_actions);
	}
}

