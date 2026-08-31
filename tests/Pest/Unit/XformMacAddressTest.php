<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

// Mirror of xform_mac_address() for unit testing without loading full Cacti.
function test_xform_mac_address($mac_address) {
	$mac_address = trim((string) $mac_address);

	if ($mac_address === '') {
		return 'NOT USER';
	}

	if (strlen($mac_address) > 10) {
		$mac_address = str_replace(
			['HEX-00:', 'HEX-:', 'HEX-', '"', ' ', '-'],
			['',        '',      '',     '',  ':', ':'],
			$mac_address
		);
	} else {
		$mac = '';

		for ($j = 0; $j < strlen($mac_address); $j++) {
			$mac .= bin2hex($mac_address[$j]) . ':';
		}

		$mac_address = $mac;
	}

	$mac_address = str_replace(':', '', $mac_address);

	return strtoupper($mac_address);
}

test('empty MAC becomes NOT USER', function () {
	expect(test_xform_mac_address(''))->toBe('NOT USER');
	expect(test_xform_mac_address('   '))->toBe('NOT USER');
});

test('ASCII and HEX- forms strip delimiters', function () {
	expect(test_xform_mac_address('aa-bb-cc-dd-ee-ff'))->toBe('AABBCCDDEEFF');
	expect(test_xform_mac_address('HEX-00:aa:bb:cc:dd:ee:ff'))->toBe('AABBCCDDEEFF');
	expect(test_xform_mac_address('aa:bb:cc:dd:ee:ff'))->toBe('AABBCCDDEEFF');
});

test('binary hex bytes convert to uppercase hex string', function () {
	$binary = hex2bin('aabbccddeeff');
	expect(test_xform_mac_address($binary))->toBe('AABBCCDDEEFF');
});

test('production xform_mac_address keeps a single working variable', function () {
	$src = file_get_contents(dirname(__DIR__, 3) . '/lib/mactrack_functions.php');
	// The max_address typo path must not reappear.
	expect($src)->not->toContain("str_replace(':', '', \$max_address)");
	expect($src)->toContain("str_replace(':', '', \$mac_address)");
	expect($src)->toContain("return 'NOT USER'");
});

test('macauth schedule persists last-run time', function () {
	$src = file_get_contents(dirname(__DIR__, 3) . '/poller_mactrack.php');
	expect($src)->toContain("set_config_option('mt_last_macauth_time'");
	expect($src)->toContain('$last_macauth_time + ($mac_auth_frequency * 60) < time()');
});
