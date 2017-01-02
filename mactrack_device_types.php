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
/* include cacti base functions */
include('./include/auth.php');
include_once('./lib/snmp.php');
include_once('./plugins/mactrack/lib/mactrack_functions.php');

/* include base and vendor functions to obtain a list of registered scanning functions */
include_once('./plugins/mactrack/lib/mactrack_functions.php');
include_once('./plugins/mactrack/lib/mactrack_vendors.php');

/* store the list of registered mactrack scanning functions */
db_execute('REPLACE INTO mac_track_scanning_functions 
	(scanning_function, type) 
	VALUES ("Not Applicable - Router", 1)');

if (isset($mactrack_scanning_functions)) {
	foreach($mactrack_scanning_functions as $scanning_function) {
		db_execute_prepared('REPLACE INTO mac_track_scanning_functions 
			(scanning_function, type) 
			VALUES (?, 1)', array($scanning_function));
	}
}

/* store the list of registered mactrack scanning functions */
db_execute('REPLACE INTO mac_track_scanning_functions 
	(scanning_function, type) 
	VALUES ("Not Applicable - Switch/Hub", 2)');

if (isset($mactrack_scanning_functions_ip)) {
	foreach($mactrack_scanning_functions_ip as $scanning_function) {
		db_execute_prepared('REPLACE INTO mac_track_scanning_functions 
			(scanning_function, type) 
			VALUES (?, 2)', array($scanning_function));
	}
}

$device_types_actions = array(
	1 => __('Delete'),
	2 => __('Duplicate')
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
	mactrack_device_type_edit();
	bottom_footer();

	break;
case 'import':
	top_header();
	mactrack_device_type_import();
	bottom_footer();

	break;
default:
	if (isset_request_var('scan')) {
		mactrack_rescan_device_types();
		exit;
	}elseif (isset_request_var('import')) {
		header('Location: mactrack_device_types.php?header=false&action=import');
		exit;
	}elseif (isset_request_var('export')) {
		mactrack_device_type_export();
		exit;
	}else{
		top_header();
		mactrack_device_type();
		bottom_footer();
	}

	break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if ((isset_request_var('save_component_device_type')) && (isempty_request_var('add_dq_y'))) {
		$device_type_id = api_mactrack_device_type_save(get_nfilter_request_var('device_type_id'), 
			get_nfilter_request_var('description'), get_nfilter_request_var('vendor'), 
			get_nfilter_request_var('device_type'), get_nfilter_request_var('sysDescr_match'), 
			get_nfilter_request_var('sysObjectID_match'), get_nfilter_request_var('scanning_function'), 
			get_nfilter_request_var('ip_scanning_function'), get_nfilter_request_var('serial_number_oid'), 
			get_nfilter_request_var('lowPort'), get_nfilter_request_var('highPort'));

		header('Location: mactrack_device_types.php?action=edit&device_type_id=' . (empty($device_type_id) ? get_nfilter_request_var('device_type_id') : $device_type_id));
	}

	if (isset_request_var('save_component_import')) {
		if (($_FILES['import_file']['tmp_name'] != 'none') && ($_FILES['import_file']['tmp_name'] != '')) {
			/* file upload */
			$csv_data = file($_FILES['import_file']['tmp_name']);

			/* obtain debug information if it's set */
			$debug_data = mactrack_device_type_import_processor($csv_data);
			if(sizeof($debug_data) > 0) {
				$_SESSION['import_debug_info'] = $debug_data;
			}
		}else{
			header('Location: mactrack_device_types.php?action=import'); exit;
		}

		header('Location: mactrack_device_types.php?action=import'); exit;
	}
}

function api_mactrack_device_type_remove($device_type_id){
	db_execute_prepared('DELETE FROM mac_track_device_types WHERE device_type_id = ?', array($device_type_id));
}

function api_mactrack_device_type_save($device_type_id, $description,
	$vendor, $device_type, $sysDescr_match, $sysObjectID_match, $scanning_function,
	$ip_scanning_function, $serial_number_oid, $lowPort, $highPort) {

	$save['device_type_id']       = $device_type_id;
	$save['description']          = form_input_validate($description, 'description', '', false, 3);
	$save['vendor']               = $vendor;
	$save['device_type']          = $device_type;
	$save['sysDescr_match']       = form_input_validate($sysDescr_match, 'sysDescr_match', '', true, 3);
	$save['sysObjectID_match']    = form_input_validate($sysObjectID_match, 'sysObjectID_match', '', true, 3);
	$save['serial_number_oid']    = form_input_validate($serial_number_oid, 'serial_number_oid', '', true, 3);
	$save['scanning_function']    = form_input_validate($scanning_function, 'scanning_function', '', true, 3);
	$save['ip_scanning_function'] = form_input_validate($ip_scanning_function, 'ip_scanning_function', '', true, 3);
	$save['lowPort']              = form_input_validate($lowPort, 'lowPort', '', true, 3);
	$save['highPort']             = form_input_validate($highPort, 'highPort', '', true, 3);

	$device_type_id = 0;
	if (!is_error_message()) {
		$device_type_id = sql_save($save, 'mac_track_device_types', 'device_type_id');

		if ($device_type_id) {
			raise_message(1);
		}else{
			raise_message(2);
		}
	}

	return $device_type_id;
}

function api_mactrack_duplicate_device_type($device_type_id, $dup_id, $device_type_title) {
	if (!empty($device_type_id)) {
		$device_type = db_fetch_row_prepared('SELECT * FROM mac_track_device_types WHERE device_type_id = ?', array($device_type_id));

		/* create new entry: graph_local */
		$save['device_type_id'] = 0;

		if (substr_count($device_type_title, '<description>')) {
			$save['description'] = $device_type['description'] . '(1)';
		}else{
			$save['description'] = $device_type_title . '(' . $dup_id . ')';
		}

		$save['vendor'] = $device_type['vendor'];
		$save['device_type'] = $device_type['device_type'];
		$save['sysDescr_match'] = '--dup--' . $device_type['sysDescr_match'];
		$save['sysObjectID_match'] = '--dup--' . $device_type['sysObjectID_match'];
		$save['scanning_function'] = $device_type['scanning_function'];
		$save['ip_scanning_function'] = $device_type['ip_scanning_function'];
		$save['lowPort'] = $device_type['lowPort'];
		$save['highPort'] = $device_type['highPort'];

		$device_type_id = sql_save($save, 'mac_track_device_types', array('device_type_id'));
	}
}

/* ------------------------
    The 'actions' function
   ------------------------ */

function form_actions() {
	global $config, $device_types_actions, $fields_mactrack_device_types_edit;

	/* ================= input validation ================= */
	get_filter_request_var('drp_action');
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_request_var('drp_action') == '1') { /* delete */
				for ($i=0; $i<count($selected_items); $i++) {
					api_mactrack_device_type_remove($selected_items[$i]);
				}
			}elseif (get_nfilter_request_var('drp_action') == '2') { /* duplicate */
				for ($i=0;($i<count($selected_items));$i++) {
					api_mactrack_duplicate_device_type($selected_items[$i], $i, get_request_var('title_format'));
				}
			}

			header('Location: mactrack_device_types.php');
			exit;
		}
	}

	/* setup some variables */
	$device_types_list = ''; $i = 0;

	/* loop through each of the device types selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$device_types_info = db_fetch_row_prepared('SELECT description FROM mac_track_device_types WHERE device_type_id = ?', array($matches[1]));
			$device_types_list .= '<li>' . $device_types_info['description'] . '</li>';
			$device_types_array[] = $matches[1];
		}
	}

	top_header();

	form_start('mactrack_device_types.php');

	html_start_box($device_types_actions[get_request_var('drp_action')], '60%', '', '3', 'center', '');

	if (!isset($device_types_array)) {
		print "<tr><td class='even'><span class='textError'>" . __('You must select at least one device type.') . "</span></td></tr>\n";
		$save_html = '';
	}else{
		$save_html = "<input type='submit' value='" . __('Yes') . "' name='save'>";

		if (get_request_var('drp_action') == '1') { /* delete */
			print "<tr>
				<td class='textArea'>
					<p>" . __('Are you sure you want to delete the following device types?') . "</p>
					<p><ul>$device_types_list</ul></p>
				</td>
			</tr>";
		}elseif (get_request_var('drp_action') == '2') { /* duplicate */
			print "<tr>
				<td class='textArea'>
					<p>" . __('When you click save, the following device types will be duplicated. You may optionally change the description for the new device types.  Otherwise, do not change value below and the original name will be replicated with a new suffix.') . "</p>
					<p><ul>$device_types_list</ul></p>
					<p>" . __('Device Type Prefix:') . '<br>'; form_text_box('title_format', '<description> (1)', '', '255', '30', 'text'); print "</p>
				</td>
			</tr>";
		}
	}

	print "<tr>
		<td colspan='2' align='right' class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($device_types_array) ? serialize($device_types_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_nfilter_request_var('drp_action') . "'>" . (strlen($save_html) ? "
			<input type='button' onClick='cactiReturnTo()' name='cancel' value='" . __('No') . "'>
			$save_html" : "<input type='submit' onClick='cactiReturnTo()' name='cancel' value='" . __('Return') . "'>") . "
		</td>
	</tr>";

	html_end_box();

	form_end();

	bottom_footer();
}

/* ---------------------
    Mactrack Device Type Functions
   --------------------- */

function mactrack_device_type_request_validation() {
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
		'type_id' => array(
			'filter' => FILTER_VALIDATE_INT, 
			'default' => '-1',
			'pageset' => true
			),
		'vendor' => array(
			'filter' => FILTER_CALLBACK, 
			'pageset' => true,
			'default' => 'All', 
			'options' => array('options' => 'sanitize_search_string')
			),
		'filter' => array(
			'filter' => FILTER_CALLBACK, 
			'pageset' => true,
			'default' => '', 
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK, 
			'default' => 'description', 
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK, 
			'default' => 'ASC', 
			'options' => array('options' => 'sanitize_search_string')
			)
	);

	validate_store_request_vars($filters, 'sess_mactrack_dt');
}

function mactrack_device_type_export() {
	global $device_actions, $mactrack_device_types, $config;

	mactrack_device_type_request_validation();

	$sql_where = '';

	$device_types = mactrack_get_device_types($sql_where, 0, FALSE);

	$xport_array = array();
	array_push($xport_array, '"vendor","description","device_type",' .
		'"sysDescr_match","sysObjectID_match","scanning_function","ip_scanning_function",' .
		'"serial_number_oid","lowPort","highPort"');

	if (sizeof($device_types)) {
		foreach($device_types as $device_type) {
			array_push($xport_array,'"' . $device_type['vendor'] . '","' .
			$device_type['description'] . '","' .
			$device_type['device_type'] . '","' .
			$device_type['sysDescr_match'] . '","' .
			$device_type['sysObjectID_match'] . '","' .
			$device_type['scanning_function'] . '","' .
			$device_type['ip_scanning_function'] . '","' .
			$device_type['serial_number_oid'] . '","' .
			$device_type['lowPort'] . '","' .
			$device_type['highPort'] . '"');
		}
	}

	header("Content-type: application/csv");
	header("Content-Disposition: attachment; filename=cacti_device_type_xport.csv");
	foreach($xport_array as $xport_line) {
		print $xport_line . "\n";
	}
}

function mactrack_rescan_device_types() {
	global $cnn_id;

	/* let's allocate an array for results */
	$insert_array = array();

	/* get all the various device types from the database */
	$device_types = db_fetch_assoc("SELECT DISTINCT snmp_sysObjectID, snmp_sysDescr, device_type_id
		FROM mac_track_devices
		WHERE snmp_sysObjectID!='' AND snmp_sysDescr!=''");

	/* get all known devices types from the device type database */
	$known_types = db_fetch_assoc('SELECT sysDescr_match, sysObjectID_match FROM mac_track_device_types');

	/* loop through all device rows and look for a matching type */
	if (sizeof($device_types)) {
	foreach($device_types as $type) {
		$found = FALSE;
		if (sizeof($known_types)) {
		foreach($known_types as $known) {
			if ((substr_count($type['snmp_sysDescr'], $known['sysDescr_match'])) &&
				(substr_count($type['snmp_sysObjectID'], $known['sysObjectID_match']))) {
				$found = TRUE;
				break;
			}
		}
		}

		if (!$found) {
			$insert_array[] = $type;
		}
	}
	}

	if (sizeof($insert_array)) {
		foreach($insert_array as $item) {
			$desc = trim($item['snmp_sysDescr']);
			$name = __('New Type');
			if (substr_count(strtolower($desc), 'cisco')) {
				$vendor = __('Cisco');
				$temp_name = str_replace('(tm)', '', $desc);
				$pos = strpos($temp_name, '(');
				if ($pos > 0) {
					$pos2 = strpos($temp_name, ')');
					if ($pos2 > $pos) {
						$desc = substr($temp_name, $pos+1, $pos2-$pos-1);

						$name = $desc . ' (' . $item['device_type_id'] . ')';
					}
				}
			}else{
				$vendor = __('Unknown');
			}

			db_execute("REPLACE INTO mac_track_device_types
				(description, vendor, device_type, sysDescr_match, sysObjectID_match)
				VALUES (" .
					db_qstr($name)                     . "," .
					db_qstr($vendor)                   . "," .
					db_qstr($item['device_type'])      . ","  .
					db_qstr($desc)                     . ","  .
					db_qstr($item['snmp_sysObjectID']) . ")");
		}

		print __('There were %d Device Types Added!', sizeof($insert_array));
	}else{
		print __('No New Device Types Found!');
	}
}

function mactrack_device_type_import() {
	global $config;

	?><form method='post' action='mactrack_device_types.php?action=import' enctype='multipart/form-data'><?php

	if ((isset($_SESSION['import_debug_info'])) && (is_array($_SESSION['import_debug_info']))) {
		html_start_box(__('Import Results'), '100%', '', '3', 'center', '');

		print "<tr class='even'><td><p class='textArea'>" . __('Cacti has imported the following items:') . '</p>';
		foreach($_SESSION['import_debug_info'] as $import_result) {
			print "<tr class='even'><td>" . $import_result . '</td>';
			print '</tr>';
		}

		html_end_box();

		kill_session_var('import_debug_info');
	}

	html_start_box(__('Import MacTrack Device Types'), '100%', '', '3', 'center', '');

	form_alternate_row();?>
		<td width='50%'><font class='textEditTitle'>Import Device Types from Local File</font><br>
			Please specify the location of the CSV file containing your device type information.
		</td>
		<td align='left'>
			<input type='file' name='import_file'>
		</td>
	</tr><?php
	form_alternate_row();?>
		<td width='50%'><font class='textEditTitle'>Overwrite Existing Data?</font><br>
			Should the import process be allowed to overwrite existing data?  Please note, this does not mean delete old row, only replace duplicate rows.
		</td>
		<td align='left'>
			<input type='checkbox' name='allow_update' id='allow_update'>Allow Existing Rows to be Updated?
		</td><?php

	html_end_box(FALSE);

	html_start_box(__('Required File Format Notes'), '100%', '', '3', 'center', '');

	form_alternate_row();?>
		<td><strong>The file must contain a header row with the following column headings.</strong>
			<br><br>
			<strong>description</strong> - A common name for the device.  For example Cisco 6509 Switch<br>
			<strong>vendor</strong> - The vendor who produces this device<br>
			<strong>device_type</strong> - The type of device this is.  See the notes below for this integer value<br>
			<strong>sysDescr_match</strong> - A unique set of characters from the snmp sysDescr that uniquely identify this device<br>
			<strong>sysObjectID_match</strong> - The vendor specific snmp sysObjectID that distinguishes this device from the next<br>
			<strong>scanning_function</strong> - The scanning function that will be used to scan this device type<br>
			<strong>ip_scanning_function</strong> - The IP scanning function that will be used to scan this device type<br>
			<strong>serial_number_oid</strong> - If the Serial Number for this device type can be obtained via an SNMP Query, add it's OID here<br>
			<strong>lowPort</strong> - If your scanning function does not have the ability to isolate trunk ports or link ports, this is the starting port number for user ports<br>
			<strong>highPort</strong> - Same as the lowPort with the exception that this is the high numbered user port number<br>
			<br>
			<strong>The primary key for this table is a combination of the following three fields:</strong>
			<br><br>
			device_type, sysDescr_match, sysObjectID_match
			<br><br>
			<strong>Therefore, if you attempt to import duplicate device types, the existing data will be updated with the new information.</strong>
			<br><br>
			<strong>device_type</strong> is an integer field and must be one of the following:
			<br><br>
			1 - Switch/Hub<br>
			2 - Switch/Router<br>
			3 - Router<br>
			<br>
			<strong>The devices device type is determined by scanning it's snmp agent for the sysObjectID and sysDescription and comparing it against
			values in the device types database.  The first match that is found in the database is used direct MacTrack as to how to scan it.  Therefore,
			it is very important that you select valid sysObjectID_match, sysDescr_match, and scanning function for your devices.</strong>
			<br>
		</td>
	</tr><?php

	form_hidden_box('save_component_import', '1', '');

	html_end_box();

	form_save_button('return', 'import');
}

function mactrack_device_type_import_processor(&$device_types) {
	$i = 0;
	$return_array = array();
	$insert_columns = array();

	$device_type_array[1] = __('Switch/Hub');
	$device_type_array[2] = __('Switch/Router');
	$device_type_array[3] = __('Router');

	foreach($device_types as $device_type) {
		/* parse line */
		$line_array = explode(',', $device_type);

		/* header row */
		if ($i == 0) {
			$save_order = '(';
			$j = 0;
			$first_column = TRUE;
			$update_suffix = '';
			$required = 0;
			$sysDescr_match_id = -1;
			$sysObjectID_match_id = -1;
			$device_type_id = -1;
			$save_vendor_id = -1;
			$save_description_id = -1;

			foreach($line_array as $line_item) {
				$line_item = trim(str_replace("'", '', $line_item));
				$line_item = trim(str_replace('"', '', $line_item));

				switch ($line_item) {
					case 'device_type':
						if (!$first_column) {
							$save_order .= ', ';
						}

						$device_type_id = $j;
						$required++;

						$save_order .= $line_item;
						$insert_columns[] = $j;
						$first_column = FALSE;

						if (strlen($update_suffix)) {
							$update_suffix .= ", $line_item=VALUES($line_item)";
						}else{
							$update_suffix .= " ON DUPLICATE KEY UPDATE $line_item=VALUES($line_item)";
						}


						break;
					case 'sysDescr_match':
						if (!$first_column) {
							$save_order .= ', ';
						}

						$sysDescr_match_id = $j;
						$required++;

						$save_order .= $line_item;
						$insert_columns[] = $j;
						$first_column = FALSE;

						if (strlen($update_suffix)) {
							$update_suffix .= ", $line_item=VALUES($line_item)";
						}else{
							$update_suffix .= " ON DUPLICATE KEY UPDATE $line_item=VALUES($line_item)";
						}


						break;
					case 'sysObjectID_match':
						if (!$first_column) {
							$save_order .= ', ';
						}

						$sysObjectID_match_id = $j;
						$required++;

						$save_order .= $line_item;
						$insert_columns[] = $j;
						$first_column = FALSE;

						if (strlen($update_suffix)) {
							$update_suffix .= ", $line_item=VALUES($line_item)";
						}else{
							$update_suffix .= " ON DUPLICATE KEY UPDATE $line_item=VALUES($line_item)";
						}

						break;
					case 'scanning_function':
					case 'ip_scanning_function':
					case 'serial_number_oid':
					case 'lowPort':
					case 'highPort':
						if (!$first_column) {
							$save_order .= ', ';
						}

						$save_order .= $line_item;
						$insert_columns[] = $j;
						$first_column = FALSE;

						if (strlen($update_suffix)) {
							$update_suffix .= ", $line_item=VALUES($line_item)";
						}else{
							$update_suffix .= " ON DUPLICATE KEY UPDATE $line_item=VALUES($line_item)";
						}

						break;
					case 'vendor':
						if (!$first_column) {
							$save_order .= ', ';
						}

						$save_order .= $line_item;
						$insert_columns[] = $j;
						$save_vendor_id = $j;
						$first_column = FALSE;

						if (strlen($update_suffix)) {
							$update_suffix .= ", $line_item=VALUES($line_item)";
						}else{
							$update_suffix .= " ON DUPLICATE KEY UPDATE $line_item=VALUES($line_item)";
						}

						break;
					case 'description':
						if (!$first_column) {
							$save_order .= ', ';
						}

						$save_order .= $line_item;
						$insert_columns[] = $j;
						$save_description_id = $j;
						$first_column = FALSE;

						if (strlen($update_suffix)) {
							$update_suffix .= ", $line_item=VALUES($line_item)";
						}else{
							$update_suffix .= " ON DUPLICATE KEY UPDATE $line_item=VALUES($line_item)";
						}

						break;
					default:
						/* ignore unknown columns */
				}

				$j++;
			}

			$save_order .= ')';

			if ($required >= 3) {
				array_push($return_array, '<strong>HEADER LINE PROCESSED OK</strong>:  <br>Columns found where: ' . $save_order . '<br>');
			}else{
				array_push($return_array, '<strong>HEADER LINE PROCESSING ERROR</strong>: Missing required field <br>Columns found where:' . $save_order . '<br>');
				break;
			}
		}else{
			$save_value = '(';
			$j = 0;
			$first_column = TRUE;
			$sql_where = '';

			foreach($line_array as $line_item) {
				if (in_array($j, $insert_columns)) {
					$line_item = trim(str_replace("'", '', $line_item));
					$line_item = trim(str_replace('"', '', $line_item));

					if (!$first_column) {
						$save_value .= ',';
					}else{
						$first_column = FALSE;
					}

					if ($j == $device_type_id || $j == $sysDescr_match_id || $j == $sysObjectID_match_id ) {
						if (strlen($sql_where)) {
							switch($j) {
							case $device_type_id:
								$sql_where .= " AND device_type='$line_item'";
								break;
							case $sysDescr_match_id:
								$sql_where .= " AND sysDescr_match='$line_item'";
								break;
							case $sysObjectID_match_id:
								$sql_where .= " AND sysObjectID_match='$line_item'";
								break;
							default:
								/* do nothing */
							}
						}else{
							switch($j) {
							case $device_type_id:
								$sql_where .= "WHERE device_type='$line_item'";
								break;
							case $sysDescr_match_id:
								$sql_where .= "WHERE sysDescr_match='$line_item'";
								break;
							case $sysObjectID_match_id:
								$sql_where .= "WHERE sysObjectID_match='$line_item'";
								break;
							default:
								/* do nothing */
							}
						}
					}

					if ($j == $device_type_id) {
						if (isset($device_type_array[$line_item])) {
							$device_type = $device_type_array[$line_item];
						}else{
							$device_type = __('Uknown Assume "Switch/Hub"');
							$line_item = 1;
						}
					}

					if ($j == $sysDescr_match_id) {
						$sysDescr_match = $line_item;
					}

					if ($j == $sysObjectID_match_id) {
						$sysObjectID_match = $line_item;
					}

					if ($j == $save_vendor_id) {
						$vendor = $line_item;
					}

					if ($j == $save_description_id) {
						$description = $line_item;
					}

					$save_value .= "'" . $line_item . "'";
				}

				$j++;
			}

			$save_value .= ')';

			if ($j > 0) {
				if (isset_request_var('allow_update')) {
					$sql_execute = 'INSERT INTO mac_track_device_types ' . $save_order .
						' VALUES' . $save_value . $update_suffix;

					if (db_execute($sql_execute)) {
						array_push($return_array,"INSERT SUCCEEDED: Vendor: $vendor, Description: $description, Type: $device_type, sysDescr: $sysDescr_match, sysObjectID: $sysObjectID_match");
					}else{
						array_push($return_array,"<strong>INSERT FAILED:</strong> Vendor: $vendor, Description: $description, Type: $device_type, sysDescr: $sysDescr_match, sysObjectID: $sysObjectID_match");
					}
				}else{
					/* perform check to see if the row exists */
					$existing_row = db_fetch_row("SELECT * FROM mac_track_device_types $sql_where");

					if (sizeof($existing_row)) {
						array_push($return_array,"<strong>INSERT SKIPPED, EXISTING:</strong> Vendor: $vendor, Description: $description, Type: $device_type, sysDescr: $sysDescr_match, sysObjectID: $sysObjectID_match");
					}else{
						$sql_execute = 'INSERT INTO mac_track_device_types ' . $save_order .
							' VALUES' . $save_value;

						if (db_execute($sql_execute)) {
							array_push($return_array,"INSERT SUCCEEDED: Vendor: $vendor, Description: $description, Type: $device_type, sysDescr: $sysDescr_match, sysObjectID: $sysObjectID_match");
						}else{
							array_push($return_array,"<strong>INSERT FAILED:</strong> Vendor: $vendor, Description: $description, Type: $device_type, sysDescr: $sysDescr_match, sysObjectID: $sysObjectID_match");
						}
					}
				}
			}
		}

		$i++;
	}

	return $return_array;
}

function mactrack_device_type_remove() {
	global $config;

	/* ================= input validation ================= */
	get_filter_request_var('device_type_id');
	/* ==================================================== */

	if ((read_config_option('remove_verification') == 'on') && (!isset_request_var('confirm'))) {
		top_header();
		form_confirm(__('Are You Sure?'), __('Are you sure you want to delete the device type %s?', db_fetch_cell_prepared('SELECT description FROM host WHERE id = ?', array(get_request_var('device_id')))), 'mactrack_device_types.php', 'mactrack_device_types.php?action=remove&id=' . get_request_var('device_type_id'));
		bottom_footer();
		exit;
	}

	if ((read_config_option('remove_verification') == '') || (isset_request_var('confirm'))) {
		api_mactrack_device_type_remove(get_request_var('device_type_id'));
	}
}

function mactrack_device_type_edit() {
	global $config, $fields_mactrack_device_type_edit;

	/* ================= input validation ================= */
	get_filter_request_var('device_type_id');
	/* ==================================================== */

	display_output_messages();

	if (!isempty_request_var('device_type_id')) {
		$device_type = db_fetch_row_prepared('SELECT * 
			FROM mac_track_device_types 
			WHERE device_type_id = ?', array(get_request_var('device_type_id')));

		$header_label = __('MacTrack Device Types [edit: %s]', $device_type['description']);
	}else{
		$header_label = __('MacTrack Device Types [new]');
	}

	form_start('mactrack_device_types.php', 'chk');

	html_start_box($header_label, '100%', '', '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => 'true'),
			'fields' => inject_form_variables($fields_mactrack_device_type_edit, (isset($device_type) ? $device_type : array()))
		)
	);

	html_end_box();

	form_save_button('mactrack_device_types.php', 'return', 'device_type_id');
}

function mactrack_get_device_types(&$sql_where, $row_limit, $apply_limits = TRUE) {
	if (get_request_var('filter') != '') {
		$sql_where = " WHERE (mac_track_device_types.vendor LIKE '%" . get_request_var('filter') . "%' OR
			mac_track_device_types.description LIKE '%" . get_request_var('filter') . "%' OR
			mac_track_device_types.sysDescr_match LIKE '%" . get_request_var('filter') . "%' OR
			mac_track_device_types.sysObjectID_match LIKE '%" . get_request_var('filter') . "%')";
	}

	if (get_request_var('vendor') == 'All') {
		/* Show all items */
	}else{
		$sql_where .= (strlen($sql_where) ? ' AND ': ' WHERE ') . "(mac_track_device_types.vendor='" . get_request_var('vendor') . "')";
	}

	if (get_request_var('type_id') == '-1') {
		/* Show all items */
	}else{
		$sql_where .= (strlen($sql_where) ? ' AND ': ' WHERE ') . "(mac_track_device_types.device_type=" . get_request_var('type_id') . ")";
	}

	$query_string = "SELECT *
		FROM mac_track_device_types
		$sql_where
		ORDER BY " . get_request_var('sort_column') . ' ' . get_request_var('sort_direction');

	if ($apply_limits) {
		$query_string .= ' LIMIT ' . ($row_limit*(get_request_var('page')-1)) . ',' . $row_limit;
	}

	return db_fetch_assoc($query_string);
}

function mactrack_device_type() {
	global $device_types_actions, $mactrack_device_types, $config, $item_rows;

	mactrack_device_type_request_validation();

	if (get_request_var('rows') == -1) {
		$row_limit = read_config_option('num_rows_table');
	}elseif (get_request_var('rows') == -2) {
		$row_limit = 999999;
	}else{
		$row_limit = get_request_var('rows');
	}

	html_start_box(__('MacTrack Device Type Filters'), '100%', '', '3', 'center', 'mactrack_device_types.php?action=edit');
	mactrack_device_type_filter();
	html_end_box();

	$sql_where = '';

	$device_types = mactrack_get_device_types($sql_where, $row_limit);

	$total_rows = db_fetch_cell("SELECT
		COUNT(mac_track_device_types.device_type_id)
		FROM mac_track_device_types" . $sql_where);

	$nav = html_nav_bar('mactrack_device_types.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $row_limit, $total_rows, 9, __('Device Types'));

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'description'          => array(__('Device Type Description'), 'ASC'),
		'vendor'               => array(__('Devices'), 'DESC'),
		'device_type'          => array(__('Device Type'), 'DESC'),
		'scanning_function'    => array(__('Port Scanner'), 'ASC'),
		'ip_scanning_function' => array(__('IP Scanner'), 'ASC'),
		'sysDescr_match'       => array(__('sysDescription Match'), 'DESC'),
		'sysObjectID_match'    => array(__('Vendor OID Match'), 'DESC'));

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'));

	if (sizeof($device_types) > 0) {
		foreach ($device_types as $device_type) {
			form_alternate_row('line' . $device_type['device_type_id'], true);
			form_selectable_cell('<a class="linkEditMain" href="mactrack_device_types.php?action=edit&device_type_id=' . $device_type['device_type_id'] . '">' . $device_type['description'] . '</a>', $device_type['device_type_id']);
			form_selectable_cell($device_type['vendor'], $device_type['device_type_id']);
			form_selectable_cell($mactrack_device_types[$device_type['device_type']], $device_type['device_type_id']);
			form_selectable_cell($device_type['scanning_function'], $device_type['device_type_id']);
			form_selectable_cell($device_type['ip_scanning_function'], $device_type['device_type_id']);
			form_selectable_cell($device_type['sysDescr_match'], $device_type['device_type_id']);
			form_selectable_cell($device_type['sysObjectID_match'], $device_type['device_type_id']);
			form_checkbox_cell($device_type['description'], $device_type['device_type_id']);
			form_end_row();
		}
	}else{
		print '<tr><td colspan="10"><em>' . __('No MacTrack Device Types') . '</em></td></tr>';
	}

	html_end_box(false);

	if (sizeof($device_types)) {
		print $nav;
	}

	draw_actions_dropdown($device_types_actions);
}

function mactrack_device_type_filter() {
	global $item_rows;

	?>
	<tr class='even'>
		<td>
		<form id='mactrack'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' id='filter' size='25' value='<?php print get_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Device Types');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('rows') == '-1') {?> selected<?php }?>><?php print __('Default');?></option>
							<?php
							if (sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . $value . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<input type='submit' id='go' title='<?php print __('Submit Query');?>' value='<?php print __('Go');?>'>
					</td>
					<td>
						<input type='button' id='clear' title='<?php print __('Clear Filtered Results');?>' value='<?php print __('Clear');?>'>
					</td>
					<td>
						<input type='button' id='scan' title='<?php print __('Scan Active Devices for Unknown Device Types');?>' value='<?php print __('Rescan');?>'>
					</td>
					<td>
						<input type='button' id='import' title='<?php print __('Import Device Types from a CSV File');?>' value='<?php print __('Import');?>'>
					</td>
					<td>
						<input type='button' id='export' title='<?php print __('Export Device Types to Share with Others');?>' value='<?php print __('Export');?>'>
					</td>
					<td>
						<span id="text" style="display:none;"></span>
					</td>
				</tr>
			</table>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Vendor');?>
					</td>
					<td>
						<select id='vendor' onChange='applyFilter()'>
							<option value='All'<?php if (get_request_var('vendor') == 'All') print ' selected';?>><?php print __('All');?></option>
							<?php
							$types = db_fetch_assoc('SELECT DISTINCT vendor from mac_track_device_types ORDER BY vendor');

							if (sizeof($types)) {
								foreach ($types as $type) {
									print '<option value="' . $type['vendor'] . '"';if (get_request_var('vendor') == $type['vendor']) { print ' selected'; } print '>' . $type['vendor'] . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Type');?>
					</td>
					<td>
						<select id='type_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('type_id') == '-1') print ' selected';?>><?php print __('All');?></option>
							<option value='1'<?php if (get_request_var('type_id') == '1') print ' selected';?>><?php print __('Switch/Hub');?></option>
							<option value='2'<?php if (get_request_var('type_id') == '2') print ' selected';?>><?php print __('Switch/Router');?></option>
							<option value='3'<?php if (get_request_var('type_id') == '3') print ' selected';?>><?php print __('Router');?></option>
						</select>
					</td>
				</tr>
			</table>
			<script type='text/javascript'>
			function applyFilter(myFunc) {
				strURL  = urlPath+'plugins/mactrack/mactrack_device_types.php?header=false';
				strURL += '&vendor=' + $('#vendor').val();
				strURL += '&type_id=' + $('#type_id').val();
				strURL += '&filter=' + $('#filter').val();
				strURL += '&rows=' + $('#rows').val();
				loadPageNoHeader(strURL);
			}

			function clearFilter() {
				strURL  = urlPath+'plugins/mactrack/mactrack_device_types.php?header=false&clear=true';
				loadPageNoHeader(strURL);
			}

			function exportRows() {
				strURL  = urlPath+'plugins/mactrack/mactrack_device_types.php?export=true';
				document.location = strURL;
			}

			function importRows() {
				strURL  = urlPath+'plugins/mactrack/mactrack_device_types.php?import=true';
				loadPageNoHeader(strURL);
			}

			function scanDeviceType() {
				strURL  = urlPath+'plugins/mactrack/mactrack_device_types.php?scan=true';
				$.get(strURL, function(data) {
					var message = data;
					applyFilter();
				});
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

				$('#import').click(function() {
					importRows();
				});

				$('#scan').click(function() {
					scanDeviceType();
				});
			});
			</script>
		</form>
		</td>
	</tr>
	<?php
}
