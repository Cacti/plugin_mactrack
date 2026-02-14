<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2025 The Cacti Group                                 |
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

include './include/auth.php';

include_once './plugins/mactrack/lib/mactrack_functions.php';

$title = __('Mactrack - MAC to IP Report View', 'mactrack');

$mactrack_view_macs_actions = [
    1 => __('Authorize', 'mactrack'),
    2 => __('Revoke', 'mactrack'),
];

$mactrack_view_agg_macs_actions = [
    3 => __('Delete', 'mactrack'),
];

set_default_action();

switch (get_request_var('action')) {
    case 'actions':
        if ('3' !== get_nfilter_request_var('drp_action')) {
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

            if (isset_request_var('scan_date') && 3 == get_nfilter_request_var('scan_date')) {
                mactrack_view_aggregated_macs();
            } elseif (isset_request_var('scan_date')) {
                mactrack_view_macs();
            } else {
                if (isset($_SESSION['sess_mtv_macs_rowstoshow']) && (3 != $_SESSION['sess_mtv_macs_rowstoshow'])) {
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

function form_actions()
{
    global $config, $mactrack_view_macs_actions;

    // ================= input validation =================
    get_filter_request_var('drp_action');
    // ====================================================

    // if we are to save this form, instead of display it
    if (isset_request_var('selected_items')) {
        $selected_items = unserialize(get_nfilter_request_var('selected_items'));

        foreach ($selected_items as $mac => $ip) {
            if (!filter_var($mac, FILTER_VALIDATE_MAC)) {
                unset($selected_items[$mac]);
            } elseif (!filter_var($ip, FILTER_VALIDATE_IP)) {
                unset($selected_items[$mac]);
            }
        }

        if (cacti_sizeof($selected_items)) {
            if ('1' == get_request_var('drp_action')) { // Authorize
                if (cacti_sizeof($selected_items)) {
                    foreach ($selected_items as $mac => $ip) {
                        api_mactrack_authorize_mac_addresses($mac, $ip);
                    }
                }
            } elseif ('2' == get_request_var('drp_action')) { // Revoke
                $errors = '';
                if (cacti_sizeof($selected_items)) {
                    foreach ($selected_items as $mac => $ip) {
                        $mac_found = db_fetch_cell_prepared('SELECT mac_address FROM mac_track_macauth WHERE mac_address = ?', [$mac]);

                        if ($mac_found) {
                            api_mactrack_revoke_mac_addresses($mac);
                        } else {
                            $errors .= ', '.mactrack_format_mac($mac);
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

    // setup some variables
    $mac_address_list = '';

    // loop through each of the device types selected on the previous page and get more info about them
    foreach ($_POST as $var => $val) {
        if ('chk_' == substr($var, 0, 4)) {
            $matches = substr($var, 4);

            // clean up the mac_address
            if (isset($matches)) {
                $matches = sanitize_search_string($matches);
                $parts = explode('-', $matches);
                $mac = str_replace('_', '', $parts[0]);
                $ip = str_replace('_', '.', $parts[1]);
            }

            if (!isset($mac_address_array[$mac])) {
                $mac_address_list .= '<li>'.mactrack_format_mac($mac).'</li>';
                $mac_address_array[$mac] = $ip;
            }
        }
    }

    general_header();

    form_start('mactrack_view_macs.php');

    html_start_box($mactrack_view_macs_actions[get_request_var('drp_action')], '60%', '', '3', 'center', '');

    if ('1' == get_request_var('drp_action')) { // Authorize Macs
        echo "<tr>
			<td class='textArea'>
				<p>".__('Click \'Continue\' to Authorize the following MAC Addresses.', 'mactrack')."</p>
				<div class='itemlist'><ul>{$mac_address_list}</ul></div>
			</td>
		</tr>";
    } elseif ('2' == get_request_var('drp_action')) { // Revoke Macs
        echo "<tr>
			<td class='textArea'>
				<p>".__('Click \'Continue\' to Revoke the following MAC Addresses.', 'mactrack')."</p>
				<div class='itemlist'><ul>{$mac_address_list}</ul></div>
			</td>
		</tr>";
    }

    if (!isset($mac_address_array)) {
        echo "<tr><td class='even'><span class='textError'>".__('You must select at least one MAC Address.', 'mactrack').'</span></td></tr>';
        $save_html = '';
    } elseif (!api_plugin_user_realm_auth('mactrack_macauth.php')) {
        echo "<tr><td class='even'><span class='textError'>".__('You are not permitted to change Mac Authorizations.', 'mactrack').'</span></td></tr>';
        $save_html = '';
    } else {
        $save_html = "<button type='submit' name='save' class='ui-button ui-corner-all ui-widget ui-state-active'>".__esc('Continue', 'mactrack').'</button>';
    }

    echo "<tr>
		<td colspan='2' class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='".(isset($mac_address_array) ? serialize($mac_address_array) : '')."'>
			<input type='hidden' name='drp_action' value='".get_request_var('drp_action')."'>".('' != $save_html ? "
			<button type='button' class='ui-button ui-corner-all ui-widget' onClick='cactiReturnTo()'>".__esc('Cancel', 'mactrack')."</button>
			{$save_html}" : "<button type='button' class='ui-button ui-corner-all ui-widget' onClick='cactiReturnTo()'>".__esc('Return', 'mactrack').'</button>').'
		</td>
	</tr>';

    html_end_box();

    form_end();

    bottom_footer();
}

function form_aggregated_actions()
{
    global $config, $mactrack_view_agg_macs_actions;

    // ================= input validation =================
    get_filter_request_var('drp_action');
    // ====================================================

    // if we are to save this form, instead of display it
    if (isset_request_var('selected_items')) {
        $selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

        if (false != $selected_items) {
            if ('3' == get_request_var('drp_action')) { // Delete
                if (cacti_sizeof($selected_items)) {
                    db_execute('DELETE FROM mac_track_aggregated_ports WHERE row_id IN ('.implode(',', $selected_items).')');
                }
            }

            header('Location: mactrack_view_macs.php');

            exit;
        }
    }

    // setup some variables
    $row_array = [];
    $mac_address_list = '';
    $row_list = '';
    $i = 0;
    $row_ids = '';

    // loop through each of the ports selected on the previous page and get more info about them
    foreach ($_POST as $var => $val) {
        if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
            // ================= input validation =================
            input_validate_input_number($matches[1]);
            // ====================================================

            $row_array[] = $matches[1];
        }
    }

    if (cacti_sizeof($row_array)) {
        $row_ids = implode(',', $row_array);
        $rows_info = db_fetch_assoc('SELECT device_name, mac_address, ip_address, port_number, count_rec
			FROM mac_track_aggregated_ports
			WHERE row_id IN ('.implode(',', $row_array).')');

        if (isset($rows_info)) {
            foreach ($rows_info as $row_info) {
                $row_list .= '<li>'.__('Dev.:%s IP.:%s MAC.:%s PORT.:%s Count.: [%s]', $row_info['device_name'], $row_info['ip_address'], mactrack_format_mac($row_info['mac_address']), $row_info['port_number'], $row_info['count_rec'], 'mactrack').'</li>';
            }
        }
    }

    general_header();

    form_start('mactrack_view_macs.php');

    html_start_box($mactrack_view_agg_macs_actions[get_request_var('drp_action')], '60%', '', '3', 'center', '');

    if (!cacti_sizeof($row_array)) {
        echo "<tr><td class='even'><span class='textError'>".__('You must select at least one Row.', 'mactrack')."</span></td></tr>\n";
        $save_html = '';
    } elseif (!api_plugin_user_realm_auth('mactrack_macauth.php')) {
        echo "<tr><td class='even'><span class='textError'>".__('You are not permitted to delete rows.', 'mactrack')."</span></td></tr>\n";
        $save_html = '';
    } else {
        $save_html = "<button type='submit' name='save' class='ui-button ui-corner-all ui-widget'>".__esc('Continue', 'mactrack').'</button>';

        if ('3' == get_request_var('drp_action')) { // Delete Macs
            echo "<tr>
				<td class='textArea'>
					<p>".__('Click \'Continue\' to Delete the following rows from Aggregated table.', 'mactrack')."</p>
					<ul>{$row_list}</ul>
				</td>
			</tr>";
        }
    }

    echo "<tr>
		<td colspan='2' align='right' class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='".(isset($row_array) ? serialize($row_array) : '')."'>
			<input type='hidden' name='drp_action' value='".get_request_var('drp_action')."'>".('' != $save_html ? "
			<button type='button' onClick='cactiReturnTo()' class='ui-button ui-corner-all ui-widget'>".__esc('Cancel', 'mactrack')."</button>
			{$save_html}" : "<button type='button' onClick='cactiReturnTo()' class='ui-button ui-corner-all ui-widget'>".__esc('Return', 'mactrack').'</button>').'
		</td>
	</tr>';

    html_end_box();

    form_end();

    bottom_footer();
}

function api_mactrack_authorize_mac_addresses($mac_address, $ip_address)
{
    db_execute_prepared(
        'UPDATE mac_track_ports
		SET authorized=1
		WHERE mac_address = ?',
        [$mac_address]
    );

    db_execute_prepared(
        'UPDATE mac_track_aggregated_ports
		SET authorized=1
		WHERE mac_address = ?',
        [$mac_address]
    );

    db_execute_prepared(
        'UPDATE mac_track_temp_ports
		SET authorized=1
		WHERE mac_address = ?',
        [$mac_address]
    );

    db_execute_prepared(
        'REPLACE INTO mac_track_macauth
		(mac_address, description, added_by)
		VALUES (?, ?, ?)',
        [$mac_address, 'Added from MacView: '.$ip_address, $_SESSION['sess_user_id']]
    );

    cacti_log('AUDIT: MAC Address `'.$mac_address.'` is authorized by '
        .db_fetch_cell_prepared('SELECT full_name FROM user_auth WHERE id = ?', [$_SESSION['sess_user_id']]), false, 'MACTRACK');
}

function api_mactrack_revoke_mac_addresses($mac_address)
{
    db_execute_prepared(
        'UPDATE mac_track_ports
		SET authorized=0
		WHERE mac_address = ?',
        [$mac_address]
    );

    db_execute_prepared(
        'UPDATE mac_track_aggregated_ports
		SET authorized=0
		WHERE mac_address = ?',
        [$mac_address]
    );

    db_execute_prepared(
        'DELETE FROM mac_track_macauth
		WHERE mac_address = ?',
        [$mac_address]
    );

    cacti_log('AUDIT: MAC Address `'.$mac_address.'` is revoked by '
        .db_fetch_cell_prepared('SELECT full_name FROM user_auth WHERE id = ?', [$_SESSION['sess_user_id']]), false, 'MACTRACK');
}

function mactrack_view_macs_validate_request_vars()
{
    // ================= input validation and session storage =================
    $filters = [
        'rows' => [
            'filter' => FILTER_VALIDATE_INT,
            'pageset' => true,
            'default' => '-1',
        ],
        'page' => [
            'filter' => FILTER_VALIDATE_INT,
            'default' => '1',
        ],
        'site_id' => [
            'filter' => FILTER_VALIDATE_INT,
            'default' => '-1',
        ],
        'device_id' => [
            'filter' => FILTER_VALIDATE_INT,
            'default' => '-1',
        ],
        'vlan' => [
            'filter' => FILTER_VALIDATE_INT,
            'default' => '-1',
        ],
        'mac_filter_type_id' => [
            'filter' => FILTER_VALIDATE_INT,
            'default' => '1',
        ],
        'port_name_filter_type_id' => [
            'filter' => FILTER_VALIDATE_INT,
            'default' => '1',
        ],
        'ip_filter_type_id' => [
            'filter' => FILTER_VALIDATE_INT,
            'default' => '1',
        ],
        'authorized' => [
            'filter' => FILTER_VALIDATE_INT,
            'default' => '-1',
            'pageset' => true,
        ],
        'filter' => [
            'filter' => FILTER_CALLBACK,
            'pageset' => true,
            'default' => '',
            'options' => ['options' => 'sanitize_search_string'],
        ],
        'ip_filter' => [
            'filter' => FILTER_DEFAULT,
            'default' => '',
        ],
        'mac_filter' => [
            'filter' => FILTER_DEFAULT,
            'default' => '',
        ],
        'port_name_filter' => [
            'filter' => FILTER_DEFAULT,
            'default' => '',
        ],
        'scan_date' => [
            'filter' => FILTER_CALLBACK,
            'default' => '2',
            'options' => ['options' => 'sanitize_search_string'],
        ],
        'sort_column' => [
            'filter' => FILTER_CALLBACK,
            'default' => 'site_name',
            'options' => ['options' => 'sanitize_search_string'],
        ],
        'sort_direction' => [
            'filter' => FILTER_CALLBACK,
            'default' => 'ASC',
            'options' => ['options' => 'sanitize_search_string'],
        ],
    ];

    validate_store_request_vars($filters, 'sess_mtv_macs');
    // ================= input validation =================
}

function mactrack_view_export_macs()
{
    mactrack_view_macs_validate_request_vars();

    $sql_where = '';

    $port_results = mactrack_view_get_mac_records($sql_where, 0, false);

    $xport_array = [];
    array_push($xport_array, '"site_name","hostname","device_name",'
        .'"vlan_id","vlan_name","mac_address","vendor_name",'
        .'"ip_address","dns_hostname","port_number","port_name","scan_date"');

    if (cacti_sizeof($port_results)) {
        foreach ($port_results as $port_result) {
            if (1 == get_request_var('scan_date')) {
                $scan_date = $port_result['scan_date'];
            } else {
                $scan_date = $port_result['scan_date'];
            }

            array_push($xport_array, '"'.$port_result['site_name'].'","'
            .$port_result['hostname'].'","'.$port_result['device_name'].'","'
            .$port_result['vlan_id'].'","'.$port_result['vlan_name'].'","'
            .mactrack_format_mac($port_result['mac_address']).'","'.$port_result['vendor_name'].'","'
            .$port_result['ip_address'].'","'.$port_result['dns_hostname'].'","'
            .$port_result['port_number'].'","'.$port_result['port_name'].'","'
            .$scan_date.'"');
        }
    }

    header('Content-type: application/csv');
    header('Content-Disposition: attachment; filename=cacti_port_macs_xport.csv');
    foreach ($xport_array as $xport_line) {
        echo $xport_line."\n";
    }
}

function mactrack_view_get_mac_records(&$sql_where, $rows, $apply_limits = true)
{
    // form the 'where' clause for our main sql query
    if ('' != get_request_var('mac_filter')) {
        $mac_filter = str_replace(':', '', get_request_var('mac_filter'));
        $mac_filter = str_replace('-', '', $mac_filter);
        $mac_filter = str_replace('.', '', $mac_filter);

        switch (get_request_var('mac_filter_type_id')) {
            case '1': // do not filter
                break;

            case '2': // matches
                $sql_where .= ('' != $sql_where ? ' AND' : 'WHERE').' mtp.mac_address = '.db_qstr($mac_filter);

                break;

            case '3': // contains
                $sql_where .= ('' != $sql_where ? ' AND' : 'WHERE').' mtp.mac_address LIKE '.db_qstr('%'.$mac_filter.'%');

                break;

            case '4': // begins with
                $sql_where .= ('' != $sql_where ? ' AND' : 'WHERE').' mtp.mac_address LIKE '.db_qstr($mac_filter.'%');

                break;

            case '5': // does not contain
                $sql_where .= ('' != $sql_where ? ' AND' : 'WHERE').' mtp.mac_address NOT LIKE '.db_qstr('%'.$mac_filter.'%');

                break;

            case '6': // does not begin with
                $sql_where .= ('' != $sql_where ? ' AND' : 'WHERE').' mtp.mac_address NOT LIKE '.db_qstr($mac_filter.'%');
        }
    }

    if (('' != get_request_var('ip_filter')) || (get_request_var('ip_filter_type_id') > 6)) {
        switch (get_request_var('ip_filter_type_id')) {
            case '1': // do not filter
                break;

            case '2': // matches
                $sql_where .= ('' != $sql_where ? ' AND' : 'WHERE').' mtp.ip_address = '.db_qstr(get_request_var('ip_filter'));

                break;

            case '3': // contains
                $sql_where .= ('' != $sql_where ? ' AND' : 'WHERE').' mtp.ip_address LIKE '.db_qstr('%'.get_request_var('ip_filter').'%');

                break;

            case '4': // begins with
                $sql_where .= ('' != $sql_where ? ' AND' : 'WHERE').' mtp.ip_address LIKE '.db_qstr(get_request_var('ip_filter').'%');

                break;

            case '5': // does not contain
                $sql_where .= ('' != $sql_where ? ' AND' : 'WHERE').' mtp.ip_address NOT LIKE '.db_qstr('%'.get_request_var('ip_filter').'%');

                break;

            case '6': // does not begin with
                $sql_where .= ('' != $sql_where ? ' AND' : 'WHERE').' mtp.ip_address NOT LIKE '.db_qstr(get_request_var('ip_filter').'%');

                break;

            case '7': // is null
                $sql_where .= ('' != $sql_where ? ' AND' : 'WHERE').' mtp.ip_address = ""';

                break;

            case '8': // is not null
                $sql_where .= ('' != $sql_where ? ' AND' : 'WHERE').' mtp.ip_address != ""';
        }
    }

    if (('' != get_request_var('port_name_filter')) || (get_request_var('port_name_filter_type_id') > 6)) {
        switch (get_request_var('port_name_filter_type_id')) {
            case '1': // do not filter
                break;

            case '2': // matches
                $sql_where .= ('' != $sql_where ? ' AND' : 'WHERE').' mtp.port_name = '.db_qstr(get_request_var('port_name_filter'));

                break;

            case '3': // contains
                $sql_where .= ('' != $sql_where ? ' AND' : 'WHERE').' mtp.port_name LIKE '.db_qstr('%'.get_request_var('port_name_filter').'%');

                break;

            case '4': // begins with
                $sql_where .= ('' != $sql_where ? ' AND' : 'WHERE').' mtp.port_name LIKE '.db_qstr(get_request_var('port_name_filter').'%');

                break;

            case '5': // does not contain
                $sql_where .= ('' != $sql_where ? ' AND' : 'WHERE').' mtp.port_name NOT LIKE '.db_qstr('%'.get_request_var('port_name_filter').'%');

                break;

            case '6': // does not begin with
                $sql_where .= ('' != $sql_where ? ' AND' : 'WHERE').' mtp.port_name NOT LIKE '.db_qstr(get_request_var('port_name_filter').'%');

                break;

            case '7': // is null
                $sql_where .= ('' != $sql_where ? ' AND' : 'WHERE').' mtp.port_name = ""';

                break;

            case '8': // is not null
                $sql_where .= ('' != $sql_where ? ' AND' : 'WHERE').' mtp.port_name != ""';

                break;
        }
    }

    if ('' != get_request_var('filter')) {
        if ('' != read_config_option('mt_reverse_dns')) {
            $sql_where .= ('' != $sql_where ? ' AND' : 'WHERE')
                .' (mtp.dns_hostname LIKE '.db_qstr('%'.get_request_var('filter').'%').' OR '
                .'mtp.device_name LIKE '.db_qstr('%'.get_request_var('filter').'%').' OR '
                .'mtp.hostname LIKE '.db_qstr('%'.get_request_var('filter').'%').' OR '
                .'mtod.vendor_name LIKE '.db_qstr('%'.get_request_var('filter').'%').' OR '
                .'mtp.vlan_name LIKE '.db_qstr('%'.get_request_var('filter').'%').')';
        } else {
            $sql_where .= ('' != $sql_where ? ' AND' : 'WHERE')
                .' (mtp.device_name LIKE '.db_qstr('%'.get_request_var('filter').'%').' OR '
                .'mtp.hostname LIKE '.db_qstr('%'.get_request_var('filter').'%').' OR '
                .'mtod.vendor_name LIKE '.db_qstr('%'.get_request_var('filter').'%').' OR '
                .'mtp.vlan_name LIKE '.db_qstr('%'.get_request_var('filter').'%').')';
        }
    }

    if ('-1' != get_request_var('authorized')) {
        $sql_where .= ('' != $sql_where ? ' AND' : 'WHERE').' mtp.authorized = '.get_request_var('authorized');
    }

    if ('-1' != get_request_var('site_id')) {
        $sql_where .= ('' != $sql_where ? ' AND' : 'WHERE').' mtp.site_id = '.get_request_var('site_id');
    }

    if ('-1' != get_request_var('vlan')) {
        $sql_where .= ('' != $sql_where ? ' AND' : 'WHERE').' mtp.vlan_id = '.get_request_var('vlan');
    }

    if ('-1' != get_request_var('device_id')) {
        $sql_where .= ('' != $sql_where ? ' AND' : 'WHERE').' mtp.device_id = '.get_request_var('device_id');
    }

    if (('1' != get_request_var('scan_date')) && ('2' != get_request_var('scan_date')) && ('3' != get_request_var('scan_date'))) {
        $sql_where .= ('' != $sql_where ? ' AND' : 'WHERE').' mtp.scan_date = '.db_qstr(get_request_var('scan_date'));
    }

    // prevent table scans, either a device or site must be selected
    if (-1 == get_request_var('site_id') && -1 == get_request_var('device_id')) {
        if ('' == $sql_where) {
            return [];
        }
    }

    $sql_order = get_order_string();
    if ($apply_limits && 999999 != $rows) {
        $sql_limit = ' LIMIT '.($rows * (get_request_var('page') - 1)).','.$rows;
    } else {
        $sql_limit = '';
    }

    if (3 == get_request_var('scan_date')) {
        $query_string = "SELECT
			row_id, site_name, device_id, device_name, hostname, mtp.mac_address,
			vendor_name, ip_address, dns_hostname, port_number,
			port_name, vlan_id, vlan_name, MAX(date_last) AS scan_date, count_rec, active_last, mtm.mac_id
			FROM mac_track_aggregated_ports AS mtp
			LEFT JOIN mac_track_sites AS mts
			ON mtp.site_id = mts.site_id
			LEFT JOIN mac_track_macauth AS mtm
			ON mtm.mac_address = mtp.mac_address
			LEFT JOIN mac_track_oui_database AS mtod
			ON mtod.vendor_mac = mtp.vendor_mac
			{$sql_where}
			GROUP BY device_id, ip_address, mtp.mac_address
			{$sql_order}
			{$sql_limit}";
    } elseif (2 != get_request_var('scan_date')) {
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
			{$sql_where}
			{$sql_order}
			{$sql_limit}";
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
			{$sql_where}
			GROUP BY device_id, mtp.mac_address, port_number, ip_address
			{$sql_order}
			{$sql_limit}";
    }

    if ('' == $sql_where) {
        return [];
    }

    return db_fetch_assoc($query_string);
}

function mactrack_view_macs()
{
    global $title, $report, $mactrack_search_types, $rows_selector, $config;
    global $mactrack_view_macs_actions, $item_rows;

    mactrack_tabs();

    html_start_box($title, '100%', '', '3', 'center', '');
    mactrack_mac_filter();
    html_end_box();

    $sql_where = '';

    if (-1 == get_request_var('rows')) {
        $rows = read_config_option('num_rows_table');
    } elseif (-2 == get_request_var('rows')) {
        $rows = 999999;
    } else {
        $rows = get_request_var('rows');
    }

    $port_results = mactrack_view_get_mac_records($sql_where, $rows, true);

    // prevent table scans, either a device or site must be selected
    if ('' == $sql_where) {
        $total_rows = 0;
    } elseif (1 == get_request_var('scan_date')) {
        $rows_query_string = "SELECT
			COUNT(mtp.device_id)
			FROM mac_track_ports AS mtp
			LEFT JOIN mac_track_sites AS mts
			ON mtp.site_id = mts.site_id
			LEFT JOIN mac_track_oui_database AS mtod
			ON mtod.vendor_mac = mtp.vendor_mac
			{$sql_where}";

        $total_rows = db_fetch_cell($rows_query_string);
    } else {
        $rows_query_string = "SELECT
			COUNT(DISTINCT device_id, mac_address, port_number, ip_address)
			FROM mac_track_ports AS mtp
			LEFT JOIN mac_track_sites AS mts
			ON mtp.site_id = mts.site_id
			LEFT JOIN mac_track_oui_database AS mtod
			ON mtod.vendor_mac = mtp.vendor_mac
			{$sql_where}";

        $total_rows = db_fetch_cell($rows_query_string);
    }

    $display_text1 = [
        'nosort' => [
            'display' => __('Actions', 'mactrack'),
        ],
        'site_name' => [
            'display' => __('Site Name', 'mactrack'),
            'sort' => 'ASC',
        ],
        'device_name' => [
            'display' => __('Switch Name', 'mactrack'),
            'sort' => 'ASC',
        ],
        'hostname' => [
            'display' => __('Switch Hostname', 'mactrack'),
            'sort' => 'ASC',
        ],
        'ip_address' => [
            'display' => __('ED IP Address', 'mactrack'),
            'sort' => 'ASC',
        ],
    ];

    $display_text2 = [];

    if ('' != read_config_option('mt_reverse_dns')) {
        $display_text2 = [
            'dns_hostname' => [
                'display' => __('ED DNS Hostname', 'mactrack'),
                'sort' => 'ASC',
            ],
        ];
    }

    $display_text3 = [
        'mac_address' => [
            'display' => __('ED MAC Address', 'mactrack'),
            'sort' => 'ASC',
        ],
        'authorized' => [
            'display' => __('Authorized', 'mactrack'),
            'sort' => 'ASC',
        ],
        'vendor_name' => [
            'display' => __('Vendor Name', 'mactrack'),
            'sort' => 'ASC',
        ],
        'port_number' => [
            'display' => __('Port Number', 'mactrack'),
            'align' => 'left',
            'sort' => 'DESC',
        ],
        'port_name' => [
            'display' => __('Port Name', 'mactrack'),
            'align' => 'left',
            'sort' => 'ASC',
        ],
        'vlan_id' => [
            'display' => __('VLAN ID', 'mactrack'),
            'align' => 'left',
            'sort' => 'DESC',
        ],
        'vlan_name' => [
            'display' => __('VLAN Name', 'mactrack'),
            'align' => 'left',
            'sort' => 'ASC',
        ],
        'scan_date' => [
            'display' => __('Last Scan Date', 'mactrack'),
            'align' => 'left',
            'sort' => 'DESC',
        ],
    ];

    $display_text = array_merge($display_text1, $display_text2, $display_text3);

    if (api_plugin_user_realm_auth('mactrack_macauth.php')) {
        $columns = cacti_sizeof($display_text) + 1;
    } else {
        $columns = cacti_sizeof($display_text);
    }

    $nav = html_nav_bar('mactrack_view_macs.php?report=macs', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, $columns, __('MAC Addresses', 'mactrack'), 'page', 'main');

    if (api_plugin_user_realm_auth('mactrack_macauth.php')) {
        form_start('mactrack_view_macs.php');
    }

    echo $nav;

    html_start_box('', '100%', '', '3', 'center', '');

    if (api_plugin_user_realm_auth('mactrack_macauth.php')) {
        html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'));
    } else {
        html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'));
    }

    if (cacti_sizeof($port_results)) {
        foreach ($port_results as $port_result) {
            if (2 != get_request_var('scan_date')) {
                $scan_date = $port_result['scan_date'];
            } else {
                $scan_date = $port_result['scan_date'];
            }

            $key = $port_result['mac_address'].'-'.$port_result['ip_address'].'-'
                .$port_result['device_id'].'-'.$port_result['port_number'].'-'.strtotime($scan_date);

            form_alternate_row('line'.$key, true);
            form_selectable_cell(mactrack_interface_actions($port_result['device_id'], $port_result['port_number'], false), $key, '1%');
            form_selectable_cell($port_result['site_name'], $key);
            form_selectable_cell($port_result['device_name'], $key);
            form_selectable_cell($port_result['hostname'], $key);
            form_selectable_cell(filter_value($port_result['ip_address'], get_request_var('filter')), $key);

            if ('on' == read_config_option('mt_reverse_dns')) {
                form_selectable_cell(filter_value($port_result['dns_hostname'], get_request_var('filter')), $key);
            }

            if ($port_result['mac_id'] > 0) {
                $auth = '<span class="deviceUp">'.__('Authorized', 'mactrack');
            } else {
                $auth = '<span class="deviceDown">'.__('Not Authorized', 'mactrack');
            }

            // echo get_request_var('filter') . "<br/>";
            form_selectable_cell(filter_value(mactrack_format_mac($port_result['mac_address']), get_request_var('filter')), $key);
            form_selectable_cell($auth, $key);
            form_selectable_cell(filter_value($port_result['vendor_name'], get_request_var('filter')), $key);
            form_selectable_cell($port_result['port_number'], $key, '', 'left');
            form_selectable_cell(filter_value($port_result['port_name'], get_request_var('filter')), $key, '', 'left');
            form_selectable_cell($port_result['vlan_id'], $key, '', 'left');
            form_selectable_cell(filter_value($port_result['vlan_name'], get_request_var('filter')), $key, '', 'left');
            form_selectable_cell($scan_date, $key, '', 'left');

            if (api_plugin_user_realm_auth('mactrack_macauth.php')) {
                form_checkbox_cell($port_result['mac_address'], $key);
            }

            form_end_row();
        }
    } else {
        if (-1 == get_request_var('site_id') && -1 == get_request_var('device_id')) {
            echo "<tr><td colspan='{$columns}'><em>".__('You must choose a Site, Device or other search criteria.', 'mactrack').'</em></td></tr>';
        } else {
            echo "<tr><td colspan='{$columns}'><em>".__('No Mactrack Port Results Found', 'mactrack').'</em></td></tr>';
        }
    }

    html_end_box(false);

    if (cacti_sizeof($port_results)) {
        echo $nav;
    }

    if (api_plugin_user_realm_auth('mactrack_macauth.php')) {
        draw_actions_dropdown($mactrack_view_macs_actions);

        form_end();
    }
}

function mactrack_view_aggregated_macs()
{
    global $title, $report, $mactrack_search_types, $rows_selector, $config;
    global $mactrack_view_agg_macs_actions, $item_rows;

    mactrack_tabs();

    html_start_box($title, '100%', '', '3', 'center', '');
    mactrack_mac_filter();
    html_end_box();

    $sql_where = '';

    if (-1 == get_request_var('rows')) {
        $rows = read_config_option('num_rows_table');
    } elseif (-2 == get_request_var('rows')) {
        $rows = 999999;
    } else {
        $rows = get_request_var('rows');
    }

    $port_results = mactrack_view_get_mac_records($sql_where, $rows, true);

    // prevent table scans, either a device or site must be selected
    if ('' == $sql_where) {
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
			{$sql_where}";

        $total_rows = db_fetch_cell($rows_query_string);
    }

    $display_text = [
        'site_name' => [
            'display' => __('Site Name', 'mactrack'),
            'sort' => 'ASC',
        ],
        'device_name' => [
            'display' => __('Switch Name', 'mactrack'),
            'sort' => 'ASC',
        ],
        'hostname' => [
            'display' => __('Switch Hostname', 'mactrack'),
            'sort' => 'ASC',
        ],
        'ip_address' => [
            'display' => __('ED IP Address', 'mactrack'),
            'sort' => 'ASC',
        ],
    ];

    if ('on' == read_config_option('mt_reverse_dns')) {
        $display_text['dns_hostname'] = [
            'display' => __('ED DNS Hostname', 'mactrack'),
            'sort' => 'ASC',
        ];
    }

    $display_text = array_merge($display_text, [
        'mac_address' => [
            'display' => __('ED MAC Address', 'mactrack'),
            'sort' => 'ASC',
        ],
        'authorized' => [
            'display' => __('Authorized', 'mactrack'),
            'sort' => 'ASC',
        ],
        'vendor_name' => [
            'display' => __('Vendor Name', 'mactrack'),
            'sort' => 'ASC',
        ],
        'port_number' => [
            'display' => __('Port Number', 'mactrack'),
            'align' => 'left',
            'sort' => 'DESC',
        ],
        'port_name' => [
            'display' => __('Port Name', 'mactrack'),
            'align' => 'left',
            'sort' => 'ASC',
        ],
        'vlan_id' => [
            'display' => __('VLAN ID', 'mactrack'),
            'align' => 'left',
            'sort' => 'DESC',
        ],
        'vlan_name' => [
            'display' => __('VLAN Name', 'mactrack'),
            'align' => 'left',
            'sort' => 'ASC',
        ],
    ]);

    if (1 == get_request_var('rows')) {
        $display_text['scan_date'] = [
            'display' => __('Last Scan Date', 'mactrack'),
            'align' => 'left',
            'sort' => 'DESC',
        ];
    } else {
        $display_text['scan_date'] = [
            'display' => __('Last Scan Date', 'mactrack'),
            'align' => 'left',
            'sort' => 'DESC',
        ];
    }

    if (3 == get_request_var('scan_date')) {
        $display_text['count_rec'] = [
            'display' => __('Count', 'mactrack'),
            'align' => 'right',
            'sort' => 'ASC',
        ];
    }

    if (api_plugin_user_realm_auth('mactrack_macauth.php')) {
        $columns = cacti_sizeof($display_text) + 1;
    } else {
        $columns = cacti_sizeof($display_text);
    }

    $nav = html_nav_bar('mactrack_view_macs.php?report=macs&scan_date=3', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, $columns, __('MAC Addresses', 'mactrack'), 'page', 'main');

    if (api_plugin_user_realm_auth('mactrack_macauth.php')) {
        form_start('mactrack_view_macs.php');
    }

    echo $nav;

    html_start_box('', '100%', '', '3', 'center', '');

    if (api_plugin_user_realm_auth('mactrack_macauth.php')) {
        html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'));
    } else {
        html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'));
    }

    $i = 0;

    if (cacti_sizeof($port_results)) {
        foreach ($port_results as $port_result) {
            if (1 == $port_result['active_last']) {
                $color_line_date = "<span style='font-weight: bold;'>";
            } else {
                $color_line_date = '';
            }

            $key = $port_result['mac_address'].'-'.$port_result['device_id']
                    .$port_result['port_number'].'-'.$port_result['scan_date'];

            $key = $port_result['row_id'];

            form_alternate_row('line'.$key, true);
            form_selectable_cell(filter_value($port_result['site_name'], get_request_var('filter')), $key);
            form_selectable_cell(filter_value($port_result['device_name'], get_request_var('filter')), $key);
            form_selectable_cell(filter_value($port_result['hostname'], get_request_var('filter')), $key);
            form_selectable_cell(filter_value($port_result['ip_address'], get_request_var('filter')), $key);

            if ('' != read_config_option('mt_reverse_dns')) {
                form_selectable_cell(filter_value($port_result['dns_hostname'], get_request_var('filter')), $key);
            }

            if ($port_result['mac_id'] > 0) {
                $auth = '<span class="deviceUp">'.__('Authorized', 'mactrack');
            } else {
                $auth = '<span class="deviceDown">'.__('Not Authorized', 'mactrack');
            }

            form_selectable_cell(filter_value(mactrack_format_mac($port_result['mac_address']), get_request_var('filter')), $key);
            form_selectable_cell($auth, $key);
            form_selectable_cell(filter_value($port_result['vendor_name'], get_request_var('filter')), $key);
            form_selectable_cell($port_result['port_number'], $key, '', 'left');
            form_selectable_cell(filter_value($port_result['port_name'], get_request_var('filter')), $key, '', 'left');
            form_selectable_cell($port_result['vlan_id'], $key, '', 'left');
            form_selectable_cell(filter_value($port_result['vlan_name'], get_request_var('filter')), $key, '', 'left');
            form_selectable_cell($color_line_date.$port_result['scan_date'], $key, '', 'left');
            form_selectable_cell($port_result['count_rec'], $key, '', 'right');

            if (api_plugin_user_realm_auth('mactrack_macauth.php')) {
                form_checkbox_cell($port_result['mac_address'], $key);
            }

            form_end_row();
        }
    } else {
        if (-1 == get_request_var('site_id') && -1 == get_request_var('device_id')) {
            echo "<tr><td colspan='10'><em>".__('You must first choose a Site, Device or other search criteria.', 'mactrack').'</em></td></tr>';
        } else {
            echo "<tr><td colspan='10'><em>".__('No Mactrack Port Results Found', 'mactrack').'</em></td></tr>';
        }
    }

    html_end_box(false);

    if (cacti_sizeof($port_results)) {
        echo $nav;
        mactrack_display_stats();
    }

    if (api_plugin_user_realm_auth('mactrack_macauth.php')) {
        // draw the dropdown containing a list of available actions for this form
        draw_actions_dropdown($mactrack_view_agg_macs_actions);

        form_end();
    }
}

function mactrack_mac_filter()
{
    global $item_rows, $rows_selector, $mactrack_search_types;

    ?>
	<tr class='even'>
		<td>
			<form id='mactrack'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php echo __('Search', 'mactrack'); ?>
					</td>
					<td>
						<input type='text' id='filter' size='25' value='<?php echo get_request_var('filter'); ?>'>
					</td>
					<td>
						<?php echo __('Site', 'mactrack'); ?>
					</td>
					<td>
						<select id='site_id' onChange='applyFilter()'>
							<option value='-1'<?php if ('-1' == get_request_var('site_id')) {?> selected<?php }?>><?php echo __('N/A', 'mactrack'); ?></option>
							<?php
                            $sites = db_fetch_assoc('SELECT site_id,site_name FROM mac_track_sites ORDER BY site_name');
    if (cacti_sizeof($sites)) {
        foreach ($sites as $site) {
            echo '<option value="'.$site['site_id'].'"';
            if (get_request_var('site_id') == $site['site_id']) {
                echo ' selected';
            } echo '>'.$site['site_name'].'</option>';
        }
    }
    ?>
						</select>
					</td>
					<td>
						<?php echo __('Device', 'mactrack'); ?>
					</td>
					<td>
						<select id='device_id' onChange='applyFilter()'>
							<option value='-1'<?php if ('-1' == get_request_var('device_id')) {?> selected<?php }?>><?php echo __('All', 'mactrack'); ?></option>
							<?php
    if (-1 == get_request_var('site_id')) {
        $filter_devices = db_fetch_assoc('SELECT device_id, device_name, hostname
									FROM mac_track_devices
									ORDER BY device_name');
    } else {
        $filter_devices = db_fetch_assoc_prepared(
            'SELECT device_id, device_name, hostname
									FROM mac_track_devices
									WHERE site_id = ?
									ORDER BY device_name',
            [get_request_var('site_id')]
        );
    }

    if (cacti_sizeof($filter_devices)) {
        foreach ($filter_devices as $filter_device) {
            echo '<option value=" '.$filter_device['device_id'].'"';
            if (get_request_var('device_id') == $filter_device['device_id']) {
                echo ' selected';
            } echo '>'.$filter_device['device_name'].'('.$filter_device['hostname'].')</option>';
        }
    }
    ?>
						</select>
					</td>
					<td>
						<?php echo __('MAC\'s', 'mactrack'); ?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<?php
    if (cacti_sizeof($rows_selector)) {
        foreach ($rows_selector as $key => $value) {
            echo '<option value="'.$key.'"';
            if (get_request_var('rows') == $key) {
                echo ' selected';
            } echo '>'.$value.'</option>\n';
        }
    }
    ?>
						</select>
					</td>
					<td>
						<span class='nowrap'>
							<button type='submit' id='go' class='ui-button ui-corner-all ui-widget ui-state-active'><?php echo __esc('Go', 'mactrack'); ?></button>
							<button type='button' id='clear' class='ui-button ui-corner-all ui-widget'><?php echo __esc('Clear', 'mactrack'); ?></button>
							<button type='button' id='export' class='ui-button ui-corner-all ui-widget'><?php echo __esc('Export', 'mactrack'); ?></button>
						</span>
					</td>
				</tr>
			</table>
			<table class='filterTable'>
				<tr>
					<td>
						<?php echo __('IP', 'mactrack'); ?>
					</td>
					<td>
						<select id='ip_filter_type_id'>
							<?php
    for ($i = 1; $i <= cacti_sizeof($mactrack_search_types); ++$i) {
        echo "<option value='".$i."'";
        if (get_request_var('ip_filter_type_id') == $i) {
            echo ' selected';
        } echo '>'.$mactrack_search_types[$i].'</option>';
    }
    ?>
						</select>
					</td>
					<td>
						<input type='text' id='ip_filter' size='25' value='<?php echo html_escape_request_var('ip_filter'); ?>'>
					</td>
					<td>
						<?php echo __('VLAN Name', 'mactrack'); ?>
					</td>
					<td>
						<select id='vlan' onChange='applyFilter()'>
							<option value='-1'<?php if ('-1' == get_request_var('vlan')) {?> selected<?php }?>><?php echo __('All', 'mactrack'); ?></option>
							<?php
    $sql_where = '';
    if ('-1' != get_request_var('device_id')) {
        $sql_where = 'WHERE device_id='.get_request_var('device_id');
    }

    if ('-1' != get_request_var('site_id')) {
        if ('' != $sql_where) {
            $sql_where .= ' AND site_id='.get_request_var('site_id');
        } else {
            $sql_where = 'WHERE site_id='.get_request_var('site_id');
        }
    }

    $vlans = db_fetch_assoc("SELECT DISTINCT vlan_id, vlan_name
								FROM mac_track_vlans
								{$sql_where}
								ORDER BY vlan_name ASC");

    if (cacti_sizeof($vlans)) {
        foreach ($vlans as $vlan) {
            echo '<option value="'.$vlan['vlan_id'].'"';
            if (get_request_var('vlan') == $vlan['vlan_id']) {
                echo ' selected';
            } echo '>'.$vlan['vlan_name'].'</option>';
        }
    }
    ?>
						</select>
					</td>
					<td>
						<?php echo __('Show', 'mactrack'); ?>
					</td>
					<td>
						<select id='scan_date' onChange='applyFilter()'>
							<option value='1'<?php if ('1' == get_request_var('scan_date')) {?> selected<?php }?>><?php echo __('All', 'mactrack'); ?></option>
							<option value='2'<?php if ('2' == get_request_var('scan_date')) {?> selected<?php }?>><?php echo __('Most Recent', 'mactrack'); ?></option>
							<option value='3'<?php if ('3' == get_request_var('scan_date')) {?> selected<?php }?>><?php echo __('Aggregated', 'mactrack'); ?></option>
							<?php

    $scan_dates = db_fetch_assoc('SELECT scan_date FROM mac_track_scan_dates ORDER BY scan_date DESC');
    if (cacti_sizeof($scan_dates)) {
        foreach ($scan_dates as $scan_date) {
            echo '<option value="'.$scan_date['scan_date'].'"';
            if (get_request_var('scan_date') == $scan_date['scan_date']) {
                echo ' selected';
            } echo '>'.$scan_date['scan_date'].'</option>';
        }
    }
    ?>
						</select>
					</td>
				</tr>
				<tr>
					<td>
						<?php echo __('MAC', 'mactrack'); ?>
					</td>
					<td>
						<select id='mac_filter_type_id'>
							<?php
    for ($i = 1; $i <= cacti_sizeof($mactrack_search_types) - 2; ++$i) {
        echo "<option value='".$i."'";
        if (get_request_var('mac_filter_type_id') == $i) {
            echo ' selected';
        } echo '>'.$mactrack_search_types[$i].'</option>';
    }
    ?>
						</select>
					</td>
					<td>
						<input type='text' id='mac_filter' size='25' value='<?php echo html_escape_request_var('mac_filter'); ?>'>
					</td>
					<td>
						<?php echo __('Authorized', 'mactrack'); ?>
					</td>
					<td>
						<select id='authorized' onChange='applyFilter()'>
							<option value='-1'<?php if ('-1' == get_request_var('authorized')) {?> selected<?php }?>><?php echo __('All', 'mactrack'); ?></option>
							<option value='1'<?php if ('1' == get_request_var('authorized')) {?> selected<?php }?>><?php echo __('Yes', 'mactrack'); ?></option>
							<option value='0'<?php if ('0' == get_request_var('authorized')) {?> selected<?php }?>><?php echo __('No', 'mactrack'); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<td>
						<?php echo __('Portname', 'mactrack'); ?>
					</td>
					<td>
						<select id='port_name_filter_type_id'>
							<?php
    for ($i = 1; $i <= cacti_sizeof($mactrack_search_types); ++$i) {
        echo "<option value='".$i."'";
        if (get_request_var('port_name_filter_type_id') == $i) {
            echo ' selected';
        } echo '>'.$mactrack_search_types[$i].'</option>';
    }
    ?>
						</select>
					</td>
					<td>
						<input type='text' id='port_name_filter' size='25' value='<?php echo html_escape_request_var('port_name_filter'); ?>'>
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
