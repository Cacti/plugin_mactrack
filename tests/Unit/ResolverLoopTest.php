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

require_once dirname(__DIR__) . '/Support/load_function.php';

/*
 * The resolver runs while the poller collects, resolves only the scanned
 * site, and writes only the host name back.
 */
beforeEach(function () {
	$this->flat = preg_replace('/\s+/', ' ', file_get_contents(dirname(__DIR__, 2) . '/mactrack_resolver.php'));
});

it('stops only when the poller is done and nothing is left', function (bool $running, int $left, bool $stop) {
	mactrack_test_load_function('mactrack_resolver.php', 'mactrack_resolver_idle_stop');

	expect(mactrack_resolver_idle_stop($running, $left))->toBe($stop);
})->with([
	'poller done, nothing left'   => [false, 0, true],
	'poller running, nothing yet' => [true, 0, false],
	'poller done, work left'      => [false, 3, false],
	'poller running, work left'   => [true, 3, false],
]);

it('reads the poller row before the pass and stops at the time limit', function () {
	$read  = strpos($this->flat, "\$poller_running = db_fetch_cell('SELECT COUNT(*) FROM mac_track_processes WHERE device_id = -1') > 0;");
	$fetch = strpos($this->flat, '$unresolved_ips = db_fetch_assoc_prepared(');

	expect($read)->toBeInt()->toBeLessThan($fetch);
	expect($this->flat)->toContain('$break = (time() - $start) > $max_script_runtime;');
	expect($this->flat)->toContain('if (mactrack_resolver_idle_stop($poller_running, cacti_sizeof($unresolved_ips))) {');
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
