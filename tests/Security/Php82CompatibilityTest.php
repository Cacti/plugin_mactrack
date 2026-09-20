<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Verify plugin source files do not use PHP 8.3+ syntax.
 * Cacti plugins must remain compatible with the PHP 8.2 floor.
 */

	// Discovered recursively so new production PHP files are covered automatically.
	$pluginRoot = realpath(__DIR__ . '/../..');
	$files      = array();

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($pluginRoot, FilesystemIterator::SKIP_DOTS)
	);

	foreach ($iterator as $file) {
		if ($file->getExtension() !== 'php') {
			continue;
		}

		$relativeFile = ltrim(str_replace($pluginRoot, '', $file->getPathname()), DIRECTORY_SEPARATOR);
		$relativeFile = str_replace(DIRECTORY_SEPARATOR, '/', $relativeFile);

		if (strpos($relativeFile, 'tests/') === 0) {
			continue;
		}

		if (strpos($relativeFile, 'vendor/') === 0) {
			continue;
		}

		$files[] = $relativeFile;
	}

	sort($files);

	it('does not use typed class constants (PHP 8.3)', function () use ($files) {
		foreach ($files as $relativeFile) {
			$path = realpath(__DIR__ . '/../../' . $relativeFile);

			if ($path === false) {
				throw new RuntimeException("Unable to resolve required plugin source");
			}

			$contents = file_get_contents($path);

			if ($contents === false) {
				throw new RuntimeException("Unable to read required plugin source");
			}

			expect(preg_match('/\bconst\s+(?:int|string|float|bool|array|iterable|self|static|mixed|object|callable|null|false|true)\s+[A-Za-z_]/', $contents))->toBe(0,
				"{$relativeFile} uses a typed class constant which requires PHP 8.3"
			);
		}
	});

	it('does not use dynamic class constant fetch (PHP 8.3)', function () use ($files) {
		foreach ($files as $relativeFile) {
			$path = realpath(__DIR__ . '/../../' . $relativeFile);

			if ($path === false) {
				throw new RuntimeException("Unable to resolve required plugin source");
			}

			$contents = file_get_contents($path);

			if ($contents === false) {
				throw new RuntimeException("Unable to read required plugin source");
			}

			expect(preg_match('/::\s*\{\s*\$/', $contents))->toBe(0,
				"{$relativeFile} uses dynamic class constant fetch which requires PHP 8.3"
			);
		}
	});

	it('does not use the #[Override] attribute (PHP 8.3)', function () use ($files) {
		foreach ($files as $relativeFile) {
			$path = realpath(__DIR__ . '/../../' . $relativeFile);

			if ($path === false) {
				throw new RuntimeException("Unable to resolve required plugin source");
			}

			$contents = file_get_contents($path);

			if ($contents === false) {
				throw new RuntimeException("Unable to read required plugin source");
			}

			expect(preg_match('/#\[\s*\\\\?Override\s*\]/', $contents))->toBe(0,
				"{$relativeFile} uses the #[Override] attribute which requires PHP 8.3"
			);
		}
	});

	it('does not use json_validate() (PHP 8.3)', function () use ($files) {
		foreach ($files as $relativeFile) {
			$path = realpath(__DIR__ . '/../../' . $relativeFile);

			if ($path === false) {
				throw new RuntimeException("Unable to resolve required plugin source");
			}

			$contents = file_get_contents($path);

			if ($contents === false) {
				throw new RuntimeException("Unable to read required plugin source");
			}

			expect(preg_match('/\bjson_validate\s*\(/', $contents))->toBe(0,
				"{$relativeFile} uses json_validate() which requires PHP 8.3"
			);
		}
	});
