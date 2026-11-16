<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 2) . '/lib/mactrack_functions.php';

beforeEach(function () {
	$GLOBALS['__test_db_calls'] = [];
	$GLOBALS['__test_next_returns'] = [];
	$GLOBALS['__test_matched_returns'] = [];
});

it('accepts a scanner only when its PID owns the device row', function ($owner, $expected) {
	mactrack_test_queue_return('db_fetch_cell_prepared', $owner === 'self' ? getmypid() : $owner);
	expect(db_process_add(7, true))->toBe($expected);
	$calls = $GLOBALS['__test_db_calls'];
	expect($calls[0]['params'])->toBe([7, getmypid()]);
	expect($calls[0]['sql'])->toContain('IF(process_id = 0, VALUES(process_id), process_id)');
})->with([
	['self', true],
	[0, false],
	[-1, false],
	[false, false],
]);

it('does not claim a scanner when the row write fails', function () {
	mactrack_test_queue_return('db_execute_prepared', false);
	expect(db_process_add(7, true))->toBeFalse();
	expect($GLOBALS['__test_db_calls'])->toHaveCount(1);
});

it('queues a scanner without replacing an existing PID', function () {
	expect(db_process_add(7))->toBeTrue();
	expect($GLOBALS['__test_db_calls'][0]['params'])->toBe([7, 0]);
	expect($GLOBALS['__test_db_calls'][0]['sql'])->not->toContain('REPLACE');
});

it('keeps the poller and resolver marker contracts', function ($device, $storepid) {
	expect(db_process_add($device, $storepid))->toBeTrue();
	expect($GLOBALS['__test_db_calls'][0]['sql'])->toContain('REPLACE INTO');
	expect($GLOBALS['__test_db_calls'][0]['params'])->toBe([$device, $storepid ? getmypid() : 0]);
})->with([[-1, false], [0, true]]);

it('removes only the current scanner or resolver row', function ($device) {
	db_process_remove($device);
	expect($GLOBALS['__test_db_calls'][0]['params'])->toBe([$device, getmypid()]);
	expect($GLOBALS['__test_db_calls'][0]['sql'])->toContain('AND process_id = ?');
})->with([0, 7]);

it('removes the poller marker whose PID is intentionally zero', function () {
	db_process_remove(-1);
	expect($GLOBALS['__test_db_calls'][0]['params'])->toBe([-1]);
});
