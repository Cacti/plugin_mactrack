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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for mactrack_tabs() in lib/mactrack_functions.php: the Dashboard
 * tab must be present and rendered first, ahead of the Sites tab.
 */

final class MactrackTabsTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('lib/mactrack_functions.php');
	}

	/**
	 * @return void
	 */
	public function test_dashboard_tab_renders_first(): void {
		ob_start();
		mactrack_tabs();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString('mactrack_view_dashboard.php', $html);
		$this->assertStringContainsString('Dashboard', $html);
		$this->assertLessThan(
			strpos($html, 'mactrack_view_sites.php'),
			strpos($html, 'mactrack_view_dashboard.php')
		);
	}
}
