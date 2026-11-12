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
 * Every partition statement is built from names that pass
 * mactrack_partition_name_valid() and positive integer bounds; anything
 * else is refused before it can reach SQL.
 */

beforeAll(function () {
	mactrack_test_load(dirname(__DIR__, 2) . '/lib/mactrack_partitions.php');
});

it('builds the PARTITION BY clause for a new table', function () {
	expect(mactrack_partition_create_sql(['s20261112061507' => 1794464108, 's20261112063007' => 1794465008]))->toBe(
		'PARTITION BY RANGE (UNIX_TIMESTAMP(scan_date)) ('
		. 'PARTITION `s20261112061507` VALUES LESS THAN (1794464108), '
		. 'PARTITION `s20261112063007` VALUES LESS THAN (1794465008), '
		. 'PARTITION `dMaxValue` VALUES LESS THAN MAXVALUE)'
	);
	expect(mactrack_partition_create_sql([]))->toBe('PARTITION BY RANGE (UNIX_TIMESTAMP(scan_date)) (PARTITION `dMaxValue` VALUES LESS THAN MAXVALUE)');
});

it('splits a new partition off dMaxValue', function () {
	expect(mactrack_partition_split_sql('s20261112061507', 1794464108))->toBe(
		'ALTER TABLE `mac_track_ports` REORGANIZE PARTITION `dMaxValue` INTO ('
		. 'PARTITION `s20261112061507` VALUES LESS THAN (1794464108), '
		. 'PARTITION `dMaxValue` VALUES LESS THAN MAXVALUE)'
	);
});

it('exchanges a partition with the stage table, with or without validation', function () {
	expect(mactrack_partition_exchange_sql('s20261112061507', false))->toBe(
		'ALTER TABLE `mac_track_ports` EXCHANGE PARTITION `s20261112061507` WITH TABLE `mac_track_ports_stage`'
	);
	expect(mactrack_partition_exchange_sql('s20261112061507', true))->toBe(
		'ALTER TABLE `mac_track_ports` EXCHANGE PARTITION `s20261112061507` WITH TABLE `mac_track_ports_stage` WITHOUT VALIDATION'
	);
});

it('drops partitions in one statement', function () {
	expect(mactrack_partition_drop_sql(['s20261101000000', 's20261101001500']))->toBe(
		'ALTER TABLE `mac_track_ports` DROP PARTITION `s20261101000000`, `s20261101001500`'
	);
});

it('refuses names that are not valid partition names', function (Closure $build) {
	expect($build)->toThrow(InvalidArgumentException::class);
})->with([
	'create'   => [fn () => mactrack_partition_create_sql(['s1;DROP' => 5])],
	'split'    => [fn () => mactrack_partition_split_sql('s1`x', 5)],
	'exchange' => [fn () => mactrack_partition_exchange_sql('', false)],
	'drop'     => [fn () => mactrack_partition_drop_sql(['p_1'])],
]);

it('refuses to name dMaxValue as a bounded partition', function (Closure $build) {
	expect($build)->toThrow(InvalidArgumentException::class);
})->with([
	'create'   => [fn () => mactrack_partition_create_sql(['dMaxValue' => 5])],
	'split'    => [fn () => mactrack_partition_split_sql('dMaxValue', 5)],
	'exchange' => [fn () => mactrack_partition_exchange_sql('dMaxValue', false)],
]);

it('refuses bounds below one or out of order', function (Closure $build) {
	expect($build)->toThrow(InvalidArgumentException::class);
})->with([
	'zero bound'      => [fn () => mactrack_partition_split_sql('s1', 0)],
	'negative create' => [fn () => mactrack_partition_create_sql(['s1' => -1])],
	'not increasing'  => [fn () => mactrack_partition_create_sql(['s2' => 20, 's1' => 10])],
	'equal'           => [fn () => mactrack_partition_create_sql(['s1' => 10, 's2' => 10])],
]);

it('never drops dMaxValue and refuses an empty drop', function () {
	expect(fn () => mactrack_partition_drop_sql(['s1', 'dMaxValue']))->toThrow(InvalidArgumentException::class);
	expect(fn () => mactrack_partition_drop_sql([]))->toThrow(InvalidArgumentException::class);
});
