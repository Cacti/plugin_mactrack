<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Converted from the standalone tests/Unit/test_filter_option_escaping.php
 * script. Confirms filter <option> labels built from database content (site
 * names, device names/hostnames, device types) are actually wired through
 * html_escape() in the views that render them, rather than only asserting
 * that html_escape() exists somewhere in the plugin.
 */
describe('filter option label escaping in mactrack', function () {
	$checks = [
		'mactrack_view_arp.php' => [
			"html_escape(\$site['site_name'])",
			"html_escape(\$filter_device['device_name'] . '(' . \$filter_device['hostname'] . ')')",
		],
		'mactrack_view_devices.php' => [
			"html_escape(\$site['site_name'])",
			'html_escape($display_text)',
		],
		'mactrack_view_dot1x.php' => [
			"html_escape(\$site['site_name'])",
		],
		'mactrack_view_interfaces.php' => [
			"html_escape(\$site['site_name'])",
			"html_escape(\$type['device_type'])",
			'html_escape($device_name)',
		],
		'mactrack_view_ips.php' => [
			"html_escape(\$site['site_name'])",
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
