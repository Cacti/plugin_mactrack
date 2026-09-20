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
 * Test bootstrap.
 *
 * MacTrack's sources expect to be included by Cacti, which has already
 * defined the db_*, request-variable, and logging helpers as plain global
 * functions. Nothing here talks to a database or a network: each Cacti
 * function is declared as a stub that records the call in
 * $GLOBALS['__test_db_calls'] and hands back a safe default (or a
 * per-test-programmed value; see mactrack_test_next_return()/
 * mactrack_test_queue_return() below).
 *
 * The CI workflow checks out a pinned Cacti release next to this plugin so
 * Pest runs against Cacti's own Composer-managed vendor tree (Pest/PHPUnit)
 * instead of a vendor tree local to this plugin. The version check below
 * makes sure that checkout actually matches what tests/.cacti-version
 * expects before any plugin source is loaded.
 *
 * Guarding every declaration with function_exists() keeps this file usable
 * if a future integration suite loads real Cacti first.
 */

$cacti_root = dirname(__DIR__, 3);
$autoload   = $cacti_root . '/include/vendor/autoload.php';
$version    = $cacti_root . '/include/cacti_version';
$expected   = __DIR__ . '/.cacti-version';

if (!is_readable($autoload)) {
	throw new RuntimeException("Cacti Composer autoloader is not readable: $autoload");
}

if (!is_readable($version)) {
	throw new RuntimeException("Cacti version file is not readable: $version");
}

if (!is_readable($expected)) {
	throw new RuntimeException("Expected Cacti version file is not readable: $expected");
}

$cacti_version    = trim((string) file_get_contents($version));
$expected_version = trim((string) file_get_contents($expected));

if ($cacti_version === '') {
	throw new RuntimeException("Cacti version file is empty: $version");
}

if ($expected_version === '') {
	throw new RuntimeException("Expected Cacti version file is empty: $expected");
}

// The CI workflow tracks a moving branch (1.2.x or develop) rather than a pinned release, so any actual version is accepted.
if (!in_array($expected_version, array('1.2.x', 'develop'), true) && $cacti_version !== $expected_version) {
	throw new RuntimeException("Expected Cacti $expected_version, found $cacti_version in $version");
}

require_once $autoload;
require_once __DIR__ . '/TestCase.php';

/*
 * base_path has to point at the Cacti root two levels above this plugin:
 * mactrack's source files build include paths from it at runtime.
 */
$GLOBALS['config'] = array(
	'base_path'       => $cacti_root,
	'url_path'        => '/cacti/',
	'cacti_version'   => $cacti_version,
	'cacti_server_os' => 'unix',
);

$GLOBALS['__test_db_calls']       = array();
$GLOBALS['__test_config_options'] = array();
$GLOBALS['__test_next_returns']   = array();
$GLOBALS['__test_matched_returns'] = array();

// mactrack_seed_default_site() reads this to scope its advisory lock name.
$GLOBALS['database_default'] = 'cacti';

/**
 * Queue the next value a stub named $fn will return (FIFO per name).
 * Falls back to the stub's own built-in default once the queue is empty.
 *
 * @param string $fn    Cacti function name.
 * @param mixed  $value Value to hand back on the next call.
 *
 * @return void
 */
function mactrack_test_queue_return($fn, $value) {
	$GLOBALS['__test_next_returns'][$fn][] = $value;
}

/**
 * Answer any call to $fn whose SQL contains $fragment with $value.
 *
 * A stub such as db_fetch_cell_prepared() is called with many different
 * queries in one test (e.g. a site-count check, GET_LOCK, RELEASE_LOCK all
 * go through it), so a positional FIFO queue breaks as soon as the code
 * under test reorders a lookup. Matching on the query keeps the fixture
 * readable and order-independent. Checked before the FIFO queue.
 *
 * @param string $fn       Cacti function name.
 * @param string $fragment Distinctive substring of the SQL.
 * @param mixed  $value    Value to hand back.
 *
 * @return void
 */
function mactrack_test_queue_return_for($fn, $fragment, $value) {
	$GLOBALS['__test_matched_returns'][$fn][] = array($fragment, $value);
}

/**
 * Take the queued return value for $fn, or $default if nothing is queued.
 *
 * @param string $fn      Cacti function name.
 * @param mixed  $default Fallback when nothing is queued.
 * @param string $sql     SQL the caller passed, for matching.
 *
 * @return mixed
 */
function mactrack_test_next_return($fn, $default, $sql = '') {
	if ($sql !== '' && !empty($GLOBALS['__test_matched_returns'][$fn])) {
		$flat = preg_replace('/\s+/', ' ', $sql);

		foreach ($GLOBALS['__test_matched_returns'][$fn] as $entry) {
			if (strpos($flat, preg_replace('/\s+/', ' ', $entry[0])) !== false) {
				return $entry[1];
			}
		}
	}

	if (!empty($GLOBALS['__test_next_returns'][$fn])) {
		return array_shift($GLOBALS['__test_next_returns'][$fn]);
	}

	return $default;
}

if (!function_exists('db_execute')) {
	function db_execute($sql) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'db_execute', 'sql' => $sql, 'params' => array());

		return mactrack_test_next_return('db_execute', true);
	}
}

if (!function_exists('db_execute_prepared')) {
	function db_execute_prepared($sql, $params = array()) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'db_execute_prepared', 'sql' => $sql, 'params' => $params);

		return mactrack_test_next_return('db_execute_prepared', true);
	}
}

if (!function_exists('db_fetch_assoc')) {
	function db_fetch_assoc($sql) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'db_fetch_assoc', 'sql' => $sql, 'params' => array());

		return mactrack_test_next_return('db_fetch_assoc', array());
	}
}

if (!function_exists('db_fetch_assoc_prepared')) {
	function db_fetch_assoc_prepared($sql, $params = array()) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'db_fetch_assoc_prepared', 'sql' => $sql, 'params' => $params);

		return mactrack_test_next_return('db_fetch_assoc_prepared', array());
	}
}

if (!function_exists('db_fetch_row')) {
	function db_fetch_row($sql) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'db_fetch_row', 'sql' => $sql, 'params' => array());

		return mactrack_test_next_return('db_fetch_row', array());
	}
}

if (!function_exists('db_fetch_row_prepared')) {
	function db_fetch_row_prepared($sql, $params = array()) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'db_fetch_row_prepared', 'sql' => $sql, 'params' => $params);

		return mactrack_test_next_return('db_fetch_row_prepared', array());
	}
}

if (!function_exists('db_fetch_cell')) {
	function db_fetch_cell($sql) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'db_fetch_cell', 'sql' => $sql, 'params' => array());

		return mactrack_test_next_return('db_fetch_cell', '');
	}
}

if (!function_exists('db_fetch_cell_prepared')) {
	function db_fetch_cell_prepared($sql, $params = array()) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'db_fetch_cell_prepared', 'sql' => $sql, 'params' => $params);

		return mactrack_test_next_return('db_fetch_cell_prepared', '', $sql);
	}
}

if (!function_exists('db_index_exists')) {
	function db_index_exists($table, $index) {
		return mactrack_test_next_return('db_index_exists', false);
	}
}

if (!function_exists('db_column_exists')) {
	function db_column_exists($table, $column) {
		return mactrack_test_next_return('db_column_exists', false);
	}
}

if (!function_exists('api_plugin_db_add_column')) {
	function api_plugin_db_add_column($plugin, $table, $data) {
		return true;
	}
}

if (!function_exists('api_plugin_db_table_create')) {
	function api_plugin_db_table_create($plugin, $table, $data) {
		return true;
	}
}

if (!function_exists('api_plugin_register_hook')) {
	function api_plugin_register_hook($plugin, $hook, $function, $file, $inline = '') {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'api_plugin_register_hook', 'sql' => $hook, 'params' => array($function, $file));

		return true;
	}
}

if (!function_exists('api_plugin_register_realm')) {
	function api_plugin_register_realm($plugin, $files, $name, $alone = 0) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'api_plugin_register_realm', 'sql' => $name, 'params' => array($files));

		return true;
	}
}

if (!function_exists('api_plugin_is_enabled')) {
	function api_plugin_is_enabled($plugin) {
		return mactrack_test_next_return('api_plugin_is_enabled', true);
	}
}

if (!function_exists('api_plugin_enable_hooks')) {
	function api_plugin_enable_hooks($plugin) {
	}
}

/*
 * A minimal in-memory config-option store: MacTrack's Default-site retry
 * logic (mactrack_ensure_default_site() and friends) round-trips its state
 * entirely through read_config_option()/set_config_option(), so tests need
 * these to actually persist per-test rather than always returning ''.
 */
if (!function_exists('read_config_option')) {
	function read_config_option($name, $force = false) {
		if (array_key_exists($name, $GLOBALS['__test_config_options'])) {
			return $GLOBALS['__test_config_options'][$name];
		}

		return '';
	}
}

if (!function_exists('set_config_option')) {
	function set_config_option($name, $value) {
		$GLOBALS['__test_config_options'][$name] = $value;
	}
}

if (!function_exists('html_escape')) {
	function html_escape($string) {
		return htmlspecialchars((string) $string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}
}

if (!function_exists('__')) {
	function __($text, $domain = '') {
		return $text;
	}
}

if (!function_exists('__esc')) {
	function __esc($text, $domain = '') {
		return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}
}

if (!function_exists('cacti_log')) {
	function cacti_log($message, $also_print = false, $log_type = '', $level = 0) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'cacti_log', 'sql' => $message, 'params' => array());
	}
}

if (!function_exists('cacti_sizeof')) {
	function cacti_sizeof($array) {
		return is_array($array) ? count($array) : 0;
	}
}

if (!function_exists('is_realm_allowed')) {
	function is_realm_allowed($realm) {
		return true;
	}
}

if (!function_exists('raise_message')) {
	function raise_message($id, $text = '', $level = 0) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'raise_message', 'sql' => $id, 'params' => array($text, $level));
	}
}

if (!function_exists('get_current_page')) {
	function get_current_page() {
		return mactrack_test_next_return('get_current_page', '');
	}
}

if (!function_exists('get_request_var')) {
	function get_request_var($name) {
		return '';
	}
}

if (!function_exists('get_nfilter_request_var')) {
	function get_nfilter_request_var($name) {
		return '';
	}
}

if (!function_exists('get_filter_request_var')) {
	function get_filter_request_var($name) {
		return '';
	}
}

if (!function_exists('form_input_validate')) {
	function form_input_validate($value, $name, $regex, $optional, $error) {
		return $value;
	}
}

if (!function_exists('is_error_message')) {
	function is_error_message() {
		return false;
	}
}

if (!function_exists('sql_save')) {
	function sql_save($array, $table, $key = 'id') {
		return isset($array['id']) ? $array['id'] : 1;
	}
}

if (!defined('CACTI_PATH_BASE')) {
	define('CACTI_PATH_BASE', $GLOBALS['config']['base_path']);
}

if (!defined('POLLER_VERBOSITY_LOW')) {
	define('POLLER_VERBOSITY_LOW', 2);
}

if (!defined('POLLER_VERBOSITY_MEDIUM')) {
	define('POLLER_VERBOSITY_MEDIUM', 3);
}

if (!defined('POLLER_VERBOSITY_DEBUG')) {
	define('POLLER_VERBOSITY_DEBUG', 5);
}

if (!defined('POLLER_VERBOSITY_NONE')) {
	define('POLLER_VERBOSITY_NONE', 6);
}

if (!defined('MESSAGE_LEVEL_ERROR')) {
	define('MESSAGE_LEVEL_ERROR', 1);
}

if (!function_exists('plugin_test_read_source')) {
	function plugin_test_read_source($relative_file) {
		$path = realpath(__DIR__ . '/../' . $relative_file);

		if ($path === false) {
			throw new RuntimeException("Unable to resolve required file: {$relative_file}");
		}

		$contents = file_get_contents($path);

		if ($contents === false) {
			throw new RuntimeException("Unable to read required file: {$relative_file}");
		}

		return $contents;
	}
}

/**
 * Load a plugin source file at global scope.
 *
 * Some plugin files define data as file-scope variables that the rest of
 * the plugin reads as globals, and they read $config while doing so.
 * Requiring them from inside a method would make both halves of that
 * method-local, so the require happens here and any variable the file
 * introduced is published to $GLOBALS.
 *
 * @param string $path Absolute path to the file.
 *
 * @return void
 */
function mactrack_test_load($path) {
	global $config;

	$__before = get_defined_vars();

	require_once $path;

	foreach (get_defined_vars() as $__name => $__value) {
		if (!array_key_exists($__name, $__before) && strncmp($__name, '__', 2) !== 0) {
			$GLOBALS[$__name] = $__value;
		}
	}
}

/**
 * Enumerate every tracked production PHP file (everything except tests/
 * and the vendored Net/DNS2 library, which isn't ours to gate).
 *
 * @return array<int, string>
 */
function mactrack_test_production_php_files() {
	$root  = realpath(__DIR__ . '/..');
	$files = [];

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
	);

	foreach ($iterator as $file) {
		if ($file->getExtension() !== 'php') {
			continue;
		}

		$relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

		if (strpos($relative, 'tests/') === 0 || strpos($relative, 'Net/') === 0) {
			continue;
		}

		$files[] = $relative;
	}

	sort($files);

	return $files;
}

