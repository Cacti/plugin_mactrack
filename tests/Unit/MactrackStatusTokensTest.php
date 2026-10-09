<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for mactrack_parse_status_tokens() in
 * lib/mactrack_functions.php. This allowlist parser is the sole gate between
 * the 'statuses' request value and the SQL status filter, so these cases pin
 * its contract: only legend classes survive, order is preserved, duplicates
 * collapse, and empty/invalid input yields an empty list (no filtering). The
 * allowlist is derived from mactrack_interface_status_legend(), so adding a
 * legend state without updating these expectations will surface here.
 */
final class MactrackStatusTokensTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('lib/mactrack_functions.php');
	}

	/**
	 * @return void
	 */
	public function test_accepts_every_legend_class(): void {
		$valid = array_keys(mactrack_interface_status_legend());

		$this->assertSame($valid, mactrack_parse_status_tokens(implode(',', $valid)));
	}

	/**
	 * @return void
	 */
	public function test_preserves_request_order(): void {
		$this->assertSame(
			['int_down', 'int_up', 'int_errors'],
			mactrack_parse_status_tokens('int_down,int_up,int_errors')
		);
	}

	/**
	 * @return void
	 */
	public function test_deduplicates_while_keeping_first_occurrence(): void {
		$this->assertSame(
			['int_up', 'int_errors'],
			mactrack_parse_status_tokens('int_up,int_errors,int_up')
		);
	}

	/**
	 * @return void
	 */
	public function test_trims_surrounding_whitespace(): void {
		$this->assertSame(
			['int_up', 'int_down'],
			mactrack_parse_status_tokens(' int_up , int_down ')
		);
	}

	/**
	 * @return void
	 */
	public function test_drops_unknown_tokens(): void {
		$this->assertSame(
			['int_up', 'int_discards'],
			mactrack_parse_status_tokens('int_up,bogus,DROP TABLE,int_discards')
		);
	}

	/**
	 * @return void
	 */
	public function test_empty_string_returns_empty_list(): void {
		$this->assertSame([], mactrack_parse_status_tokens(''));
	}

	/**
	 * @return void
	 */
	public function test_null_returns_empty_list(): void {
		$this->assertSame([], mactrack_parse_status_tokens(null));
	}

	/**
	 * @return void
	 */
	public function test_all_invalid_returns_empty_list(): void {
		$this->assertSame([], mactrack_parse_status_tokens('bogus,,int_bogus'));
	}
}
