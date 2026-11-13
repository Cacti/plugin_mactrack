<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * mactrack_require_core_function() loads lib/poller.php for the upgrade
 * launch, which runs from the config_settings hook before core loads it.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

describe('mactrack_require_core_function', function () {
	beforeEach(function () {
		$this->base = sys_get_temp_dir() . '/mactrack-core-' . getmypid();

		if (!is_dir($this->base . '/lib')) {
			mkdir($this->base . '/lib', 0700, true);
		}
		$this->saved                    = $GLOBALS['config']['base_path'];
		$GLOBALS['config']['base_path'] = $this->base;
	});

	afterEach(function () {
		$GLOBALS['config']['base_path'] = $this->saved;

		if (is_file($this->base . '/lib/fixture.php')) {
			unlink($this->base . '/lib/fixture.php');
		}

		rmdir($this->base . '/lib');
		rmdir($this->base);
	});

	it('loads the library when the function is not defined yet', function () {
		file_put_contents($this->base . '/lib/fixture.php', '<?php function mactrack_test_core_fixture() { return 1; }');

		expect(function_exists('mactrack_test_core_fixture'))->toBeFalse();

		mactrack_require_core_function('mactrack_test_core_fixture', 'fixture.php');

		expect(function_exists('mactrack_test_core_fixture'))->toBeTrue();
	});

	it('leaves an already defined function alone', function () {
		// No lib/poller.php exists under the fixture root, so an include here would warn.
		mactrack_require_core_function('exec_background', 'poller.php');

		expect(function_exists('exec_background'))->toBeTrue();
	});
});
