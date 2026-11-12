<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 */

require_once dirname(__DIR__, 2) . '/lib/mactrack_dns_resolution.php';

// mactrack_dns_resolution.php catches Net_DNS2_Exception, but only loads
// lib/mactrack_dns_resolution.php above; production reaches the class via
// mactrack_resolver.php's Net/DNS2.php require, which this standalone test
// does not perform.
if (!class_exists('Net_DNS2_Exception')) {
	require_once dirname(__DIR__, 2) . '/Net/DNS2/Exception.php';
}

test('configured resolver returns a PTR without invoking fallback', function () {
	$resolver = new class {
		/**
		 * Returns a response whose answer holds a single PTR record, "host.example".
		 *
		 * @param string $ip_address Address being resolved.
		 * @param string $type Record type requested.
		 * @return object
		 */
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

test('a NODATA PTR answer falls through to the system resolver', function () {
	$resolver = new class {
		/**
		 * Returns a NODATA response with an empty answer list.
		 *
		 * @param string $ip_address Address being resolved.
		 * @param string $type Record type requested.
		 * @return object
		 */
		public function query($ip_address, $type) {
			return (object) ['answer' => []];
		}
	};
	$fallback_calls = [];
	$fallback       = function ($ip_address) use (&$fallback_calls) {
		$fallback_calls[] = $ip_address;

		return 'system.example';
	};

	expect(mactrack_resolve_hostname($resolver, true, '192.0.2.2', $fallback))->toBe('system.example');
	expect($fallback_calls)->toBe(['192.0.2.2']);
});

test('a NODATA PTR answer normalizes to the address when the system resolver also fails', function () {
	$resolver = new class {
		/**
		 * Returns a NODATA response with an empty answer list.
		 *
		 * @param string $ip_address Address being resolved.
		 * @param string $type Record type requested.
		 * @return object
		 */
		public function query($ip_address, $type) {
			return (object) ['answer' => []];
		}
	};
	$fallback = function ($ip_address) {
		return false;
	};

	expect(mactrack_resolve_hostname($resolver, true, '192.0.2.2', $fallback))->toBe('192.0.2.2');
});

test('resolver finds PTR after a CNAME in a classless delegation answer', function () {
	$resolver = new class {
		/**
		 * Returns an answer with a CNAME record followed by a PTR record, as in a classless delegation.
		 *
		 * @param string $ip_address Address being resolved.
		 * @param string $type Record type requested.
		 * @return object
		 */
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
		/**
		 * Always throws Net_DNS2_Exception to simulate an unavailable resolver.
		 *
		 * @param string $ip_address Address being resolved.
		 * @param string $type Record type requested.
		 * @return object
		 */
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
