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
 * mactrack_setup_database() used to guarantee a Default site with a racy
 * "if no rows, INSERT" check, so two workers initializing at once (install
 * + poller, concurrent web requests) could both pass the check and insert
 * duplicate Default sites (issue#357). These tests cover the advisory-lock
 * seeding, the retry/backoff state machine, and the operator-notification
 * escalation that replaced it.
 */
final class DefaultSiteSeedingTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('includes/database.php');
	}

	/**
	 * @return void
	 */
	public function testSiteConfigurationExistsReflectsTheRowCount(): void {
		mactrack_test_queue_return('db_fetch_cell_prepared', '0');
		$this->assertFalse(mactrack_site_configuration_exists());

		mactrack_test_queue_return('db_fetch_cell_prepared', '2');
		$this->assertTrue(mactrack_site_configuration_exists());
	}

	/**
	 * @return void
	 */
	public function testSeedDefaultSiteSkipsTheLockWhenASiteAlreadyExists(): void {
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'COUNT(*) FROM mac_track_sites', '1');

		$this->assertTrue(mactrack_seed_default_site());

		foreach ($GLOBALS['__test_db_calls'] as $call) {
			$this->assertStringNotContainsString('GET_LOCK', $call['sql']);
		}
	}

	/**
	 * @return void
	 */
	public function testSeedDefaultSiteAcquiresTheLockInsertsAndReleases(): void {
		// db_fetch_cell_prepared() is called for the initial COUNT(*), then
		// GET_LOCK, then the post-insert COUNT(*) recheck, then RELEASE_LOCK.
		// GET_LOCK/RELEASE_LOCK are matched (sticky) since each occurs once;
		// the two COUNT(*) calls need different answers, so they go through
		// the plain FIFO queue instead (matched entries are checked first
		// and never match the COUNT(*) SQL, so they don't interfere).
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'GET_LOCK', '1');
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'RELEASE_LOCK', '1');
		mactrack_test_queue_return('db_fetch_cell_prepared', '0');
		mactrack_test_queue_return('db_fetch_cell_prepared', '1');
		mactrack_test_queue_return('db_execute_prepared', true);

		$this->assertTrue(mactrack_seed_default_site());

		$sqlCalls = array_column($GLOBALS['__test_db_calls'], 'sql');
		$this->assertTrue((bool) array_filter($sqlCalls, static fn ($sql) => strpos($sql, 'GET_LOCK') !== false));
		$this->assertTrue((bool) array_filter($sqlCalls, static fn ($sql) => strpos($sql, 'INSERT INTO mac_track_sites') !== false));
		$this->assertTrue((bool) array_filter($sqlCalls, static fn ($sql) => strpos($sql, 'RELEASE_LOCK') !== false));
	}

	/**
	 * @return void
	 */
	public function testSeedDefaultSiteFailsClosedWhenTheLockCannotBeAcquired(): void {
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'COUNT(*) FROM mac_track_sites', '0');
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'GET_LOCK', '0');

		$this->assertFalse(mactrack_seed_default_site());

		$sqlCalls = array_column($GLOBALS['__test_db_calls'], 'sql');
		$this->assertFalse((bool) array_filter($sqlCalls, static fn ($sql) => strpos($sql, 'INSERT INTO mac_track_sites') !== false));
	}

	/**
	 * @return void
	 */
	public function testSeedDefaultSiteRecoversIfAnotherWorkerWonTheRace(): void {
		// First check: no site. Lock denied. Second check (inside the
		// lock-denied branch): a concurrent worker already inserted one.
		mactrack_test_queue_return('db_fetch_cell_prepared', '0');
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'GET_LOCK', '0');
		mactrack_test_queue_return('db_fetch_cell_prepared', '1');

		$this->assertTrue(mactrack_seed_default_site());
	}

	/**
	 * @return void
	 */
	public function testEnsureDefaultSiteResetsRetryStateOnSuccess(): void {
		set_config_option('mt_default_site_seed_pending', 'on');
		set_config_option('mt_default_site_seed_attempts', '2');
		set_config_option('mt_default_site_seed_next_retry', (string) (time() - 10));

		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'COUNT(*) FROM mac_track_sites', '1');

		$this->assertTrue(mactrack_ensure_default_site(false));

		$this->assertSame('off', read_config_option('mt_default_site_seed_pending'));
		$this->assertSame('0', read_config_option('mt_default_site_seed_attempts'));
		$this->assertSame('0', read_config_option('mt_default_site_seed_next_retry'));
	}

	/**
	 * @return void
	 */
	public function testEnsureDefaultSiteSchedulesIncreasingBackoffOnRepeatedFailure(): void {
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'COUNT(*) FROM mac_track_sites', '0');
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'GET_LOCK', '0');

		$before = time();
		$this->assertFalse(mactrack_ensure_default_site(false));

		$this->assertSame('on', read_config_option('mt_default_site_seed_pending'));
		$this->assertSame('1', read_config_option('mt_default_site_seed_attempts'));
		$this->assertGreaterThanOrEqual($before + 60, (int) read_config_option('mt_default_site_seed_next_retry'));
	}

	/**
	 * @return void
	 */
	public function testEnsureDefaultSiteDoesNotRetryBeforeTheBackoffWindowElapses(): void {
		set_config_option('mt_default_site_seed_pending', 'on');
		set_config_option('mt_default_site_seed_attempts', '1');
		set_config_option('mt_default_site_seed_next_retry', (string) (time() + 3600));

		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'COUNT(*) FROM mac_track_sites', '0');

		$this->assertFalse(mactrack_ensure_default_site(false));

		foreach ($GLOBALS['__test_db_calls'] as $call) {
			$this->assertStringNotContainsString('GET_LOCK', $call['sql'], 'A throttled retry must not attempt to take the seed lock');
		}
	}

	/**
	 * @return void
	 */
	public function testEnsureDefaultSiteClearsThrottleWhenTheSiteWasRepairedManually(): void {
		set_config_option('mt_default_site_seed_pending', 'on');
		set_config_option('mt_default_site_seed_attempts', '3');
		set_config_option('mt_default_site_seed_next_retry', (string) (time() + 3600));

		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'COUNT(*) FROM mac_track_sites', '1');

		$this->assertTrue(mactrack_ensure_default_site(false));
		$this->assertSame('off', read_config_option('mt_default_site_seed_pending'));
	}

	/**
	 * @return void
	 */
	public function testRetryDefaultSiteIsANoOpWhenNothingIsPending(): void {
		set_config_option('mt_default_site_seed_pending', 'off');

		$this->assertTrue(mactrack_retry_default_site());
		$this->assertSame([], $GLOBALS['__test_db_calls']);
	}

	/**
	 * @return void
	 */
	public function testRetryDefaultSiteAttemptsToSeedWhenPending(): void {
		set_config_option('mt_default_site_seed_pending', 'on');
		set_config_option('mt_default_site_seed_next_retry', '0');
		mactrack_test_queue_return_for('db_fetch_cell_prepared', 'COUNT(*) FROM mac_track_sites', '1');

		$this->assertTrue(mactrack_retry_default_site());
	}
}
