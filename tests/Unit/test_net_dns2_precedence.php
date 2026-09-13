<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

$temporary = sys_get_temp_dir() . '/mactrack_dns_shadow_' . getmypid();
$shadow    = $temporary . '/Net/DNS2';

if (!mkdir($shadow, 0700, true) || file_put_contents($shadow . '/Resolver.php', "<?php\nclass Net_DNS2_Resolver {}\n") === false) {
	fwrite(STDERR, "Unable to create the DNS shadow fixture\n");
	exit(1);
}

$plugin_root = dirname(__DIR__, 2);
set_include_path($plugin_root . PATH_SEPARATOR . $temporary . PATH_SEPARATOR . get_include_path());
require_once $plugin_root . '/Net/DNS2.php';

$failed = 0;

if (!class_exists('Net_DNS2_Resolver')) {
	fwrite(STDERR, "Bundled Net_DNS2 resolver did not autoload\n");
	$failed++;
} else {
	$reflection = new ReflectionClass('Net_DNS2_Resolver');
	$resolved   = realpath((string) $reflection->getFileName());
	$expected   = realpath($plugin_root . '/Net/DNS2/Resolver.php');

	if ($resolved !== $expected) {
		fwrite(STDERR, "A system Net_DNS2 package shadowed the bundled resolver\n");
		$failed++;
	}
}

unlink($shadow . '/Resolver.php');
rmdir($shadow);
rmdir($temporary . '/Net');
rmdir($temporary);

if ($failed) {
	exit(1);
}

print "OK\n";
