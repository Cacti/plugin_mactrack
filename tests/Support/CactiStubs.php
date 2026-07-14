<?php
// Cacti 1.2.31 API declarations used only by static analysis.

function __($text, ...$args) {
	return $text;
}
function __esc($text, ...$args) {
	return $text;
}
function cacti_sizeof($value) {
	return is_countable($value) ? count($value) : 0;
}
function db_execute($sql) {
	return true;
}
function db_execute_prepared($sql, array $params = []) {
	return true;
}
function db_fetch_assoc($sql) {
	return [];
}
function db_fetch_assoc_prepared($sql, array $params = []) {
	return [];
}
function db_fetch_cell($sql) {
	return null;
}
function db_fetch_cell_prepared($sql, array $params = []) {
	return null;
}
function db_fetch_row_prepared($sql, array $params = []) {
	return [];
}
function db_qstr($value) {
	return "''";
}
function db_qstr_rlike($value) {
	return "RLIKE ''";
}
function read_config_option($name, $force = false) {
	return '';
}
function set_config_option($name, $value) {
	return true;
}
function get_request_var($name, $default = null) {
	return $default;
}
function get_filter_request_var($name, $default = null) {
	return $default;
}
function get_nfilter_request_var($name, $default = null) {
	return $default;
}
function isset_request_var($name) {
	return false;
}
function isempty_request_var($name) {
	return true;
}
function set_request_var($name, $value) {
}
function validate_store_request_vars(array $filters, $session_name) {
}
function form_input_validate($value, $field_name, $regex = '', $allow_null = false, $error_type = 3) {
	return $value;
}
function sql_save(array $save, $table, $key = null, $autoincrement = true) {
	return 0;
}
function cacti_log($message, $popup = false, $type = '') {
}
function cacti_escapeshellcmd($value) {
	return escapeshellcmd($value);
}
function cacti_escapeshellarg($value) {
	return escapeshellarg($value);
}
