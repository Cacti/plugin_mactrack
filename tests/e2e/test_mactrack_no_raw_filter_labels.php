<?php

if (PHP_SAPI !== 'cli') {
	exit(1);
}
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$checks = [
	__DIR__ . '/../../mactrack_view_macs.php' => [
		". '>' . \$site['site_name'] . '</option>';",
		". '>' . \$filter_device['device_name'] . '(' . \$filter_device['hostname'] . ')' . '</option>';",
	],
	__DIR__ . '/../../mactrack_device_types.php' => [
		". '>' . \$type['vendor'] . '</option>';",
	],
];
$assertions = 0;

foreach ($checks as $path => $patterns) {
	$contents = file_get_contents($path);
	$assertions++;

	if ($contents === false) {
		fwrite(STDERR, "Unable to read {$path}\n");
		exit(1);
	}

	foreach ($patterns as $pattern) {
		$assertions++;

		if (strpos($contents, $pattern) !== false) {
			fwrite(STDERR, "Raw filter label output remains: {$pattern}\n");
			exit(1);
		}
	}
}

print "Rendered filter labels: $assertions assertions passed\n";
