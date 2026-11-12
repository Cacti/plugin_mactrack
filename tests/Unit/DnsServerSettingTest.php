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
 * The DNS server settings accept an empty value or an IP address and reject
 * anything else when the settings page saves them (#285).
 */

require_once dirname(__DIR__, 2) . '/setup.php';

test('the DNS server filter accepts an empty value and IP addresses', function ($value) {
	expect(mactrack_validate_dns_address($value))->toBe($value);
})->with(['', '192.0.2.53', '2001:db8::53']);

test('the DNS server filter rejects anything that is not an IP address', function ($value) {
	expect(mactrack_validate_dns_address($value))->toBeFalse();
})->with(['dns.example.com', '192.0.2.300', ' 192.0.2.53', '192.0.2.53; rm']);

test('both DNS server settings use the filter', function () {
	global $settings, $settings_user, $tabs, $tabs_graphs;

	$settings      = ['path' => [], 'mail' => []];
	$settings_user = [];
	$tabs          = [];
	$tabs_graphs   = [];

	mactrack_config_settings();

	foreach (['mt_dns_primary', 'mt_dns_secondary'] as $name) {
		$field = $settings['mactrack'][$name];

		expect($field['filter'])->toBe(FILTER_CALLBACK)
			->and($field['options'])->toBe(['options' => 'mactrack_validate_dns_address'])
			->and(filter_var('not-an-ip', $field['filter'], $field['options']))->toBeFalse()
			->and(filter_var('', $field['filter'], $field['options']))->toBe('');
	}
});
