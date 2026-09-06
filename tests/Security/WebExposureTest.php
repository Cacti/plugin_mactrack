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

require_once __DIR__ . '/../Support/StandaloneTest.php';
require_once __DIR__ . '/../Support/CliGuard.php';

$index   = file_get_contents(__DIR__ . '/../index.php');
$access  = trim(file_get_contents(__DIR__ . '/../.htaccess'));
$exports = file_get_contents(__DIR__ . '/../../.gitattributes');

MactrackStandaloneTest::assertContains("header('Location:../index.php')", $index, 'the test index redirects web requests');
MactrackStandaloneTest::assertSame('Require all denied', $access, 'Apache access is denied');
MactrackStandaloneTest::assertContains('tests/ export-ignore', $exports, 'release archives exclude tests');

$root = realpath(__DIR__ . '/..');
$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
	if ($file->getExtension() !== 'php' || $file->getPathname() === $root . '/index.php') {
		continue;
	}

	$relative = substr($file->getPathname(), strlen($root) + 1);
	$source = file_get_contents($file->getPathname());
	MactrackStandaloneTest::assertTrue(MactrackCliGuard::isFirstStatement($source), "$relative starts with an effective CLI exit guard");
}

MactrackStandaloneTest::assertTrue(
	!MactrackCliGuard::isFirstStatement("<?php\n// PHP_SAPI !== 'cli'\nprint 'unsafe';\n"),
	'a guard mentioned only in a comment is rejected'
);
MactrackStandaloneTest::assertTrue(
	!MactrackCliGuard::isFirstStatement("<?php\nrequire_once 'production.php';\nif (PHP_SAPI !== 'cli') { exit(1); }\n"),
	'a guard after executable code is rejected'
);

MactrackStandaloneTest::finish('MacTrack test-harness web exposure');
