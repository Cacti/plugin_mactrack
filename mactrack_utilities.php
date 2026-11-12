<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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
require('./include/auth.php');
require_once('./plugins/mactrack/lib/mactrack_functions.php');

// set default action
set_default_action();

// add more memory for import
ini_set('memory_limit', '-1');
ini_set('max_execution_time', '300');

switch (get_request_var('action')) {
	case 'mactrack_utilities_truncate_ports_table':
		mactrack_utilities_ports_clear();

		break;
	case 'mactrack_utilities_perform_db_maint':
		top_header();
		require_once('./plugins/mactrack/lib/mactrack_functions.php');

		mactrack_utilities();
		mactrack_utilities_db_maint();

		bottom_footer();

		break;
	case 'mactrack_utilities_rebuild_scan_dates':
		top_header();
		require_once('./plugins/mactrack/lib/mactrack_functions.php');

		mactrack_utilities();
		mactrack_utilities_rebuild_scan_dates();

		bottom_footer();

		break;
	case 'mactrack_partition_status':
		top_header();

		mactrack_utilities();
		mactrack_utilities_partition_status();

		bottom_footer();

		break;
	case 'mactrack_utilities_optimize_ports':
		mactrack_utilities_optimize_ports();

		break;
	case 'mactrack_utilities_convert_ports':
		mactrack_utilities_convert_ports();

		break;
	case 'mactrack_utilities_purge_scanning_funcs':
		top_header();
		require_once('./plugins/mactrack/lib/mactrack_functions.php');

		mactrack_utilities();
		mactrack_utilities_purge_scanning_funcs();

		bottom_footer();

		break;
	case 'mactrack_utilities_purge_aggregated_data':
		mactrack_utilities_purge_aggregated_data();

		break;
	case 'mactrack_utilities_recreate_aggregated_data':
		mactrack_utilities_recreate_aggregated_data();

		break;
	case 'mactrack_refresh_oui_database':
		top_header();
		require_once('./plugins/mactrack/lib/mactrack_functions.php');

		import_oui_database('web');

		bottom_footer();

		break;
	case 'mactrack_proc_status':
		// ================= input validation =================
		get_filter_request_var('refresh');
		// ====================================================

		load_current_session_value('refresh', 'sess_mt_refresh', '30');

		$refresh['seconds'] = get_request_var('refresh');
		$refresh['page']    = 'mactrack_utilities.php?action=mactrack_proc_status&header=false';
		$refresh['logout']  = 'false';
		set_page_refresh($refresh);

		top_header();

		mactrack_display_run_status();

		bottom_footer();

		break;
	default:
		top_header();

		mactrack_utilities();

		bottom_footer();

		break;
}

/* -----------------------
	Utilities Functions
   ----------------------- */

/**
 * Computes and displays the MacTrack device-scanning process status:
 * whether a scan has ever completed, and the calculated time until the
 * next scheduled collection run and database maintenance run, based on
 * the configured collection timing and base/maintenance start times.
 *
 * @return void
 *
 * @global array  $config                      Cacti global
 *                                             configuration array
 *                                             (declared but not used
 *                                             directly here).
 * @global int    $refresh_interval            Reserved/declared for
 *                                             parity with other
 *                                             functions in this file;
 *                                             not used directly here.
 * @global array  $mactrack_poller_frequencies Reserved/declared for
 *                                             parity with other
 *                                             functions in this file;
 *                                             not used directly here.
 */
function mactrack_display_run_status(): void {
	global $config, $refresh_interval, $mactrack_poller_frequencies;

	$collection_timing = read_config_option('mt_collection_timing', true);

	if ($collection_timing != 'disabled') {
		$seconds_offset = $collection_timing * 60;
		// find out if it's time to collect device information
		$base_start_time          = read_config_option('mt_base_time', true);
		$database_maint_time      = read_config_option('mt_maint_time', true);
		$last_run_time            = read_config_option('mt_last_run_time', true);
		$last_db_maint_time       = read_config_option('mt_last_db_maint_time', true);
		$previous_base_start_time = read_config_option('mt_prev_base_time', true);
		$previous_db_maint_time   = read_config_option('mt_prev_db_maint_time', true);

		// see if the user desires a new start time
		if (!empty($previous_base_start_time)) {
			if ($base_start_time != $previous_base_start_time) {
				unset($last_run_time);
			}
		}

		// see if the user desires a new db maintenance time
		/*
		if (!empty($previous_db_maint_time)) {
			if ($database_maint_time <> $previous_db_maint_time) {
				unset($last_db_maint_time);
			}
		}
		*/

		// determine the next start time
		$current_time = strtotime('now');

		if (empty($last_run_time)) {
			$collection_never_completed = true;

			if ($current_time > strtotime($base_start_time)) {
				// if timer expired within a polling interval, then poll
				if (($current_time - 300) < strtotime($base_start_time)) {
					$next_run_time = strtotime(date('Y-m-d') . ' ' . $base_start_time);
				} else {
					$next_run_time = strtotime(date('Y-m-d') . ' ' . $base_start_time) + 3600 * 24;
				}
			} else {
				$next_run_time = strtotime(date('Y-m-d') . ' ' . $base_start_time);
			}
		} else {
			$collection_never_completed = false;
			$next_run_time              = $last_run_time + $seconds_offset;
		}

		// determine the next db maintenance time
		/*
		if (empty($last_db_maint_time)) {
			if (strtotime($base_start_time) < $current_time) {
				$next_db_maint_time = strtotime(date('Y-m-d') . ' ' . $database_maint_time) + 3600*24;
			} else {
				$next_db_maint_time = strtotime(date('Y-m-d') . ' ' . $database_maint_time);
			}
		} else {
			$next_db_maint_time = $last_db_maint_time + 24*3600;
		}
		*/

		$db_maint_time = strtotime($database_maint_time);

		if ($last_db_maint_time < $db_maint_time) {
			$next_db_maint_time = $db_maint_time;
		} else {
			$next_db_maint_time = strtotime('Tomorrow ' . $database_maint_time);
		}

		$time_till_next_run      = $next_run_time - $current_time;
		$time_till_next_db_maint = $next_db_maint_time - $current_time;
	}

	html_start_box(__('Mactrack Process Status', 'mactrack'), '100%', '', '1', 'center', '');
	?>
	<script type='text/javascript' <?php print plugin_mactrack_csp_nonce(); ?>>
	function applyFilter() {
		strURL = 'mactrack_utilities.php?action=mactrack_proc_status&header=false&refresh=' + $('#refresh').val();
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#refresh').on('change', function() {
			applyFilter();
		});

		$('#refreshButton').on('click', function() {
			applyFilter();
		});
	});
	</script>
	<tr class='even'>
		<form name='form_mactrack_utilities_stats' method='post'>
		<td>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Refresh', 'mactrack'); ?>
					</td>
					<td>
						<select id='refresh'>
						<?php
						if (cacti_sizeof($refresh_interval)) {
							foreach ($refresh_interval as $key => $interval) {
								print '<option value="' . $key . '"';

								if (get_request_var('refresh') == $key) {
									print ' selected';
								} print '>' . $interval . '</option>';
							}
						}
	?>
					</td>
					<td>
						<button type='button' id='refreshButton' class='ui-button ui-corner-all ui-widget'><?php print __esc('Refresh', 'mactrack'); ?></button>
					</td>
				</tr>
			</table>
		</td>
		</form>
	</tr>
	<?php

	html_end_box(true);

	html_start_box('', '100%', '', '1', 'center', '');

	// get information on running processes
	$running_processes = db_fetch_assoc('SELECT
		mac_track_processes.process_id,
		mac_track_devices.device_name,
		mac_track_processes.device_id,
		mac_track_processes.start_date
		FROM mac_track_devices
		INNER JOIN mac_track_processes
		ON mac_track_devices.device_id = mac_track_processes.device_id
		WHERE mac_track_processes.device_id != 0');

	$resolver_running = db_fetch_cell('SELECT COUNT(*) FROM mac_track_processes WHERE device_id=0');
	$total_processes  = cacti_sizeof($running_processes);

	$run_status = db_fetch_assoc("SELECT last_rundate,
		COUNT(last_rundate) AS devices
		FROM mac_track_devices
		WHERE disabled = ''
		GROUP BY last_rundate
		ORDER BY last_rundate DESC");

	$total_devices = db_fetch_cell('SELECT count(*) FROM mac_track_devices');

	$disabled_devices = db_fetch_cell('SELECT count(*) FROM mac_track_devices');

	html_header([__('Current Process Status', 'mactrack')], 2);
	form_alternate_row();
	print '<td>' . __('The Mactrack Poller is:', 'mactrack') . '</td><td>' . ($total_processes > 0 ? __('Running', 'mactrack') : ($collection_timing == 'disabled' ? __('Disabled', 'mactrack') : __('Idle', 'mactrack'))) . '</td>';

	if ($total_processes > 0) {
		form_alternate_row();
		print '<td>' . __('Running Processes:', 'mactrack') . '</td><td>' . $total_processes . '</td>';
	}
	form_alternate_row();
	print '<td width=200>' . __('Last Time Poller Started:', 'mactrack') . '</td><td>' . read_config_option('mt_scan_date', true) . '</td>';
	form_alternate_row();
	print '<td width=200>' . __('Poller Frequency:', 'mactrack') . '</td><td>' . ($collection_timing == 'disabled' ? __('N/A', 'mactrack') : $mactrack_poller_frequencies[$collection_timing]) . '</td>';
	form_alternate_row();
	print '<td width=200>' . __('Approx. Next Runtime:', 'mactrack') . '</td><td>' . (empty($next_run_time) ? __('N/A', 'mactrack') : date('Y-m-d H:i:s', (int) $next_run_time)) . '</td>';

	html_header([__('Database Maintenance Information', 'mactrack')], 2);
	form_alternate_row();
	print '<td width=200>' . __('Last DB Maintenance Time:', 'mactrack') . '</td><td>' . (empty($last_db_maint_time) ? __('N/A', 'mactrack') : date('Y-m-d H:i:s', $last_db_maint_time)) . '</td>';
	form_alternate_row();
	print '<td width=200>' . __('Next DB Maintenance Time:', 'mactrack') . '</td><td>' . (empty($next_db_maint_time) ? __('N/A', 'mactrack') : date('Y-m-d H:i:s', $next_db_maint_time)) . '</td>';

	html_header([__('Run Time Details', 'mactrack')], 2);
	form_alternate_row();
	print '<td width=200>' . __('Last Poller Runtime:', 'mactrack') . '</td><td>' . read_config_option('stats_mactrack', true) . '</td>';
	form_alternate_row();
	print '<td width=200>' . __('Last Poller Maintenance Runtime:', 'mactrack') . '</td><td>' . read_config_option('stats_mactrack_maint', true) . '</td>';
	form_alternate_row();
	print '<td width=200>' . __('Maximum Concurrent Processes:', 'mactrack') . '</td><td> ' . read_config_option('mt_processes', true) . __('processes', 'mactrack') . '</td>';
	form_alternate_row();
	print '<td width=200>' . __('Maximum Per Device Scan Time:', 'mactrack') . '</td><td> ' . read_config_option('mt_script_runtime', true) . __('minutes', 'mactrack') . '</td>';

	html_header([__('DNS Configuration Information', 'mactrack')], 2);
	form_alternate_row();
	print '<td width=200>' . __('Reverse DNS Resolution is', 'mactrack') . '</td><td>' . (read_config_option('mt_reverse_dns', true) == 'on' ? __('Enabled', 'mactrack') : __('Disabled', 'mactrack')) . '</td>';
	form_alternate_row();
	print '<td width=200>' . __('Primary DNS Server:', 'mactrack') . '</td><td>' . read_config_option('mt_dns_primary', true) . '</td>';
	form_alternate_row();
	print '<td width=200>' . __('Secondary DNS Server:', 'mactrack') . '</td><td>' . read_config_option('mt_dns_secondary', true) . '</td>';
	form_alternate_row();
	print '<td width=200>' . __('DNS Resolution Timeout:', 'mactrack') . '</td><td> ' . read_config_option('mt_dns_timeout', true) . __('milliseconds', 'mactrack') . '</td>';
	html_end_box(true);

	if ($total_processes > 0) {
		html_start_box(__('Running Process Summary', 'mactrack'), '100%', '', '3', 'center', '');
		?>
		<td><?php print($resolver_running ? __('The DNS Resolver is Running', 'mactrack') : __('The DNS Resolver is Not Running', 'mactrack')); ?></td>
		<?php
		html_header([__('Status', 'mactrack'), __('Devices', 'mactrack'), __('Date Started', 'mactrack')], 3);

		$other_processes = 0;
		$other_date      = 0;

		$completed_processes = 0;
		$completed_date      = '';
		$running_processes   = 0;
		$running_date        = '';
		$waiting_processes   = 0;
		$waiting_date        = '';

		if (cacti_sizeof($run_status) == 1) {
			$waiting_processes   = $total_devices - $total_processes;
			$waiting_date        = $run_status[0]['last_rundate'];
			$completed_processes = 0;
			$completed_date      = '';
			$running_processes   = $total_processes;
			$running_date        = read_config_option('mt_scan_date', true);
		} else {
			$i = 0;

			foreach ($run_status as $key => $run) {
				switch ($key) {
					case 0:
						$completed_processes = $run['devices'];
						$completed_date      = $run['last_rundate'];

						break;
					case 1:
						$waiting_processes = $run['devices'] - $total_processes;
						$waiting_date      = $run['last_rundate'];
						$running_processes = $total_processes;
						$running_date      = read_config_option('mt_scan_date', true);

						break;
					default:
						$other_processes += $run['devices'];
						$other_rundate = $run['last_rundate'];
				}
			}
		}

		form_alternate_row();
		?>
		<td><?php print __('Completed', 'mactrack'); ?></td>
		<td><?php print $completed_processes; ?></td>
		<td><?php print $completed_date; ?></td>
		<?php
		form_alternate_row();
		?>
		<td><?php print __('Running', 'mactrack'); ?></td>
		<td><?php print $running_processes; ?></td>
		<td><?php print $running_date; ?></td>
		<?php
		form_alternate_row();
		?>
		<td><?php print __('Waiting', 'mactrack'); ?></td>
		<td><?php print $waiting_processes; ?></td>
		<td><?php print $waiting_date; ?></td>
		<?php
		form_alternate_row();

		if ($other_processes > 0) {
			?>
			<td><?php print __('Other', 'mactrack'); ?></td>
			<td><?php print $other_processes; ?></td>
			<td><?php print $other_date; ?></td>
			<?php
		}

		html_end_box(true);
	}
}

/**
 * Truncates all MacTrack port/MAC/IP result tables (ports, scan dates,
 * IPs, IP ranges, VLANs, aggregated ports, dot1x) and resets the
 * corresponding summary counters on every device and site record.
 * Prompts for confirmation first when the mt_maint_confirm setting is
 * enabled. Called from this script's main request-dispatch switch.
 *
 * @return void
 *
 * @global array $config Cacti global configuration array (declared but
 *                       not used directly here).
 */
function mactrack_utilities_ports_clear(): void {
	global $config;

	if ((read_config_option('mt_maint_confirm') == 'on') && (!isset_request_var('confirm'))) {
		top_header();
		form_confirm(__('Are You Sure?', 'mactrack'), __('Are you sure you want to delete all the Port to MAC to IP results from the system?', 'mactrack'), 'plugins/mactrack/mactrack_utilities.php', 'plugins/mactrack/mactrack_utilities.php?action=mactrack_utilities_truncate_ports_table');
		bottom_footer();
		exit;
	}

	if ((read_config_option('mt_maint_confirm') == '') || (isset_request_var('confirm'))) {
		$rows = db_fetch_cell('SELECT COUNT(*) FROM mac_track_ports');

		db_execute('TRUNCATE TABLE mac_track_ports');
		db_execute('TRUNCATE TABLE mac_track_scan_dates');
		db_execute('TRUNCATE TABLE mac_track_ips');
		db_execute('TRUNCATE TABLE mac_track_ip_ranges');
		db_execute('TRUNCATE TABLE mac_track_vlans');
		db_execute('TRUNCATE TABLE mac_track_aggregated_ports');
		db_execute('TRUNCATE TABLE mac_track_dot1x');
		db_execute('UPDATE mac_track_sites SET total_macs=0, total_ips=0, total_user_ports=0, total_oper_ports=0, total_trunk_ports=0');
		db_execute('UPDATE mac_track_devices SET ips_total=0, ports_total=0, ports_active=0, ports_trunk=0, macs_active=0, vlans_total=0, last_runduration=0.0000');

		$device_rows = db_fetch_assoc('SELECT device_id FROM mac_track_devices');

		if (cacti_sizeof($device_rows)) {
			foreach ($device_rows as $device_row) {
				db_execute_prepared('UPDATE mac_track_devices SET ips_total=0 WHERE device_id = ?',    [$device_row['device_id']]);
				db_execute_prepared('UPDATE mac_track_devices SET ports_total=0 WHERE device_id = ?',  [$device_row['device_id']]);
				db_execute_prepared('UPDATE mac_track_devices SET ports_active=0 WHERE device_id = ?', [$device_row['device_id']]);
				db_execute_prepared('UPDATE mac_track_devices SET ports_trunk=0 WHERE device_id = ?',  [$device_row['device_id']]);
				db_execute_prepared('UPDATE mac_track_devices SET macs_active=0 WHERE device_id = ?',  [$device_row['device_id']]);
				db_execute_prepared('UPDATE mac_track_devices SET vlans_total=0 WHERE device_id = ?',  [$device_row['device_id']]);
				db_execute_prepared('UPDATE mac_track_devices SET last_runduration=0.00000 WHERE device_id = ?', [$device_row['device_id']]);
			}
		}

		$site_rows = db_fetch_assoc('SELECT site_id FROM mac_track_sites');

		if (cacti_sizeof($site_rows)) {
			foreach ($site_rows as $site_row) {
				db_execute_prepared('UPDATE mac_track_sites SET total_devices=0 WHERE site_id = ?',     [$site_row['site_id']]);
				db_execute_prepared('UPDATE mac_track_sites SET total_macs=0 WHERE site_id = ?',        [$site_row['site_id']]);
				db_execute_prepared('UPDATE mac_track_sites SET total_ips=0 WHERE site_id = ?',         [$site_row['site_id']]);
				db_execute_prepared('UPDATE mac_track_sites SET total_user_ports=0 WHERE site_id = ?',  [$site_row['site_id']]);
				db_execute_prepared('UPDATE mac_track_sites SET total_oper_ports=0 WHERE site_id = ?',  [$site_row['site_id']]);
				db_execute_prepared('UPDATE mac_track_sites SET total_trunk_ports=0 WHERE site_id = ?', [$site_row['site_id']]);
			}
		}

		top_header();
		mactrack_utilities();

		html_start_box(__('Mactrack Database Results', 'mactrack'), '100%', '', '3', 'center', '');
		print '<td>' . __('The following number of records have been removed from the database: %s', $rows, 'mactrack') . '</td>';
		html_end_box();
	}
}

/**
 * Truncates the mac_track_aggregated_ports table. Prompts for
 * confirmation first when the mt_maint_confirm setting is enabled.
 * Called from this script's main request-dispatch switch.
 *
 * @return void
 *
 * @global array $config Cacti global configuration array (declared but
 *                       not used directly here).
 */
function mactrack_utilities_purge_aggregated_data(): void {
	global $config;

	if ((read_config_option('mt_maint_confirm') == 'on') && (!isset_request_var('confirm'))) {
		top_header();
		form_confirm(__('Are You Sure?', 'mactrack'), __('Are you sure you want to delete all the Aggregated Port to MAC to IP results from the system?', 'mactrack'), 'plugins/mactrack/mactrack_utilities.php', 'plugins/mactrack/mactrack_utilities.php?action=mactrack_utilities_purge_aggregated_data');
		bottom_footer();
		exit;
	}

	if ((read_config_option('mt_maint_confirm') == '') || (isset_request_var('confirm'))) {
		$rows = db_fetch_cell('SELECT COUNT(*) FROM mac_track_aggregated_ports');
		db_execute('TRUNCATE TABLE mac_track_aggregated_ports');

		top_header();
		mactrack_utilities();

		html_start_box(__('Mactrack Database Results', 'mactrack'), '100%', '', '3', 'center', '');
		print '<td>' . __('The following number of records have been removed from the aggergated table: %s', $rows, 'mactrack') . '</td>';
		html_end_box();
	}
}

/**
 * Truncates and rebuilds the mac_track_aggregated_ports table by
 * re-aggregating (grouping and counting) the raw mac_track_ports data.
 * Prompts for confirmation first when the mt_maint_confirm setting is
 * enabled. Called from this script's main request-dispatch switch.
 *
 * @return void
 *
 * @global array $config Cacti global configuration array (declared but
 *                       not used directly here).
 */
function mactrack_utilities_recreate_aggregated_data(): void {
	global $config;

	if ((read_config_option('mt_maint_confirm') == 'on') && (!isset_request_var('confirm'))) {
		top_header();
		form_confirm(__('Are You Sure?', 'mactrack'), __('Are you sure you want to delete and recreate all the Aggregated Port to MAC to IP results from the system?', 'mactrack'), 'plugins/mactrack/mactrack_utilities.php', 'plugins/mactrack/mactrack_utilities.php?action=mactrack_utilities_recreate_aggregated_data');
		bottom_footer();
		exit;
	}

	if ((read_config_option('mt_maint_confirm') == '') || (isset_request_var('confirm'))) {
		$old_rows = db_fetch_cell('SELECT COUNT(*) FROM mac_track_aggregated_ports');
		db_execute('TRUNCATE TABLE mac_track_aggregated_ports');

		db_execute('INSERT INTO mac_track_aggregated_ports
			(site_id, device_id, hostname, device_name,
			vlan_id, vlan_name, mac_address, vendor_mac, ip_address, dns_hostname,
			port_number, port_name, date_last, first_scan_date, count_rec, authorized)
			SELECT site_id, device_id, hostname, device_name,
			vlan_id, vlan_name, mac_address, vendor_mac, ip_address, dns_hostname,
			port_number, port_name, max(scan_date), min(scan_date), count(*), authorized
			FROM mac_track_ports
			GROUP BY site_id,device_id, mac_address, port_number, ip_address, vlan_id, authorized');

		$new_rows = db_fetch_cell('SELECT COUNT(*) FROM mac_track_aggregated_ports');

		top_header();
		mactrack_utilities();

		html_start_box('Mactrack Database Results', '100%', '', '3', 'center', '');
		print '<td>' . __('The following number of records have been removed from the aggergated table: %s.  And %s records will be added.', $old_rows, $new_rows, 'mactrack') . '</td>';
		html_end_box();
	}
}

/**
 * Runs the standard MacTrack database maintenance routine
 * (perform_mactrack_db_maint()) and displays the number of rows
 * removed from mac_track_ports as a result. Called from this script's
 * main request-dispatch switch.
 *
 * @return void
 */
function mactrack_utilities_db_maint(): void {
	$begin_rows = db_fetch_cell('SELECT COUNT(*) FROM mac_track_ports');
	perform_mactrack_db_maint();
	$end_rows = db_fetch_cell('SELECT COUNT(*) FROM mac_track_ports');

	html_start_box('Mactrack Database Results', '100%', '', '3', 'center', '');
	print '<td>' . __('The following number of records have been removed from the database: %s', $begin_rows - $end_rows, 'mactrack') . '</td>';
	html_end_box();
}

/**
 * Rebuilds the scan date list from the stored port results
 * (mactrack_scan_dates_rebuild()) and displays how many dates it holds.
 * Called from this script's main request-dispatch switch.
 *
 * @return void
 */
function mactrack_utilities_rebuild_scan_dates(): void {
	$dates = mactrack_scan_dates_rebuild();

	html_start_box(__('Mactrack Scan Date Rebuild Results', 'mactrack'), '100%', '', '3', 'center', '');
	print '<td>' . __('Scan dates rebuilt: %d', $dates, 'mactrack') . '</td>';
	html_end_box();
}

/**
 * Shows how mac_track_ports is partitioned: the layout, the partition
 * count against the server's limit, the effective partition size and why,
 * a pending exchange, and the outcome of the last maintenance run.
 *
 * @return void
 */
function mactrack_utilities_partition_status(): void {
	$r     = mactrack_partition_report();
	$none  = __('None', 'mactrack');
	$sizes = [
		'scan' => __('One per Scan', 'mactrack'),
		'hour' => __('Hourly', 'mactrack'),
		'day'  => __('Daily', 'mactrack'),
	];
	$layouts = [
		'none'    => __('Not partitioned', 'mactrack'),
		'legacy'  => __('Daily partitions from an earlier MacTrack release. Conversion is needed.', 'mactrack'),
		'range'   => __('Partitioned by scan date', 'mactrack'),
		'invalid' => __('Unrecognized partitions. MacTrack makes no partition changes.', 'mactrack'),
	];
	$i   = 0;
	$row = function (string $label, string $value) use (&$i): void {
		form_alternate_row('line' . $i++);
		print '<td>' . html_escape($label) . '</td><td>' . html_escape($value) . '</td>';
		form_end_row();
	};

	html_start_box(__('Port Table Partitions', 'mactrack'), '100%', '', '3', 'center', '');

	$row(__('Layout', 'mactrack'), trim($layouts[$r['layout']] . ' ' . $r['reason']));

	if ($r['layout'] !== 'none') {
		$row(__('Partitions', 'mactrack'), __('%d of at most %d', $r['partitions'], $r['cap'], 'mactrack'));
		$row(__('Oldest Partition', 'mactrack'), $r['oldest'] ?? $none);
		$row(__('Newest Partition', 'mactrack'), $r['newest'] ?? $none);

		if ($r['covered_until'] !== null) {
			$row(__('Partitions Cover Scans Before', 'mactrack'), date('Y-m-d H:i:s', $r['covered_until']));
		}
	}

	$size = $sizes[$r['granularity']];

	if ($r['fell_back']) {
		$size = __('%s, coarser than the setting because %d partitions would exceed the limit', $size, $r['projected'], 'mactrack');
	}

	$row(__('Port Partition Size', 'mactrack'), $size);
	$row(__('Pending Exchange', 'mactrack'), $r['pending'] !== '' ? $r['pending'] : $none);
	$row(__('Load Path', 'mactrack'), __('PHP batches', 'mactrack'));
	$row(__('Last Scan Note', 'mactrack'), $r['last_scan_reason'] !== '' ? $r['last_scan_reason'] : $none);

	if ($r['converting'] !== null) {
		$row(__('Conversion', 'mactrack'), __('Running since %s', date('Y-m-d H:i:s', $r['converting']), 'mactrack'));
	} elseif ($r['conversion'] !== null) {
		$c = $r['conversion'];

		$row(__('Last Conversion', 'mactrack'), $c['ok']
			? trim(__('%s: %d rows into %d partitions', date('Y-m-d H:i:s', (int) $c['time']), $c['rows'], $c['partitions'], 'mactrack') . ' ' . $c['reason'])
			: __('%s: failed. %s', date('Y-m-d H:i:s', (int) $c['time']), $c['reason'], 'mactrack'));
	}

	$m = $r['maintenance'];

	if ($m === null) {
		$row(__('Last Maintenance', 'mactrack'), $none);
	} else {
		$row(__('Last Maintenance', 'mactrack'), trim(__('%s: %d partitions dropped, %d created', date('Y-m-d H:i:s', (int) $m['time']), count($m['dropped']), count($m['created']), 'mactrack') . ' ' . $m['reason']));
	}

	html_end_box();
}

/**
 * Runs OPTIMIZE TABLE on mac_track_ports, which routine maintenance no
 * longer does because it rebuilds the whole table.
 *
 * Cacti checks the CSRF token on POST requests only, so a GET just shows a
 * form that posts back here; nothing a link or a GET parameter carries can
 * start the rebuild.
 *
 * @return void
 */
function mactrack_utilities_optimize_ports(): void {
	if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
		top_header();

		html_start_box(__('Are You Sure?', 'mactrack'), '60%', '', '3', 'center', '');
		print '<tr><td class="textArea">' . html_escape(__('OPTIMIZE rebuilds the whole port table and can take a long time on a large table. Continue?', 'mactrack')) . '</td></tr>';
		print '<tr><td class="right"><form method="post" action="mactrack_utilities.php">'
			. '<input type="hidden" name="action" value="' . html_escape('mactrack_utilities_optimize_ports') . '">'
			. '<a class="ui-button ui-corner-all ui-widget" href="mactrack_utilities.php">' . html_escape(__('Cancel', 'mactrack')) . '</a> '
			. '<input type="submit" class="ui-button ui-corner-all ui-widget" value="' . html_escape(__('Optimize Port Table', 'mactrack')) . '">'
			. '</form></td></tr>';
		html_end_box();

		bottom_footer();

		return;
	}

	$result = db_fetch_assoc_prepared('OPTIMIZE TABLE mac_track_ports');

	top_header();
	mactrack_utilities();

	html_start_box(__('Mactrack Database Results', 'mactrack'), '100%', '', '3', 'center', '');

	foreach ((array) $result as $line) {
		form_alternate_row();
		print '<td>' . html_escape(($line['Msg_type'] ?? '') . ': ' . ($line['Msg_text'] ?? '')) . '</td>';
		form_end_row();
	}

	html_end_box();
	bottom_footer();
}

/**
 * Starts the conversion of mac_track_ports to the scan-date layout in the
 * background (mactrack_convert.php), because it can take hours.
 *
 * Like the OPTIMIZE action, it starts only on a POST, where Cacti checks
 * the CSRF token; a GET shows a form that posts back here.
 *
 * @return void
 *
 * @global array $config Cacti global configuration array; used to build
 *                       the conversion script's path.
 */
function mactrack_utilities_convert_ports(): void {
	global $config;

	$layout = mactrack_partition_state()['layout']['state'];
	$busy   = (string) read_config_option('mt_partition_converting', true) !== '';

	if ($busy) {
		$message = __('A conversion is already running. The partition status page shows when it has finished.', 'mactrack');
	} elseif ($layout === 'range') {
		$message = __('The port table already uses the scan-date layout.', 'mactrack');
	} elseif ($layout === 'invalid') {
		$message = __('The port table has partitions MacTrack does not recognize, so it cannot be converted. The partition status page shows why.', 'mactrack');
	} elseif (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
		top_header();

		html_start_box(__('Are You Sure?', 'mactrack'), '60%', '', '3', 'center', '');
		print '<tr><td class="textArea">' . html_escape(__('The conversion copies the whole port table into a new partitioned table and can take hours on a large table. Scans and database maintenance wait until it finishes. The database needs free space for a second copy of the table. Continue?', 'mactrack')) . '</td></tr>';
		print '<tr><td class="right"><form method="post" action="mactrack_utilities.php">'
			. '<input type="hidden" name="action" value="' . html_escape('mactrack_utilities_convert_ports') . '">'
			. '<a class="ui-button ui-corner-all ui-widget" href="mactrack_utilities.php">' . html_escape(__('Cancel', 'mactrack')) . '</a> '
			. '<input type="submit" class="ui-button ui-corner-all ui-widget" value="' . html_escape(__('Convert Port Table', 'mactrack')) . '">'
			. '</form></td></tr>';
		html_end_box();

		bottom_footer();

		return;
	} else {
		// The same command the poller hook builds: the configured PHP binary
		// and a fixed script path, with nothing taken from the request.
		exec_background(read_config_option('path_php_binary'), '-q ' . cacti_escapeshellarg($config['base_path'] . '/plugins/mactrack/mactrack_convert.php'));

		$message = __('The conversion has started in the background. The partition status page shows its progress and result.', 'mactrack');
	}

	top_header();
	mactrack_utilities();

	html_start_box(__('Mactrack Database Results', 'mactrack'), '100%', '', '3', 'center', '');
	form_alternate_row();
	print '<td>' . html_escape($message) . '</td>';
	form_end_row();
	html_end_box();

	bottom_footer();
}

/**
 * Purges and rebuilds the cached list of registered vendor scanning
 * functions (mactrack_rebuild_scanning_funcs()). Called from this
 * script's main request-dispatch switch.
 *
 * @return void
 *
 * @global array $config Cacti global configuration array (declared but
 *                       not used directly here).
 */
function mactrack_utilities_purge_scanning_funcs(): void {
	global $config;

	mactrack_rebuild_scanning_funcs();

	html_start_box('Mactrack Scanning Function Refresh Results', '100%', '', '3', 'center', '');
	print '<td>' . __('Mactrack scanning functions have been purged.  They will be recreated once you either edit a device or device type.', 'mactrack') . '</td>';
	html_end_box();
}

/**
 * Renders the main MacTrack System Utilities page: displays the
 * process status information box (with a refresh-interval selector)
 * and the database/scanning-function maintenance action links. Called
 * from this script's main request-dispatch switch as the default view.
 *
 * @return void
 */
function mactrack_utilities(): void {
	html_start_box(__('Cacti Mactrack System Utilities', 'mactrack'), '100%', '', '3', 'center', '');

	html_header([__('Process Status Information', 'mactrack')], 2);

	?>
	<colgroup span='3'>
		<col class='nowrap' style='vertical-align:top;width:20%;'>
		<col style='vertical-align:top;width:80%;'>
	</colgroup>
	<tr class='even'>
		<td class='textArea'>
			<a class='hyperLink' href='mactrack_utilities.php?action=mactrack_proc_status'><?php print __('View Mactrack Process Status', 'mactrack'); ?></a>
		</td>
		<td class='textArea'>
			<?php print __('This option will let you show and set process information associated with the Mactrack polling process.', 'mactrack'); ?>
		</td>
	</tr>

	<?php html_header([__('Database Administration', 'mactrack')], 2); ?>

	<tr class='odd'>
		<td class='textArea'>
			<a class='hyperLink' href='mactrack_utilities.php?action=mactrack_utilities_perform_db_maint'><?php print __('Perform Database Maintenance', 'mactrack'); ?></a>
		</td>
		<td class='textArea'>
			<?php print __('Deletes expired Port to MAC to IP associations from the database.  Only records that have expired, based upon your criteria are removed.', 'mactrack'); ?>
		</td>
	</tr>

	<tr class='even'>
		<td class='textArea'>
			<a class='hyperLink' href='mactrack_utilities.php?action=mactrack_refresh_oui_database'><?php print __('Refresh IEEE Vendor MAC/OUI Database', 'mactrack'); ?></a>
		</td>
		<td class='textArea'>
			<?php print __('This function will download and install the latest OIU database from the IEEE Website.  Each Network Interface Card (NIC) has a MAC Address.  The MAC Address can be broken into two parts.  The first part of the MAC Address contains the Vendor MAC.  The Vendor MAC identifies who manufactured the part.  This will be helpful in spot checking for rogue devices on your network.', 'mactrack'); ?>
		</td>
	</tr>

	<tr class='odd'>
		<td class='textArea'>
			<a class='hyperLink' href='mactrack_utilities.php?action=mactrack_utilities_purge_scanning_funcs'><?php print __('Refresh Scanning Functions', 'mactrack'); ?></a>
		</td>
		<td class='textArea'>
			<?php print __('Deletes old and potentially stale Mactrack scanning functions from the drop-down you receive when you edit a device type.', 'mactrack'); ?>
		</td>
	</tr>

	<tr class='even'>
		<td class='textArea'>
			<a class='hyperLink' href='mactrack_utilities.php?action=mactrack_utilities_truncate_ports_table'><?php print __('Remove All Scan Results', 'mactrack'); ?></a>
		</td>
		<td class='textArea'>
			<?php print __('Deletes <strong>ALL</strong> Port to MAC to IP associations from the database all IP Addresses, IP Ranges, and VLANS.  This utility is good when you want to start over.  <strong>DANGER: All prior data is deleted.</strong>', 'mactrack'); ?>
		</td>
	</tr>

	<tr class='odd'>
		<td class='textArea'>
			<a class='hyperLink' href='mactrack_utilities.php?action=mactrack_utilities_rebuild_scan_dates'><?php print __('Rebuild Scan Dates', 'mactrack'); ?></a>
		</td>
		<td class='textArea'>
			<?php print __('Rebuilds the list of scan dates from the stored Port to MAC to IP results, reading each partition once. Scans and maintenance keep the list current, so this is only needed after results were removed outside of maintenance, for example by deleting devices.', 'mactrack'); ?>
		</td>
	</tr>

	<tr class='even'>
		<td class='textArea'>
			<a class='hyperLink' href='mactrack_utilities.php?action=mactrack_partition_status'><?php print __('View Partition Status', 'mactrack'); ?></a>
		</td>
		<td class='textArea'>
			<?php print __('Shows how the Port to MAC to IP results are partitioned, the partition size in use and why, and what the last database maintenance did.', 'mactrack'); ?>
		</td>
	</tr>

	<tr class='odd'>
		<td class='textArea'>
			<a class='hyperLink' href='mactrack_utilities.php?action=mactrack_utilities_optimize_ports'><?php print __('Optimize Port Table', 'mactrack'); ?></a>
		</td>
		<td class='textArea'>
			<?php print __('Rebuilds the Port to MAC to IP results table to reclaim the space of deleted rows. Database maintenance no longer does this, because the rebuild can take a long time on a large table. Partitioned tables rarely need it.', 'mactrack'); ?>
		</td>
	</tr>

	<tr class='even'>
		<td class='textArea'>
			<a class='hyperLink' href='mactrack_utilities.php?action=mactrack_utilities_convert_ports'><?php print __('Convert Port Table', 'mactrack'); ?></a>
		</td>
		<td class='textArea'>
			<?php print __('Converts the Port to MAC to IP results table to partitions by scan date, so maintenance drops old scans instead of deleting rows. It copies the table, which can take hours; scans wait meanwhile.', 'mactrack'); ?>
		</td>
	</tr>

	<?php html_header([__('Aggregated Table Administration', 'mactrack')], 2); ?>

	<tr class='even'>
		<td class='textArea'>
			<a class='hyperLink' href='mactrack_utilities.php?action=mactrack_utilities_purge_aggregated_data'><?php print __('Remove All Aggregated Results', 'mactrack'); ?></a>
		</td>
		<td class='textArea'>
			<?php print __('Deletes ALL <strong>Aggregated</strong> (Not Scan Results) Port to MAC to IP associations from the database.  Data will again be collected on the basis of <strong>only new</strong> scanned data in the next mactrack poller run.', 'mactrack'); ?>
		</td>
	</tr>

	<tr class='odd'>
		<td class='textArea'>
			<a class='hyperLink' href='mactrack_utilities.php?action=mactrack_utilities_recreate_aggregated_data'><?php print __('Perform Aggregate Table Rebuild', 'mactrack'); ?></a>
		</td>
		<td class='textArea'>
			<?php print __('Deletes ALL <strong>Aggregated</strong> (Not Scan Results) Port to MAC to IP associations from the database and their re-creation based on <strong>All scanned data</strong> now.', 'mactrack'); ?>
		</td>
	</tr>
	<?php

	html_end_box();
}
