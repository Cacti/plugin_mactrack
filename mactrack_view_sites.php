<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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

$title = __('Device Tracking - Site Report View', 'mactrack');

if (isset_request_var('export')) {
	mactrack_view_export_sites();
} else {
	general_header();
	mactrack_view_sites();
	bottom_footer();
}

function mactrack_view_export_sites() {
	mactrack_sites_request_validation();

	$sql_where = '';

	$sites = mactrack_view_get_site_records($sql_where, 0, false);

	$xport_array = array();

	if (get_request_var('detail') == 'false') {
		array_push($xport_array, '"site_id","site_name","total_devices",' .
				'"total_device_errors","total_macs","total_ips","total_oper_ports",' .
				'"total_user_ports"');

		foreach($sites as $site) {
			array_push($xport_array,'"'   .
				$site['site_id']          . '","' . $site['site_name']           . '","' .
				$site['total_devices']    . '","' . $site['total_device_errors'] . '","' .
				$site['total_macs']       . '","' . $site['total_ips']           . '","' .
				$site['total_oper_ports'] . '","' . $site['total_user_ports']    . '"');
		}
	} else {
		array_push($xport_array, '"site_name","vendor","device_name","total_devices",' .
				'"total_ips","total_user_ports","total_oper_ports","total_trunks",' .
				'"total_macs_found"');

		foreach($sites as $site) {
			array_push($xport_array,'"'   .
				$site['site_name']        . '","' . $site['vendor']          . '","' .
				$site['device_name']      . '","' . $site['total_devices']   . '","' .
				$site['sum_ips_total']    . '","' . $site['sum_ports_total'] . '","' .
				$site['sum_ports_active'] . '","' . $site['sum_ports_trunk'] . '","' .
				$site['sum_macs_active']  . '"');
		}
	}

	header('Content-type: application/csv');
	header('Content-Disposition: attachment; filename=cacti_site_xport.csv');
	foreach($xport_array as $xport_line) {
		print $xport_line . "\n";
	}
}

function mactrack_view_get_site_records(&$sql_where, $rows, $apply_limits = true) {
	/* create SQL where clause */
	$device_type_info = db_fetch_row_prepared('SELECT *
		FROM mac_track_device_types
		WHERE device_type_id = ?',
		array(get_request_var('device_type_id')));

	$sql_where = '';

	/* form the 'where' clause for our main sql query */
	if (get_request_var('filter') != '') {
		if (get_request_var('detail') == 'false') {
			$sql_where = "WHERE (mac_track_sites.site_name LIKE '%" . get_request_var('filter') . "%')";
		} else {
			$sql_where = "WHERE (mac_track_device_types.vendor LIKE '%" . get_request_var('filter') . "%' OR " .
				"mac_track_device_types.description LIKE '%" . get_request_var('filter') . "%' OR " .
				"mac_track_sites.site_name LIKE '%" . get_request_var('filter') . "%')";
		}
	}

	if (cacti_sizeof($device_type_info)) {
		$sql_where = ($sql_where != '' ? ' AND ':'WHERE ') . '(mac_track_devices.device_type_id=' . $device_type_info['device_type_id'] . ')';
	}

	if ((get_request_var('site_id') != '-1') && (get_request_var('detail'))){
		$sql_where = ($sql_where != '' ? ' AND ':'WHERE ') . '(mac_track_devices.site_id=' . get_request_var('site_id') . ')';
	}

	$sql_order = get_order_string();
	if ($apply_limits) {
		$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ', ' . $rows;
	} else {
		$sql_limit = '';
	}

	if (get_request_var('detail') == 'false') {
		$query_string = "SELECT *
			FROM mac_track_sites
			$sql_where
			$sql_order
			$sql_limit";
	} else {
		$query_string ="SELECT mac_track_sites.site_name, mac_track_sites.site_id,
			COUNT(mac_track_device_types.device_type_id) AS total_devices,
			mac_track_device_types.device_type_id,
			mac_track_device_types.device_type,
			mac_track_device_types.vendor,
			mac_track_device_types.description,
			SUM(mac_track_devices.ips_total) AS sum_ips_total,
			SUM(mac_track_devices.ports_total) AS sum_ports_total,
			SUM(mac_track_devices.ports_active) AS sum_ports_active,
			SUM(mac_track_devices.ports_trunk) AS sum_ports_trunk,
			SUM(mac_track_devices.macs_active) AS sum_macs_active
			FROM (mac_track_device_types
			RIGHT JOIN mac_track_devices
			ON mac_track_device_types.device_type_id = mac_track_devices.device_type_id)
			RIGHT JOIN mac_track_sites
			ON mac_track_devices.site_id = mac_track_sites.site_id
			$sql_where
			GROUP BY mac_track_sites.site_name, mac_track_device_types.vendor, mac_track_device_types.description
			HAVING (((Count(mac_track_device_types.device_type_id))>0))
			$sql_order
			$sql_limit";
	}

	return db_fetch_assoc($query_string);
}

function mactrack_sites_request_validation() {
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
			'default' => 'site_name',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			),
		'site_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '-1',
			'pageset' => true
			),
		'device_type_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '-1',
			'pageset' => true
			),
		'detail' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'false',
			'options' => array('options' => 'sanitize_search_string')
			),
	);

	validate_store_request_vars($filters, 'sess_mtv_sites');
	/* ================= input validation ================= */
}

function mactrack_view_sites() {
	global $title, $config, $item_rows;

	mactrack_sites_request_validation();

	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} elseif (get_request_var('rows') == -2) {
		$rows = 999999;
	} else {
		$rows = get_request_var('rows');
	}

	$webroot = $config['url_path'] . 'plugins/mactrack/';

	mactrack_tabs();

	html_start_box($title, '100%', '', '3', 'center', '');
	mactrack_site_filter('mactrack_view_sites.php');
	html_end_box();

	$sql_where = '';

	$sites = mactrack_view_get_site_records($sql_where, $rows);

	if (get_request_var('detail') == 'false') {
		$total_rows = db_fetch_cell("SELECT
			COUNT(mac_track_sites.site_id)
			FROM mac_track_sites
			$sql_where");
	} else {
		$total_rows = cacti_sizeof(db_fetch_assoc("SELECT
			mac_track_device_types.device_type_id, mac_track_sites.site_name
			FROM (mac_track_device_types
			RIGHT JOIN mac_track_devices
			ON mac_track_device_types.device_type_id = mac_track_devices.device_type_id)
			RIGHT JOIN mac_track_sites
			ON mac_track_devices.site_id = mac_track_sites.site_id
			$sql_where
			GROUP BY mac_track_sites.site_name, mac_track_device_types.device_type_id"));
	}

	if (get_request_var('detail') == 'false') {
		$display_text = array(
			'nosort' => array(
				'display' => __('Actions', 'mactrack')
			),
			'site_name' => array(
				'display' => __('Site Name', 'mactrack'),
				'sort'    => 'ASC'
			),
			'total_devices' => array(
				'display' => __('Devices', 'mactrack'),
				'align'   => 'right',
				'sort'    => 'DESC'
			),
			'total_ips' => array(
				'display' => __('Total IP\'s', 'mactrack'),
				'align'   => 'right',
				'sort'    => 'DESC'
			),
			'total_user_ports' => array(
				'display' => __('User Ports', 'mactrack'),
				'align'   => 'right',
				'sort'    => 'DESC'
			),
			'total_oper_ports' => array(
				'display' => __('User Ports Up', 'mactrack'),
				'align'   => 'right',
				'sort'    => 'DESC'
			),
			'total_macs' => array(
				'display' => __('MACS Found', 'mactrack'),
				'align'   => 'right',
				'sort'    => 'DESC'
			),
			'total_device_errors' => array(
				'display' => __('Device Errors', 'mactrack'),
				'align'   => 'right',
				'sort'    => 'DESC'
			)
		);

		$columns = cacti_sizeof($display_text);

		$nav = html_nav_bar('mactrack_view_sites.php', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, $columns, __('Sites', 'mactrack'), 'page', 'main');

		print $nav;

		html_start_box('', '100%', '', '3', 'center', '');

		html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'));

		if (cacti_sizeof($sites)) {
			foreach ($sites as $site) {
				if (api_user_realm_auth('mactrack_sites.php')) {
					$actions = "<a class='pic' href='" . htmlspecialchars($webroot . 'mactrack_sites.php?action=edit&site_id=' . $site['site_id']) . "' title='" . __esc('Edit Site', 'mactrack') . "'><i class='mtEdit fas fa-edit'></i></a>";
					$actions .= "<a class='pic siterescan' href='#' id='r_" . $site['site_id'] . "' title='" . __esc('Rescan Site', 'mactrack') . "'><i class='mtSync fa fa-sync'></i></a>";
				} else {
					$actions = '';
				}

				$actions .= "<a class='pic' href='" . htmlspecialchars($webroot . 'mactrack_view_devices.php?report=devices&reset&site_id=' . $site['site_id']) . "' title='" . __esc('View Devices', 'mactrack') . "'><i class='mtServer fas fa-server'></i></a>";

				$actions .= "<a class='pic' href='" . htmlspecialchars($webroot . 'mactrack_view_ips.php?report=ips&reset&site_id=' . $site['site_id']) . "' title='" . __esc('View IP Ranges', 'mactrack') . "'><i class='mtIps fas fa-network-wired'></i></a>";

				$actions .= "<a class='pic' href='" . htmlspecialchars($webroot . 'mactrack_view_arp.php?report=arp&reset&site_id=' . $site['site_id']) . "' title='" . __esc('View IP Addresses', 'mactrack') . "'><i class='mtPorts fas fa-desktop'></i></a>";

				$actions .= "<a class='pic' href='" . htmlspecialchars($webroot . 'mactrack_view_macs.php?report=macs&reset&device_id=-1&scan_date=3&site_id=' . $site['site_id']) . "' title='" . __esc('View MAC Addresses', 'mactrack') . "'><i class='mtMacs fas fa-at'></i></a>";

				$actions .= "<a class='pic' href='" . htmlspecialchars($webroot . 'mactrack_view_interfaces.php?report=interfaces&reset&site=' . $site['site_id']) . "' title='" . __esc('View Interfaces', 'mactrack') . "'><i class='mtInterfaces fas fa-ethernet'></i></a>";

				form_alternate_row('row_' . $site['site_id'], true);
				form_selectable_cell($actions, 'row_' . $site['site_id'], '1%', 'nowrap');
				form_selectable_cell(filter_value($site['site_name'], get_request_var('filter')), 'row_' . $site['site_id'], '', 'hyperLink');
				form_selectable_cell(number_format_i18n($site['total_devices']), 'row_' . $site['site_id'], '', 'right');
				form_selectable_cell(number_format_i18n($site['total_ips']), 'row_' . $site['site_id'], '', 'right');
				form_selectable_cell(number_format_i18n($site['total_user_ports']), 'row_' . $site['site_id'], '', 'right');
				form_selectable_cell(number_format_i18n($site['total_oper_ports']), 'row_' . $site['site_id'], '', 'right');
				form_selectable_cell(number_format_i18n($site['total_macs']), 'row_' . $site['site_id'], '', 'right');
				form_selectable_cell(number_format_i18n($site['total_device_errors']), 'row_' . $site['site_id'], '', 'right');
				form_end_row();
			}
		} else {
			print '<tr><td colspan="' . $columns . '"><em>' . __('No Device Tracking Sites Found', 'mactrack') . '</em></td></tr>';
		}

		html_end_box(false);

		if (cacti_sizeof($sites)) {
			print $nav;

			mactrack_display_stats();
		}
	} else {
		$display_text = array(
			'nosort' => array(
				'display' => __('Actions', 'mactrack')
			),
			'site_name' => array(
				'display' => __('Site Name', 'mactrack'),
				'sort'    => 'ASC'
			),
			'vendor' => array(
				'display' => __('Vendor', 'mactrack'),
				'sort'    => 'ASC'
			),
			'description' => array(
				'display' => __('Device Type', 'mactrack'),
				'sort'    => 'DESC'
			),
			'total_devices' => array(
				'display' => __('Total Devices', 'mactrack'),
				'align'   => 'right',
				'sort'    => 'DESC'
			),
			'sum_ips_total' => array(
				'display' => __('Total IP\'s', 'mactrack'),
				'align'   => 'right',
				'sort'    => 'DESC'
			),
			'sum_ports_total' => array(
				'display' => __('Total User Ports', 'mactrack'),
				'align'   => 'right',
				'sort'    => 'DESC'
			),
			'sum_ports_active' => array(
				'display' => __('Total Oper Ports', 'mactrack'),
				'align'   => 'right',
				'sort'    => 'DESC'
			),
			'sum_ports_trunk' => array(
				'display' => __('Total Trunks', 'mactrack'),
				'align'   => 'right',
				'sort'    => 'DESC'
			),
			'sum_macs_active' => array(
				'display' => __('MACS Found', 'mactrack'),
				'align'   => 'right',
				'sort'    => 'DESC'
			)
		);

		$columns = cacti_sizeof($display_text);

		$nav = html_nav_bar('mactrack_view_sites.php', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, $columns, __('Sites / Device Types', 'mactrack'), 'page', 'main');

		print $nav;

		html_start_box('', '100%', '', '3', 'center', '');

		html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'));

		if (cacti_sizeof($sites)) {
			foreach ($sites as $site) {
				if (api_user_realm_auth('mactrack_sites.php')) {
					$actions = "<a class='pic' href='" . htmlspecialchars($webroot . 'mactrack_sites.php?action=edit&site_id=' . $site['site_id']) . "' title='" . __esc('Edit Site', 'mactrack') . "'><i class='mtEdit fas fa-edit'></i></a>";
				} else {
					$actions = '';
				}

				$actions .= "<a class='pic' href='" . htmlspecialchars($webroot . 'mactrack_view_devices.php?report=devices&site_id=' . $site['site_id'] . '&device_type_id=' . $site['device_type_id'] . '&type_id=-1&status=-1&filter=') . "' title='" . __esc('View Devices', 'mactrack') . "'><i class='mtServer fas fa-server'></i></a>";
				$actions .= "<a class='pic' href='" . htmlspecialchars($webroot . 'mactrack_view_ips.php?report=ips&reset&site_id=' . $site['site_id']) . "' title='" . __esc('View IP Ranges', 'mactrack') . "'><i class='mtIps fas fa-network-wired'></i></a>";
				$actions .= "<a class='pic' href='" . htmlspecialchars($webroot . 'mactrack_view_macs.php?report=macs&reset&device_id=-1&scan_date=3&site_id=' . $site['site_id']) . "' title='" . __esc('View MAC Addresses', 'mactrack') . "'><i class='mtMacs fas fa-at'></i></a>";
				$actions .= "<a class='pic' href='" . htmlspecialchars($webroot . 'mactrack_view_interfaces.php?report=interfaces&reset&site=' . $site['site_id']) . "' title='" . __esc('View Interfaces', 'mactrack') . "'><i class='mtInterfaces fas fa-ethernet'></i></a>";

				$dt = $site['device_type'];

				form_alternate_row('line' . $site['site_id']);
				form_selectable_cell($actions, 'row_' . $site['site_id'], '', 'nowrap');
				form_selectable_cell(filter_value($site['site_name'], get_request_var('filter')), 'row_' . $site['site_id'], '', 'hyperLink');
				form_selectable_cell(filter_value($site['vendor'], get_request_var('filter')), 'row_' . $site['site_id']);
				form_selectable_cell(filter_value($site['description'], get_request_var('filter')), 'row_' . $site['site_id']);
				form_selectable_cell(number_format_i18n($site['total_devices']), 'row_' . $site['site_id'], '', 'right');
				form_selectable_cell($dt == '1' ? __('N/A', 'mactrack') : number_format_i18n($site['sum_ips_total']), 'row_' . $site['site_id'], '', 'right');
				form_selectable_cell($dt == '3' ? __('N/A', 'mactrack') : number_format_i18n($site['sum_ports_total']), 'row_' . $site['site_id'], '', 'right');
				form_selectable_cell($dt == '3' ? __('N/A', 'mactrack') : number_format_i18n($site['sum_ports_active']), 'row_' . $site['site_id'], '', 'right');
				form_selectable_cell($dt == '3' ? __('N/A', 'mactrack') : number_format_i18n($site['sum_ports_trunk']), 'row_' . $site['site_id'], '', 'right');
				form_selectable_cell($dt == '3' ? __('N/A', 'mactrack') : number_format_i18n($site['sum_macs_active']), 'row_' . $site['site_id'], '', 'right');
				form_end_row();
			}
		} else {
			print '<tr><td colspan="' . $columns . '"><em>' . __('No Device Tracking Sites Found', 'mactrack') . '</em></td></tr>';
		}

		html_end_box(false);

		if (cacti_sizeof($sites)) {
			print $nav;

			mactrack_display_stats();
		}
	}

	print '<div id="response" title="' . __esc('Site Scan Results', 'mactrack') . '"></div>';
}

