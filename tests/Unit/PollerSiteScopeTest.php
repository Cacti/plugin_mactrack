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
 * A site scan collects only that site, so the end-of-run resets may only
 * touch that site's rows, and clearing active_last must not move
 * date_last (ON UPDATE CURRENT_TIMESTAMP).
 */
it('scopes the VLAN and aggregated resets to the scanned site', function () {
	$src = preg_replace('/\s+/', ' ', file_get_contents(dirname(__DIR__, 2) . '/poller_mactrack.php'));

	expect($src)->toContain("\$site_where = \$site_id > 0 ? ' AND site_id = ?' : '';");
	expect($src)->toContain("db_execute_prepared(\"UPDATE mac_track_vlans SET present='0' WHERE 1=1\$site_where\", \$site_params);");
	expect($src)->toContain("db_execute_prepared(\"DELETE FROM mac_track_vlans WHERE present='0'\$site_where\", \$site_params);");
	expect($src)->toContain('SET active_last=0, date_last=date_last WHERE active_last=1$site_where", $site_params);');
	expect($src)->not->toContain("db_execute(\"UPDATE mac_track_vlans SET present='0'\")");
	expect($src)->not->toContain("db_execute('UPDATE mac_track_aggregated_ports SET active_last=0')");
});
