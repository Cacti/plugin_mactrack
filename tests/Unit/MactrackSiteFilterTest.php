<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for mactrack_site_filter() in lib/mactrack_functions.php: the
 * rows/detail/site/subtype filter controls emit CSP-safe data-onchange /
 * data-onclick attributes (bound via delegated handlers in js/mactrack.js)
 * rather than inline onChange/onClick.
 */

beforeAll(function () {
	// Core helper used before the changed lines; stub so the render reaches them.
	if (!function_exists('html_escape_request_var')) {
		function html_escape_request_var($name) {
			return '';
		}
	}

	require_once dirname(__DIR__, 2) . '/lib/mactrack_functions.php';
});

it('emits the filter controls with CSP-safe data attributes and no inline handlers', function () {
	ob_start();

	try {
		mactrack_site_filter('mactrack_sites.php');
	} catch (\Throwable $e) {
		// The changed filter-control lines are emitted before any further helper.
	}

	$output = ob_get_clean();

	expect($output)->toContain("<select id='rows' data-onchange='applyFilter'>");
	expect($output)->toContain("data-onclick='applyFilter'");
	expect($output)->toContain("<select id='site_id' data-onchange='applyFilter'>");
	expect($output)->toContain("<select id='device_type_id' data-onchange='applyFilter'>");
	expect($output)->not->toContain('onChange=');
	expect($output)->not->toContain('onClick=');
});
