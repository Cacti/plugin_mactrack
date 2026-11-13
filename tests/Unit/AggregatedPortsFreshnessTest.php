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

final class AggregatedPortsFreshnessTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('lib/mactrack_functions.php');
	}

	/**
	 * @return void
	 */
	public function testSiteScanBindsItsScopeToBothWrites(): void {
		$this->assertTrue(mactrack_update_aggregated_ports('2026-11-13 01:00:00', 7));
		$calls = $GLOBALS['__test_db_calls'];
		$this->assertCount(4, $calls);
		$this->assertSame('START TRANSACTION', $calls[0]['sql']);
		$this->assertSame(['2026-11-13 01:00:00', '2026-11-13 01:00:00', HOST_UP, 7], $calls[1]['params']);
		$this->assertSame(['2026-11-13 01:00:00', '2026-11-13 01:00:00', 7], $calls[2]['params']);
		$this->assertStringContainsString('AND p.site_id = ?', $calls[1]['sql']);
		$this->assertStringContainsString('AND p.site_id = ?', $calls[2]['sql']);
		$this->assertSame('COMMIT', $calls[3]['sql']);
	}

	/**
	 * @return void
	 */
	public function testGlobalScanDoesNotAddASiteFilter(): void {
		$this->assertTrue(mactrack_update_aggregated_ports('2026-11-13 01:00:00'));
		$calls = $GLOBALS['__test_db_calls'];
		$this->assertCount(3, $calls[1]['params']);
		$this->assertCount(2, $calls[2]['params']);
		$this->assertStringNotContainsString('AND p.site_id = ?', $calls[1]['sql']);
		$this->assertStringNotContainsString('AND p.site_id = ?', $calls[2]['sql']);
	}

	/**
	 * @return void
	 */
	public function testBeginFailureDoesNotWriteSightings(): void {
		mactrack_test_queue_return('db_execute_prepared', false);
		$this->assertFalse(mactrack_update_aggregated_ports('2026-11-13 01:00:00'));
		$this->assertCount(1, $GLOBALS['__test_db_calls']);
	}

	/**
	 * @return void
	 */
	public function testResetFailureRollsBackWithoutInserting(): void {
		mactrack_test_queue_return('db_execute_prepared', true);
		mactrack_test_queue_return('db_execute_prepared', false);
		$this->assertFalse(mactrack_update_aggregated_ports('2026-11-13 01:00:00'));
		$this->assertCount(3, $GLOBALS['__test_db_calls']);
		$this->assertSame('ROLLBACK', $GLOBALS['__test_db_calls'][2]['sql']);
	}

	/**
	 * @return void
	 */
	public function testInsertFailureRollsBackTheReset(): void {
		mactrack_test_queue_return('db_execute_prepared', true);
		mactrack_test_queue_return('db_execute_prepared', true);
		mactrack_test_queue_return('db_execute_prepared', false);
		$this->assertFalse(mactrack_update_aggregated_ports('2026-11-13 01:00:00'));
		$this->assertCount(4, $GLOBALS['__test_db_calls']);
		$this->assertSame('ROLLBACK', $GLOBALS['__test_db_calls'][3]['sql']);
	}

	/**
	 * @return void
	 */
	public function testCommitFailureAttemptsRollback(): void {
		mactrack_test_queue_return('db_execute_prepared', true);
		mactrack_test_queue_return('db_execute_prepared', true);
		mactrack_test_queue_return('db_execute_prepared', true);
		mactrack_test_queue_return('db_execute_prepared', false);
		$this->assertFalse(mactrack_update_aggregated_ports('2026-11-13 01:00:00'));
		$this->assertSame('ROLLBACK', $GLOBALS['__test_db_calls'][4]['sql']);
	}

	/**
	 * @return void
	 */
	public function testOnlyCurrentObservationsAreEmphasized(): void {
		$scan = '2026-11-13 01:00:00';
		$this->assertSame('<strong>' . $scan . '</strong>', mactrack_aggregate_last_seen($scan, $scan, 1));
		$this->assertSame('2026-11-12 01:00:00 (Not observed in latest scan)', mactrack_aggregate_last_seen('2026-11-12 01:00:00', $scan, 1));
		$this->assertSame($scan . ' (Not observed in latest scan)', mactrack_aggregate_last_seen($scan, $scan, 0));
		$this->assertStringNotContainsString('<script>', mactrack_aggregate_last_seen('<script>', $scan, 1));
	}
}
