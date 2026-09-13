<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

$tracked = shell_exec("git ls-files '*.php'");

if (!is_string($tracked) || trim($tracked) === '') {
	fwrite(STDERR, "Unable to enumerate tracked PHP files\n");
	exit(1);
}

$failed = 0;

foreach (preg_split('/\R/', trim($tracked)) as $file) {
	if ($file === '' || strpos($file, 'Net/') === 0 || strpos($file, 'tests/') === 0) {
		continue;
	}

	$source = file_get_contents($file);

	if ($source === false) {
		fwrite(STDERR, "Unable to read $file\n");
		$failed++;
		continue;
	}

	foreach (['str_contains', 'str_starts_with', 'str_ends_with'] as $php8_function) {
		if (preg_match('/\\b' . $php8_function . '\\s*\\(/', $source)) {
			fwrite(STDERR, "$file uses PHP 8 function $php8_function\n");
			$failed++;
		}
	}

	if (strpos($source, '?->') !== false) {
		fwrite(STDERR, "$file uses the PHP 8 nullsafe operator\n");
		$failed++;
	}
}

if ($failed) {
	exit(1);
}

print "OK\n";
