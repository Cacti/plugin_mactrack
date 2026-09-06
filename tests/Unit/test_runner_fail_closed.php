<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

if (PHP_SAPI !== 'cli') {
	exit(1);
}

require_once __DIR__ . '/../Support/StandaloneTest.php';
require_once __DIR__ . '/../Support/ProcessRunner.php';
require_once __DIR__ . '/../Support/TestInventory.php';

$git_environment = [];

foreach (['GIT_DIR', 'GIT_WORK_TREE', 'GIT_INDEX_FILE', 'GIT_PREFIX'] as $git_variable) {
	$git_environment[$git_variable] = getenv($git_variable);
	putenv($git_variable . '=/definitely-not-the-test-repository');
}

$environment_result = MactrackProcessRunner::run([
	PHP_BINARY,
	'-r',
	'foreach (["GIT_DIR", "GIT_WORK_TREE", "GIT_INDEX_FILE", "GIT_PREFIX"] as $name) { if (getenv($name) !== false) { exit(1); } }',
]);
MactrackStandaloneTest::assertSame(0, $environment_result['status'], 'child processes cannot inherit Git repository or index overrides');

foreach ($git_environment as $git_variable => $value) {
	putenv($value === false ? $git_variable : $git_variable . '=' . $value);
}

$inventory_root = __DIR__ . '/../fixtures/inventory';
$claimed_inventory_files = [
	$inventory_root . '/Unit/test_claimed.php',
	$inventory_root . '/Security/ClaimedTest.php',
];
$unclaimed_inventory_files = MactrackTestInventory::findUnclaimed(
	[$inventory_root . '/Unit', $inventory_root . '/Security'],
	$claimed_inventory_files
);
MactrackStandaloneTest::assertSame(
	[$inventory_root . '/Security/test_orphan.php', $inventory_root . '/Unit/OrphanTest.php'],
	$unclaimed_inventory_files,
	'the inventory rejects both mixed-convention orphan test names'
);

$command = [PHP_BINARY, __DIR__ . '/../run.php', 'empty-fixture'];
$result  = MactrackProcessRunner::run($command, ['MACTRACK_RUNNER_SELF_TEST' => '1']);

MactrackStandaloneTest::assertTrue($result['started'], 'runner self-test starts');
MactrackStandaloneTest::assertSame(1, $result['status'], 'an empty requested group fails closed');
MactrackStandaloneTest::assertContains('No test files matched pattern:', $result['output'] . $result['error'], 'an empty pattern in a populated group is actionable');

$silent_command = [PHP_BINARY, __DIR__ . '/../run.php', 'silent-fixture'];
$silent_result  = MactrackProcessRunner::run($silent_command, ['MACTRACK_RUNNER_SELF_TEST' => '1']);

MactrackStandaloneTest::assertTrue($silent_result['started'], 'silent runner self-test starts');
MactrackStandaloneTest::assertSame(1, $silent_result['status'], 'a silent standalone file fails closed');
MactrackStandaloneTest::assertContains('did not report any completed assertions', $silent_result['output'] . $silent_result['error'], 'silent test failure is actionable');

$stderr_command = [PHP_BINARY, __DIR__ . '/../run.php', 'stderr-fixture'];
$stderr_result  = MactrackProcessRunner::run($stderr_command, ['MACTRACK_RUNNER_SELF_TEST' => '1']);

MactrackStandaloneTest::assertTrue($stderr_result['started'], 'large-stderr runner self-test starts');
MactrackStandaloneTest::assertSame(1, $stderr_result['status'], 'large stderr fails without deadlocking the runner');
MactrackStandaloneTest::assertContains('test_large_stderr.php failed with status 1', $stderr_result['error'], 'large-stderr failure remains actionable');

$warning_command = [PHP_BINARY, __DIR__ . '/../run.php', 'warning-fixture'];
$warning_result  = MactrackProcessRunner::run($warning_command, ['MACTRACK_RUNNER_SELF_TEST' => '1']);

MactrackStandaloneTest::assertTrue($warning_result['started'], 'warning runner self-test starts');
MactrackStandaloneTest::assertSame(1, $warning_result['status'], 'stderr fails even when a test process exits successfully');
MactrackStandaloneTest::assertContains('test_warning.php wrote to stderr', $warning_result['error'], 'stderr-only failure is actionable');
MactrackStandaloneTest::finish('MacTrack runner fail-closed behavior');
