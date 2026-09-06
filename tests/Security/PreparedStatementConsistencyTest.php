<?php

if (PHP_SAPI !== 'cli') {
	exit(1);
}
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/* Existing raw and dynamically-built DB-call debt is explicit and may only decrease. */

require_once __DIR__ . '/../Support/StandaloneTest.php';
require_once __DIR__ . '/../Support/SqlCallAnalyzer.php';
require_once __DIR__ . '/../Support/TrackedPhpFiles.php';

	$raw_baseline = [
		'includes/database.php'         => 141,
		'lib/mactrack_3com.php'         => 1,
		'lib/mactrack_aruba_oscx.php'   => 1,
		'lib/mactrack_cisco.php'        => 5,
		'lib/mactrack_enterasys_N7.php' => 1,
		'lib/mactrack_extreme.php'      => 1,
		'lib/mactrack_functions.php'    => 25,
		'lib/mactrack_h3c_3com.php'     => 1,
		'mactrack_actions.php'          => 19,
		'mactrack_convert.php'          => 8,
		'mactrack_device_types.php'     => 11,
		'mactrack_devices.php'          => 7,
		'mactrack_macauth.php'          => 2,
		'mactrack_macwatch.php'         => 2,
		'mactrack_resolver.php'         => 3,
		'mactrack_scanner.php'          => 1,
		'mactrack_sites.php'            => 3,
		'mactrack_snmp.php'             => 4,
		'mactrack_utilities.php'        => 25,
		'mactrack_vendormacs.php'       => 2,
		'mactrack_view_arp.php'         => 7,
		'mactrack_view_devices.php'     => 4,
		'mactrack_view_dot1x.php'       => 6,
		'mactrack_view_graphs.php'      => 2,
		'mactrack_view_interfaces.php'  => 5,
		'mactrack_view_ips.php'         => 3,
		'mactrack_view_macs.php'        => 9,
		'mactrack_view_sites.php'       => 3,
		'poller_mactrack.php'           => 35,
		'setup.php'                     => 20,
	];
	$dynamic_baseline = [
		'includes/database.php'         => 2,
		'lib/mactrack_aruba_oscx.php'   => 1,
		'lib/mactrack_cisco.php'        => 5,
		'lib/mactrack_enterasys_N7.php' => 1,
		'lib/mactrack_extreme.php'      => 1,
		'lib/mactrack_functions.php'    => 9,
		'lib/mactrack_h3c_3com.php'     => 1,
		'mactrack_actions.php'          => 19,
		'mactrack_convert.php'          => 1,
		'mactrack_device_types.php'     => 8,
		'mactrack_devices.php'          => 5,
		'mactrack_macauth.php'          => 2,
		'mactrack_macwatch.php'         => 2,
		'mactrack_resolver.php'         => 1,
		'mactrack_sites.php'            => 3,
		'mactrack_snmp.php'             => 4,
		'mactrack_vendormacs.php'       => 2,
		'mactrack_view_arp.php'         => 3,
		'mactrack_view_devices.php'     => 2,
		'mactrack_view_dot1x.php'       => 3,
		'mactrack_view_interfaces.php'  => 4,
		'mactrack_view_ips.php'         => 2,
		'mactrack_view_macs.php'        => 6,
		'mactrack_view_sites.php'       => 3,
		'setup.php'                     => 11,
	];
	$dynamic_prepared_baseline = [
		'mactrack_devices.php'  => 3,
		'mactrack_view_macs.php' => 1,
		'poller_mactrack.php'   => 2,
	];
	$actual_raw     = [];
	$actual_dynamic = [];
	$actual_dynamic_prepared = [];
	$root        = realpath(__DIR__ . '/../..');

	foreach (MactrackTrackedPhpFiles::listRelative($root) as $relative) {
		if (preg_match('#(^|/)(tests|vendor)(/|$)#', $relative)) {
			continue;
		}

		$path = $root . '/' . $relative;
		$source = file_get_contents($path);
		MactrackStandaloneTest::assertTrue($source !== false, "$relative is readable for SQL analysis");
		$counts = MactrackSqlCallAnalyzer::count($source);
		$raw_count = $counts['raw'];
		$dynamic_count = $counts['dynamic_raw'];
		$dynamic_prepared_count = $counts['dynamic_prepared'];

		if ($raw_count) {
			$actual_raw[$relative] = $raw_count;
		}

		if ($dynamic_count) {
			$actual_dynamic[$relative] = $dynamic_count;
		}

		if ($dynamic_prepared_count) {
			$actual_dynamic_prepared[$relative] = $dynamic_prepared_count;
		}
	}

$matches_baseline = function ($actual, $baseline) {
	return $actual === $baseline;
};
$baseline_diagnostic = function ($file, $actual, $baseline, $kind) {
	if (!array_key_exists($file, $baseline)) {
		return "$file introduced $kind DB calls without a reviewed baseline";
	}

	if ($actual > $baseline[$file]) {
		return "$file increased its $kind DB-call count to $actual; use a prepared call or explicitly review the baseline";
	}

	if ($actual < $baseline[$file]) {
		return "$file reduced its $kind DB-call count to $actual; lower the baseline in this commit";
	}

	return null;
};

MactrackStandaloneTest::assertTrue($matches_baseline(1, 1), 'the SQL-debt gate accepts an exact baseline');
MactrackStandaloneTest::assertTrue(!$matches_baseline(0, 1), 'the SQL-debt gate requires a reduced count to lower its baseline');
MactrackStandaloneTest::assertTrue(!$matches_baseline(2, 1), 'the SQL-debt gate rejects a known increase');
$missing_baseline_message = $baseline_diagnostic('new-file.php', 1, [], 'raw');
$reduced_baseline_message = $baseline_diagnostic('existing.php', 1, ['existing.php' => 2], 'raw');
$increased_baseline_message = $baseline_diagnostic('existing.php', 3, ['existing.php' => 2], 'raw');
MactrackStandaloneTest::assertContains('without a reviewed baseline', $missing_baseline_message, 'a raw DB call in a file without a baseline fails closed');
MactrackStandaloneTest::assertContains('reduced its raw DB-call count to 1; lower the baseline', $reduced_baseline_message, 'a reduction tells the contributor to lower the baseline');
MactrackStandaloneTest::assertContains('increased its raw DB-call count to 3', $increased_baseline_message, 'an increase is accurately identified');

foreach ([
	'raw' => [$raw_baseline, $actual_raw],
	'dynamically-built raw' => [$dynamic_baseline, $actual_dynamic],
	'dynamically-built prepared' => [$dynamic_prepared_baseline, $actual_dynamic_prepared],
] as $kind => $maps) {
	$files = array_values(array_unique(array_merge(array_keys($maps[0]), array_keys($maps[1]))));
	sort($files);

	foreach ($files as $file) {
		$diagnostic = $baseline_diagnostic($file, $maps[1][$file] ?? 0, $maps[0], $kind);
		MactrackStandaloneTest::assertSame(null, $diagnostic, $diagnostic ?? "$file exactly matches its $kind SQL baseline");
	}
}

MactrackStandaloneTest::finish('MacTrack SQL construction ratchets');
