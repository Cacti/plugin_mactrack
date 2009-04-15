<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2009 The Cacti Group                                 |
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

/*	valid_snmp_device - This function validates that the device is reachable via snmp.
  It first attempts	to utilize the default snmp readstring.  If it's not valid, it
  attempts to find the correct read string and then updates several system
  information variable. it returns the status	of the host (up=true, down=false)
*/

/* register this functions scanning functions */
if (!isset($mactrack_scanning_functions)) { $mactrack_scanning_functions = array(); }
array_push($mactrack_scanning_functions, "get_generic_dot1q_switch_ports", "get_generic_switch_ports", "get_generic_wireless_ports");

if (!isset($mactrack_scanning_functions_ip)) { $mactrack_scanning_functions_ip = array(); }
array_push($mactrack_scanning_functions_ip, "get_standard_arp_table", "get_netscreen_arp_table");

function mactrack_debug($message) {
	global $debug;

	if ($debug) {
		print("DEBUG: " . $message . "\n");
	}

	if (substr_count($message, "ERROR:")) {
		cacti_log($message, false, "MACTRACK");
	}
}

function mactrack_strip_alpha($string = "") {
	return trim($string, "abcdefghijklmnopqrstuvwzyzABCDEFGHIJKLMNOPQRSTUVWXYZ()[]{}");
}

function mactrack_check_user_realm($realm_id) {
	if (empty($_SESSION["sess_user_id"])) {
		return FALSE;
	}elseif (!empty($_SESSION["sess_user_id"])) {
		if ((!db_fetch_assoc("select
			user_auth_realm.realm_id
			from
			user_auth_realm
			where user_auth_realm.user_id='" . $_SESSION["sess_user_id"] . "'
			and user_auth_realm.realm_id='$realm_id'")) || (empty($realm_id))) {
			return FALSE;
		}else{
			return TRUE;
		}
	}
}

function valid_snmp_device(&$device) {
	/* initialize variable */
	$host_up = FALSE;
	$device["snmp_status"] = HOST_DOWN;

	/* force php to return numeric oid's */
	if (function_exists("snmp_set_oid_numeric_print")) {
		snmp_set_oid_numeric_print(TRUE);
	}

	/* if the first read did not work, loop until found */
	$snmp_sysObjectID = @cacti_snmp_get($device["hostname"], $device["snmp_readstring"],
					".1.3.6.1.2.1.1.2.0", $device["snmp_version"],
					"", "", "", "", "", "", $device["snmp_port"], $device["snmp_timeout"]);

	$snmp_sysObjectID = str_replace("enterprises", ".1.3.6.1.4.1", $snmp_sysObjectID);
	$snmp_sysObjectID = str_replace("OID: ", "", $snmp_sysObjectID);
	$snmp_sysObjectID = str_replace(".iso", ".1", $snmp_sysObjectID);

	if ((strlen($snmp_sysObjectID) > 0) &&
		(!substr_count($snmp_sysObjectID, "No Such Object")) &&
		(!substr_count($snmp_sysObjectID, "Error In"))) {
		$snmp_sysObjectID = trim(str_replace("\"","", $snmp_sysObjectID));
		$host_up = TRUE;
		$device["snmp_status"] = HOST_UP;
	}else{
		/* loop through the default and then other common for the correct answer */
		$read_strings = explode(":",$device["snmp_readstrings"]);

		if (sizeof($read_strings)) {
		foreach($read_strings as $snmp_readstring) {
			if ($snmp_readstring != $device["snmp_readstring"]) {
				$snmp_sysObjectID = @cacti_snmp_get($device["hostname"], $snmp_readstring,
						".1.3.6.1.2.1.1.2.0", $device["snmp_version"],
						"", "", "", "", "", "", $device["snmp_port"], $device["snmp_timeout"]);

				$snmp_sysObjectID = str_replace("enterprises", ".1.3.6.1.4.1", $snmp_sysObjectID);
				$snmp_sysObjectID = str_replace("OID: ", "", $snmp_sysObjectID);
				$snmp_sysObjectID = str_replace(".iso", ".1", $snmp_sysObjectID);

				if ((strlen($snmp_sysObjectID) > 0) &&
					(!substr_count($snmp_sysObjectID, "No Such Object")) &&
					(!substr_count($snmp_sysObjectID, "Error In"))) {
					$snmp_sysObjectID = trim(str_replace("\"", "", $snmp_sysObjectID));
					$device["snmp_readstring"] = $snmp_readstring;
					$device["snmp_status"] = HOST_UP;
					$host_up = TRUE;
					break;
				}else{
					$device["snmp_status"] = HOST_DOWN;
					$host_up = FALSE;
				}
			}
		}
		}
	}

	if ($host_up) {
		$device["snmp_sysObjectID"] = $snmp_sysObjectID;

		/* get system name */
		$snmp_sysName = @cacti_snmp_get($device["hostname"], $device["snmp_readstring"],
					".1.3.6.1.2.1.1.5.0", $device["snmp_version"],
					"", "", "", "", "", "", $device["snmp_port"], $device["snmp_timeout"]);

		if (strlen($snmp_sysName) > 0) {
			$snmp_sysName = trim(strtr($snmp_sysName,"\""," "));
			$device["snmp_sysName"] = $snmp_sysName;
		}

		/* get system location */
		$snmp_sysLocation = @cacti_snmp_get($device["hostname"], $device["snmp_readstring"],
					".1.3.6.1.2.1.1.6.0", $device["snmp_version"],
					"", "", "", "", "", "", $device["snmp_port"], $device["snmp_timeout"]);

		if (strlen($snmp_sysLocation) > 0) {
			$snmp_sysLocation = trim(strtr($snmp_sysLocation,"\""," "));
			$device["snmp_sysLocation"] = $snmp_sysLocation;
		}

		/* get system contact */
		$snmp_sysContact = @cacti_snmp_get($device["hostname"], $device["snmp_readstring"],
					".1.3.6.1.2.1.1.4.0", $device["snmp_version"],
					"", "", "", "", "", "", $device["snmp_port"], $device["snmp_timeout"]);

		if (strlen($snmp_sysContact) > 0) {
			$snmp_sysContact = trim(strtr($snmp_sysContact,"\""," "));
			$device["snmp_sysContact"] = $snmp_sysContact;
		}

		/* get system description */
		$snmp_sysDescr = @cacti_snmp_get($device["hostname"], $device["snmp_readstring"],
					".1.3.6.1.2.1.1.1.0", $device["snmp_version"],
					"", "", "", "", "", "", $device["snmp_port"], $device["snmp_timeout"]);

		if (strlen($snmp_sysDescr) > 0) {
			$snmp_sysDescr = trim(strtr($snmp_sysDescr,"\""," "));
			$device["snmp_sysDescr"] = $snmp_sysDescr;
		}

		/* get system uptime */
		$snmp_sysUptime = @cacti_snmp_get($device["hostname"], $device["snmp_readstring"],
					".1.3.6.1.2.1.1.3.0", $device["snmp_version"],
					"", "", "", "", "", "", $device["snmp_port"], $device["snmp_timeout"]);

		if (strlen($snmp_sysUptime) > 0) {
			$snmp_sysUptime = trim(strtr($snmp_sysUptime,"\""," "));
			$device["snmp_sysUptime"] = $snmp_sysUptime;
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
	if (sizeof($device_types)) {
	foreach($device_types as $device_type) {
		/* by default none match */
		$sysDescr_match = FALSE;
		$sysObjectID_match = FALSE;

		/* search for a matching snmp_sysDescr */
		if (substr_count($device_type["sysDescr_match"], "*") > 0) {
			/* need to assume mixed string */
			$parts = explode("*", $device_type["sysDescr_match"]);
			if (sizeof($parts)) {
			foreach($parts as $part) {
				if (substr_count($device["sysDescr_match"],$part) > 0) {
					$sysDescr_match = TRUE;
				}else{
					$sysDescr_match = FALSE;
				}
			}
			}
		}else{
			if (strlen($device_type["sysDescr_match"]) == 0) {
				$sysDescr_match = TRUE;
			}else{
				if (substr_count($device["snmp_sysDescr"], $device_type["sysDescr_match"])) {
					$sysDescr_match = TRUE;
				}else{
					$sysDescr_match = FALSE;
				}
			}
		}

		/* search for a matching snmp_sysObjectID */
		$len = strlen($device_type["sysObjectID_match"]);
		if (substr($device["snmp_sysObjectID"],0,$len) == $device_type["sysObjectID_match"]) {
			$sysObjectID_match = TRUE;
		}

		if (($sysObjectID_match == TRUE) && ($sysDescr_match == TRUE)) {
			$device["device_type_id"] = $device_type["device_type_id"];
			$device["scan_type"] = $device_type["device_type"];
			return $device_type;
		}
	}
	}

	return array();
}

/*	port_list_to_array - Takes a text list of ports and builds a trimmed array of
  the resulting array.  Returns the array
*/
function port_list_to_array($port_list, $delimiter = ":") {
	$port_array = array();

	$ports = explode($delimiter, $port_list);

	if (sizeof($ports)) {
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

	/* get the atifIndexes for the device */
	$atifIndexes = xform_indexed_data(".1.3.6.1.2.1.3.1.1.1", $device, 6);

	if (sizeof($atifIndexes)) {
		mactrack_debug("atifIndexes data collection complete");
		$atPhysAddress = xform_indexed_data(".1.3.6.1.2.1.3.1.1.2", $device, 6);
		mactrack_debug("atPhysAddress data collection complete");
		$atNetAddress  = xform_indexed_data(".1.3.6.1.2.1.3.1.1.3", $device, 6);
		mactrack_debug("atNetAddress data collection complete");
	}else{
		/* second attempt for Force10 Gear */
		$atifIndexes   = xform_indexed_data(".1.3.6.1.2.1.4.22.1.1", $device, 5);
		mactrack_debug("atifIndexes data collection complete");
		$atPhysAddress = xform_indexed_data(".1.3.6.1.2.1.4.22.1.2", $device, 5);
		mactrack_debug("atPhysAddress data collection complete");
		$atNetAddress = xform_indexed_data(".1.3.6.1.2.1.4.22.1.3", $device, 5);
		mactrack_debug("atNetAddress data collection complete");
	}

	/* convert the mac address if necessary */
	$keys = array_keys($atPhysAddress);
	$i = 0;
	if (sizeof($atPhysAddress)) {
	foreach($atPhysAddress as $atAddress) {
		$atPhysAddress[$keys[$i]] = xform_mac_address($atAddress);
		$i++;
	}
	}
	mactrack_debug("atPhysAddress MAC Address Conversion Completed");

	/* get the ifNames for the device */
	$keys = array_keys($atifIndexes);
	$i = 0;
	if (sizeof($atifIndexes)) {
	foreach($atifIndexes as $atifIndex) {
		$atEntries[$i]["atifIndex"] = $atifIndex;
		$atEntries[$i]["atPhysAddress"] = $atPhysAddress[$keys[$i]];
		$atEntries[$i]["atNetAddress"] = xform_net_address($atNetAddress[$keys[$i]]);
		$i++;
	}
	}
	mactrack_debug("atEntries assembly complete.");

	/* output details to database */
	if (sizeof($atEntries)) {
	foreach($atEntries as $atEntry) {
		$insert_string = "REPLACE INTO mac_track_ips " .
			"(site_id,device_id,hostname,device_name,port_number," .
			"mac_address,ip_address,scan_date)" .
			" VALUES ('" .
			$device["site_id"] . "','" .
			$device["device_id"] . "','" .
			$device["hostname"] . "','" .
			$device["device_name"] . "','" .
			$atEntry["atifIndex"] . "','" .
			$atEntry["atPhysAddress"] . "','" .
			$atEntry["atNetAddress"] . "','" .
			$scan_date . "')";

		mactrack_debug("SQL: " . $insert_string);

		db_execute($insert_string);
	}
	}

	/* save ip information for the device */
	$device["ips_total"] = sizeof($atEntries);
	db_execute("UPDATE mac_track_devices SET ips_total ='" . $device["ips_total"] . "' WHERE device_id='" . $device["device_id"] . "'");

	mactrack_debug("HOST: " . $device["hostname"] . ", IP address information collection complete");
}

/*	build_InterfacesTable - This is a basic function that will scan Interfaces table
  and return data.  It also stores data in the mac_track_interfaces table.  Some of the
  data is also used for scanning purposes.
*/
function build_InterfacesTable(&$device, &$ifIndexes, $getLinkPorts = FALSE, $getAlias = FALSE) {
	global $cnn_id;

	/* initialize the interfaces array */
	$ifInterfaces = array();

	$ifTypes = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.3", $device);
	if (sizeof($ifTypes)) {
	foreach($ifTypes as $key => $value) {
		if (!is_numeric($value)) {
			$parts = explode("(", $value);
			$piece = $parts[1];
			$ifTypes[$key] = str_replace(")", "", trim($piece));
		}
	}
	}
	mactrack_debug("ifTypes data collection complete. '" . sizeof($ifTypes) . "' rows found!");

	$ifNames = xform_standard_indexed_data(".1.3.6.1.2.1.31.1.1.1.1", $device);
	mactrack_debug("ifNames data collection complete. '" . sizeof($ifNames) . "' rows found!");

	/* get ports names through use of ifAlias */
	if ($getAlias) {
		$ifAliases = xform_standard_indexed_data(".1.3.6.1.2.1.31.1.1.1.18", $device);
		mactrack_debug("ifAlias data collection complete. '" . sizeof($ifAliases) . "' rows found!");
	}

	/* get ports that happen to be link ports */
	if ($getLinkPorts) {
		$link_ports = get_link_port_status($device);
		mactrack_debug("ipAddrTable scanning for link ports data collection complete. '" . sizeof($link_ports) . "' rows found!");
	}

	/* required only for interfaces table */
	$db_data = db_fetch_assoc("SELECT * FROM mac_track_interfaces WHERE device_id='" . $device["device_id"] . "' ORDER BY ifIndex");

	if (sizeof($db_data)) {
		foreach($db_data as $interface) {
			$db_interface[$interface["ifIndex"]] = $interface;
		}
	}

	$insert_prefix = "INSERT INTO mac_track_interfaces (site_id, device_id, ifIndex, ifType, ifName, ifAlias, linkPort, vlan_id," .
		" vlan_name, vlan_trunk, ifSpeed, ifHighSpeed, ifDuplex, " .
		" ifDescr, ifMtu, ifPhysAddress, ifAdminStatus, ifOperStatus, ifLastChange, ifInDiscards, ifInErrors, ifInUnknownProtos," .
		" ifOutDiscards, ifOutErrors, int_ifInDiscards, int_ifInErrors, int_ifInUnknownProtos, int_ifOutDiscards, int_ifOutErrors," .
		" int_discards_present, int_errors_present, last_down_time, last_up_time, stateChanges, present) VALUES ";

	$insert_suffix = " ON DUPLICATE KEY UPDATE ifType=VALUES(ifType), ifName=VALUES(ifName), ifAlias=VALUES(ifAlias), linkPort=VALUES(linkPort)," .
		" vlan_id=VALUES(vlan_id), vlan_name=VALUES(vlan_name), vlan_trunk=VALUES(vlan_trunk)," .
		" ifSpeed=VALUES(ifSpeed), ifHighSpeed=VALUES(ifHighSpeed), ifDuplex=VALUES(ifDuplex), ifDescr=VALUES(ifDescr), ifMtu=VALUES(ifMtu), ifPhysAddress=VALUES(ifPhysAddress), ifAdminStatus=VALUES(ifAdminStatus)," .
		" ifOperStatus=VALUES(ifOperStatus), ifLastChange=VALUES(ifLastChange), ifInDiscards=VALUES(ifInDiscards), ifInErrors=VALUES(ifInErrors)," .
		" ifInUnknownProtos=VALUES(ifInUnknownProtos), ifOutDiscards=VALUES(ifOutDiscards), ifOutErrors=VALUES(ifOutErrors)," .
		" int_ifInDiscards=VALUES(int_ifInDiscards), int_ifInErrors=VALUES(int_ifInErrors)," .
		" int_ifInUnknownProtos=VALUES(int_ifInUnknownProtos), int_ifOutDiscards=VALUES(int_ifOutDiscards)," .
		" int_ifOutErrors=VALUES(int_ifOutErrors), " .
		" int_discards_present=VALUES(int_discards_present), int_errors_present=VALUES(int_errors_present)," .
		" last_down_time=VALUES(last_down_time), last_up_time=VALUES(last_up_time)," .
		" stateChanges=VALUES(stateChanges), present='1'";

	$insert_vals = "";

	$ifSpeed = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.5", $device);
	mactrack_debug("ifSpeed data collection complete. '" . sizeof($ifSpeed) . "' rows found!");

	$ifHighSpeed = xform_standard_indexed_data(".1.3.6.1.2.1.31.1.1.1.15", $device);
	mactrack_debug("ifHighSpeed data collection complete. '" . sizeof($ifHighSpeed) . "' rows found!");

	$ifDescr = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.2", $device);
	mactrack_debug("ifDescr data collection complete. '" . sizeof($ifDescr) . "' rows found!");

	$ifMtu = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.4", $device);
	mactrack_debug("ifMtu data collection complete. '" . sizeof($ifMtu) . "' rows found!");

	$ifPhysAddress = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.6", $device);
	mactrack_debug("ifPhysAddress data collection complete. '" . sizeof($ifPhysAddress) . "' rows found!");

	$ifAdminStatus = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.7", $device);
	if (sizeof($ifAdminStatus)) {
	foreach($ifAdminStatus as $key => $value) {
		if (substr_count($value, "up")) {
			$ifAdminStatus[$key] = 1;
		}else{
			$ifAdminStatus[$key] = 0;
		}
	}
	}
	mactrack_debug("ifAdminStatus data collection complete. '" . sizeof($ifAdminStatus) . "' rows found!");

	$ifOperStatus = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.8", $device);
	if (sizeof($ifOperStatus)) {
	foreach($ifOperStatus as $key=>$value) {
		if (substr_count($value, "up")) {
			$ifOperStatus[$key] = 1;
		}else{
			$ifOperStatus[$key] = 0;
		}
	}
	}
	mactrack_debug("ifOperStatus data collection complete. '" . sizeof($ifOperStatus) . "' rows found!");

	$ifDuplex = xform_standard_indexed_data("	.1.3.6.1.2.1.10.7.2.19", $device);
	mactrack_debug("ifDuplex data collection complete. '" . sizeof($ifDuplex) . "' rows found!");

	$ifLastChange = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.9", $device);
	mactrack_debug("ifLastChange data collection complete. '" . sizeof($ifLastChange) . "' rows found!");

	$ifInDiscards = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.13", $device);
	mactrack_debug("ifInDiscards data collection complete. '" . sizeof($ifInDiscards) . "' rows found!");

	$ifInErrors = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.14", $device);
	mactrack_debug("ifInErrors data collection complete. '" . sizeof($ifInErrors) . "' rows found!");

	$ifInUnknownProtos = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.15", $device);
	mactrack_debug("ifInUnknownProtos data collection complete. '" . sizeof($ifInUnknownProtos) . "' rows found!");

	$ifOutDiscards = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.19", $device);
	mactrack_debug("ifOutDiscards data collection complete. '" . sizeof($ifOutDiscards) . "' rows found!");

	$ifOutErrors = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.20", $device);
	mactrack_debug("ifOutErrors data collection complete. '" . sizeof($ifOutErrors) . "' rows found!");

	$vlan_id = "";
	$vlan_name = "";
	$vlan_trunk = "";

	$i = 0;
	if (sizeof($ifIndexes)) {
	foreach($ifIndexes as $ifIndex) {
		$ifInterfaces[$ifIndex]["ifIndex"] = $ifIndex;
		$ifInterfaces[$ifIndex]["ifName"] = @$ifNames[$ifIndex];
		$ifInterfaces[$ifIndex]["ifType"] = @$ifTypes[$ifIndex];
		$ifInterfaces[$ifIndex]["ifDescr"] = @$ifDescr[$ifIndex];
		$ifInterfaces[$ifIndex]["ifOperStatus"] = @$ifOperStatus[$ifIndex];

		if ($getLinkPorts) {
			$ifInterfaces[$ifIndex]["linkPort"] = @$link_ports[$ifIndex];
			$linkPort = @$link_ports[$ifIndex];
		}else{
			$linkPort = 0;
		}

		if (($getAlias) && (sizeof($ifAliases))) {
			$ifInterfaces[$ifIndex]["ifAlias"] = @$ifAliases[$ifIndex];
			$ifAlias = @$ifAliases[$ifIndex];
		}else{
			$ifAlias = "";
		}

		/* update the last up/down status */
		if (!isset($db_interface[$ifIndex]["ifOperStatus"])) {
			if ($ifOperStatus[$ifIndex] == 1) {
				$last_up_time = date("Y-m-d H:i:s");
				$stateChanges = 0;
				$last_down_time = 0;
			}else{
				$stateChanges = 0;
				$last_up_time   = 0;
				$last_down_time = date("Y-m-d H:i:s");
			}
		}else{
			$last_up_time   = $db_interface[$ifIndex]["last_up_time"];
			$last_down_time = $db_interface[$ifIndex]["last_down_time"];
			$stateChanges   = $db_interface[$ifIndex]["stateChanges"];

			if ($db_interface[$ifIndex]["ifOperStatus"] > 1) { /* interface not up */
				if ($ifOperStatus[$ifIndex] == 1) {
					$last_up_time = date("Y-m-d H:i:s");
					$stateChanges += 1;
				}else{
					$last_up_time = @$db_interface[$ifIndex]["last_up_time"];
				}
			}else{
				if ($ifOperStatus[$ifIndex] > 1) {
					$last_down_time = date("Y-m-d H:i:s");
					$stateChanges += 1;
				}else{
					$last_down_time = @$db_interface["ifIndex"]["last_up_time"];
				}
			}
		}

		/* 32bit Integer Overflow Value */
		$overflow = 4294967295;
		/* fudge factor */
		$fudge    = 3000000001;

		/* see if in error's have been increasing */
		$int_ifInErrors = 0;
		if (!isset($db_interface[$ifIndex]["ifInErrors"])) {
			$int_ifInErrors = 0;
		}else if (!isset($ifInErrors[$ifIndex])) {
			$int_ifInErrors = 0;
		}else if ($ifInErrors[$ifIndex] <> $db_interface[$ifIndex]["ifInErrors"]) {
			/* account for 2E32 rollover */
			/* there are two types of rollovers one rolls to 0 */
			/* the other counts backwards.  let's make an educated guess */
			if ($db_interface[$ifIndex]["ifInErrors"] > $ifInErrors[$ifIndex]) {
				if (($overflow - $db_interface[$ifIndex]["ifInErrors"] + $ifInErrors[$ifIndex]) < $fudge) {
					$int_ifInErrors = $overflow - $db_interface[$ifIndex]["ifInErrors"] + $ifInErrors[$ifIndex];
				}else{
					$int_ifInErrors = $db_interface[$ifIndex]["ifInErrors"] - $ifInErrors[$ifIndex];
				}
			}else{
				$int_ifInErrors = $ifInErrors[$ifIndex] - $db_interface[$ifIndex]["ifInErrors"];
			}
		}else{
			$int_ifInErrors = 0;
		}

		/* see if out error's have been increasing */
		$int_ifOutErrors = 0;
		if (!isset($db_interface[$ifIndex]["ifOutErrors"])) {
			$int_ifOutErrors = 0;
		}else if (!isset($ifOutErrors[$ifIndex])) {
			$int_ifOutErrors = 0;
		}else if ($ifOutErrors[$ifIndex] <> $db_interface[$ifIndex]["ifOutErrors"]) {
			/* account for 2E32 rollover */
			/* there are two types of rollovers one rolls to 0 */
			/* the other counts backwards.  let's make an educated guess */
			if ($db_interface[$ifIndex]["ifOutErrors"] > $ifOutErrors[$ifIndex]) {
				if (($overflow - $db_interface[$ifIndex]["ifOutErrors"] + $ifOutErrors[$ifIndex]) < $fudge) {
					$int_ifOutErrors = $overflow - $db_interface[$ifIndex]["ifOutErrors"] + $ifOutErrors[$ifIndex];
				}else{
					$int_ifOutErrors = $db_interface[$ifIndex]["ifOutErrors"] - $ifOutErrors[$ifIndex];
				}
			}else{
				$int_ifOutErrors = $ifOutErrors[$ifIndex] - $db_interface[$ifIndex]["ifOutErrors"];
			}
		}else{
			$int_ifOutErrors = 0;
		}

		if ($int_ifInErrors > 0 || $int_ifOutErrors > 0) {
			$int_errors_present = TRUE;
		}else{
			$int_errors_present = FALSE;
		}

		/* see if in discards's have been increasing */
		$int_ifInDiscards = 0;
		if (!isset($db_interface[$ifIndex]["ifInDiscards"])) {
			$int_ifInDiscards = 0;
		}else if (!isset($ifInDiscards[$ifIndex])) {
			$int_ifInDiscards = 0;
		}else if ($ifInDiscards[$ifIndex] <> $db_interface[$ifIndex]["ifInDiscards"]) {
			/* account for 2E32 rollover */
			/* there are two types of rollovers one rolls to 0 */
			/* the other counts backwards.  let's make an educated guess */
			if ($db_interface[$ifIndex]["ifInDiscards"] > $ifInDiscards[$ifIndex]) {
				if (($overflow - $db_interface[$ifIndex]["ifInDiscards"] + $ifInDiscards[$ifIndex]) < $fudge) {
					$int_ifInDiscards = $overflow - $db_interface[$ifIndex]["ifInDiscards"] + $ifInDiscards[$ifIndex];
				}else{
					$int_ifInDiscards = $db_interface[$ifIndex]["ifInDiscards"] - $ifInDiscards[$ifIndex];
				}
			}else{
				$int_ifInDiscards = $ifInDiscards[$ifIndex] - $db_interface[$ifIndex]["ifInDiscards"];
			}
		}else{
			$int_ifInDiscards = 0;
		}

		/* see if out discards's have been increasing */
		$int_ifOutDiscards = 0;
		if (!isset($db_interface[$ifIndex]["ifOutDiscards"])) {
			$int_ifOutDiscards = 0;
		}else if (!isset($ifOutDiscards[$ifIndex])) {
			$int_ifOutDiscards = 0;
		}else if ($ifOutDiscards[$ifIndex] <> $db_interface[$ifIndex]["ifOutDiscards"]) {
			/* account for 2E32 rollover */
			/* there are two types of rollovers one rolls to 0 */
			/* the other counts backwards.  let's make an educated guess */
			if ($db_interface[$ifIndex]["ifOutDiscards"] > $ifOutDiscards[$ifIndex]) {
				if (($overflow - $db_interface[$ifIndex]["ifOutDiscards"] + $ifOutDiscards[$ifIndex]) < $fudge) {
					$int_ifOutDiscards = $overflow - $db_interface[$ifIndex]["ifOutDiscards"] + $ifOutDiscards[$ifIndex];
				}else{
					$int_ifOutDiscards = $db_interface[$ifIndex]["ifOutDiscards"] - $ifOutDiscards[$ifIndex];
				}
			}else{
				$int_ifOutDiscards = $ifOutDiscards[$ifIndex] - $db_interface[$ifIndex]["ifOutDiscards"];
			}
		}else{
			$int_ifOutDiscards = 0;
		}

		if ($int_ifInDiscards > 0 || $int_ifOutDiscards > 0) {
			$int_discards_present = TRUE;
		}else{
			$int_discards_present = FALSE;
		}

		/* see if in discards's have been increasing */
		$int_ifInUnknownProtos = 0;
		if (!isset($db_interface[$ifIndex]["ifInUnknownProtos"])) {
			$int_ifInUnknownProtos = 0;
		}else if (!isset($ifInUnknownProtos[$ifIndex])) {
			$int_ifInUnknownProtos = 0;
		}else if ($ifInUnknownProtos[$ifIndex] <> $db_interface[$ifIndex]["ifInUnknownProtos"]) {
			/* account for 2E32 rollover */
			/* there are two types of rollovers one rolls to 0 */
			/* the other counts backwards.  let's make an educated guess */
			if ($db_interface[$ifIndex]["ifInUnknownProtos"] > $ifInUnknownProtos[$ifIndex]) {
				if (($overflow - $db_interface[$ifIndex]["ifInUnknownProtos"] + $ifInUnknownProtos[$ifIndex]) < $fudge) {
					$int_ifInUnknownProtos = $overflow - $db_interface[$ifIndex]["ifInUnknownProtos"] + $ifInUnknownProtos[$ifIndex];
				}else{
					$int_ifInUnknownProtos = $db_interface[$ifIndex]["ifInUnknownProtos"] - $ifInUnknownProtos[$ifIndex];
				}
			}else{
				$int_ifInUnknownProtos = $ifInUnknownProtos[$ifIndex] - $db_interface[$ifIndex]["ifInUnknownProtos"];
			}
		}else{
			$int_ifInUnknownProtos = 0;
		}

		/* format the update packet */
		if ($i == 0) {
			$insert_vals .= " ";
		}else{
			$insert_vals .= ",";
		}

		$mac_address = @xform_mac_address($ifPhysAddress[$ifIndex]);

		$insert_vals .= "('" .
			$device["site_id"]            . "', '" . $device["device_id"]          . "', '" .
			@$ifIndex                     . "', '" .
			@$ifTypes[$ifIndex]           . "', '" . @$ifNames[$ifIndex]           . "', " .
			@$cnn_id->qstr($ifAlias)      . ", '"  . @$linkPort                    . "', '" .
			@$vlan_id                     . "', "  . @$cnn_id->qstr(@$vlan_name)   . ", '" .
			@$vlan_trunk                  . "', '" . @$ifSpeed[$ifIndex]           . "', '" .
			@$ifHighSpeed[$ifIndex]       . "', "  . @$ifDuplex[$ifIndex]          . "', " .
			@$cnn_id->qstr(@$ifDescr[$ifIndex]) . ", '" .
			@$ifMtu[$ifIndex]             . "', '" . $mac_address                  . "', '" .
			@$ifAdminStatus[$ifIndex]     . "', '" . @$ifOperStatus[$ifIndex]      . "', '" .
			@$ifLastChange[$ifIndex]      . "', '" . @$ifInDiscards[$ifIndex]      . "', '" .
			@$ifInErrors[$ifIndex]        . "', '" . @$ifInUnknownProtos[$ifIndex] . "', '" .
			@$ifOutDiscards[$ifIndex]     . "', '" . @$ifOutErrors[$ifIndex]       . "', '" .
			@$int_ifInDiscards            . "', '" . @$int_ifInErrors              . "', '" .
			@$int_ifInUnknownProtos       . "', '" . @$int_ifOutDiscards           . "', '" .
			@$int_ifInOutErrors           . "', '" . @$int_discards_present        . "', '" .
			$int_errors_present           . "', '" .  $last_down_time              . "', '" .
			$last_up_time                 . "', '" .  $stateChanges                . "', '" . "1')";

		$i++;
	}
	}
	mactrack_debug("ifInterfaces assembly complete.");

	if (strlen($insert_vals)) {
		db_execute($insert_prefix . $insert_vals . $insert_suffix);
	}

	return $ifInterfaces;
}

/*	get_generic_switch_ports - This is a basic function that will scan the dot1d
  OID tree for all switch port to MAC address association and stores in the
  mac_track_temp_ports table for future processing in the finalization steps of the
  scanning process.
*/
function get_generic_switch_ports($site, &$device, $lowPort = 0, $highPort = 0) {
	global $debug, $scan_date;

	/* initialize port counters */
	$device["ports_total"] = 0;
	$device["ports_active"] = 0;
	$device["ports_trunk"] = 0;

	/* get the ifIndexes for the device */
	$ifIndexes = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.1", $device);
	mactrack_debug("ifIndexes data collection complete");

	$ifInterfaces = build_InterfacesTable($device, $ifIndexes, TRUE, FALSE);

	get_base_dot1dTpFdbEntry_ports($site, $device, $ifInterfaces, "", TRUE, $lowPort, $highPort);

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
	$device["ports_total"] = 0;
	$device["ports_active"] = 0;
	$device["ports_trunk"] = 0;

	/* get the ifIndexes for the device */
	$ifIndexes = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.1", $device);
	mactrack_debug("ifIndexes data collection complete");

	$ifInterfaces = build_InterfacesTable($device, $ifIndexes, TRUE, FALSE);

	get_base_dot1qTpFdbEntry_ports($site, $device, $ifInterfaces, "", TRUE, $lowPort, $highPort);

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
	$device["ports_total"] = 0;
	$device["ports_active"] = 0;
	$device["ports_trunk"] = 0;

	/* get the ifIndexes for the device */
	$ifIndexes = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.1", $device);
	mactrack_debug("ifIndexes data collection complete");

	$ifInterfaces = build_InterfacesTable($device, $ifIndexes, FALSE, FALSE);

	get_base_wireless_dot1dTpFdbEntry_ports($site, $device, $ifInterfaces, "", TRUE, $lowPort, $highPort);

	return $device;
}

/*	get_base_dot1dTpFdbEntry_ports - This function will grab information from the
  port bridge snmp table and return it to the calling progrem for further processing.
  This is a foundational function for all vendor data collection functions.
*/
function get_base_dot1dTpFdbEntry_ports($site, &$device, &$ifInterfaces, $snmp_readstring = "", $store_to_db = TRUE, $lowPort = 1, $highPort = 9999) {
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
	if ($snmp_readstring == "") {
		$snmp_readstring = $device["snmp_readstring"];
	}

	/* get the operational status of the ports */
	$active_ports_array = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.8", $device);
	$indexes = array_keys($active_ports_array);

	$i = 0;
	if (sizeof($active_ports_array)) {
	foreach($active_ports_array as $port_info) {
		$port_info =  mactrack_strip_alpha($port_info);
		if ((($ifInterfaces[$indexes[$i]]["ifType"] >= 6) &&
			($ifInterfaces[$indexes[$i]]["ifType"] <= 9)) ||
			($ifInterfaces[$indexes[$i]]["ifType"] == 71)) {
			if ($port_info == 1) {
				$ports_active++;
			}
			$ports_total++;
		}

		$i++;
	}
	}

	if ($store_to_db) {
		print("INFO: HOST: " . $device["hostname"] . ", TYPE: " . substr($device["snmp_sysDescr"],0,40) . ", TOTAL PORTS: " . $ports_total . ", OPER PORTS: " . $ports_active);
		if ($debug) {
			print("\n");
		}

		$device["ports_active"] = $ports_active;
		$device["ports_total"] = $ports_total;
		$device["macs_active"] = 0;
	}

	if ($ports_active > 0) {
		/* get bridge port to ifIndex mapping */
		$bridgePortIfIndexes = xform_standard_indexed_data(".1.3.6.1.2.1.17.1.4.1.2", $device, $snmp_readstring);

		$port_status = xform_stripped_oid(".1.3.6.1.2.1.17.4.3.1.3", $device, $snmp_readstring);

		/* get device active port numbers */
		$port_numbers = xform_stripped_oid(".1.3.6.1.2.1.17.4.3.1.2", $device, $snmp_readstring);

		/* get the ignore ports list from device */
		$ignore_ports = port_list_to_array($device["ignorePorts"]);

		/* determine user ports for this device and transfer user ports to
		   a new array.
		*/
		$i = 0;
		if (sizeof($port_numbers)) {
		foreach ($port_numbers as $key => $port_number) {
			if (($highPort == 0) ||
				(($port_number >= $lowPort) &&
				($port_number <= $highPort))) {

				if (!in_array($port_number, $ignore_ports)) {
					if ((@$port_status[$key] == "3") || (@$port_status[$key] == "5")) {
						$port_key_array[$i]["key"] = $key;
						$port_key_array[$i]["port_number"] = $port_number;

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
		if (sizeof($port_key_array)) {
		foreach ($port_key_array as $port_key) {
			/* map bridge port to interface port and check type */
			if ($port_key["port_number"] > 0) {
				if (sizeof($bridgePortIfIndexes)) {
					/* some hubs do not always return a port number in the bridge table.
					   test for it by isset and substiture the port number from the ifTable
					   if it isnt in the bridge table
					*/
					if (isset($bridgePortIfIndexes[$port_key["port_number"]])) {
						$brPortIfIndex = @$bridgePortIfIndexes[$port_key["port_number"]];
					}else{
						$brPortIfIndex = @$port_key["port_number"];
					}
					$brPortIfType = @$ifInterfaces[$brPortIfIndex]["ifType"];
				}else{
					$brPortIfIndex = $port_key["port_number"];
					$brPortIfType = @$ifInterfaces[$port_key["port_number"]]["ifType"];
				}

				if (($brPortIfType >= 6) &&
					($brPortIfType <= 9) &&
					(!isset($ifInterfaces[$brPortIfIndex]["portLink"]))) {
					/* set some defaults  */
					$new_port_key_array[$i]["vlan_id"] = "N/A";
					$new_port_key_array[$i]["vlan_name"] = "N/A";
					$new_port_key_array[$i]["mac_address"] = "NOT USER";
					$new_port_key_array[$i]["port_number"] = "NOT USER";
					$new_port_key_array[$i]["port_name"] = "N/A";

					/* now set the real data */
					$new_port_key_array[$i]["key"] = $port_key["key"];
					$new_port_key_array[$i]["port_number"] = $port_key["port_number"];
					$i++;
				}
			}
		}
		}
		mactrack_debug("Port number information collected.");

		/* map mac address */
		/* only continue if there were user ports defined */
		if (sizeof($new_port_key_array)) {
			/* get the bridges active MAC addresses */
			$port_macs = xform_stripped_oid(".1.3.6.1.2.1.17.4.3.1.1", $device, $snmp_readstring);

			if (sizeof($port_macs)) {
			foreach ($port_macs as $key => $port_mac) {
				$port_macs[$key] = xform_mac_address($port_mac);
			}
			}

			if (sizeof($new_port_key_array)) {
			foreach ($new_port_key_array as $key => $port_key) {
				$new_port_key_array[$key]["mac_address"] = @$port_macs[$port_key["key"]];
				mactrack_debug("INDEX: '". $key . "' MAC ADDRESS: " . $new_port_key_array[$key]["mac_address"]);
			}
			}

			mactrack_debug("Port mac address information collected.");
		}else{
			mactrack_debug("No user ports on this network.");
		}
	}else{
		mactrack_debug("No user ports on this network.");
	}

	if ($store_to_db) {
		if ($ports_active <= 0) {
			$device["last_runmessage"] = "Data collection completed ok";
		}elseif (sizeof($new_port_key_array)) {
			$device["last_runmessage"] = "Data collection completed ok";
			$device["macs_active"] = sizeof($new_port_key_array);
			db_store_device_port_results($device, $new_port_key_array, $scan_date);
		}else{
			$device["last_runmessage"] = "WARNING: Poller did not find active ports on this device.";
		}

		if(!$debug) {
			print(" - Complete\n");
		}
	}else{
		return $new_port_key_array;
	}
}

/*	get_base_wireless_dot1dTpFdbEntry_ports - This function will grab information from the
  port bridge snmp table and return it to the calling progrem for further processing.
  This is a foundational function for all vendor data collection functions.
*/
function get_base_wireless_dot1dTpFdbEntry_ports($site, &$device, &$ifInterfaces, $snmp_readstring = "", $store_to_db = TRUE, $lowPort = 1, $highPort = 9999) {
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
	if ($snmp_readstring == "") {
		$snmp_readstring = $device["snmp_readstring"];
	}

	/* get the operational status of the ports */
	$active_ports_array = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.8", $device);
	$indexes = array_keys($active_ports_array);

	$i = 0;
	if (sizeof($active_ports_array)) {
	foreach($active_ports_array as $port_info) {
		$port_info =  mactrack_strip_alpha($port_info);
		if ((($ifInterfaces[$indexes[$i]]["ifType"] >= 6) &&
			($ifInterfaces[$indexes[$i]]["ifType"] <= 9)) ||
			($ifInterfaces[$indexes[$i]]["ifType"] == 71)) {
			if ($port_info == 1) {
				$ports_active++;
			}
			$ports_total++;
		}
		$i++;
	}
	}

	if ($store_to_db) {
		print("INFO: HOST: " . $device["hostname"] . ", TYPE: " . substr($device["snmp_sysDescr"],0,40) . ", TOTAL PORTS: " . $ports_total . ", OPER PORTS: " . $ports_active);
		if ($debug) {
			print("\n");
		}

		$device["ports_active"] = $ports_active;
		$device["ports_total"] = $ports_total;
		$device["macs_active"] = 0;
	}

	if ($ports_active > 0) {
		/* get bridge port to ifIndex mapping */
		$bridgePortIfIndexes = xform_standard_indexed_data(".1.3.6.1.2.1.17.1.4.1.2", $device, $snmp_readstring);

		$port_status = xform_stripped_oid(".1.3.6.1.2.1.17.4.3.1.3", $device, $snmp_readstring);

		/* get device active port numbers */
		$port_numbers = xform_stripped_oid(".1.3.6.1.2.1.17.4.3.1.2", $device, $snmp_readstring);

		/* get the ignore ports list from device */
		$ignore_ports = port_list_to_array($device["ignorePorts"]);

		/* get the bridge root port so we don't capture active ports on it */
		$bridge_root_port = @cacti_snmp_get($device["hostname"], $snmp_readstring,
					".1.3.6.1.2.1.17.2.7.0", $device["snmp_version"],
					"", "", "", "", "", "", $device["snmp_port"], $device["snmp_timeout"]);

		/* determine user ports for this device and transfer user ports to
		   a new array.
		*/
		$i = 0;
		if (sizeof($port_numbers)) {
		foreach ($port_numbers as $key => $port_number) {
			if (($highPort == 0) ||
				(($port_number >= $lowPort) &&
				($port_number <= $highPort) &&
				($bridge_root_port != $port_number))) {

				if (!in_array($port_number, $ignore_ports)) {
					if ((@$port_status[$key] == "3") || (@$port_status[$key] == "5")) {
						$port_key_array[$i]["key"] = $key;
						$port_key_array[$i]["port_number"] = $port_number;

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
		if (sizeof($port_key_array)) {
		foreach ($port_key_array as $port_key) {
			/* map bridge port to interface port and check type */
			if ($port_key["port_number"] > 0) {
				if (sizeof($bridgePortIfIndexes)) {
					$brPortIfIndex = @$bridgePortIfIndexes[$port_key["port_number"]];
					$brPortIfType = @$ifInterfaces[$brPortIfIndex]["ifType"];
				}else{
					$brPortIfIndex = $port_key["port_number"];
					$brPortIfType = @$ifInterfaces[$port_key["port_number"]]["ifType"];
				}

				if ((($brPortIfType >= 6) && ($brPortIfType <= 9)) || ($brPortIfType == 71)) {
					/* set some defaults  */
					$new_port_key_array[$i]["vlan_id"] = "N/A";
					$new_port_key_array[$i]["vlan_name"] = "N/A";
					$new_port_key_array[$i]["mac_address"] = "NOT USER";
					$new_port_key_array[$i]["port_number"] = "NOT USER";
					$new_port_key_array[$i]["port_name"] = "N/A";

					/* now set the real data */
					$new_port_key_array[$i]["key"] = $port_key["key"];
					$new_port_key_array[$i]["port_number"] = $port_key["port_number"];
					$i++;
				}
			}
		}
		}
		mactrack_debug("Port number information collected.");

		/* map mac address */
		/* only continue if there were user ports defined */
		if (sizeof($new_port_key_array)) {
			/* get the bridges active MAC addresses */
			$port_macs = xform_stripped_oid(".1.3.6.1.2.1.17.4.3.1.1", $device, $snmp_readstring);

			if (sizeof($port_macs)) {
			foreach ($port_macs as $key => $port_mac) {
				$port_macs[$key] = xform_mac_address($port_mac);
			}
			}

			if (sizeof($new_port_key_array)) {
			foreach ($new_port_key_array as $key => $port_key) {
				$new_port_key_array[$key]["mac_address"] = @$port_macs[$port_key["key"]];
				mactrack_debug("INDEX: '". $key . "' MAC ADDRESS: " . $new_port_key_array[$key]["mac_address"]);
			}
			}

			mactrack_debug("Port mac address information collected.");
		}else{
			mactrack_debug("No user ports on this network.");
		}
	}else{
		mactrack_debug("No user ports on this network.");
	}

	if ($store_to_db) {
		if ($ports_active <= 0) {
			$device["last_runmessage"] = "Data collection completed ok";
		}elseif (sizeof($new_port_key_array)) {
			$device["last_runmessage"] = "Data collection completed ok";
			$device["macs_active"] = sizeof($new_port_key_array);
			db_store_device_port_results($device, $new_port_key_array, $scan_date);
		}else{
			$device["last_runmessage"] = "WARNING: Poller did not find active ports on this device.";
		}

		if(!$debug) {
			print(" - Complete\n");
		}
	}else{
		return $new_port_key_array;
	}
}

/*	get_base_dot1qTpFdbEntry_ports - This function will grab information from the
  port bridge snmp table and return it to the calling progrem for further processing.
  This is a foundational function for all vendor data collection functions.
*/
function get_base_dot1qTpFdbEntry_ports($site, &$device, &$ifInterfaces, $snmp_readstring = "", $store_to_db = TRUE, $lowPort = 1, $highPort = 9999) {
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
	if ($snmp_readstring == "") {
		$snmp_readstring = $device["snmp_readstring"];
	}

	/* get the operational status of the ports */
	$active_ports_array = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.8", $device);
	$indexes = array_keys($active_ports_array);

	$i = 0;
	if (sizeof($active_ports_array)) {
	foreach($active_ports_array as $port_info) {
		$port_info =  mactrack_strip_alpha($port_info);
		if ((($ifInterfaces[$indexes[$i]]["ifType"] >= 6) &&
			($ifInterfaces[$indexes[$i]]["ifType"] <= 9)) ||
			($ifInterfaces[$indexes[$i]]["ifType"] == 71)) {
			if ($port_info == 1) {
				$ports_active++;
			}
			$ports_total++;
		}
		$i++;
	}
	}

	if ($store_to_db) {
		print("INFO: HOST: " . $device["hostname"] . ", TYPE: " . substr($device["snmp_sysDescr"],0,40) . ", TOTAL PORTS: " . $ports_total . ", OPER PORTS: " . $ports_active);
		if ($debug) {
			print("\n");
		}

		$device["ports_active"] = $ports_active;
		$device["ports_total"] = $ports_total;
		$device["macs_active"] = 0;
	}

	if ($ports_active > 0) {
		/* get bridge port to ifIndex mapping */
		$bridgePortIfIndexes = xform_standard_indexed_data(".1.3.6.1.2.1.17.1.4.1.2", $device, $snmp_readstring);

		$port_status = xform_stripped_oid(".1.3.6.1.2.1.17.7.1.2.2.1.3", $device, $snmp_readstring);

		/* get device active port numbers */
		$port_numbers = xform_stripped_oid(".1.3.6.1.2.1.17.7.1.2.2.1.2", $device, $snmp_readstring);

		/* get the ignore ports list from device */
		$ignore_ports = port_list_to_array($device["ignorePorts"]);

		/* get the bridge root port so we don't capture active ports on it */
		$bridge_root_port = @cacti_snmp_get($device["hostname"], $snmp_readstring,
					".1.3.6.1.2.1.17.2.7.0", $device["snmp_version"],
					"", "", "", "", "", "", $device["snmp_port"], $device["snmp_timeout"]);

		/* determine user ports for this device and transfer user ports to
		   a new array.
		*/
		$i = 0;
		if (sizeof($port_numbers)) {
		foreach ($port_numbers as $key => $port_number) {
			if (($highPort == 0) ||
				(($port_number >= $lowPort) &&
				($port_number <= $highPort) &&
				($bridge_root_port != $port_number))) {

				if (!in_array($port_number, $ignore_ports)) {
					if ((@$port_status[$key] == "3") || (@$port_status[$key] == "5")) {
						$port_key_array[$i]["key"] = $key;
						$port_key_array[$i]["port_number"] = $port_number;

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
		if (sizeof($port_key_array)) {
		foreach ($port_key_array as $port_key) {
			/* map bridge port to interface port and check type */
			if ($port_key["port_number"] > 0) {
				if (sizeof($bridgePortIfIndexes)) {
					$brPortIfIndex = @$bridgePortIfIndexes[$port_key["port_number"]];
					$brPortIfType = @$ifInterfaces[$brPortIfIndex]["ifType"];
				}else{
					$brPortIfIndex = $port_key["port_number"];
					$brPortIfType = @$ifInterfaces[$port_key["port_number"]]["ifType"];
				}

				if ((($brPortIfType >= 6) && ($brPortIfType <= 9)) || ($brPortIfType == 71)) {
					/* set some defaults  */
					$new_port_key_array[$i]["vlan_id"] = "N/A";
					$new_port_key_array[$i]["vlan_name"] = "N/A";
					$new_port_key_array[$i]["mac_address"] = "NOT USER";
					$new_port_key_array[$i]["port_number"] = "NOT USER";
					$new_port_key_array[$i]["port_name"] = "N/A";

					/* now set the real data */
					$new_port_key_array[$i]["key"] = $port_key["key"];
					$new_port_key_array[$i]["port_number"] = $port_key["port_number"];
					$i++;
				}
			}
		}
		}
		mactrack_debug("Port number information collected.");

		/* map mac address */
		/* only continue if there were user ports defined */
		if (sizeof($new_port_key_array)) {
			/* get the bridges active MAC addresses */
			$port_macs = xform_stripped_oid(".1.3.6.1.2.1.17.7.1.2.2.1.1", $device, $snmp_readstring);

			if (sizeof($port_macs)) {
			foreach ($port_macs as $key => $port_mac) {
				$port_macs[$key] = xform_mac_address($port_mac);
			}
			}

			if (sizeof($new_port_key_array)) {
			foreach ($new_port_key_array as $key => $port_key) {
				$new_port_key_array[$key]["mac_address"] = @$port_macs[$port_key["key"]];
				mactrack_debug("INDEX: '". $key . "' MAC ADDRESS: " . $new_port_key_array[$key]["mac_address"]);
			}
			}

			mactrack_debug("Port mac address information collected.");
		}else{
			mactrack_debug("No user ports on this network.");
		}
	}else{
		mactrack_debug("No user ports on this network.");
	}

	if ($store_to_db) {
		if ($ports_active <= 0) {
			$device["last_runmessage"] = "Data collection completed ok";
		}elseif (sizeof($new_port_key_array)) {
			$device["last_runmessage"] = "Data collection completed ok";
			$device["macs_active"] = sizeof($new_port_key_array);
			db_store_device_port_results($device, $new_port_key_array, $scan_date);
		}else{
			$device["last_runmessage"] = "WARNING: Poller did not find active ports on this device.";
		}

		if(!$debug) {
			print(" - Complete\n");
		}
	}else{
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
	$octets = explode(".", $ip);

	/* perform a quick error check */
	if (count($octets) != 4) return "ERROR";

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
			return "ERROR";
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

	if ($info["timed_out"]) {
		return "timed_out";
	}

	/* more error handling */
	if ($response == "") { return $ip; }

	/* parse the response and find the response type */
	$type = @unpack("s", substr($response, $requestsize+2));

	if ($type[1] == 0x0C00) {
		/* set up our variables */
		$host = "";
		$len = 0;

		/* set our pointer at the beginning of the hostname uses the request
		   size from earlier rather than work it out.
		*/
		$position = $requestsize + 12;

		/* reconstruct the hostname */
		do {
			/* get segment size */
			$len = unpack("c", substr($response, $position));

			/* null terminated string, so length 0 = finished */
			if ($len[1] == 0) {
				/* return the hostname, without the trailing '.' */
				return substr($host, 0, strlen($host) -1);
			}

			/* add the next segment to our host */
			$host .= substr($response, $position+1, $len[1]) . ".";

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
  TRUE array value if an IP exists on that ifIndex.
*/
function get_link_port_status(&$device) {
	$return_array = array();

	$walk_array = cacti_snmp_walk($device["hostname"], $device["snmp_readstring"],
					".1.3.6.1.2.1.4.20.1.2", $device["snmp_version"], "", "",
					"", "", "", "", $device["snmp_port"], $device["snmp_timeout"]);

	if (sizeof($walk_array)) {
	foreach ($walk_array as $walk_item) {
		$return_array[$walk_item["value"]] = TRUE;
	}
	}

	return $return_array;
}

/*  xform_stripped_oid - This function walks an OID and then strips the seed OID
  from the complete OID.  It returns the stripped OID as the key and the return
  value as the value of the resulting array
*/
function xform_stripped_oid($OID, &$device, $snmp_readstring = "") {
	$return_array = array();

	if (strlen($snmp_readstring) == 0) {
		$snmp_readstring = $device["snmp_readstring"];
	}

	$walk_array = cacti_snmp_walk($device["hostname"], $snmp_readstring,
					$OID, $device["snmp_version"], "", "",
					"", "", "", "", $device["snmp_port"], $device["snmp_timeout"]);

	$OID = ereg_replace("^\.", "", $OID);

	$i = 0;

	if (sizeof($walk_array)) {
	foreach ($walk_array as $walk_item) {
		$key = $walk_item["oid"];
		$key = str_replace("iso", "1", $key);
		$key = str_replace($OID . ".", "", $key);
		$return_array[$i]["key"] = $key;
		$return_array[$i]["value"] = $walk_item["value"];

		$i++;
	}
	}

	return array_rekey($return_array, "key", "value");
}

/*  xform_net_address - This function will return the IP address.  If the agent or snmp
  returns a differently formated IP address, then this function will convert it to dotted
  decimal notation and return.
*/
function xform_net_address($ip_address) {
	if (substr_count($ip_address, "Network Address:")) {
		$ip_address = trim(str_replace("Network Address:", "", $ip_address));
	}

	if (substr_count($ip_address, ":") != 0) {
		if (strlen($ip_address) > 11) {
			/* ipv6, don't alter */
		}else{
			$new_address = "";
			while (1) {
				$new_address .= hexdec(substr($ip_address, 0, 2));
				$ip_address = substr($ip_address, 3);
				if (!substr_count($ip_address, ":")) {
					if (strlen($ip_address)) {
						$ip_address = trim($new_address . "." . hexdec(trim($ip_address)));
					}else{
						$ip_address = trim($new_address . $ip_address);
					}
					break;
				}else{
					$new_address .= ".";
				}
			}
		}
	}

	return $ip_address;
}

/*	xform_mac_address - This function will take a variable that is either formated as
  hex or as a string representing hex and convert it to what the mactrack scanning
  function expects.
*/
function xform_mac_address($mac_address) {
	if (strlen($mac_address) == 0) {
		$mac_address = "NOT USER";
	}else{
		if (strlen($mac_address) > 10) { /* return is in ascii */
			$mac_address = str_replace("HEX-00:", "", strtoupper($mac_address));
			$mac_address = str_replace("HEX-:", "", strtoupper($mac_address));
			$mac_address = str_replace("HEX-", "", strtoupper($mac_address));
			$mac_address = trim(str_replace("\"", "", $mac_address));
			$mac_address = str_replace(" ", read_config_option("mt_mac_delim"), $mac_address);
			$mac_address = str_replace(":", read_config_option("mt_mac_delim"), $mac_address);
		}else{ /* return is hex */
			$mac = "";
			for ($j = 0; $j < strlen($mac_address); $j++) {
				$mac .= bin2hex($mac_address[$j]) . read_config_option("mt_mac_delim");
			}
			$mac_address = $mac;
		}
	}

	return $mac_address;
}

/*	xform_standard_indexed_data - This function takes an OID, and a device, and
  optionally an alternate snmp_readstring as input parameters and then walks the
  OID and returns the data in array[index] = value format.
*/
function xform_standard_indexed_data($xformOID, &$device, $snmp_readstring = "") {
	/* get raw index data */
	if ($snmp_readstring == "") {
		$snmp_readstring = $device["snmp_readstring"];
	}

	$xformArray = cacti_snmp_walk($device["hostname"], $snmp_readstring,
					$xformOID, $device["snmp_version"], "", "",
					"", "", "", "", $device["snmp_port"], $device["snmp_timeout"]);

	$i = 0;

	if (sizeof($xformArray)) {
	foreach($xformArray as $xformItem) {
		$perPos = strrpos($xformItem["oid"], ".");
		$xformItemID = substr($xformItem["oid"], $perPos+1);
		$xformArray[$i]["oid"] = $xformItemID;
		$i++;
	}
	}

	return array_rekey($xformArray, "oid", "value");
}

/*	xform_dot1q_vlan_associations - This function takes an OID, and a device, and
  optionally an alternate snmp_readstring as input parameters and then walks the
  OID and returns the data in array[index] = value format.
*/
function xform_dot1q_vlan_associations(&$device, $snmp_readstring = "") {
	/* get raw index data */
	if ($snmp_readstring == "") {
		$snmp_readstring = $device["snmp_readstring"];
	}

	/* initialize the output array */
	$output_array = array();

	/* obtain vlan associations */
	$xformArray = cacti_snmp_walk($device["hostname"], $snmp_readstring,
					".1.3.6.1.2.1.17.7.1.2.2.1.2", $device["snmp_version"],
					"", "", "", "", "", "", $device["snmp_port"], $device["snmp_timeout"]);

	$i = 0;

	if (sizeof($xformArray)) {
	foreach($xformArray as $xformItem) {
		/* peel off the beginning of the OID */
		$key = $xformItem["oid"];
		$key = str_replace("iso", "1", $key);
		$key = str_replace("1.3.6.1.2.1.17.7.1.2.2.1.2.", "", $key);

		/* now grab the VLAN */
		$perPos = strpos($key, ".");
		$output_array[$i]["vlan_id"] = substr($key,0,$perPos);
		/* save the key for association with the dot1d table */
		$output_array[$i]["key"] = substr($key, $perPos+1);
		$i++;
	}
	}

	return array_rekey($output_array, "key", "vlan_id");
}

/*	xform_cisco_workgroup_port_data - This function is specific to Cisco devices that
  use the last two OID values from each complete OID string to represent the switch
  card and port.  The function returns data in the format array[card.port] = value.
*/
function xform_cisco_workgroup_port_data($xformOID, &$device) {
	/* get raw index data */
	$xformArray = cacti_snmp_walk($device["hostname"], $device["snmp_readstring"],
							$xformOID, $device["snmp_version"], "", "",
							"", "", "", "", $device["snmp_port"], $device["snmp_timeout"]);

	$i = 0;

	if (sizeof($xformArray)) {
	foreach($xformArray as $xformItem) {
		$perPos = strrpos($xformItem["oid"], ".");
		$xformItem_piece1 = substr($xformItem["oid"], $perPos+1);
		$xformItem_remainder = substr($xformItem["oid"], 0, $perPos);
		$perPos = strrpos($xformItem_remainder, ".");
		$xformItem_piece2 = substr($xformItem_remainder, $perPos+1);
		$xformArray[$i]["oid"] = $xformItem_piece2 . "/" . $xformItem_piece1;

		$i++;
	}
	}

	return array_rekey($xformArray, "oid", "value");
}

/*	xform_indexed_data - This function is similar to other the other xform_* functions
  in that it takes the end of each OID and uses the last $xformLevel positions as the
  index.  Therefore, if $xformLevel = 3, the return value would be as follows:
  array[1.2.3] = value.
*/
function xform_indexed_data($xformOID, &$device, $xformLevel = 1) {
	/* get raw index data */
	$xformArray = cacti_snmp_walk($device["hostname"], $device["snmp_readstring"],
						$xformOID, $device["snmp_version"], "", "",
						"", "", "", "", $device["snmp_port"], $device["snmp_timeout"]);

	$i = 0;
	$output_array = array();

	if (sizeof($xformArray)) {
	foreach($xformArray as $xformItem) {
		/* break down key */
		$OID = $xformItem["oid"];
		for ($j = 0; $j < $xformLevel; $j++) {
			$perPos = strrpos($OID, ".");
			$xformItem_piece[$j] = substr($OID, $perPos+1);
			$OID = substr($OID, 0, $perPos);
		}

		/* reassemble key */
		$key = "";
		for ($j = $xformLevel-1; $j >= 0; $j--) {
			$key .= $xformItem_piece[$j];
			if ($j > 0) {
				$key .= ".";
			}
		}

		$output_array[$i]["key"] = $key;
		$output_array[$i]["value"] = $xformItem["value"];

		$i++;
	}
	}

	return array_rekey($output_array, "key", "value");
}

/*	db_process_add - This function adds a process to the process table with the entry
  with the device_id as key.
*/
function db_process_add($device_id, $storepid = FALSE) {
    /* store the PID if required */
	if ($storepid) {
		$pid = getmypid();
	}else{
		$pid = 0;
	}

	/* store pseudo process id in the database */
	db_execute("INSERT INTO mac_track_processes (device_id, process_id, status, start_date) VALUES ('" . $device_id . "', '" . $pid . "', 'Running', NOW())");
}

/*	db_process_remove - This function removes a devices entry from the processes
  table indicating that the device is done processing and the next device may start.
*/
function db_process_remove($device_id) {
	db_execute("DELETE FROM mac_track_processes WHERE device_id='" . $device_id . "'");
}

/*	db_update_device_status - This function is used by the scanner to save the status
  of the current device including the number of ports, it's readstring, etc.
*/
function db_update_device_status(&$device, $host_up, $scan_date, $start_time) {
	global $debug;

	list($micro,$seconds) = split(" ", microtime());
	$end_time = $seconds + $micro;
	$runduration = $end_time - $start_time;

	if ($host_up == TRUE) {
		$update_string = "UPDATE mac_track_devices " .
			"SET ports_total='" . $device["ports_total"] . "'," .
			"device_type_id='" . $device["device_type_id"] . "'," .
			"scan_type = '" . $device ["scan_type"] . "'," .
			"vlans_total='" . $device["vlans_total"] . "'," .
			"ports_active='" . $device["ports_active"] . "'," .
			"ports_trunk='" . $device["ports_trunk"] . "'," .
			"macs_active='" . $device["macs_active"] . "'," .
			"snmp_readstring='" . $device["snmp_readstring"] . "'," .
			"snmp_sysName='" . addslashes($device["snmp_sysName"]) . "'," .
			"snmp_sysLocation='" . addslashes($device["snmp_sysLocation"]) . "'," .
			"snmp_sysContact='" . addslashes($device["snmp_sysContact"]) . "'," .
			"snmp_sysObjectID='" . $device["snmp_sysObjectID"] . "'," .
			"snmp_sysDescr='" . addslashes($device["snmp_sysDescr"]) . "'," .
			"snmp_sysUptime='" . $device["snmp_sysUptime"] . "'," .
			"snmp_status='" . $device["snmp_status"] . "'," .
			"last_runmessage='" . $device["last_runmessage"] . "'," .
			"last_rundate='" . $scan_date . "'," .
			"last_runduration='" . round($runduration,4) . "' " .
			"WHERE device_id ='" . $device["device_id"] . "'";
	}else{
		$update_string = "UPDATE mac_track_devices " .
			"SET snmp_status='" . $device["snmp_status"] . "'," .
			"device_type_id='" . $device["device_type_id"] . "'," .
			"scan_type = '" . $device ["scan_type"] . "'," .
			"vlans_total='0'," .
			"ports_active='0'," .
			"ports_trunk='0'," .
			"macs_active='0'," .
			"last_runmessage='Device Unreachable', " .
			"last_rundate='" . $scan_date . "'," .
			"last_runduration='" . round($runduration,4) . "' " .
			"WHERE device_id ='" . $device["device_id"] . "'";
	}

	mactrack_debug("SQL: " . $update_string);

	db_execute($update_string);
}

/*	db_store_device_results - This function stores each of the port results into
  the temporary port results table for future processes once all devices have been
  scanned.
*/
function db_store_device_port_results(&$device, $port_array, $scan_date) {
	global $debug;

	/* output details to database */
	if (sizeof($port_array)) {
	foreach($port_array as $port_value) {
		if (($port_value["port_number"] <> "NOT USER") &&
			(($port_value["mac_address"] <> "NOT USER") && (strlen($port_value["mac_address"]) > 0))){

			$mac_authorized = db_check_auth($port_value["mac_address"]);
			mactrack_debug("Authorized MAC ID: " . $mac_authorized);

			if ($mac_authorized > 0) {
				$authorized_mac = 1;
			} else {
				$authorized_mac = 0;
			}

			$insert_string = "REPLACE INTO mac_track_temp_ports " .
				"(site_id,device_id,hostname,device_name,vlan_id,vlan_name," .
				"mac_address,port_number,port_name,scan_date,authorized)" .
				" VALUES ('" .
				$device["site_id"] . "','" .
				$device["device_id"] . "','" .
				addslashes($device["hostname"]) . "','" .
				addslashes($device["device_name"]) . "','" .
				$port_value["vlan_id"] . "','" .
				addslashes($port_value["vlan_name"]) . "','" .
				$port_value["mac_address"] . "','" .
				$port_value["port_number"] . "','" .
				addslashes($port_value["port_name"]) . "','" .
				$scan_date . "','" .
				$authorized_mac . "')";

			mactrack_debug("SQL: " . $insert_string);

			db_execute($insert_string);
		}
	}
	}
}

/* db_check_auth - This function checks whether the mac address exists in the mac_track+macauth table
*/
function db_check_auth($mac_address) {
	$check_string = "SELECT mac_id FROM mac_track_macauth WHERE mac_address LIKE '%%" . $mac_address . "%%'";
	mactrack_debug("SQL: " . $check_string);

	$query = db_fetch_cell($check_string);

	return $query;
}

/*	perform_mactrack_db_maint - This utility removes stale records from the database.
*/
function perform_mactrack_db_maint() {
	global $colors;

	/* remove stale records from the poller database */
	$retention = read_config_option("mt_data_retention");
	switch ($retention) {
	case "2days":
		$retention_date = date("Y-m-d H:i:s", strtotime("-2 Days"));
		break;
	case "5days":
		$retention_date = date("Y-m-d H:i:s", strtotime("-5 Days"));
		break;
	case "1week":
		$retention_date = date("Y-m-d H:i:s", strtotime("-1 Week"));
		break;
	case "2weeks":
		$retention_date = date("Y-m-d H:i:s", strtotime("-2 Week"));
		break;
	case "3weeks":
		$retention_date = date("Y-m-d H:i:s", strtotime("-3 Week"));
		break;
	case "1month":
		$retention_date = date("Y-m-d H:i:s", strtotime("-1 Month"));
		break;
	case "2months":
		$retention_date = date("Y-m-d H:i:s", strtotime("-2 Months"));
		break;
	default:
		$retention_date = date("Y-m-d H:i:s", strtotime("-2 Days"));
	}

	mactrack_debug("Started deleting old records from the main database.");
	db_execute("DELETE QUICK FROM mac_track_ports WHERE scan_date < '$retention_date'");
	db_execute("OPTIMIZE TABLE mac_track_ports");
	db_execute("TRUNCATE TABLE mac_track_scan_dates");
	db_execute("REPLACE INTO mac_track_scan_dates (SELECT DISTINCT scan_date from mac_track_ports);");
	mactrack_debug("Finished deleting old records from the main database.");
}

function import_oui_database($type = "ui", $oui_file = "http://standards.ieee.org/regauth/oui/oui.txt") {
	global $colors, $cnn_id;

	if ($type != "ui") {
		html_start_box("<strong>MacTrack OUI Database Import Results</strong>", "100%", $colors["header"], "1", "center", "");
		?><tr><td>Getting OUI Database from IEEE</td></tr><?php
	}else{
		echo "Getting OUI Database from the IEEE\n";
	}

	$oui_database = file($oui_file);

	if ($type != "ui") print "<tr><td>";

	if (sizeof($oui_database)) {
		echo "OUI Database Download from IEEE Complete\n";
	}else{
		echo "OUI Database Download from IEEE FAILED\n";
	}

	if ($type != "ui") print "</td></tr>";

	if (sizeof($oui_database)) {
		db_execute("UPDATE mac_track_oui_database SET present=0");

		/* initialize some variables */
		$begin_vendor = FALSE;
		$vendor_mac     = "";
		$vendor_name    = "";
		$vendor_address = "";
		$i = 0;

		if ($type != "ui") print "<tr><td>";

		if (sizeof($oui_database)) {
		foreach ($oui_database as $row) {
			$row = str_replace("\t", " ", $row);
			if (($begin_vendor) && (strlen(trim($row)) == 0)) {
				if (substr($vendor_address,0,1) == ",") $vendor_address = substr($vendor_address,1);
				if (substr($vendor_name,0,1) == ",")    $vendor_name    = substr($vendor_name,1);

				db_execute("REPLACE INTO mac_track_oui_database
					(vendor_mac, vendor_name, vendor_address, present)
					VALUES ('" . $vendor_mac . "'," .
					$cnn_id->qstr(ucwords(strtolower($vendor_name))) . "," .
					$cnn_id->qstr(str_replace("\n", ", ", ucwords(strtolower(trim($vendor_address))))) . ",'1')");

				/* let the user know you are working */
				if ((($i % 100) == 0) && ($type == "ui")) echo ".";
				$i++;

				/* reinitialize variables */
				$begin_vendor   = FALSE;
				$vendor_mac     = "";
				$vendor_name    = "";
				$vendor_address = "";
			}else{
				if ($begin_vendor) {
					if (strpos($row, "(base 16)")) {
						$address_start = strpos($row, "(base 16)") + 10;
						$vendor_address .= trim(substr($row,$address_start)) . "\n";
					}else{
						$vendor_address .= trim($row) . "\n";
					}
				}else{
					$vendor_address = "";
				}
			}

			if (substr_count($row, "(hex)")) {
				$begin_vendor = TRUE;
				$vendor_mac = str_replace("-", ":", substr($row, 0, 8));
				$hex_end = strpos($row, "(hex)") + 5;
				$vendor_name= trim(substr($row,$hex_end));
			}
		}
		}
		if ($type != "ui") print "</td></tr>";

		/* count bogus records */
		$j = db_fetch_cell("SELECT count(*) FROM mac_track_oui_database WHERE present=0");

		/* get rid of old records */
		db_execute("DELETE FROM mac_track_oui_database WHERE present=0");

		/* report some information */
		if ($type != "ui") print "<tr><td>";
		echo "\nThere were '" . $i . "' Entries Added/Updated in the database.";
		if ($type != "ui") print "</td></td><tr><td>";
		echo "\nThere were '" . $j . "' Records Removed from the database.\n";
		if ($type != "ui") print "</td></tr>";

		if ($type != "ui") html_end_box();
	}
}

function get_netscreen_arp_table($site, &$device) {
	global $debug, $scan_date;

	/* get the atifIndexes for the device */
	$atifIndexes = xform_indexed_data(".1.3.6.1.2.1.3.1.1.1", $device, 6);

	if (sizeof($atifIndexes)) {
		$ifIntcount = 1;
	}else{
		$ifIntcount = 0;
	}

	if ($ifIntcount != 0) {
		$atifIndexes = xform_indexed_data(".1.3.6.1.2.1.4.22.1.1", $device, 5);
	}
	mactrack_debug("atifIndexes data collection complete");

	/* get the atPhysAddress for the device */
	if ($ifIntcount != 0) {
		$atPhysAddress = xform_indexed_data(".1.3.6.1.2.1.4.22.1.2", $device, 5);
	} else {
		$atPhysAddress = xform_indexed_data(".1.3.6.1.2.1.3.1.1.2", $device, 6);
	}

	/* convert the mac address if necessary */
	$keys = array_keys($atPhysAddress);
	$i = 0;
	if (sizeof($atPhysAddress)) {
	foreach($atPhysAddress as $atAddress) {
		$atPhysAddress[$keys[$i]] = xform_mac_address($atAddress);
		$i++;
	}
	}
	mactrack_debug("atPhysAddress data collection complete");

	/* get the atPhysAddress for the device */
	if ($ifIntcount != 0) {
		$atNetAddress = xform_indexed_data(".1.3.6.1.2.1.4.22.1.3", $device, 5);
	} else {
		$atNetAddress = xform_indexed_data(".1.3.6.1.2.1.3.1.1.3", $device, 6);
	}
	mactrack_debug("atNetAddress data collection complete");

	/* get the ifNames for the device */
	$keys = array_keys($atifIndexes);
	$i = 0;
	if (sizeof($atifIndexes)) {
	foreach($atifIndexes as $atifIndex) {
		$atEntries[$i]["atifIndex"] = $atifIndex;
		$atEntries[$i]["atPhysAddress"] = $atPhysAddress[$keys[$i]];
		$atEntries[$i]["atNetAddress"] = xform_net_address($atNetAddress[$keys[$i]]);
		$i++;
	}
	}
	mactrack_debug("atEntries assembly complete.");

	/* output details to database */
	if (sizeof($atEntries)) {
	foreach($atEntries as $atEntry) {
		$insert_string = "REPLACE INTO mac_track_ips " .
			"(site_id,device_id,hostname,device_name,port_number," .
			"mac_address,ip_address,scan_date)" .
			" VALUES ('" .
			$device["site_id"] . "','" .
			$device["device_id"] . "','" .
			$device["hostname"] . "','" .
			$device["device_name"] . "','" .
			$atEntry["atifIndex"] . "','" .
			$atEntry["atPhysAddress"] . "','" .
			$atEntry["atNetAddress"] . "','" .
			$scan_date . "')";
		mactrack_debug("SQL: " . $insert_string);
		db_execute($insert_string);
	}
	}

	/* save ip information for the device */
	$device["ips_total"] = sizeof($atEntries);
	db_execute("UPDATE mac_track_devices SET ips_total ='" . $device["ips_total"] . "' WHERE device_id='" . $device["device_id"] . "'");
	mactrack_debug("HOST: " . $device["hostname"] . ", IP address information collection complete");
}


/* mactrack_draw_actions_dropdown - draws a table the allows the user to select an action to perform
     on one or more data elements
   @arg $actions_array - an array that contains a list of possible actions. this array should
     be compatible with the form_dropdown() function */
function mactrack_draw_actions_dropdown($actions_array, $include_form_end = true) {
	global $config;
	?>
	<table align='center' width='100%'>
		<tr>
			<td width='1' valign='top'>
				<img src='<?php echo $config['url_path']; ?>images/arrow.gif' alt='' align='absmiddle'>&nbsp;
			</td>
			<td align='right'>
				Choose an action:
				<?php form_dropdown("drp_action",$actions_array,"","","1","","");?>
			</td>
			<td width='1' align='right'>
				<input type='submit' name='go' value='Go'>
			</td>
		</tr>
	</table>

	<input type='hidden' name='action' value='actions'>
	<?php
	if ($include_form_end) {
		print "</form>";
	}
}

/* mactrack_save_button - draws a (save|create) and cancel button at the bottom of
     an html edit form
   @arg $force_type - if specified, will force the 'action' button to be either
     'save' or 'create'. otherwise this field should be properly auto-detected */
function mactrack_save_button($cancel_action = "", $action = "save", $force_type = "", $key_field = "id") {
	global $config;

	$calt = "Cancel";

	if ((empty($force_type)) || ($cancel_action == "return")) {
		if ($action == "import") {			$sname = "import";
			$salt  = "Import";
		}elseif (empty($_GET[$key_field])) {
			$sname = "create";
			$salt  = "Create";
		}else{
			$sname = "save";
			$salt  = "Save";
		}

		if ($cancel_action == "return") {
			$calt   = "Return";
			$action = "save";
		}else{
			$calt   = "Cancel";
		}
	}elseif ($force_type == "save") {
		$sname = "save";
		$salt  = "Save";
	}elseif ($force_type == "create") {
		$sname = "create";
		$salt  = "Create";
	}elseif ($force_type == "import") {
		$sname = "import";
		$salt  = "Import";
	}
	?>
	<table align='center' width='100%' style='background-color: #ffffff; border: 1px solid #bbbbbb;'>
		<tr>
			<td bgcolor="#f5f5f5" align="right">
				<input type='hidden' name='action' value='<?php print $action;?>'>
				<input type='button' value='<?php print $calt;?>' onClick='window.location.assign("<?php print htmlspecialchars($_SERVER['HTTP_REFERER']);?>")' name='cancel'>
				<input type='submit' value='<?php print $salt;?>' name='<?php print $sname;?>'>
			</td>
		</tr>
	</table>
	</form>
	<?php
}

function mactrack_format_device_row($device) {	global $config, $colors, $mactrack_device_types;

	/* viewer level */
	$row = "<a href='" . htmlspecialchars($config['url_path'] . "plugins/mactrack/mactrack_interfaces.php?device_id=" . $device['device_id'] . "&issues=0&page=1") . "'><img src='" . $config['url_path'] . "plugins/mactrack/images/view_interfaces.gif' alt='' onMouseOver='style.cursor=\"pointer\"' title='View Interfaces' align='absmiddle' border='0'></a>";
	if ($device["host_id"] != 0) {
		$row .= "<a href='" . htmlspecialchars($config['url_path'] . "graph_view.php?action=preview&host_id=" . $device['host_id'] . "&page=1") . "'><img src='" . $config['url_path'] . "plugins/mactrack/images/view_graphs.gif' alt='' onMouseOver='style.cursor=\"pointer\"' title='View Graphs' align='absmiddle' border='0'></a>";
	}else{
		$row .= "<img src='" . $config['url_path'] . "plugins/mactrack/images/view_graphs_disabled.gif' alt='' title='Device Not in Cacti' align='absmiddle' border='0'/>";
	}

	/* admin level */
	if (mactrack_authorized(2121)) {
		if ($device["disabled"] == '') {
			$row .= "<img id='r_" . $device["device_id"] . "' src='" . $config['url_path'] . "plugins/mactrack/images/rescan_device.gif' alt='' onMouseOver='style.cursor=\"pointer\"' onClick='scan_device(" . $device["device_id"] . ")' title='Rescan Device' align='absmiddle' border='0'>";

			if ($device["host_id"] == 0) {
				$row .= "<img id='c_" . $device["device_id"] . "' src='" . $config['url_path'] . "plugins/mactrack/images/create_device.gif' alt='' onMouseOver='style.cursor=\"pointer\"' onClick='create_cacti(" . $device["device_id"] . ")' title='Create Cacti Device and Graphs' align='absmiddle' border='0'>";
			}else{
				$row .= "<img src='" . $config['url_path'] . "plugins/mactrack/images/view_none.gif' alt='' align='absmiddle' border='0'>";
			}
		}else{
			$row .= "<img src='" . $config['url_path'] . "plugins/mactrack/images/view_none.gif' alt='' align='absmiddle' border='0'>";
			$row .= "<img src='" . $config['url_path'] . "plugins/mactrack/images/view_none.gif' alt='' align='absmiddle' border='0'>";
		}

		$row .= "<a href='" . htmlspecialchars($config['url_path'] . "plugins/mactrack/mactrack_devices.php?action=edit&device_id=" . $device['device_id']) . "'><img src='" . $config['url_path'] . "plugins/mactrack/images/view_details.gif' alt='' onMouseOver='style.cursor=\"pointer\"' title='Edit Scanner Device' align='absmiddle' border='0'></a>";

		if ($device["disabled"] == '') {
			$row .= "<img id='e_" . $device["device_id"] . "' src='" . $config['url_path'] . "plugins/mactrack/images/disable_collection.png' onClick='disable_device(" . $device["device_id"] . ")' alt='' onMouseOver='style.cursor=\"pointer\"' title='Disable Scanning' align='absmiddle' border='0'>";
			$row .= "<img id='p_" . $device["device_id"] . "' src='" . $config['url_path'] . "plugins/mactrack/images/purge_device.png' onClick='purge_device(" . $device["device_id"] . ")' alt='' onMouseOver='style.cursor=\"pointer\"' title='Delete Device from Database' align='absmiddle' border='0'>";
		}else{
			$row .= "<img id='e_" . $device["device_id"] . "' src='" . $config['url_path'] . "plugins/mactrack/images/enable_collection.png' onClick='enable_device(" . $device["device_id"] . ")' alt='' onMouseOver='style.cursor=\"pointer\"' title='Disable Scanning' align='absmiddle' border='0'>";
			$row .= "<img id='p_" . $device["device_id"] . "' src='" . $config['url_path'] . "plugins/mactrack/images/purge_device.png' onClick='purge_device(" . $device["device_id"] . ")' alt='' onMouseOver='style.cursor=\"pointer\"' title='Delete Device from Database' align='absmiddle' border='0'>";
		}
	}

	print "<td style='width:120px;'>" . $row . "</td>";//, $device["device_id"]);
	form_selectable_cell("<a class='linkEditMain' href='mactrack_devices.php?action=edit&device_id=" . $device['device_id'] . "'>" . (strlen($_REQUEST['filter']) ? eregi_replace("(" . preg_quote($_REQUEST['filter']) . ")", "<span style='background-color: #F8D93D;'>\\1</span>", $device['device_name']) : $device['device_name']) . "</a>", $device["device_id"]);
	form_selectable_cell($device["site_name"], $device["device_id"]);
	form_selectable_cell(get_colored_device_status(($device["disabled"] == "on" ? true : false), $device["snmp_status"]), $device["device_id"]);
	form_selectable_cell((strlen($_REQUEST["filter"]) ? eregi_replace("(" . preg_quote($_REQUEST["filter"]) . ")", "<span style='background-color: #F8D93D;'>\\1</span>", $device["hostname"]) : $device["hostname"]), $device["device_id"]);
	form_selectable_cell($mactrack_device_types[$device["scan_type"]], $device["device_id"]);
	form_selectable_cell(($device["scan_type"] == "1" ? "N/A" : $device["ips_total"]), $device["device_id"]);
	form_selectable_cell(($device["scan_type"] == "3" ? "N/A" : $device["ports_total"]), $device["device_id"]);
	form_selectable_cell(($device["scan_type"] == "3" ? "N/A" : $device["ports_active"]), $device["device_id"]);
	form_selectable_cell(($device["scan_type"] == "3" ? "N/A" : $device["ports_trunk"]), $device["device_id"]);
	form_selectable_cell(($device["scan_type"] == "3" ? "N/A" : $device["macs_active"]), $device["device_id"]);
	form_selectable_cell(number_format($device["last_runduration"], 1), $device["device_id"]);
	form_checkbox_cell($device["device_name"], $device["device_id"]);
	form_end_row();

}

function mactrack_authorized($realm_id) {
	if ((db_fetch_assoc("SELECT user_auth_realm.realm_id
		FROM user_auth_realm
		WHERE user_auth_realm.user_id='" . $_SESSION["sess_user_id"] . "'
		AND user_auth_realm.realm_id='$realm_id'")) || (empty($realm_id))) {
		return TRUE;
	}else{
		return FALSE;
	}
}

function mactrack_tabs() {
	global $config;

	/* present a tabbed interface */
	$tabs_mactrack = array(
		"sites" => "Sites",
		"devices" => "Devices",
		"ips" => "IP Ranges",
		"macs" => "MAC Addresses",
		"interfaces" => "Interfaces");

	/* set the default tab */
	$current_tab = $_REQUEST["report"];

	if (!isset($config["base_path"])) {
		/* draw the tabs */
		print "<div class='tabs'>\n";

		if (sizeof($tabs_mactrack)) {
		foreach (array_keys($tabs_mactrack) as $tab_short_name) {
			if (!isset($config["base_path"])) {
				print "<div class='tabDefault'><a " . (($tab_short_name == $current_tab) ? "class='tabSelected'" : "class='tabDefault'") . " href='" . $config['url_path'] .
					"plugins/mactrack/mactrack_view_" . $tab_short_name . ".php?" .
					"report=" . $tab_short_name .
					"'>$tabs_mactrack[$tab_short_name]</a></div>\n";
			}
		}
		}
		print "</div>\n";
	}else{		/* draw the tabs */
		print "<table class='report' width='100%' cellspacing='0' cellpadding='3' align='center'><tr>\n";

		if (sizeof($tabs_mactrack)) {
		foreach (array_keys($tabs_mactrack) as $tab_short_name) {
			print "<td style='padding:3px 10px 2px 5px;background-color:" . (($tab_short_name == $current_tab) ? "silver;" : "#DFDFDF;") .
				"white-space:nowrap;'" .
				" nowrap width='1%'" .
				"' align='center' class='tab'>
				<span class='textHeader'><a href='" . $config['url_path'] .
				"plugins/mactrack/mactrack_view_" . $tab_short_name . ".php?" .
				"report=" . $tab_short_name .
				"'>$tabs_mactrack[$tab_short_name]</a></span>
			</td>\n
			<td width='1'></td>\n";
		}
		}
		print "<td></td><td></td>\n</tr></table>\n";
	}
}

function mactrack_view_header() {
	global $title, $colors, $config;

if (!isset($config["base_path"])) {
?>
<table align="center" width="100%" cellpadding=1 cellspacing=0 border=0>
	<tr class="rowHeader">
		<td>
			<table cellpadding=1 cellspacing=0 border=0 width="100%">
				<form name="form_mactrack_view_reports">
				<tr>
					<td style="padding: 3px;" colspan="10">
						<table width="100%" cellpadding="0" cellspacing="0">
							<tr>
								<td class="textHeaderDark"><strong><?php print $title;?></strong></td>
							</tr>
						</table>
					</td>
				</tr>
				</form>
<?php
}else{
?>
<table align="center" width="100%" cellpadding=1 cellspacing=0 border=0 bgcolor="#<?php print $colors["header"];?>">
	<tr>
		<td>
			<table cellpadding=1 cellspacing=0 border=0 bgcolor="#<?php print $colors["form_background_dark"];?>" width="100%">
				<form name="form_mactrack_view_reports">
				<tr>
					<td bgcolor="#<?php print $colors["header"];?>" style="padding: 3px;" colspan="10">
						<table width="100%" cellpadding="0" cellspacing="0">
							<tr>
								<td bgcolor="#<?php print $colors["header"];?>" class="textHeaderDark"><strong><?php print $title;?></strong></td>
							</tr>
						</table>
					</td>
				</tr>
				</form>
<?php
}
}

function mactrack_view_footer() {
?>
							</table>
						</td>
					</tr>
				</table>
<?php
}

function mactrack_check_changed($request, $session) {
	if ((isset($_REQUEST[$request])) && (isset($_SESSION[$session]))) {
		if ($_REQUEST[$request] != $_SESSION[$session]) {
			return 1;
		}
	}
}

function mactrack_get_vendor_name($mac) {
	$vendor_mac = substr($mac,0,8);

	$vendor_name = db_fetch_cell("SELECT vendor_name FROM mac_track_oui_database WHERE vendor_mac='$vendor_mac'");

	if (strlen($vendor_name)) {
		return $vendor_name;
	}else{
		return "Unknown";
	}
}

?>