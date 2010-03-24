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

/* register this functions scanning functions */
if (!isset($mactrack_scanning_functions)) { $mactrack_scanning_functions = array(); }
array_push($mactrack_scanning_functions, "get_generic_dot1q_switch_ports", "get_generic_switch_ports", "get_generic_wireless_ports");

if (!isset($mactrack_scanning_functions_ip)) { $mactrack_scanning_functions_ip = array(); }
array_push($mactrack_scanning_functions_ip, "get_standard_arp_table", "get_netscreen_arp_table");

function mactrack_debug($message) {
	global $debug, $config;
	include_once($config["base_path"] . "/lib/functions.php");

	if ($debug) {
		print("DEBUG: " . $message . "\n");
	}

	if (substr_count($message, "ERROR:")) {
		cacti_log($message, false, "MACTRACK");
	}
}

function mactrack_rebuild_scanning_funcs() {
	global $config;

	if (defined('CACTI_BASE_PATH')) {
		$config["base_path"] = CACTI_BASE_PATH;
	}

	db_execute("TRUNCATE TABLE mac_track_scanning_functions");

	include_once($config["base_path"] . "/plugins/mactrack/lib/mactrack_functions.php");
	include_once($config["base_path"] . "/plugins/mactrack/lib/mactrack_vendors.php");

	/* store the list of registered mactrack scanning functions */
	db_execute("REPLACE INTO mac_track_scanning_functions (scanning_function,type) VALUES ('Not Applicable - Router', '1')");
	if (isset($mactrack_scanning_functions)) {
	foreach($mactrack_scanning_functions as $scanning_function) {
		db_execute("REPLACE INTO mac_track_scanning_functions (scanning_function,type) VALUES ('" . $scanning_function . "', '1')");
	}
	}

	db_execute("REPLACE INTO mac_track_scanning_functions (scanning_function,type) VALUES ('Not Applicable - Switch/Hub', '2')");
	if (isset($mactrack_scanning_functions_ip)) {
	foreach($mactrack_scanning_functions_ip as $scanning_function) {
		db_execute("REPLACE INTO mac_track_scanning_functions (scanning_function,type) VALUES ('" . $scanning_function . "', '2')");
	}
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

/* valid_snmp_device - This function validates that the device is reachable via snmp.
  It first attempts	to utilize the default snmp readstring.  If it's not valid, it
  attempts to find the correct read string and then updates several system
  information variable. it returns the status	of the host (up=true, down=false)
 */
function valid_snmp_device(&$device) {
	global $config;
	include_once($config["base_path"] . "/plugins/mactrack/mactrack_actions.php");

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
					$device["snmp_username"], $device["snmp_password"],
					$device["snmp_auth_protocol"], $device["snmp_priv_passphrase"],
					$device["snmp_priv_protocol"], $device["snmp_context"],
					$device["snmp_port"], $device["snmp_timeout"], $device["snmp_retries"]);

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
		$snmp_options = db_fetch_assoc("SELECT * from mac_track_snmp_items WHERE snmp_id=" . $device["snmp_options"] . " ORDER BY sequence");

		if (sizeof($snmp_options)) {
		foreach($snmp_options as $snmp_option) {
			# update $device for later db update via db_update_device_status
			$device["snmp_readstring"] = $snmp_option["snmp_readstring"];
			$device["snmp_version"] = $snmp_option["snmp_version"];
			$device["snmp_username"] = $snmp_option["snmp_username"];
			$device["snmp_password"] = $snmp_option["snmp_password"];
			$device["snmp_auth_protocol"] = $snmp_option["snmp_auth_protocol"];
			$device["snmp_priv_passphrase"] = $snmp_option["snmp_priv_passphrase"];
			$device["snmp_priv_protocol"] = $snmp_option["snmp_priv_protocol"];
			$device["snmp_context"] = $snmp_option["snmp_context"];
			$device["snmp_port"] = $snmp_option["snmp_port"];
			$device["snmp_timeout"] = $snmp_option["snmp_timeout"];
			$device["snmp_retries"] = $snmp_option["snmp_retries"];

			$snmp_sysObjectID = @cacti_snmp_get($device["hostname"], $device["snmp_readstring"],
					".1.3.6.1.2.1.1.2.0", $device["snmp_version"],
					$device["snmp_username"], $device["snmp_password"],
					$device["snmp_auth_protocol"], $device["snmp_priv_passphrase"],
					$device["snmp_priv_protocol"], $device["snmp_context"],
					$device["snmp_port"], $device["snmp_timeout"],
					$device["snmp_retries"]);

			$snmp_sysObjectID = str_replace("enterprises", ".1.3.6.1.4.1", $snmp_sysObjectID);
			$snmp_sysObjectID = str_replace("OID: ", "", $snmp_sysObjectID);
			$snmp_sysObjectID = str_replace(".iso", ".1", $snmp_sysObjectID);

			if ((strlen($snmp_sysObjectID) > 0) &&
				(!substr_count($snmp_sysObjectID, "No Such Object")) &&
				(!substr_count($snmp_sysObjectID, "Error In"))) {
				$snmp_sysObjectID = trim(str_replace("\"", "", $snmp_sysObjectID));
				$device["snmp_readstring"] = $snmp_option["snmp_readstring"];
				$device["snmp_status"] = HOST_UP;
				$host_up = TRUE;
				# update cacti device, if required
				sync_mactrack_to_cacti($device);
				# update to mactrack itself is done by db_update_device_status in mactrack_scanner.php
				# TODO: if db_update_device_status would use api_mactrack_device_save, there would be no need to call sync_mactrack_to_cacti here
				# but currently the parameter set doesn't match
				mactrack_debug("Result found on Option Set (" . $snmp_option["snmp_id"] . ") Sequence (" . $snmp_option["sequence"] . "): " . $snmp_sysObjectID);
				break; # no need to continue if we have a match
			}else{
				$device["snmp_status"] = HOST_DOWN;
				$host_up = FALSE;
			}
		}
		}
	}

	if ($host_up) {
		$device["snmp_sysObjectID"] = $snmp_sysObjectID;

		/* get system name */
		$snmp_sysName = @cacti_snmp_get($device["hostname"], $device["snmp_readstring"],
					".1.3.6.1.2.1.1.5.0", $device["snmp_version"],
					$device["snmp_username"], $device["snmp_password"],
					$device["snmp_auth_protocol"], $device["snmp_priv_passphrase"],
					$device["snmp_priv_protocol"], $device["snmp_context"],
					$device["snmp_port"], $device["snmp_timeout"], $device["snmp_retries"]);

		if (strlen($snmp_sysName) > 0) {
			$snmp_sysName = trim(strtr($snmp_sysName,"\""," "));
			$device["snmp_sysName"] = $snmp_sysName;
		}

		/* get system location */
		$snmp_sysLocation = @cacti_snmp_get($device["hostname"], $device["snmp_readstring"],
					".1.3.6.1.2.1.1.6.0", $device["snmp_version"],
					$device["snmp_username"], $device["snmp_password"],
					$device["snmp_auth_protocol"], $device["snmp_priv_passphrase"],
					$device["snmp_priv_protocol"], $device["snmp_context"],
					$device["snmp_port"], $device["snmp_timeout"], $device["snmp_retries"]);

		if (strlen($snmp_sysLocation) > 0) {
			$snmp_sysLocation = trim(strtr($snmp_sysLocation,"\""," "));
			$device["snmp_sysLocation"] = $snmp_sysLocation;
		}

		/* get system contact */
		$snmp_sysContact = @cacti_snmp_get($device["hostname"], $device["snmp_readstring"],
					".1.3.6.1.2.1.1.4.0", $device["snmp_version"],
					$device["snmp_username"], $device["snmp_password"],
					$device["snmp_auth_protocol"], $device["snmp_priv_passphrase"],
					$device["snmp_priv_protocol"], $device["snmp_context"],
					$device["snmp_port"], $device["snmp_timeout"], $device["snmp_retries"]);

		if (strlen($snmp_sysContact) > 0) {
			$snmp_sysContact = trim(strtr($snmp_sysContact,"\""," "));
			$device["snmp_sysContact"] = $snmp_sysContact;
		}

		/* get system description */
		$snmp_sysDescr = @cacti_snmp_get($device["hostname"], $device["snmp_readstring"],
					".1.3.6.1.2.1.1.1.0", $device["snmp_version"],
					$device["snmp_username"], $device["snmp_password"],
					$device["snmp_auth_protocol"], $device["snmp_priv_passphrase"],
					$device["snmp_priv_protocol"], $device["snmp_context"],
					$device["snmp_port"], $device["snmp_timeout"], $device["snmp_retries"]);

		if (strlen($snmp_sysDescr) > 0) {
			$snmp_sysDescr = trim(strtr($snmp_sysDescr,"\""," "));
			$device["snmp_sysDescr"] = $snmp_sysDescr;
		}

		/* get system uptime */
		$snmp_sysUptime = @cacti_snmp_get($device["hostname"], $device["snmp_readstring"],
					".1.3.6.1.2.1.1.3.0", $device["snmp_version"],
					$device["snmp_username"], $device["snmp_password"],
					$device["snmp_auth_protocol"], $device["snmp_priv_passphrase"],
					$device["snmp_priv_protocol"], $device["snmp_context"],
					$device["snmp_port"], $device["snmp_timeout"], $device["snmp_retries"]);

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
	$atifIndexes = xform_stripped_oid(".1.3.6.1.2.1.3.1.1.1", $device);
	$atEntries   = array();

	if (sizeof($atifIndexes)) {
		mactrack_debug("atifIndexes data collection complete");
		$atPhysAddress = xform_stripped_oid(".1.3.6.1.2.1.3.1.1.2", $device);
		mactrack_debug("atPhysAddress data collection complete");
		$atNetAddress  = xform_stripped_oid(".1.3.6.1.2.1.3.1.1.3", $device);
		mactrack_debug("atNetAddress data collection complete");
	}else{
		/* second attempt for Force10 Gear */
		$atifIndexes   = xform_stripped_oid(".1.3.6.1.2.1.4.22.1.1", $device);
		mactrack_debug("atifIndexes data collection complete");
		$atPhysAddress = xform_stripped_oid(".1.3.6.1.2.1.4.22.1.2", $device);
		mactrack_debug("atPhysAddress data collection complete");
		$atNetAddress = xform_stripped_oid(".1.3.6.1.2.1.4.22.1.3", $device);
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

	/* get the ifIndexes for the device */
	$ifIndexes = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.1", $device);
	mactrack_debug("ifIndexes data collection complete. '" . sizeof($ifIndexes) . "' rows found!");

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

	/* mark all interfaces as not present */
	db_execute("UPDATE mac_track_interfaces SET present=0 WHERE device_id=" . $device["device_id"]);

	$insert_prefix = "INSERT INTO mac_track_interfaces (site_id, device_id, sysUptime, ifIndex, ifType, ifName, ifAlias, linkPort, vlan_id," .
		" vlan_name, vlan_trunk_status, ifSpeed, ifHighSpeed, ifDuplex, " .
		" ifDescr, ifMtu, ifPhysAddress, ifAdminStatus, ifOperStatus, ifLastChange, ".
		" ifInOctets, ifOutOctets, ifHCInOctets, ifHCOutOctets, ifInNUcastPkts, ifOutNUcastPkts, ifInUcastPkts, ifOutUcastPkts, " .
		" ifInDiscards, ifInErrors, ifInUnknownProtos, ifOutDiscards, ifOutErrors, " .
		" int_ifInOctets, int_ifOutOctets, int_ifHCInOctets, int_ifHCOutOctets, int_ifInNUcastPkts, int_ifOutNUcastPkts, int_ifInUcastPkts, int_ifOutUcastPkts, " .
		" int_ifInDiscards, int_ifInErrors, int_ifInUnknownProtos, int_ifOutDiscards, int_ifOutErrors, int_discards_present, int_errors_present, " .
		" last_down_time, last_up_time, stateChanges, present) VALUES ";

	$insert_suffix = " ON DUPLICATE KEY UPDATE sysUptime=VALUES(sysUptime), ifType=VALUES(ifType), ifName=VALUES(ifName), ifAlias=VALUES(ifAlias), linkPort=VALUES(linkPort)," .
		" vlan_id=VALUES(vlan_id), vlan_name=VALUES(vlan_name), vlan_trunk_status=VALUES(vlan_trunk_status)," .
		" ifSpeed=VALUES(ifSpeed), ifHighSpeed=VALUES(ifHighSpeed), ifDuplex=VALUES(ifDuplex), ifDescr=VALUES(ifDescr), ifMtu=VALUES(ifMtu), ifPhysAddress=VALUES(ifPhysAddress), ifAdminStatus=VALUES(ifAdminStatus)," .
		" ifOperStatus=VALUES(ifOperStatus), ifLastChange=VALUES(ifLastChange), " .
		" ifInOctets=VALUES(ifInOctets), ifOutOctets=VALUES(ifOutOctets), ifHCInOctets=VALUES(ifHCInOctets), ifHCOutOctets=VALUES(ifHCOutOctets), " .
		" ifInNUcastPkts=VALUES(ifInNUcastPkts), ifOutNUcastPkts=VALUES(ifOutNUcastPkts), ifInUcastPkts=VALUES(ifInUcastPkts), ifOutUcastPkts=VALUES(ifOutUcastPkts), " .
		" ifInDiscards=VALUES(ifInDiscards), ifInErrors=VALUES(ifInErrors)," .
		" ifInUnknownProtos=VALUES(ifInUnknownProtos), ifOutDiscards=VALUES(ifOutDiscards), ifOutErrors=VALUES(ifOutErrors)," .
		" int_ifInOctets=VALUES(int_ifInOctets), int_ifOutOctets=VALUES(int_ifOutOctets), int_ifHCInOctets=VALUES(int_ifHCInOctets), int_ifHCOutOctets=VALUES(int_ifHCOutOctets), " .
		" int_ifInNUcastPkts=VALUES(int_ifInNUcastPkts), int_ifOutNUcastPkts=VALUES(int_ifOutNUcastPkts), int_ifInUcastPkts=VALUES(int_ifInUcastPkts), int_ifOutUcastPkts=VALUES(int_ifOutUcastPkts), " .
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

	$ifDuplex = xform_standard_indexed_data(".1.3.6.1.2.1.10.7.2.1.19", $device);
	mactrack_debug("ifDuplex data collection complete. '" . sizeof($ifDuplex) . "' rows found!");

	$ifDescr = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.2", $device);
	mactrack_debug("ifDescr data collection complete. '" . sizeof($ifDescr) . "' rows found!");

	$ifMtu = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.4", $device);
	mactrack_debug("ifMtu data collection complete. '" . sizeof($ifMtu) . "' rows found!");

	$ifPhysAddress = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.6", $device);
	mactrack_debug("ifPhysAddress data collection complete. '" . sizeof($ifPhysAddress) . "' rows found!");

	$ifAdminStatus = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.7", $device);
	if (sizeof($ifAdminStatus)) {
	foreach($ifAdminStatus as $key => $value) {
		if ((substr_count(strtolower($value), "up")) || ($value == "1")) {
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
		if ((substr_count(strtolower($value), "up")) || ($value == "1")) {
			$ifOperStatus[$key] = 1;
		}else{
			$ifOperStatus[$key] = 0;
		}
	}
	}
	mactrack_debug("ifOperStatus data collection complete. '" . sizeof($ifOperStatus) . "' rows found!");

	$ifLastChange = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.9", $device);
	mactrack_debug("ifLastChange data collection complete. '" . sizeof($ifLastChange) . "' rows found!");

	/* get timing for rate information */
	$prev_octets_time = strtotime($device["last_rundate"]);
	$cur_octets_time  = time();

	if ($prev_octets_time == 0) {
		$divisor = FALSE;
	}else{
		$divisor = $cur_octets_time - $prev_octets_time;
	}

	/* if the device is snmpv2 use high speed and don't bother with the low speed stuff */
	$ifInOctets = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.10", $device);
	mactrack_debug("ifInOctets data collection complete. '" . sizeof($ifInOctets) . "' rows found!");

	$ifOutOctets = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.16", $device);
	mactrack_debug("ifOutOctets data collection complete. '" . sizeof($ifOutOctets) . "' rows found!");

	if ($device["snmp_version"] > 1) {
		$ifHCInOctets = xform_standard_indexed_data(".1.3.6.1.2.1.31.1.1.1.6", $device);
		mactrack_debug("ifHCInOctets data collection complete. '" . sizeof($ifHCInOctets) . "' rows found!");

		$ifHCOutOctets = xform_standard_indexed_data(".1.3.6.1.2.1.31.1.1.1.10", $device);
		mactrack_debug("ifHCOutOctets data collection complete. '" . sizeof($ifHCOutOctets) . "' rows found!");
	}

	$ifInNUcastPkts = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.12", $device);
	mactrack_debug("ifInNUcastPkts data collection complete. '" . sizeof($ifInNUcastPkts) . "' rows found!");

	$ifOutNUcastPkts = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.18", $device);
	mactrack_debug("ifOutNUcastPkts data collection complete. '" . sizeof($ifOutNUcastPkts) . "' rows found!");

	$ifInUcastPkts = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.11", $device);
	mactrack_debug("ifInUcastPkts data collection complete. '" . sizeof($ifInUcastPkts) . "' rows found!");

	$ifOutUcastPkts = xform_standard_indexed_data(".1.3.6.1.2.1.2.2.1.17", $device);
	mactrack_debug("ifOutUcastPkts data collection complete. '" . sizeof($ifOutUcastPkts) . "' rows found!");

	/* get information on error conditions */
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

	$vlan_id    = "";
	$vlan_name  = "";
	$vlan_trunk = "";

	$i = 0;
	foreach($ifIndexes as $ifIndex) {
		$ifInterfaces[$ifIndex]["ifIndex"] = $ifIndex;
		$ifInterfaces[$ifIndex]["ifName"] = @$ifNames[$ifIndex];
		$ifInterfaces[$ifIndex]["ifType"] = @$ifTypes[$ifIndex];

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

			if ($db_interface[$ifIndex]["ifOperStatus"] == 0) { /* interface previously not up */
				if ($ifOperStatus[$ifIndex] == 1) {
					/* the interface just went up, mark the time */
					$last_up_time = date("Y-m-d H:i:s");
					$stateChanges += 1;

					/* if the interface has never been marked down before, make it the current time */
					if ($db_interface[$ifIndex]["last_down_time"] == '0000-00-00 00:00:00') {
						$last_down_time = $last_up_time;
					}
				}else{
					/* if the interface has never been down, make the current time */
					$last_down_time = date("Y-m-d H:i:s");

					/* if the interface stayed down, set the last up time if not set before */
					if ($db_interface[$ifIndex]["last_up_time"] == '0000-00-00 00:00:00') {
						$last_up_time = date("Y-m-d H:i:s");
					}
				}
			}else{
				if ($ifOperStatus[$ifIndex] == 0) {
					/* the interface just went down, mark the time */
					$last_down_time = date("Y-m-d H:i:s");
					$stateChanges += 1;

					/* if the interface has never been up before, mark it the current time */
					if ($db_interface[$ifIndex]["last_up_time"] == '0000-00-00 00:00:00') {
						$last_up_time = date("Y-m-d H:i:s");
					}
				}else{
					$last_up_time = date("Y-m-d H:i:s");

					if ($db_interface[$ifIndex]["last_down_time"] == '0000-00-00 00:00:00') {
						$last_down_time = date("Y-m-d H:i:s");
					}
				}
			}
		}

		/* do the in octets */
		$int_ifInOctets = get_link_int_value("ifInOctets", $ifIndex, $ifInOctets, $db_interface, $divisor, "traffic");

		/* do the out octets */
		$int_ifOutOctets = get_link_int_value("ifOutOctets", $ifIndex, $ifOutOctets, $db_interface, $divisor, "traffic");

		if ($device["snmp_version"] > 1) {
			/* do the in octets */
			$int_ifHCInOctets = get_link_int_value("ifHCInOctets", $ifIndex, $ifHCInOctets, $db_interface, $divisor, "traffic", "64");

			/* do the out octets */
			$int_ifHCOutOctets = get_link_int_value("ifHCOutOctets", $ifIndex, $ifHCOutOctets, $db_interface, $divisor, "traffic", "64");
		}

		/* accomodate values in high speed octets for interfaces that don't support 64 bit */
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

		$int_ifInNUcastPkts  = get_link_int_value("ifInNUcastPkts", $ifIndex, $ifInNUcastPkts, $db_interface, $divisor, "traffic");

		$int_ifOutNUcastPkts = get_link_int_value("ifOutNUcastPkts", $ifIndex, $ifOutNUcastPkts, $db_interface, $divisor, "traffic");

		$int_ifInUcastPkts   = get_link_int_value("ifInUcastPkts", $ifIndex, $ifInUcastPkts, $db_interface, $divisor, "traffic");

		$int_ifOutUcastPkts  = get_link_int_value("ifOutUcastPkts", $ifIndex, $ifOutUcastPkts, $db_interface, $divisor, "traffic");

		/* see if in error's have been increasing */
		$int_ifInErrors      = get_link_int_value("ifInErrors", $ifIndex, $ifInErrors, $db_interface, $divisor, "errors");

		/* see if out error's have been increasing */
		$int_ifOutErrors     = get_link_int_value("ifOutErrors", $ifIndex, $ifOutErrors, $db_interface, $divisor, "errors");

		if ($int_ifInErrors > 0 || $int_ifOutErrors > 0) {
			$int_errors_present = TRUE;
		}else{
			$int_errors_present = FALSE;
		}

		/* see if in discards's have been increasing */
		$int_ifInDiscards    = get_link_int_value("ifInDiscards", $ifIndex, $ifInDiscards, $db_interface, $divisor, "errors");

		/* see if out discards's have been increasing */
		$int_ifOutDiscards   = get_link_int_value("ifOutDiscards", $ifIndex, $ifOutDiscards, $db_interface, $divisor, "errors");

		if ($int_ifInDiscards > 0 || $int_ifOutDiscards > 0) {
			$int_discards_present = TRUE;
		}else{
			$int_discards_present = FALSE;
		}

		/* see if in discards's have been increasing */
		$int_ifInUnknownProtos = get_link_int_value("ifInUnknownProtos", $ifIndex, $ifInUnknownProtos, $db_interface, $divisor, "errors");

		/* format the update packet */
		if ($i == 0) {
			$insert_vals .= " ";
		}else{
			$insert_vals .= ",";
		}

		$mac_address = @xform_mac_address($ifPhysAddress[$ifIndex]);

		$insert_vals .= "('" .
			@$device["site_id"]                 . "', '" . @$device["device_id"]         . "', '" .
			@$device["sysUptime"]               . "', '" . @$ifIndex                     . "', '" .
			@$ifTypes[$ifIndex]                 . "', '" . @$ifNames[$ifIndex]           . "', "  .
			@$cnn_id->qstr($ifAlias)            . ", '"  . @$linkPort                    . "', '" .
			@$vlan_id                           . "', "  . @$cnn_id->qstr(@$vlan_name)   . ", '"  .
			@$vlan_trunk                        . "', '" . @$ifSpeed[$ifIndex]           . "', '" .
			@$ifHighSpeed[$ifIndex]             . "', '" . @$ifDuplex[$ifIndex]          . "', "   .
			@$cnn_id->qstr(@$ifDescr[$ifIndex]) . ", '"  . @$ifMtu[$ifIndex]             . "', '" .
			$mac_address                        . "', '" . @$ifAdminStatus[$ifIndex]     . "', '" .
			@$ifOperStatus[$ifIndex]            . "', '" . @$ifLastChange[$ifIndex]      . "', '" .
			@$ifInOctets[$ifIndex]              . "', '" . @$ifOutOctets[$ifIndex]       . "', '" .
			@$ifHCInOctets[$ifIndex]            . "', '" . @$ifHCOutOctets[$ifIndex]     . "', '" .
			@$ifInNUcastPkts[$ifIndex]          . "', '" . @$ifOutNUcastPkts[$ifIndex]   . "', '" .
			@$ifInUcastPkts[$ifIndex]           . "', '" . @$ifOutUcastPkts[$ifIndex]    . "', '" .
			@$ifInDiscards[$ifIndex]            . "', '" . @$ifInErrors[$ifIndex]        . "', '" .
			@$ifInUnknownProtos[$ifIndex]       . "', '" . @$ifOutDiscards[$ifIndex]     . "', '" .
			@$ifOutErrors[$ifIndex]             . "', '" .
			@$int_ifInOctets                    . "', '" . @$int_ifOutOctets             . "', '" .
			@$int_ifHCInOctets                  . "', '" . @$int_ifHCOutOctets           . "', '" .
			@$int_ifInNUcastPkts                . "', '" . @$int_ifOutNUcastPkts         . "', '" .
			@$int_ifInUcastPkts                 . "', '" . @$int_ifOutUcastPkts          . "', '" .
			@$int_ifInDiscards                  . "', '" . @$int_ifInErrors              . "', '" .
			@$int_ifInUnknownProtos             . "', '" . @$int_ifOutDiscards           . "', '" .
			@$int_ifOutErrors                   . "', '" . @$int_discards_present        . "', '" .
			$int_errors_present                 . "', '" .  $last_down_time              . "', '" .
			$last_up_time                       . "', '" .  $stateChanges                . "', '" . "1')";

		$i++;
	}
	mactrack_debug("ifInterfaces assembly complete: " . strlen($insert_prefix . $insert_vals . $insert_suffix));

	if (strlen($insert_vals)) {
		/* add/update records in the database */
		db_execute($insert_prefix . $insert_vals . $insert_suffix);

		/* remove all obsolete records from the database */
		db_execute("DELETE FROM mac_track_interfaces WHERE present=0 AND device_id=" . $device["device_id"]);

		/* set the percent utilized fields, you can't do this for vlans */
		db_execute("UPDATE mac_track_interfaces
			SET inBound=(int_ifHCInOctets*8)/(ifHighSpeed*10000), outBound=(int_ifHCOutOctets*8)/(ifHighSpeed*10000)
			WHERE ifHighSpeed>0 AND ifName NOT LIKE 'Vl%' AND device_id=" . $device["device_id"]);

		mactrack_debug("Adding IfInterfaces Records");
	}

	return $ifInterfaces;
}

function get_link_int_value($snmp_oid, $ifIndex, &$snmp_array, &$db_interface, $divisor, $type = "errors", $bits = "32") {
	/* 32bit and 64bit Integer Overflow Value */
	if ($bits == "32") {
		$overflow   = 4294967295;
		/* fudge factor */
		$fudge      = 3000000001;
	}else{
		$overflow = 18446744065119617025;
		/* fudge factor */
		$fudge      = 300000000001;
	}

	/* see if values have been increasing */
	$int_value = 0;
	if (!isset($db_interface[$ifIndex][$snmp_oid])) {
		$int_value = 0;
	}else if (!isset($snmp_array[$ifIndex])) {
		$int_value = 0;
	}else if ($snmp_array[$ifIndex] <> $db_interface[$ifIndex][$snmp_oid]) {
		/* account for 2E32 rollover */
		/* there are two types of rollovers one rolls to 0 */
		/* the other counts backwards.  let's make an educated guess */
		if ($db_interface[$ifIndex][$snmp_oid] > $snmp_array[$ifIndex]) {
			/* errors count backwards from overflow */
			if ($type == "errors") {
				if (($overflow - $db_interface[$ifIndex][$snmp_oid] + $snmp_array[$ifIndex]) < $fudge) {
					$int_value = $overflow - $db_interface[$ifIndex][$snmp_oid] + $snmp_array[$ifIndex];
				}else{
					$int_value = $db_interface[$ifIndex][$snmp_oid] - $snmp_array[$ifIndex];
				}
			}else{
				$int_value = $overflow - $db_interface[$ifIndex][$snmp_oid] + $snmp_array[$ifIndex];
			}
		}else{
			$int_value = $snmp_array[$ifIndex] - $db_interface[$ifIndex][$snmp_oid];
		}

		/* account for counter resets */
		$frequency = read_config_option("mt_collection_timing") * 60;
		if ($db_interface[$ifIndex]["ifHighSpeed"] > 0) {
			if ($int_value > ($db_interface[$ifIndex]["ifHighSpeed"] * 1000000 * $frequency * 1.1)) {
				$int_value = $snmp_array[$ifIndex];
			}
		}else{
			if ($int_value > ($db_interface[$ifIndex]["ifSpeed"] * $frequency * 1.1 / 8)) {
				$int_value = $snmp_array[$ifIndex];
			}
		}
	}else{
		$int_value = 0;
	}

	if (!$divisor) {
		return 0;
	}else{
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
		if (((@$ifInterfaces[$indexes[$i]]["ifType"] >= 6) &&
			(@$ifInterfaces[$indexes[$i]]["ifType"] <= 9)) ||
			(@$ifInterfaces[$indexes[$i]]["ifType"] == 71)) {
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
					$new_port_key_array[$i]["vlan_id"]     = "N/A";
					$new_port_key_array[$i]["vlan_name"]   = "N/A";
					$new_port_key_array[$i]["mac_address"] = "NOT USER";
					$new_port_key_array[$i]["port_number"] = "NOT USER";
					$new_port_key_array[$i]["port_name"]   = "N/A";

					/* now set the real data */
					$new_port_key_array[$i]["key"]         = $port_key["key"];
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
					$device["snmp_username"], $device["snmp_password"],
					$device["snmp_auth_protocol"], $device["snmp_priv_passphrase"],
					$device["snmp_priv_protocol"], $device["snmp_context"],
					$device["snmp_port"], $device["snmp_timeout"], $device["snmp_retries"]);

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
						$port_key_array[$i]["key"]         = $key;
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
					$new_port_key_array[$i]["vlan_id"]     = "N/A";
					$new_port_key_array[$i]["vlan_name"]   = "N/A";
					$new_port_key_array[$i]["mac_address"] = "NOT USER";
					$new_port_key_array[$i]["port_number"] = "NOT USER";
					$new_port_key_array[$i]["port_name"]   = "N/A";

					/* now set the real data */
					$new_port_key_array[$i]["key"]         = $port_key["key"];
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
					$device["snmp_username"], $device["snmp_password"],
					$device["snmp_auth_protocol"], $device["snmp_priv_passphrase"],
					$device["snmp_priv_protocol"], $device["snmp_context"],
					$device["snmp_port"], $device["snmp_timeout"], $device["snmp_retries"]);

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
						$port_key_array[$i]["key"]         = $key;
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
					$new_port_key_array[$i]["vlan_id"]     = "N/A";
					$new_port_key_array[$i]["vlan_name"]   = "N/A";
					$new_port_key_array[$i]["mac_address"] = "NOT USER";
					$new_port_key_array[$i]["port_number"] = "NOT USER";
					$new_port_key_array[$i]["port_name"]   = "N/A";

					/* now set the real data */
					$new_port_key_array[$i]["key"]         = $port_key["key"];
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
					".1.3.6.1.2.1.4.20.1.2", $device["snmp_version"], $device["snmp_username"],
					$device["snmp_password"], $device["snmp_auth_protocol"],
					$device["snmp_priv_passphrase"], $device["snmp_priv_protocol"],
					$device["snmp_context"], $device["snmp_port"], $device["snmp_timeout"],
					$device["snmp_retries"], $device["max_oids"]);

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
					$OID, $device["snmp_version"], $device["snmp_username"],
					$device["snmp_password"], $device["snmp_auth_protocol"],
					$device["snmp_priv_passphrase"], $device["snmp_priv_protocol"],
					$device["snmp_context"], $device["snmp_port"], $device["snmp_timeout"],
					$device["snmp_retries"], $device["max_oids"]);

	$OID = preg_replace("/^\./", "", $OID);

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
					$xformOID, $device["snmp_version"], $device["snmp_username"],
					$device["snmp_password"], $device["snmp_auth_protocol"],
					$device["snmp_priv_passphrase"], $device["snmp_priv_protocol"],
					$device["snmp_context"], $device["snmp_port"], $device["snmp_timeout"],
					$device["snmp_retries"], $device["max_oids"]);

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
					$device["snmp_username"], $device["snmp_password"],
					$device["snmp_auth_protocol"], $device["snmp_priv_passphrase"],
					$device["snmp_priv_protocol"], $device["snmp_context"],
					$device["snmp_port"], $device["snmp_timeout"],
					$device["snmp_retries"], $device["max_oids"]);

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
							$xformOID, $device["snmp_version"], $device["snmp_username"],
							$device["snmp_password"], $device["snmp_auth_protocol"],
							$device["snmp_priv_passphrase"], $device["snmp_priv_protocol"],
							$device["snmp_context"], $device["snmp_port"],
							$device["snmp_timeout"], $device["snmp_retries"], $device["max_oids"]);

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
						$xformOID, $device["snmp_version"], $device["snmp_username"],
						$device["snmp_password"], $device["snmp_auth_protocol"],
						$device["snmp_priv_passphrase"], $device["snmp_priv_protocol"],
						$device["snmp_context"], $device["snmp_port"],
						$device["snmp_timeout"], $device["snmp_retries"], $device["max_oids"]);

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

	list($micro,$seconds) = explode(" ", microtime());
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
			"snmp_version='" . $device["snmp_version"] . "'," .
			"snmp_readstring='" . $device["snmp_readstring"] . "'," .
			"snmp_port='" . $device["snmp_port"] . "'," .
			"snmp_timeout='" . $device["snmp_timeout"] . "'," .
			"snmp_retries='" . $device["snmp_retries"] . "'," .
			"max_oids='" . $device["max_oids"] . "'," .
			"snmp_username='" . $device["snmp_username"] . "'," .
			"snmp_password='" . $device["snmp_password"] . "'," .
			"snmp_auth_protocol='" . $device["snmp_auth_protocol"] . "'," .
			"snmp_priv_passphrase='" . $device["snmp_priv_passphrase"] . "'," .
			"snmp_priv_protocol='" . $device["snmp_priv_protocol"] . "'," .
			"snmp_context='" . $device["snmp_context"] . "'," .
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

function mactrack_format_interface_row($stat) {
	global $colors, $config;

	/* we will make a row string */
	$row = "";

	/* calculate a human readable uptime */
	if ($stat["ifLastChange"] == 0) {
		$upTime = "Since Restart";
	}else{
		if ($stat["ifLastChange"] > $stat["sysUptime"]) {
			$upTime = "Since Restart";
		}else{
			$time = $stat["sysUptime"] - $stat["ifLastChange"];
			$days      = intval($time / (60*60*24*100));
			$remainder = $time % (60*60*24*100);
			$hours     = intval($remainder / (60*60*100));
			$remainder = $remainder % (60*60*100);
			$minutes   = intval($remainder / (60*100));
			$upTime    = $days . "d:" . $hours . "h:" . $minutes . "m";
		}
	}

	if (mactrack_authorized(2021)) {
		if ($stat["disabled"] == '') {
			$rescan = "<img id='r_" . $stat["device_id"] . "_" . $stat["interface_id"] . "' src='" . $config['url_path'] . "plugins/mactrack/images/rescan_device.gif' alt='' onMouseOver='style.cursor=\"pointer\"' onClick='scan_device_interface(\"" . $stat["device_id"] . "_" . $stat["interface_id"] . "\")' title='Rescan Device' align='middle' border='0'>";

		}else{
			$rescan = "<img src='" . $config['url_path'] . "plugins/mactrack/images/view_none.gif' alt='' align='middle' border='0'>";
		}
	}else{
		$rescan = "<img src='" . $config['url_path'] . "plugins/mactrack/images/view_none.gif' alt='' align='middle' border='0'>";
	}

	$row .= "<td nowrap style='width:1%;white-space:nowrap;'>";
	$row .= $rescan;
	$row .= "</td>";

	$row .= "<td><b>" . $stat["device_name"]                     . "</b></td>";
	$row .= "<td>" . strtoupper($stat["device_type"])            . "</td>";
	$row .= "<td><b>" . $stat["ifName"]                          . "</b></td>";
	$row .= "<td>" . $stat["ifDescr"]                            . "</td>";
	$row .= "<td>" . $stat["ifAlias"]                            . "</td>";
	$row .= "<td>" . round($stat["inBound"],1) . " %"            . "</td>";
	$row .= "<td>" . round($stat["outBound"],1) . " %"           . "</td>";
	$row .= "<td>" . mactrack_display_Octets($stat["int_ifHCInOctets"])  . "</td>";
	$row .= "<td>" . mactrack_display_Octets($stat["int_ifHCOutOctets"]) . "</td>";
	if ($_REQUEST["totals"] == "true" || $_REQUEST["totals"] == "on") {
		$row .= "<td>" . $stat["ifInErrors"]                     . "</td>";
		$row .= "<td>" . $stat["ifInDiscards"]                   . "</td>";
		$row .= "<td>" . $stat["ifInUnknownProtos"]              . "</td>";
		$row .= "<td>" . $stat["ifOutErrors"]                    . "</td>";
		$row .= "<td>" . $stat["ifOutDiscards"]                  . "</td>";
	}else{
		$row .= "<td>" . round($stat["int_ifInErrors"],1)        . "</td>";
		$row .= "<td>" . round($stat["int_ifInDiscards"],1)      . "</td>";
		$row .= "<td>" . round($stat["int_ifInUnknownProtos"],1) . "</td>";
		$row .= "<td>" . round($stat["int_ifOutErrors"],1)       . "</td>";
		$row .= "<td>" . round($stat["int_ifOutDiscards"],1)     . "</td>";
	}
	$row .= "<td>" . ($stat["ifOperStatus"] == 1 ? "Up":"Down") . "</td>";
	$row .= "<td style='white-space:nowrap;'>" . $upTime        . "</td>";
	$row .= "<td style='white-space:nowrap;'>" . mactrack_date($stat["last_rundate"])        . "</td>";
	return $row;
}

function mactrack_display_Octets($octets) {
	$suffix = "";
	while ($octets > 1024) {
		$octets = $octets / 1024;
		switch($suffix) {
		case "":
			$suffix = "k";
			break;
		case "k":
			$suffix = "m";
			break;
		case "M":
			$suffix = "G";
			break;
		case "G":
			$suffix = "P";
			break 2;
		default:
			$suffix = "";
			break 2;
		}
	}

	$octets = round($octets,4);
	$octets = substr($octets,0,5);

	return $octets . " " . $suffix;
}

function mactrack_date($date) {
	$year = date("Y");
	return (substr_count($date, $year) ? substr($date,5) : $date);
}

function mactrack_int_row_color($stat) {
	global $colors;

	$bgc = 0;
	if ($stat["int_errors_present"] == "1") {
		$bgc = db_fetch_cell("SELECT hex FROM colors WHERE id='" . read_config_option("mt_int_errors_bgc") . "'");
	} elseif ($stat["int_discards_present"] == "1") {
		$bgc = db_fetch_cell("SELECT hex FROM colors WHERE id='" . read_config_option("mt_int_discards_bgc") . "'");
	} elseif ($stat["ifOperStatus"] == "1" && $stat["ifAlias"] == "") {
		$bgc = db_fetch_cell("SELECT hex FROM colors WHERE id='" . read_config_option("mt_int_up_wo_alias_bgc") . "'");
	} elseif ($stat["ifOperStatus"] == "0") {
		$bgc = db_fetch_cell("SELECT hex FROM colors WHERE id='" . read_config_option("mt_int_down_bgc") . "'");
	} else {
		$bgc = db_fetch_cell("SELECT hex FROM colors WHERE id='" . read_config_option("mt_int_up_bgc") . "'");
	}

	return $bgc;
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
				<img src='<?php echo $config['url_path']; ?>images/arrow.gif' alt='' align='middle'>&nbsp;
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
		if ($action == "import") {
			$sname = "import";
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

/* mactrack_create_sql_filter - this routine will take a filter string and process it into a
     sql where clause that will be returned to the caller with a formated SQL where clause
     that can then be integrated into the overall where clause.
     The filter takes the following forms.  The default is to find occurance that match "all"
     Any string prefixed by a "-" will mean "exclude" this search string.  Boolean expressions
     are currently not supported.
   @arg $filter - (string) The filter provided by the user
   @arg $fields - (array) A list of field names to include in the where clause. They can also
     contain the table name in cases where joins are important.
   @returns - (string) The formatted SQL syntax */
function mactrack_create_sql_filter($filter, $fields) {
	$query = "";

	/* field names are required */
	if (!sizeof($fields)) return;

	/* the filter must be non-blank */
	if (!strlen($filter)) return;

	$elements = explode(" ", $filter);

	foreach($elements as $element) {
		if (substr($element, 0, 1) == "-") {
			$filter   = substr($element, 1);
			$type     = "NOT";
			$operator = "AND";
		} else {
			$filter   = $element;
			$type     = "";
			$operator = "OR";
		}

		$field_no = 1;
		foreach ($fields as $field) {
			if (($field_no == 1) && (strlen($query) > 0)) {
				$query .= ") AND (";
			}elseif ($field_no == 1) {
				$query .= "(";
			}

			$query .= ($field_no == 1 ? "":" $operator ") . "($field $type LIKE '%" . $filter . "%')";

			$field_no++;
		}
	}

	return $query . ")";
}

function mactrack_display_hours($value) {
	if ($value == "") {
		return "N/A";
	}else if ($value < 60) {
		return round($value,0) . " Minutes";
	}else{
		$value = $value / 60;
		if ($value < 24) {
			return round($value,0) . " Hours";
		}else{
			$value = $value / 24;
			if ($value < 7) {
				return round($value,0) . " Days";
			}else{
				$value = $value / 7;
				return round($value,0) . " Weeks";
			}
		}
	}
}

function mactrack_display_stats() {
	global $colors;

	/* check if scanning is running */
	$processes = db_fetch_cell("SELECT COUNT(*) FROM mac_track_processes");
	$frequency = read_config_option("mt_collection_timing", TRUE) * 60;
	$mactrack_stats = read_config_option("stats_mactrack", TRUE);
	$time  = 'Not Recorded';
	$proc  = 'N/A';
	$devs  = 'N/A';
	if ($mactrack_stats != '') {
		$stats = explode(" ", $mactrack_stats);

		if (sizeof($stats == 3)) {
			$time = explode(":", $stats[0]);
			$time = $time[1];

			$proc = explode(":", $stats[1]);
			$proc = $proc[1];

			$devs = explode(":", $stats[2]);
			$devs = $devs[1];
		}
	}

	if ($processes > 0) {
		$message = "<strong>Status:</strong> Running, <strong>Processes:</strong> " . $processes . ", <strong>Progress:</strong> " . read_config_option("mactrack_process_status", TRUE) . ", <strong>LastRuntime:</strong> " . round($time,1);
	}else{
		$message = "<strong>Status:</strong> Idle, <strong>LastRuntime:</strong> " . round($time,1) . " seconds, <strong>Processes:</strong> " . $proc . " processes, <strong>Devices:</strong> " . $devs . ", <strong>Next Run Time:</strong> " . date("Y-m-d H:i:s", strtotime(read_config_option("mt_scan_date", TRUE)) + $frequency);
	}
	html_start_box("", "100%", $colors["header"], "3", "center", "");

	print "<tr>";
	print "<td><strong>Scanning Rate:</strong> Every " . mactrack_display_hours(read_config_option("mt_collection_timing")) . ", " . $message . "</td>";
	print "</tr>";

	html_end_box();
}

function mactrack_legend_row($setting, $text) {
	if (read_config_option($setting) > 0) {
		print "<td width='10%' style='text-align:center; background-color:#" . db_fetch_cell("SELECT hex FROM colors WHERE id='" . read_config_option($setting) . "'") . ";'><strong>$text</strong></td>";
	}
}

function mactrack_redirect() {
	/* set the default tab */
	load_current_session_value("report", "sess_mactrack_view_report", "devices");
	$current_tab = $_REQUEST["report"];

	$current_page = str_replace("mactrack_", "", str_replace("view_", "", str_replace(".php", "", basename($_SERVER["PHP_SELF"]))));
	$current_dir  = dirname($_SERVER["PHP_SELF"]);

	if ($current_page != $current_tab) {
		header("Location: " . $current_dir . "/mactrack_view_" . $current_tab . ".php");
	}
}

function mactrack_format_device_row($device, $actions=false) {
	global $config, $colors, $mactrack_device_types;

	/* viewer level */
	if ($actions) {
		$row = "<a href='" . htmlspecialchars($config['url_path'] . "plugins/mactrack/mactrack_interfaces.php?device_id=" . $device['device_id'] . "&issues=0&page=1") . "'><img src='" . $config['url_path'] . "plugins/mactrack/images/view_interfaces.gif' alt='' onMouseOver='style.cursor=\"pointer\"' title='View Interfaces' align='middle' border='0'></a>";
		/* admin level */
		if (mactrack_authorized(2121)) {
			if ($device["disabled"] == '') {
				$row .= "<img id='r_" . $device["device_id"] . "' src='" . $config['url_path'] . "plugins/mactrack/images/rescan_device.gif' alt='' onMouseOver='style.cursor=\"pointer\"' onClick='scan_device(" . $device["device_id"] . ")' title='Rescan Device' align='middle' border='0'>";
			}else{
				$row .= "<img src='" . $config['url_path'] . "plugins/mactrack/images/view_none.gif' alt='' align='middle' border='0'>";
			}
		}
		print "<td style='width:40px;'>" . $row . "</td>";//, $device["device_id"]);
	}

	form_selectable_cell("<a class='linkEditMain' href='mactrack_devices.php?action=edit&device_id=" . $device['device_id'] . "'>" . (strlen($_REQUEST['filter']) ? preg_replace("/(" . preg_quote($_REQUEST['filter']) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $device['device_name']) : $device['device_name']) . "</a>", $device["device_id"]);
	form_selectable_cell($device["site_name"], $device["device_id"]);
	form_selectable_cell(get_colored_device_status(($device["disabled"] == "on" ? true : false), $device["snmp_status"]), $device["device_id"]);
	form_selectable_cell((strlen($_REQUEST["filter"]) ? preg_replace("/(" . preg_quote($_REQUEST["filter"]) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $device["hostname"]) : $device["hostname"]), $device["device_id"]);
	form_selectable_cell(($device["device_type"] == '' ? 'Not Detected' : $device["device_type"]), $device["device_id"]);
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

function mactrack_mail($to, $from, $fromname, $subject, $message, $headers = '') {
	global $config;
	include_once($config['base_path'] . '/plugins/settings/include/mailer.php');

	$subject = trim($subject);

	$message = str_replace('<SUBJECT>', $subject, $message);

	$how = read_config_option('settings_how');
	if ($how < 0 && $how > 2)
		$how = 0;
	if ($how == 0) {
		$Mailer = new Mailer(array(
			'Type' => 'PHP'));
	} else if ($how == 1) {
		$sendmail = read_config_option('settings_sendmail_path');
		$Mailer = new Mailer(array(
			'Type' => 'DirectInject',
			'DirectInject_Path' => $sendmail));
	} else if ($how == 2) {
		$smtp_host     = read_config_option('settings_smtp_host');
		$smtp_port     = read_config_option('settings_smtp_port');
		$smtp_username = read_config_option('settings_smtp_username');
		$smtp_password = read_config_option('settings_smtp_password');

		$Mailer = new Mailer(array(
			'Type' => 'SMTP',
			'SMTP_Host' => $smtp_host,
			'SMTP_Port' => $smtp_port,
			'SMTP_Username' => $smtp_username,
			'SMTP_Password' => $smtp_password));
	}

	if ($from == '') {
		$from     = read_config_option('mt_from_email');
		$fromname = read_config_option('mt_from_name');
		if ($from == '') {
			if (isset($_SERVER['HOSTNAME'])) {
				$from = 'Cacti@' . $_SERVER['HOSTNAME'];
			} else {
				$from = 'thewitness@cacti.net';
			}
		}
		if ($fromname == '') {
			$fromname = 'Cacti';
		}

		$from = $Mailer->email_format($fromname, $from);
		if ($Mailer->header_set('From', $from) === false) {
			cacti_log('ERROR: ' . $Mailer->error(), true, "MACTRACK");
			return $Mailer->error();
		}
	} else {
		$from = $Mailer->email_format($fromname, $from);
		if ($Mailer->header_set('From', $from) === false) {
			cacti_log('ERROR: ' . $Mailer->error(), true, "MACTRACK");
			return $Mailer->error();
		}
	}

	if ($to == '') {
		return 'Mailer Error: No <b>TO</b> address set!!<br>If using the <i>Test Mail</i> link, please set the <b>Alert e-mail</b> setting.';
	}
	$to = explode(',', $to);

	foreach($to as $t) {
		if (trim($t) != '' && !$Mailer->header_set('To', $t)) {
			cacti_log('ERROR: ' . $Mailer->error(), true, "MACTRACK");
			return $Mailer->error();
		}
	}

	$wordwrap = read_config_option('settings_wordwrap');
	if ($wordwrap == '') {
		$wordwrap = 76;
	}else if ($wordwrap > 9999) {
		$wordwrap = 9999;
	}else if ($wordwrap < 0) {
		$wordwrap = 76;
	}

	$Mailer->Config['Mail']['WordWrap'] = $wordwrap;

	if (! $Mailer->header_set('Subject', $subject)) {
		cacti_log('ERROR: ' . $Mailer->error(), true, "MACTRACK");
		return $Mailer->error();
	}

	$text = array('text' => '', 'html' => '');
	$text['html'] = $message . '<br>';
	$text['text'] = strip_tags(str_replace('<br>', "\n", $message));

	$v = mactrack_version();
	$Mailer->header_set('X-Mailer', 'Cacti-MacTrack-v' . $v['version']);
	$Mailer->header_set('User-Agent', 'Cacti-MacTrack-v' . $v['version']);

	if ($Mailer->send($text) == false) {
		cacti_log('ERROR: ' . $Mailer->error(), true, "MACTRACK");
		return $Mailer->error();
	}

	return '';
}

function mactrack_tabs() {
	global $config;

	/* present a tabbed interface */
	$tabs_mactrack = array(
		"sites" => "Sites",
		"devices" => "Devices",
		"ips" => "IP Ranges",
		"arp" => "IP Addresses",
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
	}else{
		/* draw the tabs */
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
				<br>
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

function mactrack_site_filter() {
	global $item_rows;

	?>
		<tr>
		<form name="form_mactrack_view_sites">
		<td>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td width="50">
						&nbsp;Search:&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" size="20" value="<?php print $_REQUEST["filter"];?>">
					</td>
					<td nowrap style='white-space: nowrap;' width="40">
						&nbsp;Rows:&nbsp;
					</td>
					<td width="1">
						<select name="rows" onChange="applySiteFilterChange(document.form_mactrack_view_sites)">
							<option value="-1"<?php if (get_request_var_request("rows") == "-1") {?> selected<?php }?>>Default</option>
							<?php
							if (sizeof($item_rows) > 0) {
							foreach ($item_rows as $key => $value) {
								print "<option value='" . $key . "'"; if (get_request_var_request("rows") == $key) { print " selected"; } print ">" . $value . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td>
						&nbsp;<input type="checkbox" id="detail" name="detail" <?php if (($_REQUEST["detail"] == "true") || ($_REQUEST["detail"] == "on")) print ' checked="true"';?> onClick="applySiteFilterChange(document.form_mactrack_view_sites)" alt="Device Details" border="0" align="absmiddle">
					</td>
					<td>
						<label for="detail">Show Device Details</label>&nbsp;
					</td>
					<td>
						&nbsp;<input type="submit" name="go_x" value="Go">
					</td>
					<td>
						&nbsp;<input type="submit" name="clear_x" value="Clear">
					</td>
					<td>
						&nbsp<input type="submit" name="export_x" value="Export">
					</td>
				</tr>
			<?php
			if (!($_REQUEST["detail"] == "false")) { ?>
			</table>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td width="50">
						&nbsp;Site:&nbsp;
					</td>
					<td width="1">
						<select name="site_id" onChange="applySiteFilterChange(document.form_mactrack_view_sites)">
						<option value="-1"<?php if ($_REQUEST["site_id"] == "-1") {?> selected<?php }?>>Any</option>
						<?php
						$sites = db_fetch_assoc("SELECT * FROM mac_track_sites ORDER BY mac_track_sites.site_name");
						if (sizeof($sites) > 0) {
						foreach ($sites as $site) {
							print '<option value="' . $site["site_id"] . '"'; if ($_REQUEST["site_id"] == $site["site_id"]) { print " selected"; } print ">" . $site["site_name"] . "</option>";
						}
						}
						?>
					</td>
					<td width="70">
						&nbsp;Sub Type:
					</td>
					<td width="1">
						<select name="device_type_id" onChange="applySiteFilterChange(document.form_mactrack_view_sites)">
						<option value="-1"<?php if ($_REQUEST["device_type_id"] == "-1") {?> selected<?php }?>>Any</option>
						<?php
						$device_types = db_fetch_assoc("SELECT DISTINCT mac_track_device_types.device_type_id,
								mac_track_device_types.description, mac_track_device_types.sysDescr_match
								FROM mac_track_device_types
								INNER JOIN mac_track_devices ON (mac_track_device_types.device_type_id = mac_track_devices.device_type_id)
								ORDER BY mac_track_device_types.description");

						if (sizeof($device_types) > 0) {
						foreach ($device_types as $device_type) {
							print '<option value="' . $device_type["device_type_id"] . '"'; if ($_REQUEST["device_type_id"] == $device_type["device_type_id"]) { print " selected"; } print ">" . $device_type["description"] . " (" . $device_type["sysDescr_match"] . ")</option>";
						}
						}
						?>
					</td>
				</tr>
			<?php }?>
			</table>
		</td>
		<input type='hidden' name='page' value='1'>
		<input type='hidden' name='report' value='sites'>
		<?php
		if ($_REQUEST["detail"] == "false") { ?>
		<input type='hidden' name='hidden_device_type_id' value='-1'>
		<input type='hidden' name='hidden_site_id' value='-1'>
		<?php }?>
		</form>
	</tr>
	<?php
}

function mactrack_device_filter() {
	global $item_rows;

	?>
	<script type="text/javascript">
	<!--
	function applyDeviceFilterChange(objForm) {
		strURL = '?site_id=' + objForm.site_id.value;
		strURL = strURL + '&status=' + objForm.status.value;
		strURL = strURL + '&type_id=' + objForm.type_id.value;
		strURL = strURL + '&device_type_id=' + objForm.device_type_id.value;
		strURL = strURL + '&filter=' + objForm.filter.value;
		strURL = strURL + '&rows=' + objForm.rows.value;
		document.location = strURL;
	}

	-->
	</script>
	<tr>
		<form name="form_mactrack_devices">
		<td>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td width="50">
						&nbsp;Site:&nbsp;
					</td>
					<td width="1">
						<select name="site_id" onChange="applyDeviceFilterChange(document.form_mactrack_devices)">
						<option value="-1"<?php if ($_REQUEST["site_id"] == "-1") {?> selected<?php }?>>All</option>
						<option value="-2"<?php if ($_REQUEST["site_id"] == "-2") {?> selected<?php }?>>None</option>
						<?php
						$sites = db_fetch_assoc("select site_id,site_name from mac_track_sites order by site_name");
						if (sizeof($sites) > 0) {
						foreach ($sites as $site) {
							print '<option value="'. $site["site_id"] . '"';if ($_REQUEST["site_id"] == $site["site_id"]) { print " selected"; } print ">" . $site["site_name"] . "</option>\n";
						}
						}
						?>
						</select>
					</td>
					<td width="5"></td>
					<td width="20">
						Search:&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" size="20" value="<?php print $_REQUEST["filter"];?>">
					</td>
					<td>
						&nbsp;<input type="submit" name="go_x" value="Go">
					</td>
					<td>
						&nbsp;<input type="submit" name="clear_x" value="Clear">
					</td>
					<td>
						&nbsp<input type="submit" name="import_x" value="Import">
					</td>
					<td>
						&nbsp<input type="submit" name="export_x" value="Export">
					</td>
				</tr>
			</table>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td width="50">
						&nbsp;Type:&nbsp;
					</td>
					<td width="1">
						<select name="type_id" onChange="applyDeviceFilterChange(document.form_mactrack_devices)">
						<option value="-1"<?php if ($_REQUEST["type_id"] == "-1") {?> selected<?php }?>>Any</option>
						<option value="1"<?php if ($_REQUEST["type_id"] == "1") {?> selected<?php }?>>Switch/Hub</option>
						<option value="2"<?php if ($_REQUEST["type_id"] == "2") {?> selected<?php }?>>Switch/Router</option>
						<option value="3"<?php if ($_REQUEST["type_id"] == "3") {?> selected<?php }?>>Router</option>
						</select>
					</td>
					<td width="5"></td>
					<td width="70">
						&nbsp;Sub Type:
					</td>
					<td width="1">
						<select name="device_type_id" onChange="applyDeviceFilterChange(document.form_mactrack_devices)">
						<option value="-1"<?php if ($_REQUEST["device_type_id"] == "-1") {?> selected<?php }?>>Any</option>
						<option value="-2"<?php if ($_REQUEST["device_type_id"] == "-2") {?> selected<?php }?>>Not Detected</option>
						<?php
						if ($_REQUEST["type_id"] != -1) {
							$device_types = db_fetch_assoc("SELECT DISTINCT
								mac_track_devices.device_type_id,
								mac_track_device_types.description,
								mac_track_device_types.sysDescr_match
								FROM mac_track_device_types
								INNER JOIN mac_track_devices ON (mac_track_device_types.device_type_id = mac_track_devices.device_type_id)
								WHERE device_type='" . $_REQUEST["type_id"] . "'
								ORDER BY mac_track_device_types.description");
						}else{
							$device_types = db_fetch_assoc("SELECT DISTINCT
								mac_track_devices.device_type_id,
								mac_track_device_types.description,
								mac_track_device_types.sysDescr_match
								FROM mac_track_device_types
								INNER JOIN mac_track_devices ON (mac_track_device_types.device_type_id=mac_track_devices.device_type_id)
								ORDER BY mac_track_device_types.description;");
						}
						if (sizeof($device_types) > 0) {
						foreach ($device_types as $device_type) {
							if ($device_type["device_type_id"] == 0) {
								$display_text = "Unknown Device Type";
							}else{
								$display_text = $device_type["description"] . " (" . $device_type["sysDescr_match"] . ")";
							}
							print '<option value="' . $device_type["device_type_id"] . '"'; if ($_REQUEST["device_type_id"] == $device_type["device_type_id"]) { print " selected"; } print ">" . $display_text . "</option>";
						}
						}
						?>
						</select>
					</td>
				</tr>
			</table>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td width="50">
						&nbsp;Status:&nbsp;
					</td>
					<td width="1">
						<select name="status" onChange="applyDeviceFilterChange(document.form_mactrack_devices)">
							<option value="-1"<?php if ($_REQUEST["status"] == "-1") {?> selected<?php }?>>Any</option>
							<option value="3"<?php if ($_REQUEST["status"] == "3") {?> selected<?php }?>>Up</option>
							<option value="-2"<?php if ($_REQUEST["status"] == "-2") {?> selected<?php }?>>Disabled</option>
							<option value="1"<?php if ($_REQUEST["status"] == "1") {?> selected<?php }?>>Down</option>
							<option value="0"<?php if ($_REQUEST["status"] == "0") {?> selected<?php }?>>Unknown</option>
							<option value="4"<?php if ($_REQUEST["status"] == "4") {?> selected<?php }?>>Error</option>
						</select>
					</td>
					<td nowrap style='white-space: nowrap;' width="40">
						&nbsp;Rows:&nbsp;
					</td>
					<td width="1">
						<select name="rows" onChange="applyDeviceFilterChange(document.form_mactrack_devices)">
							<option value="-1"<?php if (get_request_var_request("rows") == "-1") {?> selected<?php }?>>Default</option>
							<?php
							if (sizeof($item_rows) > 0) {
							foreach ($item_rows as $key => $value) {
								print "<option value='" . $key . "'"; if (get_request_var_request("rows") == $key) { print " selected"; } print ">" . $value . "</option>\n";
							}
							}
							?>
						</select>
					</td>
				</tr>
			</table>
		</td>
		<input type='hidden' name='page' value='1'>
		</form>
	</tr>
	<?php
}

function mactrack_device_type_filter() {
	global $item_rows;

	?>
	<script type="text/javascript">
	<!--
	function applyDeviceTypeFilterChange(objForm) {
		strURL = '?vendor=' + objForm.vendor.value;
		strURL = strURL + '&type_id=' + objForm.type_id.value;
		strURL = strURL + '&rows=' + objForm.rows.value;
		strURL = strURL + '&filter=' + objForm.filter.value;
		document.location = strURL;
	}

	-->
	</script>
	<tr>
		<form name="form_mactrack_device_types">
		<td>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td width="40">
						&nbsp;Vendor:&nbsp;
					</td>
					<td width="1">
						<select name="vendor" onChange="applyDeviceTypeFilterChange(document.form_mactrack_device_types)">
							<option value='All'<?php print $_REQUEST['type_id']; if ($_REQUEST['vendor'] == 'All') print ' selected';?>'>All</option>
							<?php
							$types = db_fetch_assoc("SELECT DISTINCT vendor from mac_track_device_types ORDER BY vendor");

							if (sizeof($types) > 0) {
							foreach ($types as $type) {
								print '<option value="' . $type["vendor"] . '"';if ($_REQUEST["vendor"] == $type["vendor"]) { print " selected"; } print ">" . $type["vendor"] . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td width="5"></td>
					<td width="40">
						&nbsp;Type:&nbsp;
					</td>
					<td width="1">
						<select name="type_id" onChange="applyDeviceTypeFilterChange(document.form_mactrack_device_types)">
							<option value="-1"<?php print $_REQUEST["vendor"] . '"'; if ($_REQUEST['type_id'] == '-1') print ' selected';?>>All</option>
							<option value="1"<?php print $_REQUEST["vendor"] . '"'; if ($_REQUEST['type_id'] == '1') print ' selected';?>>Switch/Hub</option>
							<option value="2"<?php print $_REQUEST["vendor"] . '"'; if ($_REQUEST['type_id'] == '2') print ' selected';?>>Switch/Router</option>
							<option value="3"<?php print $_REQUEST["vendor"] . '"'; if ($_REQUEST['type_id'] == '3') print ' selected';?>>Router</option>
							?>
						</select>
					</td>
					<td nowrap style='white-space: nowrap;' width="40">
						&nbsp;Rows:&nbsp;
					</td>
					<td width="1">
						<select name="rows" onChange="applyDeviceTypeFilterChange(document.form_mactrack_device_types)">
							<option value="-1"<?php if (get_request_var_request("rows") == "-1") {?> selected<?php }?>>Default</option>
							<?php
							if (sizeof($item_rows) > 0) {
							foreach ($item_rows as $key => $value) {
								print "<option value='" . $key . "'"; if (get_request_var_request("rows") == $key) { print " selected"; } print ">" . $value . "</option>\n";
							}
							}
							?>
						</select>
					</td>
				</tr>
			</table>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td width="40">
						&nbsp;Search:&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" size="20" value="<?php print $_REQUEST["filter"];?>">
					</td>
					<td>
						&nbsp;<input type="submit" name="go_x" title="Submit Query" value="Go">
					</td>
					<td>
						&nbsp;<input type="submit" name="clear_x" title="Clear Filtered Results" value="Clear">
					</td>
					<td>
						&nbsp<input type="submit" name="scan_x" title="Scan Active Devices for Unknown Device Types" value="Rescan">
					</td>
					<td>
						&nbsp<input type="submit" name="import_x" title="Import Device Types from a CSV File" value="Import">
					</td>
					<td>
						&nbsp<input type="submit" name="export_x" title="Export Device Types to Share with Others" value="Export">
					</td>
				</tr>
			</table>
		</td>
		<input type='hidden' name='page' value='1'>
		</form>
	</tr>
	<?php
}

function mactrack_vmac_filter() {
	global $item_rows;

	?>
	<script type="text/javascript">
	<!--
	function applyVMACFilterChange(objForm) {
		strURL = '?filter=' + objForm.filter.value;
		strURL = strURL + '&rows=' + objForm.rows.value;
		document.location = strURL;
	}
	-->
	</script>
	<tr>
		<form name="form_mactrack_vmacs">
		<td>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td width="50">
						&nbsp;Search:&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" size="20" value="<?php print $_REQUEST["filter"];?>">
					</td>
					<td nowrap style='white-space: nowrap;' width="40">
						&nbsp;Rows:&nbsp;
					</td>
					<td width="1">
						<select name="rows" onChange="applyVMACFilterChange(document.form_mactrack_vmacs)">
							<option value="-1"<?php if (get_request_var_request("rows") == "-1") {?> selected<?php }?>>Default</option>
							<?php
							if (sizeof($item_rows) > 0) {
							foreach ($item_rows as $key => $value) {
								print "<option value='" . $key . "'"; if (get_request_var_request("rows") == $key) { print " selected"; } print ">" . $value . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td>
						&nbsp;<input type="submit" name="go_x" value="Go">
					</td>
					<td>
						&nbsp;<input type="submit" name="clear_x" value="Clear">
					</td>
					<td>
						&nbsp<input type="submit" name="export_x" value="Export">
					</td>
				</tr>
			</table>
		</td>
		<input type='hidden' name='page' value='1'>
		<input type='hidden' name='report' value='sites'>
		</form>
	</tr>
	<?php
}

function mactrack_macw_filter() {
	global $item_rows;

	?>
	<script type="text/javascript">
	<!--
	function applyMacWFilterChange(objForm) {
		strURL = '?filter=' + objForm.filter.value;
		strURL = strURL + '&rows=' + objForm.rows.value;
		document.location = strURL;
	}
	-->
	</script>
	<tr>
		<form name="form_mactrack_macw">
		<td>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td width="50">
						&nbsp;Search:&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" size="20" value="<?php print $_REQUEST["filter"];?>">
					</td>
					<td nowrap style='white-space: nowrap;' width="40">
						&nbsp;Rows:&nbsp;
					</td>
					<td width="1">
						<select name="rows" onChange="applyMacWFilterChange(document.form_mactrack_macw)">
							<option value="-1"<?php if (get_request_var_request("rows") == "-1") {?> selected<?php }?>>Default</option>
							<?php
							if (sizeof($item_rows) > 0) {
							foreach ($item_rows as $key => $value) {
								print "<option value='" . $key . "'"; if (get_request_var_request("rows") == $key) { print " selected"; } print ">" . $value . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td>
						&nbsp;<input type="submit" name="go_x" value="Go">
					</td>
					<td>
						&nbsp;<input type="submit" name="clear_x" value="Clear">
					</td>
				</tr>
			</table>
		</td>
		<input type='hidden' name='page' value='1'>
		</form>
	</tr>
	<?php
}

function mactrack_maca_filter() {
	global $item_rows;

	?>
	<script type="text/javascript">
	<!--
	function applyMacAFilterChange(objForm) {
		strURL = '?filter=' + objForm.filter.value;
		strURL = strURL + '&rows=' + objForm.rows.value;
		document.location = strURL;
	}
	-->
	</script>
	<tr>
		<form name="form_mactrack_maca">
		<td>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td width="50">
						&nbsp;Search:&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" size="20" value="<?php print $_REQUEST["filter"];?>">
					</td>
					<td nowrap style='white-space: nowrap;' width="40">
						&nbsp;Rows:&nbsp;
					</td>
					<td width="1">
						<select name="rows" onChange="applyMacAFilterChange(document.form_mactrack_maca)">
							<option value="-1"<?php if (get_request_var_request("rows") == "-1") {?> selected<?php }?>>Default</option>
							<?php
							if (sizeof($item_rows) > 0) {
							foreach ($item_rows as $key => $value) {
								print "<option value='" . $key . "'"; if (get_request_var_request("rows") == $key) { print " selected"; } print ">" . $value . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td>
						&nbsp;<input type="submit" name="go_x" value="Go">
					</td>
					<td>
						&nbsp;<input type="submit" name="clear_x" value="Clear">
					</td>
				</tr>
			</table>
		</td>
		<input type='hidden' name='page' value='1'>
		</form>
	</tr>
	<?php
}

function mactrack_mac_filter() {
	global $item_rows, $rows_selector, $mactrack_search_types;

	?>
	<tr>
		<form name="form_mactrack_view_macs">
		<td>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td width="80">
						&nbsp;Site:&nbsp;
					</td>
					<td width="1">
						<select name="site_id" onChange="applyMacFilterChange(document.form_mactrack_view_macs)">
						<option value="-1"<?php if ($_REQUEST["site_id"] == "-1") {?> selected<?php }?>>N/A</option>
						<?php
						$sites = db_fetch_assoc("select site_id,site_name from mac_track_sites order by site_name");
						if (sizeof($sites) > 0) {
						foreach ($sites as $site) {
							print '<option value="' . $site["site_id"] .'"'; if ($_REQUEST["site_id"] == $site["site_id"]) { print " selected"; } print ">" . $site["site_name"] . "</option>";
						}
						}
						?>
						</select>
					</td>
					<td width="1">
						&nbsp;Device:&nbsp;
					</td>
					<td width="1">
						<select name="device_id" onChange="applyMacFilterChange(document.form_mactrack_view_macs)">
						<option value="-1"<?php if ($_REQUEST["device_id"] == "-1") {?> selected<?php }?>>All</option>
						<?php
						if ($_REQUEST["site_id"] == -1) {
							$filter_devices = db_fetch_assoc("SELECT device_id, device_name, hostname FROM mac_track_devices ORDER BY device_name");
						}else{
							$filter_devices = db_fetch_assoc("SELECT device_id, device_name, hostname FROM mac_track_devices WHERE site_id='" . $_REQUEST["site_id"] . "' ORDER BY device_name");
						}
						if (sizeof($filter_devices) > 0) {
						foreach ($filter_devices as $filter_device) {
							print '<option value=" ' . $filter_device["device_id"] . '"'; if ($_REQUEST["device_id"] == $filter_device["device_id"]) { print " selected"; } print ">" . $filter_device["device_name"] . "(" . $filter_device["hostname"] . ")" .  "</option>\n";
						}
						}
						?>
						</select>
					</td>
					<td width="40">
						&nbsp;Rows&nbsp;
					</td>
					<td width="1">
						<select name="rows" onChange="applyMacFilterChange(document.form_mactrack_view_macs)">
						<?php
						if (sizeof($rows_selector) > 0) {
						foreach ($rows_selector as $key => $value) {
							print '<option value="' . $key . '"'; if ($_REQUEST["rows"] == $key) { print " selected"; } print ">" . $value . "</option>\n";
						}
						}
						?>
						</select>
					</td>
					<td>
						&nbsp;<input type="submit" name="go_x" value="Go">
					</td>
					<td>
						&nbsp;<input type="submit" name="clear_x" value="Clear">
					</td>
					<td>
						&nbsp;<input type="submit" name="export_x" value="Export">
					</td>
				</tr>
			</table>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td width="80">
						&nbsp;IP Address:
					</td>
					<td width="1">
						<select name="ip_filter_type_id">
						<?php
						for($i=1;$i<=sizeof($mactrack_search_types);$i++) {
							print "<option value='" . $i . "'"; if ($_REQUEST["ip_filter_type_id"] == $i) { print " selected"; } print ">" . $mactrack_search_types[$i] . "</option>\n";
						}
						?>
						</select>
					</td>
					<td width="1">
						<input type="text" name="ip_filter" size="20" value="<?php print $_REQUEST["ip_filter"];?>">
					</td>
					<td width="80">
						&nbsp;VLAN Name:&nbsp;
					</td>
					<td width="1">
						<select name="vlan" onChange="applyMacFilterChange(document.form_mactrack_view_macs)">
						<option value="-1"<?php if ($_REQUEST["vlan"] == "-1") {?> selected<?php }?>>All</option>
						<?php
						$sql_where = "";
						if ($_REQUEST["device_id"] != "-1") {
							$sql_where = "WHERE device_id='" . $_REQUEST["device_id"] . "'";
						}

						if ($_REQUEST["site_id"] != "-1") {
							if (strlen($sql_where)) {
								$sql_where .= " AND site_id='" . $_REQUEST["site_id"] . "'";
							}else{
								$sql_where = "WHERE site_id='" . $_REQUEST["site_id"] . "'";
							}
						}

						$vlans = db_fetch_assoc("SELECT DISTINCT vlan_id, vlan_name FROM mac_track_vlans $sql_where ORDER BY vlan_name ASC");
						if (sizeof($vlans) > 0) {
						foreach ($vlans as $vlan) {
							print '<option value="' . $vlan["vlan_id"] . '"'; if ($_REQUEST["vlan"] == $vlan["vlan_id"]) { print " selected"; } print ">" . $vlan["vlan_name"] . "</option>\n";
						}
						}
						?>
						</select>
					</td>
					<td width="40">
						&nbsp;Show:&nbsp;
					</td>
					<td width="1">
						<select name="scan_date" onChange="applyMacFilterChange(document.form_mactrack_view_macs)">
						<option value="1"<?php if ($_REQUEST["scan_date"] == "1") {?> selected<?php }?>>All</option>
						<option value="2"<?php if ($_REQUEST["scan_date"] == "2") {?> selected<?php }?>>Most Recent</option>
						<option value="3"<?php if ($_REQUEST["scan_date"] == "3") {?> selected<?php }?>>Aggregated</option>
						<?php

						$scan_dates = db_fetch_assoc("select scan_date from mac_track_scan_dates order by scan_date desc");
						if (sizeof($scan_dates) > 0) {
						foreach ($scan_dates as $scan_date) {
							print '<option value="' . $scan_date["scan_date"] . '"'; if ($_REQUEST["scan_date"] == $scan_date["scan_date"]) { print " selected"; } print ">" . $scan_date["scan_date"] . "</option>\n";
						}
						}
						?>
						</select>
					</td>
				</tr>
				<tr>
					<td width="80">
						&nbsp;Mac Address:
					</td>
					<td width="1">
						<select name="mac_filter_type_id">
						<?php
						for($i=1;$i<=sizeof($mactrack_search_types)-2;$i++) {
							print "<option value='" . $i . "'"; if ($_REQUEST["mac_filter_type_id"] == $i) { print " selected"; } print ">" . $mactrack_search_types[$i] . "</option>\n";
						}
						?>
						</select>
					</td>
					<td width="1">
						<input type="text" name="mac_filter" size="20" value="<?php print $_REQUEST["mac_filter"];?>">
					</td>
					<td width="80">
						&nbsp;Authorized:&nbsp;
					</td>
					<td width="1">
						<select name="authorized" onChange="applyMacFilterChange(document.form_mactrack_view_macs)">
						<option value="-1"<?php if ($_REQUEST["authorized"] == "-1") {?> selected<?php }?>>All</option>
						<option value="1"<?php if ($_REQUEST["authorized"] == "1") {?> selected<?php }?>>Yes</option>
						<option value="0"<?php if ($_REQUEST["authorized"] == "0") {?> selected<?php }?>>No</option>
						</select>
					</td>
				</tr>
			</table>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td width="80">
						&nbsp;Search:&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" size="45" value="<?php print $_REQUEST["filter"];?>">
					</td>
				</tr>
			</table>
		</td>
		<input type='hidden' name='report' value='macs'>
		</form>
	</tr>
	<?php
}

function mactrack_ip_address_filter() {
	global $item_rows, $rows_selector, $mactrack_search_types;

	?>
	<tr>
		<form name="form_mactrack_view_arp">
		<td>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td width="80">
						&nbsp;Site:&nbsp;
					</td>
					<td width="1">
						<select name="site_id" onChange="applyArpFilterChange(document.form_mactrack_view_arp)">
						<option value="-1"<?php if ($_REQUEST["site_id"] == "-1") {?> selected<?php }?>>N/A</option>
						<?php
						$sites = db_fetch_assoc("select site_id,site_name from mac_track_sites order by site_name");
						if (sizeof($sites) > 0) {
						foreach ($sites as $site) {
							print '<option value="' . $site["site_id"] .'"'; if ($_REQUEST["site_id"] == $site["site_id"]) { print " selected"; } print ">" . $site["site_name"] . "</option>";
						}
						}
						?>
						</select>
					</td>
					<td width="1">
						&nbsp;Device:&nbsp;
					</td>
					<td width="1">
						<select name="device_id" onChange="applyArpFilterChange(document.form_mactrack_view_arp)">
						<option value="-1"<?php if ($_REQUEST["device_id"] == "-1") {?> selected<?php }?>>All</option>
						<?php
						if ($_REQUEST["site_id"] == -1) {
							$filter_devices = db_fetch_assoc("SELECT DISTINCT device_id, device_name, hostname FROM mac_track_ips ORDER BY device_name");
						}else{
							$filter_devices = db_fetch_assoc("SELECT DISTINCT device_id, device_name, hostname FROM mac_track_ips WHERE site_id='" . $_REQUEST["site_id"] . "' ORDER BY device_name");
						}
						if (sizeof($filter_devices) > 0) {
						foreach ($filter_devices as $filter_device) {
							print '<option value=" ' . $filter_device["device_id"] . '"'; if ($_REQUEST["device_id"] == $filter_device["device_id"]) { print " selected"; } print ">" . $filter_device["device_name"] . "(" . $filter_device["hostname"] . ")" .  "</option>\n";
						}
						}
						?>
						</select>
					</td>
					<td width="40">
						&nbsp;Rows&nbsp;
					</td>
					<td width="1">
						<select name="rows" onChange="applyArpFilterChange(document.form_mactrack_view_arp)">
						<?php
						if (sizeof($rows_selector) > 0) {
						foreach ($rows_selector as $key => $value) {
							print '<option value="' . $key . '"'; if ($_REQUEST["rows"] == $key) { print " selected"; } print ">" . $value . "</option>\n";
						}
						}
						?>
						</select>
					</td>
					<td>
						&nbsp;<input type="submit" name="go_x" value="Go">
					</td>
					<td>
						&nbsp;<input type="submit" name="clear_x" value="Clear">
					</td>
					<td>
						&nbsp;<input type="submit" name="export_x" value="Export">
					</td>
				</tr>
			</table>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td width="80">
						&nbsp;IP Address:
					</td>
					<td width="1">
						<select name="ip_filter_type_id">
						<?php
						for($i=1;$i<=sizeof($mactrack_search_types);$i++) {
							print "<option value='" . $i . "'"; if ($_REQUEST["ip_filter_type_id"] == $i) { print " selected"; } print ">" . $mactrack_search_types[$i] . "</option>\n";
						}
						?>
						</select>
					</td>
					<td width="1">
						<input type="text" name="ip_filter" size="20" value="<?php print $_REQUEST["ip_filter"];?>">
					</td>
				</tr>
				<tr>
					<td width="80">
						&nbsp;Mac Address:
					</td>
					<td width="1">
						<select name="mac_filter_type_id">
						<?php
						for($i=1;$i<=sizeof($mactrack_search_types)-2;$i++) {
							print "<option value='" . $i . "'"; if ($_REQUEST["mac_filter_type_id"] == $i) { print " selected"; } print ">" . $mactrack_search_types[$i] . "</option>\n";
						}
						?>
						</select>
					</td>
					<td width="1">
						<input type="text" name="mac_filter" size="20" value="<?php print $_REQUEST["mac_filter"];?>">
					</td>
				</tr>
			</table>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td width="80">
						&nbsp;Search:&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" size="45" value="<?php print $_REQUEST["filter"];?>">
					</td>
				</tr>
			</table>
		</td>
		<input type='hidden' name='report' value='arp'>
		</form>
	</tr>
	<?php
}

function mactrack_device_filter2() {
	global $item_rows;

	?>
	<tr>
		<form name="form_mactrack_view_devices">
		<td>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td width="50">
						&nbsp;Site:&nbsp;
					</td>
					<td width="1">
						<select name="site_id" onChange="applyDeviceFilterChange(document.form_mactrack_view_devices)">
						<option value="-1"<?php if ($_REQUEST["site_id"] == "-1") {?> selected<?php }?>>All</option>
						<option value="-2"<?php if ($_REQUEST["site_id"] == "-2") {?> selected<?php }?>>None</option>
						<?php
						$sites = db_fetch_assoc("select site_id,site_name from mac_track_sites order by site_name");
						if (sizeof($sites) > 0) {
						foreach ($sites as $site) {
							print '<option value="' . $site["site_id"] . '"'; if ($_REQUEST["site_id"] == $site["site_id"]) { print " selected"; } print ">" . $site["site_name"] . "</option>";
						}
						}
						?>
						</select>
					</td>
					<td width="5"></td>
					<td width="20">
						&nbsp;Search:&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" size="20" value="<?php print $_REQUEST["filter"];?>">
					</td>
					<td>
						&nbsp;<input type="submit" name="go_x" value="Go">
					</td>
					<td>
						&nbsp;<input type="submit" name="clear_x" value="Clear">
					</td>
					<td>
						&nbsp<input type="submit" name="export_x" value="Export">
					</td>
				</tr>
			</table>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td width="50">
						&nbsp;Type:&nbsp;
					</td>
					<td width="1">
						<select name="type_id" onChange="applyDeviceFilterChange(document.form_mactrack_view_devices)">
						<option value="-1"<?php if ($_REQUEST["type_id"] == "-1") {?> selected<?php }?>>Any</option>
						<option value="1"<?php if ($_REQUEST["type_id"] == "1") {?> selected<?php }?>>Hub/Switch</option>
						<option value="2"<?php if ($_REQUEST["type_id"] == "2") {?> selected<?php }?>>Switch/Router</option>
						<option value="3"<?php if ($_REQUEST["type_id"] == "3") {?> selected<?php }?>>Router</option>
						</select>
					</td>
					<td width="5"></td>
					<td width="70">
						&nbsp;Sub Type:
					</td>
					<td width="1">
						<select name="device_type_id" onChange="applyDeviceFilterChange(document.form_mactrack_view_devices)">
						<option value="-1"<?php if ($_REQUEST["device_type_id"] == "-1") {?> selected<?php }?>>Any</option>
						<option value="-2"<?php if ($_REQUEST["device_type_id"] == "-2") {?> selected<?php }?>>Not Detected</option>
						<?php
						if ($_REQUEST["type_id"] != -1) {
							$device_types = db_fetch_assoc("SELECT DISTINCT
								mac_track_devices.device_type_id,
								mac_track_device_types.description,
								mac_track_device_types.sysDescr_match
								FROM mac_track_device_types
								INNER JOIN mac_track_devices ON (mac_track_device_types.device_type_id=mac_track_devices.device_type_id)
								WHERE device_type='" . $_REQUEST["type_id"] . "'
								ORDER BY mac_track_device_types.description");
						}else{
							$device_types = db_fetch_assoc("SELECT DISTINCT
								mac_track_devices.device_type_id,
								mac_track_device_types.description,
								mac_track_device_types.sysDescr_match
								FROM mac_track_device_types
								INNER JOIN mac_track_devices ON (mac_track_device_types.device_type_id=mac_track_devices.device_type_id)
								ORDER BY mac_track_device_types.description;");
						}
						if (sizeof($device_types) > 0) {
						foreach ($device_types as $device_type) {
							$display_text = $device_type["description"] . " (" . $device_type["sysDescr_match"] . ")";
							print '<option value="' . $device_type["device_type_id"] . '"'; if ($_REQUEST["device_type_id"] == $device_type["device_type_id"]) { print " selected"; } print ">" . $display_text . "</option>";
						}
						}
						?>
						</select>
					</td>
				</tr>
			</table>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td width="50">
						&nbsp;Status:&nbsp;
					</td>
					<td width="1">
						<select name="status" onChange="applyDeviceFilterChange(document.form_mactrack_view_devices)">
						<option value="-1"<?php if ($_REQUEST["status"] == "-1") {?> selected<?php }?>>Any</option>
						<option value="3"<?php if ($_REQUEST["status"] == "3") {?> selected<?php }?>>Up</option>
						<option value="-2"<?php if ($_REQUEST["status"] == "-2") {?> selected<?php }?>>Disabled</option>
						<option value="1"<?php if ($_REQUEST["status"] == "1") {?> selected<?php }?>>Down</option>
						<option value="0"<?php if ($_REQUEST["status"] == "0") {?> selected<?php }?>>Unknown</option>
						<option value="4"<?php if ($_REQUEST["status"] == "4") {?> selected<?php }?>>Error</option>
						</select>
					</td>
					<td nowrap style='white-space: nowrap;' width="40">
						&nbsp;Rows:&nbsp;
					</td>
					<td width="1">
						<select name="rows" onChange="applyDeviceFilterChange(document.form_mactrack_view_devices)">
							<option value="-1"<?php if (get_request_var_request("rows") == "-1") {?> selected<?php }?>>Default</option>
							<?php
							if (sizeof($item_rows) > 0) {
							foreach ($item_rows as $key => $value) {
								print "<option value='" . $key . "'"; if (get_request_var_request("rows") == $key) { print " selected"; } print ">" . $value . "</option>\n";
							}
							}
							?>
						</select>
					</td>
				</tr>
			</table>
		</td>
		<input type='hidden' name='d_page' value='1'>
		<input type='hidden' name='report' value='devices'>
		</form>
	</tr>
	<?php
}

function mactrack_ips_filter() {
	global $item_rows;

	?>
	<tr>
		<form name="form_mactrack_view_ips">
		<td>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td width="40">
						&nbsp;Site:&nbsp;
					</td>
					<td width="1">
						<select name="site_id" onChange="applyIPsFilterChange(document.form_mactrack_view_ips)">
						<option value="-1"<?php if ($_REQUEST["site_id"] == "-1") {?> selected<?php }?>>Any</option>
						<?php
						$sites = db_fetch_assoc("SELECT * FROM mac_track_sites ORDER BY mac_track_sites.site_name");
						if (sizeof($sites) > 0) {
						foreach ($sites as $site) {
							print '<option value="' . $site["site_id"] . '"'; if ($_REQUEST["site_id"] == $site["site_id"]) { print " selected"; } print ">" . $site["site_name"] . "</option>";
						}
						}
						?>
					</td>
					<td nowrap style='white-space: nowrap;' width="40">
						&nbsp;Rows:&nbsp;
					</td>
					<td width="1">
						<select name="rows" onChange="applyIPsFilterChange(document.form_mactrack_view_ips)">
							<option value="-1"<?php if (get_request_var_request("rows") == "-1") {?> selected<?php }?>>Default</option>
							<?php
							if (sizeof($item_rows) > 0) {
							foreach ($item_rows as $key => $value) {
								print "<option value='" . $key . "'"; if (get_request_var_request("rows") == $key) { print " selected"; } print ">" . $value . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td>
						&nbsp<input type="submit" name="export_x" value="Export">
					</td>
					<td>
						&nbsp<input type="submit" name="clear_x" value="Clear">
					</td>
				</tr>
			</table>
		</td>
		<input type='hidden' name='page' value='1'>
		<input type='hidden' name='report' value='ips'>
		</form>
	</tr>
	<?php
}

