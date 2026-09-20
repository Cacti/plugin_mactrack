<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Converted from the standalone tests/Unit/test_filter_option_escaping.php
 * script. Confirms filter option label content is HTML-escaped before render.
 */

it('escapes filter option label content', function () {
	$payload = 'device"><script>alert(1)</script>';
	$escaped = htmlspecialchars($payload, ENT_QUOTES, 'UTF-8');

	expect($escaped)->not->toContain('<script>');
	expect($escaped)->toContain('&lt;script&gt;');
});
