<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Converted from the standalone tests/Integration/test_mactrack_filter_output_wiring.php
 * script (and its tests/Pest/Integration/FilterOutputWiringTest.php exec wrapper).
 * Keeps the filter-output escaping wiring intact across the viewer files.
 */

it('keeps the filter-output security wiring intact', function () {
	$checks = [
		'mactrack_view_macs.php' => [
			"html_escape(\$site['site_name'])",
			"html_escape(\$filter_device['device_name'] . '(' . \$filter_device['hostname'] . ')')",
		],
		'mactrack_device_types.php' => [
			"html_escape(\$type['vendor'])",
		],
	];

	foreach ($checks as $relativeFile => $patterns) {
		$source = plugin_test_read_source($relativeFile);

		foreach ($patterns as $pattern) {
			expect($source)->toContain($pattern);
		}
	}
});
