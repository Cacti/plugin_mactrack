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
 * Regression coverage for the mac_track_aggregated_ports upgrade crash.
 *
 * The schema declares `port_number` as a UNIQUE composite key, but older
 * installs carried it as a non-unique index that let duplicate aggregated
 * rows accumulate. When a version bump re-entered the install/upgrade path,
 * api_plugin_db_table_create() tried to convert that index to UNIQUE and
 * aborted with "Duplicate entry ... for key 'port_number'", cascading into a
 * fatal "could not update table" plugin error on every Cacti page load.
 *
 * mactrack_dedupe_aggregated_ports() now prunes the duplicates before the
 * unique key is applied, and mactrack_database_upgrade() no longer drops and
 * recreates that key as a non-unique index.
 */
final class AggregatedPortsUpgradeTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('includes/database.php');
		self::loadPluginSource('setup.php');
	}

	/**
	 * @return array<int, string>
	 */
	private function sqlCalls(): array {
		return array_column($GLOBALS['__test_db_calls'], 'sql');
	}

	/**
	 * @return void
	 */
	public function testDedupeIsANoOpWhenTheTableIsAbsent(): void {
		mactrack_test_queue_return('db_table_exists', false);

		mactrack_dedupe_aggregated_ports();

		$aggCalls = array_filter($this->sqlCalls(), static fn ($sql) =>
			strpos($sql, 'mac_track_aggregated_ports') !== false
		);
		$this->assertSame(array(), $aggCalls, 'Absent table must not be touched');
	}

	/**
	 * @return void
	 */
	public function testDedupeNormalizesAndPrunesDuplicatesWhenPresent(): void {
		// db_table_exists() defaults to true in the stub environment.
		mactrack_dedupe_aggregated_ports();

		$calls = $this->sqlCalls();

		$normalized = array_filter($calls, static fn ($sql) =>
			strpos($sql, 'UPDATE mac_track_aggregated_ports') !== false &&
			strpos($sql, 'REPLACE') !== false
		);
		$this->assertCount(1, $normalized, 'MAC separators should be stripped exactly once');

		$deletes = array_values(array_filter($calls, static fn ($sql) =>
			strpos($sql, 'DELETE FROM mac_track_aggregated_ports') !== false
		));
		$this->assertCount(1, $deletes, 'Duplicate rows should be pruned exactly once');
		$this->assertStringContainsString('MIN(row_id)', $deletes[0]);
		$this->assertStringContainsString(
			'GROUP BY port_number, mac_address, ip_address, device_id, site_id, vlan_id, authorized',
			$deletes[0]
		);
	}

	/**
	 * @return void
	 */
	public function testSetupDatabaseDedupesBeforeCreatingTheTable(): void {
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'COUNT(*) FROM mac_track_sites', '1');

		mactrack_setup_database(false);

		$deletes = array_filter($this->sqlCalls(), static fn ($sql) =>
			strpos($sql, 'DELETE FROM mac_track_aggregated_ports') !== false
		);
		$this->assertCount(1, $deletes, 'Setup must prune duplicates before applying the unique key');
	}

	/**
	 * @return void
	 */
	public function testUpgradeNeverRecreatesThePortNumberIndex(): void {
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'COUNT(*) FROM mac_track_sites', '1');
		mactrack_test_queue_return_for('db_fetch_assoc', 'SHOW COLUMNS FROM mac_track_ips',
			array(array('Field' => 'port_number', 'Type' => 'varchar(30)')));

		mactrack_database_upgrade();

		foreach ($this->sqlCalls() as $sql) {
			$this->assertStringNotContainsString('DROP INDEX port_number', $sql);
			$this->assertStringNotContainsString('ADD INDEX `port_number`', $sql);
		}
	}

	/**
	 * @return void
	 */
	public function testUpgradeStripsMacSeparatorsOnceThenSkips(): void {
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'COUNT(*) FROM mac_track_sites', '1');
		mactrack_test_queue_return_for('db_fetch_assoc', 'SHOW COLUMNS FROM mac_track_ips',
			array(array('Field' => 'port_number', 'Type' => 'varchar(30)')));

		$this->assertNotSame('on', read_config_option('mt_mac_separators_stripped'));

		mactrack_database_upgrade();

		$this->assertSame('on', read_config_option('mt_mac_separators_stripped'));

		$firstPass = array_filter($this->sqlCalls(), static fn ($sql) =>
			strpos($sql, 'UPDATE mac_track_dot1x SET mac_address') !== false
		);
		$this->assertCount(1, $firstPass, 'Legacy MAC normalization should run on the first upgrade');

		// A later upgrade with the flag already set must not re-run the heavy
		// table-wide normalization UPDATEs.
		$GLOBALS['__test_db_calls'] = array();
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'COUNT(*) FROM mac_track_sites', '1');

		mactrack_database_upgrade();

		foreach ($this->sqlCalls() as $sql) {
			$this->assertStringNotContainsString('UPDATE mac_track_dot1x SET mac_address', $sql);
		}
	}
}
