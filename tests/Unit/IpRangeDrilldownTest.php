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

/*
 * The IP Ranges report links to the MAC Addresses and IP Addresses reports.
 * Their Scan Date filters do not share values: 3 is Aggregated in the MAC
 * Addresses report, but the IP Addresses report only knows 1 (All), 2 (Most
 * Recent) and a literal scan date, so the link that passed 3 opened it with
 * no rows (#453).
 */

/**
 * Returns the scan_date value of the link a report page is opened with.
 *
 * @param string $source The source of mactrack_view_ips.php.
 * @param string $page   The report page the link opens.
 *
 * @return string
 */
function mt_ir_link_scan_date(string $source, string $page): string {
	$start = strpos($source, "'" . $page . "'");

	expect($start)->not->toBeFalse();

	preg_match("/'&scan_date=([^']*)'/", substr($source, (int) $start, 600), $match);

	return $match[1] ?? '';
}

it('opens the IP Addresses report with the Most Recent scan', function () {
	$root = dirname(__DIR__, 2);
	$arp  = file_get_contents($root . '/mactrack_view_arp.php');
	$link = mt_ir_link_scan_date(file_get_contents($root . '/mactrack_view_ips.php'), 'mactrack_view_arp.php');

	expect($link)->toBe('2');
	expect($arp)->toContain("<option value='2'<?php if (get_request_var('scan_date') == '2') {?> selected<?php }?>><?php print __('Most Recent', 'mactrack'); ?></option>");
	expect($arp)->not->toContain("<option value='3'");
});

it('opens the MAC Addresses report with the Aggregated view', function () {
	$root = dirname(__DIR__, 2);
	$macs = file_get_contents($root . '/mactrack_view_macs.php');
	$link = mt_ir_link_scan_date(file_get_contents($root . '/mactrack_view_ips.php'), 'mactrack_view_macs.php');

	expect($link)->toBe('3');
	expect($macs)->toContain("<option value='3'<?php if (get_request_var('scan_date') == '3') {?> selected<?php }?>><?php print __('Aggregated', 'mactrack'); ?></option>");
});

it('defaults the IP Addresses report to the Most Recent scan', function () {
	$arp = file_get_contents(dirname(__DIR__, 2) . '/mactrack_view_arp.php');

	expect($arp)->toContain("'scan_date' => [\n\t\t\t'filter'  => FILTER_CALLBACK,\n\t\t\t'default' => '2',");
});
