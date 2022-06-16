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

$title = __('Device Tracking - ARP/IP View', 'mactrack');

set_default_action();

if (isset_request_var('export')) {
	mactrack_view_export_ips();
} else {
	general_header();
	mactrack_view_ips();
	bottom_footer();
}

/* ------------------------
    The 'actions' function
   ------------------------ */

function mactrack_view_ips_validate_request_vars() {
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
        'mac_filter_type_id' => array(
            'filter' => FILTER_VALIDATE_INT,
            'default' => '1'
            ),
        'ip_filter_type_id' => array(
            'filter' => FILTER_VALIDATE_INT,
            'default' => '1'
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

    validate_store_request_vars($filters, 'sess_mtv_arp');
    /* ================= input validation ================= */
}

function mactrack_view_export_ips() {
	mactrack_view_ips_validate_request_vars();

	$sql_where = '';

	$port_results = mactrack_view_get_ip_records($sql_where, 0, false);

	$xport_array = array();
	array_push($xport_array, '"site_name","hostname","device_name",' .
		'"mac_address","vendor_name",' .
		'"ip_address","dns_hostname","port_number","ifName","scan_date"');

	if (cacti_sizeof($port_results)) {
		foreach($port_results as $port_result) {
			$scan_date = $port_result["scan_date"];

			array_push($xport_array,'"' . $port_result['site_name'] . '","' .
			$port_result['hostname'] . '","' . $port_result['device_name'] . '","' .
			$port_result['mac_address'] . '","' . $port_result['vendor_name'] . '","' .
			$port_result['ip_address'] . '","' . $port_result['dns_hostname'] . '","' .
			$port_result['port_number'] . '","' . $port_result['ifName'] . '","' .
			$scan_date . '"');
		}
	}

	header('Content-type: application/csv');
	header('Content-Disposition: attachment; filename=cacti_port_ipaddresses_xport.csv');
	foreach($xport_array as $xport_line) {
		print $xport_line . "\n";
	}
}

function mactrack_view_get_ip_records(&$sql_where, $apply_limits = true, $rows) {
	/* form the 'where' clause for our main sql query */
	if (get_request_var('mac_filter') != '') {
		switch (get_request_var('mac_filter_type_id')) {
			case '1': /* do not filter */
				break;
			case '2': /* matches */
				$sql_where .= ($sql_where != '' ? ' AND':'WHERE') .
					' mti.mac_address = ' . db_qstr(get_request_var('mac_filter'));
				break;
			case '3': /* contains */
				$sql_where .= ($sql_where != '' ? ' AND':'WHERE') .
					' mti.mac_address LIKE ' . db_qstr('%' . get_request_var('mac_filter') . '%');
				break;
			case '4': /* begins with */
				$sql_where .= ($sql_where != '' ? ' AND':'WHERE') .
					' mti.mac_address LIKE ' . db_qstr(get_request_var('mac_filter') . '%');
				break;
			case '5': /* does not contain */
				$sql_where .= ($sql_where != '' ? ' AND':'WHERE') .
					' mti.mac_address NOT LIKE ' . db_qstr('%' . get_request_var('mac_filter') . '%');
				break;
			case '6': /* does not begin with */
				$sql_where .= ($sql_where != '' ? ' AND':'WHERE') .
					' mti.mac_address NOT LIKE ' . db_qstr(get_request_var('mac_filter') . '%');
		}
	}

	if ((get_request_var('ip_filter') != '') || (get_request_var('ip_filter_type_id') > 6)) {
		switch (get_request_var('ip_filter_type_id')) {
			case '1': /* do not filter */
				break;
			case '2': /* matches */
				$sql_where .= ($sql_where != '' ? ' AND':'WHERE') .
					' mti.ip_address = ' . db_qstr(get_request_var('ip_filter'));
				break;
			case '3': /* contains */
				$sql_where .= ($sql_where != '' ? ' AND':'WHERE') .
					' mti.ip_address LIKE ' . db_qstr('%' . get_request_var('ip_filter') . '%');
				break;
			case '4': /* begins with */
				$sql_where .= ($sql_where != '' ? ' AND':'WHERE') .
					' mti.ip_address LIKE ' . db_qstr(get_request_var('ip_filter') . '%');
				break;
			case '5': /* does not contain */
				$sql_where .= ($sql_where != '' ? ' AND':'WHERE') .
					' mti.ip_address NOT LIKE ' . db_qstr('%' . get_request_var('ip_filter') . '%');
				break;
			case '6': /* does not begin with */
				$sql_where .= ($sql_where != '' ? ' AND':'WHERE') .
					' mti.ip_address NOT LIKE ' . db_qstr(get_request_var('ip_filter') . '%');
				break;
			case '7': /* is null */
				$sql_where .= ($sql_where != '' ? ' AND':'WHERE') .
					' mti.ip_address = ""';
				break;
			case '8': /* is not null */
				$sql_where .= ($sql_where != '' ? ' AND':'WHERE') .
					' mti.ip_address != ""';
		}
	}

	if (get_request_var('filter') != '') {
		if (read_config_option('mt_reverse_dns') != '') {
			$sql_where .= ($sql_where != '' ? ' AND':'WHERE') .
				' (mti.dns_hostname LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ' OR ' .
				'mtod.vendor_name LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ')';
		} else {
			$sql_where .= ($sql_where != '' ? ' AND':'WHERE') .
				' (mtod.vendor_name LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ')';
		}
	}

	if ((get_request_var('site_id') != '-1')) {
		$sql_where .= ($sql_where != '' ? ' AND':'WHERE') .
			' mti.site_id = ' . get_request_var('site_id');
	}

	if ((get_request_var('device_id') != '-1')) {
		$sql_where .= ($sql_where != '' ? ' AND':'WHERE') .
			' mti.device_id = ' . get_request_var('device_id');
	}

	/* prevent table scans, either a device or site must be selected */
	if (get_request_var('site_id') == -1 && get_request_var('device_id') == -1) {
		if ($sql_where == '') {
			return array();
		}
	}

	$sql_order = get_order_string();
	if ($apply_limits && $rows != 999999) {
		$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ', ' . $rows;
	} else {
		$sql_limit = '';
	}

	$query_string = "SELECT mti.*, mts.site_name, mtod.*, mtin.ifName
		FROM mac_track_ips AS mti
		LEFT JOIN mac_track_sites AS mts
		ON mti.site_id = mts.site_id
		LEFT JOIN mac_track_oui_database AS mtod
		ON mtod.vendor_mac=SUBSTRING(mti.mac_address, 1, 8)
		LEFT JOIN mac_track_interfaces AS mtin
		ON mtin.device_id=mti.device_id
		AND mtin.ifIndex=mti.port_number
		$sql_where
		$sql_order
		$sql_limit";

	//echo $query_string;

	return db_fetch_assoc($query_string);
}

function mactrack_view_ips() {
	global $title, $report, $mactrack_search_types, $rows_selector, $config;
	global $item_rows;

	mactrack_view_ips_validate_request_vars();

	mactrack_tabs();

	html_start_box($title, '100%', '', '3', 'center', '');
	mactrack_ip_address_filter();
	html_end_box();

	$sql_where = '';

	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} elseif (get_request_var('rows') == -2) {
		$rows = 999999;
	} else {
		$rows = get_request_var('rows');
	}

	$port_results = mactrack_view_get_ip_records($sql_where, true, $rows);

	/* prevent table scans, either a device or site must be selected */
	if ($sql_where == '') {
		$total_rows = 0;
	} elseif (get_request_var('rows') == 1) {
		$rows_query_string = "SELECT
			COUNT(mti.device_id)
			FROM mac_track_ips AS mti
			LEFT JOIN mac_track_sites AS mts
			ON mti.site_id=mts.site_id
			LEFT JOIN mac_track_oui_database AS mtod
			ON mtod.vendor_mac=SUBSTRING(mti.mac_address,1,8)
			$sql_where";

		$total_rows = db_fetch_cell($rows_query_string);
	} else {
		$rows_query_string = "SELECT
			COUNT(DISTINCT device_id, mac_address, port_number, ip_address)
			FROM mac_track_ips AS mti
			LEFT JOIN mac_track_sites AS mts
			ON mti.site_id=mts.site_id
			LEFT JOIN mac_track_oui_database AS mtod
			ON mtod.vendor_mac=SUBSTRING(mti.mac_address,1,8)
			$sql_where";

		$total_rows = db_fetch_cell($rows_query_string);
	}

	$display_text1 = array(
		'device_name' => array(
			'display' => __('Switch Name', 'mactrack'),
			'sort'    => 'ASC'
		),
		'hostname' => array(
			'display' => __('Switch Hostname', 'mactrack'),
			'sort'    => 'ASC'
		),
		'ip_address' => array(
			'display' => __('ED IP Address', 'mactrack'),
			'sort'    => 'ASC'
		),
	);

	$display_dns = array();
	if (read_config_option('mt_reverse_dns') != '') {
		$display_dns = array(
			'dns_hostname' => array(
				'display' => __('ED DNS Hostname', 'mactrack'),
				'sort'    => 'ASC'
			)
		);
	}

	$display_text2 = array(
		'mac_address' => array(
			'display' => __('ED MAC Address', 'mactrack'),
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
		'ifName' => array(
			'display' => __('Port Name', 'mactrack'),
			'align'   => 'right',
			'sort'    => 'ASC'
		)
	);

	$display_text = array_merge($display_text1, $display_dns, $display_text2);

	$columns = sizeof($display_text);

	$nav = html_nav_bar('mactrack_view_arp.php', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, $columns, __('ARP Cache', 'mactrack'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'));

	$i = 0;
	$delim = read_config_option('mt_mac_delim');
	if (cacti_sizeof($port_results)) {
		foreach ($port_results as $port_result) {
			form_alternate_row('line' . $i, true);

			form_selectable_cell($port_result['device_name'], $i);
			form_selectable_cell($port_result['hostname'], $i);
			form_selectable_cell(filter_value($port_result['ip_address'], get_request_var('filter')), $i);

			if (read_config_option('mt_reverse_dns') != '') {
				form_selectable_cell(filter_value($port_result['dns_hostname'], get_request_var('filter')), $i);
			}

			form_selectable_cell(filter_value($port_result['mac_address'], get_request_var('filter')), $i);
			form_selectable_cell(filter_value($port_result['vendor_name'], get_request_var('filter')), $i);
			form_selectable_cell($port_result['port_number'], $i, '', 'right');
			form_selectable_cell($port_result['ifName'], $i, '', 'right');

			form_end_row();

			$i++;
		}
	} else {
		if (get_request_var('site_id') == -1 && get_request_var('device_id') == -1) {
			print '<tr><td colspan="10"><em>' . __('You must first choose a Site, Device or other search criteria.', 'mactrack') . '</em></td></tr>';
		} else {
			print '<tr><td colspan="10"><em>' . __('No Device Tracking IP Results Found', 'mactrack') . '</em></td></tr>';
		}
	}

	html_end_box(false);

	if (cacti_sizeof($port_results)) {
		print $nav;
		mactrack_display_stats();
	}
}

function mactrack_ip_address_filter() {
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
								$filter_devices = db_fetch_assoc('SELECT DISTINCT device_id, device_name, hostname
									FROM mac_track_devices
									ORDER BY device_name');
							} else {
								$filter_devices = db_fetch_assoc_prepared('SELECT DISTINCT device_id, device_name, hostname
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
						<?php print __('IP\'s', 'mactrack');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<?php
							if (cacti_sizeof($rows_selector) > 0) {
							foreach ($rows_selector as $key => $value) {
								print '<option value="' . $key . '"'; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . $value . '</option>';
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
				</tr>
			</table>
			</form>
			<script type='text/javascript'>

			function applyFilter() {
				strURL  = urlPath+'plugins/mactrack/mactrack_view_arp.php?report=arp&header=false';
				strURL += '&site_id=' + $('#site_id').val();
				strURL += '&device_id=' + $('#device_id').val();
				strURL += '&rows=' + $('#rows').val();
				strURL += '&mac_filter_type_id=' + $('#mac_filter_type_id').val();
				strURL += '&mac_filter=' + $('#mac_filter').val();
				strURL += '&filter=' + $('#filter').val();
				strURL += '&ip_filter_type_id=' + $('#ip_filter_type_id').val();
				strURL += '&ip_filter=' + $('#ip_filter').val();

				loadPageNoHeader(strURL);
			}

			function clearFilter() {
				strURL  = urlPath+'plugins/mactrack/mactrack_view_arp.php?report=arp&header=false&clear=true';
				loadPageNoHeader(strURL);
			}

			function exportRows() {
				strURL  = urlPath+'plugins/mactrack/mactrack_view_arp.php?report=arp&export=true';
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

