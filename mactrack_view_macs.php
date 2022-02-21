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
include_once('./plugins/mactrack/lib/mactrack_functions.php');

$title = __('Device Tracking - MAC to IP Report View', 'mactrack');

$mactrack_view_macs_actions = array(
	1 => __('Authorize', 'mactrack'),
	2 => __('Revoke', 'mactrack')
);

$mactrack_view_agg_macs_actions = array(
	'01' => __('Delete', 'mactrack')
);

set_default_action();

switch (get_request_var('action')) {
case 'actions':
	if (get_nfilter_request_var('drp_action') !== '01') {
		form_actions();
	} else {
		form_aggregated_actions();
	}

	break;
default:
	if (isset_request_var('export')) {
		mactrack_view_export_macs();
	} else {
		general_header();

		mactrack_view_macs_validate_request_vars();

		if (isset_request_var('scan_date') && get_nfilter_request_var('scan_date') == 3) {
			mactrack_view_aggregated_macs();
		} elseif(isset_request_var('scan_date')) {
			mactrack_view_macs();
		} else {
			if (isset($_SESSION['sess_mtv_macs_rowstoshow']) && ($_SESSION['sess_mtv_macs_rowstoshow'] != 3)) {
				mactrack_view_macs();
			} else {
				mactrack_view_aggregated_macs();
			}
		}

		bottom_footer();
	}

	break;
}

/* ------------------------
    The 'actions' function
   ------------------------ */

function form_actions() {
	global $config, $mactrack_view_macs_actions;

	/* ================= input validation ================= */
	get_filter_request_var('drp_action');
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = unserialize(stripslashes(get_nfilter_request_var('selected_items')));

		if (cacti_sizeof($selected_items)) {
			if (get_request_var('drp_action') == '1') { /* Authorize */
				if (cacti_sizeof($selected_items)) {
					foreach($selected_items as $mac) {
						$mac = sanitize_search_string($mac);
						api_mactrack_authorize_mac_addresses($mac);
					}
				}
			} elseif (get_request_var('drp_action') == '2') { /* Revoke */
				$errors = '';
				if (cacti_sizeof($selected_items)) {
					foreach($selected_items as $mac) {
						$mac = sanitize_search_string($mac);
						$mac_found = db_fetch_cell_prepared('SELECT mac_address FROM mac_track_macauth WHERE mac_address = ?', array($mac));

						if ($mac_found) {
							api_mactrack_revoke_mac_addresses($mac);
						} else {
							$errors .= ', ' . $mac;
						}
					}
				}

				if ($errors) {
					$_SESSION['sess_messages'] = __('The following MAC Addresses Could not be revoked because they are members of Group Authorizations %s', $errors, 'mactrack');
				}
			}
		}

		header('Location: mactrack_view_macs.php');
		exit;
	}

	/* setup some variables */
	$mac_address_list = '';
	$delim = read_config_option('mt_mac_delim');

	/* loop through each of the device types selected on the previous page and get more info about them */
	foreach ($_POST as $var => $val) {
		if (substr($var,0,4) == 'chk_') {
			$matches = substr($var,4);

			/* clean up the mac_address */
			if (isset($matches)) {
				$matches = sanitize_search_string($matches);
				$parts   = explode('-', $matches);
				$mac     = str_replace('_', $delim, $parts[0]);
			}

			if (!isset($mac_address_array[$mac])) {
				$mac_address_list .= '<li>' . $mac . '</li>';
				$mac_address_array[$mac] = $mac;
			}
		}
	}

	general_header();

	form_start('mactrack_view_macs.php');

	html_start_box($mactrack_view_macs_actions[get_request_var('drp_action')], '60%', '', '3', 'center', '');

	if (get_request_var('drp_action') == '1') { /* Authorize Macs */
		print "<tr>
			<td class='textArea'>
				<p>" . __('Click \'Continue\' to Authorize the following MAC Addresses.', 'mactrack') . "</p>
				<div class='itemlist'><ul>$mac_address_list</ul></div>
			</td>
		</tr>";
	} elseif (get_request_var('drp_action') == '2') { /* Revoke Macs */
		print "<tr>
			<td class='textArea'>
				<p>" . __('Click \'Continue\' to Revoke the following MAC Addresses.', 'mactrack') . "</p>
				<div class='itemlist'><ul>$mac_address_list</ul></div>
			</td>
		</tr>";
	}

	if (!isset($mac_address_array)) {
		print "<tr><td class='even'><span class='textError'>" . __('You must select at least one MAC Address.', 'mactrack') . '</span></td></tr>';
		$save_html = '';
	} elseif (!api_plugin_user_realm_auth('mactrack_macauth.php')) {
		print "<tr><td clsas='even'><span class='textError'>" . __('You are not permitted to change Mac Authorizations.', 'mactrack') . '</span></td></tr>';
		$save_html = '';
	} else {
		$save_html = "<input type='submit' name='save' value='" . __esc('Continue', 'mactrack') . "'>";
	}

	print "<tr>
		<td colspan='2' class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($mac_address_array) ? serialize($mac_address_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_request_var('drp_action') . "'>" . (strlen($save_html) ? "
			<input type='button' onClick='cactiReturnTo()' value='" . __esc('Cancel', 'mactrack') . "'>
			$save_html" : "<input type='button' onClick='cactiReturnTo()' value='" . __esc('Return', 'mactrack') . "'>") . "
		</td>
	</tr>";

	html_end_box();

	form_end();

	bottom_footer();
}

function form_aggregated_actions() {
	global $config, $mactrack_view_agg_macs_actions;

	/* ================= input validation ================= */
	get_filter_request_var('drp_action');
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
        $selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

        if ($selected_items != false) {
			if (get_request_var('drp_action') == '01') { /* Delete */
				if (cacti_sizeof($selected_items)) {
					db_execute('DELETE FROM mac_track_aggregated_ports WHERE row_id IN (' . implode(',', $selected_items) . ')');
				}
			}

			header('Location: mactrack_view_macs.php');
			exit;
		}
	}

	/* setup some variables */
	$row_array = array(); $mac_address_list = ''; $row_list = ''; $i = 0; $row_ids = '';

	/* loop through each of the ports selected on the previous page and get more info about them */
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$row_array[] = $matches[1];
		}
	}

	if (cacti_sizeof($row_array)) {
		$row_ids   = implode(',', $row_array);
		$rows_info = db_fetch_assoc('SELECT device_name, mac_address, ip_address, port_number, count_rec
			FROM mac_track_aggregated_ports
			WHERE row_id IN (' . implode(',', $row_array) . ')');

		if (isset($rows_info)) {
			foreach($rows_info as $row_info) {
				$row_list .= '<li>' . __('Dev.:%s IP.:%s MAC.:%s PORT.:%s Count.: [%s]', $row_info['device_name'], $row_info['ip_address'], $row_info['mac_address'],  $row_info['port_number'], $row_info['count_rec'], 'mactrack') . '</li>';
			}
		}
	}

	general_header();

	form_start('mactrack_view_macs.php');

	html_start_box($mactrack_view_agg_macs_actions[get_request_var('drp_action')], '60%', '', '3', 'center', '');

	if (!sizeof($row_array)) {
		print "<tr><td class='even'><span class='textError'>" . __('You must select at least one Row.', 'mactrack') . "</span></td></tr>\n";
		$save_html = "";
	} elseif (api_plugin_user_realm_auth('mactrack_macauth.php')) {
		print "<tr><td class='even'><span class='textError'>" . __('You are not permitted to delete rows.', 'mactrack') . "</span></td></tr>\n";
		$save_html = "";
	} else {
		$save_html = "<input type='submit' name='save' value='" . __esc('Continue', 'mactrack') . "'>";

		if (get_request_var('drp_action') == '1') { /* Delete Macs */
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to Delete the following rows from Aggregated table.', 'mactrack') . "</p>
					<ul>$row_list</ul>
				</td>
			</tr>";
		}
	}

	print "<tr>
		<td colspan='2' align='right' class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($row_array) ? serialize($row_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_request_var('drp_action') . "'>" . (strlen($save_html) ? "
			<input type='button' onClick='cactiReturnTo()' value='" . __esc('Cancel', 'macktrack') . "'>
			$save_html" : "<input type='button' onClick='cactiReturnTo()' value='" . __esc('Return', 'mactrack') . "'>") . "
		</td>
	</tr>";

	html_end_box();

	form_end();

	bottom_footer();
}

function api_mactrack_authorize_mac_addresses($mac_address){
	db_execute_prepared('UPDATE mac_track_ports
		SET authorized=1
		WHERE mac_address = ?', array($mac_address));

	db_execute_prepared('UPDATE mac_track_temp_ports
		SET authorized=1
		WHERE mac_address = ?', array($mac_address));

	db_execute_prepared('REPLACE INTO mac_track_macauth
		(mac_address, description, added_by)
		VALUES (?, ?, ?)',
		array($mac_address, 'Added from MacView', $_SESSION['sess_user_id']));
}

function api_mactrack_revoke_mac_addresses($mac_address){
	db_execute_prepared('UPDATE mac_track_ports
		SET authorized=0
		WHERE mac_address = ?',
		array($mac_address));

	db_execute_prepared('DELETE FROM mac_track_macauth
		WHERE mac_address = ?',
		array($mac_address));
}

function mactrack_view_macs_validate_request_vars() {
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
        'vlan' => array(
            'filter' => FILTER_VALIDATE_INT,
            'default' => '-1'
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
		'authorized' => array(
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

    validate_store_request_vars($filters, 'sess_mtv_macs');
    /* ================= input validation ================= */
}

function mactrack_view_export_macs() {
	mactrack_view_macs_validate_request_vars();

	$sql_where = '';

	$port_results = mactrack_view_get_mac_records($sql_where, 0, false);

	$xport_array = array();
	array_push($xport_array, '"site_name","hostname","device_name",' .
		'"vlan_id","vlan_name","mac_address","vendor_name",' .
		'"ip_address","dns_hostname","port_number","port_name","scan_date"');

	if (cacti_sizeof($port_results)) {
		foreach($port_results as $port_result) {
			if (get_request_var('scan_date') == 1) {
				$scan_date = $port_result['scan_date'];
			} else {
				$scan_date = $port_result['scan_date'];
			}

			array_push($xport_array,'"' . $port_result['site_name'] . '","' .
			$port_result['hostname'] . '","' . $port_result['device_name'] . '","' .
			$port_result['vlan_id'] . '","' . $port_result['vlan_name'] . '","' .
			$port_result['mac_address'] . '","' . $port_result['vendor_name'] . '","' .
			$port_result['ip_address'] . '","' . $port_result['dns_hostname'] . '","' .
			$port_result['port_number'] . '","' . $port_result['port_name'] . '","' .
			$scan_date . '"');
		}
	}

	header('Content-type: application/csv');
	header('Content-Disposition: attachment; filename=cacti_port_macs_xport.csv');
	foreach($xport_array as $xport_line) {
		print $xport_line . "\n";
	}
}

function mactrack_view_get_mac_records(&$sql_where, $apply_limits = true, $rows) {
	/* form the 'where' clause for our main sql query */
	if (get_request_var('mac_filter') != '') {
		switch (get_request_var('mac_filter_type_id')) {
		case '1': /* do not filter */
			break;
		case '2': /* matches */
			$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mtp.mac_address = ' . db_qstr(get_request_var('mac_filter'));
			break;
		case '3': /* contains */
			$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mtp.mac_address LIKE ' . db_qstr('%' . get_request_var('mac_filter') . '%');
			break;
		case '4': /* begins with */
			$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mtp.mac_address LIKE ' . db_qstr(get_request_var('mac_filter') . '%');
			break;
		case '5': /* does not contain */
			$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mtp.mac_address NOT LIKE ' . db_qstr('%' . get_request_var('mac_filter') . '%');
			break;
		case '6': /* does not begin with */
			$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mtp.mac_address NOT LIKE ' . db_qstr(get_request_var('mac_filter') . '%');
		}
	}

	if ((get_request_var('ip_filter') != '') || (get_request_var('ip_filter_type_id') > 6)) {
		switch (get_request_var('ip_filter_type_id')) {
		case '1': /* do not filter */
			break;
		case '2': /* matches */
			$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mtp.ip_address = ' . db_qstr(get_request_var('ip_filter'));
			break;
		case '3': /* contains */
			$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mtp.ip_address LIKE ' . db_qstr('%' . get_request_var('ip_filter') . '%');
			break;
		case '4': /* begins with */
			$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mtp.ip_address LIKE ' . db_qstr(get_request_var('ip_filter') . '%');
			break;
		case '5': /* does not contain */
			$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mtp.ip_address NOT LIKE ' . db_qstr('%' . get_request_var('ip_filter') . '%');
			break;
		case '6': /* does not begin with */
			$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mtp.ip_address NOT LIKE ' . db_qstr(get_request_var('ip_filter') . '%');
			break;
		case '7': /* is null */
			$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mtp.ip_address = ""';
			break;
		case '8': /* is not null */
			$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mtp.ip_address != ""';
		}
	}

	if ((get_request_var('port_name_filter') != '') || (get_request_var('port_name_filter_type_id') > 6)) {
		switch (get_request_var('port_name_filter_type_id')) {
		case '1': /* do not filter */
			break;
		case '2': /* matches */
			$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mtp.port_name = ' . db_qstr(get_request_var('port_name_filter'));
			break;
		case '3': /* contains */
			$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mtp.port_name LIKE ' . db_qstr('%' . get_request_var('port_name_filter') . '%');
			break;
		case '4': /* begins with */
			$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mtp.port_name LIKE ' . db_qstr(get_request_var('port_name_filter') . '%');
			break;
		case '5': /* does not contain */
			$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mtp.port_name NOT LIKE ' . db_qstr('%' . get_request_var('port_name_filter') . '%');
			break;
		case '6': /* does not begin with */
			$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mtp.port_name NOT LIKE ' . db_qstr(get_request_var('port_name_filter') . '%');
			break;
		case '7': /* is null */
			$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mtp.port_name = ""';
			break;
		case '8': /* is not null */
			$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mtp.port_name != ""';
			break;
		}
	}

	if (get_request_var('filter') != '') {
		if (strlen(read_config_option('mt_reverse_dns'))) {
			$sql_where .= ($sql_where != '' ? ' AND':'WHERE') .
				' (mtp.dns_hostname LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ' OR ' .
				'mtp.device_name LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ' OR ' .
				'mtp.hostname LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ' OR ' .
				'mtod.vendor_name LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ' OR ' .
				'mtp.vlan_name LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ')';
		} else {
			$sql_where .= ($sql_where != '' ? ' AND':'WHERE') .
				' (mtp.device_name LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ' OR ' .
				'mtp.hostname LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ' OR ' .
				'mtod.vendor_name LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ' OR ' .
				'mtp.vlan_name LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ')';
		}
	}

	if (get_request_var('authorized') != '-1') {
		$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mtp.authorized = ' . get_request_var('authorized');
	}

	if (get_request_var('site_id') != '-1') {
		$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mtp.site_id = ' . get_request_var('site_id');
	}

	if (get_request_var('vlan') != '-1') {
		$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mtp.vlan_id = ' . get_request_var('vlan');
	}

	if (get_request_var('device_id') != '-1') {
		$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mtp.device_id = ' . get_request_var('device_id');
	}

	if ((get_request_var('scan_date') != '1') && (get_request_var('scan_date') != '2') && (get_request_var('scan_date') != '3')) {
		$sql_where .= ($sql_where != '' ? ' AND':'WHERE') . ' mtp.scan_date = ' . db_qstr(get_request_var('scan_date'));
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

	if (get_request_var('scan_date') == 3) {
		$query_string = "SELECT
			row_id, site_name, device_id, device_name, hostname, mtp.mac_address,
			vendor_name, ip_address, dns_hostname, port_number,
			port_name, vlan_id, vlan_name, MAX(date_last) AS scan_date, COUNT(count_rec) AS count_rec, active_last, mtm.mac_id
			FROM mac_track_aggregated_ports AS mtp
			LEFT JOIN mac_track_sites AS mts
			ON mtp.site_id = mts.site_id
			LEFT JOIN mac_track_macauth AS mtm
			ON mtm.mac_address = mtp.mac_address
			LEFT JOIN mac_track_oui_database AS mtod
			ON mtod.vendor_mac = mtp.vendor_mac
			$sql_where
			GROUP BY device_id, ip_address, mtp.mac_address
			$sql_order
			$sql_limit";
	} elseif ((get_request_var('scan_date') != 2)) {
		$query_string = "SELECT
			site_name, device_id, device_name, hostname, mtp.mac_address,
			vendor_name, ip_address, dns_hostname, port_number,
			port_name, vlan_id, vlan_name, scan_date, mtm.mac_id
			FROM mac_track_ports AS mtp
			LEFT JOIN mac_track_sites AS mts
			ON mtp.site_id = mts.site_id
			LEFT JOIN mac_track_macauth AS mtm
			ON mtm.mac_address = mtp.mac_address
			LEFT JOIN mac_track_oui_database AS mtod
			ON mtod.vendor_mac = mtp.vendor_mac
			$sql_where
			$sql_order
			$sql_limit";
	} else {
		$query_string = "SELECT
			site_name, device_id, device_name, hostname, mtp.mac_address,
			vendor_name, ip_address, dns_hostname, port_number,
			port_name, vlan_id, vlan_name, MAX(scan_date) AS scan_date, mtm.mac_id
			FROM mac_track_ports AS mtp
			LEFT JOIN mac_track_sites AS mts
			ON mtp.site_id = mts.site_id
			LEFT JOIN mac_track_macauth AS mtm
			ON mtm.mac_address = mtp.mac_address
			LEFT JOIN mac_track_oui_database AS mtod
			ON mtod.vendor_mac = mtp.vendor_mac
			$sql_where
			GROUP BY device_id, mtp.mac_address, port_number, ip_address
			$sql_order
			$sql_limit";
	}

	if ($sql_where == '') {
		return array();
	} else {
		return db_fetch_assoc($query_string);
	}
}

function mactrack_view_macs() {
	global $title, $report, $mactrack_search_types, $rows_selector, $config;
	global $mactrack_view_macs_actions, $item_rows;

	mactrack_tabs();

	html_start_box($title, '100%', '', '3', 'center', '');
	mactrack_mac_filter();
	html_end_box();

	$sql_where = '';

	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} elseif (get_request_var('rows') == -2) {
		$rows = 999999;
	} else {
		$rows = get_request_var('rows');
	}

	$port_results = mactrack_view_get_mac_records($sql_where, true, $rows);

	/* prevent table scans, either a device or site must be selected */
	if ($sql_where == '') {
		$total_rows = 0;
	} elseif (get_request_var('scan_date') == 1) {
		$rows_query_string = "SELECT
			COUNT(mtp.device_id)
			FROM mac_track_ports AS mtp
			LEFT JOIN mac_track_sites AS mts
			ON mtp.site_id = mts.site_id
			LEFT JOIN mac_track_oui_database AS mtod
			ON mtod.vendor_mac = mtp.vendor_mac
			$sql_where";

		$total_rows = db_fetch_cell($rows_query_string);
	} else {
		$rows_query_string = "SELECT
			COUNT(DISTINCT device_id, mac_address, port_number, ip_address)
			FROM mac_track_ports AS mtp
			LEFT JOIN mac_track_sites AS mts
			ON mtp.site_id = mts.site_id
			LEFT JOIN mac_track_oui_database AS mtod
			ON mtod.vendor_mac = mtp.vendor_mac
			$sql_where";

		$total_rows = db_fetch_cell($rows_query_string);
	}

	$display_text1 = array(
		'nosort' => array(
			'display' => __('Actions', 'mactrack'),
		),
		'device_name' => array(
			'display' => __('Switch Name', 'mactrack'),
			'sort' => 'ASC'
		),
		'hostname' => array(
			'display' => __('Switch Hostname', 'mactrack'),
			'sort'    => 'ASC'
		),
		'ip_address' => array(
			'display' => __('ED IP Address', 'mactrack'),
			'sort'    => 'ASC'
		)
	);

	$display_text2 = array();

	if (read_config_option('mt_reverse_dns') != '') {
		$display_text2 = array(
			'dns_hostname' => array(
				'display' => __('ED DNS Hostname', 'mactrack'),
				'sort'    => 'ASC'
			)
		);
	}

	$display_text3 = array(
		'mac_address' => array(
			'display' => __('ED MAC Address', 'mactrack'),
			'sort'    => 'ASC'
		),
		'authorized' => array(
			'display' => __('Authorized', 'mactrack'),
			'sort'    => 'ASC'
		),
		'vendor_name' => array(
			'display' => __('Vendor Name', 'mactrack'),
			'sort'    => 'ASC'
		),
		'port_number' => array(
			'display' => __('Port Number', 'mactrack'),
			'align'   => 'right',
			'sort'    => 'DESC'
		),
		'port_name' => array(
			'display' => __('Port Name', 'mactrack'),
			'align'   => 'right',
			'sort'    => 'ASC'
		),
		'vlan_id' => array(
			'display' => __('VLAN ID', 'mactrack'),
			'align'   => 'right',
			'sort'    => 'DESC'
		),
		'vlan_name' => array(
			'display' => __('VLAN Name', 'mactrack'),
			'align'   => 'right',
			'sort'    => 'ASC'
		),
		'scan_date' => array(
			'display' => __('Last Scan Date', 'mactrack'),
			'align'   => 'right',
			'sort'    => 'DESC'
		)
	);

	$display_text = array_merge($display_text1, $display_text2, $display_text3);

	if (api_plugin_user_realm_auth('mactrack_macauth.php')) {
		$columns = sizeof($display_text) + 1;
	} else {
		$columns = sizeof($display_text);
	}

	$nav = html_nav_bar('mactrack_view_macs.php?report=macs', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, $columns, __('MAC Addresses', 'mactrack'), 'page', 'main');

	if (api_plugin_user_realm_auth('mactrack_macauth.php')) {
		form_start('mactrack_view_macs.php');
	}

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	if (api_plugin_user_realm_auth('mactrack_macauth.php')) {
		html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'));
	} else {
		html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'));
	}

	$delim = read_config_option('mt_mac_delim');
	if (cacti_sizeof($port_results)) {
		foreach ($port_results as $port_result) {
			if (get_request_var('scan_date') != 2) {
				$scan_date = $port_result['scan_date'];
			} else {
				$scan_date = $port_result['scan_date'];
			}

			$key =  str_replace($delim, '_', $port_result['mac_address']) . '-' . $port_result['device_id'] .
				$port_result['port_number'] . '-' . strtotime($scan_date);

			form_alternate_row('line' . $key, true);
			form_selectable_cell(mactrack_interface_actions($port_result['device_id'], $port_result['port_number'], false), $key);
			form_selectable_cell($port_result['device_name'], $key);
			form_selectable_cell($port_result['hostname'], $key);
			form_selectable_cell(filter_value($port_result['ip_address'], get_request_var('filter')), $key);

			if (read_config_option('mt_reverse_dns') == 'on') {
				form_selectable_cell(filter_value($port_result['dns_hostname'], get_request_var('filter')), $key);
			}

			if ($port_result['mac_id'] > 0) {
				$auth = '<span class="deviceUp">' . __('Authorized', 'mactrack');
			} else {
				$auth = '<span class="deviceDown">' . __('Not Authorized', 'mactrack');
			}

			form_selectable_cell(filter_value($port_result['mac_address'], get_request_var('filter')), $key);
			form_selectable_cell($auth, $key);
			form_selectable_cell(filter_value($port_result['vendor_name'], get_request_var('filter')), $key);
			form_selectable_cell($port_result['port_number'], $key, '', 'right');
			form_selectable_cell(filter_value($port_result['port_name'], get_request_var('filter')), $key, '', 'right');
			form_selectable_cell($port_result['vlan_id'], $key, '', 'right');
			form_selectable_cell(filter_value($port_result['vlan_name'], get_request_var('filter')), $key, '', 'right');
			form_selectable_cell($scan_date, $key, '', 'right');

			if (api_plugin_user_realm_auth('mactrack_macauth.php')) {
				form_checkbox_cell($port_result['mac_address'], $key);
			}

			form_end_row();
		}
	} else {
		if (get_request_var('site_id') == -1 && get_request_var('device_id') == -1) {
			print "<tr><td colspan='$columns'><em>" . __('You must choose a Site, Device or other search criteria.', 'mactrack') . "</em></td></tr>";
		} else {
			print "<tr><td colspan='$columns'><em>" . __('No Device Tracking Port Results Found', 'mactrack') . "</em></td></tr>";
		}
	}

	html_end_box(false);

	if (cacti_sizeof($port_results)) {
		print $nav;
	}

	if (api_plugin_user_realm_auth('mactrack_macauth.php')) {
		draw_actions_dropdown($mactrack_view_macs_actions);

		form_end();
	}
}

function mactrack_view_aggregated_macs() {
	global $title, $report, $mactrack_search_types, $rows_selector, $config;
	global $mactrack_view_agg_macs_actions, $item_rows;

	mactrack_tabs();

	html_start_box($title, '100%', '', '3', 'center', '');
	mactrack_mac_filter();
	html_end_box();

	$sql_where = '';

	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} elseif (get_request_var('rows') == -2) {
		$rows = 999999;
	} else {
		$rows = get_request_var('rows');
	}

	$port_results = mactrack_view_get_mac_records($sql_where, true, $rows);

	/* prevent table scans, either a device or site must be selected */
	if ($sql_where == '') {
		$total_rows = 0;
	} else {
		$rows_query_string = "SELECT
			COUNT(DISTINCT device_id, ip_address, mtp.mac_address)
			FROM mac_track_aggregated_ports AS mtp
			LEFT JOIN mac_track_sites AS mts
			ON mtp.site_id = mts.site_id
			LEFT JOIN mac_track_macauth AS mtm
			ON mtm.mac_address = mtp.mac_address
			LEFT JOIN mac_track_oui_database AS mtod
			ON mtod.vendor_mac = mtp.vendor_mac
			$sql_where";

		$total_rows = db_fetch_cell($rows_query_string);
	}

	$display_text = array(
		'device_name' => array(
			'display' => __('Switch Name', 'mactrack'),
			'sort'    => 'ASC'
		),
		'hostname'    => array(
			'display' => __('Switch Hostname', 'mactrack'),
			'sort'    => 'ASC'
		),
		'ip_address'  => array(
			'display' => __('ED IP Address', 'mactrack'),
			'sort'    => 'ASC'
		)
	);

	if (read_config_option('mt_reverse_dns') == 'on') {
		$display_text['dns_hostname'] = array(
			'display' => __('ED DNS Hostname', 'mactrack'),
			'sort'    => 'ASC'
		);
	}

	$display_text = array_merge($display_text, array(
		'mac_address' => array(
			'display' => __('ED MAC Address', 'mactrack'),
			'sort'    => 'ASC'
		),
		'authorized' => array(
			'display' => __('Authorized', 'mactrack'),
			'sort'    => 'ASC'
		),
		'vendor_name' => array(
			'display' => __('Vendor Name', 'mactrack'),
			'sort'    => 'ASC'
		),
		'port_number' => array(
			'display' => __('Port Number', 'mactrack'),
			'align'   => 'right',
			'sort'    => 'DESC'
		),
		'port_name'   => array(
			'display' => __('Port Name', 'mactrack'),
			'align'   => 'right',
			'sort'    => 'ASC'
		),
		'vlan_id'     => array(
			'display' => __('VLAN ID', 'mactrack'),
			'align'   => 'right',
			'sort'    => 'DESC'
		),
		'vlan_name'   => array(
			'display' => __('VLAN Name', 'mactrack'),
			'align'   => 'right',
			'sort'    => 'ASC'
		)
	));

	if (get_request_var('rows') == 1) {
		$display_text['scan_date'] = array(
			'display' => __('Last Scan Date', 'mactrack'),
			'align'   => 'right',
			'sort'    => 'DESC'
		);
	} else {
		$display_text['scan_date'] = array(
			'display' => __('Last Scan Date', 'mactrack'),
			'align'   => 'right',
			'sort'    => 'DESC'
		);
	}

	if (get_request_var('scan_date') == 3) {
		$display_text['count_rec'] = array(
			'display' => __('Count', 'mactrack'),
			'align'   => 'right',
			'sort'    => 'ASC'
		);
	}

	if (api_plugin_user_realm_auth('mactrack_macauth.php')) {
		$columns = sizeof($display_text) + 1;
	} else {
		$columns = sizeof($display_text);
	}

	$nav = html_nav_bar('mactrack_view_macs.php?report=macs&scan_date=3', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, $columns, __('MAC Addresses', 'mactrack'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	if (api_plugin_user_realm_auth('mactrack_macauth.php')) {
		html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'));
	} else {
		html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'));
	}

	$i = 0;
	$delim = read_config_option('mt_mac_delim');
	if (cacti_sizeof($port_results)) {
		foreach ($port_results as $port_result) {
			if ($port_result['active_last'] == 1)  {
				$color_line_date="<span style='font-weight: bold;'>";
			} else {
				$color_line_date='';
			}

			$key =  str_replace($delim, '_', $port_result['mac_address']) . '-' . $port_result['device_id'] .
					$port_result['port_number'] . '-' . $port_result['scan_date'];

			$key = $port_result['row_id'];

			form_alternate_row('line' . $key, true);
			form_selectable_cell(filter_value($port_result['device_name'], get_request_var('filter')), $key);
			form_selectable_cell(filter_value($port_result['hostname'], get_request_var('filter')), $key);
			form_selectable_cell(filter_value($port_result['ip_address'], get_request_var('filter')), $key);

			if (strlen(read_config_option('mt_reverse_dns')) > 0) {
				form_selectable_cell(filter_value($port_result['dns_hostname'], get_request_var('filter')), $key);
			}

			if ($port_result['mac_id'] > 0) {
				$auth = '<span class="deviceUp">' . __('Authorized', 'mactrack');
			} else {
				$auth = '<span class="deviceDown">' . __('Not Authorized', 'mactrack');
			}

			form_selectable_cell(filter_value($port_result['mac_address'], get_request_var('filter')), $key);
			form_selectable_cell($auth, $key);
			form_selectable_cell(filter_value($port_result['vendor_name'], get_request_var('filter')), $key);
			form_selectable_cell($port_result['port_number'], $key, '', 'right');
			form_selectable_cell(filter_value($port_result['port_name'], get_request_var('filter')), $key, '', 'right');
			form_selectable_cell($port_result['vlan_id'], $key, '', 'right');
			form_selectable_cell(filter_value($port_result['vlan_name'], get_request_var('filter')), $key, '', 'right');
			form_selectable_cell($color_line_date . $port_result['scan_date'], $key, '', 'right');
			form_selectable_cell($port_result['count_rec'], $key, '', 'right');

			if (api_plugin_user_realm_auth('mactrack_macauth.php')) {
				form_checkbox_cell($port_result['mac_address'], $key);
			}

			form_end_row();
		}
	} else {
		if (get_request_var('site_id') == -1 && get_request_var('device_id') == -1) {
			print "<tr><td colspan='10'><em>" . __('You must first choose a Site, Device or other search criteria.', 'mactrack') . "</em></td></tr>";
		} else {
			print "<tr><td colspan='10'><em>" . __('No Device Tracking Port Results Found', 'mactrack') . "</em></td></tr>";
		}
	}

	html_end_box(false);

	if (cacti_sizeof($port_results)) {
		print $nav;
		mactrack_display_stats();
	}

	if (api_plugin_user_realm_auth('mactrack_macauth.php')) {
		/* draw the dropdown containing a list of available actions for this form */
		draw_actions_dropdown($mactrack_view_agg_macs_actions);
	}
}

function mactrack_mac_filter() {
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
						<input type='text' id='filter' size='25' value='<?php print get_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Site', 'mactrack');?>
					</td>
					<td>
						<select id='site_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('site_id') == '-1') {?> selected<?php }?>><?php print __('N/A', 'mactrack');?></option>
							<?php
							$sites = db_fetch_assoc('SELECT site_id,site_name FROM mac_track_sites ORDER BY site_name');
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
								$filter_devices = db_fetch_assoc('SELECT device_id, device_name, hostname
									FROM mac_track_devices
									ORDER BY device_name');
							} else {
								$filter_devices = db_fetch_assoc_prepared('SELECT device_id, device_name, hostname
									FROM mac_track_devices
									WHERE site_id = ?
									ORDER BY device_name',
									array(get_request_var('site_id')));
							}

							if (cacti_sizeof($filter_devices)) {
								foreach ($filter_devices as $filter_device) {
									print '<option value=" ' . $filter_device['device_id'] . '"'; if (get_request_var('device_id') == $filter_device['device_id']) { print ' selected'; } print '>' . $filter_device['device_name'] . '(' . $filter_device['hostname'] . ')' .  '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('MAC\'s', 'mactrack');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<?php
							if (cacti_sizeof($rows_selector)) {
								foreach ($rows_selector as $key => $value) {
									print '<option value="' . $key . '"'; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . $value . '</option>\n';
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
							for($i=1;$i<=sizeof($mactrack_search_types);$i++) {
								print "<option value='" . $i . "'"; if (get_request_var('ip_filter_type_id') == $i) { print ' selected'; } print '>' . $mactrack_search_types[$i] . '</option>';
							}
							?>
						</select>
					</td>
					<td>
						<input type='text' id='ip_filter' size='25' value='<?php print html_escape_request_var('ip_filter');?>'>
					</td>
					<td>
						<?php print __('VLAN Name', 'mactrack');?>
					</td>
					<td>
						<select id='vlan' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('vlan') == '-1') {?> selected<?php }?>><?php print __('All', 'mactrack');?></option>
							<?php
							$sql_where = '';
							if (get_request_var('device_id') != '-1') {
								$sql_where = 'WHERE device_id=' . get_request_var('device_id');
							}

							if (get_request_var('site_id') != '-1') {
								if ($sql_where != '') {
									$sql_where .= ' AND site_id=' . get_request_var('site_id');
								} else {
									$sql_where = 'WHERE site_id=' . get_request_var('site_id');
								}
							}

							$vlans = db_fetch_assoc("SELECT DISTINCT vlan_id, vlan_name
								FROM mac_track_vlans
								$sql_where
								ORDER BY vlan_name ASC");

							if (cacti_sizeof($vlans)) {
								foreach ($vlans as $vlan) {
									print '<option value="' . $vlan['vlan_id'] . '"'; if (get_request_var('vlan') == $vlan['vlan_id']) { print ' selected'; } print '>' . $vlan['vlan_name'] . '</option>';
								}
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
							<option value='3'<?php if (get_request_var('scan_date') == '3') {?> selected<?php }?>><?php print __('Aggregated', 'mactrack');?></option>
							<?php

							$scan_dates = db_fetch_assoc('SELECT scan_date FROM mac_track_scan_dates ORDER BY scan_date DESC');
							if (cacti_sizeof($scan_dates)) {
								foreach ($scan_dates as $scan_date) {
									print '<option value="' . $scan_date['scan_date'] . '"'; if (get_request_var('scan_date') == $scan_date['scan_date']) { print ' selected'; } print '>' . $scan_date['scan_date'] . '</option>';
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
							for($i=1;$i<=sizeof($mactrack_search_types)-2;$i++) {
								print "<option value='" . $i . "'"; if (get_request_var('mac_filter_type_id') == $i) { print ' selected'; } print '>' . $mactrack_search_types[$i] . '</option>';
							}
							?>
						</select>
					</td>
					<td>
						<input type='text' id='mac_filter' size='25' value='<?php print html_escape_request_var('mac_filter');?>'>
					</td>
					<td>
						<?php print __('Authorized', 'mactrack');?>
					</td>
					<td>
						<select id='authorized' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('authorized') == '-1') {?> selected<?php }?>><?php print __('All', 'mactrack');?></option>
							<option value='1'<?php if (get_request_var('authorized') == '1') {?> selected<?php }?>><?php print __('Yes', 'mactrack');?></option>
							<option value='0'<?php if (get_request_var('authorized') == '0') {?> selected<?php }?>><?php print __('No', 'mactrack');?></option>
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
							for($i=1;$i<=sizeof($mactrack_search_types);$i++) {
								print "<option value='" . $i . "'"; if (get_request_var('port_name_filter_type_id') == $i) { print ' selected'; } print '>' . $mactrack_search_types[$i] . '</option>';
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
				strURL  = urlPath+'plugins/mactrack/mactrack_view_macs.php?report=macs&header=false';
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
				strURL += '&authorized=' + $('#authorized').val();
				strURL += '&vlan=' + $('#vlan').val();

				loadPageNoHeader(strURL);
			}

			function clearFilter() {
				strURL  = urlPath+'plugins/mactrack/mactrack_view_macs.php?report=macs&header=false&clear=true';
				loadPageNoHeader(strURL);
			}

			function exportRows() {
				strURL  = urlPath+'plugins/mactrack/mactrack_view_macs.php?report=macs&export=true';
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

