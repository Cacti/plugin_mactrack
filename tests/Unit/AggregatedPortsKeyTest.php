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
	public function testSeparatorMigrationNormalizesSatelliteTablesAndDelegatesTheAggregatedTable(): void {
		self::givenUpgradeFixtures();
		self::givenKey(self::keyRows(0));

		mactrack_database_upgrade();

		$this->assertSame('on', read_config_option('mt_mac_separators_removed'));
		$this->assertCount(2, self::callsMatching('UPDATE mac_track_ports SET mac_address = REPLACE'));

		// The aggregated-ports table is no longer rewritten in place; the
		// dedicated rebuild renames it aside and repopulates it per scan date.
		$this->assertSame([], self::callsMatching('UPDATE mac_track_aggregated_ports SET mac_address'));
		$this->assertCount(1, self::callsMatching('RENAME TABLE mac_track_aggregated_ports TO mac_track_aggregated_ports_upgrade'));
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
	 * @return void
	 */
	public function testUpgradeWidensAnIntRowIdToBigint(): void {
		self::givenUpgradeFixtures();
		self::givenKey(self::keyRows(0));
		mactrack_test_queue_return_for('db_fetch_row_prepared', 'SHOW COLUMNS FROM mac_track_aggregated_ports LIKE ?', ['Field' => 'row_id', 'Type' => 'int(10) unsigned']);

		mactrack_database_upgrade();

		$this->assertSame(
			['ALTER TABLE mac_track_aggregated_ports MODIFY COLUMN row_id bigint(20) unsigned NOT NULL auto_increment'],
			self::callsMatching('MODIFY COLUMN row_id')
		);
	}

	/**
	 * @return void
	 */
	public function testUpgradeLeavesABigintRowIdAlone(): void {
		self::givenUpgradeFixtures();
		self::givenKey(self::keyRows(0));
		mactrack_test_queue_return_for('db_fetch_row_prepared', 'SHOW COLUMNS FROM mac_track_aggregated_ports LIKE ?', ['Field' => 'row_id', 'Type' => 'bigint(20) unsigned']);

		mactrack_database_upgrade();

		$this->assertSame([], self::callsMatching('MODIFY COLUMN row_id'));
	}

	/**
	 * @return void
	 */
	public function testFreshSchemaUsesABigintRowId(): void {
		$source = (string) file_get_contents(dirname(__DIR__, 2) . '/includes/database.php');

		$this->assertStringContainsString("['name' => 'row_id', 'unsigned' => true, 'type' => 'bigint(20)'", $source);
		$this->assertStringNotContainsString("['name' => 'row_id', 'unsigned' => true, 'type' => 'int(10)'", $source);
	}

	/**
	 * An install that recorded the new version after a failed refresh never
	 * re-enters the upgrade on a version drift, so a non-unique key still has
	 * to hand the rebuild to the background upgrade.
	 *
	 * @return void
	 */
	public function testCheckUpgradeLaunchesTheBackgroundUpgradeWhenTheKeyIsNotUnique(): void {
		$info = plugin_mactrack_version();
		set_config_option('path_php_binary', '/usr/bin/php');
		mactrack_test_queue_return('get_current_page', 'index.php');
		mactrack_test_queue_return('db_fetch_row', ['version' => $info['version'], 'status' => 1]);
		self::givenKey(self::keyRows(1));

		mactrack_check_upgrade();

		$launches = array_values(array_filter($GLOBALS['__test_db_calls'], static fn ($c) => $c['fn'] === 'exec_background'));

		$this->assertCount(1, $launches);
		$this->assertStringContainsString('upgrade_database.php', $launches[0]['params'][0]);
		$this->assertSame('on', read_config_option('mt_db_upgrade_notified'));
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
	 * A page load that sees a registered, non-stale upgrade process must not
	 * launch a second copy.
	 *
	 * @return void
	 */
	public function testCheckUpgradeDoesNotRelaunchWhileTheUpgradeIsRunning(): void {
		set_config_option('path_php_binary', '/usr/bin/php');
		mactrack_test_queue_return('get_current_page', 'index.php');
		mactrack_test_queue_return('db_fetch_row', ['version' => '4.8', 'status' => 1]);
		// A registered upgrade process that has not outlived its timeout.
		mactrack_test_queue_return_for('db_fetch_row_prepared', 'FROM processes', ['pid' => 1234, 'started' => 100, 'now' => 150, 'timeout' => 86400]);

		mactrack_check_upgrade();

		$launches = array_filter($GLOBALS['__test_db_calls'], static fn ($c) => $c['fn'] === 'exec_background');

		$this->assertCount(0, $launches);
	}

	/**
	 * Once the background upgrade has recorded the new version and a unique
	 * key, the hook stops launching it and stops touching the schema.
	 *
	 * @return void
	 */
	public function testCheckUpgradeDoesNothingOnceTheUpgradeHasFinished(): void {
		$info = plugin_mactrack_version();
		set_config_option('path_php_binary', '/usr/bin/php');
		mactrack_test_queue_return('get_current_page', 'index.php');
		mactrack_test_queue_return('db_fetch_row', ['version' => $info['version'], 'status' => 1]);
		// Realms already cover every file, so the inline realm repair is skipped.
		mactrack_test_queue_return_for('db_fetch_assoc_prepared', 'FROM plugin_realms', [
			['file' => 'mactrack_view_dashboard.php,mactrack_view_ips.php,mactrack_view_arp.php,mactrack_view_macs.php,mactrack_view_dot1x.php,mactrack_view_sites.php,mactrack_view_devices.php,mactrack_view_interfaces.php,mactrack_view_graphs.php,mactrack_ajax.php,mactrack_ajax_admin.php,mactrack_devices.php,mactrack_snmp.php,mactrack_sites.php,mactrack_device_types.php,mactrack_utilities.php,mactrack_macwatch.php,mactrack_macauth.php,mactrack_vendormacs.php'],
		]);
		self::givenKey(self::keyRows(0));

		mactrack_check_upgrade();

		$launches = array_filter($GLOBALS['__test_db_calls'], static fn ($c) => $c['fn'] === 'exec_background');

		$this->assertCount(0, $launches);
		$this->assertNull(self::callIndex('UPDATE plugin_config'));
	}

	/**
	 * An interrupted rebuild restores the destination's unique key before it
	 * finishes copying, so a healthy key at the current version is not proof
	 * the upgrade is done: the leftover work table must keep it launching.
	 *
	 * @return void
	 */
	public function testCheckUpgradeLaunchesWhileTheRebuildWorkTableRemains(): void {
		$info = plugin_mactrack_version();
		set_config_option('path_php_binary', '/usr/bin/php');
		mactrack_test_queue_return('get_current_page', 'index.php');
		mactrack_test_queue_return('db_fetch_row', ['version' => $info['version'], 'status' => 1]);
		// The aggregated table is healthy (unique key)...
		self::givenKey(self::keyRows(0));
		// ...but the renamed rebuild work table is still present.
		mactrack_test_queue_return_for('db_fetch_assoc', "SHOW TABLES LIKE 'mac_track_aggregated_ports_upgrade'", [['t' => 'mac_track_aggregated_ports_upgrade']]);

		mactrack_check_upgrade();

		$launches = array_filter($GLOBALS['__test_db_calls'], static fn ($c) => $c['fn'] === 'exec_background');

		$this->assertCount(1, $launches);
	}

	/**
	 * plugin_mactrack_install() refreshes the aggregated-ports table through
	 * Cacti, which fails while the pre-normalization duplicates and non-unique
	 * key exist, so the rebuild has to come first.
	 *
	 * @return void
	 */
	public function testPerformUpgradeRebuildsAggregatedPortsBeforeReinstalling(): void {
		$source = (string) file_get_contents(dirname(__DIR__, 2) . '/setup.php');
		$body   = substr($source, (int) strpos($source, 'function mactrack_perform_upgrade('));

		$rebuild = strpos($body, 'mactrack_rebuild_aggregated_ports(');
		$install = strpos($body, 'plugin_mactrack_install(false);');

		$this->assertNotFalse($rebuild);
		$this->assertNotFalse($install);
		$this->assertLessThan($install, $rebuild);
	}

	/**
	 * With no processes table, the launcher treats the upgrade as not running.
	 *
	 * @return void
	 */
	public function testBackgroundUpgradeNotRunningWithoutAProcessesTable(): void {
		mactrack_test_queue_return('db_table_exists', false);

		$this->assertFalse(mactrack_background_upgrade_running());
	}

	/**
	 * A registration that has outlived its timeout is treated as stale so a
	 * crashed upgrade can be relaunched.
	 *
	 * @return void
	 */
	public function testBackgroundUpgradeTreatsAnExpiredRegistrationAsStale(): void {
		mactrack_test_queue_return('db_table_exists', true);
		mactrack_test_queue_return_for('db_fetch_row_prepared', 'FROM processes', ['pid' => 7, 'started' => 0, 'now' => 100000, 'timeout' => 300]);

		$this->assertFalse(mactrack_background_upgrade_running());
	}
}