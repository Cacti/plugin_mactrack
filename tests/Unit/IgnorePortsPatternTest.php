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
 * The "Ports to Ignore" setting used to be stripped of regular-expression
 * characters before being bound into the Interfaces RLIKE filter, silently
 * breaking any pattern more complex than a plain word list. These tests lock
 * in the validate/predicate/needs-ignore trio that replaced that behavior.
 */
final class IgnorePortsPatternTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('lib/mactrack_functions.php');
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function validPatternProvider() {
		return [
			'default'      => ['(Vlan|Loopback|Null)'],
			'anchored'     => ['^(Gi|Te)[0-9/]+$'],
			'literal tilde' => ['Port~Channel'],
			'escaped tilde' => ['Vlan\\~Trunk'],
		];
	}

	/**
	 * @dataProvider validPatternProvider
	 *
	 * @param string $valid
	 *
	 * @return void
	 */
	public function testValidPatternsPassThroughUnchanged($valid): void {
		$this->assertSame($valid, mactrack_validate_ignore_ports_pattern($valid));
	}

	/**
	 * @return array<string, array{0: mixed}>
	 */
	public static function invalidPatternProvider() {
		return [
			'empty'          => [''],
			'null'           => [null],
			'unclosed group' => ['(Vlan'],
			'unclosed class' => ['[a-'],
			'catastrophic backtracking' => ['(a+)+$'],
		];
	}

	/**
	 * @dataProvider invalidPatternProvider
	 *
	 * @param mixed $invalid
	 *
	 * @return void
	 */
	public function testInvalidPatternsFallBackToTheDefault($invalid): void {
		$this->assertSame('(Vlan|Loopback|Null)', mactrack_validate_ignore_ports_pattern($invalid));
	}

	/**
	 * @return void
	 */
	public function testPredicateBindsTheConfiguredPatternTwice(): void {
		set_config_option('mt_ignorePorts', '(Vlan|Loopback|Null)');

		$params    = [];
		$predicate = mactrack_get_ignore_ports_predicate($params);

		$this->assertSame('(ifName NOT RLIKE ? AND ifDescr NOT RLIKE ?)', $predicate);
		$this->assertSame(['(Vlan|Loopback|Null)', '(Vlan|Loopback|Null)'], $params);
	}

	/**
	 * @return array<string, array{0: string, 1: int, 2: bool}>
	 */
	public static function needsIgnoreProvider() {
		return [
			'-4, no bandwidth filter' => ['-4', -1, true],
			'-4, with bandwidth'      => ['-4', 70, true],
			'-3, no bandwidth filter' => ['-3', -1, true],
			'-2, no bandwidth filter' => ['-2', -1, false],
			'-2, with bandwidth'      => ['-2', 70, false],
			'-1'                      => ['-1', -1, true],
			'0'                       => ['0', -1, true],
			'1'                       => ['1', -1, true],
			'2'                       => ['2', -1, true],
			'3'                       => ['3', -1, true],
			'7, no bandwidth filter'  => ['7', -1, false],
			'9, no bandwidth filter'  => ['9', -1, false],
			'9, with bandwidth'       => ['9', 70, true],
			'10, no bandwidth filter' => ['10', -1, false],
			'10, with bandwidth'      => ['10', 70, true],
			'11, no bandwidth filter' => ['11', -1, false],
			'11, with bandwidth'      => ['11', 70, true],
		];
	}

	/**
	 * @dataProvider needsIgnoreProvider
	 *
	 * @param string $issues
	 * @param int    $bwusage
	 * @param bool   $expected
	 *
	 * @return void
	 */
	public function testNeedsIgnoreMatchesTheIssuesAndBandwidthFilterCombination($issues, $bwusage, $expected): void {
		$this->assertSame($expected, mactrack_interface_filter_needs_ignore($issues, $bwusage));
	}

	/**
	 * @return void
	 */
	public function testInterfacesQueryUsesTheValidatedPatternAndPreparedSql(): void {
		$source = plugin_test_read_source('mactrack_view_interfaces.php');

		$this->assertStringContainsString('mactrack_get_ignore_ports_predicate($sql_params)', $source);
		$this->assertStringContainsString('db_fetch_assoc_prepared($sql_query, $sql_params)', $source);
		$this->assertStringContainsString('db_fetch_cell_prepared($rows_query_string, $sql_params)', $source);
	}
}
