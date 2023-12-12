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

/* This file was modified from mactrack_juniper.php
   specifically written and tested to work with TP-LINK1600G-28TS and TP-LINKT2600G-28TS
   gigabit ethernet switches

   Gabriel Gómez Sena <ggomez@fing.edu.uy>
*/

/* register this functions scanning functions */
if (!isset($mactrack_scanning_functions)) { $mactrack_scanning_functions = array(); }
array_push($mactrack_scanning_functions, 'get_tplink_dot1q_switch_ports');

/*	get_tplink_dot1q_switch_ports - This is a basic function that will scan the dot1d
  OID tree for all switch port to MAC address association and stores in the
  mac_track_temp_ports table for future processing in the finalization steps of the
  scanning process.
*/
function get_tplink_dot1q_switch_ports($site, &$device, $lowPort = 0, $highPort = 0) {
	global $debug, $scan_date;

	/* initialize port counters */
	$device['ports_total']  = 0;
	$device['ports_active'] = 0;
	$device['ports_trunk']  = 0;

	/* get the ifIndexes for the device */
	$ifIndexes = xform_standard_indexed_data('.1.3.6.1.2.1.2.2.1.1', $device);
	mactrack_debug('ifIndexes data collection complete');

	$ifInterfaces = build_InterfacesTable($device, $ifIndexes, true, false);

	/* initialize variables */
	$port_keys = array();
	$return_array = array();
	$new_port_key_array = array();
	$port_key_array = array();
	$port_number = 0;
	$ports_active = 0;
	$active_ports = 0;
	$ports_total = 0;

	/* get the operational status of the ports */
	$active_ports_array = xform_standard_indexed_data('.1.3.6.1.2.1.2.2.1.8', $device);
	$indexes = array_keys($active_ports_array);

	$bridgePortIfIndexes = xform_standard_indexed_data('.1.3.6.1.2.1.17.1.4.1.2', $device );

	$i = 0;
	if (cacti_sizeof($active_ports_array)) {
		foreach ($active_ports_array as $port_info) {
			$port_info =  mactrack_strip_alpha($port_info);

			if ( isset( $bridgePortIfIndexes[$ifInterfaces[$indexes[$i]]['ifIndex']] ) ) {
				if ((($ifInterfaces[$indexes[$i]]['ifType'] >= 6) &&
					($ifInterfaces[$indexes[$i]]['ifType'] <= 9)) ||
					($ifInterfaces[$indexes[$i]]['ifType'] == 71)) {
					if ($port_info == 1) {
						$ports_active++;
					}

					$ports_total++;
				}
			}

			$i++;
		}
	}

	mactrack_debug('INFO: HOST: ' . $device['hostname'] . ', TYPE: ' . substr($device['snmp_sysDescr'],0,40) . ', TOTAL PORTS: ' . $ports_total . ', OPER PORTS: ' . $ports_active);

	$device['ports_active'] = $ports_active;
	$device['ports_total'] = $ports_total;
	$device['macs_active'] = 0;

	/* get VLAN information */
	$vlan_ids   = xform_standard_indexed_data('.1.3.6.1.4.1.11863.6.14.1.2.1.1.1', $device);
	$vlan_names = xform_standard_indexed_data('.1.3.6.1.4.1.11863.6.14.1.2.1.1.2', $device);
	$vlan_trunkstatus = xform_standard_indexed_data('.1.3.6.1.4.1.11863.6.14.1.1.1.1.2', $device);

	$device['vlans_total'] = cacti_sizeof($vlan_ids) - 1;
	mactrack_debug('VLAN data collected. There are ' . (cacti_sizeof($vlan_ids) - 1) . ' VLANS.');
	foreach ($ifIndexes as $ifIndex) {
		if ( isset( $vlan_trunkstatus[$ifIndex] ) && $vlan_trunkstatus[$ifIndex] == 1 ) {
			$device['ports_trunk']++;
		}
	}
	mactrack_debug('ports total : ' . $device['ports_total'] . ', ports_trunk: ' . $device['ports_trunk']);
	mactrack_debug('ifInterfaces assembly complete.');

	$i = 0;
	foreach ($vlan_ids as $vlan_id => $vlan_num) {
		$active_vlans[$vlan_id]['vlan_id'] = $vlan_num;
		$active_vlans[$vlan_id]['vlan_name'] = $vlan_names[$vlan_id];
		$active_vlans++;

		$i++;
	}
	mactrack_debug('Vlan assembly complete.');

	if (cacti_sizeof($active_vlans) > 0) {
		$i = 0;
		/* get the port status information */
		$port_results = xform_stripped_oid ( '.1.3.6.1.2.1.17.1.4.1.2', $device );
		$mac_results = xform_stripped_oid('.1.3.6.1.2.1.17.7.1.2.2.1.2', $device );

		$i = 1;
		foreach ( $port_results as $port )
		 {
			$nport_results[$i++] = $port;
		 }
		$i = 0;
		$j = 0;
		$port_array = array();
		foreach ($mac_results as $num => $mac_result) {
			if ( $mac_result != 0 ) {
				$Xvlanid = substr ($num, 0, strpos($num, '.'));
				$Xmac    = mach(substr($num, strpos($num, '.') + 1));

				$ifIndex = $nport_results[$mac_result];
				$ifType = $ifInterfaces[$ifIndex]['ifType'];
				$ifName = $ifInterfaces[$ifIndex]['ifName'];
				$portName = $ifName;
				$portTrunkStatus = $vlan_trunkstatus[$ifIndex];

				/* only output legitimate end user ports */
				if ( $portName != '' and $portName != '1' ) {
					$port_array[$i]['vlan_id'] = $active_vlans[$Xvlanid]['vlan_id'];//@$vlan_ids[$Xvlanid];
					$port_array[$i]['vlan_name'] = $active_vlans[$Xvlanid]['vlan_name'];//@$vlan_names[$Xvlandid];
					$port_array[$i]['port_number'] = $mac_result;
					$port_array[$i]['port_name'] = trim ( $ifName );
					$port_array[$i]['mac_address'] = xform_mac_address($Xmac);

					mactrack_debug('VLAN: ' . $port_array[$i]['vlan_id'] . ', ' .
						'NAME: ' . $port_array[$i]['vlan_name'] . ', ' .
						'PORT: ' . $ifIndex . ', ' .
						'NAME: ' . $port_array[$i]['port_name'] . ', ' .
						'MAC: ' . $port_array[$i]['mac_address']);

					$i++;
				}
				$j++;
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

