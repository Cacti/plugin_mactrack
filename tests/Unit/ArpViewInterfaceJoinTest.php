<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

it('joins an ARP port to its interface by ifIndex or by ifName', function () {
	$src = preg_replace('/\s+/', ' ', file_get_contents(dirname(__DIR__, 2) . '/mactrack_view_arp.php'));

	expect($src)->toContain("AND IF(mti.port_number REGEXP '^[0-9]+$', mtin.ifIndex = mti.port_number, mtin.ifName = mti.port_number)");
	expect($src)->not->toContain('AND mtin.ifIndex=mti.port_number');
});
