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

$source = file_get_contents(realpath(__DIR__ . '/../../setup.php'));
$contracts = [
	'function plugin_mactrack_install',
	'function plugin_mactrack_version',
	'function plugin_mactrack_uninstall',
	"parse_ini_file(\$config['base_path'] . '/plugins/mactrack/INFO', true)",
	"return \$info['info']",
	'mactrack_setup_table_new($operator_initiated)',
	"mactrack_setup_database(PHP_SAPI !== 'cli')",
	'plugin_mactrack_install(false)',
];

foreach ($contracts as $contract) {
	MactrackStandaloneTest::assertContains($contract, $source, "setup.php contains $contract");
}

MactrackStandaloneTest::finish('MacTrack setup structure');
