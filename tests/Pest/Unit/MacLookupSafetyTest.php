<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 */

require_once dirname(__DIR__, 3) . '/lib/mactrack_functions.php';

test('empty MAC addresses cannot become wildcard lookups', function () {
	$GLOBALS['__test_db_calls'] = [];

	expect(db_check_for_ip(''))->toBeFalse();
	expect(db_check_auth(''))->toBeFalse();
	expect(db_check_for_ip(null))->toBeFalse();
	expect(db_check_auth(null))->toBeFalse();
	expect($GLOBALS['__test_db_calls'])->toBeEmpty();
});

test('non-empty MAC lookups use bound parameters', function () {
	$GLOBALS['__test_db_calls'] = [];
	db_check_for_ip('AABBCCDDEEFF');
	db_check_auth('AABBCCDDEEFF');

	expect($GLOBALS['__test_db_calls'])->toHaveCount(2);
	expect($GLOBALS['__test_db_calls'][0]['fn'])->toBe('db_fetch_cell_prepared');
	expect($GLOBALS['__test_db_calls'][0]['params'])->toBe(['AABBCCDDEEFF']);
	expect($GLOBALS['__test_db_calls'][1]['params'])->toBe(['AABBCCDDEEFF']);
});

test('MAC lookup helpers return database matches', function () {
	$GLOBALS['__test_db_calls']              = [];
	$GLOBALS['__test_db_fetch_cell_result'] = '192.0.2.25';

	try {
		expect(db_check_for_ip('AABBCCDDEEFF'))->toBe('192.0.2.25');
		expect(db_check_auth('AABBCCDDEEFF'))->toBe('192.0.2.25');
	} finally {
		$GLOBALS['__test_db_fetch_cell_result'] = '';
	}
});

test('authorization uses exact MAC matching without wildcard expansion', function () {
	$GLOBALS['__test_db_calls'] = [];
	db_check_auth('AABBCC');
	db_check_auth('AA%_BB');

	expect($GLOBALS['__test_db_calls'][0]['sql'])->toContain('mac_address = ?');
	expect($GLOBALS['__test_db_calls'][0]['params'])->toBe(['AABBCC']);
	expect($GLOBALS['__test_db_calls'][1]['params'])->toBe(['AA%_BB']);
});
