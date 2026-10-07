<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for mactrack_legend() in lib/mactrack_functions.php: it renders
 * one solid-colour chip per state inside a .mactrackLegend container carrying a
 * --mactrack-chip-min variable sized to the longest label. html_start_box() and
 * html_end_box() are no-op stubs from the bootstrap, so only the chip markup
 * reaches the output buffer.
 */
final class MactrackLegendTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('lib/mactrack_functions.php');
	}

	/**
	 * @return void
	 */
	public function test_mactrack_legend_renders_equal_width_chips(): void {
		$items = [
			'int_up'     => 'Interface Up',
			'int_errors' => 'Errors Present',
			'int_down'   => 'Interface Down',
		];

		ob_start();

		try {
			mactrack_legend($items);
		} finally {
			$output = ob_get_clean();
		}

		$this->assertStringContainsString('<div class="mactrackLegend" style="--mactrack-chip-min: calc(', $output);
		$this->assertSame(count($items), substr_count($output, 'mactrackLegendItem'));

		foreach ($items as $class => $label) {
			$this->assertStringContainsString('<div class="mactrackLegendItem ' . $class . '">' . $label . '</div>', $output);
		}
	}
}
