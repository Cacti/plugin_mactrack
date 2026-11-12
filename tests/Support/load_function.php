<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/*
 * Some plugin scripts handle the request or run the poller as soon as they
 * are included, so a test cannot require them. This copies one function out
 * of such a script into a temporary file and loads that instead.
 */

/**
 * Load a single top-level function from a plugin script.
 *
 * @param string $file     Path relative to the plugin root.
 * @param string $function Function name.
 *
 * @return void
 */
function mactrack_test_load_function(string $file, string $function): void {
	if (function_exists($function)) {
		return;
	}

	$source = (string) file_get_contents(dirname(__DIR__, 2) . '/' . $file);
	$start  = strpos($source, "\nfunction $function(");

	if ($start === false) {
		throw new RuntimeException("$function() not found in $file");
	}

	$end  = strpos($source, "\n}\n", $start);
	$path = tempnam(sys_get_temp_dir(), 'mactrack_fn_');

	file_put_contents($path, "<?php\n" . substr($source, $start, $end - $start + 3));

	require $path;

	unlink($path);
}
