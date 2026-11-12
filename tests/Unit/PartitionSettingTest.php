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

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

beforeEach(function () {
	$GLOBALS['__test_db_calls']        = [];
	$GLOBALS['__test_next_returns']    = [];
	$GLOBALS['__test_matched_returns'] = [];
	$GLOBALS['__test_config_options']  = [];
	$GLOBALS['__test_cacti_log']       = [];
});

it('offers per-scan, hourly and daily partitions, per scan by default', function () {
	global $settings, $settings_user, $tabs_graphs;

	// The hook extends core's settings arrays, so they must exist.
	$settings      = ['mail' => [], 'path' => []];
	$settings_user = [];
	$tabs_graphs   = [];
	mactrack_config_settings();

	$setting = $settings['mactrack']['mt_partition_granularity'] ?? null;

	expect($setting)->not->toBeNull();
	expect($setting['method'])->toBe('drop_array');
	expect($setting['default'])->toBe('scan');
	expect(array_keys($setting['array']))->toBe(['scan', 'hour', 'day']);
	expect(array_keys($settings['mactrack']))->toContain('mt_data_retention_ip');
});
