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

/* This file was modified from mactrack_dell.php
   specifically written and tested to work with Trendnet TL2-G244 gigabit switches

   Gabriel Gómez Sena <ggomez@fing.edu.uy>
*/

/* register this functions scanning functions */
if (!isset($mactrack_scanning_functions)) { $mactrack_scanning_functions = array(); }
array_push($mactrack_scanning_functions, 'get_trendnet_dot1q_switch_ports');

/*	get_trendnet_dot1q_switch_ports - This is a basic function that will scan the dot1d
  OID tree for all switch port to MAC address association and stores in the
  mac_track_temp_ports table for future processing in the finalization steps of the
  scanning process.
*/
function get_trendnet_dot1q_switch_ports($site, &$device, $lowPort = 0, $highPort = 0) {
	global $debug, $scan_date;

	/* initialize port counters */
	$device['ports_total']  = 0;
	$device['ports_active'] = 0;
	$device['ports_trunk']  = 0;

	/* get the ifIndexes for the device */
	$ifIndexes = xform_standard_indexed_data('.1.3.6.1.2.1.2.2.1.1', $device);
	mactrack_debug('ifIndexes data collection complete');

	$ifInterfaces = build_InterfacesTable($device, $ifIndexes, true, true);

	/* sanitize ifInterfaces by removing text from ifType field */
	if (cacti_sizeof($ifInterfaces)) {
		foreach ($ifInterfaces as $key => $tempInterfaces){
			preg_match('/[0-9]{1,3}/', $tempInterfaces['ifType'], $newType);
			$ifInterfaces[$key]['ifType'] = $newType[0];
		}
	}

	get_base_trendnet_dot1qFdb_ports($site, $device, $ifInterfaces, '', true, $lowPort, $highPort);

	return $device;
}
/*	get_base_trendnet_dot1qFdb_ports - This function will grab information from the
  port bridge snmp table and return it to the calling progrem for further processing.
  This is a foundational function for all vendor data collection functions.
  This was mainly copied from the default dot1q function in mactrack_functions.php
  but was modified to work with Dell switches
*/
function get_base_trendnet_dot1qFdb_ports($site, &$device, &$ifInterfaces, $snmp_readstring = '', $store_to_db = true, $lowPort = 1, $highPort = 9999) {
	global $debug, $scan_date;

	/* initialize variables */
	$port_keys = array();
	$return_array = array();
	$new_port_key_array = array();
	$port_key_array = array();
	$port_number = 0;
	$ports_active = 0;
	$active_ports = 0;
	$ports_total = 0;
	$snmp_readstring = $device['snmp_readstring'];

	/* get the operational status of the ports */
	$active_ports_array = xform_standard_indexed_data('.1.3.6.1.2.1.2.2.1.8', $device);
	$indexes = array_keys($active_ports_array);

	/* Sanitize active ports array */
	if (cacti_sizeof($active_ports_array)) {
		foreach ($active_ports_array as $key => $tempPorts){
			preg_match('/[0-9]{1,3}/',$tempPorts,$newStatus);
			$active_ports_array[$key]=$newStatus[0];
		}
	}

	$i = 0;
	if (cacti_sizeof($active_ports_array)) {
		foreach ($active_ports_array as $port_info) {
			if ((($ifInterfaces[$indexes[$i]]['ifType'] >= 6) &&
				($ifInterfaces[$indexes[$i]]['ifType'] <= 9)) ||
				($ifInterfaces[$indexes[$i]]['ifType'] == 71)) {
				if ($port_info == 1) {
					$ports_active++;
				}

				$ports_total++;
			}

			$i++;
		}
	}

	if ($store_to_db) {
		mactrack_debug('INFO: HOST: ' . $device['hostname'] . ', TYPE: ' . substr($device['snmp_sysDescr'],0,40) . ', TOTAL PORTS: ' . $ports_total . ', OPER PORTS: ' . $ports_active);

		$device['ports_active'] = $ports_active;
		$device['ports_total'] = $ports_total;
		$device['macs_active'] = 0;
	}

	/* get VLAN information */
	$vlan_names = xform_stripped_oid('.1.3.6.1.2.1.17.7.1.4.3.1.1', $device, $snmp_readstring);
	$device['vlans_total'] = cacti_sizeof($vlan_names) - 1;
	mactrack_debug('VLAN data collected. There are ' . (cacti_sizeof($vlan_names) - 1) . ' VLANS.');

	$port_status = xform_stripped_oid('.1.3.6.1.4.1.28866.2.18.116.7.1.4.7.1.2', $device, $snmp_readstring);
	if (cacti_sizeof($port_status)) {
		foreach ($port_status as $key => $tempStatus){
			if ($tempStatus == 2) {
				$device['ports_trunk']++;
			}
		}
	}

	mactrack_debug('vlans total : ' . $device['vlans_total'] . ', ports_trunk: ' . $device['ports_trunk']);
	mactrack_debug('Vlan assembly complete.');

	if ($ports_active > 0) {
		/* get bridge port to ifIndex mapping */
		$bridgePortIfIndexes = xform_standard_indexed_data('.1.3.6.1.2.1.17.1.4.1.2', $device, $snmp_readstring);

		$port_status = xform_stripped_oid('.1.3.6.1.2.1.17.7.1.2.2.1.3', $device, $snmp_readstring);
		/* Sanitize port_status array */
		if (cacti_sizeof($port_status)) {
			foreach ($port_status as $key => $tempStatus){
				preg_match('/[0-9]{1,3}/',$tempStatus,$newStatus);
				$port_status[$key]=$newStatus[0];
			}
		}
		//print_r($port_status);
		/* get device active port numbers
		This is the OID that shows the mac address as the index and the port as the value*/
		$port_numbers = xform_stripped_oid('.1.3.6.1.2.1.17.7.1.2.2.1.2', $device, $snmp_readstring);

		/* get the ignore ports list from device */
		$ignore_ports = port_list_to_array($device['ignorePorts']);

		/* get the bridge root port so we don't capture active ports on it */
		$bridge_root_port = @cacti_snmp_get($device['hostname'], $snmp_readstring,
			'.1.3.6.1.2.1.17.2.7.0', $device['snmp_version'],
			$device['snmp_username'], $device['snmp_password'],
			$device['snmp_auth_protocol'], $device['snmp_priv_passphrase'],
			$device['snmp_priv_protocol'], $device['snmp_context'],
			$device['snmp_port'], $device['snmp_timeout'], $device['snmp_retries']);

		/* determine user ports for this device and transfer user ports to
		   a new array.
		*/
		$i = 0;
		if (cacti_sizeof($port_numbers)) {
			foreach ($port_numbers as $key => $port_number) {
				if (($highPort == 0) ||
					(($port_number >= $lowPort) &&
					($port_number <= $highPort) &&
					($bridge_root_port != $port_number))) {

					if (!in_array($port_number, $ignore_ports)) {
						if ((@$port_status[$key] == '3') || (@$port_status[$key] == '5')) {
							$port_key_array[$i]['key'] = $key;
							$port_key_array[$i]['port_number'] = $port_number;

							$i++;
						}
					}
				}
			}
		}

		/* compare the user ports to the brige port data, store additional
		   relevant data about the port.
		*/
		$i = 0;
		if (cacti_sizeof($port_key_array)) {
			foreach ($port_key_array as $port_key) {
				/* map bridge port to interface port and check type */
				if ($port_key['port_number'] > 0) {
					if (cacti_sizeof($bridgePortIfIndexes) != 0) {
						$brPortIfIndex = @$bridgePortIfIndexes[$port_key['port_number']];
						$brPortIfType = @$ifInterfaces[$brPortIfIndex]['ifType'];
					} else {
						$brPortIfIndex = $port_key['port_number'];
						$brPortIfType = @$ifInterfaces[$port_key['port_number']]['ifType'];
					}

					if ((($brPortIfType >= 6) && ($brPortIfType <= 9)) || ($brPortIfType == 71)) {
						/* set some defaults  */
						$new_port_key_array[$i]['vlan_id'] = 'N/A';
						$new_port_key_array[$i]['vlan_name'] = 'N/A';
						$new_port_key_array[$i]['mac_address'] = 'NOT USER';
						$new_port_key_array[$i]['port_number'] = 'NOT USER';
						$new_port_key_array[$i]['port_name'] = 'N/A';

						/* now set the real data */
						$new_port_key_array[$i]['key'] = $port_key['key'];
						$new_port_key_array[$i]['port_number'] = $port_key['port_number'];
						$new_port_key_array[$i]['port_name'] = $ifInterfaces[$port_key['port_number']]['ifAlias'];
						$i++;
					}
				}
			}
		}
		mactrack_debug('Port number information collected.');

		/* map mac address */
		/* only continue if there were user ports defined */
		if (cacti_sizeof($new_port_key_array)) {
			foreach ($new_port_key_array as $key => $port_mac) {
				$new_port_key_array[$key]['mac_address'] = dell_mac_address_convert($port_mac['key']);
				mactrack_debug('INDEX: ' . $key . ' MAC ADDRESS: ' . $new_port_key_array[$key]['mac_address']);
			}

			/* Map Vlan names to pvid's */
			$vlan_names = xform_stripped_oid('.1.3.6.1.2.1.17.7.1.4.3.1.1', $device, $snmp_readstring);


			/* map pvid's to ports with vlan names*/
			if (cacti_sizeof($new_port_key_array)) {
				foreach ($new_port_key_array as $key => $port){
					$temp_array = explode('.', $port['key']);
					$new_port_key_array[$key]['vlan_id'] = $temp_array[0];
					$new_port_key_array[$key]['vlan_name'] = @$vlan_names[$new_port_key_array[$key]['vlan_id']];
				}
			}
			mactrack_debug('Port mac address information collected.');
		} else {
			mactrack_debug('No user ports on this network.');
		}
	} else {
		mactrack_debug('No user ports on this network.');
	}

	if ($store_to_db) {
		if ($ports_active <= 0) {
			$device['last_runmessage'] = 'Data collection completed ok';
		} elseif (cacti_sizeof($new_port_key_array) > 0) {
			$device['last_runmessage'] = 'Data collection completed ok';
			$device['macs_active'] = cacti_sizeof($new_port_key_array);
			db_store_device_port_results($device, $new_port_key_array, $scan_date);
		} else {
			$device['last_runmessage'] = 'WARNING: Poller did not find active ports on this device.';
		}
	} else {
		return $new_port_key_array;
	}
}

