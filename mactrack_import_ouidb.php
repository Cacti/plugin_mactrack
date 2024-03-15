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
 | http://standards-oui.ieee.org/oui/oui.txt                               |
 +-------------------------------------------------------------------------+
*/

$dir = dirname(__FILE__);
chdir($dir);

if (substr_count(strtolower($dir), 'mactrack')) {
	chdir('../../');
}

include('./include/cli_check.php');
include_once($config['base_path'] . '/plugins/mactrack/lib/mactrack_functions.php');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

$debug    = false;
$oui_file = '';

/* add more memory for import */
ini_set('memory_limit', '-1');

if (cacti_sizeof($parms)) {
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '-f':
				$oui_file = trim($value);
				break;
			case '--version':
			case '-V':
			case '-v':
				display_version();
				exit;
			case '--help':
			case '-H':
			case '-h':
				display_help();
				exit;
			default:
				print 'ERROR: Invalid Parameter ' . $parameter . "\n\n";
				display_help();
				exit;
		}
	}
}

if (strlen($oui_file)) {
	if (!file_exists($oui_file)) {
		echo "ERROR: OUI Database file does not exist\n";
	} else {
		import_oui_database('ui', $oui_file);
	}
} else {
	import_oui_database();
}

function display_version() {
	global $config;

	$info = plugin_mactrack_version();
	print "Device Tracking Import OUI Database, Version " . $info["version"] . ", " . COPYRIGHT_YEARS . "\n";
}

/*	display_help - displays the usage of the function */
function display_help () {
	display_version();

	print "\nusage: mactrack_import_ouidb.php [-f=ouifile] [-h] [--help] [-v] [-V] [--version]\n\n";
	print "-f='outdbfile'   - Specify the location of the OUI dataabase file.  If your system\n";
	print "                   does not allow native access to the IEEE via http, you can manually\n";
	print "                   download the file, and then import it using this option.\n";
	print "-v -V --version  - Display this help message\n";
	print "-h --help        - Display this help message\n";
}

