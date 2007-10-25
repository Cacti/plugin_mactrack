<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2005 Larry Adams                                          |
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
*/

/* do NOT run this script through a web browser */
if (!isset($_SERVER["argv"][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die("<br><strong>This script is only meant to run at the command line.</strong>");
}

$no_http_headers = true;

$dir = dirname(__FILE__);
chdir($dir);

if (substr_count(strtolower($dir), 'mactrack')) {
	chdir('../../');
}

if (file_exists("./include/global.php")) {
	include("./include/global.php");
} else {
	include("./include/config.php");
}
include_once($config["base_path"] . "/plugins/mactrack/lib/mactrack_functions.php");

/* Let the scanner run for no more that 25 minutes */
ini_set("max_execution_time", 1500);

/* establish constants */
define("DEVICE_HUB_SWITCH", 1);
define("DEVICE_SWITCH_ROUTER", 2);
define("DEVICE_ROUTER", 3);

/* process calling arguments */
$parms = $_SERVER["argv"];
array_shift($parms);

$debug = FALSE;

foreach($parms as $parameter) {
	@list($arg, $value) = @explode("=", $parameter);

	switch ($arg) {
	case "-d":
		$debug = TRUE;
		break;
	case "-h":
		display_help();
		exit;
	case "-v":
		display_help();
		exit;
	case "--version":
		display_help();
		exit;
	case "--help":
		display_help();
		exit;
	default:
		print "ERROR: Invalid Parameter " . $parameter . "\n\n";
		display_help();
		exit;
	}
}

/* check if you need to run or not */
if (read_config_option("mt_reverse_dns") == "on") {
	$timeout = read_config_option("mt_dns_timeout");
	$dns_primary = read_config_option("mt_dns_primary");
	$dns_secondary = read_config_option("mt_dns_secondary");
	$primary_down = FALSE;
	$secondary_down = FALSE;
}else{
	exit;
}

/* place a process marker in the database for the ip resolver */
db_process_add(0, TRUE);

/* loop until you are it */
while (1) {
	$processes_running = db_fetch_cell("SELECT COUNT(*)
		FROM mac_track_processes
		WHERE device_id <> '0'");

	$run_status = db_fetch_assoc("SELECT last_rundate,
		COUNT(last_rundate) AS devices
		FROM mac_track_devices
		WHERE disabled = ''
		GROUP BY last_rundate
		ORDER BY last_rundate DESC;");

	if ((sizeof($run_status) == 1) && ($processes_running == 0)) {
		$break = TRUE;
	}else{
		$break = FALSE;
	}

	$unresolved_ips = db_fetch_assoc("SELECT * FROM mac_track_temp_ports WHERE ip_address != '' AND (dns_hostname = '' OR dns_hostname IS NULL)");
	if (sizeof($unresolved_ips) == 0) {
		mactrack_debug("No IP's require resolving this pass");
		sleep(3);
	}else{
		mactrack_debug(sizeof($unresolved_ips) . " IP's require resolving this pass");

		foreach($unresolved_ips as $key => $unresolved_ip) {
			$dns_hostname = $unresolved_ip["ip_address"];
			$success = TRUE;
			if (!$primary_down) {
				$dns_hostname = mactrack_get_dns_from_ip($unresolved_ip["ip_address"], $dns_primary, $timeout);
				if ($dns_hostname == "timed_out") {
					$dns_hostname == $unresolved_ip["ip_address"];
					$primary_down = TRUE;
					$success = FALSE;
				}
			}

			if ((!$success) && (!$secondary_down)) {
				$dns_hostname = mactrack_get_dns_from_ip($unresolved_ip["ip_address"], $dns_secondary, $timeout);
				if ($dns_hostname == "timed_out") {
					$dns_hostname == $unresolved_ip["ip_address"];
					$secondary_down = TRUE;
					$success = FALSE;
				}
			}elseif (!$success) {
				$dns_hostname == $unresolved_ip["ip_address"];
			}

			if (($primary_down) && ($secondary_down)) {
				mactrack_debug("ERROR: Both Primary and Seconary DNS timed out, please increase timeout. Placing both DNS servers back online now.");
				$secondary_down = FALSE;
				$primary_down = FALSE;
			}

			$unresolved_ips[$key]["dns_hostname"] = $dns_hostname;
		}
		mactrack_debug("DNS host association complete.");

		/* output updated details to database */
		foreach($unresolved_ips as $unresolved_ip) {
			$insert_string = "REPLACE INTO mac_track_temp_ports " .
				"(site_id,device_id,hostname,dns_hostname,device_name,vlan_id,vlan_name," .
				"mac_address,ip_address,port_number,port_name,scan_date)" .
				" VALUES ('" .
				$unresolved_ip["site_id"] . "','" .
				$unresolved_ip["device_id"] . "','" .
				$unresolved_ip["hostname"] . "','" .
				$unresolved_ip["dns_hostname"] . "','" .
				$unresolved_ip["device_name"] . "','" .
				$unresolved_ip["vlan_id"] . "','" .
				$unresolved_ip["vlan_name"] . "','" .
				$unresolved_ip["mac_address"] . "','" .
				$unresolved_ip["ip_address"] . "','" .
				$unresolved_ip["port_number"] . "','" .
				$unresolved_ip["port_name"] . "','" .
				$unresolved_ip["scan_date"] . "')";

			db_execute($insert_string);
		}
		mactrack_debug("Records updated with DNS information included.");
	}

	if ($break) break;
}

/* allow parent to close by removing process and then exit */
db_process_remove(0);
exit;

/*	display_help - displays the usage of the function */
function display_help () {
	print "Network Mac Tracker IP Resolver Version 1.0, Copyright 2005 - Larry Adams\n\n";
	print "usage: mactrack_resolver.php [-d] [-h] [--help] [-v] [--version]\n\n";
	print "-d            - Display verbose output during execution\n";
	print "-v --version  - Display this help message\n";
	print "-h --help     - display this help message\n";
}

?>