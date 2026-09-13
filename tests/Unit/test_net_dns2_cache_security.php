<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once __DIR__ . '/../../Net/DNS2.php';

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

if (!($cache->get('response') instanceof Net_DNS2_Packet_Response)) {
	fwrite(STDERR, "Expected a valid DNS response cache payload to survive the allowlist\n");
	exit(1);
}

$cache->seed('probe', new Mactrack_Net_DNS2_Unserialize_Probe());

if ($cache->get('probe') !== false || Mactrack_Net_DNS2_Unserialize_Probe::$awakened) {
	fwrite(STDERR, "Unexpected classes must not be instantiated from DNS cache payloads\n");
	exit(1);
}

$cacheSources = [
	__DIR__ . '/../../Net/DNS2/Cache/File.php',
	__DIR__ . '/../../Net/DNS2/Cache/Shm.php',
];

foreach ($cacheSources as $cacheSource) {
	$source = file_get_contents($cacheSource);

	if ($source === false || substr_count($source, "['allowed_classes' => false]") !== 2) {
		fwrite(STDERR, basename($cacheSource) . " must disable classes for both metadata loads\n");
		exit(1);
	}
}

print "Net_DNS2 cache deserialization checks passed\n";
