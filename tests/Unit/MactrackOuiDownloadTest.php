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
 * mactrack_download_oui_database() must tolerate transient IEEE download
 * failures (for example a refused connection) without letting PHP's file()
 * warning escape to Cacti's error handler, which would log an "ERROR PHP
 * WARNING" and fail CI. These tests drive the helper deterministically with
 * local file paths (success) and unreachable paths (failure) so no network
 * access is required, and they confirm retries, fallback ordering, warning
 * capture, and that import_oui_database() routes through the helper.
 */
final class MactrackOuiDownloadTest extends TestCase {
	/** @var array<int, string> */
	private $tempFiles = [];

	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('lib/mactrack_functions.php');
	}

	/**
	 * @return void
	 */
	protected function tearDown(): void {
		foreach ($this->tempFiles as $file) {
			if (is_file($file)) {
				unlink($file);
			}
		}

		$this->tempFiles = [];

		parent::tearDown();
	}

	/**
	 * Create a readable temp file whose contents file() can return.
	 *
	 * @param string $contents
	 *
	 * @return string Absolute path to the temp file.
	 */
	private function writeTempFile($contents) {
		$path = tempnam(sys_get_temp_dir(), 'mt_oui_');

		file_put_contents($path, $contents);

		$this->tempFiles[] = $path;

		return $path;
	}

	/**
	 * An absolute path that cannot be opened, so file() fails with E_WARNING.
	 *
	 * @return string
	 */
	private function unreachablePath() {
		return sys_get_temp_dir() . '/mt_oui_missing_' . uniqid('', true) . '/oui.txt';
	}

	/**
	 * Messages passed to the cacti_log() stub during the current test.
	 *
	 * @return array<int, string>
	 */
	private function loggedMessages() {
		$messages = [];

		foreach ($GLOBALS['__test_db_calls'] as $call) {
			if ($call['fn'] === 'cacti_log') {
				$messages[] = $call['sql'];
			}
		}

		return $messages;
	}

	/**
	 * @return void
	 */
	public function testReturnsFileContentsOnFirstSuccessfulUrl(): void {
		$path = $this->writeTempFile("line one\nline two\n");

		$result = mactrack_download_oui_database([$path]);

		$this->assertSame(file($path), $result);
		$this->assertSame([], $this->loggedMessages(), 'a successful download should not log anything');
	}

	/**
	 * @return void
	 */
	public function testFallsBackToAlternateUrlWhenPrimaryFails(): void {
		$bad  = $this->unreachablePath();
		$good = $this->writeTempFile("aa-bb-cc\n");

		$result = mactrack_download_oui_database([$bad, $good], 1, 0);

		$this->assertSame(file($good), $result);

		$messages = $this->loggedMessages();
		$this->assertCount(1, $messages, 'only the failed primary URL should be logged');
		$this->assertStringContainsString($bad, $messages[0]);
		$this->assertStringContainsString('INFO:', $messages[0]);
	}

	/**
	 * @return void
	 */
	public function testRetriesEachUrlTheRequestedNumberOfTimes(): void {
		$result = mactrack_download_oui_database([$this->unreachablePath()], 3, 0);

		$this->assertFalse($result);
		$this->assertCount(3, $this->loggedMessages());
	}

	/**
	 * @return void
	 */
	public function testReturnsFalseWhenEveryUrlAndAttemptIsExhausted(): void {
		$result = mactrack_download_oui_database([$this->unreachablePath(), $this->unreachablePath()], 2, 0);

		$this->assertFalse($result);
		$this->assertCount(4, $this->loggedMessages(), 'two URLs x two attempts should log four times');
	}

	/**
	 * @return void
	 */
	public function testFiltersOutEmptyUrls(): void {
		$good = $this->writeTempFile("vendor\n");

		$result = mactrack_download_oui_database(['', $good], 1, 0);

		$this->assertSame(file($good), $result);
		$this->assertSame([], $this->loggedMessages(), 'empty URLs must not count as a failed attempt');
	}

	/**
	 * @return void
	 */
	public function testCoercesRetriesBelowOneToASingleAttempt(): void {
		$result = mactrack_download_oui_database([$this->unreachablePath()], 0, 0);

		$this->assertFalse($result);
		$this->assertCount(1, $this->loggedMessages(), 'retries below one must still make a single attempt');
	}

	/**
	 * @return void
	 */
	public function testCapturesTheConnectionWarningLocally(): void {
		$escaped = false;

		set_error_handler(static function() use (&$escaped): bool {
			$escaped = true;

			return true;
		}, E_WARNING);

		try {
			$result = mactrack_download_oui_database([$this->unreachablePath()], 1, 0);
		} finally {
			restore_error_handler();
		}

		$this->assertFalse($result);
		$this->assertFalse($escaped, 'file() warning must be captured locally, not propagated');

		$messages = $this->loggedMessages();
		$this->assertCount(1, $messages);
		$this->assertStringContainsStringIgnoringCase('failed to open stream', $messages[0]);
	}

	/**
	 * @return void
	 */
	public function testWaitsBetweenAttemptsWhenDelayIsConfigured(): void {
		$start = microtime(true);

		$result = mactrack_download_oui_database([$this->unreachablePath()], 2, 1);

		$elapsed = microtime(true) - $start;

		$this->assertFalse($result);
		$this->assertCount(2, $this->loggedMessages());
		$this->assertGreaterThanOrEqual(1.0, $elapsed, 'a positive delay should pause between attempts');
	}

	/**
	 * import_oui_database() must route its download through the helper and
	 * still import the returned records. Driving it with a local fixture keeps
	 * the call site covered without reaching out to IEEE.
	 *
	 * @return void
	 */
	public function testImportOuiDatabaseRoutesThroughDownloader(): void {
		$fixture = $this->writeTempFile(
			"AA-BB-CC   (hex)\t\tExample Vendor Inc.\n" .
			"AABBCC     (base 16)\t\tExample Vendor Inc.\n" .
			"\t\t\t\t123 Example Street\n" .
			"\t\t\t\tCity State 12345\n" .
			"\n"
		);

		ob_start();
		import_oui_database('ui', $fixture);
		$output = ob_get_clean();

		$this->assertStringContainsString('OUI Database Download from IEEE Complete', $output);

		$replaced = false;

		foreach ($GLOBALS['__test_db_calls'] as $call) {
			if ($call['fn'] === 'db_execute' && strpos($call['sql'], 'REPLACE INTO mac_track_oui_database') !== false) {
				$replaced = true;

				break;
			}
		}

		$this->assertTrue($replaced, 'the imported vendor block should be written with a REPLACE');
	}

	/**
	 * A two-record OUI fixture in the IEEE oui.txt layout.
	 *
	 * @return string Absolute path to the fixture.
	 */
	private function ouiFixture() {
		return $this->writeTempFile(
			"AA-BB-CC   (hex)\t\tExample Vendor Inc.\n" .
			"AABBCC     (base 16)\t\tExample Vendor Inc.\n" .
			"\t\t\t\t123 Example Street\n" .
			"\n"
		);
	}

	/**
	 * A full-size OUI fixture with $count records.
	 *
	 * @param int $count
	 *
	 * @return string Absolute path to the fixture.
	 */
	private function ouiFixtureOf($count) {
		$contents = '';

		for ($n = 0; $n < $count; $n++) {
			$hex       = strtoupper(sprintf('%06x', $n));
			$contents .= substr($hex, 0, 2) . '-' . substr($hex, 2, 2) . '-' . substr($hex, 4, 2) . "   (hex)\t\tVendor $n\n" .
				"$hex     (base 16)\t\tVendor $n\n" .
				"\t\t\t\tStreet $n\n" .
				"\n";
		}

		return $this->writeTempFile($contents);
	}

	/**
	 * Number of REPLACE batches written during the current test.
	 *
	 * @return int
	 */
	private function replaceBatches() {
		return count(array_filter($this->executedSql(), static function ($sql): bool {
			return strpos($sql, 'REPLACE INTO mac_track_oui_database') !== false;
		}));
	}

	/**
	 * SQL passed to db_execute() during the current test.
	 *
	 * @return array<int, string>
	 */
	private function executedSql() {
		$sql = [];

		foreach ($GLOBALS['__test_db_calls'] as $call) {
			if ($call['fn'] === 'db_execute') {
				$sql[] = $call['sql'];
			}
		}

		return $sql;
	}

	/**
	 * @return void
	 */
	public function testReportsWhichUrlReturnedTheData(): void {
		$bad  = $this->unreachablePath();
		$good = $this->writeTempFile("aa-bb-cc\n");

		$source = 'stale';
		mactrack_download_oui_database([$bad, $good], 1, 0, $source);
		$this->assertSame($good, $source);

		$source = 'stale';
		mactrack_download_oui_database([$bad], 1, 0, $source);
		$this->assertNull($source, 'a failed download must not report a source');
	}

	/**
	 * A full list from the primary URL retires vendors that are missing from it.
	 *
	 * @return void
	 */
	public function testFullListRetiresMissingVendors(): void {
		ob_start();
		import_oui_database('ui', $this->ouiFixtureOf(1000), $this->unreachablePath());
		ob_end_clean();

		$this->assertContains('UPDATE mac_track_oui_database SET present=0', $this->executedSql());
	}

	/**
	 * A download that parses to almost nothing (for example an HTML error page
	 * served with a 200 status) must not retire the whole vendor table.
	 *
	 * @return void
	 */
	public function testNearlyEmptyFullListKeepsExistingVendors(): void {
		$page = $this->writeTempFile("<html><body>Access Denied</body></html>\n");

		ob_start();
		import_oui_database('ui', $page, $this->unreachablePath());
		ob_end_clean();

		$this->assertNotContains('UPDATE mac_track_oui_database SET present=0', $this->executedSql());

		$warnings = array_filter($this->loggedMessages(), static function ($message): bool {
			return strpos($message, 'found only 0 OUI records') !== false;
		});

		$this->assertCount(1, $warnings);
	}

	/**
	 * Both the CLI and the web import write in batches of 1000 records.
	 *
	 * @return void
	 */
	public function testImportWritesInBatchesForEveryCaller(): void {
		$fixture = $this->ouiFixtureOf(2500);

		ob_start();
		import_oui_database('web', $fixture, $this->unreachablePath());
		ob_end_clean();

		$this->assertSame(3, $this->replaceBatches(), 'the web import must not send every record in one statement');
	}

	/**
	 * A failed batch must not be followed by deleting the vendors it was
	 * meant to refresh.
	 *
	 * @return void
	 */
	public function testFailedBatchKeepsExistingVendors(): void {
		$fixture = $this->ouiFixtureOf(2000);

		mactrack_test_queue_return('db_execute', true);  // UPDATE ... SET present=0
		mactrack_test_queue_return('db_execute', false); // first batch
		mactrack_test_queue_return('db_execute', true);  // second batch

		ob_start();
		import_oui_database('ui', $fixture, $this->unreachablePath());
		ob_end_clean();

		$sql = $this->executedSql();

		$this->assertSame(2, $this->replaceBatches(), 'the remaining batches should still be written');
		$this->assertNotContains('DELETE FROM mac_track_oui_database WHERE present=0', $sql);
		$this->assertContains('UPDATE mac_track_oui_database SET present=1 WHERE present=0', $sql);

		$warnings = array_filter($this->loggedMessages(), static function ($message): bool {
			return strpos($message, 'no vendors were removed') !== false;
		});

		$this->assertCount(1, $warnings);
	}

	/**
	 * @return void
	 */
	public function testFullImportRemovesAbsentVendors(): void {
		ob_start();
		import_oui_database('ui', $this->ouiFixtureOf(1000), $this->unreachablePath());
		ob_end_clean();

		$this->assertContains('DELETE FROM mac_track_oui_database WHERE present=0', $this->executedSql());
	}

	/**
	 * @return void
	 */
	public function testParsesVendorRecords(): void {
		$records = mactrack_parse_oui_database(file($this->ouiFixture()));

		$this->assertSame([[
			'vendor_mac'     => 'AABBCC',
			'vendor_name'    => 'Example Vendor Inc.',
			'vendor_address' => 'Example Vendor Inc., 123 Example Street',
		]], $records);
	}

	/**
	 * The fallback list is partial, so importing it must keep existing vendors.
	 *
	 * @return void
	 */
	public function testPartialFallbackListKeepsExistingVendors(): void {
		ob_start();
		import_oui_database('ui', $this->unreachablePath(), $this->ouiFixture());
		$output = ob_get_clean();

		$this->assertStringContainsString('OUI Database Download from IEEE Complete', $output);
		$this->assertNotContains('UPDATE mac_track_oui_database SET present=0', $this->executedSql());

		$replaced = false;

		foreach ($this->executedSql() as $sql) {
			if (strpos($sql, 'REPLACE INTO mac_track_oui_database') !== false) {
				$replaced = true;
			}
		}

		$this->assertTrue($replaced, 'the fallback records should still be imported');

		$notes = array_filter($this->loggedMessages(), static function ($message): bool {
			return strpos($message, 'partial fallback list') !== false;
		});

		$this->assertCount(1, $notes);
	}

	/**
	 * @return void
	 */
	public function testDownloadAlwaysHasATimeout(): void {
		$options = mactrack_oui_stream_options('', 'user', 'secret');

		$this->assertSame(30, $options['http']['timeout']);
		$this->assertArrayNotHasKey('proxy', $options['http']);
		$this->assertArrayNotHasKey('header', $options['http'], 'a login without a proxy must not be sent anywhere');
	}

	/**
	 * @return void
	 */
	public function testProxyWithoutLoginSendsNoCredentials(): void {
		$options = mactrack_oui_stream_options('tcp://proxy.example:3128', '', '');

		$this->assertSame('tcp://proxy.example:3128', $options['http']['proxy']);
		$this->assertArrayNotHasKey('header', $options['http']);
	}

	/**
	 * The login must be added to the proxy settings, not replace them, or the
	 * download bypasses the proxy and sends the credentials to IEEE.
	 *
	 * @return void
	 */
	public function testProxyLoginKeepsTheProxy(): void {
		$options = mactrack_oui_stream_options('tcp://proxy.example:3128', 'user', 'secret');

		$this->assertSame('tcp://proxy.example:3128', $options['http']['proxy']);
		$this->assertSame('GET', $options['http']['method']);
		$this->assertSame(30, $options['http']['timeout']);
		$this->assertSame('Proxy-Authorization: Basic ' . base64_encode('user:secret'), $options['http']['header']);
	}
}
