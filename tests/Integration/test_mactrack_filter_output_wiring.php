<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$checks = [
	__DIR__ . '/../../mactrack_view_macs.php' => [
		"html_escape(\$site['site_name'])",
		"html_escape(\$filter_device['device_name'] . '(' . \$filter_device['hostname'] . ')')",
	],
	__DIR__ . '/../../mactrack_device_types.php' => [
		"html_escape(\$type['vendor'])",
	],
];

foreach ($checks as $path => $patterns) {
	$contents = file_get_contents($path);

	if ($contents === false) {
		fwrite(STDERR, "Unable to read {$path}\n");
		exit(1);
	}

	foreach ($patterns as $pattern) {
		if (strpos($contents, $pattern) === false) {
			fwrite(STDERR, "Missing expected escaped output: {$pattern}\n");
			exit(1);
		}
	}
}

print "OK\n";
