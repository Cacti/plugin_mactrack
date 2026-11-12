<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 24-226 The Cacti Group                                 |
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
 * Covers the guard in mactrack_mail() that skips delivery when no recipient
 * is configured. An unset report email address previously reached mailer()
 * with an empty $to and produced a CMDPHP MAILER ERROR backtrace.
 *
 * tests/bootstrap-unit.php declares the Cacti helpers (cacti_log) behind
 * function_exists() guards before this file is loaded.
 */

require_once dirname(__DIR__, 2) . '/lib/mactrack_functions.php';

// A recording mailer() stub so the test can assert the early return never
// attempts delivery. mailer() is not otherwise declared in the unit harness.
if (!function_exists('mailer')) {
	function mailer($from, $to, $cc = '', $bcc = '', $replyto = '', $subject = '', $body = '', $body_text = '', $attachments = '', $headers = array(), $html = true) {
		$GLOBALS['__test_mailer_calls'][] = $to;

		return '';
	}
}

test('mactrack_mail skips delivery and logs when no recipient is set', function ($to) {
	$GLOBALS['__test_cacti_log']    = array();
	$GLOBALS['__test_mailer_calls'] = array();

	mactrack_mail($to, 'cacti@localhost', 'Cacti', 'subject', 'message');

	expect($GLOBALS['__test_mailer_calls'])->toBe(array());
	expect($GLOBALS['__test_cacti_log'])->toContain('Mactrack Report Email Address is not set');
})->with([
	'empty string' => [''],
	'null'         => [null],
]);
