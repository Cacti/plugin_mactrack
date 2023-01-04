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
array_push($mactrack_scanning_functions, 'get_extreme_switch_ports');
array_push($mactrack_scanning_functions, 'get_extreme_extremeware_switch_ports');

if (!isset($mactrack_scanning_functions_ip)) { $mactrack_scanning_functions_ip = array(); }
array_push($mactrack_scanning_functions_ip, 'get_extreme_arp_table');
array_push($mactrack_scanning_functions_ip, 'get_extreme_extremeware_arp_table');

function get_extreme_extremeware_switch_ports($site, &$device, $lowPort = 0, $highPort = 0, $extremeware = false) {
	return get_extreme_switch_ports($site, $device, $lowPort, $highPort , true);
}
function get_extreme_extremeware_arp_table($site, &$device, $extremeware = false) {
	get_extreme_arp_table($site, $device, true);
}

function get_extreme_switch_ports($site, &$device, $lowPort = 0, $highPort = 0, $extremeware = false) {
	global $debug, $scan_date;

	/* initialize port counters */
	$device['ports_total']  = 0;
	$device['ports_active'] = 0;
	$device['ports_trunk']  = 0;
	$device['vlans_total']  = 0;
	$device['macs_active']  = 0;

	/* get VLAN information
	   VLAN index
	   .1.3.6.1.4.1.1916.1.2.1.2.1.1
	   EXTREME-VLAN-MIB::extremeVlanIfIndex.<vlanid> = index
	   VLAN name
	   .1.3.6.1.4.1.1916.1.2.1.2.1.2
	   EXTREME-VLAN-MIB::extremeVlanIfDescr.<vlanid> = description
	   VLAN ID
	   .1.3.6.1.4.1.1916.1.2.1.2.1.10
	   EXTREME-VLAN-MIB::extremeVlanIfVlanId.<vlanid> = tag id
	 */
	$vlan_ids   = xform_standard_indexed_data('.1.3.6.1.4.1.1916.1.2.1.2.1.10', $device);
	$vlan_names = xform_standard_indexed_data('.1.3.6.1.4.1.1916.1.2.1.2.1.2', $device);
	$device['vlans_total'] = cacti_sizeof($vlan_ids);
	mactrack_debug('There are ' . (cacti_sizeof($vlan_ids)) . ' VLANS.');

	/* get the ifIndexes for the device
	   .1.3.6.1.2.1.2.2.1.1
	   RFC1213-MIB::ifIndex.<index> = index
	   .1.3.6.1.2.1.2.2.1.2
	   RFC1213-MIB::ifDescr.<index> = description
	   .1.3.6.1.2.1.2.2.1.3
	   RFC1213-MIB::ifType.<index> = type (6=ether)
	   .1.3.6.1.2.1.31.1.1.1.1
	   IF-MIB::ifName.<index> = name
	   .1.3.6.1.2.1.31.1.1.1.18
	   IF-MIB::ifAlias.<index> = alias
	 */
	$ifInterfaces = build_InterfacesTable($device, $ifIndexes, true, true);

	mactrack_debug('ifInterfaces assembly complete.');

	/* get VLAN details */
	$i = 0;
	foreach ($vlan_ids as $vlan_index => $vlan_id) {
		$active_vlans[$i]['vlan_id'] = $vlan_id;
		$active_vlans[$i]['vlan_name'] = $vlan_names[$vlan_index];
		$active_vlans++;
		mactrack_debug('VLAN ID = ' . $active_vlans[$i]['vlan_id'] . ' VLAN Name = ' . $active_vlans[$i]['vlan_name']);
		$i++;
	}

	if (cacti_sizeof($active_vlans) > 0) {

		/* get the port status information */
		/* get port_number and MAC addr */
		/*extremeXOS
		  addr mac
		  .1.3.6.1.4.1.1916.1.16.4.1.1
		  EXTREME-BASE-MIB::extremeFdb.4.1.1.<MAC>.<vlanid>= hex MAC
		  index du vlan ?
		  .1.3.6.1.4.1.1916.1.16.4.1.2
		  EXTREME-BASE-MIB::extremeFdb.4.1.2.<MAC>.<vlanid>=vlanid
		  index du port
		  .1.3.6.1.4.1.1916.1.16.4.1.3
		  EXTREME-BASE-MIB::extremeFdb.4.1.3.<MAC>.<vlanid>=port id
		  status
		  .1.3.6.1.4.1.1916.1.16.4.1.4
		  EXTREME-BASE-MIB::extremeFdb.4.1.4.<MAC>.<vlanid>= 3 learned

		  extremeware
		  .1.3.6.1.4.1.1916.1.16.1.1.3
		  EXTREME-FDB-MIB::extremeFdbMacFdbMacAddress.<vlanid>.<id> = mac
		  .1.3.6.1.4.1.1916.1.16.1.1.4
		  EXTREME-FDB-MIB::extremeFdbMacFdbPortIfIndex.<vlanid>.<id> = index du port
		  .1.3.6.1.4.1.1916.1.16.1.1.5
		  EXTREME-FDB-MIB::extremeFdbMacFdbStatus.<vlanid>.<id> = 3 learned
		 */
		if ($extremeware) {
			$mac_addr_list = xform_stripped_oid('.1.3.6.1.4.1.1916.1.16.1.1.3', $device);
			$mac_port_list = xform_stripped_oid('.1.3.6.1.4.1.1916.1.16.1.1.4', $device);
			$mac_status_list = xform_stripped_oid('.1.3.6.1.4.1.1916.1.16.1.1.5', $device);
		} else {
			$mac_addr_list = xform_stripped_oid('.1.3.6.1.4.1.1916.1.16.4.1.1', $device);
			$mac_vlan_list = xform_stripped_oid('.1.3.6.1.4.1.1916.1.16.4.1.2', $device);
			$mac_port_list = xform_stripped_oid('.1.3.6.1.4.1.1916.1.16.4.1.3', $device);
			$mac_status_list = xform_stripped_oid('.1.3.6.1.4.1.1916.1.16.4.1.4', $device);
		}

		$port_array = array();

		foreach ($mac_addr_list as $mac_key => $mac_addr) {
			/* check if mac addr is 'learned'  or 'mgnt' */
			if (isset($mac_status_list[$mac_key]) and (($mac_status_list[$mac_key] == '3') || ($mac_status_list[$mac_key] == '5'))) {
				$ifIndex = $mac_port_list[$mac_key];
				$ifType = $ifInterfaces[$ifIndex]['ifType'];
				//$ifType = $ifTypes[$ifIndex];
				/* only output legitimate end user ports */
				if (($ifType >= 6) && ($ifType <= 9)) {
					if ($extremeware) {
						$vlanid = substr($mac_key,0,strpos($mac_key,'.'));
						$new_port_array['vlan_id']   = $vlan_ids[$vlanid];
						$new_port_array['vlan_name'] = $vlan_names[$vlanid];
					} else {
						$new_port_array['vlan_id']   = $vlan_ids[$mac_vlan_list[$mac_key]];
						$new_port_array['vlan_name'] = $vlan_names[$mac_vlan_list[$mac_key]];
					}

					//$new_port_array['port_number']  = $ifIndex;
					//$new_port_array['port_name']    = $ifInterfaces[$ifIndex]['ifName'];
					$new_port_array['port_number']  = $ifInterfaces[$ifIndex]['ifName'];
					$new_port_array['port_name']    = $ifInterfaces[$ifIndex]['ifAlias'];
					$new_port_array['mac_address']  = xform_mac_address($mac_addr_list[$mac_key]);
					$ifInterfaces[$ifIndex]['Used'] = 1;
					$port_array[] = $new_port_array;

					mactrack_debug('VLAN: ' . $new_port_array['vlan_id'] . ', ' .
						'NAME: ' . $new_port_array['vlan_name'] . ', ' .
						'PORT: ' . $ifIndex . ', ' .
						'NAME: ' . $new_port_array['port_name'] . ', ' .
						'MAC: '  . $new_port_array['mac_address']);
				}
			}
		}

		$device['ports_total'] = cacti_sizeof($ifInterfaces);
		$device['ports_active'] = 0;

		foreach ($ifInterfaces as $interface) {
			if (isset($interface['Used'])) {
				$device['ports_active']++;
			}
		}

		/* display completion message */
		mactrack_debug('INFO: HOST: ' . $device['hostname'] . ', TYPE: ' . substr($device['snmp_sysDescr'],0,40) . ', TOTAL PORTS: ' . $device['ports_total'] . ', ACTIVE PORTS: ' . $device['ports_active']);

		$device['last_runmessage'] = 'Data collection completed ok';
		$device['macs_active'] = cacti_sizeof($port_array);

		db_store_device_port_results($device, $port_array, $scan_date);
	} else {
		mactrack_debug('INFO: HOST: ' . $device['hostname'] . ', TYPE: ' . substr($device['snmp_sysDescr'],0,40) . ', No active devices on this network device.');

		$device['snmp_status'] = HOST_UP;
		$device['last_runmessage'] = 'Data collection completed ok. No active devices on this network device.';
	}

	return $device;
}


/*	get_extreme_arp_table - This function reads a devices ARP table for a site and stores
  the IP address and MAC address combinations in the mac_track_ips table.
*/
function get_extreme_arp_table($site, &$device, $extremeware = false) {
	global $debug, $scan_date;
/*
EXTREME-FDB-MIB::extremeFdbIpFdbIPAddress : The IP Address of the IP FDB entry.
.1.3.6.1.4.1.1916.1.16.2.1.2
EXTREME-FDB-MIB::extremeFdbIpFdbMacAddress : The MAC address corresponding to the IP Address.
.1.3.6.1.4.1.1916.1.16.2.1.3
EXTREME-FDB-MIB::extremeFdbIpFdbVlanIfIndex : The ifIndex of the Vlan on which this ip is learned.
.1.3.6.1.4.1.1916.1.16.2.1.4
EXTREME-FDB-MIB::extremeFdbIpFdbPortIfIndex : The IfIndex of the port on which this entry was learned.
.1.3.6.1.4.1.1916.1.16.2.1.5
EXTREME-VLAN-MIB::extremeVlanIfIndex.<vlanid> = index
.1.3.6.1.4.1.1916.1.2.1.2.1.1
EXTREME-VLAN-MIB::extremeVlanIfDescr.<vlanid> = description
.1.3.6.1.4.1.1916.1.2.1.2.1.2
EXTREME-VLAN-MIB::extremeVlanIfVlanId.<vlanid> = tag id
.1.3.6.1.4.1.1916.1.2.1.2.1.10
BRIDGE-MIB::dot1dBasePortIfIndex : get Ifindex from extremeFdbIpFdbPortIfIndex
.1.3.6.1.2.1.17.1.4.1.2
IF-MIB::ifName : get name of port from IfIndex
.1.3.6.1.2.1.31.1.1.1.1
*/
	if ($extremeware) { // for extremeware use standard apr table + ifDescr for interface name
		/* get the atifIndexes for the device */
		$atifIndexes = xform_stripped_oid('.1.3.6.1.2.1.3.1.1.1', $device);
		$atEntries   = array();

		if (cacti_sizeof($atifIndexes)) {
			mactrack_debug('atifIndexes data collection complete');
			$atPhysAddress = xform_stripped_oid('.1.3.6.1.2.1.3.1.1.2', $device, true);
			mactrack_debug('atPhysAddress data collection complete');
			$atNetAddress  = xform_stripped_oid('.1.3.6.1.2.1.3.1.1.3', $device, true);
			mactrack_debug('atNetAddress data collection complete');
			$ifDescr  = xform_stripped_oid('.1.3.6.1.2.1.2.2.1.2', $device);
			mactrack_debug('ifDescr data collection complete');
		}
		$i = 0;
		if (cacti_sizeof($atifIndexes)) {
			foreach($atifIndexes as $key => $atifIndex) {
				$atEntries[$i]['atifIndex'] = $ifDescr[$atifIndex];
				$atEntries[$i]['atPhysAddress'] = xform_mac_address($atPhysAddress[$key]);
				$atEntries[$i]['atNetAddress'] = xform_net_address($atNetAddress[$key]);
				$i++;
			}
		}
	} else {
		/* get the atifIndexes for the device */
		$FdbPortIfIndex = xform_stripped_oid('.1.3.6.1.4.1.1916.1.16.2.1.5', $device);
		$atEntries   = array();

		if (cacti_sizeof($FdbPortIfIndex)) {
			mactrack_debug('FdbPortIfIndex data collection complete');
			$FdbMacAddress = xform_stripped_oid('.1.3.6.1.4.1.1916.1.16.2.1.3', $device, true);
			mactrack_debug('FdbMacAddress data collection complete');
			$FdbIPAddress  = xform_stripped_oid('.1.3.6.1.4.1.1916.1.16.2.1.2', $device);
			mactrack_debug('FdbIPAddress data collection complete');
			$FdbVlanIfIndex  = xform_stripped_oid('.1.3.6.1.4.1.1916.1.16.2.1.4', $device);
			mactrack_debug('FdbVlanIfIndex data collection complete');
			$VlanIfVlanId  = xform_stripped_oid('.1.3.6.1.4.1.1916.1.2.1.2.1.10', $device);
			mactrack_debug('VlanIfVlanId data collection complete');
			$BasePortIfIndex  = xform_stripped_oid('.1.3.6.1.2.1.17.1.4.1.2', $device);
			mactrack_debug('BasePortIfIndex data collection complete');
			$ifName  = xform_stripped_oid('.1.3.6.1.2.1.31.1.1.1.1', $device);
			mactrack_debug('ifName data collection complete');
		}

		$i = 0;
		if (cacti_sizeof($FdbPortIfIndex)) {
			foreach($FdbPortIfIndex as $key => $PortIndex) {
				$atEntries[$i]['atifIndex'] = $ifName[$BasePortIfIndex[$PortIndex]] . ', vlan:' . $VlanIfVlanId[$FdbVlanIfIndex[$key]];
				$atEntries[$i]['atPhysAddress'] = xform_mac_address($FdbMacAddress[$key]);
				$atEntries[$i]['atNetAddress'] = xform_net_address($FdbIPAddress[$key]);
				$i++;
			}
		}

		mactrack_debug('atEntries assembly complete.');
	}

	/* output details to database */
	if (cacti_sizeof($atEntries)) {
		$sql = array();

		foreach($atEntries as $atEntry) {
			$sql[] = '(' .
				$device['site_id']   . ', ' .
				$device['device_id'] . ', ' .
				db_qstr($device['hostname'])       . ', ' .
				db_qstr($device['device_name'])    . ', ' .
				db_qstr($atEntry['atifIndex'])     . ', ' .
				db_qstr($atEntry['atPhysAddress']) . ', ' .
				db_qstr($atEntry['atNetAddress'])  . ', ' .
				db_qstr($scan_date) . ')';
		}

		if (cacti_sizeof($sql)) {
			db_execute('REPLACE INTO mac_track_ips
                (site_id, device_id, hostname, device_name, port_number, mac_address,ip_address,scan_date)
                VALUES ' . implode(', ', $sql));
		}
	}

	/* save ip information for the device */
	$device['ips_total'] = cacti_sizeof($atEntries);
	db_execute_prepared('UPDATE mac_track_devices
		SET ips_total = ?
		WHERE device_id = ?',
		array($device['ips_total'], $device['device_id']));

	mactrack_debug('HOST: ' . $device['hostname'] . ', IP address information collection complete: nb IP=' . cacti_sizeof($atEntries) . '.');
}

