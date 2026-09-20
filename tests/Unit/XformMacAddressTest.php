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
 * xform_mac_address() normalizes the several wire formats SNMP/ASCII MAC
 * addresses arrive in (raw binary octets, colon/dash/HEX-prefixed ASCII)
 * down to one bare uppercase hex string.
 */
final class XformMacAddressTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('lib/mactrack_functions.php');
	}

	/**
	 * @return void
	 */
	public function testEmptyAddressIsReportedAsNotUser(): void {
		$this->assertSame('NOT USER', xform_mac_address(''));
		$this->assertSame('NOT USER', xform_mac_address('   '));
	}

	/**
	 * @return void
	 */
	public function testColonDelimitedAsciiAddressIsNormalized(): void {
		$this->assertSame('AABBCCDDEEFF', xform_mac_address('aa:bb:cc:dd:ee:ff'));
	}

	/**
	 * @return void
	 */
	public function testDashDelimitedAsciiAddressIsNormalized(): void {
		$this->assertSame('AABBCCDDEEFF', xform_mac_address('aa-bb-cc-dd-ee-ff'));
	}

	/**
	 * @return void
	 */
	public function testHexPrefixedAddressStripsThePrefix(): void {
		$this->assertSame('AABBCCDDEEFF', xform_mac_address('HEX-00:aa:bb:cc:dd:ee:ff'));
		$this->assertSame('AABBCCDDEEFF', xform_mac_address('HEX-aa:bb:cc:dd:ee:ff'));
	}

	/**
	 * @return void
	 */
	public function testQuotedAndSpacedAddressIsCleaned(): void {
		$this->assertSame('AABBCCDDEEFF', xform_mac_address('"aa bb cc dd ee ff"'));
	}

	/**
	 * @return void
	 */
	public function testShortBinaryOctetsAreConvertedFromBinary(): void {
		// 6 raw binary bytes (<= 10 chars), as SNMP returns them, rather than
		// an already-ASCII-formatted address.
		$binary = "\xaa\xbb\xcc\xdd\xee\xff";

		$this->assertSame('AABBCCDDEEFF', xform_mac_address($binary));
	}
}
