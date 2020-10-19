<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2020 The Cacti Group                                 |
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
array_push($mactrack_scanning_functions, 'get_catalyst_dot1dTpFdbEntry_ports');
array_push($mactrack_scanning_functions, 'get_IOS_dot1dTpFdbEntry_ports');

if (!isset($mactrack_scanning_functions_ip)) { $mactrack_scanning_functions_ip = array(); }
array_push($mactrack_scanning_functions_ip, 'get_cisco_dhcpsnooping_table');

if (!isset($mactrack_scanning_functions_dot1x)) { $mactrack_scanning_functions_dot1x = array(); }
array_push($mactrack_scanning_functions_dot1x, 'get_cisco_dot1x_table');

/* get_catalyst_doet1dTpFdbEntry_ports
	obtains port associations for Cisco Catalyst Swtiches.  Catalyst
	switches are unique in that they support a different snmp_readstring for
	every VLAN interface on the switch.
*/
function get_catalyst_dot1dTpFdbEntry_ports($site, &$device, $lowPort = 0, $highPort = 0) {
	global $debug, $scan_date;

	/* initialize port counters */
	$device['ports_total']  = 0;
	$device['ports_active'] = 0;
	$device['ports_trunk']  = 0;
	$device['vlans_total']  = 0;

	/* Variables to determine VLAN information */
	$vlan_ids         = xform_standard_indexed_data('.1.3.6.1.4.1.9.9.46.1.3.1.1.2', $device);
	$vlan_names       = xform_standard_indexed_data('.1.3.6.1.4.1.9.9.46.1.3.1.1.4', $device);
	$vlan_trunkstatus = xform_standard_indexed_data('.1.3.6.1.4.1.9.9.46.1.6.1.1.14', $device);

	$device['vlans_total'] = sizeof($vlan_ids) - 3;
	mactrack_debug('There are ' . (cacti_sizeof($vlan_ids)-3) . ' VLANS.');

	/* get the ifIndexes for the device */
	$ifIndexes = xform_standard_indexed_data('.1.3.6.1.2.1.2.2.1.1', $device);
	mactrack_debug('ifIndexes data collection complete');

	/* get and store the interfaces table */
	$ifInterfaces = build_InterfacesTable($device, $ifIndexes, true, false);

	/* get the Voice VLAN information if it exists */
	$portVoiceVLANs = xform_standard_indexed_data('.1.3.6.1.4.1.9.9.87.1.4.1.1.37.0', $device);
	if (cacti_sizeof($portVoiceVLANs)) {
		$vvlans = true;
	} else {
		$portVoiceVLANs = xform_standard_indexed_data('.1.3.6.1.4.1.9.9.68.1.5.1.1.1', $device);
		if (cacti_sizeof($portVoiceVLANs)) {
			$vvlans = true;
		} else {
			$vvlans = false;
		}
	}
	mactrack_debug('Cisco Voice VLAN collection complete');
	if ($vvlans) {
		mactrack_debug('Voice VLANs exist on this device');
	} else {
		mactrack_debug('Voice VLANs do not exist on this device');
	}

	if (cacti_sizeof($ifIndexes)) {
		foreach ($ifIndexes as $ifIndex) {
			$ifInterfaces[$ifIndex]['trunkPortState'] = isset($vlan_trunkstatus[$ifIndex]) ? $vlan_trunkstatus[$ifIndex]:'';
			if ($vvlans) {
				$ifInterfaces[$ifIndex]['vVlanID'] = isset($portVoiceVLANs[$ifIndex]) ? $portVoiceVLANs[$ifIndex]:'';
			}

			if ($ifInterfaces[$ifIndex]['ifType'] == 6) {
				$device['ports_total']++;
			}
		}
	}
	mactrack_debug('ifInterfaces assembly complete.');

	/* get the portNames */
	$portNames = xform_cisco_workgroup_port_data('.1.3.6.1.4.1.9.5.1.4.1.1.4', $device);
	mactrack_debug('portNames data collected.');

	/* get trunking status */
	$portTrunking = xform_cisco_workgroup_port_data('.1.3.6.1.4.1.9.5.1.9.3.1.8', $device);
	mactrack_debug('portTrunking data collected.');

	/* calculate the number of end user ports */
	if (cacti_sizeof($portTrunking)) {
	foreach ($portTrunking as $portTrunk) {
		if ($portTrunk == 1) {
			$device['ports_trunk']++;
		}
	}
	}

	/* build VLAN array from results */
	$i = 0;
	$j = 0;
	$active_vlans = array();

	if (cacti_sizeof($vlan_ids)) {
	foreach ($vlan_ids as $vlan_number => $vlanStatus) {
		$vlanName = $vlan_names[$vlan_number];

		if ($vlanStatus == 1) { /* vlan is operatinal */
			switch ($vlan_number) {
			case '1002':
			case '1003':
			case '1004':
			case '1005':
				$active_vlan_ports = 0;
				break;
			default:
				if ($device['snmp_version'] < '3') {
					$snmp_readstring = $device['snmp_readstring'] . '@' . $vlan_number;
					$active_vlan_ports = cacti_snmp_get($device['hostname'], $snmp_readstring,
						'.1.3.6.1.2.1.17.1.2.0', $device['snmp_version'],
						$device['snmp_username'], $device['snmp_password'],
						$device['snmp_auth_protocol'], $device['snmp_priv_passphrase'],
						$device['snmp_priv_protocol'], $device['snmp_context'],
						$device['snmp_port'], $device['snmp_timeout'], $device['snmp_retries'],
						SNMP_POLLER, $device['snmp_engine_id']);
				} else {
					$active_vlan_ports = cacti_snmp_get($device['hostname'], '',
						'.1.3.6.1.2.1.17.1.2.0', $device['snmp_version'],
						$device['snmp_username'], $device['snmp_password'],
						$device['snmp_auth_protocol'], $device['snmp_priv_passphrase'],
						$device['snmp_priv_protocol'], 'vlan-' . $vlan_number,
						$device['snmp_port'], $device['snmp_timeout'], $device['snmp_retries'],
						SNMP_POLLER, $device['snmp_engine_id']);
				}

				if ((!is_numeric($active_vlan_ports)) || ($active_vlan_ports) < 0) {
					$active_vlan_ports = 0;
				}

				mactrack_debug('VLAN Analysis for VLAN: ' . $vlan_number . '/' . $vlanName . ' is complete. ACTIVE PORTS: ' . $active_vlan_ports);

				if ($active_vlan_ports > 0) { /* does the vlan have active ports on it */
					$active_vlans[$j]['vlan_id'] = $vlan_number;
					$active_vlans[$j]['vlan_name'] = $vlanName;
					$active_vlans[$j]['active_ports'] = $active_vlan_ports;
					$active_vlans++;

					$j++;
				}
			}
		}

		$i++;
	}
	}

	if (cacti_sizeof($active_vlans)) {
		$i = 0;
		/* get the port status information */
		foreach ($active_vlans as $active_vlan) {
			/* ignore empty vlans */
			if ($active_vlan['active_ports'] <= $device['ports_trunk']) {
				$active_vlans[$i]['port_results'] = array();
				$i++;
				continue;
			}

			if ($device['snmp_version'] < '3') {
				$snmp_readstring = $device['snmp_readstring'] . '@' . $active_vlan['vlan_id'];
			} else {
				$snmp_readstring = 'cisco@' . $active_vlan['vlan_id'];
			}

			mactrack_debug('Processing has begun for VLAN: ' . $active_vlan['vlan_id']);

			if ($highPort == 0) {
				$active_vlans[$i]['port_results'] = get_base_dot1dTpFdbEntry_ports($site, $device, $ifInterfaces, $snmp_readstring, false);
			} else {
				$active_vlans[$i]['port_results'] = get_base_dot1dTpFdbEntry_ports($site, $device, $ifInterfaces, $snmp_readstring, false, $lowPort, $highPort);
			}

			/* get bridge port mappings */
			/* get bridge port to ifIndex mappings */
			mactrack_debug('Bridge port information about to be collected.');
			mactrack_debug('VLAN_ID: ' . $active_vlans[$i]['vlan_id'] . ', VLAN_NAME: ' . $active_vlans[$i]['vlan_name'] . ', ACTIVE PORTS: ' . sizeof($active_vlans[$i]['port_results']));

			if (cacti_sizeof($active_vlans[$i]['port_results']) > 0) {
				$brPorttoifIndexes[$i] = xform_standard_indexed_data('.1.3.6.1.2.1.17.1.4.1.2', $device, $snmp_readstring);
				mactrack_debug('Bridge port information collection complete.');
			}

			$i++;
		}

		mactrack_debug('Final cross check\'s now being performed.');
		$i = 0;
		$j = 0;
		$port_array = array();

		if (cacti_sizeof($active_vlans)) {
			foreach ($active_vlans as $active_vlan) {
				if (cacti_sizeof($active_vlan['port_results'])) {
					foreach ($active_vlan['port_results'] as $port_result) {
						$ifIndex         = @$brPorttoifIndexes[$j][$port_result['port_number']];
						$ifType          = (isset($ifInterfaces[$ifIndex]['ifType']) ? $ifInterfaces[$ifIndex]['ifType'] : '');
						$ifName          = (isset($ifInterfaces[$ifIndex]['ifName']) ? $ifInterfaces[$ifIndex]['ifName'] : '');
						$portName        = (isset($portNames[$ifName]) ? $portNames[$ifName] : '');
						$portTrunk       = (isset($portTrunking[$ifName]) ? $portTrunking[$ifName] : '');
						$portTrunkStatus = (isset($ifInterfaces[$ifIndex]['trunkPortState']) ? $ifInterfaces[$ifIndex]['trunkPortState'] : '');

						if ($vvlans) {
							$vVlanID = (isset($portVoiceVLANs[$ifIndex]) ? $portVoiceVLANs[$ifIndex] : '');
						} else {
							$vVlanID = -1;
						}

						/* only output legitamate end user ports */
						if (($ifType == 6) && ($portTrunk == 2)) {
							if (($portTrunkStatus == '2')||($portTrunkStatus == '4')||($portTrunkStatus =='')) {
								$port_array[$i]['vlan_id']     = $active_vlan['vlan_id'];
								$port_array[$i]['vlan_name']   = $active_vlan['vlan_name'];
								$port_array[$i]['port_number'] = $ifInterfaces[$ifIndex]['ifName'];
								$port_array[$i]['port_name']   = $portName;
								$port_array[$i]['mac_address'] = xform_mac_address($port_result['mac_address']);
								$device['ports_active']++;
								$i++;

								mactrack_debug('VLAN: ' . $active_vlan['vlan_id'] . ', ' .
									'NAME: ' . $active_vlan['vlan_name'] . ', ' .
									'PORT: ' . $ifInterfaces[$ifIndex]['ifName'] . ', ' .
									'NAME: ' . $portName . ', ' .
									'MAC: ' . $port_result['mac_address']);
							}
						}
					}
				}

				$j++;
			}
		}

		/* display completion message */
		mactrack_debug('INFO: HOST: ' . $device['hostname'] . ', TYPE: ' . substr($device['snmp_sysDescr'],0,40) . ', TOTAL PORTS: ' . $device['ports_total'] . ', ACTIVE PORTS: ' . $device['ports_active']);

		$device['last_runmessage'] = 'Data collection completed ok';
		$device['macs_active'] = sizeof($port_array);

		db_store_device_port_results($device, $port_array, $scan_date);
	} else {
		mactrack_debug('INFO: HOST: ' . $device['hostname'] . ', TYPE: ' . substr($device['snmp_sysDescr'],0,40) . ', No active devcies on this network device.');

		$device['snmp_status'] = HOST_UP;
		$device['last_runmessage'] = 'Data collection completed ok. No active devices on this network device.';
	}

	return $device;
}

/* get_IOS_dot1dTpFdbEntry_ports
	obtains port associations for Cisco Catalyst Swtiches.  Catalyst
	switches are unique in that they support a different snmp_readstring for
	every VLAN interface on the switch.
*/
function get_IOS_dot1dTpFdbEntry_ports($site, &$device, $lowPort = 0, $highPort = 0) {
	global $debug, $scan_date;

	/* initialize port counters */
	$device['ports_total']  = 0;
	$device['ports_active'] = 0;
	$device['ports_trunk']  = 0;
	$device['vlans_total']  = 0;

	/* Variables to determine VLAN information */
	$vlan_ids         = xform_standard_indexed_data('.1.3.6.1.4.1.9.9.46.1.3.1.1.2', $device);
	$vlan_names       = xform_standard_indexed_data('.1.3.6.1.4.1.9.9.46.1.3.1.1.4', $device);
	$vlan_trunkstatus = xform_standard_indexed_data('.1.3.6.1.4.1.9.9.46.1.6.1.1.14', $device);

	$device['vlans_total'] = sizeof($vlan_ids) - 3;
	mactrack_debug('There are ' . (cacti_sizeof($vlan_ids)-3) . ' VLANS.');

	/* get the ifIndexes for the device */
	$ifIndexes = xform_standard_indexed_data('.1.3.6.1.2.1.2.2.1.1', $device);
	mactrack_debug('ifIndexes data collection complete');

	$ifInterfaces = build_InterfacesTable($device, $ifIndexes, true, true);

	/* get the Voice VLAN information if it exists */
	$portVoiceVLANs = xform_standard_indexed_data('.1.3.6.1.4.1.9.9.87.1.4.1.1.37.0', $device);
	if (cacti_sizeof($portVoiceVLANs) > 0) {
		$vvlans = true;
	} else {
		$portVoiceVLANs = xform_standard_indexed_data('.1.3.6.1.4.1.9.9.68.1.5.1.1.1', $device);
		if (cacti_sizeof($portVoiceVLANs) > 0) {
			$vvlans = true;
		} else {
			$vvlans = false;
		}
	}

	mactrack_debug('Cisco Voice VLAN collection complete');
	if ($vvlans) {
		mactrack_debug('Voice VLANs exist on this device');
	} else {
		mactrack_debug('Voice VLANs do not exist on this device');
	}

	if (cacti_sizeof($ifIndexes)) {
		foreach ($ifIndexes as $ifIndex) {
			$ifInterfaces[$ifIndex]['trunkPortState'] = (isset($vlan_trunkstatus[$ifIndex]) ? $vlan_trunkstatus[$ifIndex] : '');
			if ($vvlans) {
				$ifInterfaces[$ifIndex]['vVlanID'] = (isset($portVoiceVLANs[$ifIndex]) ? $portVoiceVLANs[$ifIndex] : '');
			}

			if ($ifInterfaces[$ifIndex]['ifType'] == 6) {
				$device['ports_total']++;
			}

			if ($ifInterfaces[$ifIndex]['trunkPortState'] == '1') {
				$device['ports_trunk']++;
			}
		}
	}
	mactrack_debug('ifInterfaces assembly complete.');

	/* build VLAN array from results */
	$i = 0;
	$j = 0;
	$active_vlans = array();

	if (cacti_sizeof($vlan_ids)) {
		foreach ($vlan_ids as $vlan_number => $vlanStatus) {
			$vlanName = @$vlan_names[$vlan_number];

			if ($vlanStatus == 1) { /* vlan is operatinal */
				switch ($vlan_number) {
				case '1002':
				case '1003':
				case '1004':
				case '1005':
					$active_vlan_ports = 0;
					break;
				default:
					if ($device['snmp_version'] < '3') {
						$snmp_readstring = $device['snmp_readstring'] . '@' . $vlan_number;
						$active_vlan_ports = cacti_snmp_get($device['hostname'], $snmp_readstring,
							'.1.3.6.1.2.1.17.1.2.0', $device['snmp_version'],
							$device['snmp_username'], $device['snmp_password'],
							$device['snmp_auth_protocol'], $device['snmp_priv_passphrase'],
							$device['snmp_priv_protocol'], $device['snmp_context'],
							$device['snmp_port'], $device['snmp_timeout'], $device['snmp_retries'],
							SNMP_POLLER, $device['snmp_engine_id']);
					} else {
						$active_vlan_ports = cacti_snmp_get($device['hostname'], 'vlan-' . $vlan_number,
							'.1.3.6.1.2.1.17.1.2.0', $device['snmp_version'],
							$device['snmp_username'], $device['snmp_password'],
							$device['snmp_auth_protocol'], $device['snmp_priv_passphrase'],
							$device['snmp_priv_protocol'], 'vlan-' . $vlan_number,
							$device['snmp_port'], $device['snmp_timeout'], $device['snmp_retries'],
							SNMP_POLLER, $device['snmp_engine_id']);
					}

					if ((!is_numeric($active_vlan_ports)) || ($active_vlan_ports) < 0) {
						$active_vlan_ports = 0;
					}

					mactrack_debug('VLAN Analysis for VLAN: ' . $vlan_number . '/' . $vlanName . ' is complete. ACTIVE PORTS: ' . $active_vlan_ports);

					if ($active_vlan_ports > 0) { /* does the vlan have active ports on it */
						$active_vlans[$j]['vlan_id'] = $vlan_number;
						$active_vlans[$j]['vlan_name'] = $vlanName;
						$active_vlans[$j]['active_ports'] = $active_vlan_ports;
						$active_vlans++;

						$j++;
					}
				}
			}

			$i++;
		}
	}

	if (cacti_sizeof($active_vlans)) {
		$i = 0;
		/* get the port status information */
		foreach ($active_vlans as $active_vlan) {
			if ($device['snmp_version'] < '3') {
				$snmp_readstring = $device['snmp_readstring'] . '@' . $active_vlan['vlan_id'];
			} else {
				$snmp_readstring = 'vlan-' . $active_vlan['vlan_id'];
			}

			mactrack_debug('Processing has begun for VLAN: ' . $active_vlan['vlan_id']);
			if ($highPort == 0) {
				$active_vlans[$i]['port_results'] = get_base_dot1dTpFdbEntry_ports($site, $device, $ifInterfaces, $snmp_readstring, false);
			} else {
				$active_vlans[$i]['port_results'] = get_base_dot1dTpFdbEntry_ports($site, $device, $ifInterfaces, $snmp_readstring, false, $lowPort, $highPort);
			}

			/* get bridge port mappings */
			/* get bridge port to ifIndex mappings */
			mactrack_debug('Bridge port information about to be collected.');
			mactrack_debug('VLAN_ID: ' . $active_vlans[$i]['vlan_id'] . ', VLAN_NAME: ' . $active_vlans[$i]['vlan_name'] . ', ACTIVE PORTS: ' . sizeof($active_vlans[$i]['port_results']));

			if (cacti_sizeof($active_vlans[$i]['port_results']) > 0) {
				$brPorttoifIndexes[$i] = xform_standard_indexed_data('.1.3.6.1.2.1.17.1.4.1.2', $device, $snmp_readstring);
				mactrack_debug('Bridge port information collection complete.');
			}
			$i++;
		}

		$i = 0;
		$j = 0;
		$port_array = array();

		mactrack_debug('Final cross check\'s now being performed.');
		if (cacti_sizeof($active_vlans)) {
			foreach ($active_vlans as $active_vlan) {
				if (cacti_sizeof($active_vlan['port_results'])) {
					foreach ($active_vlan['port_results'] as $port_result) {
						$ifIndex    = (isset($brPorttoifIndexes[$j][$port_result['port_number']]) ? $brPorttoifIndexes[$j][$port_result['port_number']] : '');
						$ifType     = (isset($ifInterfaces[$ifIndex]['ifType']) ? $ifInterfaces[$ifIndex]['ifType'] : '');
						$ifName     = (isset($ifInterfaces[$ifIndex]['ifName']) ? $ifInterfaces[$ifIndex]['ifName'] : '');
						$portNumber = (isset($ifInterfaces[$ifIndex]['ifName']) ? $ifInterfaces[$ifIndex]['ifName'] : '');
						$portName   = (isset($ifInterfaces[$ifIndex]['ifAlias']) ? $ifInterfaces[$ifIndex]['ifAlias'] : '');
						$portTrunk  = (isset($portTrunking[$ifName]) ? $portTrunking[$ifName] : '');

						if ($vvlans) {
							$vVlanID = (isset($portVoiceVLANs[$ifIndex]) ? $portVoiceVLANs[$ifIndex] : '');
						} else {
							$vVlanID = -1;
						}

						$portTrunkStatus = (isset($ifInterfaces[$ifIndex]['trunkPortState']) ? $ifInterfaces[$ifIndex]['trunkPortState'] : '');

						/* only output legitamate end user ports */
						if ($ifType == 6) {
							if (($portTrunkStatus == '2') ||
								(empty($portTrunkStatus)) ||
								(($vVlanID > 0) && ($vVlanID <= 1000))) {
								$port_array[$i]['vlan_id']     = $active_vlan['vlan_id'];
								$port_array[$i]['vlan_name']   = $active_vlan['vlan_name'];
								$port_array[$i]['port_number'] = $portNumber;
								$port_array[$i]['port_name']   = $portName;
								$port_array[$i]['mac_address'] = xform_mac_address($port_result['mac_address']);
								$device['ports_active']++;
								$i++;

								mactrack_debug('VLAN: ' . $active_vlan['vlan_id'] . ', ' .
									'NAME: ' . $active_vlan['vlan_name'] . ', ' .
									'PORT: ' . $portNumber . ', ' .
									'NAME: ' . $portName . ', ' .
									'MAC: ' . $port_result['mac_address']);
							}
						}
					}
				}

				$j++;
			}
		}

		/* display completion message */
		mactrack_debug('INFO: HOST: ' . $device['hostname'] . ', TYPE: ' . substr($device['snmp_sysDescr'],0,40) . ', TOTAL PORTS: ' . $device['ports_total'] . ', ACTIVE PORTS: ' . $device['ports_active']);

		$device['last_runmessage'] = 'Data collection completed ok';
		$device['macs_active'] = sizeof($port_array);

		db_store_device_port_results($device, $port_array, $scan_date);
	} else {
		mactrack_debug('INFO: HOST: ' . $device['hostname'] . ', TYPE: ' . substr($device['snmp_sysDescr'],0,40) . ', No active end devices on this device.');

		$device['snmp_status'] = HOST_UP;
		$device['last_runmessage'] = 'Data collection completed ok.  No active end devices on this device.';
	}

	return $device;
}

/*	get_cisco_dhcpsnooping_table - This function reads a devices DHCP Snooping table for a site and stores
  the IP address and MAC address combinations in the mac_track_ips table. Since CISCO-DHCP-SNOOPING-MIB is not
  fully implemented we match MACs from dot1dTpFdbEntry so some IPs won't get the MAC populated.
  Send an email to mii@external.cisco.com with the word 'help' in the subject to get MIBs supported per IOS Image.
*/
function get_cisco_dhcpsnooping_table($site, &$device) {
	global $debug, $scan_date;

	/* get the cdsBindingInterface Index for the device */
	$cdsBindingInterface 	= xform_stripped_oid('.1.3.6.1.4.1.9.9.380.1.4.1.1.5', $device);
	$vlan_ids            	= xform_standard_indexed_data('.1.3.6.1.4.1.9.9.46.1.3.1.1.2', $device);
	$vlan_names          	= xform_standard_indexed_data('.1.3.6.1.4.1.9.9.46.1.3.1.1.4', $device);
	$cdsBindingsIpAddress 	= xform_stripped_oid('.1.3.6.1.4.1.9.9.380.1.4.1.1.4', $device);
	$cdsBindingEntries   = array();
	$dot1dTpFdbEntries   = array();

	/* build VLAN array from results */
	$i = 0;
	$j = 0;
	$active_vlans = array();

	if (cacti_sizeof($vlan_ids)) {
		foreach ($vlan_ids as $vlan_number => $vlanStatus) {
			$vlanName = $vlan_names[$vlan_number];

			if ($vlanStatus == 1) { /* vlan is operatinal */
				switch ($vlan_number) {
				case '1002':
				case '1003':
				case '1004':
				case '1005':
					$active_vlan_ports = 0;
					break;
				default:
					if ($device['snmp_version'] < '3') {
						$snmp_readstring = $device['snmp_readstring'] . '@' . $vlan_number;
						$active_vlan_ports = cacti_snmp_get($device['hostname'], $snmp_readstring,
							'.1.3.6.1.2.1.17.1.2.0', $device['snmp_version'],
							$device['snmp_username'], $device['snmp_password'],
							$device['snmp_auth_protocol'], $device['snmp_priv_passphrase'],
							$device['snmp_priv_protocol'], $device['snmp_context'],
							$device['snmp_port'], $device['snmp_timeout'], $device['snmp_retries'],
							SNMP_POLLER, $device['snmp_engine_id']);
					} else {
						$active_vlan_ports = cacti_snmp_get($device['hostname'], 'vlan-' . $vlan_number,
							'.1.3.6.1.2.1.17.1.2.0', $device['snmp_version'],
							$device['snmp_username'], $device['snmp_password'],
							$device['snmp_auth_protocol'], $device['snmp_priv_passphrase'],
							$device['snmp_priv_protocol'], 'vlan-' . $vlan_number,
							$device['snmp_port'], $device['snmp_timeout'], $device['snmp_retries'],
							SNMP_POLLER, $device['snmp_engine_id']);
					}

					if ((!is_numeric($active_vlan_ports)) || ($active_vlan_ports) < 0) {
						$active_vlan_ports = 0;
					}

					mactrack_debug('VLAN Analysis for VLAN: ' . $vlan_number . '/' . $vlanName . ' is complete. ACTIVE PORTS: ' . $active_vlan_ports);

					if ($active_vlan_ports > 0) { /* does the vlan have active ports on it */
						$active_vlans[$j]['vlan_id'] = $vlan_number;
						$active_vlans[$j]['vlan_name'] = $vlanName;
						$active_vlans[$j]['active_ports'] = $active_vlan_ports;
						$active_vlans++;

						$j++;
					}
				}
			}

			$i++;
		}
	}

	if (cacti_sizeof($active_vlans)) {
		$n = 1;
		$dot1dTpFdbEntry   = array();

		/* get the port status information */
		foreach ($active_vlans as $active_vlan) {
			if ($device['snmp_version'] < '3') {
				$snmp_readstring = $device['snmp_readstring'] . '@' . $active_vlan['vlan_id'];
			} else {
				$snmp_readstring = 'vlan-' . $active_vlan['vlan_id'];
			}

			mactrack_debug('Processing has begun for VLAN: ' . $active_vlan['vlan_id']);

			if (cacti_sizeof($active_vlans)) {
				$dot1dTpFdbEntries[$n] = xform_stripped_oid('.1.3.6.1.2.1.17.4.3.1.1', $device, $snmp_readstring);
				foreach ($dot1dTpFdbEntries[$n] as $key => $val) {
					$dot1dTpFdbEntries[$n][$active_vlan['vlan_id']. '.' . $key] = $val; //ugly tweak to add vlan id to OID.
					unset($dot1dTpFdbEntries[$n][$key]);
				}

				mactrack_debug('dot1dTpFdbEntry data collection complete :' . sizeof($dot1dTpFdbEntries[$n]));

				if ($n > 0 ) {
					$dot1dTpFdbEntry = array_merge($dot1dTpFdbEntry, $dot1dTpFdbEntries[$n]);
					mactrack_debug('merge data collection complete : ' . sizeof($dot1dTpFdbEntry));
				}
			}
			$n++;

			mactrack_debug('dot1dTpFdbEntry vlan_id: ' . $active_vlan['vlan_id']);
		}

		$keys = array_keys($cdsBindingInterface);

		$j = 0;
		if (cacti_sizeof($cdsBindingInterface)) {
			foreach ($cdsBindingInterface as $cdsBindingIndex) {
				$cdsBindingEntries[$j]['cdsBindingIndex'] = $cdsBindingIndex;
				$cdsBindingEntries[$j]['dot1dTpFdbEntry'] = isset($dot1dTpFdbEntry[$keys[$j]]) ? xform_mac_address($dot1dTpFdbEntry[$keys[$j]]):'';
				$cdsBindingEntries[$j]['cdsBindingsIpAddress'] = isset($cdsBindingsIpAddress[$keys[$j]]) ? xform_net_address($cdsBindingsIpAddress[$keys[$j]]):'';
				$j++;
			}

			mactrack_debug('cdsBindingEntries Total entries: ' . sizeof($cdsBindingEntries));
		}

		mactrack_debug('cdsBindingEntries assembly complete.');
	} else {
		mactrack_debug('INFO: HOST: ' . $device['hostname'] . ', TYPE: ' . substr($device['snmp_sysDescr'],0,40) . ', No active end devices on this device.');

		$device['snmp_status'] = HOST_UP;
		$device['last_runmessage'] = 'Data collection completed ok.  No active end devices on this device.';
	}

	/* output details to database */
	if (cacti_sizeof($cdsBindingEntries)) {
		foreach ($cdsBindingEntries as $cdsBindingEntry) {
			if ($cdsBindingEntry['cdsBindingsIpAddress'] != '') { //It's acceptable to have IPs without a MAC (meaning that MAC is not present but DHCP entry is still present) here but not the other way around.
				$insert_string = 'REPLACE INTO mac_track_ips
					(site_id,device_id,hostname,device_name,port_number,
					mac_address,ip_address,scan_date)
					VALUES (' .
					$device['site_id'] . ',' .
					$device['device_id'] . ',' .
					db_qstr($device['hostname']) . ',' .
					db_qstr($device['device_name']) . ',' .
					db_qstr($cdsBindingEntry['cdsBindingIndex']) . ',' .
					db_qstr($cdsBindingEntry['dot1dTpFdbEntry']) . ',' .
					db_qstr($cdsBindingEntry['cdsBindingsIpAddress']) . ',' .
					db_qstr($scan_date) . ')';

				//mactrack_debug('SQL: ' . $insert_string);

				db_execute($insert_string);
			}
		}
	}

	/* save ip information for the device */
	$device['ips_total'] = sizeof($cdsBindingEntries);
	db_execute('UPDATE mac_track_devices SET ips_total =' . $device['ips_total'] . ' WHERE device_id=' . $device['device_id']);

	mactrack_debug('HOST: ' . $device['hostname'] . ', IP address information collection complete');
}

/*	get_cisco_dot1x_table - This function reads a devices Dot1x table for a site and stores
  the IP address, MAC address, Username, Domain and Status combinations in the mac_track_dot1x table.
*/
function get_cisco_dot1x_table($site, &$device) {
	global $debug, $scan_date;

	/* get the cafSessionAuthUserName from the device */
	$cafSessionAuthUserName = xform_stripped_oid('.1.3.6.1.4.1.9.9.656.1.4.1.1.10', $device);

	if (cacti_sizeof($cafSessionAuthUserName)) {
		mactrack_debug('cafSessionAuthUserName data collection complete: ' . sizeof($cafSessionAuthUserName));
		$cafSessionClientMacAddress = xform_stripped_oid('.1.3.6.1.4.1.9.9.656.1.4.1.1.2', $device);
		mactrack_debug('cafSessionClientMacAddress data collection complete: ' . sizeof($cafSessionClientMacAddress));
		$cafSessionClientAddress  = xform_stripped_oid('.1.3.6.1.4.1.9.9.656.1.4.1.1.4', $device);
		mactrack_debug('cafSessionClientAddress data collection complete: ' . sizeof($cafSessionClientAddress));
		$cafSessionDomain  = xform_stripped_oid('.1.3.6.1.4.1.9.9.656.1.4.1.1.6', $device);
		mactrack_debug('cafSessionDomain data collection complete: ' . sizeof($cafSessionDomain));
		$cafSessionStatus  = xform_stripped_oid('.1.3.6.1.4.1.9.9.656.1.4.1.1.5', $device);
		mactrack_debug('cafSessionStatus data collection complete: ' . sizeof($cafSessionStatus));
	} else {
		mactrack_debug(sprintf('The Device: %s does not support dot1x', $device['hostname']));
		return false;
	}

	$ifIndex = array();
	$entries = array();
	$cafSessionAuthUserNames = array();
	$cafSessionAuthUserKey = array_keys($cafSessionAuthUserName); //Getting the keys to explode the first part which is the ifIndex

	/* This is to take the ifIndex from the OID */
	$i = 0;
	if (cacti_sizeof($cafSessionAuthUserName)) {
		foreach ($cafSessionAuthUserName as $keyName) {
			$parts = explode('.', trim($keyName, '.'));
			$ifIndexes[$i] = $parts[0];
			$i++;
		}
	}

	mactrack_debug('ifIndexes assembly complete: ' . sizeof($ifIndexes));

	$i = 0;
	if (cacti_sizeof($cafSessionAuthUserName)) {
		foreach ($cafSessionAuthUserName as $index) {
			$entries[$i]['Dot1xIndex']                 = $index;
			$entries[$i]['cafSessionClientMacAddress'] = isset($cafSessionClientMacAddress[$index]) ? xform_mac_address($cafSessionClientMacAddress[$index]):'';
			$entries[$i]['cafSessionClientAddress']    = isset($cafSessionClientAddress[$index]) ? xform_net_address($cafSessionClientAddress[$index]):'';
			$entries[$i]['cafSessionDomain']           = isset($cafSessionDomain[$index]) ? $cafSessionDomain[$index]:'';
			$entries[$i]['cafSessionStatus']           = isset($cafSessionStatus[$index]) ? $cafSessionStatus[$index]:'';
			$entries[$i]['port_number']                = isset($ifIndexes[$i]) ? $ifIndexes[$i]:'';
			$i++;
		}
	}
	mactrack_debug('entries assembly complete.');

	/* output details to database */
	$sql = array();
	$prefix = 'REPLACE INTO mac_track_dot1x
		(site_id, device_id, hostname, device_name, username, mac_address, ip_address, domain, status, port_number, scan_date)
		VALUES ';

	if (cacti_sizeof($entries)) {
		foreach ($entries as $entry) {
			if ($entry['Dot1xIndex'] != '') {
				$sql[] = '(' . $device['site_id'] . ',' .
					$device['device_id'] . ',' .
					db_qstr($device['hostname']) . ',' .
					db_qstr($device['device_name']) . ',' .
					db_qstr($entry['Dot1xIndex']) . ',' .
					db_qstr($entry['cafSessionClientMacAddress']) . ',' .
					db_qstr($entry['cafSessionClientAddress']) . ',' .
					db_qstr($entry['cafSessionDomain']) . ',' .
					db_qstr($entry['cafSessionStatus']) . ',' .
					db_qstr($entry['port_number']) . ',' .
					db_qstr($scan_date) . ')';
			}
		}

		if (cacti_sizeof($sql)) {
			db_execute($prefix . implode(', ', $sql));
		}
	}
}

