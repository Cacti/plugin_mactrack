<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * These exercise the real xform_mac_address(). An earlier version of this file
 * carried a hand-copied mirror of the function, which kept passing after the
 * production behaviour changed and certified the old contract instead of the
 * new one. The function touches neither the database nor settings, so it can be
 * exercised directly.
 */

// tests/bootstrap.php is loaded by PHPUnit before any test file and declares
// the Cacti helpers behind function_exists() guards, so nothing else is needed
// here.  Support/CactiStubs.php is a Psalm stub, declares the same names
// unguarded, and fatals the whole suite if it is loaded at runtime.
require_once dirname(__DIR__, 3) . '/lib/mactrack_functions.php';

test('an interface with no hardware address stays empty', function ($input) {
	// mac_track_interfaces.ifPhysAddress is a MAC column. A VLAN SVI, loopback
	// or tunnel reports an empty address and must not pick up a placeholder.
	expect(xform_mac_address($input))->toBe('');
})->with([
	[''],
	['   '],
	[null],
]);

test('ASCII and HEX- forms strip delimiters', function ($input) {
	expect(xform_mac_address($input))->toBe('AABBCCDDEEFF');
})->with([
	['aa-bb-cc-dd-ee-ff'],
	['HEX-00:aa:bb:cc:dd:ee:ff'],
	['aa:bb:cc:dd:ee:ff'],
	['AA:BB:CC:DD:EE:FF'],
]);

test('binary hex bytes convert to an uppercase hex string', function () {
	expect(xform_mac_address(hex2bin('aabbccddeeff')))->toBe('AABBCCDDEEFF');
});

test('the transformed value is returned, not the trimmed input', function () {
	// The historical $max_address typo discarded the transform and returned the
	// input instead. Any of the cases above would catch it; this states it.
	expect(xform_mac_address(' aa-bb-cc-dd-ee-ff '))->toBe('AABBCCDDEEFF');
});

test('macauth schedule persists last-run time', function () {
	$src = file_get_contents(dirname(__DIR__, 3) . '/poller_mactrack.php');

	expect($src)->toContain("set_config_option('mt_last_macauth_time'");
	expect($src)->toContain('$last_macauth_time + ($mac_auth_frequency * 60) < time()');
});
