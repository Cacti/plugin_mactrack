<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/**
 * Covers the repair of the mac_track_aggregated_ports `port_number` unique
 * key. The 4.9 upgrade recreated it as a plain index on every upgrade, so
 * poller passes inserted duplicate rows and Cacti's table refresh then
 * failed with "Duplicate entry ... for key 'port_number'".
 */
final class AggregatedPortsKeyTest extends TestCase {
	private const KEY_COLUMNS = ['port_number', 'mac_address', 'ip_address', 'device_id', 'site_id', 'vlan_id', 'authorized'];

	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('includes/database.php');
		self::loadPluginSource('setup.php');
	}

	/**
	 * SHOW INDEX rows for a `port_number` key over $columns.
	 *
	 * @param int                $non_unique 0 for a UNIQUE index, 1 otherwise.
	 * @param array<int, string> $columns    Key columns in index order.
	 *
	 * @return array<int, array<string, int|string>>
	 */
	private static function keyRows(int $non_unique, array $columns = self::KEY_COLUMNS): array {
		$rows = [];

		foreach ($columns as $i => $column) {
			$rows[] = ['Key_name' => 'port_number', 'Non_unique' => $non_unique, 'Seq_in_index' => $i + 1, 'Column_name' => $column];
		}

		return $rows;
	}

	/**
	 * Stub the table, its `port_number` key, and the lock.
	 *
	 * @param array<int, array<string, int|string>> $rows SHOW INDEX result.
	 *
	 * @return void
	 */
	private static function givenKey(array $rows): void {
		mactrack_test_queue_return_for('db_fetch_assoc', "SHOW TABLES LIKE 'mac_track_aggregated_ports'", [['Tables_in_cacti' => 'mac_track_aggregated_ports']]);
		mactrack_test_queue_return_for('db_fetch_assoc_prepared', 'SHOW INDEX FROM mac_track_aggregated_ports WHERE Key_name = ?', $rows);
		mactrack_test_queue_return_for('db_fetch_assoc', 'SHOW INDEXES FROM mac_track_aggregated_ports', $rows);
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'GET_LOCK', '1');
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'RELEASE_LOCK', '1');
	}

	/**
	 * Position of the first recorded call whose SQL contains $fragment.
	 *
	 * @param string $fragment SQL substring.
	 *
	 * @return int|null
	 */
	private static function callIndex(string $fragment): ?int {
		foreach ($GLOBALS['__test_db_calls'] as $i => $call) {
			if (strpos(preg_replace('/\s+/', ' ', $call['sql']), $fragment) !== false) {
				return $i;
			}
		}

		return null;
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
			static fn ($sql) => strpos($sql, $fragment) !== false
		));
	}

	/**
	 * @return void
	 */
	public function testIntactUniqueKeyIsLeftAlone(): void {
		self::givenKey(self::keyRows(0));

		$this->assertTrue(mactrack_repair_aggregated_ports_key());
		$this->assertNull(self::callIndex('GET_LOCK'));
		$this->assertSame([], self::callsMatching('ALTER TABLE'));
		$this->assertSame([], self::callsMatching('DELETE'));
	}

	/**
	 * @return void
	 */
	public function testNonUniqueKeyIsDedupedBeforeTheUniqueKeyIsRebuilt(): void {
		self::givenKey(self::keyRows(1));

		$this->assertTrue(mactrack_repair_aggregated_ports_key());

		$order = [
			self::callIndex('GET_LOCK'),
			self::callIndex('BEGIN'),
			self::callIndex('SUM(count_rec) AS count_rec'),
			self::callIndex('DELETE a FROM mac_track_aggregated_ports'),
			self::callIndex('COMMIT'),
			self::callIndex('ALTER TABLE mac_track_aggregated_ports'),
			self::callIndex('RELEASE_LOCK'),
		];

		$this->assertNotContains(null, $order);
		$sorted = $order;
		sort($sorted);
		$this->assertSame($sorted, $order, 'Merge must commit before the unique key is rebuilt, inside the lock');

		$merge = self::callsMatching('SUM(count_rec)')[0];
		$this->assertStringContainsString('GROUP BY ' . implode(', ', self::KEY_COLUMNS), $merge);
		$this->assertStringContainsString('MIN(first_scan_date)', $merge);
		$this->assertStringContainsString('MAX(date_last)', $merge);

		$delete = self::callsMatching('DELETE a FROM')[0];
		$this->assertStringContainsString('a.authorized = d.authorized', $delete);
		$this->assertStringContainsString('a.row_id < d.keep_id', $delete);

		$this->assertSame(
			['ALTER TABLE mac_track_aggregated_ports DROP INDEX `port_number`, ADD UNIQUE INDEX `port_number` (`' . implode('`, `', self::KEY_COLUMNS) . '`)'],
			self::callsMatching('ALTER TABLE')
		);
		$this->assertContains('MACTRACK: Restored the mac_track_aggregated_ports unique key', $GLOBALS['__test_cacti_log']);
	}

	/**
	 * @return void
	 */
	public function testUniqueKeyOverTheWrongColumnsIsRebuilt(): void {
		self::givenKey(self::keyRows(0, ['port_number', 'mac_address', 'device_id']));

		$this->assertTrue(mactrack_repair_aggregated_ports_key());
		$this->assertCount(1, self::callsMatching('ADD UNIQUE INDEX `port_number`'));
	}

	/**
	 * @return void
	 */
	public function testMissingKeyIsAddedWithoutADrop(): void {
		self::givenKey([]);

		$this->assertTrue(mactrack_repair_aggregated_ports_key());
		$this->assertSame(
			['ALTER TABLE mac_track_aggregated_ports ADD UNIQUE INDEX `port_number` (`' . implode('`, `', self::KEY_COLUMNS) . '`)'],
			self::callsMatching('ALTER TABLE')
		);
	}

	/**
	 * @return void
	 */
	public function testMissingTableIsLeftToTheInstaller(): void {
		$this->assertTrue(mactrack_repair_aggregated_ports_key());
		$this->assertNull(self::callIndex('SHOW INDEX FROM'));
		$this->assertNull(self::callIndex('GET_LOCK'));
	}

	/**
	 * @return void
	 */
	public function testConcurrentRepairIsSkippedWhileAnotherRequestHoldsTheLock(): void {
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'GET_LOCK', '0');
		self::givenKey(self::keyRows(1));

		$this->assertFalse(mactrack_repair_aggregated_ports_key());
		$this->assertNull(self::callIndex('BEGIN'));
		$this->assertNull(self::callIndex('RELEASE_LOCK'));
	}

	/**
	 * @return void
	 */
	public function testRepairFinishedByAnotherRequestWhileWaitingIsNotRepeated(): void {
		mactrack_test_queue_return_for('db_fetch_assoc', "SHOW TABLES LIKE 'mac_track_aggregated_ports'", [['Tables_in_cacti' => 'mac_track_aggregated_ports']]);
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'GET_LOCK', '1');
		mactrack_test_queue_return('db_fetch_assoc_prepared', self::keyRows(1)); // before the lock
		mactrack_test_queue_return('db_fetch_assoc_prepared', self::keyRows(0)); // after the lock

		$this->assertTrue(mactrack_repair_aggregated_ports_key());
		$this->assertNull(self::callIndex('BEGIN'));
		$this->assertSame([], self::callsMatching('ALTER TABLE'));
		$this->assertNotNull(self::callIndex('RELEASE_LOCK'));
	}

	/**
	 * @return void
	 */
	public function testFailedMergeRollsBackAndKeepsTheKey(): void {
		self::givenKey(self::keyRows(1));
		mactrack_test_queue_return('db_execute_prepared', false); // merge UPDATE

		$this->assertFalse(mactrack_repair_aggregated_ports_key());
		$this->assertNotNull(self::callIndex('ROLLBACK'));
		$this->assertNull(self::callIndex('DELETE a FROM'));
		$this->assertSame([], self::callsMatching('ALTER TABLE'));
		$this->assertNotNull(self::callIndex('RELEASE_LOCK'));
	}

	/**
	 * @return void
	 */
	public function testFailedKeyRebuildIsReported(): void {
		self::givenKey(self::keyRows(1));

		foreach ([true, true, false] as $result) { // UPDATE, DELETE, ALTER
			mactrack_test_queue_return('db_execute_prepared', $result);
		}

		$this->assertFalse(mactrack_repair_aggregated_ports_key());
		$this->assertContains('MACTRACK: Could not restore the mac_track_aggregated_ports unique key', $GLOBALS['__test_cacti_log']);
		$this->assertNotNull(self::callIndex('RELEASE_LOCK'));
	}

	/**
	 * Stub the lookups mactrack_database_upgrade() makes outside this change.
	 *
	 * @return void
	 */
	private static function givenUpgradeFixtures(): void {
		mactrack_test_queue_return('db_fetch_cell', '0');
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'COUNT(*) FROM mac_track_sites', '1');
		mactrack_test_queue_return_for('db_fetch_assoc', 'SHOW COLUMNS FROM mac_track_ips', [
			['Field' => 'port_number', 'Type' => 'varchar(30)'],
		]);
	}

	/**
	 * @return void
	 */
	public function testSeparatorMigrationRunsOnceAndRestoresTheUniqueKey(): void {
		self::givenUpgradeFixtures();
		self::givenKey(self::keyRows(0));

		mactrack_database_upgrade();

		$this->assertSame('on', read_config_option('mt_mac_separators_removed'));
		$this->assertCount(1, self::callsMatching('ALTER TABLE mac_track_aggregated_ports DROP INDEX port_number'));
		$this->assertCount(2, self::callsMatching('UPDATE mac_track_ports SET mac_address = REPLACE'));
		$this->assertLessThan(
			self::callIndex('UPDATE mac_track_aggregated_ports SET mac_address'),
			self::callIndex('ALTER TABLE mac_track_aggregated_ports DROP INDEX port_number'),
			'The unique key must be gone before separators are stripped'
		);
	}

	/**
	 * Before this fix the 4.9 block compared an array to 7, so it dropped
	 * the key and rewrote every MAC table on each upgrade.
	 *
	 * @return void
	 */
	public function testLaterUpgradesSkipTheSeparatorMigration(): void {
		set_config_option('mt_mac_separators_removed', 'on');
		self::givenUpgradeFixtures();
		self::givenKey(self::keyRows(0));

		mactrack_database_upgrade();

		$this->assertSame([], self::callsMatching('REPLACE(mac_address'));
		$this->assertSame([], self::callsMatching('INDEX port_number'));
		$this->assertSame([], self::callsMatching('INDEX `port_number`'));
	}

	/**
	 * An install that recorded the new version after a failed refresh never
	 * re-enters the upgrade, so the repair must also run without a drift.
	 *
	 * @return void
	 */
	public function testCheckUpgradeRepairsTheKeyWithoutAVersionChange(): void {
		$info = plugin_mactrack_version();
		mactrack_test_queue_return('get_current_page', 'index.php');
		mactrack_test_queue_return('db_fetch_row', ['version' => $info['version'], 'status' => 1]);
		self::givenKey(self::keyRows(1));

		mactrack_check_upgrade();

		$this->assertCount(1, self::callsMatching('ADD UNIQUE INDEX `port_number`'));
		$this->assertNull(self::callIndex('UPDATE plugin_config'));
	}

	/**
	 * @return void
	 */
	public function testCheckUpgradeLeavesADisabledPluginAlone(): void {
		$info = plugin_mactrack_version();
		mactrack_test_queue_return('get_current_page', 'index.php');
		mactrack_test_queue_return('db_fetch_row', ['version' => $info['version'], 'status' => 0]);
		self::givenKey(self::keyRows(1));

		mactrack_check_upgrade();

		$this->assertNull(self::callIndex('SHOW INDEX FROM mac_track_aggregated_ports WHERE Key_name = ?'));
	}

	/**
	 * plugin_mactrack_install() refreshes the table through Cacti, which
	 * fails while duplicates exist, so the repair has to come first.
	 *
	 * @return void
	 */
	public function testCheckUpgradeRepairsBeforeReinstalling(): void {
		$source = (string) file_get_contents(dirname(__DIR__, 2) . '/setup.php');
		$body   = substr($source, (int) strpos($source, 'function mactrack_check_upgrade('));

		$repair  = strpos($body, 'mactrack_repair_aggregated_ports_key();');
		$install = strpos($body, 'plugin_mactrack_install(false);');

		$this->assertNotFalse($repair);
		$this->assertNotFalse($install);
		$this->assertLessThan($install, $repair);
	}
}
