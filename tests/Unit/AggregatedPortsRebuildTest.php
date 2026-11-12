<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/**
 * Covers mactrack_rebuild_aggregated_ports() and its schema helper, which
 * rename mac_track_aggregated_ports aside, recreate the modified table, copy
 * it back one scan date at a time with normalized MAC addresses, and drop the
 * old table. The rebuild replaces the full-table rewrite that was killed at
 * the web-request timeout.
 */
final class AggregatedPortsRebuildTest extends TestCase {
	private const KEY_COLUMNS = ['port_number', 'mac_address', 'ip_address', 'device_id', 'site_id', 'vlan_id', 'authorized'];

	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('includes/database.php');
	}

	/**
	 * Stub whether a table exists (drives mactrack_db_table_exists()).
	 *
	 * @param string $table  Table name.
	 * @param bool   $exists Whether SHOW TABLES should find it.
	 *
	 * @return void
	 */
	private static function stubTable(string $table, bool $exists): void {
		mactrack_test_queue_return_for('db_fetch_assoc', "SHOW TABLES LIKE '$table'", $exists ? [['t' => $table]] : []);
	}

	/**
	 * Stub the SHOW INDEXES rows for a table (drives mactrack_db_key_exists()).
	 *
	 * @param string                                 $table Table name.
	 * @param array<int, array<string, int|string>>  $rows  Index rows.
	 *
	 * @return void
	 */
	private static function stubShowIndexes(string $table, array $rows): void {
		mactrack_test_queue_return_for('db_fetch_assoc', "SHOW INDEXES FROM $table", $rows);
	}

	/**
	 * Stub the unique-key probe (drives mactrack_aggregated_ports_key_is_unique()).
	 *
	 * @param string $table      Table name.
	 * @param int    $non_unique 0 for UNIQUE, 1 otherwise.
	 *
	 * @return void
	 */
	private static function stubUniqueKey(string $table, int $non_unique): void {
		$rows = [];

		foreach (self::KEY_COLUMNS as $i => $column) {
			$rows[] = ['Key_name' => 'port_number', 'Non_unique' => $non_unique, 'Seq_in_index' => $i + 1, 'Column_name' => $column];
		}

		mactrack_test_queue_return_for('db_fetch_assoc_prepared', "SHOW INDEX FROM $table WHERE Key_name = ?", $rows);
	}

	/**
	 * Stub the SHOW COLUMNS probe used by the schema normalizer.
	 *
	 * @param string $type Column type returned for row_id/port_number.
	 *
	 * @return void
	 */
	private static function stubColumnType(string $type): void {
		mactrack_test_queue_return_for('db_fetch_row_prepared', 'SHOW COLUMNS FROM mac_track_aggregated_ports LIKE', ['Field' => 'col', 'Type' => $type]);
	}

	/**
	 * SQL of every recorded call whose SQL contains $fragment.
	 *
	 * @param string $fragment SQL substring.
	 *
	 * @return array<int, string>
	 */
	private static function callsMatching(string $fragment): array {
		return array_values(array_filter(
			array_column($GLOBALS['__test_db_calls'], 'sql'),
			static fn ($sql) => strpos((string) preg_replace('/\s+/', ' ', $sql), $fragment) !== false
		));
	}

	/**
	 * @return void
	 */
	public function testAlreadyRebuiltIsANoOp(): void {
		set_config_option('mt_aggregated_ports_rebuilt', 'on');

		$this->assertTrue(mactrack_rebuild_aggregated_ports());
		$this->assertSame([], $GLOBALS['__test_db_calls']);
	}

	/**
	 * @return void
	 */
	public function testNoAggregatedTableRecordsCompletion(): void {
		self::stubTable('mac_track_aggregated_ports', false);
		self::stubTable('mac_track_aggregated_ports_upgrade', false);

		$this->assertTrue(mactrack_rebuild_aggregated_ports());
		$this->assertSame('on', read_config_option('mt_aggregated_ports_rebuilt'));
		$this->assertSame([], self::callsMatching('RENAME TABLE'));
	}

	/**
	 * @return void
	 */
	public function testLockContentionDefersTheRebuild(): void {
		self::stubTable('mac_track_aggregated_ports', true);
		self::stubTable('mac_track_aggregated_ports_upgrade', false);
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'GET_LOCK', '0');

		$this->assertFalse(mactrack_rebuild_aggregated_ports());
		$this->assertSame([], self::callsMatching('RENAME TABLE'));
		$this->assertNotSame('on', read_config_option('mt_aggregated_ports_rebuilt'));
	}

	/**
	 * Another process finishing the rebuild between our flag read and lock
	 * acquisition is detected by the re-check and leaves the table alone.
	 *
	 * @return void
	 */
	public function testConcurrentCompletionIsDetectedAfterLocking(): void {
		self::stubTable('mac_track_aggregated_ports', true);
		self::stubTable('mac_track_aggregated_ports_upgrade', false);
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'GET_LOCK', '1');
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'RELEASE_LOCK', '1');
		// Flag reads off before the lock, then on just after acquiring it.
		mactrack_test_queue_return('read_config_option', '');
		mactrack_test_queue_return('read_config_option', 'on');

		$this->assertTrue(mactrack_rebuild_aggregated_ports());
		$this->assertSame([], self::callsMatching('RENAME TABLE'));
		$this->assertCount(1, self::callsMatching('RELEASE_LOCK'));
	}

	/**
	 * The live table is renamed aside, the destination schema is normalized,
	 * each scan date is copied and drained, and the source table is dropped.
	 *
	 * @return void
	 */
	public function testRebuildRenamesCopiesPerScanDateAndDrops(): void {
		self::stubTable('mac_track_aggregated_ports', true);
		self::stubTable('mac_track_aggregated_ports_upgrade', false);
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'GET_LOCK', '1');
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'RELEASE_LOCK', '1');
		// row_id/port_number still on the legacy types, so both are widened.
		self::stubColumnType('int(10) unsigned');
		// Non-unique key with the key present, so it is dropped and re-added.
		self::stubUniqueKey('mac_track_aggregated_ports', 1);
		self::stubShowIndexes('mac_track_aggregated_ports', [['Key_name' => 'port_number']]);
		// One scan date to copy, then the drain loop ends.
		mactrack_test_queue_return('db_fetch_cell', '2024-01-01 00:00:00');

		$this->assertTrue(mactrack_rebuild_aggregated_ports());

		$this->assertCount(1, self::callsMatching('RENAME TABLE mac_track_aggregated_ports TO mac_track_aggregated_ports_upgrade'));
		$this->assertCount(1, self::callsMatching('ALTER TABLE mac_track_aggregated_ports_upgrade ADD INDEX `date_last`'));
		$this->assertCount(1, self::callsMatching('MODIFY COLUMN row_id bigint(20)'));
		$this->assertCount(1, self::callsMatching("MODIFY COLUMN port_number varchar(30)"));
		$this->assertCount(1, self::callsMatching('DROP INDEX port_number'));
		$this->assertCount(1, self::callsMatching('ADD UNIQUE INDEX `port_number`'));
		$this->assertCount(1, self::callsMatching('INSERT INTO mac_track_aggregated_ports'));
		$this->assertCount(1, self::callsMatching('ON DUPLICATE KEY UPDATE count_rec = count_rec + VALUES(count_rec)'));
		$this->assertCount(1, self::callsMatching('DELETE FROM mac_track_aggregated_ports_upgrade WHERE date_last = ?'));
		$this->assertCount(1, self::callsMatching('DROP TABLE IF EXISTS mac_track_aggregated_ports_upgrade'));
		$this->assertSame('on', read_config_option('mt_aggregated_ports_rebuilt'));
	}

	/**
	 * A prior interrupted run leaves the source table in place and the
	 * destination missing; the rebuild resumes by creating the destination
	 * rather than renaming again, and adds the key when it is absent.
	 *
	 * @return void
	 */
	public function testRebuildResumesWithoutRenamingWhenTheSourceExists(): void {
		self::stubTable('mac_track_aggregated_ports', false);
		self::stubTable('mac_track_aggregated_ports_upgrade', true);
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'GET_LOCK', '1');
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'RELEASE_LOCK', '1');
		// The destination is already on the target column types (no ALTER).
		self::stubColumnType('bigint(20) unsigned');
		// Key absent, so it is added without a preceding DROP.
		self::stubUniqueKey('mac_track_aggregated_ports', 1);
		// Stub the longer (source) name first: the live name is a prefix of it,
		// so a live-name match would otherwise shadow the source lookup.
		self::stubShowIndexes('mac_track_aggregated_ports_upgrade', [['Key_name' => 'date_last']]);
		self::stubShowIndexes('mac_track_aggregated_ports', []);

		$this->assertTrue(mactrack_rebuild_aggregated_ports());

		$this->assertSame([], self::callsMatching('RENAME TABLE'));
		$this->assertCount(1, self::callsMatching('CREATE TABLE mac_track_aggregated_ports LIKE mac_track_aggregated_ports_upgrade'));
		$this->assertSame([], self::callsMatching('ADD INDEX `date_last`'));
		$this->assertSame([], self::callsMatching('MODIFY COLUMN row_id'));
		$this->assertSame([], self::callsMatching('DROP INDEX port_number'));
		$this->assertCount(1, self::callsMatching('ADD UNIQUE INDEX `port_number`'));
	}

	/**
	 * A failed copy transaction rolls back and reports the pass did not finish.
	 *
	 * @return void
	 */
	public function testRebuildRollsBackAndReturnsFalseOnACopyFailure(): void {
		self::stubTable('mac_track_aggregated_ports', true);
		self::stubTable('mac_track_aggregated_ports_upgrade', false);
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'GET_LOCK', '1');
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'RELEASE_LOCK', '1');
		self::stubColumnType('bigint(20) unsigned');
		self::stubUniqueKey('mac_track_aggregated_ports', 0);
		mactrack_test_queue_return('db_fetch_cell', '2024-01-01 00:00:00');
		// The commit fails, forcing a rollback.
		mactrack_test_queue_return('db_commit_transaction', false);

		$this->assertFalse(mactrack_rebuild_aggregated_ports());
		$this->assertCount(1, self::callsMatching('ROLLBACK'));
		$this->assertSame([], self::callsMatching('DROP TABLE IF EXISTS'));
		$this->assertNotSame('on', read_config_option('mt_aggregated_ports_rebuilt'));
	}
}
