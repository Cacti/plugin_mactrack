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
 * End-to-end coverage of the install/upgrade entry points that wire into
 * mactrack_ensure_default_site() (issue#357): a full mactrack_setup_database()
 * run (every table, plus device-type seeding) followed by the Default-site
 * seed, exercised the way plugin_mactrack_install()/mactrack_check_upgrade()
 * actually call it, rather than the seeding function in isolation.
 */
final class DefaultSiteIdempotencyTest extends TestCase {
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
	public function testOperatorInitiatedInstallSeedsTheDefaultSiteOnce(): void {
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'GET_LOCK', '1');
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'RELEASE_LOCK', '1');
		mactrack_test_queue_return('db_fetch_cell_prepared', '0');
		mactrack_test_queue_return('db_fetch_cell_prepared', '1');
		mactrack_test_queue_return('db_execute_prepared', true);

		$this->assertTrue(mactrack_setup_table_new(true));
		$this->assertSame('off', read_config_option('mt_default_site_seed_pending'));

		$inserted = array_filter(
			array_column($GLOBALS['__test_db_calls'], 'sql'),
			static fn ($sql) => strpos($sql, 'INSERT INTO mac_track_sites') !== false
		);
		$this->assertCount(1, $inserted, 'Exactly one Default-site insert should have been attempted');
	}

	/**
	 * @return void
	 */
	public function testReenteringUpgradeAfterASuccessfulSeedDoesNotInsertAgain(): void {
		// The site already exists (e.g. install already succeeded); a later
		// automatic upgrade check must not attempt another insert.
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'COUNT(*) FROM mac_track_sites', '1');

		$this->assertTrue(mactrack_setup_table_new(false));

		foreach ($GLOBALS['__test_db_calls'] as $call) {
			$this->assertStringNotContainsString('INSERT INTO mac_track_sites', $call['sql']);
			$this->assertStringNotContainsString('GET_LOCK', $call['sql']);
		}
	}

	/**
	 * @return void
	 */
	public function testAnOperatorInitiatedReinstallClearsAPriorBackoffEvenWhileThrottled(): void {
		set_config_option('mt_default_site_seed_pending', 'on');
		set_config_option('mt_default_site_seed_attempts', '4');
		set_config_option('mt_default_site_seed_next_retry', (string) (time() + 3600));

		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'GET_LOCK', '1');
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'RELEASE_LOCK', '1');
		mactrack_test_queue_return('db_fetch_cell_prepared', '0');
		mactrack_test_queue_return('db_fetch_cell_prepared', '1');
		mactrack_test_queue_return('db_execute_prepared', true);

		$this->assertTrue(mactrack_setup_table_new(true));
		$this->assertSame('off', read_config_option('mt_default_site_seed_pending'));
	}

	/**
	 * @return void
	 */
	public function testAnAutomaticUpgradePreservesAPriorBackoffWindow(): void {
		$future = (string) (time() + 3600);
		set_config_option('mt_default_site_seed_pending', 'on');
		set_config_option('mt_default_site_seed_attempts', '2');
		set_config_option('mt_default_site_seed_next_retry', $future);

		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'COUNT(*) FROM mac_track_sites', '0');

		$this->assertFalse(mactrack_setup_table_new(false));
		$this->assertSame('on', read_config_option('mt_default_site_seed_pending'));
		$this->assertSame($future, read_config_option('mt_default_site_seed_next_retry'));

		foreach ($GLOBALS['__test_db_calls'] as $call) {
			$this->assertStringNotContainsString('GET_LOCK', $call['sql']);
		}
	}

	/**
	 * @return void
	 */
	public function testInstallReturnsTheSeedResultForTheCallerToAct(): void {
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'GET_LOCK', '0');
		mactrack_test_queue_return('db_fetch_cell_prepared', '0');

		$this->assertFalse(plugin_mactrack_install());
		$this->assertSame('on', read_config_option('mt_default_site_seed_pending'));
	}
}
