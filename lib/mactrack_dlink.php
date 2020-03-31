<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2019 The Cacti Group                                 |
 | Copyright (C) 2009 Susanin (gthe)                                       |
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
array_push($mactrack_scanning_functions, 'get_dlink_l2_switch_ports');
if (!isset($mactrack_scanning_functions)) { $mactrack_scanning_functions = array(); }
array_push($mactrack_scanning_functions, 'get_dlink_l2_dot1dTpFdbEntry_ports');

/*	get_generic_switch_ports - This is a basic function that will scan the dot1d
  OID tree for all switch port to MAC address association and stores in the
  mac_track_temp_ports table for future processing in the finalization steps of the
  scanning process.
*/
function get_dlink_l2_switch_ports($site, &$device, $lowPort = 0, $highPort = 0) {
	global $debug, $scan_date;

	/* initialize port counters */
	$device['ports_total'] = 0;
	$device['ports_active'] = 0;
	$device['ports_trunk'] = 0;

	/* get the ifIndexes for the device */
	$ifIndexes = xform_standard_indexed_data('.1.3.6.1.2.1.2.2.1.1', $device);
	mactrack_debug('ifIndexes data collection complete');

	/* get the ifTypes for the device */
	$ifTypes = xform_standard_indexed_data('.1.3.6.1.2.1.2.2.1.3', $device);
	mactrack_debug('ifTypes data collection complete.');

	/* get the ifNames for the device */
	$ifNames = xform_standard_indexed_data('.1.3.6.1.2.1.31.1.1.1.1', $device);
	mactrack_debug('ifNames data collection complete.');

	/* get ports that happen to be link ports */
	$link_ports = get_link_port_status($device);
	mactrack_debug('ipAddrTable scanning for Dlink ports data collection complete.');

	if (cacti_sizeof($ifIndexes)) {
		foreach ($ifIndexes as $ifIndex) {
			$ifInterfaces[$ifIndex]['ifIndex'] = $ifIndex;
			$ifInterfaces[$ifIndex]['ifName'] = @$ifNames[$ifIndex];
			$ifInterfaces[$ifIndex]['ifType'] = $ifTypes[$ifIndex];
			$ifInterfaces[$ifIndex]['linkPort'] = @$link_ports[$ifIndex];
		}
	}
	mactrack_debug('ifInterfaces assembly complete.');

	get_dlink_l2_dot1dTpFdbEntry_ports($site, $device, $ifInterfaces, '', true, $lowPort, $highPort);

	return $device;
	mactrack_debug('Finish function get_dlink_l2_switch_ports for dev=: ' . ' dev=' . $device['hostname'] );
}


/*	get_base_dot1dTpFdbEntry_ports - This function will grab information from the
  port bridge snmp table and return it to the calling progrem for further processing.
  This is a foundational function for all vendor data collection functions.
*/
function get_dlink_l2_dot1dTpFdbEntry_ports($site, &$device, &$ifInterfaces, $snmp_readstring = '', $store_to_db = true, $lowPort = 1, $highPort = 9999) {
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

	/* if one is not defined, use the default */
	if ($snmp_readstring == '') {
		$snmp_readstring = $device['snmp_readstring'];
	}

	/* get the operational status of the ports */
	$active_ports_array = xform_standard_indexed_data('.1.3.6.1.2.1.2.2.1.8', $device);
	$indexes = array_keys($active_ports_array);

	$i = 0;
	if (cacti_sizeof($active_ports_array)) {
		foreach ($active_ports_array as $port_info) {
			if (((convert_dlink_data($ifInterfaces[$indexes[$i]]['ifType']) >= 6) &&
				(convert_dlink_data($ifInterfaces[$indexes[$i]]['ifType']) <= 9)) ||
		  (convert_dlink_data($ifInterfaces[$indexes[$i]]['ifType']) == 117)) {
				if (convert_dlink_data($port_info) == 1) {
					$ports_active++;
				}
				$ports_total++;
			}
			$i++;
		}
	}

	if ($store_to_db) {
		print('\nINFO: HOST: ' . $device['hostname'] . ', TYPE: ' . substr($device['snmp_sysDescr'],0,40) . ', TOTAL PORTS: ' . $ports_total . ', OPER PORTS: ' . $ports_active);
		if ($debug) {
			print('\n');
		}

		$device['ports_active'] = $ports_active;
		$device['ports_total'] = $ports_total;
		$device['macs_active'] = 0;
	}

	if ($ports_active > 0) {
		/* get bridge port to ifIndex mapping */
		$bridgePortIfIndexes = xform_standard_indexed_data('.1.3.6.1.2.1.17.1.4.1.2', $device, $snmp_readstring);

		$vlan_names = xform_standard_indexed_data('.1.3.6.1.2.1.17.7.1.4.3.1.1', $device, $snmp_readstring);

		$port_status = xform_stripped_oid('1.3.6.1.2.1.17.7.1.2.2.1.3', $device, $snmp_readstring);

		/* get device active port numbers */
		$port_numbers = xform_stripped_oid('.1.3.6.1.2.1.17.7.1.2.2.1.2', $device, $snmp_readstring);

    /* get device active port numbers */
		//$vlan_id = get_vlan_id_oid($port_numbers);


    /* get device active port numbers */
		//$vlan_ids = xform_dlink_vlan_associations($device, $snmp_readstring);

		/* get the ignore ports list from device */
		$ignore_ports = port_list_to_array($device['ignorePorts']);

		/* determine user ports for this device and transfer user ports to
		   a new array.
		*/
		$i = 0;

		if (cacti_sizeof($port_numbers) > 0) {
			foreach ($port_numbers as $key => $port_number) {
				if (($highPort == 0) ||
					(($port_number >= $lowPort) &&
					($port_number <= $highPort))) {

					if (!in_array($port_number, $ignore_ports)) {
						if ((@$port_status[$key] == '3') || (@$port_status[$key] == '1'))  {
							$port_key_array[$i]['key'] = $key;
							$port_key_array[$i]['port_number'] = $port_number;
							$i++;
						}
					}
				}
			}
		}

    $i = 0;


		/* compare the user ports to the brige port data, store additional
		   relevant data about the port.
		*/

		$ifNames = xform_standard_indexed_data('.1.3.6.1.2.1.31.1.1.1.18', $device);

		$i = 0;
		if (cacti_sizeof($port_key_array) > 0) {
			foreach ($port_key_array as $port_key) {
				/* map bridge port to interface port and check type */
				if ($port_key['port_number'] >= 0) {
					if (cacti_sizeof($bridgePortIfIndexes) != 0) {
						/* some hubs do not always return a port number in the bridge table.
						   test for it by isset and substiture the port number from the ifTable
						   if it isnt in the bridge table
						*/
						if (isset($bridgePortIfIndexes[$port_key['port_number']])) {
							$brPortIfIndex = @$bridgePortIfIndexes[$port_key['port_number']];
						} else {
							$brPortIfIndex = @$port_key['port_number'];
						}
						$brPortIfType = @$ifInterfaces[$brPortIfIndex]['ifType'];
					} else {
						$brPortIfIndex = $port_key['port_number'];
						$brPortIfType = @$ifInterfaces[$port_key['port_number']]['ifType'];
					}

					if ((((convert_dlink_data($brPortIfType) >= 6) &&
						(convert_dlink_data($brPortIfType) <= 9)) || (convert_dlink_data($brPortIfType) == 117)) &&
						(!isset($ifInterfaces[$brPortIfIndex]['portLink']))) {
						/* set some defaults  */
						$new_port_key_array[$i]['vlan_id'] = get_dlink_vlan_id($port_key['key']);
						$new_port_key_array[$i]['vlan_name'] = $vlan_names[$new_port_key_array[$i]['vlan_id']];
						$new_port_key_array[$i]['mac_address'] = dlink_convert_macs($port_key['key']);
						$new_port_key_array[$i]['port_number'] = $port_key['port_number'];
						$new_port_key_array[$i]['port_name'] = @$ifNames[$port_key['port_number']];
						/* now set the real data */
						$new_port_key_array[$i]['key'] = $port_key['key'];
						$i++;
					}
				}
			}
		}
		mactrack_debug('Port number information collected.');

	} else {
		print('\nINFO: HOST: ' . $device['hostname'] . ', TYPE: ' . substr($device['snmp_sysDescr'],0,40) . ', No active devcies on this network device.\n');

		$device['snmp_status'] = HOST_UP;
		$device['last_runmessage'] = 'Data collection completed ok. No active devices on this network device.';
	}

	if ($store_to_db) {
		if ($ports_active <= 0) {
			$device['last_runmessage'] = 'WARNING: Poller did not find active ports on this device.';
		} elseif (cacti_sizeof($new_port_key_array) > 0) {
			$device['last_runmessage'] = 'Data collection completed ok';
			$device['macs_active'] = sizeof($new_port_key_array);
			db_store_device_port_results($device, $new_port_key_array, $scan_date);
		} else {
			$device['last_runmessage'] = 'WARNING: Poller did not find active ports on this device.';
		}
		if (!$debug) {
			print(' - Complete\n');
		}
	} else {
		return $new_port_key_array;
	}
}

function dlink_convert_macs ($oldmac){
	if ($oldmac{0} != '.') {
		$oldmac = '.' . $oldmac;
	}
	$oldmac=substr($oldmac,stripos($oldmac,'.')+1);
	$oldmac=substr($oldmac,stripos($oldmac,'.')+1);
	$piece = explode('.', $oldmac);
	$newmac = '';
	for ($i = 0; $i < 6; $i++){
		$newmac = $newmac . dec2hex($piece[$i],2) . ':';
	}
	$newmac = substr($newmac,0,strlen($newmac)-1);

	return $newmac;

	}

function dec2hex($number, $length) {
	$hexval='';
	while ($number>0) {
		$remainder=$number%16;
		if ($remainder<10)
			$hexval=$remainder.$hexval;
		elseif ($remainder==10)
			$hexval='a'.$hexval;
		elseif ($remainder==11)
			$hexval='b'.$hexval;
		elseif ($remainder==12)
			$hexval='c'.$hexval;
		elseif ($remainder==13)
			$hexval='d'.$hexval;
		elseif ($remainder==14)
			$hexval='e'.$hexval;
		elseif ($remainder==15)
			$hexval='f'.$hexval;
		$number=floor($number/16);
	}
	while (strlen($hexval)<$length) $hexval='0'.$hexval;
//this is just to add zero's at the beginning to make hexval a certain length
	return $hexval;
}

if (!function_exists('stripos')) {
	function stripos($str,$needle) {
		return strpos(strtolower($str),strtolower($needle));
	}
}


function xform_dlink_vlan_associations(&$device, $snmp_readstring = '') {
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
	foreach ($xformArray as $xformItem) {
		/* peel off the beginning of the OID */
		$key = $xformItem['oid'];
		$key = str_replace('iso', '1', $key);
		$key = str_replace('1.3.6.1.2.1.17.7.1.2.2.1.2.', '', $key);
		/* now grab the VLAN */
		$perPos = strpos($key, '.',1);
		$output_array[$i]['vlan_id'] = substr($key,1,$perPos-1);
		/* save the key for association with the dot1d table */
		$output_array[$i]['key'] = substr($key, $perPos+1);
		$vlan_name = @cacti_snmp_get($device['hostname'], $snmp_readstring,
			'.1.3.6.1.2.1.17.7.1.4.3.1.1.' . $output_array[$i]['vlan_id'], $device['snmp_version'],
			$device['snmp_username'], $device['snmp_password'], $device['snmp_auth_protocol'],
			$device['snmp_priv_passphrase'], $device['snmp_priv_protocol'], $device['snmp_context'],
			$device['snmp_port'], $device['snmp_timeout'], $device['snmp_retries']);

		$output_array[$i]['vlan_name'] = $vlan_name;

		$i++;
	}

	//return array_rekey($output_array, 'key', 'vlan_name');
	return $output_array;
}

function get_dlink_vlan_id($OID) {
		if ($OID{0} != '.') {
		$OID = '.' . $OID;
		}
		$perPos = strpos($OID, '.',1);
		$vlan_id = substr($OID,1,$perPos-1);
	return $vlan_id;
}
function convert_dlink_data($old_port_type) {
		if (substr_count($old_port_type, '(') > 0) {
			$pos1 = strpos($old_port_type, '(');
			$pos2 = strpos($old_port_type, ')');
			$rezult = substr($old_port_type, $pos1+1, $pos2-$pos1-1);
		} else{
			$rezult=$old_port_type;
		}

return $rezult;
}
?>
