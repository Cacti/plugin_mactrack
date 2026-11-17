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
final class LocallyAdministeredMacDatabaseTest extends TestCase {
	private PDO $db;

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
		$this->db->exec('CREATE TEMPORARY TABLE mac_track_oui_database (
			vendor_mac VARCHAR(8) NOT NULL, vendor_name VARCHAR(100) NOT NULL DEFAULT "", PRIMARY KEY (vendor_mac)
		) ENGINE=InnoDB');
		$this->db->exec('CREATE TEMPORARY TABLE mac_track_ips (mac_address VARCHAR(20) NOT NULL) ENGINE=InnoDB');
		$this->db->exec("INSERT INTO mac_track_oui_database VALUES ('525400', 'Virtual/KVM Host'), ('001122', 'Acme')");
		$this->db->exec("INSERT INTO mac_track_ips VALUES ('525400AABBCC'), ('520000000001'), ('001122334455'), ('AABBCCDDEEFF')");
	}

	/**
	 * @return array<string, string> MAC address => vendor name, with null as an empty string.
	 */
	private function vendors(string $filter = ''): array {
		$vendor = mactrack_vendor_name_sql('mti.mac_address');
		$where  = $filter === '' ? '' : ' WHERE ' . $vendor . ' LIKE ' . $this->db->quote('%' . $filter . '%');
		$rows   = $this->db->query("SELECT mti.mac_address, $vendor AS vendor_name
			FROM mac_track_ips AS mti
			LEFT JOIN mac_track_oui_database AS mtod ON mtod.vendor_mac = SUBSTRING(mti.mac_address, 1, 6)
			$where ORDER BY vendor_name, mti.mac_address")->fetchAll(PDO::FETCH_KEY_PAIR);

		return array_map('strval', $rows);
	}

	/**
	 * @return void
	 */
	public function testNamesEveryFiftyTwoAddressExceptTheRegisteredKvmPrefix(): void {
		$this->assertEquals([
			'AABBCCDDEEFF' => '',
			'520000000001' => 'Locally Admined MAC',
			'001122334455' => 'Acme',
			'525400AABBCC' => 'Virtual/KVM Host',
		], $this->vendors());
	}

	/**
	 * @return void
	 */
	public function testSearchFindsTheLabel(): void {
		$this->assertEquals(['520000000001' => 'Locally Admined MAC'], $this->vendors('Locally Admin'));
		$this->assertEquals(['525400AABBCC' => 'Virtual/KVM Host'], $this->vendors('KVM'));
	}
}
