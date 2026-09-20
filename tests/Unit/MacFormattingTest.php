<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Converted from the standalone tests/Unit/test_mac_formatting.php script.
 * An unset or unrecognised mt_mac_format used to fall off the end of
 * mactrack_format_mac() and blank every address; these guard the fallback.
 */

require_once dirname(__DIR__, 2) . '/lib/mactrack_functions.php';

test('mactrack_format_mac renders every configured format, and falls back to the raw value', function ($format, $expected) {
	set_config_option('mt_mac_format', $format);

	expect(mactrack_format_mac('aabbccddeeff'))->toBe($expected);
})->with([
	['aa:bb:cc:dd:ee:ff', 'aa:bb:cc:dd:ee:ff'],
	['aa-bb-cc-dd-ee-ff', 'aa-bb-cc-dd-ee-ff'],
	['aabbccddeeff', 'aabbccddeeff'],
	['aabb-ccdd-eeff', 'aabb-ccdd-eeff'],
	['aabb.ccdd.eeff', 'aabb.ccdd.eeff'],
	['', 'aabbccddeeff'],
	['not-a-format', 'aabbccddeeff'],
]);

test('a short or null address is returned unchanged rather than split', function ($short) {
	expect(mactrack_format_mac($short))->toBe($short);
})->with([
	[null],
	[''],
	['aabbcc'],
]);
