<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Converted from the standalone tests/Unit/test_net_dns2_precedence.php
 * script. Confirms the bundled Net/DNS2.php autoloader always resolves its
 * own classes even when another Net/DNS2 tree sits earlier on the include
 * path (guards against a system package silently shadowing the bundle).
 */

it('never lets an earlier include-path entry shadow the bundled Net_DNS2 resolver', function () {
	$temporary = sys_get_temp_dir() . '/mactrack_dns_shadow_' . getmypid();
	$shadow    = $temporary . '/Net/DNS2';

	expect(mkdir($shadow, 0700, true))->toBeTrue();
	expect(file_put_contents($shadow . '/Resolver.php', "<?php\nclass Net_DNS2_Resolver {}\n"))->not->toBeFalse();

	$plugin_root = dirname(__DIR__, 2);

	try {
		set_include_path($plugin_root . PATH_SEPARATOR . $temporary . PATH_SEPARATOR . get_include_path());
		require_once $plugin_root . '/Net/DNS2.php';

		expect(class_exists('Net_DNS2_Resolver'))->toBeTrue();

		$reflection = new ReflectionClass('Net_DNS2_Resolver');
		$resolved   = realpath((string) $reflection->getFileName());
		$expected   = realpath($plugin_root . '/Net/DNS2/Resolver.php');

		expect($resolved)->toBe($expected);
	} finally {
		unlink($shadow . '/Resolver.php');
		rmdir($shadow);
		rmdir($temporary . '/Net');
		rmdir($temporary);
	}
});
