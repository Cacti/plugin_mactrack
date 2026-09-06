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

final class MactrackProcessRunner {
	public static function run(array $command, $environment = null, $working_directory = null) {
		$stdout_path = tempnam(sys_get_temp_dir(), 'mactrack-stdout-');
		$stderr_path = tempnam(sys_get_temp_dir(), 'mactrack-stderr-');

		if ($stdout_path === false || $stderr_path === false) {
			self::cleanup([$stdout_path, $stderr_path]);

			return ['started' => false, 'status' => -1, 'output' => '', 'error' => 'Unable to allocate process output files'];
		}

		$child_environment = getenv();

		if (!is_array($child_environment)) {
			$child_environment = $_ENV;
		}

		foreach (['GIT_DIR', 'GIT_WORK_TREE', 'GIT_INDEX_FILE', 'GIT_PREFIX'] as $git_variable) {
			unset($child_environment[$git_variable]);
		}

		if (is_array($environment)) {
			$child_environment = array_merge($child_environment, $environment);
		}

		$process = proc_open(
			$command,
			[0 => STDIN, 1 => ['file', $stdout_path, 'w'], 2 => ['file', $stderr_path, 'w']],
			$pipes,
			$working_directory,
			$child_environment
		);

		if (!is_resource($process)) {
			self::cleanup([$stdout_path, $stderr_path]);

			return ['started' => false, 'status' => -1, 'output' => '', 'error' => 'Unable to start process'];
		}

		$status = proc_close($process);
		$output = file_get_contents($stdout_path);
		$error  = file_get_contents($stderr_path);
		self::cleanup([$stdout_path, $stderr_path]);

		return [
			'started' => true,
			'status'  => $status,
			'output'  => $output === false ? '' : $output,
			'error'   => $error === false ? '' : $error,
		];
	}

	private static function cleanup(array $paths) {
		foreach ($paths as $path) {
			if (is_string($path) && is_file($path)) {
				unlink($path);
			}
		}
	}
}
