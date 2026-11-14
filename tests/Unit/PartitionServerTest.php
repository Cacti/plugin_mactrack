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

/*
 * The partition cap and WITHOUT VALIDATION depend on the server: MariaDB
 * keeps split, exchange and drop flat up to 2,048 partitions, MySQL slows
 * by about 2 ms per partition per statement, and MariaDB before 11.4
 * rejects WITHOUT VALIDATION.
 */

beforeAll(function () {
	mactrack_test_load(dirname(__DIR__, 2) . '/lib/mactrack_partitions.php');
});

it('parses the vendor and version from VERSION()', function (string $raw, bool $mariadb, string $version) {
	expect(mactrack_partition_server($raw))->toBe(['mariadb' => $mariadb, 'version' => $version]);
})->with([
	['10.5.29-MariaDB-ubu2004-log', true, '10.5.29'],
	['11.4.13-MariaDB-log', true, '11.4.13'],
	['10.11.19-MariaDB-ubu2204-log', true, '10.11.19'],
	['8.0.46', false, '8.0.46'],
	['8.4.13', false, '8.4.13'],
	['5.5.5-10.6.28-MariaDB', true, '10.6.28'],
]);

it('refuses a version string it cannot read', function () {
	expect(fn () => mactrack_partition_server('unknown'))->toThrow(InvalidArgumentException::class);
});

it('caps partitions at 1024 on MariaDB and 512 on MySQL', function () {
	expect(mactrack_partition_cap(mactrack_partition_server('10.5.29-MariaDB')))->toBe(1024);
	expect(mactrack_partition_cap(mactrack_partition_server('8.4.13')))->toBe(512);
});

it('uses WITHOUT VALIDATION only where the server accepts it', function (string $raw, bool $supported) {
	expect(mactrack_partition_without_validation(mactrack_partition_server($raw)))->toBe($supported);
})->with([
	['10.5.29-MariaDB-ubu2004-log', false],
	['10.6.28-MariaDB-ubu2204-log', false],
	['10.11.19-MariaDB-ubu2204-log', false],
	['11.4.13-MariaDB-ubu2404-log', true],
	['11.8.9-MariaDB-ubu2404-log', true],
	['8.0.46', true],
	['8.4.13', true],
	['5.7.4', false],
	['5.7.5', true],
]);

it('chooses the finest granularity that fits under the cap', function (string $wanted, int $interval, int $days, int $cap, array $expected) {
	expect(mactrack_partition_choose($wanted, $interval, $days, $cap))->toBe($expected);
})->with([
	// periods per day x (retention + 1) + 2: a day builds up between the
	// daily drops, plus dMaxValue and one partition made ahead.
	'15-min scans, 7 days'    => ['scan', 900, 7, 1024, ['granularity' => 'scan', 'projected' => 780, 'fell_back' => false]],
	'15-min scans, 10 days'   => ['scan', 900, 10, 1024, ['granularity' => 'hour', 'projected' => 337, 'fell_back' => true]],
	'5-min scans, 7 days'     => ['scan', 300, 7, 1024, ['granularity' => 'hour', 'projected' => 265, 'fell_back' => true]],
	'5-min scans, 60 days'    => ['scan', 300, 60, 1024, ['granularity' => 'day', 'projected' => 65, 'fell_back' => true]],
	'hourly, 400 days, MySQL' => ['hour', 900, 400, 512, ['granularity' => 'day', 'projected' => 405, 'fell_back' => true]],
	'daily asked, fits'       => ['day', 900, 30, 1024, ['granularity' => 'day', 'projected' => 35, 'fell_back' => false]],
	'nothing fits'            => ['day', 900, 2000, 1024, ['granularity' => 'day', 'projected' => 2005, 'fell_back' => true]],
	'exactly at the cap'      => ['scan', 900, 10, 1071, ['granularity' => 'scan', 'projected' => 1071, 'fell_back' => false]],
]);

it('refuses an unknown granularity or an interval below a minute', function (string $wanted, int $interval, int $days) {
	expect(fn () => mactrack_partition_choose($wanted, $interval, $days, 1024))->toThrow(InvalidArgumentException::class);
})->with([
	['week', 900, 7],
	['scan', 59, 7],
	['scan', 900, 0],
]);
