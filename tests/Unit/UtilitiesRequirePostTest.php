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
 * Cacti checks the CSRF token on POST requests only. Every Utilities
 * action that changes data therefore runs only on a POST; a GET, which a
 * link can send, gets a confirmation form that posts the action back.
 */

require_once dirname(__DIR__, 2) . '/lib/mactrack_functions.php';

const MT_UP_ACTIONS = array(
	'mactrack_utilities_truncate_ports_table',
	'mactrack_utilities_perform_db_maint',
	'mactrack_utilities_purge_scanning_funcs',
	'mactrack_utilities_purge_aggregated_data',
	'mactrack_utilities_recreate_aggregated_data',
	'mactrack_refresh_oui_database',
	'mactrack_utilities_rebuild_scan_dates',
	'mactrack_utilities_optimize_ports',
	'mactrack_utilities_convert_ports',
);

it('lists every Utilities action that changes data', function () {
	expect(array_keys(mactrack_utilities_post_actions()))->toEqualCanonicalizing(MT_UP_ACTIONS);
});

it('gives each such action a confirmation message', function () {
	foreach (mactrack_utilities_post_actions() as $message) {
		expect($message)->toBeString()->not->toBe('');
	}
});

it('runs a data-changing action only on a POST', function (string $method, bool $runs) {
	foreach (MT_UP_ACTIONS as $action) {
		expect(mactrack_utilities_may_run($action, $method))->toBe($runs);
	}
})->with(array(
	'POST'      => array('POST', true),
	'GET'       => array('GET', false),
	'HEAD'      => array('HEAD', false),
	'no method' => array('', false),
	'lowercase' => array('post', false),
));

it('lets read-only pages through on any method', function (string $action) {
	expect(mactrack_utilities_may_run($action, 'GET'))->toBeTrue();
})->with(array('mactrack_proc_status', '', 'unknown'));

it('gates the dispatcher before any action runs', function () {
	$src  = file_get_contents(dirname(__DIR__, 2) . '/mactrack_utilities.php');
	$gate = strpos($src, "mactrack_utilities_may_run(get_request_var('action'), \$_SERVER['REQUEST_METHOD'] ?? '')");

	expect($gate)->not->toBeFalse();
	expect($gate)->toBeLessThan(strpos($src, "switch (get_request_var('action'))"));
});

it('no longer lets a confirm parameter stand in for the confirmation', function () {
	$src = file_get_contents(dirname(__DIR__, 2) . '/mactrack_utilities.php');

	expect($src)->not->toContain("isset_request_var('confirm')");
	expect($src)->not->toContain('form_confirm(');
});

it('posts the confirmed action back with a form Cacti adds its token to', function () {
	$src = file_get_contents(dirname(__DIR__, 2) . '/mactrack_utilities.php');

	// Cacti's output filter adds the token to forms with method="post".
	expect($src)->toContain('<form method="post" action="mactrack_utilities.php">');

	foreach (MT_UP_ACTIONS as $action) {
		expect($src)->not->toContain("href='mactrack_utilities.php?action=$action'");
		expect($src)->toContain("mactrack_utilities_action_link('$action'");
	}
});
