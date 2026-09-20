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

/**
 * mactrack_format_mac() used to fall off the end of its format switch for an
 * unset or unrecognised mt_mac_format, blanking every address. Locks in the
 * fallback-to-raw-hex behavior for that case alongside every known format.
 */
final class MacFormattingTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('lib/mactrack_functions.php');
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function macFormatProvider() {
		return [
			'colon'        => ['aa:bb:cc:dd:ee:ff', 'aa:bb:cc:dd:ee:ff'],
			'dash'         => ['aa-bb-cc-dd-ee-ff', 'aa-bb-cc-dd-ee-ff'],
			'raw'          => ['aabbccddeeff', 'aabbccddeeff'],
			'quad-dash'    => ['aabb-ccdd-eeff', 'aabb-ccdd-eeff'],
			'dot'          => ['aabb.ccdd.eeff', 'aabb.ccdd.eeff'],
			'unset'        => ['', 'aabbccddeeff'],
			'unrecognised' => ['not-a-format', 'aabbccddeeff'],
		];
	}

	/**
	 * @dataProvider macFormatProvider
	 *
	 * @param string $format
	 * @param string $expected
	 *
	 * @return void
	 */
	public function testFormatsAccordingToMtMacFormat($format, $expected): void {
		set_config_option('mt_mac_format', $format);

		$this->assertSame($expected, mactrack_format_mac('aabbccddeeff'));
	}

	/**
	 * @return void
	 */
	public function testShortOrNullAddressIsReturnedUnchanged(): void {
		foreach ([null, '', 'aabbcc'] as $short) {
			$this->assertSame($short, mactrack_format_mac($short));
		}
	}
}
