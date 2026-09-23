<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for the small schema-introspection and guarded-DDL helper
 * functions in setup.php: mactrack_db_table_exists(),
 * mactrack_db_column_exists(), mactrack_db_key_exists(),
 * mactrack_execute_sql(), mactrack_create_table(), mactrack_add_column(),
 * mactrack_add_index(), mactrack_modify_column(), and
 * mactrack_delete_column().
 *
 * db_fetch_assoc() itself logs every call into $GLOBALS['__test_db_calls'],
 * so assertions here filter for db_execute specifically rather than
 * counting the whole call log. mactrack_db_table_exists() returns
 * cacti_sizeof()'s int result, not a strict bool, so truthy/falsy
 * assertions are used for it specifically.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

beforeEach(function () {
	$GLOBALS['__test_db_calls']        = array();
	$GLOBALS['__test_next_returns']    = array();
	$GLOBALS['__test_matched_returns'] = array();
});

function mactrack_test_executed_sql() {
	return array_column(array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute';
	}), 'sql');
}

it('reports a table as existing only when SHOW TABLES returns a row', function () {
	mactrack_test_queue_return('db_fetch_assoc', array(array('Tables_in_cacti' => 'mac_track_sites')));
	expect(mactrack_db_table_exists('mac_track_sites'))->toBeTruthy();

	mactrack_test_queue_return('db_fetch_assoc', array());
	expect(mactrack_db_table_exists('mac_track_sites'))->toBeFalsy();
});

it('reports a column as existing only when the table exists and the column is listed', function () {
	mactrack_test_queue_return('db_fetch_assoc', array()); // table missing
	expect(mactrack_db_column_exists('mac_track_sites', 'SiteName'))->toBeFalse();

	mactrack_test_queue_return('db_fetch_assoc', array(array('Tables_in_cacti' => 'mac_track_sites'))); // table exists
	mactrack_test_queue_return('db_fetch_assoc', array(array('Field' => 'SiteName'), array('Field' => 'SiteID')));
	expect(mactrack_db_column_exists('mac_track_sites', 'SiteName'))->toBeTrue();

	mactrack_test_queue_return('db_fetch_assoc', array(array('Tables_in_cacti' => 'mac_track_sites')));
	mactrack_test_queue_return('db_fetch_assoc', array(array('Field' => 'SiteID')));
	expect(mactrack_db_column_exists('mac_track_sites', 'SiteName'))->toBeFalse();
});

it('reports a key as existing only when the table exists and the index is listed', function () {
	mactrack_test_queue_return('db_fetch_assoc', array(array('Tables_in_cacti' => 'mac_track_sites')));
	mactrack_test_queue_return('db_fetch_assoc', array(array('Key_name' => 'PRIMARY')));
	expect(mactrack_db_key_exists('mac_track_sites', 'PRIMARY'))->toBeTrue();

	mactrack_test_queue_return('db_fetch_assoc', array(array('Tables_in_cacti' => 'mac_track_sites')));
	mactrack_test_queue_return('db_fetch_assoc', array(array('Key_name' => 'PRIMARY')));
	expect(mactrack_db_key_exists('mac_track_sites', 'other_index'))->toBeFalse();
});

it('executes the given SQL unconditionally', function () {
	mactrack_execute_sql('creating something', 'CREATE TABLE foo (id INT)');

	expect(mactrack_test_executed_sql())->toBe(array('CREATE TABLE foo (id INT)'));
});

it('creates a table only when it does not already exist', function () {
	mactrack_test_queue_return('db_fetch_assoc', array());
	mactrack_create_table('foo', 'CREATE TABLE foo (id INT)');
	expect(mactrack_test_executed_sql())->toBe(array('CREATE TABLE foo (id INT)'));

	$GLOBALS['__test_db_calls'] = array();
	mactrack_test_queue_return('db_fetch_assoc', array(array('Tables_in_cacti' => 'foo')));
	mactrack_create_table('foo', 'CREATE TABLE foo (id INT)');
	expect(mactrack_test_executed_sql())->toBeEmpty();
});

it('adds a column only when it does not already exist', function () {
	mactrack_test_queue_return('db_fetch_assoc', array()); // table missing -> column missing
	mactrack_add_column('foo', 'bar', 'ALTER TABLE foo ADD COLUMN bar INT');
	expect(mactrack_test_executed_sql())->toBe(array('ALTER TABLE foo ADD COLUMN bar INT'));

	$GLOBALS['__test_db_calls'] = array();
	mactrack_test_queue_return('db_fetch_assoc', array(array('Tables_in_cacti' => 'foo')));
	mactrack_test_queue_return('db_fetch_assoc', array(array('Field' => 'bar')));
	mactrack_add_column('foo', 'bar', 'ALTER TABLE foo ADD COLUMN bar INT');
	expect(mactrack_test_executed_sql())->toBeEmpty();
});

it('adds an index only when it does not already exist', function () {
	mactrack_test_queue_return('db_fetch_assoc', array());
	mactrack_add_index('foo', 'idx_bar', 'ALTER TABLE foo ADD INDEX idx_bar (bar)');
	expect(mactrack_test_executed_sql())->toBe(array('ALTER TABLE foo ADD INDEX idx_bar (bar)'));

	$GLOBALS['__test_db_calls'] = array();
	mactrack_test_queue_return('db_fetch_assoc', array(array('Tables_in_cacti' => 'foo')));
	mactrack_test_queue_return('db_fetch_assoc', array(array('Key_name' => 'idx_bar')));
	mactrack_add_index('foo', 'idx_bar', 'ALTER TABLE foo ADD INDEX idx_bar (bar)');
	expect(mactrack_test_executed_sql())->toBeEmpty();
});

it('modifies a column only when it already exists', function () {
	mactrack_test_queue_return('db_fetch_assoc', array());
	mactrack_modify_column('foo', 'bar', 'ALTER TABLE foo MODIFY COLUMN bar VARCHAR(10)');
	expect(mactrack_test_executed_sql())->toBeEmpty();

	$GLOBALS['__test_db_calls'] = array();
	mactrack_test_queue_return('db_fetch_assoc', array(array('Tables_in_cacti' => 'foo')));
	mactrack_test_queue_return('db_fetch_assoc', array(array('Field' => 'bar')));
	mactrack_modify_column('foo', 'bar', 'ALTER TABLE foo MODIFY COLUMN bar VARCHAR(10)');
	expect(mactrack_test_executed_sql())->toBe(array('ALTER TABLE foo MODIFY COLUMN bar VARCHAR(10)'));
});

it('deletes a column only when it already exists', function () {
	mactrack_test_queue_return('db_fetch_assoc', array());
	mactrack_delete_column('foo', 'bar', 'ALTER TABLE foo DROP COLUMN bar');
	expect(mactrack_test_executed_sql())->toBeEmpty();

	$GLOBALS['__test_db_calls'] = array();
	mactrack_test_queue_return('db_fetch_assoc', array(array('Tables_in_cacti' => 'foo')));
	mactrack_test_queue_return('db_fetch_assoc', array(array('Field' => 'bar')));
	mactrack_delete_column('foo', 'bar', 'ALTER TABLE foo DROP COLUMN bar');
	expect(mactrack_test_executed_sql())->toBe(array('ALTER TABLE foo DROP COLUMN bar'));
});
