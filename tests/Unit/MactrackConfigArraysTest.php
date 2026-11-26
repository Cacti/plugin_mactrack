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
*/

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

it('registers mactrack configuration arrays including macauth frequencies', function () {
	global $mactrack_macauth_frequencies, $menu;

	$menu = ['Management' => []];
	mactrack_config_arrays();

	expect($mactrack_macauth_frequencies)->toBeArray()
		->and($mactrack_macauth_frequencies)->toHaveKey('2880');
});
