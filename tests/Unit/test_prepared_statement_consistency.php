<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

$baseline = [
	'includes/database.php'           => 144,
	'lib/mactrack_3com.php'           => 1,
	'lib/mactrack_aruba_oscx.php'     => 1,
	'lib/mactrack_cisco.php'          => 5,
	'lib/mactrack_enterasys_N7.php'   => 1,
	'lib/mactrack_extreme.php'        => 1,
	'lib/mactrack_functions.php'      => 28,
	'lib/mactrack_h3c_3com.php'       => 1,
	'mactrack_actions.php'            => 19,
	'mactrack_convert.php'            => 9,
	'mactrack_device_types.php'       => 11,
	'mactrack_devices.php'            => 7,
	'mactrack_macauth.php'            => 2,
	'mactrack_macwatch.php'           => 2,
	'mactrack_resolver.php'           => 3,
	'mactrack_scanner.php'            => 1,
	'mactrack_sites.php'              => 3,
	'mactrack_snmp.php'               => 4,
	'mactrack_utilities.php'          => 25,
	'mactrack_vendormacs.php'         => 2,
	'mactrack_view_arp.php'           => 7,
	'mactrack_view_devices.php'       => 4,
	'mactrack_view_dot1x.php'         => 6,
	'mactrack_view_graphs.php'        => 2,
	'mactrack_view_interfaces.php'    => 3,
	'mactrack_view_ips.php'           => 3,
	'mactrack_view_macs.php'          => 9,
	'mactrack_view_sites.php'         => 3,
	'poller_mactrack.php'             => 39,
	'setup.php'                       => 22,
];

$tracked = shell_exec("git ls-files '*.php'");

if (!is_string($tracked) || trim($tracked) === '') {
	fwrite(STDERR, "Unable to enumerate tracked PHP files\n");
	exit(1);
}

$failed  = 0;
$pattern = '/\\bdb_(?:execute|fetch_row|fetch_assoc|fetch_cell)\\s*\\(/i';

foreach (preg_split('/\R/', trim($tracked)) as $file) {
	if ($file === '' || strpos($file, 'Net/') === 0 || strpos($file, 'tests/') === 0) {
		continue;
	}

	$source = file_get_contents($file);

	if ($source === false) {
		fwrite(STDERR, "Unable to read $file\n");
		$failed++;
		continue;
	}

	$count = preg_match_all($pattern, $source);

	if ($count > ($baseline[$file] ?? 0)) {
		fwrite(STDERR, "$file increased raw database calls from " . ($baseline[$file] ?? 0) . " to $count\n");
		$failed++;
	}
}

if ($failed) {
	exit(1);
}

print "OK\n";
