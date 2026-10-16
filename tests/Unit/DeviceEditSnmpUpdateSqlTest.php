<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

it('does not leave a trailing comma before WHERE in any mac_track_devices UPDATE', function () {
	$source = plugin_test_read_source('mactrack_devices.php');

	expect(preg_match('/,\s*WHERE\b/i', $source))->toBe(0);
	expect($source)->toContain("snmp_context = ?\n\t\t\t\t\t\t\t\tWHERE device_id = ?");
});
