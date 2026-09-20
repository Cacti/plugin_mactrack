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

describe('setup.php structure in mactrack', function () {
	$source = plugin_test_read_source('setup.php');

	$infoPath = realpath(__DIR__ . '/../../INFO');
	if ($infoPath === false) {
		throw new RuntimeException('Unable to resolve required file: INFO');
	}

	$infoFile = parse_ini_file($infoPath, true);
	if (!is_array($infoFile) || !isset($infoFile['info']) || !is_array($infoFile['info'])) {
		throw new RuntimeException('Unable to parse the INFO section');
	}
	$info = $infoFile['info'];

	it('defines plugin_mactrack_install function', function () use ($source) {
		expect($source)->toContain('function plugin_mactrack_install');
	});

	it('defines plugin_mactrack_uninstall function', function () use ($source) {
		expect($source)->toContain('function plugin_mactrack_uninstall');
	});

	it('defines plugin_mactrack_version function', function () use ($source) {
		expect($source)->toContain('function plugin_mactrack_version');
	});

	it('defines plugin_mactrack_check_config function', function () use ($source) {
		expect($source)->toContain('function plugin_mactrack_check_config');
	});

	it('registers hooks via api_plugin_register_hook', function () use ($source) {
		expect($source)->toContain("api_plugin_register_hook('mactrack'");
	});

	it('registers realms via api_plugin_register_realm', function () use ($source) {
		expect($source)->toContain("api_plugin_register_realm('mactrack'");
	});

	it('cleans up Default-site retry tracking settings on uninstall', function () use ($source) {
		expect($source)->toContain('mt_default_site_seed_pending');
		expect($source)->toContain('mt_default_site_seed_attempts');
		expect($source)->toContain('mt_default_site_seed_next_retry');
	});

	it('declares a plugin name in INFO', function () use ($info) {
		expect($info)->toHaveKey('name');
		expect($info['name'])->toBe('mactrack');
	});

	it('declares a plugin version in INFO', function () use ($info) {
		expect($info)->toHaveKey('version');
		expect($info['version'])->not->toBe('');
		expect($info['version'])->toMatch('/^\d+\.\d+$/');
	});
});
