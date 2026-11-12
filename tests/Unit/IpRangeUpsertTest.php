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

it('resets unseen ranges for the scanned sites and upserts the rest at once', function () {
	$src = preg_replace('/\s+/', ' ', file_get_contents(dirname(__DIR__, 2) . '/poller_mactrack.php'));

	expect($src)->toContain('db_execute_prepared("UPDATE mac_track_ip_ranges SET ips_current = 0 WHERE 1=1$site_where", $site_params);');
	expect($src)->toContain("COUNT(DISTINCT ip_address), MAX(scan_date) FROM mac_track_temp_ports WHERE ip_address != '' GROUP BY ip_range, site_id ON DUPLICATE KEY UPDATE ips_current = VALUES(ips_current), ips_current_date = VALUES(ips_current_date)");
	expect($src)->not->toContain('scan_date AS ips_current_date');
});
