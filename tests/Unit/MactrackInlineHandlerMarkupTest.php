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
 * Unit coverage for the shared markup in lib/mactrack_functions.php that
 * used to carry inline onClick/onChange attributes: the device-row rescan
 * icon and the sites filter. Handlers are now bound from $(function() {})
 * blocks, so the markup must expose hooks (class, data attribute, element
 * ids) instead of inline handlers.
 */

if (!function_exists('html_escape_request_var')) {
	/**
	 * Test stub standing in for Cacti core's html_escape_request_var(). Returns the HTML-escaped value of a request variable.
	 *
	 * @param string $name Request variable name.
	 * @return string
	 */
	function html_escape_request_var($name) {
		return html_escape(get_request_var($name));
	}
}

if (!function_exists('filter_value')) {
	/**
	 * Test stub standing in for Cacti core's filter_value(). Returns the value without highlighting or linking it.
	 *
	 * @param string $value Cell value.
	 * @param string $filter Unused filter text.
	 * @param string $href Unused link target.
	 * @return string
	 */
	function filter_value($value, $filter, $href = '') {
		return $value;
	}
}

if (!function_exists('get_colored_device_status')) {
	/**
	 * Test stub standing in for Cacti core's get_colored_device_status(). Renders no status markup.
	 *
	 * @param bool|int $disabled Whether the device is disabled.
	 * @param int $status Device status code.
	 * @return string Always an empty string.
	 */
	function get_colored_device_status($disabled, $status) {
		return '';
	}
}

if (!function_exists('number_format_i18n')) {
	/**
	 * Test stub standing in for Cacti core's number_format_i18n(). Records array('number' => ..., 'decimals' => ...) in $GLOBALS['__test_number_format_i18n'] and applies no locale formatting.
	 *
	 * @param int|float $number Number to format.
	 * @param int|null $decimals Requested decimals.
	 * @return string The number cast to a string.
	 */
	function number_format_i18n($number, $decimals = 0) {
		return (string) $number;
	}
}

if (!function_exists('form_checkbox_cell')) {
	/**
	 * Test stub standing in for Cacti core's form_checkbox_cell(). Prints an empty table cell.
	 *
	 * @param string $title Unused row title.
	 * @param int|string $id Unused row id.
	 * @return void
	 */
	function form_checkbox_cell($title, $id) {
		print '<td></td>';
	}
}

if (!function_exists('form_end_row')) {
	/**
	 * Test stub standing in for Cacti core's form_end_row(). Prints a closing row tag.
	 *
	 * @return void
	 */
	function form_end_row() {
		print '</tr>';
	}
}

final class MactrackInlineHandlerMarkupTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('lib/mactrack_functions.php');
	}

	/**
	 * @param array $over
	 *
	 * @return array
	 */
	private function device(array $over = []): array {
		return array_merge([
			'device_id'        => 7,
			'disabled'         => '',
			'device_name'      => 'sw1',
			'site_name'        => 'HQ',
			'snmp_status'      => 3,
			'hostname'         => 'sw1.example.com',
			'device_type'      => 'Switch',
			'scan_type'        => '2',
			'ips_total'        => 1,
			'ports_total'      => 48,
			'ports_active'     => 12,
			'ports_trunk'      => 2,
			'macs_active'      => 30,
			'last_runduration' => 4.2,
		], $over);
	}

	/**
	 * @param array $device
	 *
	 * @return string
	 */
	private function renderRow(array $device): string {
		ob_start();
		mactrack_format_device_row($device, true);

		return (string) ob_get_clean();
	}

	/**
	 * @return void
	 */
	public function test_rescan_icon_uses_class_and_data_attribute_not_onclick(): void {
		$html = $this->renderRow($this->device());

		$this->assertStringContainsString("class='mactrackRescanDevice'", $html);
		$this->assertStringContainsString("data-device-id='7'", $html);
		$this->assertStringNotContainsStringIgnoringCase('onclick', $html);
	}

	/**
	 * @return void
	 */
	public function test_disabled_device_has_no_rescan_icon(): void {
		$html = $this->renderRow($this->device(['disabled' => 'on']));

		$this->assertStringNotContainsString('mactrackRescanDevice', $html);
		$this->assertStringContainsString('view_none.gif', $html);
	}

	/**
	 * @return void
	 */
	public function test_site_filter_has_no_inline_handlers_and_binds_in_ready_block(): void {
		global $item_rows;

		$item_rows = [10 => '10', 20 => '20'];

		mactrack_test_queue_return('db_fetch_assoc', [['site_id' => 1, 'site_name' => 'HQ']]);

		ob_start();
		mactrack_site_filter('mactrack_sites.php');
		$html = (string) ob_get_clean();

		$this->assertDoesNotMatchRegularExpression('/\son(change|click)\s*=/i', $html);

		foreach (['rows', 'site_id', 'device_type_id', 'detail'] as $id) {
			$this->assertStringContainsString("id='$id'", $html);
		}

		$this->assertStringContainsString("\$('#rows, #site_id, #device_type_id').on('change'", $html);
		$this->assertStringContainsString("\$('#detail').on('click'", $html);
	}
}
