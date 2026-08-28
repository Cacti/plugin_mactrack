<?php

it('does not reintroduce raw filter labels into rendered endpoints', function () {
	$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../../e2e/test_mactrack_no_raw_filter_labels.php');
	exec($command, $output, $result);

	expect($result)->toBe(0);
});
