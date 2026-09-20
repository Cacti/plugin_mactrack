<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Converted from the standalone tests/Unit/test_net_dns2_cache_security.php
 * script. Confirms the bundled Net_DNS2 cache only unserializes DNS response
 * classes, never arbitrary objects (PHP Object Injection hardening).
 */

require_once dirname(__DIR__, 2) . '/Net/DNS2.php';

class Mactrack_Net_DNS2_Test_Cache extends Net_DNS2_Cache {
	public function seed($key, $value) {
		$this->cache_serializer = 'serialize';
		$this->cache_data[$key] = ['object' => serialize($value)];
	}
}

class Mactrack_Net_DNS2_Unserialize_Probe {
	public static $awakened = false;

	public function __wakeup() {
		self::$awakened = true;
	}
}

it('only unserializes an allowlisted DNS response class from cache payloads', function () {
	$cache                = new Mactrack_Net_DNS2_Test_Cache();
	$reflection           = new ReflectionClass('Net_DNS2_Packet_Response');
	$response             = $reflection->newInstanceWithoutConstructor();
	$response->rdata      = '';
	$response->rdlength   = 0;
	$response->header     = null;
	$response->question   = [];
	$response->answer     = [];
	$response->authority  = [];
	$response->additional = [];

	$cache->seed('response', $response);

	expect($cache->get('response'))->toBeInstanceOf(Net_DNS2_Packet_Response::class);

	$cache->seed('probe', new Mactrack_Net_DNS2_Unserialize_Probe());

	expect($cache->get('probe'))->toBeFalse();
	expect(Mactrack_Net_DNS2_Unserialize_Probe::$awakened)->toBeFalse();
});

it('disables classes for both cache metadata loads', function () {
	foreach (['Cache/File.php', 'Cache/Shm.php'] as $relative) {
		$source = plugin_test_read_source('Net/DNS2/' . $relative);

		expect(substr_count($source, "['allowed_classes' => false]"))->toBe(2);
	}
});
