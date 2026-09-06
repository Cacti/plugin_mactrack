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

$workflow_root = realpath(__DIR__ . '/../../.github/workflows');
$workflow_branches = function ($path, $event) {
	$source = file_get_contents($path);
	MactrackStandaloneTest::assertTrue($source !== false, basename($path) . ' is readable');
	$pattern = '/^  ' . preg_quote($event, '/') . ":\\R    branches:\\R((?:      - [^\\r\\n]+\\R?)+)/m";
	$matched = preg_match($pattern, $source, $section);
	MactrackStandaloneTest::assertSame(1, $matched, basename($path) . " declares $event branches");
	preg_match_all('/^      - ([A-Za-z0-9._\\/-]+)$/m', $section[1], $branches);
	$branches = array_values(array_unique($branches[1]));
	sort($branches);

	return $branches;
};

$suite = $workflow_root . '/test-suite.yml';
$siblings = [
	$workflow_root . '/code-quality.yml',
	$workflow_root . '/plugin-ci-workflow.yml',
];
$expected = ['develop', 'main'];

foreach (['push', 'pull_request'] as $event) {
	$suite_branches = $workflow_branches($suite, $event);
	MactrackStandaloneTest::assertSame($expected, $suite_branches, "the cohesive suite protects main and develop on $event");

	foreach ($siblings as $sibling) {
		MactrackStandaloneTest::assertSame(
			$workflow_branches($sibling, $event),
			$suite_branches,
			'the cohesive suite matches ' . basename($sibling) . " on $event"
		);
	}
}

MactrackStandaloneTest::finish('MacTrack workflow branch coverage');
