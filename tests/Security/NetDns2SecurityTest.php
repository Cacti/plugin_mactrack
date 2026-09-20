<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

require_once __DIR__ . '/../../Net/DNS2.php';

class MactrackNetDns2TestCache extends Net_DNS2_Cache {
	public function seed($key, $value) {
		$this->cache_serializer = 'serialize';
		$this->cache_data[$key] = ['object' => serialize($value)];
	}
}

class MactrackNetDns2UnserializeProbe {
	public static $awakened = false;

	public function __wakeup() {
		self::$awakened = true;
	}
}

describe('Net_DNS2 cache deserialization safety in mactrack', function () {
	it('restores a valid DNS response payload from the cache', function () {
		$cache      = new MactrackNetDns2TestCache();
		$reflection = new ReflectionClass('Net_DNS2_Packet_Response');
		$response   = $reflection->newInstanceWithoutConstructor();

		$response->rdata      = '';
		$response->rdlength   = 0;
		$response->header     = null;
		$response->question   = [];
		$response->answer     = [];
		$response->authority  = [];
		$response->additional = [];

		$cache->seed('response', $response);

		expect($cache->get('response'))->toBeInstanceOf(Net_DNS2_Packet_Response::class);
	});

	it('refuses to instantiate an unexpected class from a cache payload', function () {
		MactrackNetDns2UnserializeProbe::$awakened = false;

		$cache = new MactrackNetDns2TestCache();
		$cache->seed('probe', new MactrackNetDns2UnserializeProbe());

		expect($cache->get('probe'))->toBeFalse();
		expect(MactrackNetDns2UnserializeProbe::$awakened)->toBeFalse();
	});

	it('disables arbitrary class construction for both cache metadata loads', function () {
		foreach (['Net/DNS2/Cache/File.php', 'Net/DNS2/Cache/Shm.php'] as $relative) {
			$source = file_get_contents(__DIR__ . '/../../' . $relative);

			expect($source)->not->toBeFalse("Unable to read {$relative}");
			expect(substr_count($source, "['allowed_classes' => false]"))->toBe(2, "{$relative} must disable classes for both metadata loads");
		}
	});
});

describe('Net_DNS2 include-path precedence in mactrack', function () {
	it('loads the bundled resolver even when a same-named class exists earlier on the include path', function () {
		$temporary = sys_get_temp_dir() . '/mactrack_dns_shadow_' . getmypid();
		$shadow    = $temporary . '/Net/DNS2';

		expect(mkdir($shadow, 0700, true))->toBeTrue('Unable to create the DNS shadow fixture directory');
		expect(file_put_contents($shadow . '/Resolver.php', "<?php\nclass Mactrack_Shadow_Net_DNS2_Resolver {}\n"))->not->toBeFalse();

		$plugin_root = realpath(__DIR__ . '/../..');
		set_include_path($plugin_root . PATH_SEPARATOR . $temporary . PATH_SEPARATOR . get_include_path());

		try {
			require_once $plugin_root . '/Net/DNS2.php';

			expect(class_exists('Net_DNS2_Resolver'))->toBeTrue('Bundled Net_DNS2 resolver did not autoload');

			$reflection = new ReflectionClass('Net_DNS2_Resolver');
			$resolved   = realpath((string) $reflection->getFileName());
			$expected   = realpath($plugin_root . '/Net/DNS2/Resolver.php');

			expect($resolved)->toBe($expected, 'A shadowed path resolved the bundled resolver to the wrong file');
		} finally {
			@unlink($shadow . '/Resolver.php');
			@rmdir($shadow);
			@rmdir($temporary . '/Net');
			@rmdir($temporary);
		}
	});
});
