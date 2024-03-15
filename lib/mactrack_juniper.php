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

/* register this functions scanning functions */
if (!isset($mactrack_scanning_functions)) { $mactrack_scanning_functions = array(); }
array_push($mactrack_scanning_functions, 'get_JEX_switch_ports');

function mach($macd, $del = ':') {
	$result = '';
	$macsd  = explode ('.', $macd);
	foreach ($macsd as $d) {
		$hex     = strtoupper(sprintf("%02x$del", $d));
		$result .= $hex;
	}
	$result = substr ($result, 0, -1);
	return ($result);
}

/* get_JEX_switch_ports
        obtains port associations for Juniper Ex Switches.
*/
function get_JEX_switch_ports($site, &$device, $lowPort = 0, $highPort = 0) {
	global $debug, $scan_date;

	/* initialize port counters */
	$device['ports_total']  = 0;
	$device['ports_active'] = 0;
	$device['ports_trunk']  = 0;

	/* get VLAN information */
	$vlan_ids   = xform_standard_indexed_data('.1.3.6.1.4.1.2636.3.40.1.5.1.5.1.5', $device);
	$vlan_names = xform_standard_indexed_data('.1.3.6.1.4.1.2636.3.40.1.5.1.5.1.2', $device);

	$device['vlans_total'] = cacti_sizeof($vlan_ids) - 1;
	mactrack_debug('VLAN data collected. There are ' . (cacti_sizeof($vlan_ids) - 1) . ' VLANS.');

	/* get VLAN Trunk status */
	$vlan_trunkstatus = xform_standard_indexed_data('.1.3.6.1.4.1.2636.3.40.1.5.1.7.1.5', $device);
	foreach ($vlan_trunkstatus as $vts) {
		if ($vts == 2) {
			$device['ports_trunk']++;
		}
	}
	/* get the ifIndexes for the device */
	$ifIndexes = xform_standard_indexed_data('.1.3.6.1.2.1.2.2.1.1', $device);
	mactrack_debug('ifIndexes data collection complete');

	/* get and store the interfaces table */
	$ifInterfaces = build_InterfacesTable($device, $ifIndexes, true, true);

	/* get port description */

	$portDescription = xform_standard_indexed_data('.1.0.8802.1.1.2.1.3.7.1.4', $device);

	/* get the ignore ports list from device */
	$ignore_ports = port_list_to_array($device['ignorePorts']);

	foreach ($ifIndexes as $ifIndex) {
		$ifInterfaces[$ifIndex]['trunkPortState'] = mactrack_arr_key($vlan_trunkstatus, $ifIndex);
		$ifInterfaces[$ifIndex]['portDesc'] = mactrack_arr_key($portDescription, $ifIndex);

		if (($ifInterfaces[$ifIndex]['ifType'] == 'propVirtual(53)') ||
			($ifInterfaces[$ifIndex]['ifType'] == '53') ||
			($ifInterfaces[$ifIndex]['ifType'] == '161') ||
			($ifInterfaces[$ifIndex]['ifType'] == 'ieee8023adLag(161)')) {
			$device['ports_total']++;
		}
	}
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
		//$port_results = get_base_dot1dTpFdbEntry_ports($site, $device, $ifInterfaces, '', '', false);
		$mac_results  = xform_stripped_oid ('.1.3.6.1.2.1.17.7.1.2.2.1.2', $device);
		$port_results = xform_stripped_oid ('.1.3.6.1.2.1.17.1.4.1.2', $device);

		$i = 0;
		$j = 0;
		$port_array = array();
		foreach ($mac_results as $num => $mac_result) {
			if ($mac_result != 0) {
				$Xvlanid = substr($num, strpos($num, '.')+1, strpos($num, '.',1)-1);
				$Xmac    = mach(substr($num, strpos($num, '.',1) + 1));

				$ifIndex  = mactrack_arr_key($port_results, ".".strval($mac_result));
				$ifType   = isset($ifInterfaces[$ifIndex]['ifType']) ? $ifInterfaces[$ifIndex]['ifType'] : '';
				$ifName   = isset($ifInterfaces[$ifIndex]['ifName']) ? $ifInterfaces[$ifIndex]['ifName'] : '';
				$ifDesc   = "";
				$ifDesc   = isset($ifInterfaces[$ifIndex]['portDesc']) ? $ifInterfaces[$ifIndex]['portDesc'] : '';
				$portName = $ifName;

				$portTrunkStatus = isset($ifInterfaces[$ifIndex]['trunkPortState']) ? $ifInterfaces[$ifIndex]['trunkPortState'] : '';

				/* only output legitimate end user ports */
				//if ((($ifType >= 6) && ($ifType <= 9)) and ( $portName != '' or $portName != '1' )) {
				if ( $portName != '' and $portName != '1' ) {
					$port_array[$i]['vlan_id'] = $active_vlans[$Xvlanid]['vlan_id']; //@$vlan_ids[$Xvlanid];
					$port_array[$i]['vlan_name'] = $active_vlans[$Xvlanid]['vlan_name']; //@$vlan_names[$Xvlandid];
					//$port_array[$i]['port_number'] = @$port_results[".".strval($mac_result)];
					$port_array[$i]['port_number'] = trim ( $ifName );
					if(isset($ifDesc)){
						$port_array[$i]['port_name'] = $ifDesc;
					}else{
						$port_array[$i]['port_name'] = trim ( $ifName );
					}
					$port_array[$i]['mac_address'] = xform_mac_address($Xmac);
					$device['ports_active']++;

					mactrack_debug('VLAN: ' . $port_array[$i]['vlan_id'] . ', ' .
						'NAME: ' . $port_array[$i]['vlan_name'] . ', ' .
						'PORT: ' . $ifIndex . ', ' .
						'NAME: ' . $port_array[$i]['port_number'] . ', ' .
						'DESC: ' . $port_array[$i]['port_name'] . ', ' .
						'MAC: '  . $port_array[$i]['mac_address']);

					$i++;
				}
				$j++;
			}
		}
		$newPorts=array();
		foreach ($port_array as $port) {
			if(in_array($port['port_number'], $ignore_ports)===false){
				array_push($newPorts, $port);
			}
		}
		/* display completion message */
		mactrack_debug('INFO: HOST: ' . $device['hostname'] . ', TYPE: ' . substr($device['snmp_sysDescr'],0,40) . ', TOTAL PORTS: ' . $device['ports_total'] . ', ACTIVE PORTS: ' . $device['ports_active']);

		$device['last_runmessage'] = 'Data collection completed ok';
		$device['macs_active'] = cacti_sizeof($newPorts);

		db_store_device_port_results($device, $newPorts, $scan_date);
	} else {
		mactrack_debug('INFO: HOST: ' . $device['hostname'] . ', TYPE: ' . substr($device['snmp_sysDescr'],0,40) . ', No active devices on this network device.');

		$device['snmp_status'] = HOST_UP;
		$device['last_runmessage'] = 'Data collection completed ok. No active devices on this network device.';
	}

	return $device;
}

