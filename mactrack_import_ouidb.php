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
 | Updates to the oui database can be obtained from the following web site |
 | http://standards.ieee.org/regauth/oui/oui.txt                           |
 +-------------------------------------------------------------------------+
*/

/* do NOT run this script through a web browser */
if (!isset($_SERVER["argv"][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die("<br><strong>This script is only meant to run at the command line.</strong>");
}

$no_http_headers = true;
if (file_exists(dirname(__FILE__) . "/../../include/global.php")) {
	include(dirname(__FILE__) . "/../../include/global.php");
} else {
	include(dirname(__FILE__) . "/../../include/config.php");
}
include(dirname(__FILE__) . "/lib/mactrack_functions.php");

/* process calling arguments */
$parms = $_SERVER["argv"];
array_shift($parms);

$debug    = FALSE;
$oui_file = "";

foreach($parms as $parameter) {
	@list($arg, $value) = @explode("=", $parameter);

	switch ($arg) {
	case "-f":
		$oui_file = trim($value);
		break;
	case "-h":
		display_help();
		exit;
	case "-v":
	case "-V":
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

if (strlen($oui_file)) {
	if (!file_exists($oui_file)) {		echo "ERROR: OUI Database file does not exist\n";
	}else{
		import_oui_database("ui", $oui_file);
	}
}else{	import_oui_database();
}

/*	display_help - displays the usage of the function */
function display_help () {
	print "Import OUI Database 1.0, Copyright 2006-2007 - Larry Adams\n\n";
	print "usage: mactrack_import_ouidb.php [-f=ouifile] [-h] [--help] [-v] [-V] [--version]\n\n";
	print "-f='outdbfile'   - Specify the location of the OUI dataabase file.  If your system\n";
	print "                   does not allow native access to the IEEE via http, you can manually\n";
	print "                   download the file, and then import it using this option.\n";
	print "-v -V --version  - Display this help message\n";
	print "-h --help        - display this help message\n";
}

?>
