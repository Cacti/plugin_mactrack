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
require_once __DIR__ . '/../Support/TrackedPhpFiles.php';
require_once __DIR__ . '/../Support/SqlCallAnalyzer.php';

$root = realpath(__DIR__ . '/../..');
$manifest = require __DIR__ . '/../Support/ProductionPhpManifest.php';
$tracked = MactrackTrackedPhpFiles::listRelative($root);
$production = array_values(array_filter($tracked, function ($file) {
	return !preg_match('#(^|/)(tests|vendor)(/|$)#', $file);
}));
sort($manifest);
sort($production);

MactrackStandaloneTest::assertSame($manifest, $production, 'the production manifest equals Git-tracked PHP files when Git is available');
MactrackStandaloneTest::assertTrue(in_array('includes/database.php', $tracked, true), 'the tracked inventory includes production PHP');

$threw = false;

try {
	MactrackTrackedPhpFiles::listRelative($root, $root . '/missing-git');
} catch (RuntimeException $exception) {
	$threw = true;
}

MactrackStandaloneTest::assertTrue($threw, 'the tracked inventory fails closed when Git is unavailable');

$fixture_root = sys_get_temp_dir() . '/mactrack-git-' . bin2hex(random_bytes(8));
mkdir($fixture_root);
$unsafe_source = <<<'PHP'
<?php
db_execute("SELECT * FROM example WHERE id = $id");
str_contains($value, 'x');
PHP;
file_put_contents($fixture_root . '/tracked.php', $unsafe_source);
file_put_contents($fixture_root . '/untracked.php', "<?php\n");
$init = MactrackProcessRunner::run(['git', 'init', '--quiet'], null, $fixture_root);
$add  = MactrackProcessRunner::run(['git', 'add', 'tracked.php'], null, $fixture_root);
MactrackStandaloneTest::assertSame(0, $init['status'], 'the isolated fixture repository initializes successfully');
MactrackStandaloneTest::assertSame(0, $add['status'], 'the isolated fixture stages only its tracked source');
MactrackStandaloneTest::assertSame(['tracked.php'], MactrackTrackedPhpFiles::listRelative($fixture_root), 'an untracked production-like fixture cannot alter the gate');
MactrackStandaloneTest::assertSame(1, MactrackSqlCallAnalyzer::count($unsafe_source)['dynamic_raw'], 'the enumerated negative control trips the SQL ratchet analyzer');

$remove_tree = function ($path) use (&$remove_tree) {
	if (is_dir($path) && !is_link($path)) {
		foreach (new FilesystemIterator($path) as $entry) {
			$remove_tree($entry->getPathname());
		}
		rmdir($path);
	} elseif (file_exists($path)) {
		unlink($path);
	}
};
$remove_tree($fixture_root);

MactrackStandaloneTest::finish('MacTrack tracked-PHP inventory');
