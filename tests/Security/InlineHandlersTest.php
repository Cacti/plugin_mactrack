<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/*
 * Content-Security-Policy hygiene: inline event-handler attributes
 * (onClick, onChange, ...) and javascript: URLs are blocked by a strict CSP,
 * and inline <script> tags need the per-request nonce. Handlers must be bound
 * from $(function() {}) blocks instead.
 */

/**
 * Returns the plugin's first-party PHP/JS sources that emit markup.
 *
 * @return array<int, string>
 */
function mactrack_inline_handler_sources(): array {
	$root  = realpath(__DIR__ . '/../..');
	$files = [];

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
	);

	foreach ($iterator as $file) {
		$path = str_replace('\\', '/', $file->getPathname());

		if (!preg_match('/\.(php|js)$/', $path)) {
			continue;
		}

		if (preg_match('#/(Net|tests|locales|vendor|\.git|\.github)/#', $path) || preg_match('#/js/apexcharts#', $path)) {
			continue;
		}

		$files[] = $path;
	}

	sort($files);

	return $files;
}

test('no source emits inline event-handler attributes or javascript: URLs', function () {
	$offenders = [];

	foreach (mactrack_inline_handler_sources() as $path) {
		foreach (file($path) as $number => $line) {
			if (preg_match('/\son[a-z]+\s*=\s*[\'"\\\\]|javascript:/i', $line)) {
				$offenders[] = basename($path) . ':' . ($number + 1) . ' ' . trim($line);
			}
		}
	}

	expect($offenders)->toBe([]);
});

test('every inline script tag carries the CSP nonce', function () {
	$offenders = [];

	foreach (mactrack_inline_handler_sources() as $path) {
		foreach (file($path) as $number => $line) {
			if (preg_match('/<script\b/i', $line) && !preg_match('/csp_nonce\(\)/', $line) && !preg_match('/^\s*(\*|\/\/)/', $line)) {
				$offenders[] = basename($path) . ':' . ($number + 1) . ' ' . trim($line);
			}
		}
	}

	expect($offenders)->toBe([]);
});
