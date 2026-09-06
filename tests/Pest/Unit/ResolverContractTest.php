<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 */

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 3) . '/lib/mactrack_dns_resolution.php';

test('configured resolver returns a PTR without invoking fallback', function () {
	$resolver = new class {
		public function query($ip_address, $type) {
			return (object) ['answer' => [(object) ['ptrdname' => 'host.example']]];
		}
	};
	$fallback_calls = [];
	$fallback       = function ($ip_address) use (&$fallback_calls) {
		$fallback_calls[] = $ip_address;

		return 'system.example';
	};

	expect(mactrack_resolve_hostname($resolver, true, '192.0.2.1', $fallback))->toBe('host.example');
	expect($fallback_calls)->toBeEmpty();
});

test('authoritative empty PTR answer does not invoke system fallback', function () {
	$resolver = new class {
		public function query($ip_address, $type) {
			return (object) ['answer' => []];
		}
	};
	$fallback_calls = [];
	$fallback       = function ($ip_address) use (&$fallback_calls) {
		$fallback_calls[] = $ip_address;

		return 'system.example';
	};

	expect(mactrack_resolve_hostname($resolver, true, '192.0.2.2', $fallback))->toBe('192.0.2.2');
	expect($fallback_calls)->toBeEmpty();
});

test('resolver finds PTR after a CNAME in a classless delegation answer', function () {
	$resolver = new class {
		public function query($ip_address, $type) {
			return (object) ['answer' => [
				(object) ['cname' => '1.0/25.2.0.192.in-addr.arpa'],
				(object) ['ptrdname' => 'delegated.example'],
			]];
		}
	};
	$fallback_calls = [];
	$fallback       = function ($ip_address) use (&$fallback_calls) {
		$fallback_calls[] = $ip_address;

		return 'system.example';
	};

	expect(mactrack_resolve_hostname($resolver, true, '192.0.2.1', $fallback))->toBe('delegated.example');
	expect($fallback_calls)->toBeEmpty();
});

test('resolver exceptions use system fallback', function () {
	$resolver = new class {
		public function query($ip_address, $type) {
			throw new Net_DNS2_Exception('unavailable');
		}
	};
	$fallback_calls = [];
	$fallback       = function ($ip_address) use (&$fallback_calls) {
		$fallback_calls[] = $ip_address;

		return 'system.example';
	};

	expect(mactrack_resolve_hostname($resolver, true, '192.0.2.3', $fallback))->toBe('system.example');
	expect($fallback_calls)->toBe(['192.0.2.3']);
});

test('disabled configured resolver uses system resolver and normalizes misses', function () {
	$fallback = function ($ip_address) {
		return false;
	};

	expect(mactrack_resolve_hostname(null, false, '192.0.2.4', $fallback))->toBe('192.0.2.4');
});

test('invalid configured resolver fails over without a fatal error', function () {
	$fallback = function ($ip_address) {
		return 'fallback.example';
	};

	expect(mactrack_resolve_hostname(null, true, '192.0.2.5', $fallback))->toBe('fallback.example');
	expect(mactrack_resolve_hostname(new stdClass(), true, '192.0.2.6', $fallback))->toBe('fallback.example');
});
