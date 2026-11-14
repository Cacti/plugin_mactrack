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
 * Partition names and bounds are built in UTC from epoch seconds, so the
 * session time zone and DST cannot shift or duplicate them.
 */

beforeAll(function () {
	mactrack_test_load(dirname(__DIR__, 2) . '/lib/mactrack_partitions.php');
});

// 2026-11-12 06:15:07 UTC
const MT_PN_TS = 1794464107;

it('gives a scan its own one-second period', function () {
	expect(mactrack_partition_period('scan', MT_PN_TS))->toBe(['start' => 1794464107, 'bound' => 1794464108]);
	expect(mactrack_partition_name('scan', MT_PN_TS))->toBe('s20261112061507');
});

it('puts a scan in the UTC hour that contains it', function () {
	expect(mactrack_partition_period('hour', MT_PN_TS))->toBe(['start' => 1794463200, 'bound' => 1794466800]);
	expect(mactrack_partition_name('hour', MT_PN_TS))->toBe('h2026111206');
});

it('puts a scan in the UTC day that contains it', function () {
	expect(mactrack_partition_period('day', MT_PN_TS))->toBe(['start' => 1794441600, 'bound' => 1794528000]);
	expect(mactrack_partition_name('day', MT_PN_TS))->toBe('d20261112');
});

it('starts a period at a scan that falls exactly on its boundary', function () {
	expect(mactrack_partition_period('hour', 1794463200))->toBe(['start' => 1794463200, 'bound' => 1794466800]);
	expect(mactrack_partition_period('day', 1794441600))->toBe(['start' => 1794441600, 'bound' => 1794528000]);
	expect(mactrack_partition_name('hour', 1794463200))->toBe('h2026111206');
});

it('ignores the PHP time zone', function () {
	$tz = date_default_timezone_get();
	date_default_timezone_set('America/Los_Angeles');

	try {
		expect(mactrack_partition_name('day', MT_PN_TS))->toBe('d20261112');
		expect(mactrack_partition_period('day', MT_PN_TS)['start'])->toBe(1794441600);
	} finally {
		date_default_timezone_set($tz);
	}
});

it('refuses an unknown granularity or a timestamp below one', function (string $granularity, int $ts) {
	expect(fn () => mactrack_partition_period($granularity, $ts))->toThrow(InvalidArgumentException::class);
	expect(fn () => mactrack_partition_name($granularity, $ts))->toThrow(InvalidArgumentException::class);
})->with([
	'week'     => ['week', MT_PN_TS],
	'empty'    => ['', MT_PN_TS],
	'zero ts'  => ['scan', 0],
	'negative' => ['day', -5],
]);

it('accepts the names it builds and dMaxValue', function (string $name) {
	expect(mactrack_partition_name_valid($name))->toBeTrue();
})->with(['s20261112061507', 'h2026111206', 'd20261112', 'dMaxValue']);

it('rejects names that could not have come from a timestamp', function (string $name) {
	expect(mactrack_partition_name_valid($name))->toBeFalse();
})->with([
	'empty'          => [''],
	'upper prefix'   => ['S2026'],
	'injection'      => ['s2026;DROP'],
	'underscore'     => ['p_1'],
	'wrong case max' => ['dmaxvalue'],
	'too long'       => ['s' . str_repeat('1', 64)],
	'prefix only'    => ['s'],
	'newline'        => ["s2026\n"],
]);
