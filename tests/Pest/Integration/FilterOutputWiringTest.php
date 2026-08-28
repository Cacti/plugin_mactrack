<?php

it('keeps the filter-output security wiring intact', function () {
	$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../../Integration/test_mactrack_filter_output_wiring.php');
	exec($command, $output, $result);

	expect($result)->toBe(0);
});
