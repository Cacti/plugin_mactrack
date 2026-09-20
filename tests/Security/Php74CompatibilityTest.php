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
 * mactrack_test_production_php_files() is defined in tests/bootstrap-unit.php
 * so this file can share it with Php82CompatibilityTest.php and
 * PreparedStatementConsistencyTest.php.
 *
 * README.md documents a PHP 7.4 support floor for the plugin's production
 * code, so this guards against PHP 8.0+-only syntax creeping in even though
 * the CI matrix itself only runs PHP 8.2+.
 */
describe('PHP 7.4 compatibility in mactrack', function () {
	$files = mactrack_test_production_php_files();

	it('does not use PHP 8 string helper functions', function () use ($files) {
		foreach ($files as $f) {
			$c = file_get_contents(__DIR__ . '/../../' . $f);
			if ($c === false) continue;

			foreach (['str_contains', 'str_starts_with', 'str_ends_with'] as $php8_function) {
				expect(preg_match('/\b' . $php8_function . '\s*\(/', $c))->toBe(0,
					"{$f} uses PHP 8 function {$php8_function}()"
				);
			}
		}
	});

	it('does not use the nullsafe operator (PHP 8.0)', function () use ($files) {
		foreach ($files as $f) {
			$c = file_get_contents(__DIR__ . '/../../' . $f);
			if ($c === false) continue;
			expect(strpos($c, '?->'))->toBe(false, "{$f} uses the PHP 8 nullsafe operator");
		}
	});

	it('does not use the match expression (PHP 8.0)', function () use ($files) {
		foreach ($files as $f) {
			$c = file_get_contents(__DIR__ . '/../../' . $f);
			if ($c === false) continue;
			expect(preg_match('/\bmatch\s*\(/', $c))->toBe(0, "{$f} uses the PHP 8 match expression");
		}
	});
});
