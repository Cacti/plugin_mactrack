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
 * Optional MySQL/MariaDB verification. Supply MACTRACK_TEST_DSN,
 * MACTRACK_TEST_DB_USER and MACTRACK_TEST_DB_PASSWORD. All fixture tables
 * are connection-local temporary tables; installed data is never changed.
 */
final class AggregatedPortsFreshnessDatabaseTest extends TestCase {
	private PDO $db;
	private const PREVIOUS = '2026-11-12 01:00:00';
	private const CURRENT  = '2026-11-13 01:00:00';

	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('lib/mactrack_functions.php');
	}

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$dsn = getenv('MACTRACK_TEST_DSN');

		if (!$dsn) {
			$this->markTestSkipped('Set MACTRACK_TEST_DSN to run database integration tests.');
		}

		$this->db = new PDO($dsn, getenv('MACTRACK_TEST_DB_USER'), getenv('MACTRACK_TEST_DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
		$this->db->exec("CREATE TEMPORARY TABLE mac_track_aggregated_ports (
			site_id INT NOT NULL, device_id INT NOT NULL,
			hostname VARCHAR(40) NOT NULL DEFAULT '', device_name VARCHAR(100) NOT NULL DEFAULT '',
			vlan_id VARCHAR(5) NOT NULL DEFAULT '', vlan_name VARCHAR(50) NOT NULL DEFAULT '',
			mac_address VARCHAR(20) NOT NULL, vendor_mac VARCHAR(8) DEFAULT NULL,
			ip_address VARCHAR(20) NOT NULL DEFAULT '', dns_hostname VARCHAR(200) DEFAULT NULL,
			port_number VARCHAR(30) NOT NULL, port_name VARCHAR(50) NOT NULL DEFAULT '',
			date_last TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			first_scan_date TIMESTAMP NULL DEFAULT NULL, count_rec INT NOT NULL DEFAULT 1,
			active_last TINYINT NOT NULL DEFAULT 1, authorized TINYINT NOT NULL DEFAULT 0,
			UNIQUE KEY port_number (port_number, mac_address, ip_address, device_id, site_id, vlan_id, authorized)
		) ENGINE=InnoDB");
		$this->db->exec('CREATE TEMPORARY TABLE mac_track_temp_ports LIKE mac_track_aggregated_ports');
		$this->db->exec('ALTER TABLE mac_track_temp_ports
			DROP date_last, DROP first_scan_date, DROP count_rec, DROP active_last,
			ADD scan_date TIMESTAMP NULL DEFAULT NULL');
		$this->db->exec('CREATE TEMPORARY TABLE mac_track_ips (
			site_id INT, mac_address VARCHAR(20), ip_address VARCHAR(20), scan_date TIMESTAMP NULL
		) ENGINE=InnoDB');
		$this->db->exec('CREATE TEMPORARY TABLE mac_track_devices (
			device_id INT, site_id INT, snmp_status INT, disabled VARCHAR(2), last_rundate TIMESTAMP NULL
		) ENGINE=InnoDB');
	}

	/**
	 * Seed the outcome of a device scan.
	 *
	 * @param int    $id       Device identifier.
	 * @param int    $site     Site identifier.
	 * @param int    $status   SNMP status.
	 * @param string $date     Last scanner timestamp.
	 * @param string $disabled Administrative disable flag.
	 *
	 * @return void
	 */
	private function device(int $id, int $site, int $status = HOST_UP, string $date = self::CURRENT, string $disabled = ''): void {
		$this->db->prepare('INSERT INTO mac_track_devices VALUES (?, ?, ?, ?, ?)')->execute([$id, $site, $status, $disabled, $date]);
	}

	/**
	 * Seed a previously observed attachment.
	 *
	 * @param int    $id   Device identifier.
	 * @param int    $site Site identifier.
	 * @param string $mac  Endpoint MAC.
	 * @param string $ip   Previously associated address.
	 *
	 * @return void
	 */
	private function previous(int $id, int $site, string $mac = '001122334455', string $ip = ''): void {
		$this->db->prepare('INSERT INTO mac_track_aggregated_ports
			(device_id, site_id, mac_address, port_number, ip_address, date_last, first_scan_date)
			VALUES (?, ?, ?, "1", ?, ?, ?)')->execute([$id, $site, $mac, $ip, self::PREVIOUS, self::PREVIOUS]);
	}

	/**
	 * Stage a Layer 2 observation.
	 *
	 * @param int    $id   Device identifier.
	 * @param int    $site Site identifier.
	 * @param string $mac  Endpoint MAC.
	 * @param string $date Observation timestamp.
	 *
	 * @return void
	 */
	private function observed(int $id, int $site, string $mac = '001122334455', string $date = self::CURRENT): void {
		$this->db->prepare('INSERT INTO mac_track_temp_ports
			(device_id, site_id, mac_address, port_number, scan_date)
			VALUES (?, ?, ?, "1", ?)')->execute([$id, $site, $mac, $date]);
	}

	/**
	 * Execute the production SQL against the temporary fixture tables.
	 *
	 * @param int $site Site scope, or zero for all sites.
	 *
	 * @return void
	 */
	private function applyScan(int $site = 0): void {
		$GLOBALS['__test_db_calls'] = [];
		$this->assertTrue(mactrack_update_aggregated_ports(self::CURRENT, $site));

		// Execute the production helper's actual statements and bound values.
		foreach ($GLOBALS['__test_db_calls'] as $call) {
			$this->db->prepare($call['sql'])->execute($call['params']);
		}
	}

	/**
	 * @return void
	 */
	public function testFailedEmptyUnscannedAndDisabledDevicesKeepTheirLastObservation(): void {
		$this->device(1, 1, 1);
		$this->device(2, 1);
		$this->device(3, 1, HOST_UP, self::PREVIOUS);
		$this->device(4, 1, HOST_UP, self::CURRENT, 'on');

		foreach ([1, 2, 3, 4] as $id) {
			$this->previous($id, 1);
		}

		// Failed, old and disabled scans may have leftover staging rows.
		foreach ([1, 3, 4] as $id) {
			$this->observed($id, 1, 'AABBCCDDEEFF');
		}

		$this->applyScan();
		$rows = $this->db->query('SELECT date_last, active_last FROM mac_track_aggregated_ports WHERE mac_address = "001122334455"')->fetchAll(PDO::FETCH_ASSOC);
		$this->assertCount(4, $rows);

		foreach ($rows as $row) {
			$this->assertSame(self::PREVIOUS, $row['date_last']);
			$this->assertSame(1, (int) $row['active_last']);
		}
	}

	/**
	 * @return void
	 */
	public function testSiteScanLeavesOtherSitesAndTheirTimestampsUntouched(): void {
		foreach ([1, 2] as $site) {
			$this->device($site, $site);
			$this->previous($site, $site);
			$this->observed($site, $site, 'AABBCCDDEEFF');
		}

		$this->applyScan(1);
		$rows = $this->db->query('SELECT site_id, date_last, active_last FROM mac_track_aggregated_ports WHERE mac_address = "001122334455" ORDER BY site_id')->fetchAll(PDO::FETCH_ASSOC);
		$this->assertSame(self::PREVIOUS, $rows[0]['date_last']);
		$this->assertSame(0, (int) $rows[0]['active_last']);
		$this->assertSame(self::PREVIOUS, $rows[1]['date_last']);
		$this->assertSame(1, (int) $rows[1]['active_last']);
		$this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM mac_track_aggregated_ports WHERE site_id = 2 AND mac_address = "AABBCCDDEEFF"')->fetchColumn());
	}

	/**
	 * @return void
	 */
	public function testLayerTwoWithoutArpIsStoredAndRetryDoesNotInflateCounts(): void {
		$this->device(1, 1);
		$this->previous(1, 1);
		$this->observed(1, 1);
		$this->applyScan();
		$this->applyScan();
		$row = $this->db->query('SELECT * FROM mac_track_aggregated_ports')->fetch(PDO::FETCH_ASSOC);
		$this->assertSame('', $row['ip_address']);
		$this->assertSame(self::CURRENT, $row['date_last']);
		$this->assertSame(self::PREVIOUS, $row['first_scan_date']);
		$this->assertSame(2, (int) $row['count_rec']);
		$this->assertSame(1, (int) $row['active_last']);
	}

	/**
	 * @return void
	 */
	public function testAddressesStayScopedToTheCurrentScanAndSite(): void {
		$this->device(1, 1);
		$this->observed(1, 1);
		$insert = $this->db->prepare('INSERT INTO mac_track_ips VALUES (?, "001122334455", ?, ?)');
		$insert->execute([1, '192.0.2.1', self::CURRENT]);
		$insert->execute([1, '192.0.2.1', self::CURRENT]);
		$insert->execute([1, '192.0.2.2', self::CURRENT]);
		$insert->execute([2, '198.51.100.1', self::CURRENT]);
		$insert->execute([1, '192.0.2.99', self::PREVIOUS]);
		$this->applyScan();
		$rows = $this->db->query('SELECT ip_address, count_rec FROM mac_track_aggregated_ports ORDER BY ip_address')->fetchAll(PDO::FETCH_ASSOC);
		$this->assertSame(['192.0.2.1', '192.0.2.2'], array_column($rows, 'ip_address'));
		$this->assertSame([1, 1], array_map('intval', array_column($rows, 'count_rec')));
	}

	/**
	 * @return void
	 */
	public function testOldStagingRowsCannotBecomeCurrentSightings(): void {
		$this->device(1, 1);
		$this->previous(1, 1);
		$this->observed(1, 1, 'AABBCCDDEEFF', self::PREVIOUS);
		$this->applyScan();
		$this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM mac_track_aggregated_ports')->fetchColumn());
		$this->assertSame(self::PREVIOUS, $this->db->query('SELECT date_last FROM mac_track_aggregated_ports')->fetchColumn());
	}
}
