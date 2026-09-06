<?php

if (PHP_SAPI !== 'cli') {
	exit(1);
}
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once __DIR__ . '/ProcessRunner.php';

final class MactrackTrackedPhpFiles {
	public static function listRelative($root, $git = null) {
		if ($git === null) {
			$git = self::findExecutable('git');
		}

		if ($git === null || !is_file($git) || !is_executable($git)) {
			throw new RuntimeException('Unable to enumerate tracked PHP files: git is unavailable');
		}

		$top_level = MactrackProcessRunner::run([$git, '-C', $root, 'rev-parse', '--show-toplevel']);

		if (!$top_level['started'] || $top_level['status'] !== 0 || realpath(trim($top_level['output'])) !== realpath($root)) {
			throw new RuntimeException('Unable to enumerate tracked PHP files: repository root mismatch');
		}

		$result = MactrackProcessRunner::run([$git, '-C', $root, 'ls-files', '-z', '--', '*.php']);

		if (!$result['started'] || $result['status'] !== 0 || $result['output'] === '') {
			throw new RuntimeException('Unable to enumerate tracked PHP files: ' . $result['error']);
		}

		$files = array_values(array_filter(explode("\0", $result['output']), 'strlen'));

		sort($files);

		return $files;
	}

	private static function findExecutable($name) {
		foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $directory) {
			$path = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;

			if (is_file($path) && is_executable($path)) {
				return $path;
			}
		}

		return null;
	}
}
