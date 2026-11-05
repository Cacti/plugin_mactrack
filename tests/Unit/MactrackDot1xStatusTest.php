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
 * Unit coverage for the 802.1x status helpers in lib/mactrack_functions.php:
 * the status legend map, the failure-status set, the failure predicate, and
 * the 'statuses' request-value token parser. These drive the dot1x multi-select
 * Status filter and the failure-only status pills, so a future status-code or
 * failure-set change surfaces here.
 */
final class MactrackDot1xStatusTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('lib/mactrack_functions.php');
	}

	/**
	 * @return void
	 */
	public function test_legend_exposes_the_seven_status_codes(): void {
		$this->assertSame(
			[1, 2, 3, 4, 5, 6, 7],
			array_map('intval', array_keys(mactrack_dot1x_status_legend()))
		);
	}

	/**
	 * @return void
	 */
	public function test_failure_statuses_are_the_two_failed_states(): void {
		$this->assertSame([5, 7], mactrack_dot1x_failure_statuses());
	}

	/**
	 * @return void
	 */
	public function test_status_is_failure_true_for_failed_states(): void {
		$this->assertTrue(mactrack_dot1x_status_is_failure(5));
		$this->assertTrue(mactrack_dot1x_status_is_failure(7));
		$this->assertTrue(mactrack_dot1x_status_is_failure('5'));
	}

	/**
	 * @return void
	 */
	public function test_status_is_failure_false_for_other_states(): void {
		foreach ([0, 1, 2, 3, 4, 6, 99] as $status) {
			$this->assertFalse(mactrack_dot1x_status_is_failure($status));
		}
	}

	/**
	 * @return void
	 */
	public function test_parse_accepts_every_status_code(): void {
		$this->assertSame(
			[1, 2, 3, 4, 5, 6, 7],
			mactrack_parse_dot1x_status_tokens('1,2,3,4,5,6,7')
		);
	}

	/**
	 * @return void
	 */
	public function test_parse_preserves_request_order(): void {
		$this->assertSame([7, 5, 3], mactrack_parse_dot1x_status_tokens('7,5,3'));
	}

	/**
	 * @return void
	 */
	public function test_parse_deduplicates_while_keeping_first_occurrence(): void {
		$this->assertSame([5, 7], mactrack_parse_dot1x_status_tokens('5,7,5'));
	}

	/**
	 * @return void
	 */
	public function test_parse_trims_surrounding_whitespace(): void {
		$this->assertSame([1, 7], mactrack_parse_dot1x_status_tokens(' 1 , 7 '));
	}

	/**
	 * @return void
	 */
	public function test_parse_drops_out_of_range_and_non_numeric_tokens(): void {
		$this->assertSame([5], mactrack_parse_dot1x_status_tokens('0,8,99,bogus,5'));
	}

	/**
	 * @return void
	 */
	public function test_parse_empty_string_returns_empty_list(): void {
		$this->assertSame([], mactrack_parse_dot1x_status_tokens(''));
	}

	/**
	 * @return void
	 */
	public function test_parse_null_returns_empty_list(): void {
		$this->assertSame([], mactrack_parse_dot1x_status_tokens(null));
	}

	/**
	 * @return void
	 */
	public function test_parse_all_invalid_returns_empty_list(): void {
		$this->assertSame([], mactrack_parse_dot1x_status_tokens('bogus,,0,9'));
	}
}
