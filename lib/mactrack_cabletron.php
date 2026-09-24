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

// register this functions scanning functions
$mactrack_scanning_functions ??= [];
array_push($mactrack_scanning_functions, 'get_cabletron_switch_ports');
array_push($mactrack_scanning_functions, 'get_repeater_rev4_ports');

/**
 * SNMP-scans a Cabletron switch for its port and MAC address table
 * data, populating $device with counts and details. Detects whether
 * the device is running SecureFast (via a marker OID) and delegates
 * to get_base_sfps_ports() or the standard
 * get_base_dot1dTpFdbEntry_ports() accordingly. Registered in
 * $mactrack_scanning_functions for dispatch by the MacTrack poller
 * against devices of this vendor's device type.
 *
 * @param array $site     The site record the device belongs to.
 * @param array &$device  The device record being scanned; updated in
 *                        place with port/MAC scan results.
 * @param int   $lowPort  Lowest port number to include in the scan.
 * @param int   $highPort Highest port number to include in the scan.
 *
 * @return array The updated $device record.
 *
 * @global bool   $debug     Whether debug output is enabled.
 * @global string $scan_date The current scan timestamp.
 */
function get_cabletron_switch_ports($site, &$device, $lowPort, $highPort) {
	global $debug, $scan_date;

	// initialize port counters
	$device['ports_total']  = 0;
	$device['ports_active'] = 0;
	$device['ports_trunk']  = 0;
	$device['vlans_total']  = 0;

	// get the ifIndexes for the device
	$ifIndexes = xform_standard_indexed_data('.1.3.6.1.2.1.2.2.1.1', $device);
	mactrack_debug('ifIndexes data collection complete');

	// get and store the interfaces table
	$ifInterfaces = build_InterfacesTable($device, $ifIndexes, false, false);

	$securefast_marker = @cacti_snmp_get($device['hostname'], $device['snmp_readstring'],
		'.1.3.6.1.4.1.52.4.2.4.2.1.1.1.1.1.1.1', $device['snmp_version'],
		$device['snmp_username'], $device['snmp_password'], $device['snmp_auth_protocol'],
		$device['snmp_priv_passphrase'], $device['snmp_priv_protocol'],
		$device['snmp_context'], $device['snmp_port'], $device['snmp_timeout'], $device['snmp_retries']);

	mactrack_debug('Cabletron securefast marker obtained');

	if (empty($securefast_marker)) {
		get_base_dot1dTpFdbEntry_ports($site, $device, $ifInterfaces, '', true, $lowPort, $highPort);
	} else {
		get_base_sfps_ports($site, $device, $ifInterfaces, '', true, $lowPort, $highPort);
	}

	return $device;
}

/**
 * Retrieves port-to-MAC-address association data for a Cabletron
 * switch running SecureFast (SFPS), using the SecureFast-specific
 * bridge/MAC table OIDs rather than the standard dot1d bridge-port
 * table, optionally storing results to the database.
 *
 * @param array $site            The site record the device belongs to.
 * @param array &$device         The device record being scanned.
 * @param array &$ifInterfaces   The device's built interfaces table
 *                               (from build_InterfacesTable()).
 * @param string $snmp_readstring Unused parameter; retained for
 *                               signature parity with other vendor
 *                               port-collection functions.
 * @param bool  $store_to_db     Whether to persist the collected port
 *                               results to the database; when false,
 *                               the port results array is returned
 *                               instead.
 * @param int   $lowPort         Lowest port number to include in the
 *                               scan.
 * @param int   $highPort        Highest port number to include in the
 *                               scan.
 *
 * @return array|void The collected port results array when
 *                     $store_to_db is false; otherwise no explicit
 *                     return value ($device is updated in place).
 *
 * @global bool   $debug     Whether debug output is enabled.
 * @global string $scan_date The current scan timestamp.
 */
function get_base_sfps_ports($site, &$device, &$ifInterfaces, $snmp_readstring, $store_to_db, $lowPort, $highPort) {
	global $debug, $scan_date;

	// initialize variables
	$port_number  = 0;
	$ports_active = 0;
	$ports_total  = 0;

	// get the operational status of the ports
	$active_ports_array = xform_standard_indexed_data('.1.3.6.1.2.1.2.2.1.8', $device);
	$indexes            = array_keys($active_ports_array);

	// get the ignore ports list
	$ignore_ports = port_list_to_array($device['ignorePorts']);

	$i = 0;

	if (cacti_sizeof($active_ports_array)) {
		foreach ($active_ports_array as $port_info) {
			if (($ifInterfaces[$indexes[$i]]['ifType'] >= 6) &&
				($ifInterfaces[$indexes[$i]]['ifType'] <= 9)) {
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
		$device['ports_total']  = $ports_total;
	}

	// now obtain securefast port information
	$sfps_A_ports         = xform_indexed_data('.1.3.6.1.4.1.52.4.2.4.2.2.3.6.1.1.6', $device, 3);
	$sfps_A_mac_addresses = xform_indexed_data('.1.3.6.1.4.1.52.4.2.4.2.2.3.6.1.1.8', $device, 3, true);

	$sfps_A_keys = array_keys($sfps_A_ports);
	$sfps_A_size = cacti_sizeof($sfps_A_ports);

	$j = 0;
	$i = 0;

	while ($j < $sfps_A_size) {
		$port_number = $sfps_A_ports[$sfps_A_keys[$j]];
		$mac_address = $sfps_A_mac_addresses[$sfps_A_keys[$j]];

		if (($port_number >= $lowPort) && ($port_number <= $highPort)) {
			if (!in_array($port_number, $ignore_ports, true)) {
				$temp_port_A_array[$i]['port_number'] = $port_number;
				$temp_port_A_array[$i]['mac_address'] = xform_mac_address($mac_address);
				$i++;
			}
		}
		$j++;
	}

	$j          = 0;
	$port_array = [];

	for ($i = 0; $i < cacti_sizeof($temp_port_A_array); $i++) {
		$port_array[$temp_port_A_array[$i]['port_number']]['vlan_id']     = 'N/A';
		$port_array[$temp_port_A_array[$i]['port_number']]['vlan_name']   = 'N/A';
		$port_array[$temp_port_A_array[$i]['port_number']]['port_name']   = 'N/A';
		$port_array[$temp_port_A_array[$i]['port_number']]['port_number'] = $temp_port_A_array[$i]['port_number'];
		$port_array[$temp_port_A_array[$i]['port_number']]['mac_address'] = $temp_port_A_array[$i]['mac_address'];
	}

	if ($store_to_db) {
		if (cacti_sizeof($port_array) > 0) {
			$device['last_runmessage'] = 'Data collection completed ok';
			$device['macs_active']     = cacti_sizeof($port_array);
			db_store_device_port_results($device, $port_array, $scan_date);
		} else {
			$device['last_runmessage'] = 'WARNING: Poller did not find active ports on this device.';
		}
	} else {
		return $port_array;
	}
}

/**
 * Cabletron SEHI repeaters are quite odd: they have potentially 5
 * distinct SNMP read strings, one for each of 5 agent structures. If
 * the read string for port information differs from the device's
 * primary sysObjectID read string, this probes each configured
 * candidate read string in turn to find the one that successfully
 * queries port data.
 *
 * @param array &$device The device record being probed.
 *
 * @return string The working SNMP read string for port data, or an
 *                empty string if none of the candidates worked.
 */
function get_repeater_snmp_readstring(&$device) {
	$active_ports = @cacti_snmp_get($device['hostname'], $device['snmp_readstring'],
		'.1.3.6.1.4.1.52.4.1.1.1.4.1.1.4.0', $device['snmp_version'],
		$device['snmp_username'], $device['snmp_password'],
		$device['snmp_auth_protocol'], $device['snmp_priv_passphrase'],
		$device['snmp_priv_protocol'], $device['snmp_context'],
		$device['snmp_port'], $device['snmp_timeout'], $device['snmp_retries']);

	if ($active_ports != '') {
		mactrack_debug('Repeater readstring is: ' . $device['snmp_readstring']);

		return $device['snmp_readstring'];
	} else {
		// loop through the default and then other common for the correct answer
		$read_strings = explode(':', $device['snmp_readstrings']);

		if (cacti_sizeof($read_strings)) {
			foreach ($read_strings as $snmp_readstring) {
				$active_ports = @cacti_snmp_get($device['hostname'], $snmp_readstring,
					'.1.3.6.1.4.1.52.4.1.1.1.4.1.1.4.0', $device['snmp_version'],
					$device['snmp_username'], $device['snmp_password'],
					$device['snmp_auth_protocol'], $device['snmp_priv_passphrase'],
					$device['snmp_priv_protocol'], $device['snmp_context'],
					$device['snmp_port'], $device['snmp_timeout'], $device['snmp_retries']);

				if ($active_ports != '') {
					mactrack_debug('Repeater readstring is: ' . $snmp_readstring);

					return $snmp_readstring;
				}
			}
		}
	}

	return '';
}

/**
 * SNMP-scans a Cabletron rev4 repeater for its active/total port
 * counts, first resolving the correct SNMP read string via
 * get_repeater_snmp_readstring() since these devices may use a
 * different read string per agent structure. Registered in
 * $mactrack_scanning_functions for dispatch by the MacTrack poller
 * against devices of this vendor's device type.
 *
 * @param array $site     The site record the device belongs to.
 * @param array &$device  The device record being scanned; updated in
 *                        place with port count results.
 * @param int   $lowPort  Lowest port number to include in the scan.
 * @param int   $highPort Highest port number to include in the scan.
 *
 * @return array The updated $device record.
 *
 * @global bool   $debug     Whether debug output is enabled.
 * @global string $scan_date The current scan timestamp.
 */
function get_repeater_rev4_ports($site, &$device, $lowPort, $highPort) {
	global $debug, $scan_date;

	$snmp_readstring = get_repeater_snmp_readstring($device);

	if ($snmp_readstring != '') {
		$ports_active = @cacti_snmp_get($device['hostname'], $snmp_readstring,
			'.1.3.6.1.4.1.52.4.1.1.1.4.1.1.5.0', $device['snmp_version'],
			$device['snmp_username'], $device['snmp_password'],
			$device['snmp_auth_protocol'], $device['snmp_priv_passphrase'],
			$device['snmp_priv_protocol'], $device['snmp_context'],
			$device['snmp_port'], $device['snmp_timeout'], $device['snmp_retries']) - 1;

		$ports_total = @cacti_snmp_get($device['hostname'], $snmp_readstring,
			'.1.3.6.1.4.1.52.4.1.1.1.4.1.1.4.0', $device['snmp_version'],
			$device['snmp_username'], $device['snmp_password'],
			$device['snmp_auth_protocol'], $device['snmp_priv_passphrase'],
			$device['snmp_priv_protocol'], $device['snmp_context'],
			$device['snmp_port'], $device['snmp_timeout'], $device['snmp_retries']) - 1;

		// get the ignore ports list
		$ignore_ports = port_list_to_array($device['ignorePorts']);

		mactrack_debug('INFO: HOST: ' . $device['hostname'] . ', TYPE: ' . substr($device['snmp_sysDescr'],0,40) . ', TOTAL PORTS: ' . $ports_total . ', ACTIVE PORTS: ' . $ports_active);

		$device['vlans_total'] = 0;
		$device['ports_total'] = $ports_total;

		if ($ports_active >= 0) {
			$device['ports_active'] = $ports_active;
		} else {
			$device['ports_active'] = 0;
		}

		if ($device['snmp_version'] == 2) {
			$snmp_version = '2c';
		} else {
			$snmp_version = $device['snmp_version'];
		}

		$port_keys          = [];
		$return_array       = [];
		$new_port_key_array = [];
		$port_number        = 0;
		$nextOID            = '.1.3.6.1.4.1.52.4.1.1.1.4.1.5.2.1.2';
		$to                 = ceil($device['snmp_timeout'] / 1000);

		$i             = 0;
		$previous_port = 0;

		while (1) {
			$exec_string = trim(cacti_escapeshellcmd(read_config_option('path_snmpgetnext')) .
				' -c ' . cacti_escapeshellarg($snmp_readstring) .
				' -OnUQ -v ' . cacti_escapeshellarg($snmp_version) .
				' -r ' . intval($device['snmp_retries']) .
				' -t ' . intval($to) . ' ' .
				cacti_escapeshellarg($device['hostname'] . ':' . intval($device['snmp_port'])) . ' ' .
				cacti_escapeshellarg($nextOID));

			exec($exec_string, $return_array, $return_code);

			[$nextOID, $port_number] = explode('=', $return_array[$i]);

			if ($port_number < $previous_port) {
				break;
			}

			if (($port_number <= $highPort) && ($port_number >= $lowPort)) {
				if (!in_array($port_number, $ignore_ports, true)) {
					// set defaults for devices in case they don't have/support vlans
					$new_port_key_array[$i]['vlan_id']   = 'N/A';
					$new_port_key_array[$i]['vlan_name'] = 'N/A';
					$new_port_key_array[$i]['port_name'] = 'N/A';

					$new_port_key_array[$i]['key']         = trim(substr($nextOID,36));
					$new_port_key_array[$i]['port_number'] = trim(strtr($port_number,' ',''));
				}

				$previous_port = trim(strtr($port_number,' ',''));
			} else {
				break;
			}

			mactrack_debug('CMD: ' . $exec_string . ', PORT: ' . $port_number);
			$i++;
			$port_number = '';
		}

		if (cacti_sizeof($new_port_key_array) > 0) {
			// map mac address
			$i = 0;

			foreach ($new_port_key_array as $port_key) {
				$OID = '.1.3.6.1.4.1.52.4.1.1.1.4.1.5.2.1.1.' . $port_key['key'];

				$mac_address = @cacti_snmp_get($device['hostname'], $snmp_readstring,
					$OID, $device['snmp_version'], $device['snmp_username'],
					$device['snmp_password'], $device['snmp_auth_protocol'],
					$device['snmp_priv_passphrase'], $device['snmp_priv_protocol'],
					$device['snmp_context'], $device['snmp_port'], $device['snmp_timeout'], $device['snmp_retries']);

				$new_port_key_array[$i]['mac_address'] = xform_mac_address($mac_address);

				mactrack_debug('OID: ' . $OID . ', MAC ADDRESS: ' . $new_port_key_array[$i]['mac_address']);
				$i++;
			}

			$device['last_runmessage'] = 'Data collection completed ok';
		} else {
			mactrack_debug('INFO: The following device has no active ports: ' . $site . '/' . $device['hostname']);

			$device['last_runmessage'] = 'Data collection completed ok';
		}
	} else {
		mactrack_debug('ERROR: Could not determine snmp_readstring for host: ' . $site . '/' . $device['hostname']);

		$device['snmp_status']     = HOST_ERROR;
		$device['last_runmessage'] = 'ERROR: Could not determine snmp_readstring for host.';
	}

	$device['ports_active'] = $ports_active;
	$device['macs_active']  = cacti_sizeof($new_port_key_array);
	db_store_device_port_results($device, $new_port_key_array, $scan_date);

	return $device;
}
