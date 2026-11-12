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
 * The schema used to declare `port_number` as a UNIQUE composite key, but the
 * aggregated-ports table keeps multiple rows per MAC by design (the MAC view
 * counts them to show how often a MAC has been seen). When a version bump
 * re-entered the upgrade path, api_plugin_db_table_create() tried to apply the
 * UNIQUE key and aborted with "Duplicate entry ... for key 'port_number'",
 * cascading into a fatal "could not update table" plugin error on every Cacti
 * page load.
 *
 * The fix makes `port_number` a plain (non-unique) index and widens `row_id`
 * to bigint unsigned. The upgrade drops any legacy UNIQUE index and re-adds it
 * non-unique (db_update_table reconciles indexes by column set only and will
 * not do it on its own), and the poller inserts aggregated rows as a plain
 * append rather than an ON DUPLICATE KEY UPDATE upsert.
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
	 * Prime the stubs mactrack_database_upgrade() needs to run cleanly: a
	 * seeded Default site (so the trailing seed is a no-op) and the port_number
	 * column type lookup on mac_track_ips.
	 *
	 * @return void
	 */
	private function primeUpgradeStubs(): void {
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'COUNT(*) FROM mac_track_sites', '1');
		mactrack_test_queue_return_for('db_fetch_assoc', 'SHOW COLUMNS FROM mac_track_ips',
			array(array('Field' => 'port_number', 'Type' => 'varchar(30)')));
	}

	/**
	 * @return void
	 */
	public function testAggregatedPortsSchemaIsNonUniqueWithBigintRowId(): void {
		$src = file_get_contents(dirname(__DIR__, 2) . '/includes/database.php');

		$this->assertStringContainsString(
			"['name' => 'row_id', 'unsigned' => true, 'type' => 'bigint(20)'",
			$src,
			'row_id should be bigint unsigned'
		);
		$this->assertStringContainsString(
			"\$data['keys'][]        = ['name' => 'port_number'",
			$src,
			'port_number should be a plain (non-unique) index'
		);
		$this->assertStringNotContainsString(
			"\$data['unique_keys'][] = ['name' => 'port_number'",
			$src,
			'port_number must no longer be a unique key'
		);
	}

	/**
	 * @return void
	 */
	public function testUpgradeReaddsPortNumberAsAPlainIndex(): void {
		$this->primeUpgradeStubs();

		mactrack_database_upgrade();

		$adds = array_values(array_filter($this->sqlCalls(), static fn ($sql) =>
			strpos($sql, 'ADD INDEX `port_number`') !== false
		));
		$this->assertCount(1, $adds, 'The port_number index should be re-added');
		$this->assertStringNotContainsString('UNIQUE', $adds[0], 'The re-added index must be non-unique');

		foreach ($this->sqlCalls() as $sql) {
			$this->assertStringNotContainsString('ADD UNIQUE INDEX `port_number`', $sql);
		}
	}

	/**
	 * @return void
	 */
	public function testUpgradeDropsAnExistingPortNumberIndexBeforeReadding(): void {
		$this->primeUpgradeStubs();

		// Make mactrack_db_key_exists() report an existing port_number index.
		mactrack_test_queue_return_for('db_fetch_assoc', "SHOW TABLES LIKE 'mac_track_aggregated_ports'",
			array(array('name' => 'mac_track_aggregated_ports')));
		mactrack_test_queue_return_for('db_fetch_assoc', 'SHOW INDEXES FROM mac_track_aggregated_ports',
			array(array('Key_name' => 'port_number')));

		mactrack_database_upgrade();

		$calls = $this->sqlCalls();

		$drops = array_filter($calls, static fn ($sql) =>
			strpos($sql, 'DROP INDEX port_number') !== false
		);
		$this->assertCount(1, $drops, 'An existing port_number index should be dropped first');

		$adds = array_filter($calls, static fn ($sql) =>
			strpos($sql, 'ADD INDEX `port_number`') !== false
		);
		$this->assertCount(1, $adds, 'The index should then be re-added as a plain index');
	}

	/**
	 * @return void
	 */
	public function testUpgradeStripsMacSeparatorsOnceThenSkips(): void {
		$this->primeUpgradeStubs();

		$this->assertNotSame('on', read_config_option('mt_mac_separators_stripped'));

		mactrack_database_upgrade();

		$this->assertSame('on', read_config_option('mt_mac_separators_stripped'));

		$firstPass = array_filter($this->sqlCalls(), static fn ($sql) =>
			strpos($sql, 'UPDATE mac_track_dot1x SET mac_address') !== false
		);
		$this->assertCount(1, $firstPass, 'Legacy MAC normalization should run on the first upgrade');

		// A later upgrade with the flag already set must not re-run the heavy
		// table-wide normalization UPDATEs or re-index the table.
		$GLOBALS['__test_db_calls'] = array();
		$this->primeUpgradeStubs();

		mactrack_database_upgrade();

		foreach ($this->sqlCalls() as $sql) {
			$this->assertStringNotContainsString('UPDATE mac_track_dot1x SET mac_address', $sql);
			$this->assertStringNotContainsString('ADD INDEX `port_number`', $sql);
		}
	}

	/**
	 * @return void
	 */
	public function testPollerAppendsAggregatedPortsWithoutAnUpsert(): void {
		$src = file_get_contents(dirname(__DIR__, 2) . '/poller_mactrack.php');

		$this->assertStringContainsString('INSERT INTO mac_track_aggregated_ports', $src);
		$this->assertStringNotContainsString('ON DUPLICATE KEY UPDATE count_rec', $src,
			'The aggregated-ports insert must be a plain append, not an upsert');
	}
}
