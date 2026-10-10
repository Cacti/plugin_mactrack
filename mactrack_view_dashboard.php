<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

$guest_account = true;

chdir('../../');
require('./include/auth.php');
require_once('./plugins/mactrack/lib/mactrack_functions.php');

$title = __('MacTrack - Dashboard', 'mactrack');

// AJAX card/layout endpoints used by the shared card chrome (js/cards.js).
if (isset_request_var('action') && get_nfilter_request_var('action') == 'dash_card') {
	header('Content-Type: application/json');
	print mactrack_dashboard_card_ajax();

	exit;
} elseif (isset_request_var('action') && get_nfilter_request_var('action') == 'dash_layout') {
	header('Content-Type: application/json');
	print mactrack_dashboard_layout_save();

	exit;
}

general_header();
mactrack_dashboard();
bottom_footer();

/**
 * The dashboard's card catalogue: each key maps to a title, a grid column
 * span (out of 8) and whether the card can expand to show extra detail.
 *
 * @return array Ordered map of card key => definition.
 */
function mactrack_dashboard_card_meta(): array {
	return [
		'inventory'      => ['title' => __('Inventory', 'mactrack'),                'span' => 2, 'expandable' => false],
		'device_status'  => ['title' => __('Device status', 'mactrack'),            'span' => 2, 'expandable' => false],
		'address_totals' => ['title' => __('Address and port totals', 'mactrack'),  'span' => 2, 'expandable' => false],
		'settings'       => ['title' => __('Collection settings', 'mactrack'),      'span' => 2, 'expandable' => false],
		'scan_runtimes'  => ['title' => __('Scan runtimes', 'mactrack'),            'span' => 4, 'expandable' => true],
		'table_sizes'    => ['title' => __('Table sizes', 'mactrack'),              'span' => 4, 'expandable' => true],
		'macwatch'       => ['title' => __('MacWatch activity', 'mactrack'),        'span' => 2, 'expandable' => false],
		'vendors'        => ['title' => __('Vendors', 'mactrack'),                  'span' => 2, 'expandable' => true],
	];
}

/**
 * The card keys available in this environment, in default order.
 *
 * @return string[]
 */
function mactrack_dashboard_available_cards(): array {
	return array_keys(mactrack_dashboard_card_meta());
}

/**
 * Formats an integer for display with thousands separators.
 *
 * @param mixed $value The raw value.
 *
 * @return string The formatted count.
 */
function mactrack_dashboard_count($value): string {
	return number_format((float) $value, 0);
}

/**
 * Formats a byte count into a human-readable size (KB/MB/GB).
 *
 * @param mixed $bytes The raw byte count.
 *
 * @return string The formatted size.
 */
function mactrack_dashboard_bytes($bytes): string {
	$bytes = (float) $bytes;
	$units = ['B', 'KB', 'MB', 'GB', 'TB'];
	$i     = 0;

	while ($bytes >= 1024 && $i < count($units) - 1) {
		$bytes /= 1024;
		$i++;
	}

	return round($bytes, $i < 2 ? 0 : 1) . ' ' . $units[$i];
}

/**
 * Formats a duration in seconds as a compact human string.
 *
 * @param mixed $seconds The raw seconds.
 *
 * @return string The formatted duration.
 */
function mactrack_dashboard_seconds($seconds): string {
	$seconds = (int) $seconds;

	if ($seconds <= 0) {
		return '0s';
	}

	if ($seconds < 60) {
		return $seconds . 's';
	}

	$minutes = intdiv($seconds, 60);
	$rest    = $seconds % 60;

	if ($minutes < 60) {
		return $minutes . 'm ' . $rest . 's';
	}

	$hours   = intdiv($minutes, 60);
	$minutes = $minutes % 60;

	return $hours . 'h ' . $minutes . 'm';
}

/**
 * Formats a timestamp for display, or a dash when empty.
 *
 * @param mixed $value The raw timestamp.
 *
 * @return string The formatted time, or '—' when empty.
 */
function mactrack_dashboard_time($value): string {
	$value = (string) $value;

	if ($value === '' || $value === '0000-00-00 00:00:00' || $value === null) {
		return '—';
	}

	return $value;
}

/**
 * Builds a two-column key/value table row for a card body.
 *
 * @param string $label The row label.
 * @param string $value The already-formatted value.
 *
 * @return string The <tr> markup.
 */
function mactrack_dashboard_kv_row($label, $value): string {
	return '<tr><th class="mtdashKvLabel">' . html_escape($label) . '</th><td>' . html_escape($value) . '</td></tr>';
}

/**
 * Builds a labelled proportion bar for a card body.
 *
 * @param string $label   The bar label.
 * @param int    $value   The value represented.
 * @param int    $max     The maximum (full-width) value.
 * @param string $state   Severity class suffix (up/warn/down/none).
 *
 * @return string The bar markup.
 */
function mactrack_dashboard_bar($label, $value, $max, $state = 'none'): string {
	$pct = ($max > 0) ? max(0, min(100, round(($value / $max) * 100))) : 0;

	return '<div class="mtdashBarRow">'
		. '<div class="mtdashBarHead"><span>' . html_escape($label) . '</span><span>' . html_escape(mactrack_dashboard_count($value)) . '</span></div>'
		. '<div class="mtdashBar"><span class="mtdashBarFill mtdash-' . html_escape($state) . '" style="width:' . $pct . '%"></span></div>'
		. '</div>';
}

/**
 * Request-scoped device/site/address aggregate counts.
 *
 * @return array The aggregate counts.
 */
function mactrack_dashboard_counts(): array {
	static $counts = null;

	if ($counts !== null) {
		return $counts;
	}

	$row = db_fetch_row('SELECT
		COUNT(*) AS devices,
		SUM(disabled = "") AS enabled,
		SUM(disabled = "on") AS disabled,
		SUM(disabled = "" AND snmp_status = 3) AS up,
		SUM(disabled = "" AND snmp_status = 1) AS down,
		SUM(disabled = "" AND snmp_status = 0) AS unknown,
		SUM(disabled = "" AND snmp_status = 4) AS error,
		SUM(ports_total) AS ports_total,
		SUM(ports_active) AS ports_active,
		SUM(ports_trunk) AS ports_trunk,
		SUM(macs_active) AS macs_active,
		SUM(ips_total) AS ips_total
		FROM mac_track_devices');

	$counts = array_map('intval', is_array($row) ? $row : []);

	$counts['sites']        = (int) db_fetch_cell('SELECT COUNT(*) FROM mac_track_sites');
	$counts['device_types'] = (int) db_fetch_cell('SELECT COUNT(*) FROM mac_track_device_types');
	$counts['interfaces']   = (int) db_fetch_cell('SELECT COUNT(*) FROM mac_track_interfaces');
	$counts['oui']          = (int) db_fetch_cell('SELECT COUNT(*) FROM mac_track_oui_database');

	return $counts;
}

/**
 * Renders the inner HTML body for a single dashboard card.
 *
 * @param string $key The card key.
 *
 * @return string The card body HTML.
 */
function mactrack_dashboard_card_body(string $key): string {
	ob_start();

	switch ($key) {
		case 'inventory':
			$c = mactrack_dashboard_counts();
			print '<table class="mtdashTable mtdashKv"><tbody>';
			print mactrack_dashboard_kv_row(__('Sites', 'mactrack'), mactrack_dashboard_count($c['sites']));
			print mactrack_dashboard_kv_row(__('Devices', 'mactrack'), mactrack_dashboard_count($c['devices'] ?? 0));
			print mactrack_dashboard_kv_row(__('Enabled devices', 'mactrack'), mactrack_dashboard_count($c['enabled'] ?? 0));
			print mactrack_dashboard_kv_row(__('Disabled devices', 'mactrack'), mactrack_dashboard_count($c['disabled'] ?? 0));
			print mactrack_dashboard_kv_row(__('Device types', 'mactrack'), mactrack_dashboard_count($c['device_types']));
			print mactrack_dashboard_kv_row(__('Interfaces tracked', 'mactrack'), mactrack_dashboard_count($c['interfaces']));
			print '</tbody></table>';

			break;
		case 'device_status':
			$c   = mactrack_dashboard_counts();
			$max = max(1, (int) ($c['enabled'] ?? 0));
			print '<div class="mtdashBars">';
			print mactrack_dashboard_bar(__('Up', 'mactrack'), (int) ($c['up'] ?? 0), $max, 'up');
			print mactrack_dashboard_bar(__('Down', 'mactrack'), (int) ($c['down'] ?? 0), $max, 'down');
			print mactrack_dashboard_bar(__('Unknown', 'mactrack'), (int) ($c['unknown'] ?? 0), $max, 'warn');
			print mactrack_dashboard_bar(__('Error', 'mactrack'), (int) ($c['error'] ?? 0), $max, 'warn');
			print mactrack_dashboard_bar(__('Disabled', 'mactrack'), (int) ($c['disabled'] ?? 0), max(1, (int) ($c['devices'] ?? 1)), 'none');
			print '</div>';

			break;
		case 'address_totals':
			$c = mactrack_dashboard_counts();
			print '<table class="mtdashTable mtdashKv"><tbody>';
			print mactrack_dashboard_kv_row(__('Active MACs', 'mactrack'), mactrack_dashboard_count($c['macs_active'] ?? 0));
			print mactrack_dashboard_kv_row(__('IP addresses', 'mactrack'), mactrack_dashboard_count($c['ips_total'] ?? 0));
			print mactrack_dashboard_kv_row(__('Total ports', 'mactrack'), mactrack_dashboard_count($c['ports_total'] ?? 0));
			print mactrack_dashboard_kv_row(__('Active ports', 'mactrack'), mactrack_dashboard_count($c['ports_active'] ?? 0));
			print mactrack_dashboard_kv_row(__('Trunk ports', 'mactrack'), mactrack_dashboard_count($c['ports_trunk'] ?? 0));
			print mactrack_dashboard_kv_row(__('Unique OUI vendors', 'mactrack'), mactrack_dashboard_count($c['oui']));
			print '</tbody></table>';

			break;
		case 'settings':
			$frequency  = read_config_option('mt_collection_timing');
			print '<table class="mtdashTable mtdashKv"><tbody>';
			print mactrack_dashboard_kv_row(__('Scanning frequency', 'mactrack'), $frequency === 'disabled' || $frequency === '' ? __('Disabled', 'mactrack') : $frequency . ' ' . __('minutes', 'mactrack'));
			print mactrack_dashboard_kv_row(__('Concurrent processes', 'mactrack'), (string) (read_config_option('mt_processes') ?: '—'));
			print mactrack_dashboard_kv_row(__('Scanner max runtime', 'mactrack'), (read_config_option('mt_script_runtime') ?: '—') . ' ' . __('minutes', 'mactrack'));
			print mactrack_dashboard_kv_row(__('MAC retention', 'mactrack'), (read_config_option('mt_data_retention') ?: '—') . ' ' . __('days', 'mactrack'));
			print mactrack_dashboard_kv_row(__('IP retention', 'mactrack'), (read_config_option('mt_data_retention_ip') ?: '—') . ' ' . __('days', 'mactrack'));
			print mactrack_dashboard_kv_row(__('Reverse DNS', 'mactrack'), read_config_option('mt_reverse_dns') != '' ? __('Enabled', 'mactrack') : __('Disabled', 'mactrack'));
			print '</tbody></table>';

			break;
		case 'scan_runtimes':
			$run = db_fetch_row('SELECT
				MAX(last_rundate) AS last_scan,
				AVG(last_runduration) AS avg_dur,
				MAX(last_runduration) AS max_dur,
				SUM(last_rundate >= DATE_SUB(NOW(), INTERVAL 1 HOUR)) AS last_hour
				FROM mac_track_devices
				WHERE disabled = ""');

			print '<table class="mtdashTable mtdashKv"><tbody>';
			print mactrack_dashboard_kv_row(__('Most recent scan', 'mactrack'), mactrack_dashboard_time($run['last_scan'] ?? ''));
			print mactrack_dashboard_kv_row(__('Average duration', 'mactrack'), mactrack_dashboard_seconds($run['avg_dur'] ?? 0));
			print mactrack_dashboard_kv_row(__('Longest duration', 'mactrack'), mactrack_dashboard_seconds($run['max_dur'] ?? 0));
			print mactrack_dashboard_kv_row(__('Scanned in last hour', 'mactrack'), mactrack_dashboard_count($run['last_hour'] ?? 0));
			print '</tbody></table>';

			$slow = db_fetch_assoc('SELECT device_name, last_runduration, last_rundate
				FROM mac_track_devices
				WHERE disabled = "" AND last_runduration > 0
				ORDER BY last_runduration DESC
				LIMIT 8');

			print '<table class="mtdashTable"><thead><tr><th>' . __esc('Slowest devices', 'mactrack') . '</th><th>' . __esc('Duration', 'mactrack') . '</th><th>' . __esc('Last scan', 'mactrack') . '</th></tr></thead><tbody>';

			if (cacti_sizeof($slow)) {
				foreach ($slow as $d) {
					print '<tr><th scope="row">' . html_escape($d['device_name']) . '</th><td>' . html_escape(mactrack_dashboard_seconds($d['last_runduration'])) . '</td><td>' . html_escape(mactrack_dashboard_time($d['last_rundate'])) . '</td></tr>';
				}
			} else {
				print '<tr><td colspan="3" class="mtdashEmpty">' . __esc('No scan runtimes recorded yet.', 'mactrack') . '</td></tr>';
			}

			print '</tbody></table>';

			break;
		case 'table_sizes':
			$tables = db_fetch_assoc('SELECT TABLE_NAME AS name, TABLE_ROWS AS rows,
				(DATA_LENGTH + INDEX_LENGTH) AS bytes
				FROM information_schema.TABLES
				WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE "mac\\_track%"
				ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC
				LIMIT 12');

			print '<table class="mtdashTable"><thead><tr><th>' . __esc('Table', 'mactrack') . '</th><th>' . __esc('Rows', 'mactrack') . '</th><th>' . __esc('Size', 'mactrack') . '</th></tr></thead><tbody>';

			if (cacti_sizeof($tables)) {
				$max = 1;
				foreach ($tables as $t) {
					$max = max($max, (int) $t['bytes']);
				}

				foreach ($tables as $t) {
					$pct = round(((int) $t['bytes'] / $max) * 100);
					print '<tr><th scope="row">' . html_escape($t['name']) . '</th>'
						. '<td>' . html_escape(mactrack_dashboard_count($t['rows'])) . '</td>'
						. '<td><div class="mtdashInlineBar"><span class="mtdashBarFill mtdash-none" style="width:' . $pct . '%"></span></div>' . html_escape(mactrack_dashboard_bytes($t['bytes'])) . '</td></tr>';
				}
			} else {
				print '<tr><td colspan="3" class="mtdashEmpty">' . __esc('No table statistics available.', 'mactrack') . '</td></tr>';
			}

			print '</tbody></table>';

			break;
		case 'macwatch':
			$mw = db_fetch_row('SELECT COUNT(*) AS entries,
				SUM(discovered) AS discovered,
				MAX(date_last_seen) AS last_seen
				FROM mac_track_macwatch');

			print '<table class="mtdashTable mtdashKv"><tbody>';
			print mactrack_dashboard_kv_row(__('Watched MACs', 'mactrack'), mactrack_dashboard_count($mw['entries'] ?? 0));
			print mactrack_dashboard_kv_row(__('Discovered', 'mactrack'), mactrack_dashboard_count($mw['discovered'] ?? 0));
			print mactrack_dashboard_kv_row(__('Last match', 'mactrack'), mactrack_dashboard_time($mw['last_seen'] ?? ''));
			print '</tbody></table>';

			break;
		case 'vendors':
			$c = mactrack_dashboard_counts();
			print '<table class="mtdashTable mtdashKv"><tbody>';
			print mactrack_dashboard_kv_row(__('OUI database entries', 'mactrack'), mactrack_dashboard_count($c['oui']));
			print '</tbody></table>';

			$top = db_fetch_assoc('SELECT dt.vendor AS vendor, COUNT(*) AS devices
				FROM mac_track_devices AS d
				INNER JOIN mac_track_device_types AS dt
				ON d.device_type_id = dt.device_type_id
				WHERE dt.vendor != ""
				GROUP BY dt.vendor
				ORDER BY devices DESC
				LIMIT 8');

			print '<table class="mtdashTable"><thead><tr><th>' . __esc('Vendor', 'mactrack') . '</th><th>' . __esc('Devices', 'mactrack') . '</th></tr></thead><tbody>';

			if (cacti_sizeof($top)) {
				foreach ($top as $v) {
					print '<tr><th scope="row">' . html_escape($v['vendor']) . '</th><td>' . html_escape(mactrack_dashboard_count($v['devices'])) . '</td></tr>';
				}
			} else {
				print '<tr><td colspan="2" class="mtdashEmpty">' . __esc('No vendor data available.', 'mactrack') . '</td></tr>';
			}

			print '</tbody></table>';

			break;
	}

	return (string) ob_get_clean();
}

/**
 * Renders a complete dashboard card (header + tools + body) for the given key.
 *
 * @param string $key      The card key.
 * @param bool   $expanded Whether the card starts expanded.
 *
 * @return string The card HTML, or '' when the key is unknown.
 */
function mactrack_dashboard_render_card(string $key, bool $expanded = false): string {
	$meta = mactrack_dashboard_card_meta();

	if (!isset($meta[$key])) {
		return '';
	}

	$def = $meta[$key];

	$tools = '';

	if ($def['expandable']) {
		$tools .= '<button type="button" class="mtdashCardTool" data-tool="expand" aria-label="' . __esc('Show more', 'mactrack') . '" title="' . __esc('Show more', 'mactrack') . '"><i class="fa fa-chevron-down" aria-hidden="true"></i></button>';
		$tools .= '<button type="button" class="mtdashCardTool" data-tool="collapse" aria-label="' . __esc('Show less', 'mactrack') . '" title="' . __esc('Show less', 'mactrack') . '"><i class="fa fa-chevron-up" aria-hidden="true"></i></button>';
	}

	$tools .= '<button type="button" class="mtdashCardTool" data-tool="maximize" aria-label="' . __esc('Open in a dialog', 'mactrack') . '" title="' . __esc('Open in a dialog', 'mactrack') . '"><i class="fa fa-window-maximize" aria-hidden="true"></i></button>';
	$tools .= '<button type="button" class="mtdashCardTool" data-tool="refresh" aria-label="' . __esc('Refresh', 'mactrack') . '" title="' . __esc('Refresh', 'mactrack') . '"><i class="fa fa-sync-alt" aria-hidden="true"></i></button>';
	$tools .= '<button type="button" class="mtdashCardTool" data-tool="remove" aria-label="' . __esc('Remove from page', 'mactrack') . '" title="' . __esc('Remove from page', 'mactrack') . '"><i class="fa fa-times" aria-hidden="true"></i></button>';

	$classes = 'mtdashCard mtdashSpan' . (int) $def['span'];

	if ($def['expandable']) {
		$classes .= ' mtdashCardExpandable';
	}

	if ($expanded) {
		$classes .= ' mtdashCardExpanded';
	}

	return '<section class="' . $classes . '" data-card="' . html_escape($key) . '">'
		. '<header class="mtdashCardHeader">'
		. '<button type="button" class="mtdashCardDrag" aria-label="' . __esc('Drag to reorder card', 'mactrack') . '"><i class="fa fa-bars" aria-hidden="true"></i></button>'
		. '<h2 class="mtdashCardTitle">' . html_escape($def['title']) . '</h2>'
		. '<span class="mtdashCardTools">' . $tools . '</span>'
		. '</header>'
		. '<div class="mtdashCardBody">' . mactrack_dashboard_card_body($key) . '</div>'
		. '</section>';
}

/**
 * The current user's saved dashboard layout (present cards in order plus
 * expanded state). With no saved layout every card is present in default order.
 *
 * @return array{order: string[], expanded: array<string, bool>}
 */
function mactrack_dashboard_layout(): array {
	$available = mactrack_dashboard_available_cards();
	$saved     = json_decode((string) read_user_setting('mactrack_dashboard_layout', '', true), true);

	if (!is_array($saved) || !isset($saved['order']) || !is_array($saved['order'])) {
		return ['order' => $available, 'expanded' => []];
	}

	$order = [];

	foreach ($saved['order'] as $key) {
		if (is_string($key) && in_array($key, $available, true) && !in_array($key, $order, true)) {
			$order[] = $key;
		}
	}

	$expanded = [];

	if (isset($saved['expanded']) && is_array($saved['expanded'])) {
		foreach ($saved['expanded'] as $key => $on) {
			if ($on && in_array($key, $available, true)) {
				$expanded[(string) $key] = true;
			}
		}
	}

	return ['order' => $order, 'expanded' => $expanded];
}

/**
 * Persists the posted dashboard layout (card order and expanded state) for the
 * current user in a single settings_user row, validated to available cards.
 *
 * @return string A JSON status document.
 */
function mactrack_dashboard_layout_save(): string {
	if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !function_exists('csrf_check') || !csrf_check(false)) {
		return (string) json_encode(['error' => __('Invalid request. Please try again.', 'mactrack')]);
	}

	$available = mactrack_dashboard_available_cards();
	$posted    = json_decode(isset_request_var('layout') ? (string) get_nfilter_request_var('layout') : '', true);

	$order    = [];
	$expanded = [];

	if (is_array($posted)) {
		if (isset($posted['order']) && is_array($posted['order'])) {
			foreach ($posted['order'] as $key) {
				if (is_string($key) && in_array($key, $available, true) && !in_array($key, $order, true)) {
					$order[] = $key;
				}
			}
		}

		if (isset($posted['expanded']) && is_array($posted['expanded'])) {
			foreach ($posted['expanded'] as $key => $on) {
				if ($on && is_string($key) && in_array($key, $available, true)) {
					$expanded[$key] = true;
				}
			}
		}
	}

	set_user_setting('mactrack_dashboard_layout', json_encode(['order' => $order, 'expanded' => $expanded]));

	return (string) json_encode(['ok' => true]);
}

/**
 * AJAX: render a single dashboard card, used when adding one from the
 * catalogue or refreshing one in place.
 *
 * @return string A JSON document with the card key and rendered HTML.
 */
function mactrack_dashboard_card_ajax(): string {
	if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !function_exists('csrf_check') || !csrf_check(false)) {
		return (string) json_encode(['error' => __('Invalid request. Please try again.', 'mactrack')]);
	}

	$key = isset_request_var('card') ? (string) get_nfilter_request_var('card') : '';

	if (!in_array($key, mactrack_dashboard_available_cards(), true)) {
		return (string) json_encode(['error' => __('Unknown card.', 'mactrack')]);
	}

	$expanded = isset_request_var('expanded') && get_nfilter_request_var('expanded') === '1';

	return (string) json_encode(['card' => $key, 'html' => mactrack_dashboard_render_card($key, $expanded)]);
}

/**
 * Renders the MacTrack Dashboard tab: the add-card toolbar and the grid of
 * admin status cards.
 *
 * @return void
 */
function mactrack_dashboard(): void {
	mactrack_tabs();

	$layout = mactrack_dashboard_layout();
	$meta   = mactrack_dashboard_card_meta();
	$absent = array_values(array_diff(mactrack_dashboard_available_cards(), $layout['order']));

	print '<div id="mactrack_dashboard_panel" class="mtdashPanel">';

	print '<div class="mtdashToolbar">';
	print '<label class="mtdashToolbarLabel" for="mactrack_dashboard_add">' . __esc('Add card', 'mactrack') . '</label>';
	print '<select id="mactrack_dashboard_add" class="mtdashAdd"><option value="">' . __esc('Add a dashboard card…', 'mactrack') . '</option>';

	foreach ($absent as $key) {
		print '<option value="' . html_escape($key) . '">' . html_escape($meta[$key]['title']) . '</option>';
	}

	print '</select>';
	print '</div>';

	print '<div id="mactrack_dashboard" class="mtdashGrid">';

	foreach ($layout['order'] as $key) {
		print mactrack_dashboard_render_card($key, !empty($layout['expanded'][$key]));
	}

	print '</div>';

	// Modal container reused by the maximize tool.
	print '<div id="mactrack_dashboard_dialog" class="mtdashDialog" style="display:none"></div>';

	print '</div>';

	print "<script type='text/javascript' " . plugin_mactrack_csp_nonce() . ">initMactrackDashboard();</script>";
}
