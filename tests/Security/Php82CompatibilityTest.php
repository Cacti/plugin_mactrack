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
 * so this file and PreparedStatementConsistencyTest.php can share it.
 */

describe('PHP 8.2+ CI matrix compatibility in mactrack', function () {
	$files = mactrack_test_production_php_files();

	// README.md documents a PHP 8.2 support floor for production code, so
	// 8.0/8.1-only syntax is in scope and allowed. These checks instead guard
	// against syntax/functions that require PHP 8.3 or 8.4, which would break
	// the 8.2/8.3 legs of the CI matrix, plus functions removed well before
	// 8.2 that would break every leg.
	it('does not use each() (removed in PHP 8.0)', function () use ($files) {
		foreach ($files as $f) {
			$c = file_get_contents(__DIR__ . '/../../' . $f);
			if ($c === false) continue;
			expect(preg_match('/\beach\s*\(/', $c))->toBe(0, "{$f} uses each() (removed in PHP 8.0)");
		}
	});

	it('does not use create_function() (removed in PHP 8.0)', function () use ($files) {
		foreach ($files as $f) {
			$c = file_get_contents(__DIR__ . '/../../' . $f);
			if ($c === false) continue;
			expect(preg_match('/\bcreate_function\s*\(/', $c))->toBe(0, "{$f} uses create_function() (removed in PHP 8.0)");
		}
	});

	it('does not use curly-brace string offset access (removed in PHP 8.0)', function () use ($files) {
		foreach ($files as $f) {
			$c = file_get_contents(__DIR__ . '/../../' . $f);
			if ($c === false) continue;
			expect(preg_match('/\$\w+\s*\{\s*\d+\s*\}/', $c))->toBe(0, "{$f} uses curly-brace string offset access (removed in PHP 8.0)");
		}
	});

	it('does not use json_validate() (PHP 8.3)', function () use ($files) {
		foreach ($files as $f) {
			$c = file_get_contents(__DIR__ . '/../../' . $f);
			if ($c === false) continue;
			expect(preg_match('/\bjson_validate\s*\(/', $c))->toBe(0, "{$f} uses json_validate() (PHP 8.3)");
		}
	});

	it('does not use dynamic class constant fetch syntax (PHP 8.3)', function () use ($files) {
		foreach ($files as $f) {
			$c = file_get_contents(__DIR__ . '/../../' . $f);
			if ($c === false) continue;
			expect(preg_match('/\w+::\{.+\}/', $c))->toBe(0, "{$f} uses dynamic class constant fetch (PHP 8.3)");
		}
	});

	it('does not use typed class constants (PHP 8.3)', function () use ($files) {
		foreach ($files as $f) {
			$c = file_get_contents(__DIR__ . '/../../' . $f);
			if ($c === false) continue;
			expect(preg_match('/\b(?:public|private|protected|final)\s+const\s+\??[\w|]+\s+\w+\s*=/', $c))->toBe(0,
				"{$f} uses typed class constants (PHP 8.3)"
			);
		}
	});

	it('does not use the #[Override] attribute (PHP 8.4)', function () use ($files) {
		foreach ($files as $f) {
			$c = file_get_contents(__DIR__ . '/../../' . $f);
			if ($c === false) continue;
			expect(preg_match('/#\[\s*\\\\?Override\s*\]/', $c))->toBe(0, "{$f} uses #[Override] attribute (PHP 8.4)");
		}
	});

	it('does not use asymmetric visibility (PHP 8.4)', function () use ($files) {
		foreach ($files as $f) {
			$c = file_get_contents(__DIR__ . '/../../' . $f);
			if ($c === false) continue;
			expect(preg_match('/\b(?:public|protected)\s*\(\s*set\s*\)/', $c))->toBe(0,
				"{$f} uses asymmetric visibility (PHP 8.4)"
			);
		}
	});

	it('does not use property hooks (PHP 8.4)', function () use ($files) {
		foreach ($files as $f) {
			$c = file_get_contents(__DIR__ . '/../../' . $f);
			if ($c === false) continue;
			expect(preg_match('/\b(?:get|set)\s*\{/', $c))->toBe(0, "{$f} uses property hooks (PHP 8.4)");
		}
	});

	// Every tracked file's syntax is already gated by the CI workflow's
	// dedicated "Check PHP Syntax for Plugin" step (php -l over every file),
	// so it isn't duplicated here.
});
