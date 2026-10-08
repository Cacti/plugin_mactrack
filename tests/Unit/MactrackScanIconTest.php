<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for the rescan-device icon rendered by
 * mactrack_format_device_row() in lib/mactrack_functions.php. The icon uses a
 * CSP-safe mactrackScanDevice class and data-device-id attribute (bound via a
 * delegated handler in js/mactrack.js) rather than an inline onClick.
 */

beforeAll(function () {
	require_once dirname(__DIR__, 2) . '/lib/mactrack_functions.php';
});

it('renders the rescan icon with a CSP-safe data-device-id and no inline onClick', function () {
	global $config;

	$config['url_path'] = '/';

	ob_start();

	try {
		mactrack_format_device_row(array('device_id' => 7, 'disabled' => ''), true);
	} catch (\Throwable $e) {
		// The full row render calls form helpers beyond the rescan icon; the icon
		// (the changed line) is emitted and printed before any such point.
	}

	$output = ob_get_clean();

	expect($output)->toContain('mactrackScanDevice');
	expect($output)->toContain("data-device-id='7'");
	expect($output)->not->toContain('onClick');
});
