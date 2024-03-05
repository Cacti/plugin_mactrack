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

/* register this functions scanning functions */
if (!isset($mactrack_scanning_functions)) { $mactrack_scanning_functions = array(); }
array_push($mactrack_scanning_functions, 'get_aruba_oscx_switch_ports');

if (!isset($mactrack_scanning_functions_ip)) { $mactrack_scanning_functions_ip = array(); }
array_push($mactrack_scanning_functions_ip, 'get_aruba_oscx_arp_table');


function oscx_mac ($mac) {

	$slabiky = explode ('.', trim($mac));
	$mac = '';

	for ($f = 0; $f < 6; $f++) {
		$slabiky[$f] = strtoupper(dechex($slabiky[$f]));
		if (strlen($slabiky[$f]) < 2) $slabiky[$f] = '0' . $slabiky[$f];
	}

	return (implode(':', $slabiky));
}

function get_aruba_oscx_switch_ports($site, &$device, $lowPort = 0, $highPort = 0) {
	global $debug, $scan_date;

	// initialize port counters
	$device['ports_total']  = 0;
	$device['ports_active'] = 0;
	$device['ports_trunk']  = 0;

	/*
	get VLAN information
	    .1.3.6.1.2.1.47.1.2.1.1.2
		.1.3.6.1.2.1.47.1.2.1.1.2.1 = STRING: "DEFAULT_VLAN_1"
		.1.3.6.1.2.1.47.1.2.1.1.2.11 = STRING: "guest"
		.1.3.6.1.2.1.47.1.2.1.1.2.199 = STRING: "management"
		.1.3.6.1.2.1.47.1.2.1.1.2.215 = STRING: "x"
	*/
	
	$vlan_names   = xform_standard_indexed_data('.1.3.6.1.2.1.47.1.2.1.1.2', $device);

	foreach ($vlan_names as $key=>$value) {
	    $vlan_ids[$key] = $key;
	}

	$device['vlans_total'] = cacti_sizeof($vlan_names);
	mactrack_debug('There are ' . (cacti_sizeof($vlan_names)) . ' VLANS.');

	/*
	vlan_ids:
	array(8) {
	  [1]=>
	  string(1) "1"
	  [102]=>
	  string(3) "102"
	  [103]=>
	  string(3) "103"
	  [122]=>
	  string(3) "122"

	vlan_names:
	array(8) {
	  [1]=>
	  string(9) "VLAN 0001"
	  [102]=>
	  string(6) "kamery"
	  [103]=>
	  string(8) "technici"
	  [122]=>
	  string(10) "vlan122-pc"
	  [144]=>
	  string(11) "test 802.1x"
	*/

	/*
	ports vlan membership
	.1.3.6.1.2.1.17.7.1.2.2 - tady je i cislo vlany, je to prvni kousek indexu, pak je mac adresa
		last six - mac address, before - vlanid
		.1.3.6.1.2.1.17.7.1.2.2.1.2.11.0.12.41.5.138.209 = INTEGER: 52
		.1.3.6.1.2.1.17.7.1.2.2.1.2.199.0.9.15.9.0.18 = INTEGER: 52
		.1.3.6.1.2.1.17.7.1.2.2.1.2.199.0.12.41.5.138.209 = INTEGER: 52
		.1.3.6.1.2.1.17.7.1.2.2.1.2.199.0.12.41.48.235.60 = INTEGER: 52
		.1.3.6.1.2.1.17.7.1.2.2.1.2.199.0.30.193.124.199.1 = INTEGER: 52
		.1.3.6.1.2.1.17.7.1.2.2.1.2.199.0.80.86.175.58.24 = INTEGER: 52
	*/

	$xdata = xform_indexed_data('.1.3.6.1.2.1.17.7.1.2.2.1.2', $device, 7);
	$port_vlan_data = array();

	foreach ($xdata as $key=>$value) {
		$keys = explode('.', $key);
		// it doesn't work for trunk ports. It  If port has more vlans, last is used
		if (!isset($port_vlan_data[$value])) {
			$port_vlan_data[$value] = $keys[0];
		}
	}

	// get the ifIndexes for the device
	$ifIndexes = xform_standard_indexed_data('.1.3.6.1.2.1.2.2.1.1', $device);
	mactrack_debug('ifIndexes data collection complete: ' . cacti_sizeof($ifIndexes));

	// get and store the interfaces table
	$ifInterfaces = build_InterfacesTable($device, $ifIndexes, true, false);

	foreach($ifIndexes as $ifIndex) {
		if (($ifInterfaces[$ifIndex]['ifType'] >= 6) && ($ifInterfaces[$ifIndex]['ifType'] <= 9)) {
			$device['ports_total']++;
		}
	}
	mactrack_debug('ifInterfaces assembly complete: ' . cacti_sizeof($ifIndexes));

	// map vlans to bridge ports
	if (cacti_sizeof($vlan_ids) > 0) {
		// get the port status information

		$port_results = get_aruba_oscx_dot1dTpFdbEntry_ports($site, $device, $ifInterfaces, $device['snmp_readstring'], false, $lowPort, $highPort);

		// get the ifIndexes for the device
		$i = 0;
		$j = 0;
		$port_array = array();
		foreach($port_results as $port_result) {
			$ifIndex = $port_result['port_number'];

			$ifType = $ifInterfaces[$ifIndex]['ifType'];

			/* only output legitimate end user ports */
			if (($ifType >= 6) && ($ifType <= 9)) {
				$port_array[$i]['vlan_id']     = mactrack_arr_key($port_vlan_data, $port_result['port_number']);
				$port_array[$i]['vlan_name']   = isset($vlan_names[$port_array[$i]['vlan_id']]) ? $vlan_names[$port_array[$i]['vlan_id']] : '';
				$port_array[$i]['port_number'] = mactrack_arr_key($port_result, 'port_number');
				$port_array[$i]['port_name']   = isset($ifInterfaces[$ifIndex]['ifName']) ? $ifInterfaces[$ifIndex]['ifName'] : '';
				$port_array[$i]['mac_address'] = xform_mac_address($port_result['mac_address']);
				mactrack_debug('VLAN: ' . $port_array[$i]['vlan_id'] . ', ' .
					'NAME: ' . $port_array[$i]['vlan_name'] . ', ' .
					'PORT: ' . $ifInterfaces[$ifIndex]['ifName'] . ', ' .
					'NAME: ' . $port_array[$i]['port_name'] . ', ' .
					'MAC: ' . $port_array[$i]['mac_address']);

				$i++;
			}

			$j++;
		}

		/* display completion message */
		mactrack_debug('INFO: HOST: ' . $device['hostname'] . ', TYPE: ' . trim(substr($device['snmp_sysDescr'],0,40)) . ', TOTAL PORTS: ' . $device['ports_total'] . ', ACTIVE PORTS: ' . $device['ports_active']);

		$device['last_runmessage'] = 'Data collection completed ok';
		$device['macs_active'] = cacti_sizeof($port_array);

		mactrack_debug('macs active on this switch:' . $device['macs_active']);
		db_store_device_port_results($device, $port_array, $scan_date);
	} else {
		mactrack_debug('INFO: HOST: ' . $device['hostname'] . ', TYPE: ' . substr($device['snmp_sysDescr'],0,40) . ', No active devices on this network device.');

		$device['snmp_status'] = HOST_UP;
		$device['last_runmessage'] = 'Data collection completed ok. No active devices on this network device.';
	}

	return $device;
}

/*	get_base_dot1dTpFdbEntry_ports - This function will grab information from the
  port bridge snmp table and return it to the calling progrem for further processing.
  This is a foundational function for all vendor data collection functions.
*/
function get_aruba_oscx_dot1dTpFdbEntry_ports($site, &$device, &$ifInterfaces, $snmp_readstring = '', $store_to_db = true, $lowPort = 1, $highPort = 9999) {
	global $debug, $scan_date;
	mactrack_debug('FUNCTION: get_aruba_oscx_dot1dTpFdbEntry_ports started');

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
	mactrack_debug('get active ports: ' . cacti_sizeof($active_ports_array));
	$indexes = array_keys($active_ports_array);

	$i = 0;
	foreach($active_ports_array as $port_info) {
		if (($ifInterfaces[$indexes[$i]]['ifType'] >= 6) &&
			($ifInterfaces[$indexes[$i]]['ifType'] <= 9)) {
			if ($port_info == 1) {
				$ports_active++;
			}
			$ports_total++;
		}
		$i++;
	}

	if ($store_to_db) {
		mactrack_debug('INFO: HOST: ' . $device['hostname'] . ', TYPE: ' . substr($device['snmp_sysDescr'], 0, 40) . ', TOTAL PORTS: ' . $ports_total . ', OPER PORTS: ' . $ports_active);

		$device['ports_active'] = $ports_active;
		$device['ports_total'] = $ports_total;
		$device['macs_active'] = 0;
	}

	if ($ports_active > 0) {
		/* get bridge port to ifIndex mapping: dot1dBasePortIfIndex from dot1dBasePortTable
		GET NEXT: 1.3.6.1.2.1.17.1.4.1.2.1: 1
		GET NEXT: 1.3.6.1.2.1.17.1.4.1.2.2: 4
		GET NEXT: 1.3.6.1.2.1.17.1.4.1.2.64: 12001
		GET NEXT: 1.3.6.1.2.1.17.1.4.1.2.65: 12002
		GET NEXT: 1.3.6.1.2.1.17.1.4.1.2.66: 12003
		GET NEXT: 1.3.6.1.2.1.17.1.4.1.2.67: 12004
		GET NEXT: 1.3.6.1.2.1.17.1.4.1.2.68: 12005
		GET NEXT: 1.3.6.1.2.1.17.1.4.1.2.69: 12006
		GET NEXT: 1.3.6.1.2.1.17.1.4.1.2.70: 12007
		where
		table index = bridge port (dot1dBasePort) and
		table value = ifIndex */
		/* -------------------------------------------- */
		$bridgePortIfIndexes = xform_standard_indexed_data('.1.3.6.1.2.1.17.1.4.1.2', $device, $snmp_readstring);
		mactrack_debug('get bridgePortIfIndexes: ' . cacti_sizeof($bridgePortIfIndexes));

		/* get port status: dot1dTpFdbStatus from dot1dTpFdbTable
		GET NEXT: 1.3.6.1.2.1.17.4.3.1.3.0.0.94.0.1.1: 3
		GET NEXT: 1.3.6.1.2.1.17.4.3.1.3.0.1.227.32.11.99: 3
		GET NEXT: 1.3.6.1.2.1.17.4.3.1.3.0.1.227.37.228.26: 3
		GET NEXT: 1.3.6.1.2.1.17.4.3.1.3.0.1.227.37.238.180: 3
		GET NEXT: 1.3.6.1.2.1.17.4.3.1.3.0.1.230.56.96.234: 3
		GET NEXT: 1.3.6.1.2.1.17.4.3.1.3.0.1.230.59.133.114: 3
		GET NEXT: 1.3.6.1.2.1.17.4.3.1.3.0.1.230.107.157.61: 3
		GET NEXT: 1.3.6.1.2.1.17.4.3.1.3.0.1.230.107.189.168: 3
		GET NEXT: 1.3.6.1.2.1.17.4.3.1.3.0.1.230.109.208.105: 3
		where
		table index = MAC Address (dot1dTpFdbAddress e.g. 0.0.94.0.1.1 = 00:00:5E:00:01:01) and
		table value = port status (other(1), invalid(2), learned(3), self(4), mgmt(5)*/
		/* -------------------------------------------- */
		$port_status = xform_stripped_oid('.1.3.6.1.2.1.17.4.3.1.3', $device, $snmp_readstring);
		mactrack_debug('get port_status: ' . cacti_sizeof($port_status));

		/* get device active port numbers: dot1dTpFdbPort from dot1dTpFdbTable
		GET NEXT: 1.3.6.1.2.1.17.4.3.1.2.0.0.94.0.1.1: 72
		GET NEXT: 1.3.6.1.2.1.17.4.3.1.2.0.1.227.32.11.99: 70
		GET NEXT: 1.3.6.1.2.1.17.4.3.1.2.0.1.227.37.228.26: 70
		GET NEXT: 1.3.6.1.2.1.17.4.3.1.2.0.1.227.37.238.180: 70
		GET NEXT: 1.3.6.1.2.1.17.4.3.1.2.0.1.230.56.96.234: 70
		GET NEXT: 1.3.6.1.2.1.17.4.3.1.2.0.1.230.59.133.114: 69
		GET NEXT: 1.3.6.1.2.1.17.4.3.1.2.0.1.230.107.157.61: 70
		GET NEXT: 1.3.6.1.2.1.17.4.3.1.2.0.1.230.107.189.168: 68
		GET NEXT: 1.3.6.1.2.1.17.4.3.1.2.0.1.230.109.208.105: 68
		where
		table index = MAC Address (dot1dTpFdbAddress e.g. 0.0.94.0.1.1 = 00:00:5E:00:01:01) and
		table value = bridge port */
		/* -------------------------------------------- */
		$port_numbers = xform_stripped_oid('.1.3.6.1.2.1.17.4.3.1.2', $device, $snmp_readstring);
		mactrack_debug('get port_numbers: ' . cacti_sizeof($port_numbers));

		/* get VLAN information */
		/* -------------------------------------------- */

		$vlan_ids = array();
		$vlan_names = xform_standard_indexed_data('.1.3.6.1.2.1.47.1.2.1.1.2', $device);
		foreach ($vlan_names as $key=>$value) {
		    $vlan_ids[$key] = $key;
		}

		mactrack_debug('get vlan_ids: ' . cacti_sizeof($vlan_ids));

		/* get the ignore ports list from device */
		$ignore_ports = port_list_to_array($device['ignorePorts']);

    		$xdata = xform_indexed_data('.1.3.6.1.2.1.17.7.1.2.2.1.2', $device, 7);
		$port_vlan_data = array();

		foreach ($xdata as $key=>$value) {
		    $keys = explode('.', $key);
		    $port_vlan_data[$value] = $keys[0];
		}

		/* determine user ports for this device and transfer user ports to
		   a new array.
		*/
		$i = 0;
		foreach ($port_numbers as $key => $port_number) {
			/* key = MAC Address from dot1dTpFdbTable */
			/* value = bridge port			  */
			if (($highPort == 0) ||
				(($port_number >= $lowPort) &&
				($port_number <= $highPort))) {

				if (!in_array($port_number, $ignore_ports)) {
					if (isset($port_status[$key]) && $port_status[$key] == '3') {
						$port_key_array[$i]['key'] = substr($key,1);
						$port_key_array[$i]['port_number'] = $port_number;
						$i++;
					}
				}
			}
		}

		/* compare the user ports to the bridge port data, store additional
		   relevant data about the port.
		*/

		$i = 0;
		foreach ($port_key_array as $port_key) {
			/* map bridge port to interface port and check type */
			if ($port_key['port_number'] > 0) {
				if (cacti_sizeof($bridgePortIfIndexes) != 0) {
					/* some hubs do not always return a port number in the bridge table.
					   test for it by isset and substitute the port number from the ifTable
					   if it isnt in the bridge table
					*/
					mactrack_debug('Searching Bridge Port: ' . $port_key['port_number'] . ', Bridge: ' . $bridgePortIfIndexes[$port_key['port_number']]);
					if (isset($bridgePortIfIndexes[$port_key['port_number']])) {
						$brPortIfIndex = mactrack_arr_key($bridgePortIfIndexes, $port_key['port_number']);
					} else {
						$brPortIfIndex = mactrack_arr_key($port_key, 'port_number');
					}
					$brPortIfType = isset($ifInterfaces[$brPortIfIndex]['ifType']) ? $ifInterfaces[$brPortIfIndex]['ifType'] : '';
				} else {
					$brPortIfIndex = $port_key['port_number'];
					$brPortIfType = isset($ifInterfaces[$port_key['port_number']]['ifType']) ? $ifInterfaces[$port_key['port_number']]['ifType'] : '';
				}

				if (($brPortIfType >= 6) &&
					($brPortIfType <= 9) &&
					(!isset($ifInterfaces[$brPortIfIndex]['portLink']))) {
					/* set some defaults  */
					$new_port_key_array[$i]['vlan_id'] = 'N/A';
					$new_port_key_array[$i]['vlan_name'] = 'N/A';
					$new_port_key_array[$i]['mac_address'] = 'NOT USER';
					$new_port_key_array[$i]['port_number'] = 'NOT USER';
					$new_port_key_array[$i]['port_name'] = 'N/A';

					/* now set the real data */
					$new_port_key_array[$i]['key'] = mactrack_arr_key($port_key, 'key');
					$new_port_key_array[$i]['port_number'] = isset($brPortIfIndex) ? $brPortIfIndex : '';
					$new_port_key_array[$i]['port_name'] = mactrack_arr_key(ifInterfaces, $port_key['port_number']);
					$new_port_key_array[$i]['mac_address'] = oscx_mac($port_key['key']);
					$new_port_key_array[$i]['vlan_id'] = mactrack_arr_key($port_vlan_data, $brPortIfIndex);
					$new_port_key_array[$i]['vlan_name'] = isset ($brPortIfIndex) ? mactrack_arr_key($vlan_names, $port_vlan_data[$brPortIfIndex]) : '';

					$i++;
				}
			}
		}
		mactrack_debug('Port number information collected: ' . cacti_sizeof($new_port_key_array));
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


/*	get_aruba_oscx_arp_table - This function reads a devices CTAlias table for a site and stores
  the IP address and MAC address combinations in the mac_track_ips table.
*/
function get_aruba_oscx_arp_table($site, &$device) {
	global $debug, $scan_date;

	mactrack_debug('FUNCTION: get_aruba_oscx_arp_table started');

/*
joining mac = port with ip = mac
1.3.6.1.2.1.17.7.1.2.2.1.2
.1.3.6.1.2.1.17.7.1.2.2.1.2.1.0.2.209.50.110.192 = INTEGER: 28
.1.3.6.1.2.1.17.7.1.2.2.1.2.1.0.9.15.9.0.18 = INTEGER: 28
.1.3.6.1.2.1.17.7.1.2.2.1.2.1.0.11.134.101.90.128 = INTEGER: 28
.1.3.6.1.2.1.17.7.1.2.2.1.2.1.0.12.41.187.210.4 = INTEGER: 28
.1.3.6.1.2.1.17.7.1.2.2.1.2.1.0.16.116.104.90.49 = INTEGER: 28
.1.3.6.1.2.1.17.7.1.2.2.1.2.1.0.17.50.44.9.137 = INTEGER: 28
.1.3.6.1.2.1.17.7.1.2.2.1.2.1.0.22.108.186.185.185 = INTEGER: 28

.1.3.6.1.2.1.4.35.1.4
.1.3.6.1.2.1.4.35.1.4.16777415.1.4.192.168.199.95 = STRING: 4c:ae:a3:64:5a:cb
.1.3.6.1.2.1.4.35.1.4.16777415.1.4.192.168.199.96 = STRING: 4c:ae:a3:64:50:ab
.1.3.6.1.2.1.4.35.1.4.16777415.1.4.192.168.199.97 = STRING: 40:b9:3c:4b:9c:de
.1.3.6.1.2.1.4.35.1.4.16777415.1.4.192.168.199.98 = STRING: ec:9b:8b:78:9b:d7
*/

	$xdata = xform_indexed_data('.1.3.6.1.2.1.17.7.1.2.2.1.2', $device, 6);
	$port_vlan_data = array();

	foreach ($xdata as $key=>$value) {
		$mac_port[oscx_mac($key)] = $value;
	}

	$xdata = xform_indexed_data('.1.3.6.1.2.1.4.35.1.4', $device, 4);
	foreach ($xdata as $key=>$value) {
		$ip_mac[$key] = strtr($value, ' ', ':');
	}

	$result = array();

	foreach($ip_mac as $key=>$value) {
		if (isset($mac_port[$value])) {
			$result[$key]['port'] = $mac_port[$value];
			$result[$key]['mac'] = $value;
		}
	}

	mactrack_debug('arp assembly complete.');

	// output details to database 
	if (cacti_sizeof($result)) {
		$sql = array();

		foreach($result as $key=>$value) {
			$sql[] = '(' .
				$device['site_id']   . ', ' .
				$device['device_id'] . ', ' .
				db_qstr($device['hostname'])       . ', ' .
				db_qstr($device['device_name'])    . ', ' .
				db_qstr($value['port'])     . ', ' .
				db_qstr($value['mac']) . ', ' .
				db_qstr($key)  . ', ' .
				db_qstr($scan_date) . ')';
		}

		if (cacti_sizeof($sql)) {
			db_execute('REPLACE INTO mac_track_ips 
				(site_id, device_id, hostname, device_name, port_number, mac_address,ip_address,scan_date)
				VALUES ' . implode(', ', $sql));
		}
	}

	// save ip information for the device 
	$device['ips_total'] = cacti_sizeof($result);

	db_execute_prepared('UPDATE mac_track_devices
		SET ips_total = ?
		WHERE device_id = ?',
		array($device['ips_total'], $device['device_id']));

	mactrack_debug('HOST: ' . $device['hostname'] . ', IP address information collection complete. IP=' . cacti_sizeof($result) . '.');
}

