<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 2) . '/lib/mactrack_functions.php';

/*
 * The first maintenance run after retention starts working can face tens of
 * millions of rows; they go in batches, not one transaction.
 */
beforeEach(function () {
	$GLOBALS['__test_db_calls']     = [];
	$GLOBALS['__test_next_returns'] = [];
});

it('repeats full batches and stops after a short one', function () {
	mactrack_test_queue_return('db_affected_rows', 3);
	mactrack_test_queue_return('db_affected_rows', 3);
	mactrack_test_queue_return('db_affected_rows', 1);

	$deleted = mactrack_delete_batched('DELETE FROM t WHERE d < ? ORDER BY d', ['2026-11-01'], 3);

	expect($deleted)->toBe(7);
	expect(array_column($GLOBALS['__test_db_calls'], 'sql'))->toBe(array_fill(0, 3, 'DELETE FROM t WHERE d < ? ORDER BY d LIMIT 3'));
	expect(array_column($GLOBALS['__test_db_calls'], 'params'))->toBe(array_fill(0, 3, ['2026-11-01']));
});

it('runs once when nothing is left to delete', function () {
	expect(mactrack_delete_batched('DELETE FROM t WHERE d < ? ORDER BY d', ['2026-11-01']))->toBe(0);
	expect($GLOBALS['__test_db_calls'])->toHaveCount(1);
	expect($GLOBALS['__test_db_calls'][0]['sql'])->toEndWith('LIMIT 10000');
});

it('stops when a batch fails', function () {
	mactrack_test_queue_return('db_execute_prepared', false);
	mactrack_test_queue_return('db_affected_rows', 10000);

	expect(mactrack_delete_batched('DELETE FROM t WHERE d < ? ORDER BY d', ['2026-11-01']))->toBe(0);
	expect($GLOBALS['__test_db_calls'])->toHaveCount(1);
});
