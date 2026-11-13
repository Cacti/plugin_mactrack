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
 * ARP translations and 802.1x sessions expire like the rest of the data,
 * and the IP fallback uses each MAC's newest translation.
 */
it('prunes the ARP and 802.1x tables in maintenance', function () {
	$src = preg_replace('/\s+/', ' ', file_get_contents(dirname(__DIR__, 2) . '/lib/mactrack_functions.php'));

	expect($src)->toContain("db_execute_prepared('DELETE FROM mac_track_arp WHERE scan_date < CURDATE() - INTERVAL ? DAY', [(int) read_config_option('mt_data_retention_ip')]);");
	expect($src)->toContain("if (\$days > 0) { mactrack_delete_batched('DELETE FROM mac_track_dot1x WHERE scan_date < ? ORDER BY scan_date', [\$retention_date]); }");
});

it('reads the newest ARP translation for a MAC', function () {
	$src = preg_replace('/\s+/', ' ', file_get_contents(dirname(__DIR__, 2) . '/lib/mactrack_functions.php'));

	expect($src)->toContain('FROM mac_track_arp WHERE mac_address = ? AND site_id = ? ORDER BY scan_date DESC, ip_address DESC LIMIT 1');
});

it('fills unresolved ports from the newest translation in one statement', function () {
	$src = preg_replace('/\s+/', ' ', file_get_contents(dirname(__DIR__, 2) . '/poller_mactrack.php'));

	expect($src)->toContain('SELECT site_id, mac_address, MAX(scan_date) AS scan_date FROM mac_track_arp GROUP BY site_id, mac_address');
	expect($src)->toContain('AND arp.site_id = newest.site_id');
	expect($src)->toContain('AND tp.site_id = arp.site_id');
	expect($src)->toContain('SET tp.updated = 1, tp.ip_address = arp.ip_address WHERE tp.updated = 0');
	expect($src)->not->toContain("db_fetch_row_prepared('SELECT mac_address,ip_address FROM mac_track_arp WHERE mac_address = ?'");
});
