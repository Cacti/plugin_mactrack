<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2022 The Cacti Group                                 |
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
include('./include/auth.php');
include_once('./include/global_arrays.php');
include_once('./plugins/mactrack/lib/mactrack_functions.php');

$title = __('Device Tracking - 802.1x View', 'mactrack');

set_default_action();

if (isset_request_var('export')) {
	mactrack_view_export_dot1x();
} else {
	general_header();

	mactrack_view_dot1x_validate_request_vars();

	if (isset_request_var('scan_date')) {
		mactrack_view_dot1x();
	}

	bottom_footer();
}

function mactrack_view_dot1x_validate_request_vars() {
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
            'default' => '-1'
            ),
        'device_id' => array(
            'filter' => FILTER_VALIDATE_INT,
            'default' => '-1'
            ),
        'status' => array(
            'filter' => FILTER_VALIDATE_INT,
            'default' => '0'
            ),
        'mac_filter_type_id' => array(
            'filter' => FILTER_VALIDATE_INT,
            'default' => '1'
            ),
        'port_name_filter_type_id' => array(
            'filter' => FILTER_VALIDATE_INT,
            'default' => '1'
            ),
        'ip_filter_type_id' => array(
            'filter' => FILTER_VALIDATE_INT,
            'default' => '1'
            ),
		'domain' => array(
            'filter' => FILTER_VALIDATE_INT,
            'default' => '-1',
			'pageset' => true
			),
        'filter' => array(
            'filter' => FILTER_CALLBACK,
            'pageset' => true,
            'default' => '',
            'options' => array('options' => 'sanitize_search_string')
            ),
        'ip_filter' => array(
            'filter' => FILTER_DEFAULT,
            'default' => '',
            ),
        'mac_filter' => array(
            'filter' => FILTER_DEFAULT,
            'default' => '',
            ),
        'port_name_filter' => array(
            'filter' => FILTER_DEFAULT,
            'default' => '',
            ),
        'scan_date' => array(
            'filter' => FILTER_CALLBACK,
            'default' => '2',
            'options' => array('options' => 'sanitize_search_string')
            ),
        'sort_column' => array(
            'filter' => FILTER_CALLBACK,
            'default' => 'device_name',
            'options' => array('options' => 'sanitize_search_string')
            ),
        'sort_direction' => array(
            'filter' => FILTER_CALLBACK,
            'default' => 'ASC',
            'options' => array('options' => 'sanitize_search_string')
            )
    );

    validate_store_request_vars($filters, 'sess_mtv_dot1x');
    /* ================= input validation ================= */
}

function mactrack_view_export_dot1x() {
	mactrack_view_dot1x_validate_request_vars();

	$sql_where = '';

	$port_results = mactrack_view_get_dot1x_records($sql_where, 0, false);

	$xport_array = array();
	array_push($xport_array, '"site_name","hostname","device_name",' .
		'"domain","status","mac_address",' .
		'"ip_address","dns_hostname","port_number","ifName","username","scan_date"');

	if (cacti_sizeof($port_results)) {
		foreach($port_results as $port_result) {
			if (get_request_var('scan_date') == 1) {
				$scan_date = $port_result['scan_date'];
			} else {
				$scan_date = $port_result['max_scan_date'];
			}

			array_push($xport_array,'"' . $port_result['site_name'] . '","' .
			$port_result['hostname'] . '","' . $port_result['device_name'] . '","' .
			$port_result['domain'] . '","' . $port_result['status'] . '","' .
			$port_result['mac_address'] . '","' . $port_result['ip_address'] . '","' .
			$port_result['dns_hostname'] . '","' . $port_result['port_number'] . '","' .
			$port_result['ifName'] . '","' . $port_result['username'] . '","' .
			$scan_date . '"');
		}
	}

	header('Content-type: application/csv');
	header('Content-Disposition: attachment; filename=mactrack_dot1x_xport.csv');
	foreach($xport_array as $xport_line) {
		print $xport_line . "\n";
	}
}

function mactrack_view_get_dot1x_records(&$sql_where, $apply_limits = true, $rows) {
	/* status sql where */
	if (get_request_var('status') == '1') { // Idle
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . 'mtd.status = 1';
	} elseif (get_request_var('status') == '2') { // Running
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . 'mtd.status = 2';
	} elseif (get_request_var('status') == '3') { // No Method
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . 'mtd.status = 3';
	} elseif (get_request_var('status') == '4') { // Authentication Success
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . 'mtd.status = 4';
	} elseif (get_request_var('status') == '5') { // Authentication Failed
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . 'mtd.status = 5';
	} elseif (get_request_var('status') == '6') { // Authorization Success
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . 'mtd.status = 6';
	} elseif (get_request_var('status') == '7') { // Authorization Failed
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . 'mtd.status = 7';
	}

	/* form the 'where' clause for our main sql query */
	if (get_request_var('mac_filter') != '') {
		switch (get_request_var('mac_filter_type_id')) {
			case '1': /* do not filter */
				break;
			case '2': /* matches */
				$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mtd.mac_address = ' . db_qstr(get_request_var('mac_filter'));
				break;
			case '3': /* contains */
				$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mtd.mac_address LIKE ' . db_qstr('%' . get_request_var('mac_filter') . '%');
				break;
			case '4': /* begins with */
				$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mtd.mac_address LIKE ' . db_qstr(get_request_var('mac_filter') . '%');
				break;
			case '5': /* does not contain */
				$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mtd.mac_address NOT LIKE ' . db_qstr('%' . get_request_var('mac_filter') . '%');
				break;
			case '6': /* does not begin with */
				$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mtd.mac_address NOT LIKE ' . db_qstr(get_request_var('mac_filter') . '%');
				break;
		}
	}

	if ((get_request_var('ip_filter') != '') || (get_request_var('ip_filter_type_id') > 6)) {
		switch (get_request_var('ip_filter_type_id')) {
			case '1': /* do not filter */
				break;
			case '2': /* matches */
				$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mtd.ip_address = ' . db_qstr(get_request_var('ip_filter'));
				break;
			case '3': /* contains */
				$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mtd.ip_address LIKE ' . db_qstr('%' . get_request_var('ip_filter') . '%');
				break;
			case '4': /* begins with */
				$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mtd.ip_address LIKE ' . db_qstr(get_request_var('ip_filter') . '%');
				break;
			case '5': /* does not contain */
				$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mtd.ip_address NOT LIKE ' . db_qstr('%' . get_request_var('ip_filter') . '%');
				break;
			case '6': /* does not begin with */
				$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mtd.ip_address NOT LIKE ' . db_qstr(get_request_var('ip_filter') . '%');
				break;
			case '7': /* is null */
				$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mtd.ip_address = ""';
				break;
			case '8': /* is not null */
				$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mtd.ip_address != ""';
				break;
		}
	}

	if ((get_request_var('port_name_filter') != '') || (get_request_var('port_name_filter_type_id') > 6)) {
		switch (get_request_var('port_name_filter_type_id')) {
			case '1': /* do not filter */
				break;
			case '2': /* matches */
				$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mti.ifName = ' . db_qstr(get_request_var('port_name_filter'));
				break;
			case '3': /* contains */
				$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mti.ifName LIKE ' . db_qstr('%' . get_request_var('port_name_filter') . '%');
				break;
			case '4': /* begins with */
				$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mti.ifName LIKE ' . db_qstr(get_request_var('port_name_filter') . '%');
				break;
			case '5': /* does not contain */
				$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mti.ifName NOT LIKE ' . db_qstr('%' . get_request_var('port_name_filter') . '%');
				break;
			case '6': /* does not begin with */
				$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mti.ifName NOT LIKE ' . db_qstr(get_request_var('port_name_filter') . '%');
				break;
			case '7': /* is null */
				$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mti.ifName = ""';
				break;
			case '8': /* is not null */
				$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mti.ifName != ""';
				break;
		}
	}

	if (get_request_var('filter') != '') {
		if (read_config_option('mt_reverse_dns') != '') {
			$sql_where .= ($sql_where != '' ? ' AND':'WHERE') .
				' (mtd.dns_hostname LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ' OR ' .
				'mti.ifName LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ' OR ' .
				'mtd.device_name LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ' OR ' .
				'mtd.username LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ' OR ' .
				'mtd.ip_address LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ' OR ' .
				'mtd.mac_address LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ')';
		} else {
			$sql_where .= ($sql_where != '' ? ' AND':'WHERE') .
				' (mtd.dns_hostname LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ' OR ' .
				'mti.ifName LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ' OR ' .
				'mtd.device_name LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ' OR ' .
				'mtd.username LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ' OR ' .
				'mtd.ip_address LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ' OR ' .
				'mtd.mac_address LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ')';
		}
	}

	if (get_request_var('domain') != '-1') {
		$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mtd.domain = ' . db_qstr(get_request_var('domain'));
	}

	if (get_request_var('site_id') != '-1') {
		$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mtd.site_id = ' . get_request_var('site_id');
	}

	if (get_request_var('status') != '0') {
		$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mtd.status = ' . get_request_var('status');
	}

	if (get_request_var('device_id') != '-1') {
		$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mtd.device_id = ' . get_request_var('device_id');
	}

	if ((get_request_var('scan_date') != '1') && (get_request_var('scan_date') != '2')) {
		$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mtd.scan_date =' . db_qstr(get_request_var('scan_date'));
	}

	/* prevent table scans, either a device or site must be selected */
	if (get_request_var('site_id') == -1 && get_request_var('device_id') == -1) {
		if ($sql_where == '') {
			return array();
		}
	}

	$sql_order = get_order_string();
	if ($apply_limits  && $rows != 999999) {
		$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;
	} else {
		$sql_limit = '';
	}

	if ((get_request_var('scan_date') != 2)) {
		$query_string = "SELECT mts.site_name, mtd.device_id, mtd.device_name, mtd.hostname,
			mtd.mac_address, mtd.username, mtd.ip_address, mtd.dns_hostname, mtd.port_number,
			mti.ifName, mti.ifDescr, mtd.domain, mtd.status, mtd.scan_date
			FROM mac_track_dot1x AS mtd
			LEFT JOIN mac_track_sites AS mts
			ON mtd.site_id = mts.site_id
			LEFT JOIN mac_track_interfaces AS mti
			ON mtd.port_number = mti.ifIndex
			AND mtd.device_id = mti.device_id
			$sql_where
			$sql_order
			$sql_limit";
	} else {
		$query_string = "SELECT mts.site_name, mtd.device_id, mtd.device_name, mtd.hostname,
			mtd.mac_address, mtd.username, mtd.ip_address, mtd.dns_hostname, mtd.port_number,
			mti.ifName, mti.ifDescr, mtd.domain, mtd.status, MAX(mtd.scan_date) AS scan_date
			FROM mac_track_dot1x AS mtd
			LEFT JOIN mac_track_sites AS mts
			ON (mtd.site_id = mts.site_id)
			LEFT JOIN mac_track_interfaces AS mti
			ON mtd.port_number = mti.ifIndex
			AND mtd.device_id = mti.device_id
			$sql_where
			GROUP BY device_id, mac_address, port_number, ip_address
			$sql_order
			$sql_limit";
	}

	if ($sql_where == '') {
		return array();
	} else {
		return db_fetch_assoc($query_string);
	}
}

function mactrack_view_dot1x() {
	global $title, $report, $mactrack_search_types, $rows_selector, $config;
	global $item_rows;

	mactrack_tabs();

	html_start_box($title, '100%', '', '3', 'center', '');
	mactrack_dot1x_filter();
	html_end_box();

	$sql_where = '';

	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} elseif (get_request_var('rows') == -2) {
		$rows = 999999;
	} else {
		$rows = get_request_var('rows');
	}

	$port_results = mactrack_view_get_dot1x_records($sql_where, true, $rows);

	/* prevent table scans, either a device or site must be selected */
	if ($sql_where == '') {
		$total_rows = 0;
	} elseif (get_request_var('scan_date') != 3) {
		$rows_query_string = "SELECT
			COUNT(mtd.device_id)
			FROM mac_track_dot1x AS mtd
			LEFT JOIN mac_track_sites AS mts
			ON mtd.site_id = mts.site_id
			LEFT JOIN mac_track_interfaces AS mti
			ON mtd.port_number = mti.ifIndex
			AND mtd.device_id = mti.device_id
			$sql_where";

		$total_rows = db_fetch_cell($rows_query_string);
	} else {
		$rows_query_string = "SELECT
			COUNT(DISTINCT device_id, mac_address, port_number, ip_address)
			FROM mac_track_dot1x AS mtd
			LEFT JOIN mac_track_sites AS mts
			ON mtd.site_id = mts.site_id
			LEFT JOIN mac_track_interfaces AS mti
			ON mtd.port_number = mti.ifIndex
			AND mtd.device_id = mti.device_id
			$sql_where";

		$total_rows = db_fetch_cell($rows_query_string);
	}

	if (read_config_option('mt_reverse_dns') != '') {
		$display_text = array(
			'nosort'       => array(__('Actions', 'mactrack'), ''),
			'device_name'  => array(__('Switch Name', 'mactrack'), 'ASC'),
			'hostname'     => array(__('Switch Hostname', 'mactrack'), 'ASC'),
			'username'     => array(__('Username', 'mactrack'), 'ASC'),
			'ip_address'   => array(__('ED IP Address', 'mactrack'), 'ASC'),
			'dns_hostname' => array(__('ED DNS Hostname', 'mactrack'), 'ASC'),
			'mac_address'  => array(__('ED MAC Address', 'mactrack'), 'ASC'),
			'ifName'       => array(__('Port Name', 'mactrack'), 'ASC'),
			'domain'       => array(__('Domain', 'mactrack'), 'DESC'),
			'status'       => array(__('Status', 'mactrack'), 'ASC'),
			'scan_date'    => array(__('Last Scan Date', 'mactrack'), 'DESC')
		);
	} else {
		$display_text = array(
			'nosort'       => array(__('Actions', 'mactrack'), ''),
			'device_name'  => array(__('Switch Name', 'mactrack'), 'ASC'),
			'hostname'     => array(__('Switch Hostname', 'mactrack'), 'ASC'),
			'username'     => array(__('Username', 'mactrack'), 'ASC'),
			'ip_address'   => array(__('ED IP Address', 'mactrack'), 'ASC'),
			'mac_address'  => array(__('ED MAC Address', 'mactrack'), 'ASC'),
			'ifName'       => array(__('Port Name', 'mactrack'), 'ASC'),
			'domain'       => array(__('Domain', 'mactrack'), 'DESC'),
			'status'       => array(__('Status', 'mactrack'), 'ASC'),
			'scan_date'    => array(__('Last Scan Date', 'mactrack'), 'DESC')
		);
	}

	if (api_plugin_user_realm_auth('mactrack_macauth.php')) {
		$columns = cacti_sizeof($display_text) + 1;
	} else {
		$columns = cacti_sizeof($display_text);
	}

	$nav = html_nav_bar('mactrack_view_dot1x.php?report=dot1x', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, $columns, __('802.1x Sessions', 'mactrack'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	if (api_plugin_user_realm_auth('mactrack_macauth.php')) {
		html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'));
	} else {
		html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'));
	}

	$i = 0;
	if (cacti_sizeof($port_results)) {
		foreach ($port_results as $port_result) {
			/* find the background color and enclose it */
			$class = mactrack_dot1x_row_class($port_result);

			if ($class) {
				print "<tr id='row_" . $port_result['device_id'] . '_' . $port_result['port_number'] . "' class='tableRow $class'>\n"; $i++;
			} else {
				if (($i % 2) == 1) {
					$class = 'odd';
				} else {
					$class = 'even';
				}

				print "<tr id='row_" . $port_result['device_id'] . "' class='tableRow $class'>\n"; $i++;
			}

			print mactrack_format_dot1x_row($port_result);
		}
	} else {
		print '<tr><td colspan="7"><em>' . __('No Device Tracking 802.1x Sessions Found', 'mactrack') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($port_results)) {
		print $nav;
	}

	print '<div class="center" style="position:fixed;left:0;bottom:0;display:table;margin-left:auto;margin-right:auto;width:100%;">';

	html_start_box('', '100%', '', '3', 'center', '');
	print '<tr class="tableRow">';
	mactrack_legend_row('authn_success', __('Authorization Success', 'mactrack'));
	mactrack_legend_row('auth_success', __('Authentication Success', 'mactrack'));
	mactrack_legend_row('authn_failed', __('Authorization Failed', 'mactrack'));
	mactrack_legend_row('auth_failed', __('Authentication Failed', 'mactrack'));
	mactrack_legend_row('running', __('Running', 'mactrack'));
	mactrack_legend_row('idle', __('Idle', 'mactrack'));
	print '</tr>';
	html_end_box(false);

	print '</div>';

	print '<div id="response" title="' . __esc('Dot1x Scan Results', 'mactrack') . '"></div>';

	bottom_footer();

}

function mactrack_dot1x_filter() {
	global $item_rows, $rows_selector, $mactrack_search_types;

	?>
	<tr class='even'>
		<td>
			<form id='mactrack'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search', 'mactrack');?>
					</td>
					<td>
						<input type='text' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Site', 'mactrack');?>
					</td>
					<td>
						<select id='site_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('site_id') == '-1') {?> selected<?php }?>><?php print __('N/A', 'mactrack');?></option>
							<?php
							$sites = db_fetch_assoc('SELECT site_id, site_name
								FROM mac_track_sites
								ORDER BY site_name');

							if (cacti_sizeof($sites)) {
								foreach ($sites as $site) {
									print '<option value="' . $site['site_id'] .'"'; if (get_request_var('site_id') == $site['site_id']) { print ' selected'; } print '>' . $site['site_name'] . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Device', 'mactrack');?>
					</td>
					<td>
						<select id='device_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('device_id') == '-1') {?> selected<?php }?>><?php print __('All', 'mactrack');?></option>
							<?php
							if (get_request_var('site_id') == -1) {
								$filter_devices = db_fetch_assoc('SELECT DISTINCT device_id, device_name, hostname
									FROM mac_track_devices
									WHERE device_type_id
									IN (SELECT device_type_id from mac_track_device_types
									WHERE dot1x_scanning_function="get_cisco_dot1x_table")
									ORDER BY device_name');
							} else {
								$filter_devices = db_fetch_assoc_prepared('SELECT device_id, device_name, hostname
									FROM mac_track_devices
									WHERE (site_id = ? )
									AND (device_type_id IN
									(SELECT device_type_id from mac_track_device_types
									WHERE dot1x_scanning_function="get_cisco_dot1x_table"))
									ORDER BY device_name',
									array(get_request_var('site_id')));
							}

							if (cacti_sizeof($filter_devices)) {
								foreach ($filter_devices as $filter_device) {
									print '<option value="' . $filter_device['device_id'] . '"' . (get_request_var('device_id') == $filter_device['device_id'] ? ' selected':'') . '>' . html_escape($filter_device['device_name'] . '(' . $filter_device['hostname'] . ')') .  '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Sessions', 'mactrack');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<?php
							if (cacti_sizeof($rows_selector)) {
								foreach ($rows_selector as $key => $value) {
									print '<option value="' . $key . '"' . (get_request_var('rows') == $key ? ' selected':'') . '>' . $value . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<span class='nowrap'>
							<input type='submit' id='go' value='<?php print __esc('Go', 'mactrack');?>'>
							<input type='button' id='clear' value='<?php print __esc('Clear', 'mactrack');?>'>
							<input type='button' id='export' value='<?php print __esc('Export', 'mactrack');?>'>
						</span>
					</td>
				</tr>
			</table>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('IP', 'mactrack');?>
					</td>
					<td>
						<select id='ip_filter_type_id'>
							<?php
							foreach($mactrack_search_types as $i => $type) {
								print "<option value='$i'" . (get_request_var('ip_filter_type_id') == $i ? ' selected':'') . '>' . $type . '</option>';
							}
							?>
						</select>
					</td>
					<td>
						<input type='text' id='ip_filter' size='25' value='<?php print html_escape_request_var('ip_filter');?>'>
					</td>
					<td>
						<?php print __('Status', 'mactrack');?>
					</td>
					<td>
						<select id='status' onChange='applyFilter()'>
							<?php
							$all_status = array(
								0 => __esc('Any Status', 'mactrack'),
								1 => __esc('Idle', 'mactrack'),
								2 => __esc('Running', 'mactrack'),
								3 => __esc('No Method', 'mactrack'),
								4 => __esc('Authentication Success', 'mactrack'),
								5 => __esc('Authentication Failed', 'mactrack'),
								6 => __esc('Authorization Success', 'mactrack'),
								7 => __esc('Authorization Failed', 'mactrack'),
							);

							foreach($all_status as $i => $status) {
								print "<option value='$i'" . (get_request_var('status') == $i ? ' selected':'') . '>' . $status . '</option>';
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Show', 'mactrack');?>
					</td>
					<td>
						<select id='scan_date' onChange='applyFilter()'>
							<option value='1'<?php if (get_request_var('scan_date') == '1') {?> selected<?php }?>><?php print __('All', 'mactrack');?></option>
							<option value='2'<?php if (get_request_var('scan_date') == '2') {?> selected<?php }?>><?php print __('Most Recent', 'mactrack');?></option>
							<?php
							$scan_dates = db_fetch_assoc('SELECT DISTINCT scan_date
								FROM mac_track_dot1x
								ORDER BY scan_date
								DESC LIMIT 10');

							if (cacti_sizeof($scan_dates)) {
								foreach ($scan_dates as $scan_date) {
									print '<option value="' . $scan_date['scan_date'] . '"' . (get_request_var('scan_date') == $scan_date['scan_date'] ? ' selected':'') . '>' . $scan_date['scan_date'] . '</option>';
								}
							}
							?>
						</select>
					</td>
				</tr>
				<tr>
					<td>
						<?php print __('MAC', 'mactrack');?>
					</td>
					<td>
						<select id='mac_filter_type_id'>
							<?php
							foreach($mactrack_search_types as $i => $type) {
								print "<option value='$i'" . (get_request_var('mac_filter_type_id') == $i ? ' selected':'') . '>' . $type . '</option>';
							}
							?>
						</select>
					</td>
					<td>
						<input type='text' id='mac_filter' size='25' value='<?php print html_escape_request_var('mac_filter');?>'>
					</td>
					<td>
						<?php print __('Domain', 'mactrack');?>
					</td>
					<td>
						<select id='domain' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('domain') == '-1') {?> selected<?php }?>><?php print __('All', 'mactrack');?></option>
							<option value='2'<?php if (get_request_var('domain') == '2') {?> selected<?php }?>><?php print __('DATA', 'mactrack');?></option>
							<option value='3'<?php if (get_request_var('domain') == '3') {?> selected<?php }?>><?php print __('VOICE', 'mactrack');?></option>
						</select>
					</td>
				</tr>
				<tr>
					<td>
						<?php print __('Portname', 'mactrack');?>
					</td>
					<td>
						<select id='port_name_filter_type_id'>
							<?php
							foreach($mactrack_search_types as $i => $type) {
								print "<option value='$i'" . (get_request_var('port_name_filter_type_id') == $i ? ' selected':'') . '>' . $type . '</option>';
							}
							?>
						</select>
					</td>
					<td>
						<input type='text' id='port_name_filter' size='25' value='<?php print html_escape_request_var('port_name_filter');?>'>
					</td>
					<td colspan='2'>
					</td>
				</tr>
			</table>
			</form>
			<script type='text/javascript'>

			function applyFilter() {
				strURL  = urlPath+'plugins/mactrack/mactrack_view_dot1x.php?report=dot1x&header=false';
				strURL += '&site_id=' + $('#site_id').val();
				strURL += '&device_id=' + $('#device_id').val();
				strURL += '&rows=' + $('#rows').val();
				strURL += '&mac_filter_type_id=' + $('#mac_filter_type_id').val();
				strURL += '&mac_filter=' + $('#mac_filter').val();
				strURL += '&filter=' + $('#filter').val();
				strURL += '&ip_filter_type_id=' + $('#ip_filter_type_id').val();
				strURL += '&ip_filter=' + $('#ip_filter').val();
				strURL += '&port_name_filter_type_id=' + $('#port_name_filter_type_id').val();
				strURL += '&port_name_filter=' + $('#port_name_filter').val();
				strURL += '&scan_date=' + $('#scan_date').val();
				strURL += '&domain=' + $('#domain').val();
				strURL += '&status=' + $('#status').val();

				loadPageNoHeader(strURL);
			}

			function clearFilter() {
				strURL  = urlPath+'plugins/mactrack/mactrack_view_dot1x.php?report=dot1x&header=false&clear=true';
				loadPageNoHeader(strURL);
			}

			function exportRows() {
				strURL  = urlPath+'plugins/mactrack/mactrack_view_dot1x.php?report=dot1x&export=true';
				document.location = strURL;
			}

			$(function() {
				$('#mactrack').submit(function(event) {
					event.preventDefault();
					applyFilter();
				});

				$('#clear').click(function() {
					clearFilter();
				});

				$('#export').click(function() {
					exportRows();
				});
			});

			</script>
		</td>
	</tr>
	<?php
}

