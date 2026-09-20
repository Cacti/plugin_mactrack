<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Converted from the standalone tests/Unit/test_ignore_ports_pattern.php
 * script. Guards mactrack's "Ports to Ignore" regex validation, its
 * parameterized RLIKE predicate, and the interface-issues filter matrix.
 */

require_once dirname(__DIR__, 2) . '/lib/mactrack_functions.php';

$default = '(Vlan|Loopback|Null)';

test('a valid ignore-ports pattern is preserved verbatim', function ($valid) {
	expect(mactrack_validate_ignore_ports_pattern($valid))->toBe($valid);
})->with([
	'(Vlan|Loopback|Null)',
	'^(Gi|Te)[0-9/]+$',
	'Port~Channel',
	'Vlan\~Trunk',
]);

test('an invalid ignore-ports pattern falls back to the default', function ($invalid) use ($default) {
	expect(mactrack_validate_ignore_ports_pattern($invalid))->toBe($default);
})->with([
	'',
	null,
	'(Vlan',
	'[a-',
	'(a+)+$',
]);

it('preserves the bound ignore expression under NOT in the Interfaces viewer', function () {
	$source = plugin_test_read_source('mactrack_view_interfaces.php');

	expect($source)->toContain("' NOT ' . \$ignore");
});

test('the default ignore-ports expression survives into bound RLIKE parameters', function () use ($default) {
	$GLOBALS['__test_config_options']['mt_ignorePorts'] = $default;
	$GLOBALS['__test_db_calls']                          = [];

	$params    = [];
	$predicate = mactrack_get_ignore_ports_predicate($params);

	expect($predicate)->toBe('(ifName NOT RLIKE ? AND ifDescr NOT RLIKE ?)');
	expect($params)->toBe([$default, $default]);
});

test('the interface-issues filter matrix produces bound parameters exactly when it ignores', function ($issues, $bwusage, $expected_ignore) use ($default) {
	$GLOBALS['__test_config_options']['mt_ignorePorts'] = $default;

	$actual_ignore = mactrack_interface_filter_needs_ignore($issues, $bwusage);
	$params        = [];
	$sql           = '';

	if ($actual_ignore) {
		$sql = mactrack_get_ignore_ports_predicate($params);
	}

	expect($actual_ignore)->toBe($expected_ignore);
	expect(substr_count($sql, '?'))->toBe(count($params));
})->with([
	['-4', -1, true], ['-4', 70, true],
	['-3', -1, true], ['-3', 70, true],
	['-2', -1, false], ['-2', 70, false],
	['-1', -1, true], ['-1', 70, true],
	['0', -1, true], ['0', 70, true],
	['1', -1, true], ['1', 70, true],
	['2', -1, true], ['2', 70, true],
	['3', -1, true], ['3', 70, true],
	['7', -1, false], ['7', 70, false],
	['9', -1, false], ['9', 70, true],
	['10', -1, false], ['10', 70, true],
	['11', -1, false], ['11', 70, true],
]);

test('a viewer fallback must not overwrite a non-empty administrator setting', function () use ($default) {
	$GLOBALS['__test_config_options']['mt_ignorePorts'] = '(Vlan';
	$GLOBALS['__test_db_calls']                          = [];

	expect(mactrack_get_ignore_ports_pattern())->toBe($default);
	expect($GLOBALS['__test_db_calls'])->toBeEmpty();
});

test('an empty ignore-ports setting is initialized exactly once', function () use ($default) {
	$GLOBALS['__test_config_options']['mt_ignorePorts'] = '';
	$GLOBALS['__test_db_calls']                          = [];

	expect(mactrack_get_ignore_ports_pattern())->toBe($default);
	expect($GLOBALS['__test_db_calls'])->toHaveCount(1);
});
