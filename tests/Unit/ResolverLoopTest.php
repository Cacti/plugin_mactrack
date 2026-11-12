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
 * The resolver runs while the poller collects, resolves only the scanned
 * site, and writes only the host name back.
 */
beforeEach(function () {
	$this->flat = preg_replace('/\s+/', ' ', file_get_contents(dirname(__DIR__, 2) . '/mactrack_resolver.php'));
});

it('stops only when the poller is done and nothing is left, or at the limit', function () {
	expect($this->flat)->toContain("\$poller_running = db_fetch_cell('SELECT COUNT(*) FROM mac_track_processes WHERE device_id = -1') > 0;");
	expect($this->flat)->toContain('$break = (time() - $start) > $max_script_runtime;');
	expect($this->flat)->toContain("if (cacti_sizeof(\$unresolved_ips) == 0) { if (!\$poller_running) { mactrack_debug('Terminating DNS resolving, nothing to do'); break; }");
	expect($this->flat)->not->toContain('$nothing > 5');
});

it('limits a site scan to that site with a bound id', function () {
	expect($this->flat)->toContain("\$sql_where = 'AND site_id = ?'; \$sql_params = [(int) \$site_id];");
	expect($this->flat)->toContain('AND (dns_hostname = \'\' OR dns_hostname IS NULL) $sql_where", $sql_params);');
	expect($this->flat)->not->toContain("'AND site_id=' . \$site_id");
});

it('writes only the host name, by primary key', function () {
	expect($this->flat)->toContain("db_execute_prepared('UPDATE mac_track_temp_ports SET dns_hostname = ? WHERE port_number = ? AND scan_date = ? AND mac_address = ? AND device_id = ?',");
	expect($this->flat)->not->toContain('vendor_mac = VALUES(vendor_mac)');
	expect($this->flat)->not->toContain('INSERT INTO mac_track_temp_ports');
});
