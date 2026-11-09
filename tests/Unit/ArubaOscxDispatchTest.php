<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/**
 * Verifies the Aruba AOS-CX default device types dispatch to registered
 * scanner callbacks, including repair of callback names on upgrade.
 */
final class ArubaOscxDispatchTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('includes/database.php');
		self::loadPluginSource('setup.php');
	}

	/**
	 * @return void
	 */
	public function testUpgradeRepairsOnlyKnownObsoleteArubaDefaults(): void {
		mactrack_test_queue_return('db_fetch_cell', '0');
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'GET_LOCK', '1');
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'RELEASE_LOCK', '1');
		mactrack_test_queue_return('db_fetch_cell_prepared', '0');
		mactrack_test_queue_return('db_fetch_cell_prepared', '1');
		mactrack_test_queue_return_for('db_fetch_assoc', 'SHOW COLUMNS FROM mac_track_ips', [
			['Field' => 'port_number', 'Type' => 'varchar(30)'],
		]);
		mactrack_database_upgrade();

		$updates = array_values(array_filter($GLOBALS['__test_db_calls'], static function ($call) {
			return $call['fn'] === 'db_execute_prepared' && strpos($call['sql'], 'UPDATE mac_track_device_types') !== false;
		}));

		$this->assertCount(4, $updates);
		$this->assertSame([
			'get_aruba_oscx_switch_ports', 'Aruba 6300-default', 'get_oscx_switch_ports',
		], $updates[0]['params']);
		$this->assertSame([
			'get_aruba_oscx_arp_table', 'Aruba 6300-default', 'get_oscx_arp_table',
		], $updates[1]['params']);
		$this->assertSame([
			'get_aruba_oscx_switch_ports', 'Aruba 6200-default', 'get_oscx_switch_ports',
		], $updates[2]['params']);
		$this->assertSame([
			'get_aruba_oscx_arp_table', 'Aruba 6200-default', 'get_oscx_arp_table',
		], $updates[3]['params']);
		$this->assertStringContainsString('AND scanning_function = ?', $updates[0]['sql']);
		$this->assertStringContainsString('AND ip_scanning_function = ?', $updates[1]['sql']);

		$inserts = array_column(array_filter($GLOBALS['__test_db_calls'], static function ($call) {
			return $call['fn'] === 'db_execute' && (strpos($call['sql'], "'Aruba 6300-default'") !== false || strpos($call['sql'], "'Aruba 6200-default'") !== false) && strpos($call['sql'], 'INSERT INTO mac_track_device_types') !== false;
		}), 'sql');
		$this->assertCount(2, $inserts);
		$callbacks = "'get_aruba_oscx_switch_ports','get_aruba_oscx_arp_table'";
		$this->assertStringContainsString($callbacks, $inserts[0]);
		$this->assertStringContainsString($callbacks, $inserts[1]);
	}

	/**
	 * @return void
	 */
	public function testSeededArubaTypesUseRegisteredCallbackNames(): void {
		self::loadPluginSource('lib/mactrack_aruba_oscx.php');

		$this->assertTrue(is_callable('get_aruba_oscx_switch_ports'));
		$this->assertTrue(is_callable('get_aruba_oscx_arp_table'));

		$source = file_get_contents(dirname(__DIR__, 2) . '/includes/database.php');
		$this->assertSame(4, substr_count($source, "'get_aruba_oscx_switch_ports','get_aruba_oscx_arp_table'"));
	}

	/**
	 * Fresh install seeds live in mactrack_setup_database(), a second copy of
	 * the default-type INSERTs. Cover that path so both copies stay in sync.
	 *
	 * @return void
	 */
	public function testSetupDatabaseSeedsArubaOscxCallbacks(): void {
		// The COUNT(*) gate uses == 0; PHP 8 treats '' == 0 as false, so the
		// stub must return the string '0' rather than its empty-string default.
		mactrack_test_queue_return('db_fetch_cell', '0');
		mactrack_test_queue_return_for('db_fetch_cell', "description LIKE '%-default'", '0');
		mactrack_setup_database();

		$inserts = array_values(array_filter($GLOBALS['__test_db_calls'], static function ($call) {
			return $call['fn'] === 'db_execute' && (strpos($call['sql'], "'Aruba 6300-default'") !== false || strpos($call['sql'], "'Aruba 6200-default'") !== false) && strpos($call['sql'], 'INSERT INTO mac_track_device_types') !== false;
		}));
		$this->assertCount(2, $inserts);
		$callbacks = "'get_aruba_oscx_switch_ports','get_aruba_oscx_arp_table'";
		$this->assertStringContainsString($callbacks, $inserts[0]['sql']);
		$this->assertStringContainsString($callbacks, $inserts[1]['sql']);
	}
}
