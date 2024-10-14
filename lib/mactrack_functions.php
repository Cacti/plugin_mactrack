<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2024 The Cacti Group                                 |
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

/* register these scanning functions */
global $mactrack_scanning_functions;
if (!isset($mactrack_scanning_functions)) { $mactrack_scanning_functions = array(); }
array_push($mactrack_scanning_functions, 'get_generic_dot1q_switch_ports', 'get_generic_switch_ports', 'get_generic_wireless_ports');

global $mactrack_scanning_functions_ip;
if (!isset($mactrack_scanning_functions_ip)) { $mactrack_scanning_functions_ip = array(); }
array_push($mactrack_scanning_functions_ip, 'get_standard_arp_table', 'get_netscreen_arp_table');

global $mactrack_device_status;
if (!isset($mactrack_device_status)) {
	$mactrack_device_status = array(
		1 => __('Idle', 'mactrack'),
		2 => __('Running', 'mactrack'),
		3 => __('No method', 'mactrack'),
		4 => __('Authentication Success', 'mactrack'),
		5 => __('Authentication Failed', 'mactrack'),
		6 => __('Authorization Success', 'mactrack'),
		7 => __('Authorization Failed', 'mactrack')
	);
}

function mactrack_debug($message) {
	global $debug, $web, $config;

	$print_output=!(isset($web) && $web);
	if (isset($web) && $web && is_string($message) && !substr_count($message, 'SQL')) {
		print($message . '<br>');
	}

	$debug_level=POLLER_VERBOSITY_HIGH;
	if (substr_count($message, 'ERROR:') || $debug) {
		$debug_level=POLLER_VERBOSITY_LOW;
	}

	if (!preg_match('~(\w): .*~',$message)) {
		$message = 'DEBUG: ' . $message;
	}

	cacti_log($message, $print_output, 'MACTRACK', $debug_level);
}

function mactrack_rebuild_scanning_funcs() {
	global $config, $mactrack_scanning_functions_ip, $mactrack_scanning_functions, $mactrack_scanning_functions_dot1x;

	if (defined('CACTI_BASE_PATH')) {
		$config['base_path'] = CACTI_BASE_PATH;
	}

	db_execute('TRUNCATE TABLE mac_track_scanning_functions');

	include_once($config['base_path'] . '/plugins/mactrack/lib/mactrack_vendors.php');

	/* store the list of registered mactrack scanning functions */
	db_execute("REPLACE INTO mac_track_scanning_functions
		(scanning_function,type)
		VALUES ('Not Applicable - Router', '1')");

	if (isset($mactrack_scanning_functions)) {
		foreach($mactrack_scanning_functions as $scanning_function) {
			db_execute_prepared('REPLACE INTO mac_track_scanning_functions
				(scanning_function, type)
				VALUES (?, ?)',
				array($scanning_function, 1));
		}
	}

	db_execute("REPLACE INTO mac_track_scanning_functions
		(scanning_function,type)
		VALUES ('Not Applicable - Switch/Hub', '2')");

	if (isset($mactrack_scanning_functions_ip)) {
		foreach($mactrack_scanning_functions_ip as $scanning_function) {
			db_execute_prepared('REPLACE INTO mac_track_scanning_functions
				(scanning_function, type)
				VALUES (?, ?)',
				array($scanning_function, 2));
		}
	}

	db_execute("REPLACE INTO mac_track_scanning_functions
		(scanning_function,type)
		VALUES ('Not Applicable', '3')");

	if (isset($mactrack_scanning_functions_dot1x)) {
		foreach($mactrack_scanning_functions_dot1x as $scanning_function) {
			db_execute_prepared('REPLACE INTO mac_track_scanning_functions
				(scanning_function, type)
				VALUES (?, ?)',
				array($scanning_function, 3));
		}
	}
}

function mactrack_strip_alpha($string = '') {
	return trim($string, 'abcdefghijklmnopqrstuvwzyzABCDEFGHIJKLMNOPQRSTUVWXYZ()[]{}');
}

function mactrack_check_user_realm($realm_id) {
	return is_realm_allowed($realm_id);
}

/* valid_snmp_device - This function validates that the device is reachable via snmp.
  It first attempts	to utilize the default snmp readstring.  If it's not valid, it
  attempts to find the correct read string and then updates several system
  information variable. it returns the status	of the host (up=true, down=false)
 */
function valid_snmp_device(&$device) {
	global $config;

	/* initialize variable */
	$host_up = false;
	$device['snmp_status'] = HOST_DOWN;

	/* force php to return numeric oid's */
	cacti_oid_numeric_format();

	/* if the first read did not work, loop until found */
	$snmp_sysObjectID = @cacti_snmp_get($device['hostname'], $device['snmp_readstring'],
		'.1.3.6.1.2.1.1.2.0', $device['snmp_version'],
		$device['snmp_username'], $device['snmp_password'],
		$device['snmp_auth_protocol'], $device['snmp_priv_passphrase'],
		$device['snmp_priv_protocol'], $device['snmp_context'],
		$device['snmp_port'], $device['snmp_timeout'], $device['snmp_retries']);

	$snmp_sysObjectID = str_replace('enterprises', '.1.3.6.1.4.1', $snmp_sysObjectID);
	$snmp_sysObjectID = str_replace('OID: ', '', $snmp_sysObjectID);
	$snmp_sysObjectID = str_replace('.iso', '.1', $snmp_sysObjectID);

	if ($snmp_sysObjectID != '' &&
		$snmp_sysObjectID != 'U' &&
		(!substr_count($snmp_sysObjectID, 'No Such Object')) &&
		(!substr_count($snmp_sysObjectID, 'Error In'))) {
		$snmp_sysObjectID = trim(str_replace('"','', $snmp_sysObjectID));
		$host_up = true;
		$device['snmp_status'] = HOST_UP;
	} else {
		/* loop through the default and then other common for the correct answer */
		$snmp_options = db_fetch_assoc_prepared('SELECT * from mac_track_snmp_items WHERE snmp_id = ? ORDER BY sequence', array($device['snmp_options']));

		if (cacti_sizeof($snmp_options)) {
			foreach($snmp_options as $snmp_option) {
				# update $device for later db update via db_update_device_status
				$device['snmp_readstring'] = $snmp_option['snmp_readstring'];
				$device['snmp_version'] = $snmp_option['snmp_version'];
				$device['snmp_username'] = $snmp_option['snmp_username'];
				$device['snmp_password'] = $snmp_option['snmp_password'];
				$device['snmp_auth_protocol'] = $snmp_option['snmp_auth_protocol'];
				$device['snmp_priv_passphrase'] = $snmp_option['snmp_priv_passphrase'];
				$device['snmp_priv_protocol'] = $snmp_option['snmp_priv_protocol'];
				$device['snmp_context'] = $snmp_option['snmp_context'];
				$device['snmp_port'] = $snmp_option['snmp_port'];
				$device['snmp_timeout'] = $snmp_option['snmp_timeout'];
				$device['snmp_retries'] = $snmp_option['snmp_retries'];

				$snmp_sysObjectID = @cacti_snmp_get($device['hostname'], $device['snmp_readstring'],
					'.1.3.6.1.2.1.1.2.0', $device['snmp_version'],
					$device['snmp_username'], $device['snmp_password'],
					$device['snmp_auth_protocol'], $device['snmp_priv_passphrase'],
					$device['snmp_priv_protocol'], $device['snmp_context'],
					$device['snmp_port'], $device['snmp_timeout'],
					$device['snmp_retries']);

				$snmp_sysObjectID = str_replace('enterprises', '.1.3.6.1.4.1', $snmp_sysObjectID);
				$snmp_sysObjectID = str_replace('OID: ', '', $snmp_sysObjectID);
				$snmp_sysObjectID = str_replace('.iso', '.1', $snmp_sysObjectID);

				if ($snmp_sysObjectID != '' &&
					$snmp_sysObjectID != 'U' &&
					(!substr_count($snmp_sysObjectID, 'No Such Object')) &&
					(!substr_count($snmp_sysObjectID, 'Error In'))) {
					$snmp_sysObjectID = trim(str_replace("'", '', $snmp_sysObjectID));
					$device['snmp_readstring'] = $snmp_option['snmp_readstring'];
					$device['snmp_status'] = HOST_UP;
					$host_up = true;
					# update cacti device, if required
					sync_mactrack_to_cacti($device);
					# update to mactrack itself is done by db_update_device_status in mactrack_scanner.php
					# TODO: if db_update_device_status would use api_mactrack_device_save, there would be no need to call sync_mactrack_to_cacti here
					# but currently the parameter set doesn't match
					mactrack_debug('Result found on Option Set (' . $snmp_option['snmp_id'] . ') Sequence (' . $snmp_option['sequence'] . '): ' . $snmp_sysObjectID);
					break; # no need to continue if we have a match
				} else {
					$device['snmp_status'] = HOST_DOWN;
					$host_up = false;
				}
			}
		}
	}

	if ($host_up) {
		$device['snmp_sysObjectID'] = $snmp_sysObjectID;

		/* get system name */
		$snmp_sysName = @cacti_snmp_get($device['hostname'], $device['snmp_readstring'],
			'.1.3.6.1.2.1.1.5.0', $device['snmp_version'],
			$device['snmp_username'], $device['snmp_password'],
			$device['snmp_auth_protocol'], $device['snmp_priv_passphrase'],
			$device['snmp_priv_protocol'], $device['snmp_context'],
			$device['snmp_port'], $device['snmp_timeout'], $device['snmp_retries']);

		if ($snmp_sysName != '') {
			$snmp_sysName = trim(strtr($snmp_sysName,'"',' '));
			$device['snmp_sysName'] = $snmp_sysName;
		}

		/* get system location */
		$snmp_sysLocation = @cacti_snmp_get($device['hostname'], $device['snmp_readstring'],
			'.1.3.6.1.2.1.1.6.0', $device['snmp_version'],
			$device['snmp_username'], $device['snmp_password'],
			$device['snmp_auth_protocol'], $device['snmp_priv_passphrase'],
			$device['snmp_priv_protocol'], $device['snmp_context'],
			$device['snmp_port'], $device['snmp_timeout'], $device['snmp_retries']);

		if ($snmp_sysLocation != '') {
			$snmp_sysLocation = trim(strtr($snmp_sysLocation,'"',' '));
			$device['snmp_sysLocation'] = $snmp_sysLocation;
		}

		/* get system contact */
		$snmp_sysContact = @cacti_snmp_get($device['hostname'], $device['snmp_readstring'],
			'.1.3.6.1.2.1.1.4.0', $device['snmp_version'],
			$device['snmp_username'], $device['snmp_password'],
			$device['snmp_auth_protocol'], $device['snmp_priv_passphrase'],
			$device['snmp_priv_protocol'], $device['snmp_context'],
			$device['snmp_port'], $device['snmp_timeout'], $device['snmp_retries']);

		if ($snmp_sysContact != '') {
			$snmp_sysContact = trim(strtr($snmp_sysContact,'"',' '));
			$device['snmp_sysContact'] = $snmp_sysContact;
		}

		/* get system description */
		$snmp_sysDescr = @cacti_snmp_get($device['hostname'], $device['snmp_readstring'],
			'.1.3.6.1.2.1.1.1.0', $device['snmp_version'],
			$device['snmp_username'], $device['snmp_password'],
			$device['snmp_auth_protocol'], $device['snmp_priv_passphrase'],
			$device['snmp_priv_protocol'], $device['snmp_context'],
			$device['snmp_port'], $device['snmp_timeout'], $device['snmp_retries']);

		if ($snmp_sysDescr != '') {
			$snmp_sysDescr = trim(strtr($snmp_sysDescr,'"',' '));
			$device['snmp_sysDescr'] = $snmp_sysDescr;
		}

		/* get system uptime */
		$snmp_sysUptime = @cacti_snmp_get($device['hostname'], $device['snmp_readstring'],
			'.1.3.6.1.2.1.1.3.0', $device['snmp_version'],
			$device['snmp_username'], $device['snmp_password'],
			$device['snmp_auth_protocol'], $device['snmp_priv_passphrase'],
			$device['snmp_priv_protocol'], $device['snmp_context'],
			$device['snmp_port'], $device['snmp_timeout'], $device['snmp_retries']);

		if ($snmp_sysUptime != '') {
			$snmp_sysUptime = trim(strtr($snmp_sysUptime,'"',' '));
			$device['snmp_sysUptime'] = $snmp_sysUptime;
		}
	}

	return $host_up;
}

/*	find_scanning_function - This function scans the mac_track_device_type database
  for a valid scanning function and then returns an array with the current device
  type and it's characteristics for the main mac_track_scanner function to call.
*/
function find_scanning_function(&$device, &$device_types) {
	/* scan all device_types to determine the function to call */
	if (cacti_sizeof($device_types)) {
		foreach($device_types as $device_type) {
			/* by default none match */
			$sysDescr_match = false;
			$sysObjectID_match = false;

			/* search for a matching snmp_sysDescr */
			if (substr_count($device_type['sysDescr_match'], '*') > 0) {
				/* need to assume mixed string */
				$parts = explode('*', $device_type['sysDescr_match']);
				if (cacti_sizeof($parts)) {
					foreach($parts as $part) {
						if ($part != '') {
							if (substr_count($device['snmp_sysDescr'],$part) > 0) {
								$sysDescr_match = true;
							} else {
								$sysDescr_match = false;
							}
						}
					}
				}
			} else {
				if ($device_type['sysDescr_match'] == '') {
					$sysDescr_match = true;
				} else {
					if (substr_count($device['snmp_sysDescr'], $device_type['sysDescr_match'])) {
						$sysDescr_match = true;
					} else {
						$sysDescr_match = false;
					}
				}
			}

			/* search for a matching snmp_sysObjectID*/
			/* need to assume mixed string */
			if (substr_count($device_type['sysObjectID_match'], '*') > 0) {
				$parts = explode('*', $device_type['sysObjectID_match']);
				if (cacti_sizeof($parts)) {
					foreach($parts as $part) {
						if ($part != '') {
							if (substr_count($device['snmp_sysObjectID'],$part) > 0) {
								$sysObjectID_match = true;
							} else {
								$sysObjectID_match = false;
							}
						}
					}
				}
			} else {
				if ($device_type['sysObjectID_match'] == '') {
					$sysObjectID_match = true;
				} else {
					if (substr_count($device['snmp_sysObjectID'], $device_type['sysObjectID_match'])) {
						$sysObjectID_match = true;
					} else {
						$sysObjectID_match = false;
					}
				}
			}

			if (($sysObjectID_match == true) && ($sysDescr_match == true)) {
				$device['device_type_id'] = $device_type['device_type_id'];
				$device['scan_type'] = $device_type['device_type'];
				return $device_type;
			}
		}
	}

	return array();
}

/*	port_list_to_array - Takes a text list of ports and builds a trimmed array of
  the resulting array.  Returns the array
*/
function port_list_to_array($port_list, $delimiter = ':') {
	$port_array = array();

	if (read_config_option('mt_ignorePorts_delim') == '-1') {
		/* find the delimiter */
		$t1 = cacti_sizeof(explode(':', $port_list));
		$t2 = cacti_sizeof(explode('|', $port_list));
		$t3 = cacti_sizeof(explode(' ', $port_list));

		if ($t1 > $t2 && $t1 > $t3) {
			$delimiter = ':';
		} elseif ($t2 > $t1 && $t2 > $t3) {
			$delimiter = '|';
		} elseif ($t3 > $t1 && $t3 > $t2) {
			$delimiter = ' ';
		}
	} else {
		$delimiter = read_config_option('mt_ignorePorts_delim');
	}

	$ports = explode($delimiter, $port_list);

	if (cacti_sizeof($ports)) {
		foreach ($ports as $port) {
			array_push($port_array, trim($port));
		}
	}

	return $port_array;
}

/*	get_standard_arp_table - This function reads a devices ARP table for a site and stores
  the IP address and MAC address combinations in the mac_track_ips table.
*/
function get_standard_arp_table($site, &$device) {
	global $debug, $scan_date;

	$atEntries   = array();

	/* get the atifIndexes for the device */
	$atifIndexes = xform_stripped_oid('.1.3.6.1.2.1.3.1.1.1', $device);
	if (cacti_sizeof($atifIndexes)) {
		mactrack_debug('atifIndexes data collection complete');
		$atPhysAddress = xform_stripped_oid('.1.3.6.1.2.1.3.1.1.2', $device);
		mactrack_debug('atPhysAddress data collection complete');
		$atNetAddress  = xform_stripped_oid('.1.3.6.1.2.1.3.1.1.3', $device);
		mactrack_debug('atNetAddress data collection complete');
	} else {
		/* second attempt for Force10 Gear */
		$atifIndexes   = xform_stripped_oid('.1.3.6.1.2.1.4.22.1.1', $device);
		mactrack_debug('atifIndexes data collection complete');
		$atPhysAddress = xform_stripped_oid('.1.3.6.1.2.1.4.22.1.2', $device, '', true);
		mactrack_debug('atPhysAddress data collection complete');
		$atNetAddress = xform_stripped_oid('.1.3.6.1.2.1.4.22.1.3', $device);
		mactrack_debug('atNetAddress data collection complete');
	}

	$atifNames = xform_standard_indexed_data('.1.3.6.1.2.1.31.1.1.1.1', $device);
	mactrack_debug('ifNames data collection complete. \'' . cacti_sizeof($atifNames) . '\' rows found!');

	/* convert the mac address if necessary */
	$keys = array_keys($atPhysAddress);
	$i = 0;
	if (cacti_sizeof($atPhysAddress)) {
		foreach($atPhysAddress as $atAddress) {
			$atPhysAddress[$keys[$i]] = xform_mac_address($atAddress);
			$i++;
		}
	}
	mactrack_debug('atPhysAddress MAC Address Conversion Completed');

	/* get the ifNames for the device */
	$keys = array_keys($atifIndexes);
	$i = 0;
	if (cacti_sizeof($atifIndexes)) {
		foreach($atifIndexes as $atifIndex) {
			$atEntries[$i]['atifName'] = isset($atifNames[$atifIndex]) ? $atifNames[$atifIndex]:'';
			$atEntries[$i]['atPhysAddress'] = isset($atPhysAddress[$keys[$i]]) ? $atPhysAddress[$keys[$i]]:'';
			$atEntries[$i]['atNetAddress'] = isset($atNetAddress[$keys[$i]]) ? xform_net_address($atNetAddress[$keys[$i]]):'';
			$i++;
		}
	}
	mactrack_debug('atEntries assembly complete.');

	/* output details to database */
	if (cacti_sizeof($atEntries)) {
		foreach($atEntries as $atEntry) {
			/* check the mac_track_arp table if no IP address is found */
			if ($atEntry['atNetAddress'] == "") {
				$atEntry['atNetAddress'] = db_check_for_ip($atEntry['atPhysAddress']);
				mactrack_debug('atNetAddress ****:' . $atEntry['atPhysAddress'] . '(' . $atEntry['atNetAddress'] . ')');
			}

			db_execute_prepared('REPLACE INTO mac_track_ips
				(site_id, device_id, hostname, device_name, port_number, mac_address, ip_address, scan_date)
				VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
				array(
					$device['site_id'],
					$device['device_id'],
					$device['hostname'],
					$device['device_name'],
					$atEntry['atifName'],
					$atEntry['atPhysAddress'],
					$atEntry['atNetAddress'],
					$scan_date
				)
			);
		}
	}

	/* save ip information for the device */
	$device['ips_total'] = cacti_sizeof($atEntries);
	db_execute_prepared('UPDATE mac_track_devices
		SET ips_total = ?
		WHERE device_id = ?',
		array($device['ips_total'], $device['device_id']));

	mactrack_debug('HOST: ' . $device['hostname'] . ', IP address information collection complete');
}

/*	build_InterfacesTable - This is a basic function that will scan Interfaces table
  and return data.  It also stores data in the mac_track_interfaces table.  Some of the
  data is also used for scanning purposes.
*/
function build_InterfacesTable(&$device, &$ifIndexes, $getLinkPorts = false, $getAlias = false) {
	/* initialize the interfaces array */
	$ifInterfaces = array();

	/* get the ifIndexes for the device */
	$ifIndexes = xform_standard_indexed_data('.1.3.6.1.2.1.2.2.1.1', $device);
	mactrack_debug('ifIndexes data collection complete. \'' . cacti_sizeof($ifIndexes) . '\' rows found!');

	$ifTypes = xform_standard_indexed_data('.1.3.6.1.2.1.2.2.1.3', $device);
	if (cacti_sizeof($ifTypes)) {
		foreach($ifTypes as $key => $value) {
			if (!is_numeric($value)) {
				$parts = explode('(', $value);
				$piece = $parts[1];
				$ifTypes[$key] = str_replace(')', '', trim($piece));
			}
		}
	}
	mactrack_debug('ifTypes data collection complete. \'' . cacti_sizeof($ifTypes) . '\' rows found!');

	$ifNames = xform_standard_indexed_data('.1.3.6.1.2.1.31.1.1.1.1', $device);
	mactrack_debug('ifNames data collection complete. \'' . cacti_sizeof($ifNames) . '\' rows found!');

	/* get ports names through use of ifAlias */
	if ($getAlias) {
		$ifAliases = xform_standard_indexed_data('.1.3.6.1.2.1.31.1.1.1.18', $device);
		mactrack_debug('ifAlias data collection complete. \'' . cacti_sizeof($ifAliases) . '\' rows found!');
	}

	/* get ports that happen to be link ports */
	if ($getLinkPorts) {
		$link_ports = get_link_port_status($device);
		mactrack_debug("ipAddrTable scanning for link ports data collection complete. '" . cacti_sizeof($link_ports) . "' rows found!");
	}

	/* required only for interfaces table */
	$db_data = db_fetch_assoc("SELECT * FROM mac_track_interfaces WHERE device_id='" . $device["device_id"] . "' ORDER BY ifIndex");

	if (cacti_sizeof($db_data)) {
		foreach($db_data as $interface) {
			$db_interface[$interface["ifIndex"]] = $interface;
		}
	}

	/* mark all interfaces as not present */
	db_execute_prepared('UPDATE mac_track_interfaces
		SET present=0
		WHERE device_id= ?',
		array($device['device_id']));

	$insert_prefix = 'INSERT INTO mac_track_interfaces (site_id, device_id, sysUptime,
		ifIndex, ifType, ifName, ifAlias, linkPort, vlan_id,
		vlan_name, vlan_trunk_status, ifSpeed, ifHighSpeed, ifDuplex,
		ifDescr, ifMtu, ifPhysAddress, ifAdminStatus, ifOperStatus, ifLastChange,
		ifInOctets, ifOutOctets, ifHCInOctets, ifHCOutOctets, ifInUcastPkts, ifOutUcastPkts,
		ifInDiscards, ifInErrors, ifInUnknownProtos, ifOutDiscards, ifOutErrors,
		ifInMulticastPkts, ifOutMulticastPkts, ifInBroadcastPkts, ifOutBroadcastPkts,
		int_ifInOctets, int_ifOutOctets, int_ifHCInOctets, int_ifHCOutOctets,
		int_ifInUcastPkts, int_ifOutUcastPkts, int_ifInDiscards, int_ifInErrors,
		int_ifInUnknownProtos, int_ifOutDiscards, int_ifOutErrors, int_ifInMulticastPkts,
		int_ifOutMulticastPkts, int_ifInBroadcastPkts, int_ifOutBroadcastPkts,
		int_discards_present, int_errors_present, last_down_time, last_up_time,
		stateChanges, present) VALUES ';

	$insert_suffix = ' ON DUPLICATE KEY UPDATE
		sysUptime=VALUES(sysUptime),
		ifType=VALUES(ifType),
		ifName=VALUES(ifName),
		ifAlias=VALUES(ifAlias),
		linkPort=VALUES(linkPort),
		vlan_id=VALUES(vlan_id),
		vlan_name=VALUES(vlan_name),
		vlan_trunk_status=VALUES(vlan_trunk_status),
		ifSpeed=VALUES(ifSpeed),
		ifHighSpeed=VALUES(ifHighSpeed),
		ifDuplex=VALUES(ifDuplex),
		ifDescr=VALUES(ifDescr),
		ifMtu=VALUES(ifMtu),
		ifPhysAddress=VALUES(ifPhysAddress),
		ifAdminStatus=VALUES(ifAdminStatus),
		ifOperStatus=VALUES(ifOperStatus),
		ifLastChange=VALUES(ifLastChange),
		ifInOctets=VALUES(ifInOctets),
		ifOutOctets=VALUES(ifOutOctets),
		ifHCInOctets=VALUES(ifHCInOctets),
		ifHCOutOctets=VALUES(ifHCOutOctets),
		ifInUcastPkts=VALUES(ifInUcastPkts),
		ifOutUcastPkts=VALUES(ifOutUcastPkts),
		ifInDiscards=VALUES(ifInDiscards),
		ifInErrors=VALUES(ifInErrors),
		ifInUnknownProtos=VALUES(ifInUnknownProtos),
		ifOutDiscards=VALUES(ifOutDiscards),
		ifOutErrors=VALUES(ifOutErrors),
		ifInMulticastPkts=VALUES(ifInMulticastPkts),
		ifOutMulticastPkts=VALUES(ifOutMulticastPkts),
		ifInBroadcastPkts=VALUES(ifInBroadcastPkts),
		ifOutBroadcastPkts=VALUES(ifOutBroadcastPkts),
		int_ifInOctets=VALUES(int_ifInOctets),
		int_ifOutOctets=VALUES(int_ifOutOctets),
		int_ifHCInOctets=VALUES(int_ifHCInOctets),
		int_ifHCOutOctets=VALUES(int_ifHCOutOctets),
		int_ifInUcastPkts=VALUES(int_ifInUcastPkts),
		int_ifOutUcastPkts=VALUES(int_ifOutUcastPkts),
		int_ifInDiscards=VALUES(int_ifInDiscards),
		int_ifInErrors=VALUES(int_ifInErrors),
		int_ifInUnknownProtos=VALUES(int_ifInUnknownProtos),
		int_ifOutDiscards=VALUES(int_ifOutDiscards),
		int_ifOutErrors=VALUES(int_ifOutErrors),
		int_ifInMulticastPkts=VALUES(int_ifInMulticastPkts),
		int_ifOutMulticastPkts=VALUES(int_ifOutMulticastPkts),
		int_ifInBroadcastPkts=VALUES(int_ifInBroadcastPkts),
		int_ifOutBroadcastPkts=VALUES(int_ifOutBroadcastPkts),
		int_discards_present=VALUES(int_discards_present),
		int_errors_present=VALUES(int_errors_present),
		last_down_time=VALUES(last_down_time),
		last_up_time=VALUES(last_up_time),
		stateChanges=VALUES(stateChanges),
		present="1"';

	$insert_vals = '';

	$ifSpeed = xform_standard_indexed_data('.1.3.6.1.2.1.2.2.1.5', $device);
	mactrack_debug("ifSpeed data collection complete. '" . cacti_sizeof($ifSpeed) . "' rows found!");

	$ifHighSpeed = xform_standard_indexed_data('.1.3.6.1.2.1.31.1.1.1.15', $device);
	mactrack_debug("ifHighSpeed data collection complete. '" . cacti_sizeof($ifHighSpeed) . "' rows found!");

	$ifDuplex = xform_standard_indexed_data('.1.3.6.1.2.1.10.7.2.1.19', $device);
	mactrack_debug("ifDuplex data collection complete. '" . cacti_sizeof($ifDuplex) . "' rows found!");

	$ifDescr = xform_standard_indexed_data('.1.3.6.1.2.1.2.2.1.2', $device);
	mactrack_debug("ifDescr data collection complete. '" . cacti_sizeof($ifDescr) . "' rows found!");

	$ifMtu = xform_standard_indexed_data('.1.3.6.1.2.1.2.2.1.4', $device);
	mactrack_debug("ifMtu data collection complete. '" . cacti_sizeof($ifMtu) . "' rows found!");

	$ifPhysAddress = xform_standard_indexed_data('.1.3.6.1.2.1.2.2.1.6', $device, '', true);
	mactrack_debug("ifPhysAddress data collection complete. '" . cacti_sizeof($ifPhysAddress) . "' rows found!");

	$ifAdminStatus = xform_standard_indexed_data('.1.3.6.1.2.1.2.2.1.7', $device);
	if (cacti_sizeof($ifAdminStatus)) {
		foreach($ifAdminStatus as $key => $value) {
			if ((substr_count(strtolower($value), 'up')) || ($value == '1')) {
				$ifAdminStatus[$key] = 1;
			} else {
				$ifAdminStatus[$key] = 0;
			}
		}
	}
	mactrack_debug("ifAdminStatus data collection complete. '" . cacti_sizeof($ifAdminStatus) . "' rows found!");

	$ifOperStatus = xform_standard_indexed_data('.1.3.6.1.2.1.2.2.1.8', $device);
	if (cacti_sizeof($ifOperStatus)) {
		foreach($ifOperStatus as $key=>$value) {
			if ((substr_count(strtolower($value), 'up')) || ($value == '1')) {
				$ifOperStatus[$key] = 1;
			} else {
				$ifOperStatus[$key] = 0;
			}
		}
	}
	mactrack_debug("ifOperStatus data collection complete. '" . cacti_sizeof($ifOperStatus) . "' rows found!");

	$ifLastChange = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.9", $device);
	mactrack_debug("ifLastChange data collection complete. '" . cacti_sizeof($ifLastChange) . "' rows found!");

	/* get timing for rate information */
	$prev_octets_time = strtotime($device['last_rundate']);
	$cur_octets_time  = time();

	if ($prev_octets_time == 0) {
		$divisor = false;
	} else {
		$divisor = $cur_octets_time - $prev_octets_time;
	}

	/* if the device is snmpv2 use high speed and don't bother with the low speed stuff */
	$ifInOctets = xform_standard_indexed_data('.1.3.6.1.2.1.2.2.1.10', $device);
	mactrack_debug("ifInOctets data collection complete. '" . cacti_sizeof($ifInOctets) . "' rows found!");

	$ifOutOctets = xform_standard_indexed_data('.1.3.6.1.2.1.2.2.1.16', $device);
	mactrack_debug("ifOutOctets data collection complete. '" . cacti_sizeof($ifOutOctets) . "' rows found!");

	if ($device['snmp_version'] > 1) {
		$ifHCInOctets = xform_standard_indexed_data('.1.3.6.1.2.1.31.1.1.1.6', $device);
		mactrack_debug("ifHCInOctets data collection complete. '" . cacti_sizeof($ifHCInOctets) . "' rows found!");

		$ifHCOutOctets = xform_standard_indexed_data('.1.3.6.1.2.1.31.1.1.1.10', $device);
		mactrack_debug("ifHCOutOctets data collection complete. '" . cacti_sizeof($ifHCOutOctets) . "' rows found!");
	}


	$ifInMulticastPkts = xform_standard_indexed_data('.1.3.6.1.2.1.31.1.1.1.2', $device);
	mactrack_debug("ifInMulticastPkts data collection complete. '" . cacti_sizeof($ifInMulticastPkts) . "' rows found!");

	$ifOutMulticastPkts = xform_standard_indexed_data('.1.3.6.1.2.1.31.1.1.1.4', $device);
	mactrack_debug("ifOutMulticastPkts data collection complete. '" . cacti_sizeof($ifOutMulticastPkts) . "' rows found!");

	$ifInBroadcastPkts = xform_standard_indexed_data('.1.3.6.1.2.1.31.1.1.1.3', $device);
	mactrack_debug("ifInBroadcastPkts data collection complete. '" . cacti_sizeof($ifInBroadcastPkts) . "' rows found!");

	$ifOutBroadcastPkts = xform_standard_indexed_data('.1.3.6.1.2.1.31.1.1.1.5', $device);
	mactrack_debug("ifOutBroadcastPkts data collection complete. '" . cacti_sizeof($ifOutBroadcastPkts) . "' rows found!");

	$ifInUcastPkts = xform_standard_indexed_data('.1.3.6.1.2.1.2.2.1.11', $device);
	mactrack_debug("ifInUcastPkts data collection complete. '" . cacti_sizeof($ifInUcastPkts) . "' rows found!");

	$ifOutUcastPkts = xform_standard_indexed_data('.1.3.6.1.2.1.2.2.1.17', $device);
	mactrack_debug("ifOutUcastPkts data collection complete. '" . cacti_sizeof($ifOutUcastPkts) . "' rows found!");

	/* get information on error conditions */
	$ifInDiscards = xform_standard_indexed_data('.1.3.6.1.2.1.2.2.1.13', $device);
	mactrack_debug("ifInDiscards data collection complete. '" . cacti_sizeof($ifInDiscards) . "' rows found!");

	$ifInErrors = xform_standard_indexed_data('.1.3.6.1.2.1.2.2.1.14', $device);
	mactrack_debug("ifInErrors data collection complete. '" . cacti_sizeof($ifInErrors) . "' rows found!");

	$ifInUnknownProtos = xform_standard_indexed_data('.1.3.6.1.2.1.2.2.1.15', $device);
	mactrack_debug("ifInUnknownProtos data collection complete. '" . cacti_sizeof($ifInUnknownProtos) . "' rows found!");

	$ifOutDiscards = xform_standard_indexed_data('.1.3.6.1.2.1.2.2.1.19', $device);
	mactrack_debug("ifOutDiscards data collection complete. '" . cacti_sizeof($ifOutDiscards) . "' rows found!");

	$ifOutErrors = xform_standard_indexed_data('.1.3.6.1.2.1.2.2.1.20', $device);
	mactrack_debug("ifOutErrors data collection complete. '" . cacti_sizeof($ifOutErrors) . "' rows found!");

	$vlan_id    = '';
	$vlan_name  = '';
	$vlan_trunk = '';

	$i = 0;
	foreach($ifIndexes as $ifIndex) {
		$ifInterfaces[$ifIndex]['ifIndex'] = $ifIndex;
		$ifInterfaces[$ifIndex]['ifName'] = (isset($ifNames[$ifIndex]) ? $ifNames[$ifIndex] : '');
		$ifInterfaces[$ifIndex]['ifType'] = (isset($ifTypes[$ifIndex]) ? $ifTypes[$ifIndex] : '');

		if ($getLinkPorts) {
			$ifInterfaces[$ifIndex]['linkPort'] = (isset($link_ports[$ifIndex]) ? $link_ports[$ifIndex] : '');
			$linkPort = (isset($link_ports[$ifIndex]) ? $link_ports[$ifIndex] : '');
		} else {
			$linkPort = 0;
		}

		if (($getAlias) && (cacti_sizeof($ifAliases))) {
			$ifInterfaces[$ifIndex]['ifAlias'] = (isset($ifAliases[$ifIndex]) ? $ifAliases[$ifIndex] : '');
			$ifAlias = (isset($ifAliases[$ifIndex]) ? $ifAliases[$ifIndex] : '');
		} else {
			$ifAlias = '';
		}

		/* update the last up/down status */
		if (!isset($db_interface[$ifIndex])) {
			if ($ifOperStatus[$ifIndex] == 1) {
				$last_up_time = date('Y-m-d H:i:s');
				$stateChanges = 0;
				$last_down_time = 0;
			} else {
				$stateChanges = 0;
				$last_up_time   = 0;
				$last_down_time = date('Y-m-d H:i:s');
			}
		} else {
			$last_up_time   = $db_interface[$ifIndex]['last_up_time'];
			$last_down_time = $db_interface[$ifIndex]['last_down_time'];
			$stateChanges   = $db_interface[$ifIndex]['stateChanges'];

			if ($db_interface[$ifIndex]['ifOperStatus'] == 0) { /* interface previously not up */
				if (isset($ifOperStatus[$ifIndex]) && $ifOperStatus[$ifIndex] == 1) {
					/* the interface just went up, mark the time */
					$last_up_time = date('Y-m-d H:i:s');
					$stateChanges += 1;

					/* if the interface has never been marked down before, make it the current time */
					if ($db_interface[$ifIndex]['last_down_time'] == '0000-00-00 00:00:00') {
						$last_down_time = $last_up_time;
					}
				} else {
					/* if the interface has never been down, make the current time */
					$last_down_time = date('Y-m-d H:i:s');

					/* if the interface stayed down, set the last up time if not set before */
					if ($db_interface[$ifIndex]['last_up_time'] == '0000-00-00 00:00:00') {
						$last_up_time = date('Y-m-d H:i:s');
					}
				}
			} else {
				if (isset($ifOperStatus[$ifIndex]) && $ifOperStatus[$ifIndex] == 0) {
					/* the interface just went down, mark the time */
					$last_down_time = date('Y-m-d H:i:s');
					$stateChanges += 1;

					/* if the interface has never been up before, mark it the current time */
					if ($db_interface[$ifIndex]['last_up_time'] == '0000-00-00 00:00:00') {
						$last_up_time = date('Y-m-d H:i:s');
					}
				} else {
					$last_up_time = date('Y-m-d H:i:s');

					if ($db_interface[$ifIndex]['last_down_time'] == '0000-00-00 00:00:00') {
						$last_down_time = date('Y-m-d H:i:s');
					}
				}
			}
		}

		/* do the in octets */
		$int_ifInOctets = get_link_int_value('ifInOctets', $ifIndex, $ifInOctets, $db_interface, $divisor, 'traffic');

		/* do the out octets */
		$int_ifOutOctets = get_link_int_value('ifOutOctets', $ifIndex, $ifOutOctets, $db_interface, $divisor, 'traffic');

		if ($device['snmp_version'] > 1) {
			/* do the in octets */
			$int_ifHCInOctets = get_link_int_value('ifHCInOctets', $ifIndex, $ifHCInOctets, $db_interface, $divisor, 'traffic', '64');

			/* do the out octets */
			$int_ifHCOutOctets = get_link_int_value('ifHCOutOctets', $ifIndex, $ifHCOutOctets, $db_interface, $divisor, 'traffic', '64');
		}

		/* accommodate values in high speed octets for interfaces that don't support 64 bit */
		if (isset($ifInOctets[$ifIndex])) {
			if (!isset($ifHCInOctets[$ifIndex])) {
				$ifHCInOctets[$ifIndex] = $ifInOctets[$ifIndex];
				$int_ifHCInOctets = $int_ifInOctets;
			}
		}

		if (isset($ifOutOctets[$ifIndex])) {
			if (!isset($ifHCOutOctets[$ifIndex])) {
				$ifHCOutOctets[$ifIndex] = $ifOutOctets[$ifIndex];
				$int_ifHCOutOctets = $int_ifOutOctets;
			}
		}

		$int_ifInMulticastPkts  = get_link_int_value('ifInMulticastPkts', $ifIndex, $ifInMulticastPkts, $db_interface, $divisor, 'traffic');
		$int_ifOutMulticastPkts = get_link_int_value('ifOutMulticastPkts', $ifIndex, $ifOutMulticastPkts, $db_interface, $divisor, 'traffic');
		$int_ifInBroadcastPkts  = get_link_int_value('ifInBroadcastPkts', $ifIndex, $ifInBroadcastPkts, $db_interface, $divisor, 'traffic');
		$int_ifOutBroadcastPkts = get_link_int_value('ifOutBroadcastPkts', $ifIndex, $ifOutBroadcastPkts, $db_interface, $divisor, 'traffic');
		$int_ifInUcastPkts      = get_link_int_value('ifInUcastPkts', $ifIndex, $ifInUcastPkts, $db_interface, $divisor, 'traffic');
		$int_ifOutUcastPkts     = get_link_int_value('ifOutUcastPkts', $ifIndex, $ifOutUcastPkts, $db_interface, $divisor, 'traffic');

		/* see if in error's have been increasing */
		$int_ifInErrors         = get_link_int_value('ifInErrors', $ifIndex, $ifInErrors, $db_interface, $divisor, 'errors');

		/* see if out error's have been increasing */
		$int_ifOutErrors        = get_link_int_value('ifOutErrors', $ifIndex, $ifOutErrors, $db_interface, $divisor, 'errors');

		if ($int_ifInErrors > 0 || $int_ifOutErrors > 0) {
			$int_errors_present = true;
		} else {
			$int_errors_present = false;
		}

		/* see if in discards's have been increasing */
		$int_ifInDiscards    = get_link_int_value('ifInDiscards', $ifIndex, $ifInDiscards, $db_interface, $divisor, 'errors');

		/* see if out discards's have been increasing */
		$int_ifOutDiscards   = get_link_int_value('ifOutDiscards', $ifIndex, $ifOutDiscards, $db_interface, $divisor, 'errors');

		if ($int_ifInDiscards > 0 || $int_ifOutDiscards > 0) {
			$int_discards_present = true;
		} else {
			$int_discards_present = false;
		}

		/* see if in discards's have been increasing */
		$int_ifInUnknownProtos = get_link_int_value('ifInUnknownProtos', $ifIndex, $ifInUnknownProtos, $db_interface, $divisor, 'errors');

		/* format the update packet */
		if ($i == 0) {
			$insert_vals .= ' ';
		} else {
			$insert_vals .= ',';
		}

		if (isset($ifTypes[$ifIndex])) {
			$type = $ifTypes[$ifIndex];
		} else {
			$type = 'Undefined';
		}

		if (isset($ifNames[$ifIndex])) {
			$name = $ifNames[$ifIndex];
		} else {
			$name = 'Undefined';
		}

		if (isset($ifSpeed[$ifIndex])) {
			$speed = $ifSpeed[$ifIndex];
		} else {
			$speed = 0;
		}

		if (isset($ifDescr[$ifIndex])) {
			$desc = $ifDescr[$ifIndex];
		} else {
			$desc = '';
		}

		if (isset($ifLastChange[$ifIndex]) && strpos($ifLastChange[$ifIndex], ':') !== false) {
			$ifLastChange[$ifIndex] = mactrack_timetics_to_seconds($ifLastChange[$ifIndex]);
		}

		$mac_address = isset($ifPhysAddress[$ifIndex]) ? xform_mac_address($ifPhysAddress[$ifIndex]):'';

		$insert_vals .= "('" .
			$device['site_id']                  . "', '" . $device['device_id']          . "', '" .
			$device['snmp_sysUptime']           . "', '" . $ifIndex                      . "', "  .
			db_qstr($type)                      . ", "   . db_qstr($name)                . ", "   .
			db_qstr($ifAlias)                   . ", '"  . $linkPort                     . "', '" .
			$vlan_id                            . "', "  . db_qstr($vlan_name)           . ", '"  .
			$vlan_trunk                         . "', '" . $speed                        . "', '" .
			(isset($ifHighSpeed[$ifIndex]) ? $ifHighSpeed[$ifIndex] : '')                . "', '" .
			(isset($ifDuplex[$ifIndex]) ? $ifDuplex[$ifIndex] : '')                      . "', " .
			db_qstr($desc)                                                               . ", '"  .
			(isset($ifMtu[$ifIndex]) ? $ifMtu[$ifIndex] : '')             		         . "', '" .
			$mac_address                                                                 . "', '" .
			(isset($ifAdminStatus[$ifIndex]) ? $ifAdminStatus[$ifIndex] : '')    	     . "', '" .
			(isset($ifOperStatus[$ifIndex]) ? $ifOperStatus[$ifIndex] : '')              . "', '" .
			(isset($ifLastChange[$ifIndex]) ? $ifLastChange[$ifIndex] : '')		         . "', '" .
			(isset($ifInOctets[$ifIndex]) ? $ifInOctets[$ifIndex] : '')                  . "', '" .
			(isset($ifOutOctets[$ifIndex]) ? $ifOutOctets[$ifIndex] : '')      	         . "', '" .
			(isset($ifHCInOctets[$ifIndex]) ? $ifHCInOctets[$ifIndex] : '')              . "', '" .
			(isset($ifHCOutOctets[$ifIndex]) ? $ifHCOutOctets[$ifIndex] : '')     	     . "', '" .
			(isset($ifInUcastPkts[$ifIndex]) ? $ifInUcastPkts[$ifIndex] : '')            . "', '" .
			(isset($ifOutUcastPkts[$ifIndex]) ? $ifOutUcastPkts[$ifIndex] : '')          . "', '" .
			(isset($ifInDiscards[$ifIndex]) ? $ifInDiscards[$ifIndex] : '')              . "', '" .
			(isset($ifInErrors[$ifIndex]) ? $ifInErrors[$ifIndex] : '')        	         . "', '" .
			(isset($ifInUnknownProtos[$ifIndex]) ? $ifInUnknownProtos[$ifIndex] : '')    . "', '" .
			(isset($ifOutDiscards[$ifIndex]) ? $ifOutDiscards[$ifIndex] : '')	         . "', '" .
			(isset($ifOutErrors[$ifIndex]) ? $ifOutErrors[$ifIndex] : '')                . "', '" .
			(isset($ifInMulticastPkts[$ifIndex]) ? $ifInMulticastPkts[$ifIndex] : '')    . "', '" .
			(isset($ifOutMulticastPkts[$ifIndex]) ? $ifOutMulticastPkts[$ifIndex] : '')  . "', '" .
			(isset($ifInBroadcastPkts[$ifIndex]) ? $ifInBroadcastPkts[$ifIndex] : '')    . "', '" .
			(isset($ifOutBroadcastPkts[$ifIndex]) ? $ifOutBroadcastPkts[$ifIndex] : '')  . "', '" .
			@$int_ifInOctets                    . "', '" . @$int_ifOutOctets             . "', '" .
			@$int_ifHCInOctets                  . "', '" . @$int_ifHCOutOctets           . "', '" .
			@$int_ifInMulticastPkts             . "', '" . @$int_ifOutMulticastPkts      . "', '" .
			@$int_ifInBroadcastPkts             . "', '" . @$int_ifOutBroadcastPkts      . "', '" .
			@$int_ifInUcastPkts                 . "', '" . @$int_ifOutUcastPkts          . "', '" .
			@$int_ifInDiscards                  . "', '" . @$int_ifInErrors              . "', '" .
			@$int_ifInUnknownProtos             . "', '" . @$int_ifOutDiscards           . "', '" .
			@$int_ifOutErrors                   . "', '" . @$int_discards_present        . "', '" .
			$int_errors_present                 . "', '" .  $last_down_time              . "', '" .
			$last_up_time                       . "', '" .  $stateChanges                . "', '" . "1')";

		$i++;
	}

	mactrack_debug('ifInterfaces assembly complete: ' . strlen($insert_prefix . $insert_vals . $insert_suffix));

	if ($insert_vals != '') {
		/* add/update records in the database */
		db_execute($insert_prefix . $insert_vals . $insert_suffix);

		/* remove all obsolete records from the database */
		db_execute_prepared('DELETE FROM mac_track_interfaces
			WHERE present=0
			AND device_id = ?',
			array($device['device_id']));

		/* set the percent utilized fields, you can't do this for vlans */
		db_execute_prepared('UPDATE mac_track_interfaces
			SET inBound=(int_ifHCInOctets*8)/(ifHighSpeed*10000), outBound=(int_ifHCOutOctets*8)/(ifHighSpeed*10000)
			WHERE ifHighSpeed>0
			AND ifName NOT LIKE "Vl%"
			AND device_id = ?',
			array($device['device_id']));

		mactrack_debug('Adding IfInterfaces Records');
	}

	if ($device['host_id'] > 0) {
		mactrack_find_host_graphs($device['device_id'], $device['host_id']);
	}

	return $ifInterfaces;
}

function mactrack_timetics_to_seconds($timetics) {
	$time  = 0;
	$parts = explode(':', $timetics);

	if (cacti_sizeof($parts) == 4) {
		$time += $parts[0] * 86400;
		$time += $parts[1] * 3600;
		$time += $parts[2] * 60;
		$time += round($parts[3], 0);
	} elseif (cacti_sizeof($parts) == 3) {
		$time += $parts[0] * 3600;
		$time += $parts[1] * 60;
		$time += round($parts[2],0);
	}

	return $time;
}

function mactrack_find_host_graphs($device_id, $host_id) {
	$field_name = 'ifName';

	$local_data_ids = db_fetch_assoc_prepared('SELECT dl.*,
		hsc.field_name, hsc.field_value
		FROM data_local AS dl
		INNER JOIN data_template_data AS dtd
		ON dl.id=dtd.local_data_id
		LEFT JOIN data_input AS di
		ON di.id=dtd.data_input_id
		LEFT JOIN data_template AS dt
		ON dl.data_template_id=dt.id
		LEFT JOIN host_snmp_cache AS hsc
		ON hsc.snmp_query_id=dl.snmp_query_id
		AND hsc.host_id=dl.host_id
		AND hsc.snmp_index=dl.snmp_index
		WHERE dl.id=dtd.local_data_id
		AND hsc.host_id = ?
		AND field_name = ?',
		array($host_id, $field_name));

	$output_array    = array();
	if (cacti_sizeof($local_data_ids)) {
		foreach($local_data_ids as $local_data_id) {
			$local_graph_ids = array_rekey(
				db_fetch_assoc_prepared('SELECT DISTINCT gtg.local_graph_id AS id, gtg.graph_template_id
					FROM graph_templates_graph AS gtg
					INNER JOIN graph_templates_item AS gti
					ON gtg.local_graph_id=gti.local_graph_id
					INNER JOIN data_template_rrd AS dtr
					ON gti.task_item_id=dtr.id
					WHERE gtg.local_graph_id>0
					AND dtr.local_data_id = ?',
					array($local_data_id['id'])),
				'id', 'graph_template_id'
			);

			if (cacti_sizeof($local_graph_ids)) {
				foreach($local_graph_ids as $local_graph_id => $graph_template_id) {
					$output_array[$local_data_id['field_value']][$local_graph_id] = array($graph_template_id, $local_data_id['snmp_query_id']);
				}
			}
		}
	}

	$sql = '';
	$found = 0;
	if (cacti_sizeof($output_array)) {
		$interfaces = array_rekey(
			db_fetch_assoc("SELECT device_id, ifIndex, $field_name
				FROM mac_track_interfaces
				WHERE device_id=$device_id"),
			$field_name, array('device_id', 'ifIndex')
		);

		if (cacti_sizeof($interfaces)) {
			foreach($interfaces as $key => $data) {
				if (isset($output_array[$key])) {
					foreach($output_array[$key] as $local_graph_id => $graph_details) {
						$sql .= ($sql != '' ? ', (' : '(') .
							$data['ifIndex']   . ",'" .
							$key               . "'," .
							$local_graph_id    . ','  .
							$device_id         . ','  .
							$host_id           . ','  .
							$graph_details[0]  . ','  .
							$graph_details[1]  . ",'" .
							$key . "','" . $field_name . "', 1)";

						$found++;
					}
				}
			}
		}
	}

	if ($found) {
		/* let's make sure we mark everything gone first */
		db_execute_prepared('UPDATE mac_track_interface_graphs
			SET present = 0
			WHERE device_id = ?
			AND host_id = ?',
			array($device_id, $host_id));

		db_execute("INSERT INTO mac_track_interface_graphs
			(ifIndex, ifName, local_graph_id, device_id, host_id, snmp_query_id, graph_template_id, field_value, field_name, present)
			VALUES $sql
			ON DUPLICATE KEY UPDATE
				snmp_query_id=VALUES(snmp_query_id),
				graph_template_id=VALUES(graph_template_id),
				field_value=VALUES(field_value),
				field_name=VALUES(field_name),
				present=VALUES(present)");

		db_execute_prepared('DELETE FROM mac_track_interface_graphs
			WHERE present = 0
			AND device_id = ?
			AND host_id = ?',
			array($device_id, $host_id));
	}
}

function get_link_int_value($snmp_oid, $ifIndex, &$snmp_array, &$db_interface, $divisor, $type = 'errors', $bits = '32') {
	/* 32bit and 64bit Integer Overflow Value */
	if ($bits == '32') {
		$overflow   = 4294967295;
		/* fudge factor */
		$fudge      = 3000000001;
	} else {
		$overflow = 18446744065119617025;
		/* fudge factor */
		$fudge      = 300000000001;
	}

	/* see if values have been increasing */
	$int_value = 0;
	if (!isset($db_interface[$ifIndex][$snmp_oid])) {
		$int_value = 0;
	} elseif (!isset($snmp_array[$ifIndex])) {
		$int_value = 0;
	} elseif ($snmp_array[$ifIndex] != $db_interface[$ifIndex][$snmp_oid]) {
		/* account for 2E32 rollover */
		/* there are two types of rollovers one rolls to 0 */
		/* the other counts backwards.  let's make an educated guess */
		if ($db_interface[$ifIndex][$snmp_oid] > $snmp_array[$ifIndex]) {
			/* errors count backwards from overflow */
			if ($type == 'errors') {
				if (($overflow - $db_interface[$ifIndex][$snmp_oid] + $snmp_array[$ifIndex]) < $fudge) {
					$int_value = $overflow - $db_interface[$ifIndex][$snmp_oid] + $snmp_array[$ifIndex];
				} else {
					$int_value = $db_interface[$ifIndex][$snmp_oid] - $snmp_array[$ifIndex];
				}
			} else {
				$int_value = $overflow - $db_interface[$ifIndex][$snmp_oid] + $snmp_array[$ifIndex];
			}
		} else {
			$int_value = $snmp_array[$ifIndex] - $db_interface[$ifIndex][$snmp_oid];
		}

		/* account for counter resets */
		$frequency = 0;
		$timing = read_config_option('mt_collection_timing');

		if ($timing != 'disabled') {
			$frequency = $timing * 60;
		}

		if ($frequency > 0) {
			if ($db_interface[$ifIndex]['ifHighSpeed'] > 0) {
				if ($int_value > ($db_interface[$ifIndex]['ifHighSpeed'] * 1000000 * $frequency * 1.1)) {
					$int_value = $snmp_array[$ifIndex];
				}
			} else {
				if ($int_value > ($db_interface[$ifIndex]['ifSpeed'] * $frequency * 1.1 / 8)) {
					$int_value = $snmp_array[$ifIndex];
				}
			}
		}
	} else {
		$int_value = 0;
	}

	if (!$divisor) {
		return 0;
	} else {
		return $int_value / $divisor;
	}
}

/*	get_generic_switch_ports - This is a basic function that will scan the dot1d
  OID tree for all switch port to MAC address association and stores in the
  mac_track_temp_ports table for future processing in the finalization steps of the
  scanning process.
*/
function get_generic_switch_ports($site, &$device, $lowPort = 0, $highPort = 0) {
	global $debug, $scan_date;

	/* initialize port counters */
	$device['ports_total'] = 0;
	$device['ports_active'] = 0;
	$device['ports_trunk'] = 0;

	/* get the ifIndexes for the device */
	$ifIndexes = xform_standard_indexed_data('.1.3.6.1.2.1.2.2.1.1', $device);
	mactrack_debug('ifIndexes data collection complete');

	$ifInterfaces = build_InterfacesTable($device, $ifIndexes, true, false);

	get_base_dot1dTpFdbEntry_ports($site, $device, $ifInterfaces, '', true, $lowPort, $highPort);

	return $device;
}

/*	get_generic_dot1q_switch_ports - This is a basic function that will scan the dot1d
  OID tree for all switch port to MAC address association and stores in the
  mac_track_temp_ports table for future processing in the finalization steps of the
  scanning process.
*/
function get_generic_dot1q_switch_ports($site, &$device, $lowPort = 0, $highPort = 0) {
	global $debug, $scan_date;

	/* initialize port counters */
	$device['ports_total'] = 0;
	$device['ports_active'] = 0;
	$device['ports_trunk'] = 0;

	/* get the ifIndexes for the device */
	$ifIndexes = xform_standard_indexed_data('.1.3.6.1.2.1.2.2.1.1', $device);
	mactrack_debug('ifIndexes data collection complete');

	$ifInterfaces = build_InterfacesTable($device, $ifIndexes, true, false);

	get_base_dot1qTpFdbEntry_ports($site, $device, $ifInterfaces, '', true, $lowPort, $highPort);

	return $device;
}

/*	get_generic_wireless_ports - This is a basic function that will scan the dot1d
  OID tree for all switch port to MAC address association and stores in the
  mac_track_temp_ports table for future processing in the finalization steps of the
  scanning process.
*/
function get_generic_wireless_ports($site, &$device, $lowPort = 0, $highPort = 0) {
	global $debug, $scan_date;

	/* initialize port counters */
	$device['ports_total'] = 0;
	$device['ports_active'] = 0;
	$device['ports_trunk'] = 0;

	/* get the ifIndexes for the device */
	$ifIndexes = xform_standard_indexed_data('.1.3.6.1.2.1.2.2.1.1', $device);
	mactrack_debug('ifIndexes data collection complete');

	$ifInterfaces = build_InterfacesTable($device, $ifIndexes, false, false);

	get_base_wireless_dot1dTpFdbEntry_ports($site, $device, $ifInterfaces, '', true, $lowPort, $highPort);

	return $device;
}

/*	get_base_dot1dTpFdbEntry_ports - This function will grab information from the
  port bridge snmp table and return it to the calling progrem for further processing.
  This is a foundational function for all vendor data collection functions.
*/
function get_base_dot1dTpFdbEntry_ports($site, &$device, &$ifInterfaces, $snmp_readstring = '', $store_to_db = true, $lowPort = 1, $highPort = 9999) {
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

	/* cisco uses a hybrid read string, if one is not defined, use the default */
	if ($snmp_readstring == '') {
		$snmp_readstring = $device['snmp_readstring'];
	}

	/* get the operational status of the ports */
	$active_ports_array = xform_standard_indexed_data('.1.3.6.1.2.1.2.2.1.8', $device);
	$indexes = array_keys($active_ports_array);

	$i = 0;
	if (cacti_sizeof($active_ports_array)) {
		foreach($active_ports_array as $port_info) {
			$port_info =  mactrack_strip_alpha($port_info);
			if (isset($indexes[$i]) && isset($ifInterfaces[$indexes[$i]]['ifType'])) {
				if ((($ifInterfaces[$indexes[$i]]['ifType'] >= 6) &&
				     ($ifInterfaces[$indexes[$i]]['ifType'] <= 9)) ||
				    ($ifInterfaces[$indexes[$i]]['ifType'] == 53)  || #vlan
				    ($ifInterfaces[$indexes[$i]]['ifType'] == 161) || #port-channel
				    ($ifInterfaces[$indexes[$i]]['ifType'] == 71)) {
					if ($port_info == 1) {
						$ports_active++;
					}
				}
				$ports_total++;
			}

			$i++;
		}
	}
    $device['ports_active'] = $ports_active;

	if ($store_to_db) {
		mactrack_debug('INFO: HOST: ' . $device['hostname'] . ', TYPE: ' . substr($device['snmp_sysDescr'],0,40) . ', TOTAL PORTS: ' . $ports_total . ', OPER PORTS: ' . $ports_active);

		$device['ports_total'] = $ports_total;
		$device['macs_active'] = 0;
	}

	if ($ports_active > 0) {
		/* get bridge port to ifIndex mapping */
		$bridgePortIfIndexes = xform_standard_indexed_data('.1.3.6.1.2.1.17.1.4.1.2', $device, $snmp_readstring);

		$port_status = xform_stripped_oid('.1.3.6.1.2.1.17.4.3.1.3', $device, $snmp_readstring);

		/* get device active port numbers */
		$port_numbers = xform_stripped_oid('.1.3.6.1.2.1.17.4.3.1.2', $device, $snmp_readstring);

		/* get the ignore ports list from device */
		$ignore_ports = port_list_to_array($device['ignorePorts']);

		/* determine user ports for this device and transfer user ports to
		   a new array.
		*/
		$i = 0;
		if (cacti_sizeof($port_numbers)) {
			foreach ($port_numbers as $key => $port_number) {
				if (($highPort == 0) ||
					(($port_number >= $lowPort) &&
					($port_number <= $highPort))) {

					if (!in_array($port_number, $ignore_ports)) {
						if ((isset($port_status[$key]) && $port_status[$key] == '3') ||
						    (isset($port_status[$key]) && $port_status[$key] == '5')) {
							$port_key_array[$i]['key'] = $key;
							$port_key_array[$i]['port_number'] = $port_number;

							$i++;
						}
					}
				}
			}
		}

		/* compare the user ports to the bridge port data, store additional
		   relevant data about the port.
		*/
		$i = 0;
		if (cacti_sizeof($port_key_array)) {
		foreach ($port_key_array as $port_key) {
			/* map bridge port to interface port and check type */
			if ($port_key['port_number'] > 0) {
				if (cacti_sizeof($bridgePortIfIndexes)) {
					/* some hubs do not always return a port number in the bridge table.
					   test for it by isset and substitute the port number from the ifTable
					   if it isnt in the bridge table
					*/
					if (isset($bridgePortIfIndexes[$port_key['port_number']])) {
						$brPortIfIndex = @$bridgePortIfIndexes[$port_key['port_number']];
					} else {
						$brPortIfIndex = @$port_key['port_number'];
					}
					$brPortIfType = (isset($ifInterfaces[$brPortIfIndex]['ifType']) ? $ifInterfaces[$brPortIfIndex]['ifType'] : '');
				} else {
					$brPortIfIndex = $port_key['port_number'];
					$brPortIfType = @$ifInterfaces[$port_key['port_number']]['ifType'];
				}

				if (($brPortIfType >= 6) &&
					($brPortIfType <= 9) &&
					(!isset($ifInterfaces[$brPortIfIndex]['portLink']))) {
					/* set some defaults  */
					$new_port_key_array[$i]['vlan_id']     = 'N/A';
					$new_port_key_array[$i]['vlan_name']   = 'N/A';
					$new_port_key_array[$i]['mac_address'] = 'NOT USER';
					$new_port_key_array[$i]['port_number'] = 'NOT USER';
					$new_port_key_array[$i]['port_name']   = 'N/A';

					/* now set the real data */
					$new_port_key_array[$i]['key']         = $port_key['key'];
					$new_port_key_array[$i]['port_number'] = $port_key['port_number'];
					$i++;
				}
			}
		}
		}
		mactrack_debug('Port number information collected.');

		/* map mac address */
		/* only continue if there were user ports defined */
		if (cacti_sizeof($new_port_key_array)) {
			/* get the bridges active MAC addresses */
			$port_macs = xform_stripped_oid('.1.3.6.1.2.1.17.4.3.1.1', $device, $snmp_readstring, true);

			if (cacti_sizeof($port_macs)) {
				foreach ($port_macs as $key => $port_mac) {
					$port_macs[$key] = xform_mac_address($port_mac);
				}
			}

			if (cacti_sizeof($new_port_key_array)) {
				foreach ($new_port_key_array as $key => $port_key) {
					$new_port_key_array[$key]['mac_address'] = (isset($port_macs[$port_key['key']]) ? $port_macs[$port_key['key']]:'' );
					mactrack_debug("INDEX: '". $key . "' MAC ADDRESS: " . $new_port_key_array[$key]['mac_address']);
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
		} elseif (cacti_sizeof($new_port_key_array)) {
			$device['last_runmessage'] = 'Data collection completed ok';
			$device['macs_active']     = cacti_sizeof($new_port_key_array);
			db_store_device_port_results($device, $new_port_key_array, $scan_date);
		} else {
			$device['last_runmessage'] = 'WARNING: Poller did not find active ports on this device.';
		}
	} else {
		return $new_port_key_array;
	}
}

/* get_ios_vrf_arp_table
  	obtains arp associations for cisco Catalyst Switches.
  	At this stage only tested on 6800 series
 */

function get_ios_vrf_arp_table($oid, &$device, $snmp_readstring = '', $hex = false) {
	$return_array = array();

	if ($snmp_readstring == '') {
		$snmp_readstring = $device['snmp_readstring'];
	}

	if ($device['snmp_version'] == '3' && substr_count($snmp_readstring,'vlan-')) {
		$snmp_context = $snmp_readstring;
	} else {
		$snmp_context = $device['snmp_context'];
	}

	$walk_array = cacti_snmp_walk($device['hostname'], $snmp_readstring,
		$oid, $device['snmp_version'], $device['snmp_username'],
		$device['snmp_password'], $device['snmp_auth_protocol'],
		$device['snmp_priv_passphrase'], $device['snmp_priv_protocol'],
		$snmp_context, $device['snmp_port'], $device['snmp_timeout'],
		$device['snmp_retries'], $device['max_oids'],
		SNMP_POLLER, $device['snmp_engine_id'],
		($hex ? SNMP_STRING_OUTPUT_HEX : SNMP_STRING_OUTPUT_GUESS));

	$i = 0;

	if (cacti_sizeof($walk_array)) {

		foreach ($walk_array as $walk_item) {
			$key = $walk_item['oid'];
			$key = preg_replace('/' . $oid . '\.[0-9]+\.1\./', '', $key);
			$return_array[$i]['key'] = $key;
			$return_array[$i]['value'] = str_replace(' ', ':', $walk_item['value']);

			$i++;
		}
	}

	return $return_array;

}


/*	get_base_wireless_dot1dTpFdbEntry_ports - This function will grab information from the
  port bridge snmp table and return it to the calling progrem for further processing.
  This is a foundational function for all vendor data collection functions.
*/
function get_base_wireless_dot1dTpFdbEntry_ports($site, &$device, &$ifInterfaces, $snmp_readstring = '', $store_to_db = true, $lowPort = 1, $highPort = 9999) {
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

	/* cisco uses a hybrid read string, if one is not defined, use the default */
	if ($snmp_readstring == '') {
		$snmp_readstring = $device['snmp_readstring'];
	}

	/* get the operational status of the ports */
	$active_ports_array = xform_standard_indexed_data('.1.3.6.1.2.1.2.2.1.8', $device);
	$indexes = array_keys($active_ports_array);

	$i = 0;
	if (cacti_sizeof($active_ports_array)) {
		foreach($active_ports_array as $port_info) {
			$port_info =  mactrack_strip_alpha($port_info);
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

	if ($ports_active > 0) {
		/* get bridge port to ifIndex mapping */
		$bridgePortIfIndexes = xform_standard_indexed_data('.1.3.6.1.2.1.17.1.4.1.2', $device, $snmp_readstring);

		$port_status = xform_stripped_oid('.1.3.6.1.2.1.17.4.3.1.3', $device, $snmp_readstring);

		/* get device active port numbers */
		$port_numbers = xform_stripped_oid('.1.3.6.1.2.1.17.4.3.1.2', $device, $snmp_readstring);

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
						$port_key_array[$i]['key']         = $key;
						$port_key_array[$i]['port_number'] = $port_number;

						$i++;
					}
				}
			}
		}
		}

		/* compare the user ports to the bridge port data, store additional
		   relevant data about the port.
		*/
		$i = 0;
		if (cacti_sizeof($port_key_array)) {
			foreach ($port_key_array as $port_key) {
				/* map bridge port to interface port and check type */
				if ($port_key['port_number'] > 0) {
					if (cacti_sizeof($bridgePortIfIndexes)) {
						$brPortIfIndex = @$bridgePortIfIndexes[$port_key['port_number']];
						$brPortIfType = @$ifInterfaces[$brPortIfIndex]['ifType'];
					} else {
						$brPortIfIndex = $port_key['port_number'];
						$brPortIfType = @$ifInterfaces[$port_key['port_number']]['ifType'];
					}

					if ((($brPortIfType >= 6) && ($brPortIfType <= 9)) || ($brPortIfType == 71)) {
						/* set some defaults  */
						$new_port_key_array[$i]['vlan_id']     = 'N/A';
						$new_port_key_array[$i]['vlan_name']   = 'N/A';
						$new_port_key_array[$i]['mac_address'] = 'NOT USER';
						$new_port_key_array[$i]['port_number'] = 'NOT USER';
						$new_port_key_array[$i]['port_name']   = 'N/A';

						/* now set the real data */
						$new_port_key_array[$i]['key']         = $port_key['key'];
						$new_port_key_array[$i]['port_number'] = $port_key['port_number'];
						$i++;
					}
				}
			}
		}
		mactrack_debug('Port number information collected.');

		/* map mac address */
		/* only continue if there were user ports defined */
		if (cacti_sizeof($new_port_key_array)) {
			/* get the bridges active MAC addresses */
			$port_macs = xform_stripped_oid('.1.3.6.1.2.1.17.4.3.1.1', $device, $snmp_readstring, true);

			if (cacti_sizeof($port_macs)) {
				foreach ($port_macs as $key => $port_mac) {
					$port_macs[$key] = xform_mac_address($port_mac);
				}
			}

			if (cacti_sizeof($new_port_key_array)) {
				foreach ($new_port_key_array as $key => $port_key) {
					$new_port_key_array[$key]['mac_address'] = @$port_macs[$port_key['key']];
					mactrack_debug("INDEX: '". $key . "' MAC ADDRESS: " . $new_port_key_array[$key]['mac_address']);
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
		} elseif (cacti_sizeof($new_port_key_array)) {
			$device['last_runmessage'] = 'Data collection completed ok';
			$device['macs_active']     = cacti_sizeof($new_port_key_array);
			db_store_device_port_results($device, $new_port_key_array, $scan_date);
		} else {
			$device['last_runmessage'] = 'WARNING: Poller did not find active ports on this device.';
		}
	} else {
		return $new_port_key_array;
	}
}

/*	get_base_dot1qTpFdbEntry_ports - This function will grab information from the
  port bridge snmp table and return it to the calling progrem for further processing.
  This is a foundational function for all vendor data collection functions.
*/
function get_base_dot1qTpFdbEntry_ports($site, &$device, &$ifInterfaces, $snmp_readstring = '', $store_to_db = true, $lowPort = 1, $highPort = 9999) {
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

	/* cisco uses a hybrid read string, if one is not defined, use the default */
	if ($snmp_readstring == '') {
		$snmp_readstring = $device['snmp_readstring'];
	}

	/* get the operational status of the ports */
	$active_ports_array = xform_standard_indexed_data('.1.3.6.1.2.1.2.2.1.8', $device);
	$indexes = array_keys($active_ports_array);

	$i = 0;
	if (cacti_sizeof($active_ports_array)) {
		foreach($active_ports_array as $port_info) {
			$port_info =  mactrack_strip_alpha($port_info);
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
    $device['ports_active'] = $ports_active;

	if ($store_to_db) {
		mactrack_debug('INFO: HOST: ' . $device['hostname'] . ', TYPE: ' . substr($device['snmp_sysDescr'],0,40) . ', TOTAL PORTS: ' . $ports_total . ', OPER PORTS: ' . $ports_active);

		$device['ports_total'] = $ports_total;
		$device['macs_active'] = 0;
	}

	if ($ports_active > 0) {
		/* get bridge port to ifIndex mapping */
		$bridgePortIfIndexes = xform_standard_indexed_data('.1.3.6.1.2.1.17.1.4.1.2', $device, $snmp_readstring);

		$port_status = xform_stripped_oid('.1.3.6.1.2.1.17.7.1.2.2.1.3', $device, $snmp_readstring);

		/* get device active port numbers */
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
						if ((isset($port_status[$key]) && $port_status[$key] == '3') || (isset($port_status[$key]) && $port_status[$key] == '5')) {
							$port_key_array[$i]['key']         = $key;
							$port_key_array[$i]['port_number'] = $port_number;

							$i++;
						}
					}
				}
			}
		}

		/* compare the user ports to the bridge port data, store additional
		   relevant data about the port.
		*/
		$i = 0;
		if (cacti_sizeof($port_key_array)) {
			foreach ($port_key_array as $port_key) {
				/* map bridge port to interface port and check type */
				if ($port_key['port_number'] > 0) {
					if (cacti_sizeof($bridgePortIfIndexes)) {
						if (isset ($bridgePortIfIndexes[$port_key['port_number']])) {
							$brPortIfIndex = $bridgePortIfIndexes[$port_key['port_number']];
						}
						if (isset($ifInterfaces[$brPortIfIndex]['ifType'])) {
							$brPortIfType = $ifInterfaces[$brPortIfIndex]['ifType'];
						}
					} else {
						$brPortIfIndex = $port_key['port_number'];
						if (isset($ifInterfaces[$port_key['port_number']]['ifType'])) {
							$brPortIfType = $ifInterfaces[$port_key['port_number']]['ifType'];
						}
					}

					if ((($brPortIfType >= 6) && ($brPortIfType <= 9)) || ($brPortIfType == 71)) {
						/* set some defaults  */
						$new_port_key_array[$i]['vlan_id']     = 'N/A';
						$new_port_key_array[$i]['vlan_name']   = 'N/A';
						$new_port_key_array[$i]['mac_address'] = 'NOT USER';
						$new_port_key_array[$i]['port_number'] = 'NOT USER';
						$new_port_key_array[$i]['port_name']   = 'N/A';

						/* now set the real data */
						$new_port_key_array[$i]['key']         = $port_key['key'];
						$new_port_key_array[$i]['port_number'] = $port_key['port_number'];
						$i++;
					}
				}
			}
		}
		mactrack_debug('Port number information collected.');

		/* map mac address */
		/* only continue if there were user ports defined */
		if (cacti_sizeof($new_port_key_array)) {
			/* get the bridges active MAC addresses */
			$port_macs = xform_stripped_oid('.1.3.6.1.2.1.17.7.1.2.2.1.1', $device, $snmp_readstring, true);

			if (cacti_sizeof($port_macs)) {
				foreach ($port_macs as $key => $port_mac) {
					$port_macs[$key] = xform_mac_address($port_mac);
				}
			}

			if (cacti_sizeof($new_port_key_array)) {
				foreach ($new_port_key_array as $key => $port_key) {
					if (isset($port_macs[$port_key['key']])) {
						$new_port_key_array[$key]['mac_address'] = @$port_macs[$port_key['key']];
						mactrack_debug("INDEX: '". $key . "' MAC ADDRESS: " . $new_port_key_array[$key]['mac_address']);
					} else {
						mactrack_debug("INDEX: '". $key . "' not found in port_macs array, skipping");
					}
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
		} elseif (cacti_sizeof($new_port_key_array)) {
			$device['last_runmessage'] = 'Data collection completed ok';
			$device['macs_active']     = cacti_sizeof($new_port_key_array);
			db_store_device_port_results($device, $new_port_key_array, $scan_date);
		} else {
			$device['last_runmessage'] = 'WARNING: Poller did not find active ports on this device.';
		}
	} else {
		return $new_port_key_array;
	}
}

/*	gethostbyaddr_wtimeout - This function provides a good method of performing
  a rapid lookup of a DNS entry for a host so long as you don't have to look far.
*/
function mactrack_get_dns_from_ip($ip, $dns, $timeout = 1000) {
	/* random transaction number (for routers etc to get the reply back) */
	$data = rand(10, 99);

	/* trim it to 2 bytes */
	$data = substr($data, 0, 2);

	/* create request header */
	$data .= "\1\0\0\1\0\0\0\0\0\0";

	/* split IP into octets */
	$octets = explode('.', $ip);

	/* perform a quick error check */
	if (count($octets) != 4) return 'ERROR';

	/* needs a byte to indicate the length of each segment of the request */
	for ($x=3; $x>=0; $x--) {
		switch (strlen($octets[$x])) {
			case 1: // 1 byte long segment
				$data .= "\1"; break;
			case 2: // 2 byte long segment
				$data .= "\2"; break;
			case 3: // 3 byte long segment
				$data .= "\3"; break;
			default: // segment is too big, invalid IP
				return 'ERROR';
		}

		/* and the segment itself */
		$data .= $octets[$x];
	}

	/* and the final bit of the request */
	$data .= "\7in-addr\4arpa\0\0\x0C\0\1";

	/* create UDP socket */
	$handle = @fsockopen("udp://$dns", 53);

	@stream_set_timeout($handle, floor($timeout/1000), ($timeout*1000)%1000000);
	@stream_set_blocking($handle, 1);

	/* send our request (and store request size so we can cheat later) */
	$requestsize = @fwrite($handle, $data);

	/* get the response */
	$response = @fread($handle, 1000);

	/* check to see if it timed out */
	$info = stream_get_meta_data($handle);

	/* close the socket */
	@fclose($handle);

	if ($info['timed_out']) {
		return 'timed_out';
	}

	/* more error handling */
	if ($response == '') { return $ip; }

	/* parse the response and find the response type */
	$type = @unpack('s', substr($response, $requestsize+2));

	if ($type[1] == 0x0C00) {
		/* set up our variables */
		$host = '';
		$len = 0;

		/* set our pointer at the beginning of the hostname uses the request
		   size from earlier rather than work it out.
		*/
		$position = $requestsize + 12;

		/* reconstruct the hostname */
		do {
			/* get segment size */
			$len = unpack('c', substr($response, $position));

			/* null terminated string, so length 0 = finished */
			if ($len[1] == 0) {
				/* return the hostname, without the trailing '.' */
				return substr($host, 0, strlen($host) -1);
			}

			/* add the next segment to our host */
			$host .= substr($response, $position+1, $len[1]) . '.';

			/* move pointer on to the next segment */
			$position += $len[1] + 1;
		} while ($len != 0);

		/* error - return the hostname we constructed (without the . on the end) */
		return $ip;
	}

	/* error - return the hostname */
	return $ip;
}

/*  get_link_port_status - This function walks an the ip mib for ifIndexes with
  ip addresses aka link ports and then returns that list if ifIndexes with a
  true array value if an IP exists on that ifIndex.
*/
function get_link_port_status(&$device) {
	$return_array = array();

	$walk_array = cacti_snmp_walk($device['hostname'], $device['snmp_readstring'],
		'.1.3.6.1.2.1.4.20.1.2', $device['snmp_version'], $device['snmp_username'],
		$device['snmp_password'], $device['snmp_auth_protocol'],
		$device['snmp_priv_passphrase'], $device['snmp_priv_protocol'],
		$device['snmp_context'], $device['snmp_port'], $device['snmp_timeout'],
		$device['snmp_retries'], $device['max_oids']);

	if (cacti_sizeof($walk_array)) {
		foreach ($walk_array as $walk_item) {
			$return_array[$walk_item['value']] = true;
		}
	}

	return $return_array;
}

/*  xform_stripped_oid - This function walks an OID and then strips the seed OID
  from the complete oid.  It returns the stripped oid as the key and the return
  value as the value of the resulting array
*/
function xform_stripped_oid($oid, &$device, $snmp_readstring = '', $hex = false) {
	$return_array = array();

	if ($snmp_readstring == '') {
		$snmp_readstring = $device['snmp_readstring'];
	}

	if ($device['snmp_version'] == '3' && substr_count($snmp_readstring,'vlan-')) {
		$snmp_context = $snmp_readstring;
	} else {
		$snmp_context = $device['snmp_context'];
	}

	$walk_array = cacti_snmp_walk($device['hostname'], $snmp_readstring,
		$oid, $device['snmp_version'], $device['snmp_username'],
		$device['snmp_password'], $device['snmp_auth_protocol'],
		$device['snmp_priv_passphrase'], $device['snmp_priv_protocol'],
		$snmp_context, $device['snmp_port'], $device['snmp_timeout'],
		$device['snmp_retries'], $device['max_oids'],
		SNMP_POLLER, $device['snmp_engine_id'],
		($hex ? SNMP_STRING_OUTPUT_HEX : SNMP_STRING_OUTPUT_GUESS));

	$oid = preg_replace('/^\./', '', $oid);

	$i = 0;

	if (cacti_sizeof($walk_array)) {
		foreach ($walk_array as $walk_item) {
			$key = $walk_item['oid'];
			$key = str_replace('iso', '1', $key);
			$key = str_replace($oid . '.', '', $key);
			$return_array[$i]['key'] = $key;
			$return_array[$i]['value'] = $walk_item['value'];

			$i++;
		}
	}

	return array_rekey($return_array, 'key', 'value');
}

/*  xform_net_address - This function will return the IP address.  If the agent or snmp
  returns a differently formatted IP address, then this function will convert it to dotted
  decimal notation and return.
*/
function xform_net_address($ip_address) {
	$ip_address = trim($ip_address);

	if (substr_count($ip_address, 'Network Address:')) {
		$ip_address = trim(str_replace('Network Address:', '', $ip_address));
	}

	// Handle the binary format first
	$length = strlen($ip_address);
	if ($length == 4 or $length == 16) {
		return inet_ntop(pack('A' . $length, $ip_address));
	} else {
		// Adjust for HEX IP in form "0A 09 15 72"
		$ip_address = str_replace(' ', ':', $ip_address);

		if (substr_count($ip_address, ':') != 0) {
			if (strlen($ip_address) > 11) {
				/* ipv6, don't alter */
			} else {
				$newaddr = '';
				$address = explode(':', $ip_address);

				foreach($address as $index => $part) {
					$newaddr .= ($index == 0 ? '':'.') . hexdec($part);
				}

				$ip_address = $newaddr;
			}
		}

		return $ip_address;
	}
}

/*	xform_mac_address - This function will take a variable that is either formatted as
  hex or as a string representing hex and convert it to what the mactrack scanning
  function expects.
*/
function xform_mac_address($mac_address) {
	$max_address = trim($mac_address);

	$separator = read_config_option('mt_mac_delim');

	if ($mac_address == '') {
		$mac_address = 'NOT USER';
	} elseif (strlen($mac_address) > 10) { /* return is in ascii */
		$max_address = str_replace(
			array('HEX-00:', 'HEX-:', 'HEX-', '"', ' ', '-'),
			array('',        '',      '',     '',  ':', ':'),
			$mac_address
		);
	} else { /* return is hex */
		$mac = '';

		for ($j = 0; $j < strlen($mac_address); $j++) {
			$mac .= bin2hex($mac_address[$j]) . ':';
		}

		$mac_address = $mac;
	}

	$mac_address = str_replace(':', $separator, $max_address);

	return strtoupper($mac_address);
}

/*	xform_standard_indexed_data - This function takes an oid, and a device, and
  optionally an alternate snmp_readstring as input parameters and then walks the
  oid and returns the data in array[index] = value format.
*/
function xform_standard_indexed_data($xformOID, &$device, $snmp_readstring = '', $hex = false) {
	/* get raw index data */
	if ($snmp_readstring == '') {
		$snmp_readstring = $device['snmp_readstring'];
	}

	if ($device['snmp_version'] == '3' && substr_count($snmp_readstring,'vlan-')) {
		$snmp_context = $snmp_readstring;
	} else {
		$snmp_context = $device['snmp_context'];
	}

	$xformArray = cacti_snmp_walk($device['hostname'], $snmp_readstring,
		$xformOID, $device['snmp_version'], $device['snmp_username'],
		$device['snmp_password'], $device['snmp_auth_protocol'],
		$device['snmp_priv_passphrase'], $device['snmp_priv_protocol'],
		$snmp_context, $device['snmp_port'], $device['snmp_timeout'],
		$device['snmp_retries'], $device['max_oids'],
		SNMP_POLLER, $device['snmp_engine_id'],
		($hex ? SNMP_STRING_OUTPUT_HEX : SNMP_STRING_OUTPUT_GUESS));

	$i = 0;

	if (cacti_sizeof($xformArray)) {
		foreach($xformArray as $xformItem) {
			$perPos = strrpos($xformItem['oid'], '.');
			$xformItemID = substr($xformItem['oid'], $perPos+1);
			$xformArray[$i]['oid'] = $xformItemID;
			$i++;
		}
	}

	return array_rekey($xformArray, 'oid', 'value');
}

/*	xform_dot1q_vlan_associations - This function takes an OID, and a device, and
  optionally an alternate snmp_readstring as input parameters and then walks the
  OID and returns the data in array[index] = value format.
*/
function xform_dot1q_vlan_associations(&$device, $snmp_readstring = '') {
	/* get raw index data */
	if ($snmp_readstring == '') {
		$snmp_readstring = $device['snmp_readstring'];
	}

	/* initialize the output array */
	$output_array = array();

	/* obtain vlan associations */
	$xformArray = cacti_snmp_walk($device['hostname'], $snmp_readstring,
		'.1.3.6.1.2.1.17.7.1.2.2.1.2', $device['snmp_version'],
		$device['snmp_username'], $device['snmp_password'],
		$device['snmp_auth_protocol'], $device['snmp_priv_passphrase'],
		$device['snmp_priv_protocol'], $device['snmp_context'],
		$device['snmp_port'], $device['snmp_timeout'],
		$device['snmp_retries'], $device['max_oids']);

	$i = 0;

	if (cacti_sizeof($xformArray)) {
		foreach($xformArray as $xformItem) {
			/* peel off the beginning of the OID */
			$key = $xformItem['oid'];
			$key = str_replace('iso', '1', $key);
			$key = str_replace('1.3.6.1.2.1.17.7.1.2.2.1.2.', '', $key);

			/* now grab the VLAN */
			$perPos = strpos($key, '.');
			$output_array[$i]['vlan_id'] = substr($key,0,$perPos);

			/* save the key for association with the dot1d table */
			$output_array[$i]['key'] = substr($key, $perPos+1);
			$i++;
		}
	}

	return array_rekey($output_array, 'key', 'vlan_id');
}

/*	xform_cisco_workgroup_port_data - This function is specific to Cisco devices that
  use the last two OID values from each complete OID string to represent the switch
  card and port.  The function returns data in the format array[card.port] = value.
*/
function xform_cisco_workgroup_port_data($xformOID, &$device) {
	/* get raw index data */
	$xformArray = cacti_snmp_walk($device['hostname'], $device['snmp_readstring'],
		$xformOID, $device['snmp_version'], $device['snmp_username'],
		$device['snmp_password'], $device['snmp_auth_protocol'],
		$device['snmp_priv_passphrase'], $device['snmp_priv_protocol'],
		$device['snmp_context'], $device['snmp_port'],
		$device['snmp_timeout'], $device['snmp_retries'], $device['max_oids']);

	$i = 0;

	if (cacti_sizeof($xformArray)) {
		foreach($xformArray as $xformItem) {
			$perPos = strrpos($xformItem['oid'], '.');
			$xformItem_piece1 = substr($xformItem['oid'], $perPos+1);
			$xformItem_remainder = substr($xformItem['oid'], 0, $perPos);
			$perPos = strrpos($xformItem_remainder, '.');
			$xformItem_piece2 = substr($xformItem_remainder, $perPos+1);
			$xformArray[$i]['oid'] = $xformItem_piece2 . '/' . $xformItem_piece1;

			$i++;
		}
	}

	return array_rekey($xformArray, 'oid', 'value');
}

/*	xform_indexed_data - This function is similar to other the other xform_* functions
  in that it takes the end of each OID and uses the last $xformLevel positions as the
  index.  Therefore, if $xformLevel = 3, the return value would be as follows:
  array[1.2.3] = value.
*/
function xform_indexed_data($xformOID, &$device, $xformLevel = 1, $hex = false) {
	/* get raw index data */
	$xformArray = cacti_snmp_walk($device['hostname'], $device['snmp_readstring'],
		$xformOID, $device['snmp_version'], $device['snmp_username'],
		$device['snmp_password'], $device['snmp_auth_protocol'],
		$device['snmp_priv_passphrase'], $device['snmp_priv_protocol'],
		$device['snmp_context'], $device['snmp_port'],
		$device['snmp_timeout'], $device['snmp_retries'], $device['max_oids'],
		SNMP_POLLER, $device['snmp_engine_id'],
		($hex ? SNMP_STRING_OUTPUT_HEX : SNMP_STRING_OUTPUT_GUESS));

	$i = 0;
	$output_array = array();

	if (cacti_sizeof($xformArray)) {
		foreach($xformArray as $xformItem) {
			/* break down key */
			$OID = $xformItem['oid'];
			for ($j = 0; $j < $xformLevel; $j++) {
				$perPos = strrpos($OID, '.');
				$xformItem_piece[$j] = substr($OID, $perPos+1);
				$OID = substr($OID, 0, $perPos);
			}

			/* reassemble key */
			$key = '';
			for ($j = $xformLevel-1; $j >= 0; $j--) {
				$key .= $xformItem_piece[$j];
				if ($j > 0) {
					$key .= '.';
				}
			}

			$output_array[$i]['key'] = $key;
			$output_array[$i]['value'] = $xformItem['value'];

			$i++;
		}
	}

	return array_rekey($output_array, 'key', 'value');
}

/*	db_process_add - This function adds a process to the process table with the entry
  with the device_id as key.
*/
function db_process_add($device_id, $storepid = false) {
    /* store the PID if required */
	if ($storepid) {
		$pid = getmypid();
	} else {
		$pid = 0;
	}

	/* store pseudo process id in the database */
	db_execute_prepared('REPLACE INTO mac_track_processes
		(device_id, process_id, status, start_date)
		VALUES (?, ?, "Running", NOW())',
		array($device_id, $pid));
}

/*	db_process_remove - This function removes a devices entry from the processes
  table indicating that the device is done processing and the next device may start.
*/
function db_process_remove($device_id) {
	db_execute_prepared('DELETE FROM mac_track_processes
		WHERE device_id= ?',
		array($device_id));
}

/*	db_update_device_status - This function is used by the scanner to save the status
  of the current device including the number of ports, it's readstring, etc.
*/
function db_update_device_status(&$device, $host_up, $scan_date, $start_time) {
	global $debug;

	$end_time = microtime(true);
	$runduration = $end_time - $start_time;

	if ($host_up == true) {
		db_execute_prepared('UPDATE mac_track_devices
			SET ports_total = ?, device_type_id = ?, scan_type = ?, vlans_total = ?,
			ports_active = ?, ports_trunk = ?, macs_active = ?, snmp_version = ?,
			snmp_readstring = ?, snmp_port = ?, snmp_timeout = ?, snmp_retries = ?,
			max_oids = ?, snmp_username = ?, snmp_password = ?, snmp_auth_protocol = ?,
			snmp_priv_passphrase = ?, snmp_priv_protocol = ?, snmp_context = ?, snmp_sysName = ?,
			snmp_sysLocation = ?, snmp_sysContact = ?, snmp_sysObjectID = ?, snmp_sysDescr = ?,
			snmp_sysUptime = ?, snmp_status = ?, last_runmessage = ?, last_rundate = ?,
			last_runduration = ?
			WHERE device_id = ?',
			array(
				$device['ports_total'], $device['device_type_id'], $device ['scan_type'], $device['vlans_total'],
				$device['ports_active'], $device['ports_trunk'], $device['macs_active'], $device['snmp_version'],
				$device['snmp_readstring'],  $device['snmp_port'], $device['snmp_timeout'], $device['snmp_retries'],
				$device['max_oids'],  $device['snmp_username'], $device['snmp_password'], $device['snmp_auth_protocol'],
				$device['snmp_priv_passphrase'], $device['snmp_priv_protocol'],  $device['snmp_context'], $device['snmp_sysName'],
				$device['snmp_sysLocation'], $device['snmp_sysContact'], $device['snmp_sysObjectID'], $device['snmp_sysDescr'],
				$device['snmp_sysUptime'], $device['snmp_status'], $device['last_runmessage'], $scan_date,
				round($runduration,4), $device['device_id']
			)
		);
	} else {
		db_execute_prepared('UPDATE mac_track_devices
			SET snmp_status = ?, device_type_id = ?, scan_type = ?, vlans_total = 0,
			ports_active = 0, ports_trunk = 0, macs_active = 0, last_runmessage = "Device Unreachable",
			last_rundate = ?,  last_runduration = ?
			WHERE device_id =?',
			array(
				$device['snmp_status'],
				$device['device_type_id'],
				$device ['scan_type'],
				$scan_date,
				round($runduration,4),
				$device['device_id']
			)
		);
	}
}

/*	db_store_device_results - This function stores each of the port results into
  the temporary port results table for future processes once all devices have been
  scanned.
*/
function db_store_device_port_results(&$device, $port_array, $scan_date) {
	global $debug;

	/* output details to database */
	if (cacti_sizeof($port_array)) {
		foreach($port_array as $port_value) {
			if ($port_value['port_number'] <> 'NOT USER' && $port_value['mac_address'] <> 'NOT USER' && $port_value['mac_address'] != '') {
				$mac_authorized = db_check_auth($port_value['mac_address']);

				mactrack_debug('MAC Address \'' . $port_value['mac_address'] . '\' on device \'' . $device['device_name'] . '\' is ' . ($mac_authorized != '' ? '':'NOT') . ' Authorized');

				if ($mac_authorized != '') {
					$authorized_mac = 1;
				} else {
					$authorized_mac = 0;
				}

				if (!isset($port_value['vlan_id'])) {
					$port_value['vlan_id'] = 'N/A';
				}

				if (!isset($port_value['vlan_name'])) {
					$port_value['vlan_name'] = 'N/A';
				}

				db_execute_prepared('REPLACE INTO mac_track_temp_ports
					(site_id,device_id,hostname,device_name,vlan_id,vlan_name,
					mac_address,port_number,port_name,scan_date,authorized)
					VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
					array(
						$device['site_id'],
						$device['device_id'],
						$device['hostname'],
						$device['device_name'],
						$port_value['vlan_id'],
						$port_value['vlan_name'],
						$port_value['mac_address'],
						$port_value['port_number'],
						$port_value['port_name'],
						$scan_date,
						$authorized_mac
					)
				);
			}
		}
	}
}

/* db_check_auth - This function checks whether the mac address exists in the mac_track+macauth table
*/
function db_check_auth($mac_address) {
	$query = db_fetch_cell_prepared('SELECT mac_id
		FROM mac_track_macauth
		WHERE mac_address
		LIKE ?',
		array('%' . $mac_address . '%'));

	return $query;
}

/* db_check_for_ip - This function checks whether the mac address has a matching IP address in the mac_track_arp table
*/
function db_check_for_ip($mac_address) {
	$query = db_fetch_cell_prepared('SELECT ip_address
		FROM mac_track_arp
		WHERE mac_address
		LIKE ?',
		array('%' . $mac_address . '%'));
	return $query;
}

/*	perform_mactrack_db_maint - This utility removes stale records from the database.
*/
function perform_mactrack_db_maint() {
	global $database_default;

	/* remove stale records from the poller database */
	$retention = read_config_option('mt_data_retention');
	if (is_numeric($retention)) {
		$retention_date = date('Y-m-d H:i:s', time() - ($retention *  86400));
		$days           = $retention;
	} else {
		switch ($retention) {
		case '2days':
			$retention_date = date('Y-m-d H:i:s', strtotime('-2 Days'));
			break;
		case '5days':
			$retention_date = date('Y-m-d H:i:s', strtotime('-5 Days'));
			break;
		case '1week':
			$retention_date = date('Y-m-d H:i:s', strtotime('-1 Week'));
			break;
		case '2weeks':
			$retention_date = date('Y-m-d H:i:s', strtotime('-2 Week'));
			break;
		case '3weeks':
			$retention_date = date('Y-m-d H:i:s', strtotime('-3 Week'));
			break;
		case '1month':
			$retention_date = date('Y-m-d H:i:s', strtotime('-1 Month'));
			break;
		case '2months':
			$retention_date = date('Y-m-d H:i:s', strtotime('-2 Months'));
			break;
		default:
			$retention_date = date('Y-m-d H:i:s', strtotime('-2 Days'));
		}

		$days = ceil((time() - strtotime($retention_date)) / 86400);
	}

	set_config_option('mt_data_retention', $days);

	mactrack_debug('Started deleting old records from the main database.');

	$syntax = db_fetch_row('SHOW CREATE TABLE mac_track_ports');
	if (substr_count($syntax['Create Table'], 'PARTITION')) {
		$partitioned = true;
	} else {
		$partitioned = false;
	}

	/* delete old syslog and syslog soft messages */
	if ($retention > 0 || $partitioned) {
		if (!$partitioned) {
			db_execute_prepared('DELETE QUICK FROM mac_track_ports WHERE scan_date < ?', array($retention_date));
			db_execute('OPTIMIZE TABLE mac_track_ports');
		} else {
			$syslog_deleted = 0;
			$number_of_partitions = db_fetch_assoc_prepared('SELECT *
				FROM `information_schema`.`partitions`
				WHERE table_schema = ?
				AND table_name="mac_track_ports"
				ORDER BY partition_ordinal_position',
				array($database_default));

			$time     = time();
			$now      = date('Y-m-d', $time);
			$format   = date('Ymd', $time);
			$cur_day  = db_fetch_row("SELECT TO_DAYS('$now') AS today");
			$cur_day  = $cur_day['today'];

			$lday_ts  = read_config_option('mactrack_lastday_timestamp');
			$lnow     = date('Y-m-d', $lday_ts);
			$lformat  = date('Ymd', $lday_ts);
			$last_day = db_fetch_row("SELECT TO_DAYS('$lnow') AS today");
			$last_day = $last_day['today'];

			mactrack_debug("There are currently '" . cacti_sizeof($number_of_partitions) . "' Mactrack Partitions, We will keep '$days' of them.");
			mactrack_debug("The current day is '$cur_day', the last day is '$last_day'");

			if ($cur_day != $last_day) {
				set_config_option('mactrack_lastday_timestamp', $time);

				if ($lday_ts != '') {
					cacti_log("MACTRACK: Creating new partition 'd" . $lformat . "'", false, "SYSTEM");
					mactrack_debug("Creating new partition 'd" . $lformat . "'");
					db_execute("ALTER TABLE mac_track_ports REORGANIZE PARTITION dMaxValue INTO (
						PARTITION d" . $lformat . " VALUES LESS THAN (TO_DAYS('$lnow')),
						PARTITION dMaxValue VALUES LESS THAN MAXVALUE)");

					if ($days > 0) {
						$user_partitions = cacti_sizeof($number_of_partitions) - 1;
						if ($user_partitions >= $days) {
							$i = 0;
							while ($user_partitions > $days) {
								$oldest = $number_of_partitions[$i];
								cacti_log("MACTRACK: Removing old partition 'd" . $oldest["PARTITION_NAME"] . "'", false, "SYSTEM");
								mactrack_debug("Removing partition '" . $oldest['PARTITION_NAME'] . "'");
								db_execute("ALTER TABLE mac_track_ports DROP PARTITION " . $oldest['PARTITION_NAME']);
								$i++;
								$user_partitions--;
								$mactrack_deleted++;
							}
						}
					}
				}
			}
		}
	}

	db_execute('REPLACE INTO mac_track_scan_dates
		(SELECT DISTINCT scan_date FROM mac_track_ports)');

	db_execute('DELETE FROM mac_track_scan_dates
		WHERE scan_date NOT IN (
			SELECT DISTINCT scan_date
			FROM mac_track_ports
		)');

	mactrack_debug('Finished deleting old records from the main database.');
}

function import_oui_database($type = 'ui', $oui_file = 'http://standards-oui.ieee.org/oui.txt') {
	$oui_alternate = 'https://services13.ieee.org/RST/standards-ra-web/rest/assignments/download/?registry=MA-L&format=txt';
	if ($type != 'ui') {
		html_start_box(__('Mactrack Device Tracking OUI Database Import Results', 'mactrack'), '100%', '', '1', 'center', '');
		print '<tr><td>' . __('Getting OUI Database from IEEE', 'mactrack') . '</td></tr>';
	} else {
		print __('Getting OUI Database from the IEEE', 'mactrack') . PHP_EOL;
	}

	$oui_database = file($oui_file);

	if ($type != 'ui') print '<tr><td>';

	if (is_array($oui_database)) {
		print __('OUI Database Download from IEEE Complete', 'mactrack') . PHP_EOL;
	} else {
		print __('OUI Database Download from IEEE FAILED', 'mactrack') . PHP_EOL;
	}

	if ($type != 'ui') print '</td></tr>';

	if (is_array($oui_database)) {
		db_execute('UPDATE mac_track_oui_database SET present=0');

		/* initialize some variables */
		$begin_vendor = false;
		$vendor_mac     = '';
		$vendor_name    = '';
		$vendor_address = '';
		$i = 0;
		$sql = '';

		if ($type != 'ui') print '<tr><td>';

		if (cacti_sizeof($oui_database)) {
			foreach ($oui_database as $row) {
				$row = str_replace("\t", ' ', $row);
				if ($begin_vendor && trim($row) == '') {
					if (substr($vendor_address,0,1) == ',') $vendor_address = substr($vendor_address,1);
					if (substr($vendor_name,0,1) == ',')    $vendor_name    = substr($vendor_name,1);

					$sql .= ($sql != '' ? ',':'') .
						'(' .
						db_qstr($vendor_mac) . ', ' .
						db_qstr(ucwords(strtolower($vendor_name))) . ', ' .
						db_qstr(str_replace("\n", ', ', ucwords(strtolower(trim($vendor_address))))) . ', 1)';

					/* let the user know you are working */
					if ((($i % 1000) == 0) && ($type == 'ui')) {
						print '.';

						db_execute('REPLACE INTO mac_track_oui_database
							(vendor_mac, vendor_name, vendor_address, present)
							VALUES ' . $sql);

						$sql = '';
					}

					$i++;

					/* reinitialize variables */
					$begin_vendor   = false;
					$vendor_mac     = '';
					$vendor_name    = '';
					$vendor_address = '';
				} else {
					if ($begin_vendor) {
						if (strpos($row, '(base 16)')) {
							$address_start = strpos($row, '(base 16)') + 10;
							$vendor_address .= trim(substr($row,$address_start)) . "\n";
						} else {
							$vendor_address .= trim($row) . "\n";
						}
					} else {
						$vendor_address = '';
					}
				}

				if (substr_count($row, '(hex)')) {
					$begin_vendor = true;
					$vendor_mac = str_replace('-', ':', substr(trim($row), 0, 8));
					$hex_end = strpos($row, '(hex)') + 5;
					$vendor_name= trim(substr($row,$hex_end));
				}
			}
		}

		if ($sql != '') {
			db_execute('REPLACE INTO mac_track_oui_database
				(vendor_mac, vendor_name, vendor_address, present)
				VALUES ' . $sql);
		}

		if ($type != 'ui') print '</td></tr>';

		/* count bogus records */
		$j = db_fetch_cell('SELECT count(*) FROM mac_track_oui_database WHERE present=0');

		/* get rid of old records */
		db_execute('DELETE FROM mac_track_oui_database WHERE present=0');

		/* report some information */
		if ($type != 'ui') print '<tr><td>';
		print PHP_EOL . __('There were \'%d\' Entries Added/Updated in the database.', $i, 'mactrack');
		if ($type != 'ui') print '</td></td><tr><td>';
		print PHP_EOL . __('There were \'%d\' Records Removed from the database.', $j, 'mactrack') . PHP_EOL;
		if ($type != 'ui') print '</td></tr>';

		if ($type != 'ui') html_end_box();
	}
}

function get_netscreen_arp_table($site, &$device) {
	global $debug, $scan_date;

	/* get the atifIndexes for the device */
	$atifIndexes = xform_indexed_data('.1.3.6.1.2.1.3.1.1.1', $device, 6);

	if (cacti_sizeof($atifIndexes)) {
		$ifIntcount = 1;
	} else {
		$ifIntcount = 0;
	}

	if ($ifIntcount != 0) {
		$atifIndexes = xform_indexed_data('.1.3.6.1.2.1.4.22.1.1', $device, 5);
	}
	mactrack_debug(__('atifIndexes data collection complete', 'mactrack'));

	/* get the atPhysAddress for the device */
	if ($ifIntcount != 0) {
		$atPhysAddress = xform_indexed_data('.1.3.6.1.2.1.4.22.1.2', $device, 5, true);
	} else {
		$atPhysAddress = xform_indexed_data('.1.3.6.1.2.1.3.1.1.2', $device, 6, true);
	}

	/* convert the mac address if necessary */
	$keys = array_keys($atPhysAddress);
	$i = 0;
	if (cacti_sizeof($atPhysAddress)) {
		foreach($atPhysAddress as $atAddress) {
			$atPhysAddress[$keys[$i]] = xform_mac_address($atAddress);
			$i++;
		}
	}
	mactrack_debug(__('atPhysAddress data collection complete', 'mactrack'));

	/* get the atPhysAddress for the device */
	if ($ifIntcount != 0) {
		$atNetAddress = xform_indexed_data('.1.3.6.1.2.1.4.22.1.3', $device, 5);
	} else {
		$atNetAddress = xform_indexed_data('.1.3.6.1.2.1.3.1.1.3', $device, 6);
	}
	mactrack_debug(__('atNetAddress data collection complete', 'mactrack'));

	/* get the ifNames for the device */
	$keys = array_keys($atifIndexes);
	$i = 0;
	if (cacti_sizeof($atifIndexes)) {
	foreach($atifIndexes as $atifIndex) {
		$atEntries[$i]['atifIndex']     = $atifIndex;
		$atEntries[$i]['atPhysAddress'] = $atPhysAddress[$keys[$i]];
		$atEntries[$i]['atNetAddress']  = xform_net_address($atNetAddress[$keys[$i]]);
		$i++;
	}
	}
	mactrack_debug(__('atEntries assembly complete.', 'mactrack'));

	/* output details to database */
	if (cacti_sizeof($atEntries)) {
		foreach($atEntries as $atEntry) {
			db_execute_prepared('REPLACE INTO mac_track_ips
				(site_id,device_id,hostname,device_name,port_number,
				mac_address,ip_address,scan_date)
				VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
				array(
					$device['site_id'],
					$device['device_id'],
					$device['hostname'],
					$device['device_name'],
					$atEntry['atifIndex'],
					$atEntry['atPhysAddress'],
					$atEntry['atNetAddress'],
					$scan_date
				)
			);
		}
	}

	/* save ip information for the device */
	$device['ips_total'] = cacti_sizeof($atEntries);

	db_execute_prepared('UPDATE mac_track_devices
		SET ips_total = ?
		WHERE device_id = ?',
		array($device['ips_total'], $device['device_id']));

	mactrack_debug(__('HOST: %s, IP address information collection complete', $device['hostname'], 'mactrack'));
}

function mactrack_interface_actions($device_id, $ifIndex, $show_rescan = true) {
	global $config;

	$row    = '';
	$rescan = '';

	$device = db_fetch_row_prepared('SELECT host_id, disabled
		FROM mac_track_devices
		WHERE device_id = ?',
		array($device_id));

	if ($show_rescan) {
		if (api_user_realm_auth('mactrack_sites.php')) {
			if ($device['disabled'] == '') {
				$rescan = "<a class='pic rescan' id='r_" . $device_id . '_' . str_replace(' ', ':', $ifIndex) . "' title='" . __esc('Rescan Device', 'mactrack') . "'><i class='mtSync fa fa-sync'></i></a>";
			}
		}
	}

	if ($device['host_id'] != 0) {
		/* get non-interface graphs */
		$graphs = db_fetch_assoc_prepared('SELECT DISTINCT gl.id AS local_graph_id
			FROM mac_track_interface_graphs AS mtig
			RIGHT JOIN graph_local AS gl
			ON gl.host_id=mtig.host_id
			AND gl.id=mtig.local_graph_id
			WHERE gl.host_id = ?
			AND mtig.device_id IS NULL',
			array($device['host_id']));

		if (cacti_sizeof($graphs)) {
			$url  = $config['url_path'] . 'plugins/mactrack/mactrack_view_graphs.php?action=preview&report=graphs&style=selective&graph_list=';
			$list = '';
			foreach($graphs as $graph) {
				$list .= ($list != '' ? ',': '') . $graph['local_graph_id'];
			}

			$row .= "<a class='pic' href='" . htmlspecialchars($url . $list . '&page=1') . "' title='" .  __esc('View Non Interface Graphs', 'mactrack') . "'><i class='mtChart fas fa-chart-line'></i></a>";
		} else {
			$row .= "<i class='mtChartDisabled fas fa-chart-line'  title='" . __esc('No Non Interface Graphs in Cacti', 'mactrack') . "'></i>";
		}

		/* get interface graphs */
		$graphs = db_fetch_assoc_prepared('SELECT local_graph_id
			FROM mac_track_interface_graphs
			WHERE host_id = ?
			AND ifIndex = ?',
			array($device['host_id'], $ifIndex));

		if (cacti_sizeof($graphs)) {
			$url  = $config['url_path'] . 'plugins/mactrack/mactrack_view_graphs.php?action=preview&report=graphs&style=selective&graph_list=';
			$list = '';
			foreach($graphs as $graph) {
				$list .= ($list != '' ? ',': '') . $graph['local_graph_id'];
			}

			$row .= "<a class='pic' href='" . htmlspecialchars($url . $list . '&page=1') . "' title='" . __esc('View Interface Graphs', 'mactrack') . "'><i class='mtChart fas fa-chart-line'></i></a>";
		} else {
			$row .= "<i class='mcChartDisabled fas fa-chart-line'  title='" . __esc('No Interface Graphs in Cacti', 'mactrack') . "'></i>";
		}
	}

	$row .= $rescan;

	return $row;
}

function mactrack_format_interface_row($stat) {
	global $config;

	/* we will make a row string */
	$row = '';

	/* calculate a human readable uptime */
	if ($stat['ifLastChange'] == 0) {
		$upTime = __('Since Restart', 'mactrack');
	} else {
		if ($stat['ifLastChange'] > $stat['sysUptime']) {
			$upTime = __('Since Restart', 'mactrack');
		} else {
			$time = $stat['sysUptime'] - $stat['ifLastChange'];
			$days      = intval($time / (60*60*24*100));
			$remainder = $time % (60*60*24*100);
			$hours     = intval($remainder / (60*60*100));
			$remainder = $remainder % (60*60*100);
			$minutes   = intval($remainder / (60*100));
			$upTime    = $days . 'd:' . $hours . 'h:' . $minutes . 'm';
		}
	}

	ob_start();

	form_selectable_cell(mactrack_interface_actions($stat['device_id'], $stat['ifIndex']), $stat['device_id']);
	form_selectable_cell($stat['device_name'], $stat['device_id']);
	form_selectable_cell(strtoupper($stat['device_type']), $stat['device_id']);
	form_selectable_cell($stat['ifName'], $stat['device_id']);
	form_selectable_cell($stat['ifDescr'], $stat['device_id']);
	form_selectable_cell($stat['ifAlias'], $stat['device_id']);
	form_selectable_cell(round($stat['inBound'],1) . ' %', $stat['device_id'], '', 'right');
	form_selectable_cell(round($stat['outBound'],1) . ' %', $stat['device_id'], '', 'right');
	form_selectable_cell(mactrack_display_Octets($stat['int_ifHCInOctets']), $stat['device_id'], '', 'right');
	form_selectable_cell(mactrack_display_Octets($stat['int_ifHCOutOctets']), $stat['device_id'], '', 'right');

	if (get_request_var('totals') == 'true' || get_request_var('totals') == 'on') {
		form_selectable_cell($stat['ifInErrors'], $stat['device_id'], '', 'right');
		form_selectable_cell($stat['ifInDiscards'], $stat['device_id'], '', 'right');
		form_selectable_cell($stat['ifInUnknownProtos'], $stat['device_id'], '', 'right');
		form_selectable_cell($stat['ifOutErrors'], $stat['device_id'], '', 'right');
		form_selectable_cell($stat['ifOutDiscards'], $stat['device_id'], '', 'right');
	} else {
		form_selectable_cell(round($stat['int_ifInErrors'],1), $stat['device_id'], '', 'right');
		form_selectable_cell(round($stat['int_ifInDiscards'],1), $stat['device_id'], '', 'right');
		form_selectable_cell(round($stat['int_ifInUnknownProtos'],1), $stat['device_id'], '', 'right');
		form_selectable_cell(round($stat['int_ifOutErrors'],1), $stat['device_id'], '', 'right');
		form_selectable_cell(round($stat['int_ifOutDiscards'],1), $stat['device_id'], '', 'right');
	}

	form_selectable_cell($stat['ifOperStatus'] == 1 ? __('Up', 'mactrack'):__('Down', 'mactrack'), $stat['device_id'], '', 'right');
	form_selectable_cell($upTime, $stat['device_id'], '', 'right');
	form_selectable_cell(mactrack_date($stat['last_rundate']), $stat['device_id'], '', 'right');

	return ob_get_clean();
}

function mactrack_format_dot1x_row($port_result) {
	global $config,$mactrack_device_status;

	/* we will make a row string */
	$row = '';

	if (get_request_var('scan_date') != 3) {
		$scan_date = $port_result['scan_date'];
	} else {
		$scan_date = $port_result['max_scan_date'];
	}

	$status = 'Unknown';
	if (array_key_exists($port_result['status'],$mactrack_device_status)) {
		$status = $mactrack_device_status[$port_result['status']];
	}

	$row .= "<td class='nowrap'>" . mactrack_interface_actions($port_result['device_id'], $port_result['port_number']) . '</td>';
	$row .= '<td><b>' . $port_result['device_name'] . '</b></td>';
	$row .= '<td>'    . $port_result['hostname']    . '</td>';
	$row .= '<td><b>' . $port_result['username']    . '</b></td>';
	$row .= '<td>'    . $port_result['ip_address']  . '</td>';

	if (read_config_option('mt_reverse_dns') != '') {
		$row .= '<td>' . $port_result['dns_hostname']  . '</td>';
	}

	$row .= '<td>'    . $port_result['mac_address'] . '</td>';
	$row .= '<td>'    . $port_result['ifName']      . '</td>';
	$row .= '<td><b>' . ($port_result['domain'] == 2 ? __('Data', 'mactrack'):__('Voice', 'mactrack')) . '</b></td>';
	$row .= '<td><b>' . $status . '</b></td>';
	$row .= "<td class='nowrap'>" . $scan_date . '</td>';

	return $row;
}

function mactrack_display_Octets($octets) {
	$suffix = '';
	while ($octets > 1024) {
		$octets = $octets / 1024;
		switch($suffix) {
		case '':
			$suffix = 'k';
			break;
		case 'k':
			$suffix = 'm';
			break;
		case 'M':
			$suffix = 'G';
			break;
		case 'G':
			$suffix = 'P';
			break 2;
		default:
			$suffix = '';
			break 2;
		}
	}

	$octets = round($octets,4);
	$octets = substr($octets,0,5);

	return $octets . ' ' . $suffix;
}

function mactrack_rescan($web = false) {
	global $config;

	$device_id = get_request_var('device_id');
	$ifIndex   = get_request_var('ifIndex');

	$dbinfo = db_fetch_row_prepared('SELECT *
		FROM mac_track_devices
		WHERE device_id = ?',
		array($device_id));

	$data = array();

	if (cacti_sizeof($dbinfo)) {
		if ($dbinfo['disabled'] == '') {
			/* log the transaction to the database */
			mactrack_log_action(__('Device Rescan \'%s\'', $dbinfo['hostname'], 'mactrack'));

			/* create the command script */
			$command_string = $config['base_path'] . '/plugins/mactrack/mactrack_scanner.php';
			$extra_args     = ' -id=' . $dbinfo['device_id'] . ($web ? ' --web':'');

			/* print out the type, and device_id */
			$data['device_id'] = get_request_var('device_id');
			$data['ifIndex']   = $ifIndex;

			/* add the cacti header */
			ob_start();

			/* execute the command, and show the results */
			$command = read_config_option('path_php_binary') . ' -q ' . $command_string . $extra_args;
			passthru($command);

			$data['content'] = ob_get_clean();
		}
	}

	header('Content-Type: application/json; charset=utf-8');

	print json_encode($data);
}

function mactrack_site_scan($web = false) {
	global $config, $web;

	$site_id = get_request_var('site_id');

	$dbinfo  = db_fetch_row_prepared('SELECT *
		FROM mac_track_sites
		WHERE site_id = ?',
		array($site_id));

	$data = array();

	if (cacti_sizeof($dbinfo)) {
		/* log the transaction to the database */
		mactrack_log_action(__('Site scan \'%s\'', $dbinfo['site_name'], 'mactrack'));

		/* create the command script */
		$command_string = $config['base_path'] . '/plugins/mactrack/poller_mactrack.php';
		$extra_args     = ' --web -sid=' . $dbinfo['site_id'];

		/* print out the type, and device_id */
		$data['site_id'] = $site_id;

		/* add the cacti header */
		ob_start();

		/* execute the command, and show the results */
		$command = read_config_option('path_php_binary') . ' -q ' . $command_string . $extra_args;
		passthru($command);

		$data['content'] = ob_get_clean();
	}

	header('Content-Type: application/json; charset=utf-8');

	print json_encode($data);
}

function mactrack_enable() {
	/* ================= input validation ================= */
	get_filter_request_var('device_id');
	/* ==================================================== */

	$dbinfo = db_fetch_row_prepared('SELECT *
		FROM mac_track_devices
		WHERE device_id = ?',
		array(get_request_var('device_id')));

	$data = array();

	/* log the transaction to the database */
	mactrack_log_action(__('Device Enable \'%s\'', $dbinfo['hostname'], 'mactrack'));

	db_execute_prepared('UPDATE mac_track_devices
		SET disabled = ""
		WHERE device_id = ?',
		array(get_request_var('device_id')));

	/* get the new html */
	$html = mactrack_format_device_row($dbinfo);

	/* send the response back to the browser */
	$data['device_id'] = get_request_var('device_id');
	$data['content']   = $html;

	header('Content-Type: application/json; charset=utf-8');

	print json_encode($data);
}

function mactrack_disable() {
	/* ================= input validation ================= */
	get_filter_request_var('device_id');
	/* ==================================================== */

	$dbinfo = db_fetch_row_prepared('SELECT *
		FROM mactrack_devices
		WHERE device_id = ?',
		array(get_request_var('device_id')));

	$data = array();

	/* log the transaction to the database */
	mactrack_log_action(__('Device Disable \'%d\'', $dbinfo['hostname'], 'mactrack'));

	db_execute_prepared('UPDATE mactack_devices
		SET disabled="on"
		WHERE device_id = ?',
		array(get_request_var('device_id')));

	/* get the new html */
	$html = mactrack_format_device_row($stat);

	/* send the response back to the browser */
	$data['device_id'] = get_request_var('device_id');
	$data['content']   = $html;

	header('Content-Type: application/json; charset=utf-8');

	print json_encode($data);
}

function mactrack_log_action($message) {
	$user = db_fetch_row_prepared('SELECT username, full_name
		FROM user_auth
		WHERE id = ?',
		array($_SESSION['sess_user_id']));

	cacti_log('MACTRACK: ' . $message . ", by '" . $user['full_name'] . '(' . $user['username'] . ")'", false, 'SYSTEM');
}

function mactrack_date($date) {
	$year = date('Y');
	return (substr_count($date, $year) ? substr($date,5) : $date);
}

function mactrack_int_row_class($stat) {
	if ($stat['int_errors_present'] == '1') {
		return 'int_errors';
	} elseif ($stat['int_discards_present'] == '1') {
		return 'int_discards';
	} elseif ($stat['ifOperStatus'] == '1' && $stat['ifAlias'] == '') {
		return 'int_up_wo_alias';
	} elseif ($stat['ifOperStatus'] == '0') {
		return 'int_down';
	} else {
		return 'int_up';
	}
}

function mactrack_dot1x_row_class($port_result) {
	if ($port_result['status'] == '7') {
		return 'dot1x_authn_failed';
	} elseif ($port_result['status'] == '5') {
		return 'dot1x_auth_failed';
	} elseif ($port_result['status'] == '3') {
		return 'dot1x_auth_no_method';
	} elseif ($port_result['status'] == '2') {
		return 'dot1x_running';
	} elseif ($port_result['status'] == '1') {
		return 'dot1x_idle';
	} elseif ($port_result['status'] == '4') {
		return 'dot1x_auth_success';
	} else {
		return 'dot1x_authn_success';
	}
}

/* mactrack_create_sql_filter - this routine will take a filter string and process it into a
     sql where clause that will be returned to the caller with a formatted SQL where clause
     that can then be integrated into the overall where clause.
     The filter takes the following forms.  The default is to find occurrence that match "all"
     Any string prefixed by a "-" will mean "exclude" this search string.  Boolean expressions
     are currently not supported.
   @arg $filter - (string) The filter provided by the user
   @arg $fields - (array) A list of field names to include in the where clause. They can also
     contain the table name in cases where joins are important.
   @returns - (string) The formatted SQL syntax */
function mactrack_create_sql_filter($filter, $fields) {
	$query = '';

	/* field names are required */
	if (!cacti_sizeof($fields)) return;

	/* the filter must be non-blank */
	if ($filter == '') {
		return;
	}

	$elements = explode(' ', $filter);

	foreach($elements as $element) {
		if (substr($element, 0, 1) == '-') {
			$filter   = substr($element, 1);
			$type     = 'NOT';
			$operator = 'AND';
		} else {
			$filter   = $element;
			$type     = '';
			$operator = 'OR';
		}

		$field_no = 1;
		foreach ($fields as $field) {
			if ($field_no == 1 && $query != '') {
				$query .= ') AND (';
			} elseif ($field_no == 1) {
				$query .= '(';
			}

			$query .= ($field_no == 1 ? '':" $operator ") . "($field $type LIKE '%" . $filter . "%')";

			$field_no++;
		}
	}

	return $query . ')';
}

function mactrack_display_hours($value) {
	if ($value == '' || $value == 'disabled') {
		return __('N/A', 'mactrack');
	} elseif ($value < 60) {
		return __('%d Minutes', round($value,0), 'mactrack');
	} else {
		$value = $value / 60;
		if ($value < 24) {
			return __('%d Hours', round($value,0), 'mactrack');
		} else {
			$value = $value / 24;
			if ($value < 7) {
				return __('%d Days', round($value,0), 'mactrack');
			} else {
				$value = $value / 7;
				return __('%d Weeks', round($value,0), 'mactrack');
			}
		}
	}
}

function mactrack_display_stats() {
	/* check if scanning is running */
	$processes = db_fetch_cell('SELECT COUNT(*) FROM mac_track_processes');
	$timing    = read_config_option('mt_collection_timing', true);
	$frequency = 0;

	if ($timing != 'disabled') {
		$frequency = $timing * 60;
	}

	$mactrack_stats = read_config_option('stats_mactrack', true);

	$time  = __('Not Recorded', 'mactrack');
	$proc  = __('N/A', 'mactrack');
	$devs  = __('N/A', 'mactrack');
	if ($mactrack_stats != '') {
		$stats = explode(' ', $mactrack_stats);

		if (cacti_sizeof($stats == 3)) {
			$time = explode(':', $stats[0]);
			$time = $time[1];
			$time = round($time, 1);

			$proc = explode(':', $stats[1]);
			$proc = $proc[1];

			$devs = explode(':', $stats[2]);
			$devs = $devs[1];
		}
	}

	if ($processes > 0) {
		$message = __('Status: Running, Processes: %d, Progress: %s, LastRuntime: %2.1f', $processes, read_config_option('mactrack_process_status', true), $time, 'mactrack');
	} else {
		$message = __('Status: Idle, LastRuntime: %2.1f seconds, Processes: %d processes, Devices: %d, Next Run Time: %s',
			$time, $proc , $devs,
			($timing != 'disabled' ? date('Y-m-d H:i:s', strtotime(read_config_option('mt_scan_date', true)) + $frequency):__('Disabled', 'mactrack')), 'mactrack');
	}

	html_start_box('', '100%', '', '3', 'center', '');

	print '<tr class="tableRow">';
	print '<td>' . __('Scanning Rate: Every %s', mactrack_display_hours(read_config_option('mt_collection_timing')), 'mactrack') . ', ' . $message . '</td>';
	print '</tr>';

	html_end_box();
}

function mactrack_legend_row($class, $text) {
	print "<td width='16.67%' class='$class' style='text-align:center;;'>$text</td>";
}

function mactrack_format_device_row($device, $actions=false) {
	global $config, $mactrack_device_types;

	/* viewer level */
	if ($actions) {
		$row = "<a class='pic' href='" . htmlspecialchars($config['url_path'] . 'plugins/mactrack/mactrack_interfaces.php?device_id=' . $device['device_id'] . '&issues=0&page=1') . "' title='" . __('View Interfaces', 'mactrack') . "'><i class='mtRanges fas fa-sitemap'></i></a>";

		/* admin level */
		if (api_user_realm_auth('mactrack_sites.php')) {
			if ($device['disabled'] == '') {
				$row .= "<img id='r_" . $device['device_id'] . "' src='" . $config['url_path'] . "plugins/mactrack/images/rescan_device.gif' alt='' onClick='scan_device(" . $device['device_id'] . ")' title='" . __('Rescan Device', 'mactrack') . "'>";
			} else {
				$row .= "<img src='" . $config['url_path'] . "plugins/mactrack/images/view_none.gif' alt=''>";
			}
		}

		print "<td style='width:40px;'>" . $row . "</td>";
	}

	form_selectable_cell(filter_value($device['device_name'], get_request_var('filter'), "mactrack_devices.php?action=edit&device_id=" . $device['device_id']), $device['device_id']);
	form_selectable_cell($device['site_name'], $device['device_id']);
	form_selectable_cell(get_colored_device_status(($device['disabled'] == 'on' ? true : false), $device['snmp_status']), $device['device_id']);
	form_selectable_cell(filter_value($device['hostname'], get_request_var('filter')), $device['device_id']);
	form_selectable_cell(($device['device_type'] == '' ? __('Not Detected', 'mactrack') : $device['device_type']), $device['device_id']);
	form_selectable_cell(($device['scan_type'] == '1' ? __('N/A', 'mactrack') : number_format_i18n($device['ips_total'], -1)), $device['device_id'], '', 'right');
	form_selectable_cell(($device['scan_type'] == '3' ? __('N/A', 'mactrack') : number_format_i18n($device['ports_total'], -1)), $device['device_id'], '', 'right');
	form_selectable_cell(($device['scan_type'] == '3' ? __('N/A', 'mactrack') : number_format_i18n($device['ports_active'], -1)), $device['device_id'], '', 'right');
	form_selectable_cell(($device['scan_type'] == '3' ? __('N/A', 'mactrack') : number_format_i18n($device['ports_trunk'], -1)), $device['device_id'], '', 'right');
	form_selectable_cell(($device['scan_type'] == '3' ? __('N/A', 'mactrack') : number_format_i18n($device['macs_active'], -1)), $device['device_id'], '', 'right');
	form_selectable_cell(number_format($device['last_runduration'], 1), $device['device_id'], '', 'right');
	form_checkbox_cell($device['device_name'], $device['device_id']);
	form_end_row();

}

function mactrack_mail($to, $fromemail, $fromname, $subject, $message, $headers = '') {
	global $config;

	$v = plugin_mactrack_version();
	$headers = array(
		'X-Mailer'   => 'Cacti-MacTrack-v' . $v['version'],
		'User-Agent' => 'Cacti-MacTrack-v' . $v['version']
	);

	$from[0]['email'] = $fromemail;
	$from[0]['name']  = $fromname;

	if (strpos($to, ';') !== false) {
		$to = explode(';', $to);
	}

	mailer($from, $to, '', '', '', $subject, $message, '', '', $headers);
}

function mactrack_sanitize_load_report() {
	if (!isset_request_var('report')) {
		if (isset($_SESSION['sess_mt_tab']) && $_SESSION['sess_mt_tab'] != '') {
			set_request_var('report', $_SESSION['sess_mt_tab']);
		} else {
			set_request_var('report', read_user_setting('default_mactrack_tab'));
		}

		if (get_request_var('report') == '') {
			set_request_var('report', 'sites');
		}
	} else {
		set_request_var('report', sanitize_search_string(get_nfilter_request_var('report')));
	}

	$_SESSION['sess_mt_tab'] = get_request_var('report');
}

function mactrack_tabs() {
	global $config;

	/* present a tabbed interface */
	$tabs_mactrack = array(
		'sites'      => __('Sites', 'mactrack'),
		'devices'    => __('Devices', 'mactrack'),
		'ips'        => __('IP Ranges', 'mactrack'),
		'arp'        => __('IP Address', 'mactrack'),
		'macs'       => __('MAC Address', 'mactrack'),
		'interfaces' => __('Interfaces', 'mactrack'),
		'dot1x'      => __('Dot1x', 'mactrack'),
		'graphs'     => __('Graphs', 'mactrack')
	);

	mactrack_sanitize_load_report();

	/* set the default tab */
	$current_tab = get_nfilter_request_var('report');

	/* draw the tabs */
	print "<div class='tabs'><nav><ul>\n";

	if (cacti_sizeof($tabs_mactrack)) {
		foreach ($tabs_mactrack as $tab_short_name => $tab_name) {
			print '<li><a class="tab pic' . (($tab_short_name == $current_tab) ? ' selected"' : '"') . " href='" . htmlspecialchars($config['url_path'] .
				'plugins/mactrack/mactrack_view_' . $tab_short_name . '.php?' .
				'report=' . $tab_short_name) .
				"'>$tab_name</a></li>\n";
		}
	}

	print "</ul></nav><script type='text/javascript'>\n";

	print "$(function() { if (pageName.indexOf('mactrack_view') >= 0) { $('.maintabs a.selected').attr('href', urlPath+'plugins/mactrack/'+pageName); } });";
	print "</script></div>";
}

function mactrack_get_vendor_name($mac) {
	$vendor_mac = substr($mac,0,8);

	$vendor_name = db_fetch_cell_prepared('SELECT vendor_name FROM mac_track_oui_database WHERE vendor_mac = ?', array($vendor_mac));

	if ($vendor_name != '') {
		return $vendor_name;
	} else {
		return __('Unknown', 'mactrack');
	}
}

function mactrack_site_filter($page = 'mactrack_sites.php') {
	global $item_rows;

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
						<?php print __('Sites', 'mactrack');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('rows') == '-1') {?> selected<?php }?>><?php print __('Default', 'mactrack');?></option>
							<?php
								if (cacti_sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print '<option value="' . $key . '"'; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . $value . "</option>\n";
								}
								}
							?>
						</select>
					</td>
					<td>
						<input type='checkbox' id='detail' <?php if (get_request_var('detail') == 'true') print ' checked="true"';?> onClick='applyFilter()'>
					</td>
					<td>
						<label for='detail'><?php print __('Show Device Details', 'mactrack');?></label>
					</td>
					<td>
						<input type='submit' id='go' value='<?php print __('Go', 'mactrack');?>'>
					</td>
					<td>
						<input type='button' id='clear' value='<?php print __('Clear', 'mactrack');?>'>
					</td>
					<td>
						<input type='button' id='export' value='<?php print __('Export', 'mactrack');?>'>
					</td>
				</tr>
			<?php
			if (!(get_request_var('detail') == 'false')) { ?>
			</table>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Site', 'mactrack');?>
					</td>
					<td>
						<select id='site_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('site_id') == '-1') {?> selected<?php }?>><?php print __('Any', 'mactrack');?></option>
							<?php
							$sites = db_fetch_assoc('SELECT * FROM mac_track_sites ORDER BY site_name');
							if (cacti_sizeof($sites) > 0) {
							foreach ($sites as $site) {
								print '<option value="' . $site['site_id'] . '"'; if (get_request_var('site_id') == $site['site_id']) { print ' selected'; } print '>' . $site['site_name'] . '</option>';
							}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('SubType', 'mactrack');?>
					</td>
					<td>
						<select id='device_type_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('device_type_id') == '-1') {?> selected<?php }?>><?php print __('Any', 'mactrack');?></option>
							<?php
							$device_types = db_fetch_assoc('SELECT DISTINCT mac_track_device_types.device_type_id,
								mac_track_device_types.description, mac_track_device_types.sysDescr_match
								FROM mac_track_device_types
								INNER JOIN mac_track_devices
								ON mac_track_device_types.device_type_id = mac_track_devices.device_type_id
								ORDER BY mac_track_device_types.description');

							if (cacti_sizeof($device_types)) {
							foreach ($device_types as $device_type) {
								print '<option value="' . $device_type['device_type_id'] . '"'; if (get_request_var('device_type_id') == $device_type['device_type_id']) { print ' selected'; } print '>' . $device_type['description'] . ' (' . $device_type['sysDescr_match'] . ')</option>';
							}
							}
							?>
						</select>
					</td>
				</tr>
			<?php }?>
			</table>
			<?php
			if (get_request_var('detail') == 'false') { ?>
			<input type='hidden' id='device_type_id' value='-1'>
			<input type='hidden' id='site_id' value='-1'>
			<?php }?>
			</form>
			<script type='text/javascript'>

			function applyFilter() {
				strURL  = urlPath+'plugins/mactrack/<?php print $page;?>?header=false';
				strURL += '&report=sites';
				strURL += '&device_type_id=' + $('#device_type_id').val();
				strURL += '&site_id=' + $('#site_id').val();
				strURL += '&detail=' + $('#detail').is(':checked');
				strURL += '&filter=' + $('#filter').val();
				strURL += '&rows=' + $('#rows').val();
				loadPageNoHeader(strURL);
			}

			function clearFilter() {
				strURL  = urlPath+'plugins/mactrack/<?php print $page;?>?header=false&clear=true';
				loadPageNoHeader(strURL);
			}

			function exportRows() {
				strURL  = urlPath+'plugins/mactrack/<?php print $page;?>?export=true';
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

				$('.siterescan').off('click').on('click', function(event) {
					event.preventDefault();

					var site_id = $(this).attr('id').replace('r_', '');

					site_scan(site_id);
				});
			});
			</script>
		</td>
	</tr>
	<?php
}

if (!function_exists('cacti_sizeof')) {
	function cacti_sizeof($array) {
		return ($array === false || !is_array($array)) ? 0 : sizeof($array);
	}
}

if (!function_exists('cacti_count')) {
	function cacti_count($array) {
		return ($array === false || !is_array($array)) ? 0 : count($array);
	}
}

function mactrack_arr_key ($array, $key, $default = '') {
	if (array_key_exists($key, $array)) {
		return $array[$key];
	} else {
		return $default;
	}
}
