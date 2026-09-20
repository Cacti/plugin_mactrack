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
 * Confirms the filter-driven views actually wire their labels through
 * html_escape() before rendering, rather than only asserting that
 * html_escape() exists somewhere in the plugin.
 */
describe('filter output escaping wiring in mactrack', function () {
	$checks = [
		'mactrack_view_macs.php' => [
			"html_escape(\$site['site_name'])",
			"html_escape(\$filter_device['device_name'] . '(' . \$filter_device['hostname'] . ')')",
		],
		'mactrack_device_types.php' => [
			"html_escape(\$type['vendor'])",
		],
	];

	foreach ($checks as $file => $patterns) {
		foreach ($patterns as $pattern) {
			it("renders {$pattern} in {$file}", function () use ($file, $pattern) {
				$source = plugin_test_read_source($file);

				expect($source)->toContain($pattern);
			});
		}
	}
});
