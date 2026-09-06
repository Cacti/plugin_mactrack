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

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../Support/StandaloneTest.php';
require_once __DIR__ . '/../../lib/mactrack_functions.php';

$test = 'MacTrack production helpers';

MactrackStandaloneTest::assertSame('1/0/24', mactrack_strip_alpha('GigabitEthernet1/0/24'), 'interface prefixes are stripped');
MactrackStandaloneTest::assertSame(93784.0, mactrack_timetics_to_seconds('1:02:03:04'), 'day timetics convert to seconds');
MactrackStandaloneTest::assertSame(3723.0, mactrack_timetics_to_seconds('1:02:03'), 'hour timetics convert to seconds');
MactrackStandaloneTest::assertSame(0, mactrack_timetics_to_seconds('invalid'), 'invalid timetics are harmless');

MactrackStandaloneTest::assertSame('10.9.21.114', xform_net_address('0A 09 15 72'), 'hex IPv4 addresses normalize');
MactrackStandaloneTest::assertSame('192.168.1.10', xform_net_address('Network Address: 192.168.1.10'), 'SNMP address prefixes normalize');
MactrackStandaloneTest::assertSame(
	'2001:0db8:85a3:0000:0000:8a2e:0370:7334',
	xform_net_address('2001:0db8:85a3:0000:0000:8a2e:0370:7334'),
	'IPv6 text remains intact'
);
MactrackStandaloneTest::assertSame('192.168.1.17', xform_net_address("\xc0\xa8\x01\x11"), 'binary IPv4 addresses normalize');

MactrackStandaloneTest::assertSame('AABBCCDDEEFF', xform_mac_address('aa-bb-cc-dd-ee-ff'), 'hyphenated MAC addresses normalize');
MactrackStandaloneTest::assertSame('AABBCCDDEEFF', xform_mac_address('HEX-00:aa:bb:cc:dd:ee:ff'), 'Net-SNMP HEX MAC addresses normalize');
MactrackStandaloneTest::assertSame('AABBCCDDEEFF', xform_mac_address(hex2bin('aabbccddeeff')), 'binary MAC addresses normalize');
MactrackStandaloneTest::assertSame('NOT USER', xform_mac_address(''), 'empty MAC addresses use the sentinel value');

$formats = [
	'aa:bb:cc:dd:ee:ff' => 'aa:bb:cc:dd:ee:ff',
	'aa-bb-cc-dd-ee-ff' => 'aa-bb-cc-dd-ee-ff',
	'aabbccddeeff'       => 'aabbccddeeff',
	'aabb-ccdd-eeff'     => 'aabb-ccdd-eeff',
	'aabb.ccdd.eeff'     => 'aabb.ccdd.eeff',
];

foreach ($formats as $format => $expected) {
	$GLOBALS['__test_config']['mt_mac_format'] = $format;
	MactrackStandaloneTest::assertSame($expected, mactrack_format_mac('aabbccddeeff'), "MAC display format $format");
}

MactrackStandaloneTest::assertSame(null, mactrack_format_mac(null), 'null MAC addresses remain null');
MactrackStandaloneTest::assertSame('aabbcc', mactrack_format_mac('aabbcc'), 'short MAC values remain unchanged');

$GLOBALS['__test_config']['mt_ignorePorts_delim'] = '|';
MactrackStandaloneTest::assertSame(['Gi1', 'Gi2', 'Vlan1'], port_list_to_array('Gi1| Gi2 |Vlan1'), 'configured port delimiters are honored');
$GLOBALS['__test_config']['mt_ignorePorts_delim'] = '-1';
MactrackStandaloneTest::assertSame(['Gi1', 'Gi2', 'Vlan1'], port_list_to_array('Gi1:Gi2:Vlan1'), 'port delimiters are auto-detected');

$GLOBALS['__test_request']['rows'] = -1;
$GLOBALS['__test_config']['num_rows_table'] = '30';
MactrackStandaloneTest::assertSame('30', plugin_get_rows_per_page(), 'default rows use the Cacti preference');
$GLOBALS['__test_request']['rows'] = -2;
MactrackStandaloneTest::assertSame(999999, plugin_get_rows_per_page(), 'all rows uses the bounded sentinel');
$GLOBALS['__test_request']['rows'] = 25;
MactrackStandaloneTest::assertSame(25, plugin_get_rows_per_page(), 'explicit rows pass through');

MactrackStandaloneTest::assertSame('fallback', mactrack_arr_key([], 'missing', 'fallback'), 'array defaults are returned');
MactrackStandaloneTest::assertSame(0, mactrack_arr_key(['value' => 0], 'value', 9), 'falsey array values are preserved');

$filter = mactrack_create_sql_filter("edge -guest's", ['device_name', 'hostname']);
MactrackStandaloneTest::assertContains("device_name  LIKE '%edge%'", $filter, 'positive filters cover the first field');
MactrackStandaloneTest::assertContains("hostname  LIKE '%edge%'", $filter, 'positive filters cover every field');
MactrackStandaloneTest::assertContains('device_name NOT LIKE ', $filter, 'negative filters cover the first field');
MactrackStandaloneTest::assertContains('hostname NOT LIKE ', $filter, 'negative filters cover every field');
MactrackStandaloneTest::assertTrue(strpos($filter, "guest's") === false, 'negative filters never interpolate an unquoted needle');
MactrackStandaloneTest::assertSame(null, mactrack_create_sql_filter('', ['hostname']), 'blank filters add no SQL');
MactrackStandaloneTest::assertSame(null, mactrack_create_sql_filter('edge', []), 'a blank field list produces no SQL');

$poller_source = file_get_contents(__DIR__ . '/../../poller_mactrack.php');
MactrackStandaloneTest::assertContains("includes/database.php", $poller_source, 'the headless poller loads Default-site recovery helpers');
MactrackStandaloneTest::assertContains('mactrack_retry_default_site();', $poller_source, 'the headless poller drives scheduled Default-site recovery');
MactrackStandaloneTest::assertContains('$last_macauth_time + ($mac_auth_frequency * 60) < time()', $poller_source, 'MacAuth reports honor the configured frequency window');
MactrackStandaloneTest::assertContains("set_config_option('mt_last_macauth_time', (string) time())", $poller_source, 'MacAuth report completion persists its last-run time');

$interface_classes = [
	[['int_errors_present' => '1', 'int_discards_present' => '0', 'ifOperStatus' => '1', 'ifAlias' => 'x'], 'int_errors'],
	[['int_errors_present' => '0', 'int_discards_present' => '1', 'ifOperStatus' => '1', 'ifAlias' => 'x'], 'int_discards'],
	[['int_errors_present' => '0', 'int_discards_present' => '0', 'ifOperStatus' => '1', 'ifAlias' => ''], 'int_up_wo_alias'],
	[['int_errors_present' => '0', 'int_discards_present' => '0', 'ifOperStatus' => '0', 'ifAlias' => 'x'], 'int_down'],
	[['int_errors_present' => '0', 'int_discards_present' => '0', 'ifOperStatus' => '1', 'ifAlias' => 'x'], 'int_up'],
];

foreach ($interface_classes as $case) {
	MactrackStandaloneTest::assertSame($case[1], mactrack_int_row_class($case[0]), 'interface state class is deterministic');
}

$dot1x_classes = [1 => 'dot1x_idle', 2 => 'dot1x_running', 3 => 'dot1x_auth_no_method', 4 => 'dot1x_auth_success', 5 => 'dot1x_auth_failed', 6 => 'dot1x_authn_success', 7 => 'dot1x_authn_failed'];

foreach ($dot1x_classes as $status => $expected) {
	MactrackStandaloneTest::assertSame($expected, mactrack_dot1x_row_class(['status' => (string) $status]), "802.1X status $status maps to its CSS class");
}

MactrackStandaloneTest::finish($test);
