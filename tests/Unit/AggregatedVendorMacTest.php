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

/*
 * Before 4.9 vendor_mac held '00:11:22'; the OUI table now holds '001122'.
 */
it('derives vendor_mac from the stripped MAC when rebuilding', function () {
	$src = file_get_contents(dirname(__DIR__, 2) . '/includes/database.php');

	expect($src)->toContain("REPLACE(REPLACE(mac_address, ':', ''), '-', ''), SUBSTRING(REPLACE(REPLACE(mac_address, ':', ''), '-', ''), 1, 6), ip_address,");
});

it('refreshes vendor_mac when the poller updates an aggregated row', function () {
	$src = preg_replace('/\s+/', ' ', file_get_contents(dirname(__DIR__, 2) . '/poller_mactrack.php'));

	expect($src)->toContain('ON DUPLICATE KEY UPDATE count_rec=count_rec+1, active_last=1, date_last=t1.scan_date,port_name=t1.port_name, vendor_mac=VALUES(vendor_mac)');
});
