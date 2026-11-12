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
	/**
	 * Test stub standing in for Cacti core's db_execute(). Records the call (fn, sql, empty params) in $GLOBALS['__test_db_calls'].
	 *
	 * @param string $sql SQL statement.
	 * @return bool|mixed Next queued return for db_execute, else true.
	 */
	function db_execute($sql) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'db_execute', 'sql' => $sql, 'params' => array());

		return mactrack_test_next_return('db_execute', true);
	}
}

if (!function_exists('db_execute_prepared')) {
	/**
	 * Test stub standing in for Cacti core's db_execute_prepared(). Records the call with its bound params in $GLOBALS['__test_db_calls'].
	 *
	 * @param string $sql SQL statement.
	 * @param array $params Bound parameters.
	 * @return bool|mixed SQL-matched or queued return for db_execute_prepared, else true.
	 */
	function db_execute_prepared($sql, $params = array()) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'db_execute_prepared', 'sql' => $sql, 'params' => $params);

		return mactrack_test_next_return('db_execute_prepared', true, $sql);
	}
}

if (!function_exists('db_affected_rows')) {
	function db_affected_rows($db_conn = false) {
		return mactrack_test_next_return('db_affected_rows', 0);
	}
}

if (!function_exists('db_fetch_assoc')) {
	/**
	 * Test stub standing in for Cacti core's db_fetch_assoc(). Records the call in $GLOBALS['__test_db_calls'].
	 *
	 * @param string $sql SQL statement.
	 * @return array|mixed Queued or SQL-matched return for db_fetch_assoc, else an empty array.
	 */
	function db_fetch_assoc($sql) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'db_fetch_assoc', 'sql' => $sql, 'params' => array());

		return mactrack_test_next_return('db_fetch_assoc', array(), $sql);
	}
}

if (!function_exists('db_fetch_assoc_prepared')) {
	/**
	 * Test stub standing in for Cacti core's db_fetch_assoc_prepared(). Records the call with its bound params in $GLOBALS['__test_db_calls'].
	 *
	 * @param string $sql SQL statement.
	 * @param array $params Bound parameters.
	 * @return array|mixed Next queued return for db_fetch_assoc_prepared, else an empty array.
	 */
	function db_fetch_assoc_prepared($sql, $params = array()) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'db_fetch_assoc_prepared', 'sql' => $sql, 'params' => $params);

		return mactrack_test_next_return('db_fetch_assoc_prepared', array(), $sql);
	}
}

if (!function_exists('db_begin_transaction')) {
	function db_begin_transaction($db_conn = false) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'db_begin_transaction', 'sql' => 'BEGIN', 'params' => array());

		return mactrack_test_next_return('db_begin_transaction', true);
	}
}

if (!function_exists('db_commit_transaction')) {
	function db_commit_transaction($db_conn = false) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'db_commit_transaction', 'sql' => 'COMMIT', 'params' => array());

		return mactrack_test_next_return('db_commit_transaction', true);
	}
}

if (!function_exists('db_rollback_transaction')) {
	function db_rollback_transaction($db_conn = false) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'db_rollback_transaction', 'sql' => 'ROLLBACK', 'params' => array());

		return mactrack_test_next_return('db_rollback_transaction', true);
	}
}

if (!function_exists('db_fetch_row')) {
	/**
	 * Test stub standing in for Cacti core's db_fetch_row(). Records the call in $GLOBALS['__test_db_calls'].
	 *
	 * @param string $sql SQL statement.
	 * @return array|mixed Next queued return for db_fetch_row, else an empty array.
	 */
	function db_fetch_row($sql) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'db_fetch_row', 'sql' => $sql, 'params' => array());

		return mactrack_test_next_return('db_fetch_row', array());
	}
}

if (!function_exists('db_fetch_row_prepared')) {
	/**
	 * Test stub standing in for Cacti core's db_fetch_row_prepared(). Records the call with its bound params in $GLOBALS['__test_db_calls'].
	 *
	 * @param string $sql SQL statement.
	 * @param array $params Bound parameters.
	 * @return array|mixed Next queued return for db_fetch_row_prepared, else an empty array.
	 */
	function db_fetch_row_prepared($sql, $params = array()) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'db_fetch_row_prepared', 'sql' => $sql, 'params' => $params);

		return mactrack_test_next_return('db_fetch_row_prepared', array(), $sql);
	}
}

if (!function_exists('db_fetch_cell')) {
	/**
	 * Test stub standing in for Cacti core's db_fetch_cell(). Records the call in $GLOBALS['__test_db_calls'].
	 *
	 * @param string $sql SQL statement.
	 * @return mixed Queued or SQL-matched return for db_fetch_cell, else an empty string.
	 */
	function db_fetch_cell($sql) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'db_fetch_cell', 'sql' => $sql, 'params' => array());

		return mactrack_test_next_return('db_fetch_cell', '', $sql);
	}
}

if (!function_exists('db_fetch_cell_prepared')) {
	/**
	 * Test stub standing in for Cacti core's db_fetch_cell_prepared(). Records the call with its bound params in $GLOBALS['__test_db_calls'].
	 *
	 * @param string $sql SQL statement.
	 * @param array $params Bound parameters.
	 * @return mixed Queued or SQL-matched return for db_fetch_cell_prepared, else an empty string.
	 */
	function db_fetch_cell_prepared($sql, $params = array()) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'db_fetch_cell_prepared', 'sql' => $sql, 'params' => $params);

		return mactrack_test_next_return('db_fetch_cell_prepared', '', $sql);
	}
}

if (!function_exists('db_qstr')) {
	function db_qstr($value) {
		return "'" . str_replace("'", "''", (string) $value) . "'";
	}
}

if (!function_exists('db_index_exists')) {
	/**
	 * Test stub standing in for Cacti core's db_index_exists(). Does not record the call; returns the next queued value, defaulting to false.
	 *
	 * @param string $table Table name.
	 * @param string $index Index name.
	 * @return bool|mixed
	 */
	function db_index_exists($table, $index) {
		return mactrack_test_next_return('db_index_exists', false);
	}
}

if (!function_exists('db_column_exists')) {
	/**
	 * Test stub standing in for Cacti core's db_column_exists(). Does not record the call; returns the next queued value, defaulting to false.
	 *
	 * @param string $table Table name.
	 * @param string $column Column name.
	 * @return bool|mixed
	 */
	function db_column_exists($table, $column) {
		return mactrack_test_next_return('db_column_exists', false);
	}
}

if (!function_exists('db_table_exists')) {
	/**
	 * Test stub standing in for Cacti core's db_table_exists(). Does not record the call; returns the next queued value, defaulting to true.
	 *
	 * @param string $table Table name.
	 * @return bool|mixed
	 */
	function db_table_exists($table) {
		return mactrack_test_next_return('db_table_exists', true, (string) $table);
	}
}

if (!function_exists('get_selected_theme')) {
	/**
	 * Test stub standing in for Cacti core's get_selected_theme(). Returns $GLOBALS['__test_selected_theme'], defaulting to 'modern'.
	 *
	 * @return string
	 */
	function get_selected_theme() {
		return $GLOBALS['__test_selected_theme'] ?? 'modern';
	}
}

if (!function_exists('get_md5_include_css')) {
	/**
	 * Test stub standing in for Cacti core's get_md5_include_css(). Returns the path unchanged, without a cache-busting hash.
	 *
	 * @param string $path Stylesheet path.
	 * @return string
	 */
	function get_md5_include_css($path) {
		return (string) $path;
	}
}

if (!function_exists('get_md5_include_js')) {
	/**
	 * Test stub standing in for Cacti core's get_md5_include_js(). Returns the path unchanged, without a cache-busting hash.
	 *
	 * @param string $path Script path.
	 * @return string
	 */
	function get_md5_include_js($path) {
		return (string) $path;
	}
}

if (!function_exists('api_plugin_db_add_column')) {
	/**
	 * Test stub standing in for Cacti core's api_plugin_db_add_column(). Does nothing and reports success.
	 *
	 * @param string $plugin Plugin directory name.
	 * @param string $table Table name.
	 * @param array $data Column definition.
	 * @return bool Always true.
	 */
	function api_plugin_db_add_column($plugin, $table, $data) {
		return true;
	}
}

if (!function_exists('api_plugin_db_table_create')) {
	/**
	 * Test stub standing in for Cacti core's api_plugin_db_table_create(). Does nothing and reports success.
	 *
	 * @param string $plugin Plugin directory name.
	 * @param string $table Table name.
	 * @param array $data Table definition.
	 * @return bool Always true.
	 */
	function api_plugin_db_table_create($plugin, $table, $data) {
		return true;
	}
}

if (!function_exists('api_plugin_register_hook')) {
	/**
	 * Test stub standing in for Cacti core's api_plugin_register_hook(). Records the registration in $GLOBALS['__test_db_calls'] with the hook name in 'sql' and array($function, $file) in 'params'.
	 *
	 * @param string $plugin Plugin directory name.
	 * @param string $hook Hook name.
	 * @param string $function Callback function name.
	 * @param string $file File that defines the callback.
	 * @param string $inline Unused.
	 * @return bool Always true.
	 */
	function api_plugin_register_hook($plugin, $hook, $function, $file, $inline = '') {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'api_plugin_register_hook', 'sql' => $hook, 'params' => array($function, $file));

		return true;
	}
}

if (!function_exists('api_plugin_register_realm')) {
	/**
	 * Test stub standing in for Cacti core's api_plugin_register_realm(). Records the registration in $GLOBALS['__test_db_calls'] with the realm name in 'sql' and array($files, $alone) in 'params'.
	 *
	 * @param string $plugin Plugin directory name.
	 * @param string $files Comma-separated files the realm covers.
	 * @param string $name Realm display name.
	 * @param int $alone Standalone-realm flag.
	 * @return bool Always true.
	 */
	function api_plugin_register_realm($plugin, $files, $name, $alone = 0) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'api_plugin_register_realm', 'sql' => $name, 'params' => array($files, $alone));

		return true;
	}
}

if (!function_exists('api_plugin_is_enabled')) {
	/**
	 * Test stub standing in for Cacti core's api_plugin_is_enabled(). Returns the next queued value, defaulting to true.
	 *
	 * @param string $plugin Plugin directory name.
	 * @return bool|mixed
	 */
	function api_plugin_is_enabled($plugin) {
		return mactrack_test_next_return('api_plugin_is_enabled', true);
	}
}

if (!function_exists('exec_background')) {
	/**
	 * Test stub standing in for Cacti core's exec_background(). Records the command in 'sql' and array($args) in 'params' of $GLOBALS['__test_db_calls'] instead of launching a process.
	 *
	 * @param string $command Command that would be run.
	 * @param string $args Command arguments.
	 * @return void
	 */
	function exec_background($command, $args = '') {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'exec_background', 'sql' => $command, 'params' => array($args));
	}
}

/*
 * Process-registry helpers used by the background database upgrade. Tests only
 * need them to record the call and hand back a programmable result.
 */
if (!function_exists('register_process_start')) {
	function register_process_start($tasktype, $taskname, $taskid = 0, $timeout = 300) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'register_process_start', 'sql' => "$tasktype:$taskname:$taskid", 'params' => array($timeout));

		return mactrack_test_next_return('register_process_start', true);
	}
}

if (!function_exists('unregister_process')) {
	function unregister_process($tasktype, $taskname, $taskid = 0, $pid = -1) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'unregister_process', 'sql' => "$tasktype:$taskname:$taskid", 'params' => array($pid));

		return true;
	}
}

if (!function_exists('heartbeat_process')) {
	function heartbeat_process($tasktype, $taskname, $taskid = 0) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'heartbeat_process', 'sql' => "$tasktype:$taskname:$taskid", 'params' => array());

		return true;
	}
}

if (!function_exists('api_plugin_enable_hooks')) {
	/**
	 * Test stub standing in for Cacti core's api_plugin_enable_hooks(). Does nothing.
	 *
	 * @param string $plugin Plugin directory name.
	 * @return void
	 */
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
	/**
	 * Test stub standing in for Cacti core's read_config_option(). Reads from the in-memory store $GLOBALS['__test_config_options'].
	 *
	 * @param string $name Option name.
	 * @param bool $force Unused.
	 * @return mixed The stored value, or an empty string when unset.
	 */
	function read_config_option($name, $force = false) {
		// Allow a test to program successive return values (FIFO) so a
		// concurrency re-check that reads the same option twice can be driven
		// to differ between calls. Falls back to the persistent store.
		if (!empty($GLOBALS['__test_next_returns']['read_config_option'])) {
			return array_shift($GLOBALS['__test_next_returns']['read_config_option']);
		}

		if (array_key_exists($name, $GLOBALS['__test_config_options'])) {
			return $GLOBALS['__test_config_options'][$name];
		}

		return '';
	}
}

if (!function_exists('set_config_option')) {
	/**
	 * Test stub standing in for Cacti core's set_config_option(). Writes the value to the in-memory store $GLOBALS['__test_config_options'].
	 *
	 * @param string $name Option name.
	 * @param mixed $value Value to store.
	 * @return void
	 */
	function set_config_option($name, $value) {
		$GLOBALS['__test_config_options'][$name] = $value;
	}
}

if (!function_exists('html_escape')) {
	/**
	 * Test stub standing in for Cacti core's html_escape(). Escapes quotes and markup with htmlspecialchars() as UTF-8 HTML5.
	 *
	 * @param mixed $string Value to escape.
	 * @return string
	 */
	function html_escape($string) {
		return htmlspecialchars((string) $string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}
}

if (!function_exists('__')) {
	// Mirror Cacti's variadic __(): a format string plus optional sprintf
	// arguments and a trailing text domain. Declared with func_get_args() so
	// static analysis treats it as variadic rather than a fixed 2-arg function.
	/**
	 * Test stub standing in for Cacti core's variadic __() translator. Performs no translation or sprintf substitution.
	 *
	 * @return string The first argument, or an empty string when none is given.
	 */
	function __() {
		$args = func_get_args();

		return $args[0] ?? '';
	}
}

if (!function_exists('html_start_box')) {
	/**
	 * Test stub standing in for Cacti core's html_start_box(). Prints nothing.
	 *
	 * @param string $title Box title.
	 * @param string $width Box width.
	 * @param string $div Unused.
	 * @param int $cell_padding Cell padding.
	 * @param string $align Alignment.
	 * @param string $add_text Link text for the title bar.
	 * @return void
	 */
	function html_start_box($title = '', $width = '100%', $div = '', $cell_padding = 3, $align = 'center', $add_text = '') {
	}
}

if (!function_exists('html_end_box')) {
	/**
	 * Test stub standing in for Cacti core's html_end_box(). Prints nothing.
	 *
	 * @param bool $trailing_br Whether a trailing break would be emitted.
	 * @param bool $resizable Whether the box would be resizable.
	 * @return void
	 */
	function html_end_box($trailing_br = true, $resizable = false) {
	}
}

if (!function_exists('__esc')) {
	/**
	 * Test stub standing in for Cacti core's __esc(). Returns the text HTML-escaped with htmlspecialchars() without translating it.
	 *
	 * @param string $text Text to escape.
	 * @param string $domain Unused text domain.
	 * @return string
	 */
	function __esc($text, $domain = '') {
		return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}
}

if (!function_exists('cacti_log')) {
	/**
	 * Test stub standing in for Cacti core's cacti_log(). Records the message in $GLOBALS['__test_db_calls'] and appends it to $GLOBALS['__test_cacti_log'].
	 *
	 * @param string $message Log message.
	 * @param bool $also_print Unused.
	 * @param string $log_type Unused.
	 * @param int $level Unused.
	 * @return void
	 */
	function cacti_log($message, $also_print = false, $log_type = '', $level = 0) {
		$GLOBALS['__test_db_calls'][]    = array('fn' => 'cacti_log', 'sql' => $message, 'params' => array());
		$GLOBALS['__test_cacti_log'][]   = (string) $message;
	}
}

if (!function_exists('cacti_sizeof')) {
	/**
	 * Test stub standing in for Cacti core's cacti_sizeof().
	 *
	 * @param mixed $array Value to count.
	 * @return int Element count for an array, otherwise 0.
	 */
	function cacti_sizeof($array) {
		return is_array($array) ? count($array) : 0;
	}
}

if (!function_exists('cacti_escapeshellarg')) {
	/**
	 * Test stub standing in for Cacti core's cacti_escapeshellarg(). Delegates to escapeshellarg().
	 *
	 * @param mixed $string Argument to quote.
	 * @param bool $quote Unused.
	 * @return string
	 */
	function cacti_escapeshellarg($string, $quote = true) {
		return escapeshellarg((string) $string);
	}
}

if (!function_exists('cacti_escapeshellcmd')) {
	/**
	 * Test stub standing in for Cacti core's cacti_escapeshellcmd(). Delegates to escapeshellcmd().
	 *
	 * @param mixed $string Command to escape.
	 * @return string
	 */
	function cacti_escapeshellcmd($string) {
		return escapeshellcmd((string) $string);
	}
}

if (!function_exists('is_realm_allowed')) {
	/**
	 * Test stub standing in for Cacti core's is_realm_allowed(). Grants every realm.
	 *
	 * @param int $realm Realm id.
	 * @return bool Always true.
	 */
	function is_realm_allowed($realm) {
		return true;
	}
}

if (!function_exists('raise_message')) {
	/**
	 * Test stub standing in for Cacti core's raise_message(). Records the id in 'sql' and array($text, $level) in 'params' of $GLOBALS['__test_db_calls'].
	 *
	 * @param int $id Message id.
	 * @param string $text Message text.
	 * @param int $level Message level.
	 * @return void
	 */
	function raise_message($id, $text = '', $level = 0) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'raise_message', 'sql' => $id, 'params' => array($text, $level));
	}
}

/*
 * Records the (sql_where, order_by, limit) of each call in
 * $GLOBALS['__test_graph_calls'] and reports $GLOBALS['__test_graph_total']
 * rows; TestCase::setUp() resets both.
 */
if (!function_exists('get_allowed_graphs')) {
	/**
	 * Test stub standing in for Cacti core's get_allowed_graphs(). Records array($sql_where, $order_by, $limit) in $GLOBALS['__test_graph_calls'] and sets $total_rows from $GLOBALS['__test_graph_total'].
	 *
	 * @param string $sql_where WHERE clause.
	 * @param string $order_by ORDER BY clause.
	 * @param string $limit LIMIT clause.
	 * @param int &$total_rows Receives the stubbed total row count.
	 * @return array Always empty.
	 */
	function get_allowed_graphs($sql_where = '', $order_by = '', $limit = '', &$total_rows = 0) {
		$GLOBALS['__test_graph_calls'][] = array($sql_where, $order_by, $limit);
		$total_rows = $GLOBALS['__test_graph_total'] ?? 0;

		return array();
	}
}

if (!function_exists('get_current_page')) {
	/**
	 * Test stub standing in for Cacti core's get_current_page(). Returns the next queued value, defaulting to an empty string.
	 *
	 * @return string|mixed
	 */
	function get_current_page() {
		return mactrack_test_next_return('get_current_page', '');
	}
}

if (!function_exists('get_request_var')) {
	/**
	 * Test stub standing in for Cacti core's get_request_var(). Reads $GLOBALS['__test_request_vars'].
	 *
	 * @param string $name Request variable name.
	 * @return mixed The stored value, or an empty string when unset.
	 */
	function get_request_var($name) {
		return $GLOBALS['__test_request_vars'][$name] ?? '';
	}
}

if (!function_exists('get_nfilter_request_var')) {
	/**
	 * Test stub standing in for Cacti core's get_nfilter_request_var(). Ignores the request store.
	 *
	 * @param string $name Request variable name.
	 * @return string Always an empty string.
	 */
	function get_nfilter_request_var($name) {
		return '';
	}
}

if (!function_exists('get_filter_request_var')) {
	/**
	 * Test stub standing in for Cacti core's get_filter_request_var(). Performs no validation and ignores the request store.
	 *
	 * @param string $name Request variable name.
	 * @return string Always an empty string.
	 */
	function get_filter_request_var($name) {
		return '';
	}
}

if (!function_exists('isset_request_var')) {
	/**
	 * Test stub standing in for Cacti core's isset_request_var(). Returns the next queued value, defaulting to false.
	 *
	 * @param string $name Request variable name.
	 * @return bool|mixed
	 */
	function isset_request_var($name) {
		return mactrack_test_next_return('isset_request_var', false);
	}
}

if (!function_exists('set_request_var')) {
	/**
	 * Test stub standing in for Cacti core's set_request_var(). Stores the value in $GLOBALS['__test_request_vars'].
	 *
	 * @param string $name Request variable name.
	 * @param mixed $value Value to store.
	 * @return void
	 */
	function set_request_var($name, $value) {
		$GLOBALS['__test_request_vars'][$name] = $value;
	}
}

if (!function_exists('read_user_setting')) {
	/**
	 * Test stub standing in for Cacti core's read_user_setting(). Always returns the supplied default.
	 *
	 * @param string $name Setting name.
	 * @param mixed $default Value to return.
	 * @param bool $force Unused.
	 * @param int $user_id Unused.
	 * @return mixed
	 */
	function read_user_setting($name, $default = false, $force = false, $user_id = 0) {
		return $default;
	}
}

if (!function_exists('sanitize_search_string')) {
	/**
	 * Test stub standing in for Cacti core's sanitize_search_string(). Returns the string unchanged.
	 *
	 * @param string $string Search string.
	 * @return string
	 */
	function sanitize_search_string($string) {
		return $string;
	}
}

if (!function_exists('api_user_realm_auth')) {
	/**
	 * Test stub standing in for Cacti core's api_user_realm_auth(). Returns the next queued value, defaulting to true (authorized).
	 *
	 * @param string $filename Page file name.
	 * @return bool|mixed
	 */
	function api_user_realm_auth($filename) {
		return mactrack_test_next_return('api_user_realm_auth', true);
	}
}

if (!function_exists('form_input_validate')) {
	/**
	 * Test stub standing in for Cacti core's form_input_validate(). Performs no validation and returns the value unchanged.
	 *
	 * @param mixed $value Submitted value.
	 * @param string $name Field name.
	 * @param string $regex Unused validation pattern.
	 * @param bool $optional Unused.
	 * @param int $error Unused error id.
	 * @return mixed
	 */
	function form_input_validate($value, $name, $regex, $optional, $error) {
		return $value;
	}
}

if (!function_exists('is_error_message')) {
	/**
	 * Test stub standing in for Cacti core's is_error_message(). Reports that no validation error is pending.
	 *
	 * @return bool Always false.
	 */
	function is_error_message() {
		return false;
	}
}

if (!function_exists('sql_save')) {
	/**
	 * Test stub standing in for Cacti core's sql_save(). Writes nothing.
	 *
	 * @param array $array Row to save.
	 * @param string $table Table name.
	 * @param string $key Primary key column.
	 * @return int The row's id when present, otherwise 1.
	 */
	function sql_save($array, $table, $key = 'id') {
		return isset($array['id']) ? $array['id'] : 1;
	}
}

/*
 * Row-rendering helpers mactrack_format_device_row() calls, so the device
 * enable/disable actions can run to completion.
 */
if (!function_exists('filter_value')) {
	/**
	 * Test stub standing in for Cacti core's filter_value(). Returns the value without highlighting or linking it.
	 *
	 * @param string $value Cell value.
	 * @param string $filter Unused filter text.
	 * @param string $href Unused link target.
	 * @return string
	 */
	function filter_value($value, $filter, $href = '') {
		return $value;
	}
}

if (!function_exists('get_colored_device_status')) {
	/**
	 * Test stub standing in for Cacti core's get_colored_device_status(). Renders no status markup.
	 *
	 * @param bool|int $disabled Whether the device is disabled.
	 * @param int $status Device status code.
	 * @param int $thold_failure_count Unused.
	 * @param int $status_event_count Unused.
	 * @return string Always an empty string.
	 */
	function get_colored_device_status($disabled, $status, $thold_failure_count = -1, $status_event_count = -1) {
		return '';
	}
}

if (!function_exists('number_format_i18n')) {
	/**
	 * Test stub standing in for Cacti core's number_format_i18n(). Records array('number' => ..., 'decimals' => ...) in $GLOBALS['__test_number_format_i18n'] and applies no locale formatting.
	 *
	 * @param int|float $number Number to format.
	 * @param int|null $decimals Requested decimals.
	 * @param int $baseu Unused base unit.
	 * @return string The number cast to a string.
	 */
	function number_format_i18n($number, $decimals = null, $baseu = 1024) {
		$GLOBALS['__test_number_format_i18n'][] = array('number' => $number, 'decimals' => $decimals);

		return (string) $number;
	}
}

if (!function_exists('form_checkbox_cell')) {
	/**
	 * Test stub standing in for Cacti core's form_checkbox_cell(). Prints an empty table cell.
	 *
	 * @param string $title Unused row title.
	 * @param int|string $id Unused row id.
	 * @param bool $disabled Unused.
	 * @return void
	 */
	function form_checkbox_cell($title, $id, $disabled = false) {
		print '<td></td>';
	}
}

if (!function_exists('form_end_row')) {
	/**
	 * Test stub standing in for Cacti core's form_end_row(). Prints a closing row tag.
	 *
	 * @return void
	 */
	function form_end_row() {
		print '</tr>';
	}
}

/*
 * SNMP answers for the scanner functions. A test stores the rows a walk of
 * an OID returns in $GLOBALS['__test_snmp_walk'][$oid], in the
 * array('oid' => ..., 'value' => ...) shape cacti_snmp_walk() returns.
 */
if (!function_exists('cacti_snmp_walk')) {
	/**
	 * Test stub standing in for Cacti core's cacti_snmp_walk(). Returns the rows a test stored in $GLOBALS['__test_snmp_walk'][$oid]; all connection arguments are ignored.
	 *
	 * @param string $hostname Unused.
	 * @param string $community Unused.
	 * @param string $oid OID whose stored rows are returned.
	 * @param int $version Unused.
	 * @param mixed ... Remaining SNMP credential and transport arguments, all unused.
	 * @return array Stored array('oid' => ..., 'value' => ...) rows, or an empty array.
	 */
	function cacti_snmp_walk($hostname, $community, $oid, $version, $auth_user = '', $auth_pass = '',
		$auth_proto = '', $priv_pass = '', $priv_proto = '', $context = '',
		$port = 161, $timeout_ms = 500, $retries = 0, $max_oids = 10, $environ = 0,
		$engineid = '', $value_output_format = 1) {
		return $GLOBALS['__test_snmp_walk'][$oid] ?? array();
	}
}

if (!function_exists('cacti_snmp_get')) {
	/**
	 * Test stub standing in for Cacti core's cacti_snmp_get(). Returns the next queued value, defaulting to an empty string; all connection arguments are ignored.
	 *
	 * @param string $hostname Unused.
	 * @param string $community Unused.
	 * @param string $oid Unused.
	 * @param int $version Unused.
	 * @param mixed ... Remaining SNMP credential and transport arguments, all unused.
	 * @return string|mixed
	 */
	function cacti_snmp_get($hostname, $community, $oid, $version, $auth_user = '', $auth_pass = '',
		$auth_proto = '', $priv_pass = '', $priv_proto = '', $context = '',
		$port = 161, $timeout_ms = 500, $retries = 0, $environ = 0,
		$engineid = '', $value_output_format = 1) {
		return mactrack_test_next_return('cacti_snmp_get', '');
	}
}

// Same body as Cacti's lib/functions.php.
if (!function_exists('array_rekey')) {
	/**
	 * Test stub standing in for Cacti core's array_rekey(), with the same body. Re-keys rows by the value of one column and maps it to a single column or a sub-array of columns.
	 *
	 * @param array $array Rows to re-key.
	 * @param string $key Column whose value becomes the new key.
	 * @param string|array $key_value Column, or list of columns, to keep per key.
	 * @return array
	 */
	function array_rekey($array, $key, $key_value) {
		$ret_array = array();

		if (is_array($array)) {
			foreach ($array as $item) {
				$item_key = $item[$key];

				if (is_array($key_value)) {
					foreach ($key_value as $value) {
						$ret_array[$item_key][$value] = $item[$value];
					}
				} else {
					$ret_array[$item_key] = $item[$key_value];
				}
			}
		}

		return $ret_array;
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

if (!defined('POLLER_VERBOSITY_HIGH')) {
	define('POLLER_VERBOSITY_HIGH', 4);
}

if (!defined('HOST_UP')) {
	define('HOST_UP', 3);
}

if (!defined('SNMP_POLLER')) {
	define('SNMP_POLLER', 0);
}

if (!defined('SNMP_STRING_OUTPUT_GUESS')) {
	define('SNMP_STRING_OUTPUT_GUESS', 1);
}

if (!defined('SNMP_STRING_OUTPUT_HEX')) {
	define('SNMP_STRING_OUTPUT_HEX', 3);
}

if (!defined('MESSAGE_LEVEL_ERROR')) {
	define('MESSAGE_LEVEL_ERROR', 1);
}

if (!defined('MESSAGE_LEVEL_WARN')) {
	define('MESSAGE_LEVEL_WARN', 2);
}

/*
 * MacTrack's list views render cells through Cacti's form_selectable_cell()
 * and gate the status-pill detail on CACTI_VERSION via cacti_version_compare().
 * Neither Cacti itself nor its constants are loaded in the isolated unit
 * process, so stub them here. CACTI_VERSION is pinned to a release that
 * supports the pill detail so mactrack_interfaces_show_issues() resolves true.
 */
if (!function_exists('form_selectable_cell')) {
	/**
	 * Test stub standing in for Cacti core's form_selectable_cell(). Prints the contents inside a plain table cell.
	 *
	 * @param string $contents Cell HTML.
	 * @param string $id Unused.
	 * @param string $width Unused.
	 * @param string $align Unused.
	 * @param string $title Unused.
	 * @return void
	 */
	function form_selectable_cell($contents, $id = '', $width = '', $align = '', $title = '') {
		print '<td>' . $contents . '</td>';
	}
}

if (!function_exists('cacti_version_compare')) {
	/**
	 * Test stub standing in for Cacti core's cacti_version_compare(). Delegates to version_compare().
	 *
	 * @param string $version1 First version.
	 * @param string $version2 Second version.
	 * @param string $operator Comparison operator.
	 * @return bool|int
	 */
	function cacti_version_compare($version1, $version2, $operator = '>=') {
		return version_compare((string) $version1, (string) $version2, $operator);
	}
}

if (!defined('CACTI_VERSION')) {
	define('CACTI_VERSION', '1.2.32');
}

if (!function_exists('plugin_test_read_source')) {
	/**
	 * Reads a plugin source file for static-analysis tests.
	 *
	 * @param string $relative_file Path relative to the plugin root.
	 * @return string File contents. Throws RuntimeException when the path cannot be resolved or read.
	 */
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

