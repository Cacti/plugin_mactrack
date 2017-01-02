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

/* set default action */
set_default_action();

/* add more memory for import */
ini_set('memory_limit', '256M');
ini_set('max_execution_time', '300');

switch (get_request_var('action')) {
	case 'mactrack_utilities_truncate_ports_table':
		mactrack_utilities_ports_clear();

		break;
	case 'mactrack_utilities_perform_db_maint':
		top_header();
		include_once('./plugins/mactrack/lib/mactrack_functions.php');

		mactrack_utilities();
		mactrack_utilities_db_maint();

		bottom_footer();
		break;
	case 'mactrack_utilities_purge_scanning_funcs':
		top_header();
		include_once('./plugins/mactrack/lib/mactrack_functions.php');

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
		include_once('./plugins/mactrack/lib/mactrack_functions.php');

		import_oui_database('web');

		bottom_footer();
		break;
	case 'mactrack_proc_status':
		/* ================= input validation ================= */
		get_filter_request_var('refresh');
		/* ==================================================== */

		load_current_session_value('refresh', 'sess_mactrack_utilities_refresh', '30');

		$refresh['seconds'] = get_request_var('refresh');
		$refresh['page'] = 'mactrack_utilities.php?action=mactrack_proc_status';

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

function mactrack_display_run_status() {
	global $config, $refresh_interval, $mactrack_poller_frequencies;

	$seconds_offset = read_config_option('mt_collection_timing', TRUE);
	if ($seconds_offset <> 'disabled') {
		$seconds_offset = $seconds_offset * 60;
		/* find out if it's time to collect device information */
		$base_start_time = read_config_option('mt_base_time', TRUE);
		$database_maint_time = read_config_option('mt_maint_time', TRUE);
		$last_run_time = read_config_option('mt_last_run_time', TRUE);
		$last_db_maint_time = read_config_option('mt_last_db_maint_time', TRUE);
		$previous_base_start_time = read_config_option('mt_prev_base_time', TRUE);
		$previous_db_maint_time = read_config_option('mt_prev_db_maint_time', TRUE);

		/* see if the user desires a new start time */
		if (!empty($previous_base_start_time)) {
			if ($base_start_time <> $previous_base_start_time) {
				unset($last_run_time);
			}
		}

		/* see if the user desires a new db maintenance time */
		if (!empty($previous_db_maint_time)) {
			if ($database_maint_time <> $previous_db_maint_time) {
				unset($last_db_maint_time);
			}
		}

		/* determine the next start time */
		$current_time = strtotime('now');
		if (empty($last_run_time)) {
			$collection_never_completed = TRUE;
			if ($current_time > strtotime($base_start_time)) {
				/* if timer expired within a polling interval, then poll */
				if (($current_time - 300) < strtotime($base_start_time)) {
					$next_run_time = strtotime(date('Y-m-d') . ' ' . $base_start_time);
				}else{
					$next_run_time = strtotime(date('Y-m-d') . ' ' . $base_start_time) + 3600*24;
				}
			}else{
				$next_run_time = strtotime(date('Y-m-d') . ' ' . $base_start_time);
			}
		}else{
			$collection_never_completed = FALSE;
			$next_run_time = $last_run_time + $seconds_offset;
		}

		if (empty($last_db_maint_time)) {
			if (strtotime($base_start_time) < $current_time) {
				$next_db_maint_time = strtotime(date('Y-m-d') . ' ' . $database_maint_time) + 3600*24;
			}else{
				$next_db_maint_time = strtotime(date('Y-m-d') . ' ' . $database_maint_time);
			}
		}else{
			$next_db_maint_time = $last_db_maint_time + 24*3600;
		}

		$time_till_next_run = $next_run_time - $current_time;
		$time_till_next_db_maint = $next_db_maint_time - $current_time;
	}

	html_start_box(__('MacTrack Process Status'), '100%', '', '1', 'center', '');
	?>
	<script type='text/javascript'>
	function applyFilter() {
		strURL = 'mactrack_utilities.php?action=mactrack_proc_status&refresh='+$('#refresh').val();
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#refresh').click(function() {
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
						<?php print __('Refresh');?>
					</td>
					<td>
						<select id='refresh' onChange='applyFilter()'>
						<?php
						foreach ($refresh_interval as $key => $interval) {
							print '<option value="' . $key . '"'; if (get_request_var('refresh') == $key) { print ' selected'; } print '>' . $interval . '</option>';
						}
						?>
					</td>
					<td>
						<input type='button' value='<?php print __('Refresh');?>' id='refresh'>
					</td>
				</tr>
			</table>
		</td>
		</form>
	</tr>
	<?php

	html_end_box(TRUE);

	html_start_box('', '100%', '', '1', 'center', '');

	/* get information on running processes */
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
	$total_processes = sizeof($running_processes);

	$run_status = db_fetch_assoc("SELECT last_rundate,
		COUNT(last_rundate) AS devices
		FROM mac_track_devices
		WHERE disabled = ''
		GROUP BY last_rundate
		ORDER BY last_rundate DESC");

	$total_devices = db_fetch_cell('SELECT count(*) FROM mac_track_devices');

	$disabled_devices = db_fetch_cell('SELECT count(*) FROM mac_track_devices');

	html_header(array(__('Current Process Status')), 2);
	form_alternate_row();
	print '<td>' . __('The MacTrack Poller is:') . '</td><td>' . ($total_processes > 0 ? __('RUNNING') : ($seconds_offset == 'disabled' ? __('DISABLED') : __('IDLE'))) . '</td>';
	if ($total_processes > 0) {
		form_alternate_row();
		print '<td>' . __('Running Processes:') . '</td><td>' . $total_processes . '</td>';
	}
	form_alternate_row();
	print '<td width=200>' . __('Last Time Poller Started:') . '</td><td>' . read_config_option('mt_scan_date', TRUE) . '</td>';
	form_alternate_row();
	print '<td width=200>' . __('Poller Frequency:') . '</td><td>' . ($seconds_offset == 'disabled' ? __('N/A') : $mactrack_poller_frequencies[$seconds_offset/60]) . '</td>';
	form_alternate_row();
	print '<td width=200>' . __('Approx. Next Runtime:') . '</td><td>' . (empty($next_run_time) ? __('N/A') : date('Y-m-d G:i:s', $next_run_time)) . '</td>';
	form_alternate_row();
	print '<td width=200>' . __('Approx. Next DB Maintenance:') . '</td><td>' . (empty($next_db_maint_time) ? __('N/A') : date('Y-m-d G:i:s', $next_db_maint_time)) . '</td>';

	html_header(array(__('Run Time Details')), 2);
	form_alternate_row();
	print '<td width=200>' . __('Last Poller Runtime:') . '</td><td>' . read_config_option('stats_mactrack', TRUE) . '</td>';
	form_alternate_row();
	print '<td width=200>' . __('Last Poller Maintenence Runtime:') . '</td><td>' . read_config_option('stats_mactrack_maint', TRUE) . '</td>';
	form_alternate_row();
	print '<td width=200>' . __('Maximum Concurrent Processes:') . '</td><td> ' . read_config_option('mt_processes', TRUE) . __('processes') . '</td>';
	form_alternate_row();
	print '<td width=200>' . __('Maximum Per Device Scan Time:') . '</td><td> ' . read_config_option('mt_script_runtime', TRUE) . __('minutes') . '</td>';

	html_header(array(__('DNS Configuration Information')), 2);
	form_alternate_row();
	print '<td width=200>' . __('Reverse DNS Resolution is') . '</td><td>' . (read_config_option('mt_reverse_dns', TRUE) == 'on' ? __('ENABLED') : __('DISABLED')) . '</td>';
	form_alternate_row();
	print '<td width=200>' . __('Primary DNS Server:') . '</td><td>' . read_config_option('mt_dns_primary', TRUE) . '</td>';
	form_alternate_row();
	print '<td width=200>' . __('Secondary DNS Server:') . '</td><td>' . read_config_option('mt_dns_secondary', TRUE) . '</td>';
	form_alternate_row();
	print '<td width=200>' . __('DNS Resoution Timeout:') . '</td><td> ' . read_config_option('mt_dns_timeout', TRUE) . __('milliseconds') . '</td>';
	html_end_box(TRUE);

	if ($total_processes > 0) {
		html_start_box(__('Running Process Summary'), '100%', '', '3', 'center', '');
		?>
		<td><?php print ($resolver_running ? __('The DNS Resolver is Running') : __('The DNS Resolver is Not Running'));?></td>
		<?php
		html_header(array(__('Status'), __('Devices'), __('Date Started')), 3);

		$other_processes = 0;
		$other_date = 0;
		if (sizeof($run_status) == 1) {
			$waiting_processes = $total_devices - $total_processes;
			$waiting_date = $run_status[0]['last_rundate'];
			$completed_processes = 0;
			$completed_date = '';
			$running_processes = $total_processes;
			$running_date = read_config_option('mt_scan_date', TRUE);
		}else{
			$i = 0;
			foreach($run_status as $key => $run) {
			switch ($key) {
			case 0:
				$completed_processes = $run['devices'];
				$completed_date = $run['last_rundate'];
				break;
			case 1:
				$waiting_processes = $run['devices'] - $total_processes;
				$waiting_date = $run['last_rundate'];
				$running_processes = $total_processes;
				$running_date = read_config_option('mt_scan_date', TRUE);
				break;
			default;
				$other_processes += $run['devices'];
				$other_rundate = $run['last_rundate'];
			}
			}
		}

		form_alternate_row();
		?>
		<td><?php print __('Completed');?></td>
		<td><?php print $completed_processes;?></td>
		<td><?php print $completed_date;?></td>
		<?php
		form_alternate_row();
		?>
		<td><?php print __('Running');?></td>
		<td><?php print $running_processes;?></td>
		<td><?php print $running_date;?></td>
		<?php
		form_alternate_row();
		?>
		<td><?php print __('Waiting');?></td>
		<td><?php print $waiting_processes;?></td>
		<td><?php print $waiting_date;?></td>
		<?php
		form_alternate_row();
		if ($other_processes > 0) {
			?>
			<td><?php print __('Other');?></td>
			<td><?php print $other_processes;?></td>
			<td><?php print $other_date;?></td>
			<?php
		}

		html_end_box(TRUE);
	}

}

function mactrack_utilities_ports_clear() {
	global $config;

	if ((read_config_option('remove_verification') == 'on') && (!isset_request_var('confirm'))) {
		top_header();
		form_confirm(__('Are You Sure?'), __('Are you sure you want to delete all the Port to MAC to IP results from the system?'), 'mactrack_utilities.php', 'mactrack_utilities.php?action=mactrack_utilities_truncate_ports_table');
		bottom_footer();
		exit;
	}

	if ((read_config_option('remove_verification') == '') || (isset_request_var('confirm'))) {
		$rows = db_fetch_cell('SELECT COUNT(*) FROM mac_track_ports');

		db_execute('TRUNCATE TABLE mac_track_ports');
		db_execute('TRUNCATE TABLE mac_track_scan_dates');
		db_execute('TRUNCATE TABLE mac_track_ips');
		db_execute('TRUNCATE TABLE mac_track_ip_ranges');
		db_execute('TRUNCATE TABLE mac_track_vlans');
		db_execute('TRUNCATE TABLE mac_track_aggregated_ports');
		db_execute('UPDATE mac_track_sites SET total_macs=0, total_ips=0, total_user_ports=0, total_oper_ports=0, total_trunk_ports=0');
		db_execute('UPDATE mac_track_devices SET ips_total=0, ports_total=0, ports_active=0, ports_trunk=0, macs_active=0, vlans_total=0, last_runduration=0.0000');

		$device_rows = db_fetch_assoc('SELECT device_id FROM mac_track_devices');
		if (sizeof($device_rows)) {
			foreach ($device_rows as $device_row) {
				db_execute_prepared('UPDATE mac_track_devices SET ips_total=0 WHERE device_id = ?',    array($device_row['device_id']));
				db_execute_prepared('UPDATE mac_track_devices SET ports_total=0 WHERE device_id = ?',  array($device_row['device_id']));
				db_execute_prepared('UPDATE mac_track_devices SET ports_active=0 WHERE device_id = ?', array($device_row['device_id']));
				db_execute_prepared('UPDATE mac_track_devices SET ports_trunk=0 WHERE device_id = ?',  array($device_row['device_id']));
				db_execute_prepared('UPDATE mac_track_devices SET macs_active=0 WHERE device_id = ?',  array($device_row['device_id']));
				db_execute_prepared('UPDATE mac_track_devices SET vlans_total=0 WHERE device_id = ?',  array($device_row['device_id']));
				db_execute_prepared('UPDATE mac_track_devices SET last_runduration=0.00000 WHERE device_id = ?', array($device_row['device_id']));
			}
		}

		$site_rows = db_fetch_assoc('SELECT site_id FROM mac_track_sites');

		if (sizeof($site_rows)) {
		foreach ($site_rows as $site_row) {
			db_execute('UPDATE mac_track_sites SET total_devices=0 WHERE site_id = ?',     array($site_row['site_id']));
			db_execute('UPDATE mac_track_sites SET total_macs=0 WHERE site_id = ?',        array($site_row['site_id']));
			db_execute('UPDATE mac_track_sites SET total_ips=0 WHERE site_id = ?',         array($site_row['site_id']));
			db_execute('UPDATE mac_track_sites SET total_user_ports=0 WHERE site_id = ?',  array($site_row['site_id']));
			db_execute('UPDATE mac_track_sites SET total_oper_ports=0 WHERE site_id = ?',  array($site_row['site_id']));
			db_execute('UPDATE mac_track_sites SET total_trunk_ports=0 WHERE site_id = ?', array($site_row['site_id']));
		}
		}

		top_header();
		mactrack_utilities();
		html_start_box(__('Device Tracking Database Results'), '100%', '', '3', 'center', '');
		?>
		<td>
			The following number of records have been removed from the database: <?php print $rows;?>
		</td>
		<?php
		html_end_box();
	}
}

function mactrack_utilities_purge_aggregated_data() {
	global $config;

	if ((read_config_option('remove_verification') == 'on') && (!isset_request_var('confirm'))) {
		top_header();
		form_confirm(__('Are You Sure?'), __('Are you sure you want to delete all the Aggregated Port to MAC to IP results from the system?'), 'mactrack_utilities.php', 'mactrack_utilities.php?action=mactrack_utilities_purge_aggregated_data');
		bottom_footer();
		exit;
	}

	if ((read_config_option('remove_verification') == '') || (isset_request_var('confirm'))) {
		$rows = db_fetch_cell('SELECT COUNT(*) FROM mac_track_aggregated_ports');
		db_execute('TRUNCATE TABLE mac_track_aggregated_ports');

		top_header();
		mactrack_utilities();
		html_start_box(__('Device Tracking Database Results'), '100%', '', '3', 'center', '');
		?>
		<td>
			The following number of records have been removed from the aggergated table: <?php print $rows;?>
		</td>
		<?php
		html_end_box();
	}
}

function mactrack_utilities_recreate_aggregated_data() {
	global $config;

	if ((read_config_option('remove_verification') == 'on') && (!isset_request_var('confirm'))) {
		top_header();
		form_confirm(__('Are You Sure?'), __('Are you sure you want to delete and recreate all the Aggregated Port to MAC to IP results from the system?'), 'mactrack_utilities.php', 'mactrack_utilities.php?action=mactrack_utilities_recreate_aggregated_data');
		bottom_footer();
		exit;
	}


	if ((read_config_option('remove_verification') == '') || (isset_request_var('confirm'))) {
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
		html_start_box('Device Tracking Database Results', '100%', '', '3', 'center', '');
		?>
		<td>
			The following number of records have been removed from the aggergated table: <?php print $old_rows;?>. And <?php print $new_rows;?> number of record will be added.
		</td>
		<?php
		html_end_box();
	}
}

function mactrack_utilities_db_maint() {
	$begin_rows = db_fetch_cell('SELECT COUNT(*) FROM mac_track_ports');
	perform_mactrack_db_maint();
	$end_rows = db_fetch_cell('SELECT COUNT(*) FROM mac_track_ports');
	html_start_box('Device Tracking Database Results', '100%', '', '3', 'center', '');
	?>
	<td>
		The following number of records have been removed from the database: <?php print $begin_rows-$end_rows;?>
	</td>
	<?php
	html_end_box();
}

function mactrack_utilities_purge_scanning_funcs() {
	global $config;

	mactrack_rebuild_scanning_funcs();

	html_start_box('Device Tracking Scanning Function Refresh Results', '100%', '', '3', 'center', '');
	?>
	<td>
		The Device Tracking scanning functions have been purged.  They will be recreated once you either edit a device or device type.
	</td>
	<?php
	html_end_box();
}

function mactrack_utilities() {
	html_start_box('Cacti MacTrack System Utilities', '100%', '', '3', 'center', '');

	html_header(array('Process Status Information'), 2);

	?>
	<colgroup span="3">
		<col class="nowrap" style="vertical-align:top;width:20%;">
		<col style="vertical-align:top;width:80%;">
	</colgroup>
	<tr class='even'>
		<td class='textArea'>
			<a class='hyperLink' href='mactrack_utilities.php?action=mactrack_proc_status'>View MacTrack Process Status</a>
		</td>
		<td class='textArea'>
			This option will let you show and set process information associated with the MacTrack polling process.
		</td>
	</tr>

	<?php html_header(array('Database Administration'), 2);?>

	<tr class='odd'>
		<td class='textArea'>
			<a class='hyperLink' href='mactrack_utilities.php?action=mactrack_utilities_perform_db_maint'>Perform Database Maintenance</a>
		</td>
		<td class='textArea'>
			Deletes expired Port to MAC to IP associations from the database.  Only records that have expired, based upon your criteria are removed.
		</td>
	</tr>

	<tr class='even'>
		<td class='textArea'>
			<a class='hyperLink' href='mactrack_utilities.php?action=mactrack_refresh_oui_database'>Refresh IEEE Vendor MAC/OUI Database</a>
		</td>
		<td class='textArea'>
			This function will download and install the latest OIU database from the IEEE Website.
			Each Network Interface Card (NIC) has a MAC Address.  The MAC Address can be broken into two
			parts.  The first part of the MAC Addess contains the Vendor MAC.  The Vendor MAC identifies who
			manufactured the part.  This will be helpful in spot checking for rogue devices on your network.
		</td>
	</tr>

	<tr class='odd'>
		<td class='textArea'>
			<a class='hyperLink' href='mactrack_utilities.php?action=mactrack_utilities_purge_scanning_funcs'>Refresh Scanning Functions</a>
		</td>
		<td class='textArea'>
			Deletes old and potentially stale Device Tracking scanning functions from the drop-down
				you receive when you edit a device type.
		</td>
	</tr>

	<tr class='even'>
		<td class='textArea'>
			<a class='hyperLink' href='mactrack_utilities.php?action=mactrack_utilities_truncate_ports_table'>Remove All Scan Results</a>
		</td>
		<td class='textArea'>
			Deletes <strong>ALL</strong> Port to MAC to IP associations from the database all IP Addresses, IP Ranges, and VLANS.  This utility is good when you want to start over.  <strong>DANGER: All prior data is deleted.</strong>
		</td>
	</tr>

	<?php html_header(array('Aggregated Table Administration'), 2);?>

	<tr class='even'>
		<td class='textArea'>
			<a class='hyperLink' href='mactrack_utilities.php?action=mactrack_utilities_purge_aggregated_data'>Remove All Aggregated Results</a>
		</td>
		<td class='textArea'>
			Deletes ALL <strong>Aggregated</strong> (Not Scan Results) Port to MAC to IP associations from the database.  Data will again be collected on the basis of <strong>only new</strong> scanned data in the next mactrack poller run.
		</td>
	</tr>

	<tr class='odd'>
		<td class='textArea'>
			<a class='hyperLink' href='mactrack_utilities.php?action=mactrack_utilities_recreate_aggregated_data'>Perform Aggregate Table Rebuild</a>
		</td>
		<td class='textArea'>
			Deletes ALL <strong>Aggregated</strong> (Not Scan Results) Port to MAC to IP associations from the database and their re-creation based on <strong>All scaned data</strong>  now.
		</td>
	</tr>
	<?php

	html_end_box();
}

?>
