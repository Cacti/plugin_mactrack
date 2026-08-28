<?php

it('passes the SQL, XSS, command, path, and dependency regression checks', function () {
	$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../../Unit/test_device_type_sql_safety.php');
	exec($command, $output, $result);

	expect($result)->toBe(0);
});

it('escapes filter option labels before rendering', function () {
	$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../../Unit/test_filter_option_escaping.php');
	exec($command, $output, $result);

	expect($result)->toBe(0);
});
