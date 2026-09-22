<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for mactrack_draw_navigation_text() in setup.php.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

it('adds the mactrack breadcrumb entries without disturbing existing ones', function () {
	$nav = mactrack_draw_navigation_text(array('other.php:' => array('title' => 'Other')));

	expect($nav)->toHaveKey('other.php:');
	expect($nav)->toHaveKey('mactrack_devices.php:');
	expect($nav)->toHaveKey('mactrack_devices.php:edit');
	expect($nav['mactrack_devices.php:edit']['mapping'])->toBe('index.php:,mactrack_devices.php:');
});
