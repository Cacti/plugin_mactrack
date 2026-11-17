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

require_once dirname(__DIR__) . '/Support/load_function.php';

beforeAll(function () {
	mactrack_test_load(dirname(__DIR__, 2) . '/lib/mactrack_functions.php');
});

foreach (['conversion_fields', 'post_form', 'action_link', 'convert_ports', 'confirm', 'rebuild_scan_dates', 'busy_message'] as $function) {
	mactrack_test_load_function('mactrack_utilities.php', 'mactrack_utilities_' . $function);
}
mactrack_test_load_function('mactrack_utilities.php', 'mactrack_utilities');

// Only Cacti's page chrome is omitted; the real handler and forms are exercised.
function top_header() {
}
function bottom_footer() {
}
function html_header($items, $columns = 1, $sort = '', $direction = '') {
}
function form_alternate_row($id = '', $selected = false) {
}

beforeEach(function () {
	$GLOBALS['__test_request_vars']    = [];
	$GLOBALS['__test_db_calls']        = [];
	$GLOBALS['__test_next_returns']    = [];
	$GLOBALS['__test_matched_returns'] = [];
	$GLOBALS['__test_config_options']  = ['path_php_binary' => '/usr/bin/php'];
});

afterEach(function () {
	$GLOBALS['__test_request_vars']    = [];
});

it('offers daily weekly and disabled in a POST form without a backup option', function () {
	$html = mactrack_utilities_post_form('mactrack_utilities_convert_ports', 'Continue');
	$dom  = new DOMDocument();
	$dom->loadHTML($html);
	$xpath = new DOMXPath($dom);

	expect($xpath->query('//form[@method="post"]'))->toHaveCount(1);
	expect($xpath->query('//select[@name="partition_scheme"]/option'))->toHaveCount(3);
	expect($xpath->query('//option[@selected]')->item(0)->getAttribute('value'))->toBe('weekly');
	expect($xpath->query('//input[@name="keep_old"]'))->toHaveCount(0);
});

it('preselects the saved setting, disabled included', function (string $saved, string $selected) {
	$GLOBALS['__test_config_options']['mt_partition_granularity'] = $saved;
	expect(mactrack_utilities_conversion_fields())->toContain('value="' . $selected . '" selected');
	expect(mactrack_utilities_post_form('mactrack_utilities_perform_db_maint', 'Continue'))->not->toContain('partition_scheme');
})->with([['day', 'daily'], ['week', 'weekly'], ['disabled', 'disabled'], ['hour', 'weekly']]);

it('always opens partition options even when routine confirmations are disabled', function () {
	$GLOBALS['__test_config_options']['mt_maint_confirm'] = '';
	$link                                                 = mactrack_utilities_action_link('mactrack_utilities_convert_ports', 'Configure');

	expect($link)->toContain('<a ', 'action=mactrack_utilities_convert_ports');
	expect($link)->not->toContain('<form');
});

it('launches the rebuild CLI with the chosen scheme from the web', function (string $scheme) {
	$GLOBALS['__test_request_vars'] = ['partition_scheme' => $scheme];
	ob_start();
	mactrack_utilities_convert_ports();
	$html     = ob_get_clean();
	$launches = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'exec_background'));

	expect($launches)->toHaveCount(1);
	expect($launches[0]['sql'])->toBe('/usr/bin/php');
	expect($launches[0]['params'][0])->toContain('cli/upgrade_partitioning.php', escapeshellarg('--partition=' . $scheme));
	expect($launches[0]['params'][0])->not->toContain('--keep-old');
	expect($html)->toContain('requested in the background');
})->with(['daily', 'weekly', 'disabled']);

it('does not launch a second rebuild while one is running', function () {
	$GLOBALS['__test_request_vars'] = ['partition_scheme' => 'weekly'];
	mactrack_test_queue_return_for('db_fetch_row_prepared', 'FROM processes', ['pid' => (string) getmypid(), 'started' => '99000', 'now' => '100000', 'timeout' => '86400']);
	ob_start();
	mactrack_utilities_convert_ports();
	$html = ob_get_clean();

	expect(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'exec_background'))->toBe([]);
	expect($html)->toContain('already running');
});

it('resumes a rebuild whose process died and left the converting flag behind', function () {
	$GLOBALS['__test_request_vars']                              = ['partition_scheme' => 'weekly'];
	$GLOBALS['__test_config_options']['mt_partition_converting'] = (string) time();
	ob_start();
	mactrack_utilities_convert_ports();
	$html = ob_get_clean();

	expect(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'exec_background'))->toHaveCount(1);
	expect($GLOBALS['__test_config_options']['mt_partition_converting'])->toBe('');
	expect($html)->toContain('requested in the background');
});

it('rejects missing or unrecognized schemes without launching a process', function ($scheme) {
	$GLOBALS['__test_request_vars'] = ['partition_scheme' => $scheme];
	ob_start();
	mactrack_utilities_convert_ports();
	$html = ob_get_clean();

	expect(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'exec_background'))->toBe([]);
	expect($html)->toContain('Select daily, weekly or disabled');
})->with([null, '', 'weekly; touch /tmp/bad', [['weekly']]]);

it('warns about the maintenance window and offers a cancel link before launching', function () {
	ob_start();
	mactrack_utilities_confirm('mactrack_utilities_convert_ports');
	$html = ob_get_clean();

	expect($html)->toContain('off hours', 'disk space', 'Scheduled scans are skipped', 'maintenance message', 'href="mactrack_utilities.php"', 'name="partition_scheme"');
	expect(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'exec_background'))->toBe([]);
});


it('reports a failed date rebuild and releases its scan lock', function () {
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'GET_LOCK', '1');
	mactrack_test_queue_return_for('db_fetch_assoc_prepared', 'information_schema.PARTITIONS', false);
	ob_start();
	mactrack_utilities_rebuild_scan_dates();
	$html = ob_get_clean();

	expect($html)->toContain('Scan date rebuild failed.');
	expect($html)->not->toContain('Scan dates rebuilt:');
	expect(implode("\n", array_column($GLOBALS['__test_db_calls'], 'sql')))->toContain('RELEASE_LOCK');
});

it('reports an empty successful date rebuild as zero', function () {
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'GET_LOCK', '1');
	ob_start();
	mactrack_utilities_rebuild_scan_dates();
	$html = ob_get_clean();

	expect($html)->toContain('Scan dates rebuilt: 0');
});
