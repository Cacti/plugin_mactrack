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

$title = __('Device Tracking - Site IP Range Report View', 'mactrack');

if (isset_request_var('export')) {
	mactrack_view_export_ip_ranges();
} else {
	general_header();
	mactrack_view_ip_ranges();
	bottom_footer();
}

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
            'filter' => FILTER_CALLBACK,
            'default' => '',
            'options' => array('options' => 'sanitize_search_string')
            ),
        'mac_filter' => array(
            'filter' => FILTER_CALLBACK,
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
            )
    );

    validate_store_request_vars($filters, 'sess_mtv_ips');
    /* ================= input validation ================= */
}

function mactrack_view_export_ip_ranges() {
	mactrack_view_ips_validate_request_vars();

	$sql_where = '';

	$ip_ranges = mactrack_view_get_ip_range_records($sql_where, 0, false);

	$xport_array = array();

	array_push($xport_array, '"site_id","site_name","ip_range",' .
			'"ips_current","ips_current_date","ips_max","ips_max_date"');

	if (is_array($ip_ranges)) {
		foreach($ip_ranges as $ip_range) {
			array_push($xport_array,'"'   .
				$ip_range['site_id']     . '","' . $ip_range['site_name']        . '","' .
				$ip_range['ip_range']    . '","' .
				$ip_range['ips_current'] . '","' . $ip_range['ips_current_date'] . '","' .
				$ip_range['ips_max']     . '","' . $ip_range['ips_max_date']     . '"');
		}
	}

	header('Content-type: application/csv');
	header('Content-Disposition: attachment; filename=cacti_ip_range_xport.csv');
	foreach($xport_array as $xport_line) {
		print $xport_line . "\n";
	}
}

function mactrack_view_get_ip_range_records(&$sql_where, $rows, $apply_limits = true) {
	if (get_request_var('site_id') != '-1') {
		$sql_where = 'WHERE mtir.site_id = ' . get_request_var('site_id');
	} else {
		$sql_where = '';
	}

	$sql_order = get_order_string();
	if ($apply_limits) {
		$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ', ' . $rows;
	} else {
		$sql_limit = '';
	}

	$ip_ranges = "SELECT
		mts.site_id,
		mts.site_name,
		mtir.ip_range,
		mtir.ips_max,
		mtir.ips_current,
		mtir.ips_max_date,
		mtir.ips_current_date
		FROM mac_track_ip_ranges AS mtir
		INNER JOIN mac_track_sites AS mts
		ON mtir.site_id = mts.site_id
		$sql_where
		$sql_order
		$sql_limit";

	return db_fetch_assoc($ip_ranges);
}

function mactrack_view_ip_ranges() {
	global $title, $config, $item_rows;

	mactrack_view_ips_validate_request_vars();

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
	mactrack_ips_filter();
	html_end_box();

	$sql_where = '';

	$ip_ranges = mactrack_view_get_ip_range_records($sql_where, $rows);

	$total_rows = db_fetch_cell("SELECT
		COUNT(mtir.ip_range)
		FROM mac_track_ip_ranges AS mtir
		INNER JOIN mac_track_sites AS mts
		ON mtir.site_id = mts.site_id
		$sql_where");

	$display_text = array(
		'nosort' => array(
			'display' => __('Actions', 'mactrack')
		),
		'site_name' => array(
			'display' => __('Site Name', 'mactrack'),
			'sort'    => 'ASC'
		),
		'ip_range' => array(
			'display' => __('IP Range', 'mactrack'),
			'sort'    => 'ASC'
		),
		'ips_current' => array(
			'display' => __('Current IP Addresses', 'mactrack'),
			'align'   => 'right',
			'sort'    => 'DESC'
		),
		'ips_current_date' => array(
			'display' => __('Current Date', 'mactrack'),
			'align'   => 'right',
			'sort'    => 'DESC'
		),
		'ips_max' => array(
			'display' => __('Maximum IP Addresses', 'mactrack'),
			'align'   => 'right',
			'sort'    => 'DESC'
		),
		'ips_max_date' => array(
			'display' => __('Maximum Date', 'mactrack'),
			'align'   => 'right',
			'sort'    => 'DESC'
		)
	);

	$columns = sizeof($display_text);

	$nav = html_nav_bar('mactrack_view_ips.php', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, $columns, __('IP Address Ranges', 'mactrack'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'));

	if (cacti_sizeof($ip_ranges)) {
		$i = 0;

		foreach ($ip_ranges as $ip_range) {
			$url  = '<a href="' . html_escape($webroot . 'mactrack_sites.php' .
				'?action=edit' .
				'&reset=true' .
				'&site_id=' . $ip_range['site_id']) . '" title="' . __esc('Edit Site', 'mactrack') . '"><i class="mtEdit fas fa-edit"></i></a>';

			$url .= '<a href="' . html_escape($webroot . 'mactrack_view_macs.php' .
				'?report=macs' .
				'&reset=true' .
				'&ip_filter_type_id=3' .
				'&ip_filter=' . $ip_range['ip_range'] .
				'&device_id=-1' .
				'&scan_date=3' .
				'&site_id=' . $ip_range['site_id']) . '" title="' . __esc('View MAC Addresses', 'mactrack') . '"><i class="mtMacs fas fa-at"></i></a>';

			$url .= '<a href="' . html_escape($webroot . 'mactrack_view_arp.php' .
				'?report=arp' .
				'&reset=true' .
				'&ip_filter_type_id=3' .
				'&ip_filter=' . $ip_range['ip_range'] . '.' .
				'&device_id=-1' .
				'&scan_date=3' .
				'&site_id=' . $ip_range['site_id']) . '" title="' . __esc('View IP Addresses', 'mactrack') . '"><i class="mtPorts fas fa-desktop"></i></a>';

			form_alternate_row('line' . $i, true);
			form_selectable_cell($url, $i);
			form_selectable_cell($ip_range['site_name'], $i);
			form_selectable_cell($ip_range['ip_range'] . '.*', $i);
			form_selectable_cell(number_format_i18n($ip_range['ips_current']), $i, '', 'right');
			form_selectable_cell($ip_range['ips_current_date'], $i, '', 'right');
			form_selectable_cell(number_format_i18n($ip_range['ips_max']), $i, '', 'right');
			form_selectable_cell($ip_range['ips_max_date'], $i, '', 'right');
			form_end_row();

			$i++;
		}
	} else {
		print '<tr><td colspan="' . $columns . '"><em>' . __('No Device Tracking Site IP Ranges Found', 'mactrack') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($ip_ranges)) {
		print $nav;
		mactrack_display_stats();
	}
}

function mactrack_ips_filter() {
	global $item_rows;

	?>
	<tr class='even'>
		<td>
			<form id='form_mactrack_view_ips'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Site', 'mactrack');?>
					</td>
					<td>
						<select id='site_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('site_id') == '-1') {?> selected<?php }?>><?php print __('Any', 'mactrack');?></option>
							<?php
							$sites = db_fetch_assoc('SELECT * FROM mac_track_sites ORDER BY mac_track_sites.site_name');
							if (cacti_sizeof($sites)) {
								foreach ($sites as $site) {
									print '<option value="' . $site['site_id'] . '"'; if (get_request_var('site_id') == $site['site_id']) { print ' selected'; } print '>' . $site['site_name'] . '</option>';
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
							<option value='-1'<?php if (get_request_var('rows') == '-1') {?> selected<?php }?>><?php print __('Default', 'mactrack');?></option>
							<?php
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . $value . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<span class='nowrap'>
							<input type='button' id='export' value='<?php print __esc('Export', 'mactrack');?>'>
							<input type='button' id='clear' value='<?php print __esc('Clear', 'mactrack');?>'>
						</span>
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' value='<?php print get_request_var('page');?>'>
			<input type='hidden' id='report' value='ips'>
			</form>
			<script type='text/javascript'>

			function applyFilter() {
				strURL  = urlPath+'plugins/mactrack/mactrack_view_ips.php?report=ips&header=false';
				strURL += '&site_id=' + $('#site_id').val();
				strURL += '&rows=' + $('#rows').val();
				loadPageNoHeader(strURL);
			}

			function clearFilter() {
				strURL  = urlPath+'plugins/mactrack/mactrack_view_ips.php?report=ips&header=false&clear=true';
				loadPageNoHeader(strURL);
			}

			function exportRows() {
				strURL  = urlPath+'plugins/mactrack/mactrack_view_ips.php?report=ips&export=true';
				document.location = strURL;
			}

			$(function() {
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

