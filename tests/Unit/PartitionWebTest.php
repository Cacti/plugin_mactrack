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

foreach (['conversion_fields', 'post_form', 'action_link', 'convert_ports', 'confirm'] as $function) {
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

function mt_pw_layout(bool $partitioned): void {
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'VERSION()', '10.5.29-MariaDB');
	mactrack_test_queue_return_for('db_fetch_assoc_prepared', 'information_schema.PARTITIONS', $partitioned ? [[
		'PARTITION_NAME'       => 'dMaxValue', 'PARTITION_ORDINAL_POSITION' => '1', 'PARTITION_METHOD' => 'RANGE',
		'PARTITION_EXPRESSION' => 'unix_timestamp(`scan_date`)', 'PARTITION_DESCRIPTION' => 'MAXVALUE', 'TABLE_ROWS' => '0',
	]] : []);
}

it('offers daily weekly disabled and the backup option in a POST form', function () {
	mt_pw_layout(true);
	$html = mactrack_utilities_post_form('mactrack_utilities_convert_ports', 'Continue');
	$dom  = new DOMDocument();
	$dom->loadHTML($html);
	$xpath = new DOMXPath($dom);

	expect($xpath->query('//form[@method="post"]'))->toHaveCount(1);
	expect($xpath->query('//select[@name="partition_scheme"]/option'))->toHaveCount(3);
	expect($xpath->query('//option[@selected]')->item(0)->getAttribute('value'))->toBe('weekly');
	expect($xpath->query('//input[@name="keep_old"]'))->toHaveCount(1);
});

it('preselects disabled for an unpartitioned table whatever the size setting says', function () {
	mt_pw_layout(false);
	$GLOBALS['__test_config_options']['mt_partition_granularity'] = 'day';
	expect(mactrack_utilities_conversion_fields())->toContain('value="disabled" selected');
	expect(mactrack_utilities_post_form('mactrack_utilities_perform_db_maint', 'Continue'))->not->toContain('partition_scheme');
});

it('preselects the saved size for a partitioned table', function () {
	mt_pw_layout(true);
	$GLOBALS['__test_config_options']['mt_partition_granularity'] = 'day';
	expect(mactrack_utilities_conversion_fields())->toContain('value="daily" selected');
});

it('always opens partition options even when routine confirmations are disabled', function () {
	$GLOBALS['__test_config_options']['mt_maint_confirm'] = '';
	$link                                                 = mactrack_utilities_action_link('mactrack_utilities_convert_ports', 'Configure');

	expect($link)->toContain('<a ', 'action=mactrack_utilities_convert_ports');
	expect($link)->not->toContain('<form');
});

it('launches the same CLI options from the web', function (string $scheme, bool $keep) {
	$GLOBALS['__test_request_vars'] = ['partition_scheme' => $scheme, 'keep_old' => $keep ? 'on' : ''];
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'VERSION()', '8.4.3');
	ob_start();
	mactrack_utilities_convert_ports();
	$html     = ob_get_clean();
	$launches = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'exec_background'));

	expect($launches)->toHaveCount(1);
	expect($launches[0]['sql'])->toBe('/usr/bin/php');
	expect($launches[0]['params'][0])->toContain(escapeshellarg('--partition=' . $scheme));
	expect(str_contains($launches[0]['params'][0], '--keep-old'))->toBe($keep);
	expect($html)->toContain('requested in the background');
})->with([['daily', false], ['weekly', true], ['disabled', true]]);

it('rejects missing or unrecognized schemes without launching a process', function ($scheme) {
	$GLOBALS['__test_request_vars'] = ['partition_scheme' => $scheme];
	mactrack_test_queue_return_for('db_fetch_cell_prepared', 'VERSION()', '8.4.3');
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

	expect($html)->toContain('off hours', 'disk space', 'Scheduled scans are skipped and database maintenance waits', 'href="mactrack_utilities.php"', 'name="partition_scheme"');
	expect(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'exec_background'))->toBe([]);
});
