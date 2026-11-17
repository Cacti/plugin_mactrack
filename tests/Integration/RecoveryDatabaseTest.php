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
 * Requires CREATE/DROP DATABASE on an isolated MySQL/MariaDB server supplied
 * through MACTRACK_TEST_DSN and MACTRACK_TEST_DB_USER/PASSWORD. Each scenario
 * uses a random database and removes it even when its SQL fails.
 */
function mactrack_recovery_scenario(string $scenario): array {
	if (!getenv('MACTRACK_TEST_DSN')) {
		test()->markTestSkipped('Set MACTRACK_TEST_DSN to run database recovery tests.');
	}

	$command = [PHP_BINARY, dirname(__DIR__) . '/Support/recovery_database.php', $scenario];
	$process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	fclose($pipes[0]);
	$output = stream_get_contents($pipes[1]);
	$error = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	expect(proc_close($process))->toBe(0, $error);

	return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
}

it('restarts a failed aggregate rebuild without counting the source twice', function ($failure, $partial) {
	$result = mactrack_recovery_scenario('aggregate-' . $failure);

	expect($result['first'])->toBeFalse();
	expect($result['partial'])->toBe($partial);
	expect($result['source'])->toBe(30);
	expect($result['retry'])->toBeTrue();
	expect($result['repeated'])->toBeTrue();
	expect($result['source_exists'])->toBeFalse();
	expect($result['rows'])->toHaveCount(1);
	expect((int) $result['rows'][0]['count_rec'])->toBe(30);
	expect($result['rows'][0])->toMatchArray([
		'mac_address' => '001122334455', 'port_name' => 'new',
		'first_scan_date' => '2026-11-12 00:00:00', 'date_last' => '2026-11-15 00:00:00',
	]);
})->with(['second copy fails' => ['copy', 10], 'final drop fails' => ['drop', 30]]);

it('keeps a failed scan available and copies all rows on retry', function ($scenario) {
	$result = mactrack_recovery_scenario($scenario);

	expect($result['first'])->toBeFalse();
	expect($result['temp'])->toBe(1001);
	expect($result['pending'])->toBe('');
	expect($result['retry'])->toBe(1001);
	expect($result['complete'])->toBe(1001);
	if (str_starts_with($scenario, 'exchange-')) {
		expect($result['partial'])->toBe(0);
	}
})->with(['direct-read', 'direct-write', 'exchange-read', 'exchange-write']);

it('keeps date metadata after a failed partition read and reconciles it on retry', function () {
	$result = mactrack_recovery_scenario('dates');

	expect($result['first'])->toBeFalse();
	expect($result['preserved'])->toBe([
		['scan_date' => '2026-10-01 00:00:00'], ['scan_date' => '2026-11-16 12:00:00'],
	]);
	expect($result['retry'])->toBe(1);
	expect($result['dates'])->toBe([['scan_date' => '2026-11-16 12:00:00']]);
});
