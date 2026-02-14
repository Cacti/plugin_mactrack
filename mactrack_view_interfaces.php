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

$title = __('Mactrack - Network Interfaces View', 'mactrack');

// check actions
if (isset_request_var('export')) {
    mactrack_export_records();
} else {
    mactrack_view();
}

function mactrack_get_records(&$sql_where, $apply_limits = true, $rows = '30')
{
    global $timespan, $group_function, $summary_stats;

    $match = read_config_option('mt_ignorePorts', true);
    if ('' == $match) {
        $match = '(Vlan|Loopback|Null)';
        db_execute_prepared('REPLACE INTO settings SET name="mt_ignorePorts", value = ?', [$match]);
    }
    $ignore = "(ifName NOT REGEXP '".$match."' AND ifDescr NOT REGEXP '".$match."')";

    // issues sql where
    if ('-2' == get_request_var('issues')) { // All Interfaces
        // do nothing all records
    } elseif ('-3' == get_request_var('issues')) { // Non Ignored Interfaces
        $sql_where .= ('' != $sql_where ? ' AND ' : 'WHERE ').$ignore;
    } elseif ('-4' == get_request_var('issues')) { // Ignored Interfaces
        $sql_where .= ('' != $sql_where ? ' AND ' : 'WHERE ').' NOT '.$ignore;
    } elseif ('-1' == get_request_var('issues')) { // With Issues
        $sql_where .= ('' != $sql_where ? ' AND ' : 'WHERE ')."((int_errors_present=1 OR int_discards_present=1) AND {$ignore})";
    } elseif ('0' == get_request_var('issues')) { // Up Interfaces
        $sql_where .= ('' != $sql_where ? ' AND ' : 'WHERE ')."(ifOperStatus=1 AND {$ignore})";
    } elseif ('1' == get_request_var('issues')) { // Up w/o Alias
        $sql_where .= ('' != $sql_where ? ' AND ' : 'WHERE ')."(ifOperStatus=1 AND ifAlias='' AND {$ignore})";
    } elseif ('2' == get_request_var('issues')) { // Errors Up
        $sql_where .= ('' != $sql_where ? ' AND ' : 'WHERE ')."(int_errors_present=1 AND {$ignore})";
    } elseif ('3' == get_request_var('issues')) { // Discards Up
        $sql_where .= ('' != $sql_where ? ' AND ' : 'WHERE ')."(int_discards_present=1 AND {$ignore})";
    } elseif ('7' == get_request_var('issues')) { // Change < 24 Hours
        $sql_where .= ('' != $sql_where ? ' AND ' : 'WHERE ').'(mac_track_interfaces.sysUptime-ifLastChange < 8640000) AND ifLastChange > 0 AND (mac_track_interfaces.sysUptime-ifLastChange > 0)';
    } elseif ('9' == get_request_var('issues') && '-1' != get_filter_request_var('bwusage')) { // In/Out over 70%
        $sql_where .= ('' != $sql_where ? ' AND ' : 'WHERE ').'((inBound>'.get_request_var('bwusage').' OR outBound>'.get_request_var('bwusage').") AND {$ignore})";
    } elseif ('10' == get_request_var('issues') && '-1' != get_filter_request_var('bwusage')) { // In over 70%
        $sql_where .= ('' != $sql_where ? ' AND ' : 'WHERE ').'(inBound>'.get_request_var('bwusage')." AND {$ignore})";
    } elseif ('11' == get_request_var('issues') && '-1' != get_filter_request_var('bwusage')) { // Out over 70%
        $sql_where .= ('' != $sql_where ? ' AND ' : 'WHERE ').'(outBound>'.get_request_var('bwusage')." AND {$ignore})";
    }

    // filter sql where
    $filter_where = mactrack_create_sql_filter(get_request_var('filter'), ['ifAlias', 'hostname', 'ifName', 'ifDescr']);

    if ('' != $filter_where) {
        $sql_where .= ('' != $sql_where ? ' AND ' : 'WHERE ').$filter_where;
    }

    // device_id sql where
    if ('-1' == get_filter_request_var('device_id')) {
        // do nothing all states
    } else {
        $sql_where .= ('' != $sql_where ? ' AND ' : 'WHERE ').'mac_track_interfaces.device_id='.get_request_var('device_id');
    }

    // site sql where
    if ('-1' == get_filter_request_var('site_id')) {
        // do nothing all sites
    } else {
        $sql_where .= ('' != $sql_where ? ' AND ' : 'WHERE ').'mac_track_interfaces.site_id='.get_request_var('site_id');
    }

    // type sql where
    if ('-1' == get_filter_request_var('device_type_id')) {
        // do nothing all states
    } else {
        $sql_where .= ('' != $sql_where ? ' AND ' : 'WHERE ').'mac_track_devices.device_type_id='.get_request_var('device_type_id');
    }

    $sql_order = get_order_string();
    if ($apply_limits) {
        $sql_limit = ' LIMIT '.($rows * (get_request_var('page') - 1)).', '.$rows;
    } else {
        $sql_limit = '';
    }

    $sql_query = "SELECT mac_track_interfaces.*,
		mac_track_device_types.description AS device_type,
		mac_track_devices.device_name,
		mac_track_devices.host_id,
		mac_track_devices.disabled,
		mac_track_devices.last_rundate
		FROM mac_track_interfaces
		INNER JOIN mac_track_devices
		ON mac_track_interfaces.device_id=mac_track_devices.device_id
		INNER JOIN mac_track_device_types
		ON mac_track_device_types.device_type_id=mac_track_devices.device_type_id
		{$sql_where}
		{$sql_order}
		{$sql_limit}";

    // echo $sql_query;

    return db_fetch_assoc($sql_query);
}

function mactrack_interfaces_request_validation()
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
        'filter' => [
            'filter' => FILTER_CALLBACK,
            'pageset' => true,
            'default' => '',
            'options' => ['options' => 'sanitize_search_string'],
        ],
        'sort_column' => [
            'filter' => FILTER_CALLBACK,
            'default' => 'device_name',
            'options' => ['options' => 'sanitize_search_string'],
        ],
        'sort_direction' => [
            'filter' => FILTER_CALLBACK,
            'default' => 'ASC',
            'options' => ['options' => 'sanitize_search_string'],
        ],
        'site_id' => [
            'filter' => FILTER_VALIDATE_INT,
            'default' => '-1',
            'pageset' => true,
        ],
        'device_id' => [
            'filter' => FILTER_VALIDATE_INT,
            'default' => '-1',
            'pageset' => true,
        ],
        'device_type_id' => [
            'filter' => FILTER_VALIDATE_INT,
            'default' => '-1',
            'pageset' => true,
        ],
        'issues' => [
            'filter' => FILTER_VALIDATE_INT,
            'default' => '-2',
            'pageset' => true,
        ],
        'period' => [
            'filter' => FILTER_VALIDATE_INT,
            'default' => '-2',
            'pageset' => true,
        ],
        'bwusage' => [
            'filter' => FILTER_VALIDATE_INT,
            'default' => read_config_option('mt_interface_high'),
            'pageset' => true,
        ],
        'totals' => [
            'filter' => FILTER_CALLBACK,
            'default' => 'true',
            'options' => ['options' => 'sanitize_search_string'],
        ],
    ];

    validate_store_request_vars($filters, 'sess_mtv_int');
    // ================= input validation =================
}

function mactrack_export_records()
{
    mactrack_interfaces_request_validation();

    $sql_where = '';

    $stats = mactrack_get_records($sql_where, true, 10000);

    $xport_array = [];

    array_push($xport_array, '"device_name","device_type",'
        .'"sysUptime",'
        .'"ifIndex","ifName",'
        .'"ifAlias","ifDescr",'
        .'"ifType","ifMtu",'
        .'"ifSpeed","ifHighSpeed",'
        .'"ifPhysAddress","ifAdminStatus",'
        .'"ifOperStatus","ifLastChange",'
        .'"ifHCInOctets","ifHCOutOctets",'
        .'"ifInDiscards","ifInErrors",'
        .'"ifInUnknownProtos","ifOutDiscards",'
        .'"ifOutErrors","last_up_time",'
        .'"last_down_time","stateChanges",');

    if (cacti_sizeof($stats)) {
        foreach ($stats as $stat) {
            array_push($xport_array, '"'
                .$stat['device_name'].'","'.$stat['device_type'].'","'
                .$stat['sysUptime'].'","'.$stat['ifIndex'].'","'
                .$stat['ifName'].'","'.$stat['ifAlias'].'","'
                .$stat['ifDescr'].'","'.$stat['ifType'].'","'
                .$stat['ifMtu'].'","'.$stat['ifSpeed'].'","'
                .$stat['ifHighSpeed'].'","'.$stat['ifPhysAddress'].'","'
                .$stat['ifAdminStatus'].'","'.$stat['ifOperStatus'].'","'
                .$stat['ifLastChange'].'","'.$stat['ifHCInOctets'].'","'
                .$stat['ifHCOutOctets'].'","'.$stat['ifInDiscards'].'","'
                .$stat['ifInErrors'].'","'.$stat['ifInUnknownProtos'].'","'
                .$stat['ifOutDiscards'].'","'.$stat['ifOutErrors'].'","'
                .$stat['last_up_time'].'","'.$stat['last_down_time'].'","'
                .$stat['stateChanges'].'"');
        }
    }

    header('Content-type: application/csv');
    header('Cache-Control: max-age=15');
    header('Content-Disposition: attachment; filename=device_mactrack_xport.csv');
    foreach ($xport_array as $xport_line) {
        echo $xport_line."\n";
    }
}

function mactrack_view()
{
    global $title, $mactrack_rows, $config;

    mactrack_interfaces_request_validation();

    general_header();

    $sql_where = '';

    if (-1 == get_request_var('rows')) {
        $rows = read_config_option('num_rows_table');
    } elseif (-2 == get_request_var('rows')) {
        $rows = 99999999;
    } else {
        $rows = get_request_var('rows');
    }

    $stats = mactrack_get_records($sql_where, true, $rows);

    mactrack_tabs();

    html_start_box($title, '100%', '', '3', 'center', '');
    mactrack_filter_table();
    html_end_box();

    $rows_query_string = "SELECT COUNT(*)
		FROM mac_track_interfaces
		INNER JOIN mac_track_devices
		ON mac_track_interfaces.device_id=mac_track_devices.device_id
		INNER JOIN mac_track_device_types
		ON mac_track_device_types.device_type_id=mac_track_devices.device_type_id
		{$sql_where}";

    $total_rows = db_fetch_cell($rows_query_string);

    $display_text = mactrack_display_array();

    $columns = cacti_sizeof($display_text);

    $nav = html_nav_bar('mactrack_view_interfaces.php?report=interfaces', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, $columns, __('Interfaces', 'mactrack'), 'page', 'main');

    echo $nav;

    html_start_box('', '100%', '', '3', 'left', '');

    html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'));

    $i = 0;
    if (cacti_sizeof($stats)) {
        foreach ($stats as $stat) {
            // find the background color and enclose it
            $class = mactrack_int_row_class($stat);

            if ($class) {
                echo "<tr id='line_".$stat['device_id'].'_'.$stat['ifName']."' class='tableRow selectable {$class}'>";
            } else {
                if (($i % 2) == 1) {
                    $class = 'odd';
                } else {
                    $class = 'even';
                }

                echo "<tr id='line_".$stat['device_id']."' class='tableRow selectable {$class}'>";
            }

            ++$i;

            echo mactrack_format_interface_row($stat);

            form_end_row();
        }
    } else {
        echo '<tr><td colspan="7"><em>'.__('No Mactrack Interfaces Found', 'mactrack').'</em></td></tr>';
    }

    html_end_box(false);

    if (cacti_sizeof($stats)) {
        echo $nav;
    }

    echo '<div class="center" style="position:fixed;left:0;bottom:0;display:table;margin-left:auto;margin-right:auto;width:100%;">';

    html_start_box('', '100%', '', '3', 'center', '');
    echo '<tr>';
    mactrack_legend_row('int_up', __('Interface Up', 'mactrack'));
    mactrack_legend_row('int_up_wo_alias', __('No Alias', 'mactrack'));
    mactrack_legend_row('int_errors', __('Errors Present', 'mactrack'));
    mactrack_legend_row('int_discards', __('Discards Present', 'mactrack'));
    mactrack_legend_row('int_no_graph', __('No Graphs', 'mactrack'));
    mactrack_legend_row('int_down', __('Interface Down', 'mactrack'));
    echo '</tr>';
    html_end_box(false);

    echo '</div>';

    if (cacti_sizeof($stats)) {
        mactrack_display_stats();
    }

    echo '<div id="response" title="'.__esc('Interface Scan Results', 'mactrack').'"></div>';

    bottom_footer();
}

function mactrack_display_array()
{
    $display_text = [
        'nosort' => [
            'display' => __('Actions', 'mactrack'),
            'sort' => 'ASC',
        ],
        'hostname' => [
            'display' => __('Hostname', 'mactrack'),
            'sort' => 'ASC',
        ],
        'device_type' => [
            'display' => __('Type', 'mactrack'),
            'sort' => 'ASC',
        ],
        'ifName' => [
            'display' => __('Name', 'mactrack'),
            'sort' => 'ASC',
        ],
        'ifDescr' => [
            'display' => __('Description', 'mactrack'),
            'sort' => 'ASC',
        ],
        'ifAlias' => [
            'display' => __('Alias', 'mactrack'),
            'sort' => 'ASC',
        ],
        'inBound' => [
            'display' => __('InBound %%%', 'mactrack'),
            'align' => 'right',
            'sort' => 'DESC',
        ],
        'outBound' => [
            'display' => __('OutBound %%%', 'mactrack'),
            'align' => 'right',
            'sort' => 'DESC',
        ],
        'int_ifHCInOctets' => [
            'display' => __('In (B/S)', 'mactrack'),
            'align' => 'right',
            'sort' => 'DESC',
        ],
        'int_ifHCOutOctets' => [
            'display' => __('Out (B/S)', 'mactrack'),
            'align' => 'right',
            'sort' => 'DESC',
        ],
    ];

    if ('true' == get_request_var('totals')) {
        $display_text += [
            'ifInErrors' => [
                'display' => __('In Err Total', 'mactrack'),
                'align' => 'right',
                'sort' => 'DESC',
            ],
            'ifInDiscards' => [
                'display' => __('In Disc Total', 'mactrack'),
                'align' => 'right',
                'sort' => 'DESC',
            ],
            'ifInUnknownProtos' => [
                'display' => __('UProto Total', 'mactrack'),
                'align' => 'right',
                'sort' => 'DESC',
            ],
            'ifOutErrors' => [
                'display' => __('Out Err Total', 'mactrack'),
                'align' => 'right',
                'sort' => 'DESC',
            ],
            'ifOutDiscards' => [
                'display' => __('Out Disc Total', 'mactrack'),
                'align' => 'right',
                'sort' => 'DESC',
            ],
        ];
    } else {
        $display_text += [
            'int_ifInErrors' => [
                'display' => __('In Err (E/S)', 'mactrack'),
                'align' => 'right',
                'sort' => 'DESC',
            ],
            'int_ifInDiscards' => [
                'display' => __('In Disc (D/S)', 'mactrack'),
                'align' => 'right',
                'sort' => 'DESC',
            ],
            'int_ifInUnknownProtos' => [
                'display' => __('UProto (UP/S)', 'mactrack'),
                'align' => 'right',
                'sort' => 'DESC',
            ],
            'int_ifOutErrors' => [
                'display' => __('Out Err (OE/S)', 'mactrack'),
                'align' => 'right',
                'sort' => 'DESC',
            ],
            'int_ifOutDiscards' => [
                'display' => __('Out Disc (OD/S)', 'mactrack'),
                'align' => 'right',
                'sort' => 'DESC',
            ],
        ];
    }

    $display_text += [
        'ifOperStatus' => [
            'display' => __('Status', 'mactrack'),
            'align' => 'right',
            'sort' => 'ASC',
        ],
        'ifLastChange' => [
            'display' => __('Last Change', 'mactrack'),
            'align' => 'right',
            'sort' => 'ASC',
        ],
        'last_rundate' => [
            'display' => __('Last Scanned', 'mactrack'),
            'align' => 'right',
            'sort' => 'ASC',
        ],
    ];

    return $display_text;
}

function mactrack_filter_table()
{
    global $config, $rows_selector;

    ?>
	<tr class='even'>
		<td>
		<form id='mactrack'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php echo __('Site', 'mactrack'); ?>
					</td>
					<td>
						<select id='site_id' onChange='applyFilter()'>
							<option value='-1'<?php if ('-1' == get_request_var('site_id')) {?> selected<?php }?>><?php echo __('All', 'mactrack'); ?></option>
							<?php
                            $sites = db_fetch_assoc('SELECT site_id, site_name FROM mac_track_sites ORDER BY site_name');
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
						<?php echo __('Filters', 'mactrack'); ?>
					</td>
					<td>
						<select id='issues' onChange='applyFilter()'>
							<option value='-2'<?php if ('-2' == get_request_var('issues')) {?> selected<?php }?>><?php echo __('All Interfaces', 'mactrack'); ?></option>
							<option value='-3'<?php if ('-3' == get_request_var('issues')) {?> selected<?php }?>><?php echo __('All Non-Ignored Interfaces', 'mactrack'); ?></option>
							<option value='-4'<?php if ('-4' == get_request_var('issues')) {?> selected<?php }?>><?php echo __('All Ignored Interfaces', 'mactrack'); ?></option>
							<?php if ('-1' != get_request_var('bwusage')) {?><option value='9'<?php if ('9' == get_request_var('issues') && '-1' != get_request_var('bwusage')) {?> selected<?php }?>><?php echo __('High In/Out Utilization > %d &#37;', get_request_var('bwusage'), 'mactrack'); ?></option><?php }?>
							<?php if ('-1' != get_request_var('bwusage')) {?><option value='10'<?php if ('10' == get_request_var('issues') && '-1' != get_request_var('bwusage')) {?> selected<?php }?>><?php echo __('High In Utilization > %d &#37;', get_request_var('bwusage'), 'mactrack'); ?></option><?php }?>
							<?php if ('-1' != get_request_var('bwusage')) {?><option value='11'<?php if ('11' == get_request_var('issues') && '-1' != get_request_var('bwusage')) {?> selected<?php }?>><?php echo __('High Out Utilization > %d &#37;', get_request_var('bwusage'), 'mactrack'); ?></option><?php }?>
							<option value='-1'<?php if ('-1' == get_request_var('issues')) {?> selected<?php }?>><?php echo __('With Issues', 'mactrack'); ?></option>
							<option value='0'<?php if ('0' == get_request_var('issues')) {?> selected<?php }?>><?php echo __('Up Interfaces', 'mactrack'); ?></option>
							<option value='1'<?php if ('1' == get_request_var('issues')) {?> selected<?php }?>><?php echo __('Up Interfaces No Alias', 'mactrack'); ?></option>
							<option value='2'<?php if ('2' == get_request_var('issues')) {?> selected<?php }?>><?php echo __('Errors Accumulating', 'mactrack'); ?></option>
							<option value='3'<?php if ('3' == get_request_var('issues')) {?> selected<?php }?>><?php echo __('Discards Accumulating', 'mactrack'); ?></option>
							<option value='7'<?php if ('7' == get_request_var('issues')) {?> selected<?php }?>><?php echo __('Changed in Last Day', 'mactrack'); ?></option>
						</select><BR>
					<td>
						<?php echo __('Bandwidth', 'mactrack'); ?>
					</td>
					<td>
						<select id='bwusage' onChange='applyFilter()'>
							<option value='-1'<?php if ('-1' == get_request_var('bwusage')) {?> selected<?php }?>><?php echo __('N/A', 'mactrack'); ?></option>
							<?php
    for ($bwpercent = 10; $bwpercent < 100; $bwpercent += 10) {
        ?><option value='<?php echo $bwpercent; ?>' <?php if (isset_request_var('bwusage') and (get_request_var('bwusage') == $bwpercent)) {?> selected<?php }?>> >=<?php echo $bwpercent; ?>%</option><?php
    }
    ?>
						</select>
					</td>
					<td>
						<span>
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
						<?php echo __('Type', 'mactrack'); ?>
					</td>
					<td>
						<select id='device_type_id' onChange='applyFilter()'>
							<option value='-1'<?php if ('-1' == get_request_var('device_type_id')) {?> selected<?php }?>><?php echo __('All', 'mactrack'); ?></option>
							<?php
    $sql_where = '';
    if (-1 != get_request_var('site_id')) {
        $sql_where .= ' WHERE mac_track_devices.site_id='.get_request_var('site_id');
    } else {
        $sql_where = '';
    }

    $types = db_fetch_assoc("SELECT DISTINCT mac_track_device_types.device_type_id,
								mac_track_device_types.description AS device_type
								FROM mac_track_device_types
								INNER JOIN mac_track_devices
								ON mac_track_device_types.device_type_id=mac_track_devices.device_type_id
								{$sql_where}
								ORDER BY device_type");

    if (cacti_sizeof($types)) {
        foreach ($types as $type) {
            echo '<option value="'.$type['device_type_id'].'"';
            if (get_request_var('device_type_id') == $type['device_type_id']) {
                echo ' selected';
            } echo '>'.$type['device_type'].'</option>';
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
    $sql_where = '';
    if (-1 != get_request_var('site_id')) {
        $sql_where .= ('' != $sql_where ? ' AND ' : 'WHERE ').'site_id='.get_request_var('site_id');
    }

    if ('-1' != get_request_var('device_type_id')) {
        $sql_where .= ('' != $sql_where ? ' AND ' : 'WHERE ').'device_type_id='.get_request_var('device_type_id');
    }

    $devices = array_rekey(db_fetch_assoc("SELECT device_id, device_name FROM mac_track_devices {$sql_where} ORDER BY device_name"), 'device_id', 'device_name');
    if (cacti_sizeof($devices)) {
        foreach ($devices as $device_id => $device_name) {
            echo '<option value="'.$device_id.'"';
            if (get_request_var('device_id') == $device_id) {
                echo ' selected';
            } echo '>'.$device_name.'</option>';
        }
    }
    ?>
						</select>
					</td>
					<td>
						<?php echo __('Interfaces', 'mactrack'); ?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<?php
    if (cacti_sizeof($rows_selector)) {
        foreach ($rows_selector as $key => $value) {
            echo '<option value="'.$key.'"';
            if (get_request_var('rows') == $key) {
                echo 'selected';
            } echo '>'.$value.'</option>';
        }
    }
    ?>
						</select>
					</td>
				</tr>
			</table>
			<table class='filterTable'>
				<tr>
					<td>
						<?php echo __('Search', 'mactrack'); ?>
					</td>
					<td>
						<input type='text' id='filter' size='25' value='<?php echo get_request_var('filter'); ?>'>
					</td>
					<td>
						<input type='checkbox' id='totals' onChange='applyFilter()' <?php echo 'true' == get_request_var('totals') ? 'checked' : ''; ?>>
					</td>
					<td>
						<label for='totals'><?php echo __('Show Totals', 'mactrack'); ?></label>
					</td>
				</tr>
			</table>
			</form>
			<script type='text/javascript'>

			function applyFilter() {
				strURL  = urlPath+'plugins/mactrack/mactrack_view_interfaces.php?report=interfaces&header=false';
				strURL += '&filter=' + $('#filter').val();
				strURL += '&rows=' + $('#rows').val();
				strURL += '&site_id=' + $('#site_id').val();
				strURL += '&device_id=' + $('#device_id').val();
				strURL += '&issues=' + $('#issues').val();
				strURL += '&bwusage=' + $('#bwusage').val();
				strURL += '&device_type_id=' + $('#device_type_id').val();
				strURL += '&totals=' + $('#totals').is(':checked');
				loadPageNoHeader(strURL);
			}

			function clearFilter() {
				strURL  = urlPath+'plugins/mactrack/mactrack_view_interfaces.php?header=false&clear=true';
				loadPageNoHeader(strURL);
			}

			function exportRows() {
				strURL  = urlPath+'plugins/mactrack/mactrack_view_interfaces.php?export=true';
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

				$('.rescan').off('click').on('click', function(event) {
					event.preventDefault();

					var parts = $(this).attr('id').split('_');

					scan_device_interface(parts[1], parts[2]);
				});
			});

			</script>
		</td>
	</tr><?php
}
