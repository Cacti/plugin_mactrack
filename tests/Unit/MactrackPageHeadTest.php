<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for mactrack_page_head()'s stylesheet selection in setup.php,
 * including the per-theme override loaded from css/<theme>.css.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

afterEach(function () {
	unset($GLOBALS['__test_selected_theme']);
});

it('links the per-theme stylesheet from css/ when it exists', function () {
	$restore = $GLOBALS['config']['base_path'];
	$base    = sys_get_temp_dir() . '/mactrack-ph-' . uniqid();
	mkdir($base . '/plugins/mactrack/css', 0777, true);
	file_put_contents($base . '/plugins/mactrack/css/modern.css', '');
	$GLOBALS['__test_selected_theme'] = 'modern';
	$GLOBALS['config']['base_path']   = $base;

	ob_start();

	try {
		mactrack_page_head();
	} finally {
		$output = ob_get_clean();
		$GLOBALS['config']['base_path'] = $restore;
	}

	expect($output)->toContain('plugins/mactrack/css/modern.css');
});

it('falls back to css/mactrack.css when no per-theme stylesheet exists', function () {
	$restore = $GLOBALS['config']['base_path'];
	$base    = sys_get_temp_dir() . '/mactrack-ph-' . uniqid();
	mkdir($base . '/plugins/mactrack/css', 0777, true);
	$GLOBALS['__test_selected_theme'] = 'no-such-theme';
	$GLOBALS['config']['base_path']   = $base;

	ob_start();

	try {
		mactrack_page_head();
	} finally {
		$output = ob_get_clean();
		$GLOBALS['config']['base_path'] = $restore;
	}

	expect($output)->toContain('plugins/mactrack/css/mactrack.css');
});
