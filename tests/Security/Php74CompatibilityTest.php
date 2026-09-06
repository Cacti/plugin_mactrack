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

/*
 * Verify plugin source files do not use PHP 8.0+ syntax.
 * Cacti 1.2.x plugins must remain compatible with PHP 7.4.
 */

require_once __DIR__ . '/../Support/StandaloneTest.php';
require_once __DIR__ . '/../Support/Php74Scanner.php';
require_once __DIR__ . '/../Support/ProcessRunner.php';
require_once __DIR__ . '/../Support/TrackedPhpFiles.php';

$root = realpath(__DIR__ . '/../..');

foreach (MactrackTrackedPhpFiles::listRelative($root) as $relative_file) {
	if (preg_match('#(^|/)vendor(/|$)#', $relative_file)) {
		continue;
	}

	$path = $root . '/' . $relative_file;
	$contents = file_get_contents($path);
	MactrackStandaloneTest::assertTrue($contents !== false, "$relative_file is readable for PHP 7.4 function analysis");
	MactrackStandaloneTest::assertSame([], MactrackPhp74Scanner::violations($contents), "$relative_file avoids functions unavailable in PHP 7.4");
	$result = MactrackProcessRunner::run([PHP_BINARY, '-l', $path], null, $root);
	MactrackStandaloneTest::assertTrue($result['started'], "$relative_file syntax check starts");
	MactrackStandaloneTest::assertSame(0, $result['status'], "$relative_file parses with PHP " . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION);
}

$function_fixture = "<?php\n" . implode("();\n", MactrackPhp74Scanner::forbiddenFunctions()) . "();\n";
$expected_functions = array_map(function ($name) {
	return $name . '()';
}, MactrackPhp74Scanner::forbiddenFunctions());
MactrackStandaloneTest::assertSame($expected_functions, MactrackPhp74Scanner::violations($function_fixture), 'the PHP 7.4 gate rejects every unavailable function it tracks');
MactrackStandaloneTest::assertSame(['str_contains()'], MactrackPhp74Scanner::violations('<?php \\str_contains($value, "x");'), 'a fully qualified unavailable function trips the PHP 7.4 gate');
MactrackStandaloneTest::assertSame([], MactrackPhp74Scanner::violations('<?php // str_contains($value, "x");'), 'comments cannot trip the PHP 7.4 function gate');
$syntax_fixture = <<<'PHP'
<?php
$value?->run();
$result = match ($value) { 1 => true, default => false };
#[Example]
enum ExampleEnum { case Value; }
readonly class ExampleClass {}
PHP;
$expected_syntax = array_values(MactrackPhp74Scanner::forbiddenSyntaxTokens());
$actual_syntax = MactrackPhp74Scanner::violations($syntax_fixture);
sort($expected_syntax);
sort($actual_syntax);

if ($expected_syntax) {
	MactrackStandaloneTest::assertSame($expected_syntax, $actual_syntax, 'the current tokenizer detects every newer syntax token it exposes');
} else {
	print "PHP 7.4 exposes no PHP 8 syntax tokens; its native parser negative control supplies this gate\n";
}

$fixture = tempnam(sys_get_temp_dir(), 'mactrack-php8-syntax-');
MactrackStandaloneTest::assertTrue($fixture !== false, 'a parser negative-control fixture is allocated');

if ($fixture !== false) {
	$written = file_put_contents($fixture, "<?php\nmatch (true) { true => 'yes' };\n");
	MactrackStandaloneTest::assertTrue($written !== false, 'the parser negative-control fixture is written');
	$result = MactrackProcessRunner::run([PHP_BINARY, '-l', $fixture]);
	unlink($fixture);
	if (PHP_VERSION_ID < 80000) {
		MactrackStandaloneTest::assertTrue($result['status'] !== 0, 'the PHP 7.4 parser rejects a PHP 8-only negative control');
	} else {
		MactrackStandaloneTest::assertSame(0, $result['status'], 'the PHP 8 parser accepts the PHP 8 syntax control');
	}
}

MactrackStandaloneTest::finish('MacTrack PHP 7.4 compatibility');
