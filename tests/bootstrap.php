<?php

if (PHP_SAPI !== 'cli') {
	exit(1);
}
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Test bootstrap: stub Cacti framework functions so plugin code
 * can be loaded in isolation without the full Cacti application.
 */

$GLOBALS['__test_config']                 = [];
$GLOBALS['__test_db_calls']               = [];
$GLOBALS['__test_db_execute_prepared']    = null;
$GLOBALS['__test_db_fetch_assoc']         = null;
$GLOBALS['__test_db_fetch_row']           = null;
$GLOBALS['__test_db_fetch_cell_prepared'] = '';
$GLOBALS['__test_read_config_option']      = null;
$GLOBALS['__test_request']                = [];
$GLOBALS['__test_table_definitions']      = [];
$GLOBALS['__test_logs']                   = [];
$GLOBALS['__test_messages']               = [];

if (!function_exists('db_execute')) {
	function db_execute($sql) {
		$GLOBALS['__test_db_calls'][] = ['fn' => 'db_execute', 'sql' => $sql, 'params' => []];

		return true;
	}
}

if (!function_exists('db_execute_prepared')) {
	function db_execute_prepared($sql, $params = []) {
		$GLOBALS['__test_db_calls'][] = ['fn' => 'db_execute_prepared', 'sql' => $sql, 'params' => $params];

		if (is_callable($GLOBALS['__test_db_execute_prepared'])) {
			return $GLOBALS['__test_db_execute_prepared']($sql, $params);
		}

		return true;
	}
}

if (!function_exists('db_fetch_assoc')) {
	function db_fetch_assoc($sql) {
		if (is_callable($GLOBALS['__test_db_fetch_assoc'])) {
			return $GLOBALS['__test_db_fetch_assoc']($sql);
		}

		return [];
	}
}

if (!function_exists('db_fetch_assoc_prepared')) {
	function db_fetch_assoc_prepared($sql, $params = []) {
		return [];
	}
}

if (!function_exists('db_fetch_row')) {
	function db_fetch_row($sql) {
		if (is_callable($GLOBALS['__test_db_fetch_row'])) {
			return $GLOBALS['__test_db_fetch_row']($sql);
		}

		return [];
	}
}

if (!function_exists('db_fetch_row_prepared')) {
	function db_fetch_row_prepared($sql, $params = []) {
		return [];
	}
}

if (!function_exists('db_fetch_cell')) {
	function db_fetch_cell($sql) {
		return '';
	}
}

if (!function_exists('db_fetch_cell_prepared')) {
	function db_fetch_cell_prepared($sql, $params = []) {
		if (is_callable($GLOBALS['__test_db_fetch_cell_prepared'])) {
			return $GLOBALS['__test_db_fetch_cell_prepared']($sql, $params);
		}

		return $GLOBALS['__test_db_fetch_cell_prepared'];
	}
}

if (!function_exists('db_index_exists')) {
	function db_index_exists($table, $index) {
		return false;
	}
}

if (!function_exists('db_column_exists')) {
	function db_column_exists($table, $column) {
		return false;
	}
}

if (!function_exists('db_table_exists')) {
	function db_table_exists($table) {
		return false;
	}
}

if (!function_exists('array_rekey')) {
	function array_rekey($array, $key, $column) {
		$rekeyed = [];

		foreach ($array as $row) {
			$rekeyed[$row[$key]] = $row[$column];
		}

		return $rekeyed;
	}
}

if (!function_exists('api_plugin_db_add_column')) {
	function api_plugin_db_add_column($plugin, $table, $data) {
		return true;
	}
}

if (!function_exists('api_plugin_db_table_create')) {
	function api_plugin_db_table_create($plugin, $table, $data) {
		$GLOBALS['__test_table_definitions'][$table] = $data;
		$GLOBALS['__test_db_calls'][] = ['fn' => 'api_plugin_db_table_create', 'table' => $table];

		return true;
	}
}

if (!function_exists('read_config_option')) {
	function read_config_option($name, $force = false) {
		if (is_callable($GLOBALS['__test_read_config_option'])) {
			return $GLOBALS['__test_read_config_option']($name, $force);
		}

		return $GLOBALS['__test_config'][$name] ?? '';
	}
}

if (!function_exists('set_config_option')) {
	function set_config_option($name, $value) {
		$GLOBALS['__test_config'][$name] = $value;
	}
}

if (!function_exists('db_qstr')) {
	function db_qstr($value) {
		return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], (string) $value) . "'";
	}
}

if (!function_exists('html_escape')) {
	function html_escape($string) {
		return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
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
		$GLOBALS['__test_logs'][] = ['message' => $message, 'type' => $log_type];
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
		$GLOBALS['__test_messages'][] = ['id' => $id, 'text' => $text, 'level' => $level];
	}
}

if (!function_exists('get_request_var')) {
	function get_request_var($name, $default = '') {
		return $GLOBALS['__test_request'][$name] ?? $default;
	}
}

if (!function_exists('get_nfilter_request_var')) {
	function get_nfilter_request_var($name, $default = '') {
		return $GLOBALS['__test_request'][$name] ?? $default;
	}
}

if (!function_exists('get_filter_request_var')) {
	function get_filter_request_var($name, $filter = FILTER_VALIDATE_INT, $options = []) {
		if (!array_key_exists($name, $GLOBALS['__test_request'])) {
			return $options['options']['default'] ?? null;
		}

		$value = $GLOBALS['__test_request'][$name];

		$result = $options ? filter_var($value, $filter, $options) : filter_var($value, $filter);

		if ($result === false) {
			throw new InvalidArgumentException("Invalid request value: $name");
		}

		return $result;
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
	define('CACTI_PATH_BASE', '/var/www/html/cacti');
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
	define('MESSAGE_LEVEL_ERROR', 3);
}
