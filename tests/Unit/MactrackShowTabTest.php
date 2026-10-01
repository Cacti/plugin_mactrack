<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for mactrack_show_tab() in setup.php: it loads the plugin
 * function library and, when the user may view MAC reports, prints the
 * console tab anchor.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

afterEach(function () {
	unset($_SERVER['REQUEST_URI']);
});

it('loads the function library and renders the MacTrack tab when permitted', function () {
	$_SERVER['REQUEST_URI'] = '/cacti/plugins/mactrack/mactrack_view_macs.php';

	mactrack_test_queue_return('isset_request_var', true);
	mactrack_test_queue_return('api_user_realm_auth', true);

	ob_start();

	try {
		mactrack_show_tab();
	} finally {
		$output = ob_get_clean();
	}

	expect($output)->toContain('plugins/mactrack/images/tab_mactrack');
});

it('renders nothing when the user may not view MAC reports', function () {
	mactrack_test_queue_return('isset_request_var', false);
	mactrack_test_queue_return('api_user_realm_auth', false);

	ob_start();

	try {
		mactrack_show_tab();
	} finally {
		$output = ob_get_clean();
	}

	expect($output)->toBe('');
});
