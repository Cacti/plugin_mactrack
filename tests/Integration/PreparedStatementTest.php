<?php

declare(strict_types=1);

describe('prepared statement consistency', function () {
    it('has no direct request-variable concatenation into SQL in root PHP files', function () {
        $root       = realpath(__DIR__ . '/../../');
        $violations = [];

        foreach (glob($root . '/*.php') as $file) {
            $source   = file_get_contents($file);
            $basename = basename($file);

            // Only flag lines where a request-input source is directly concatenated into
            // a db_execute/db_fetch call without going through a prepared statement or
            // request-validation wrapper.
            $pattern = '/db_(?:execute|fetch\w*)\s*\(["\'].*\'\s*\.\s*(?:get_(?:n)?filter_request_var|get_request_var|\$_(?:GET|POST|REQUEST))/';
            if (preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $line         = substr_count(substr($source, 0, $match[1]), "\n") + 1;
                    $violations[] = "$basename:$line — " . trim($match[0]);
                }
            }
        }

        expect($violations)->toBeEmpty(
            'Found request-var SQL injection: ' . implode("\n", $violations)
        );
    });

    it('uses db_fetch_assoc_prepared in lib/mactrack_functions.php for device_id query', function () {
        $src = file_get_contents(realpath(__DIR__ . '/../../lib/mactrack_functions.php'));
        expect($src)->toContain('db_fetch_assoc_prepared(');
        expect($src)->toContain('WHERE device_id = ?');
        expect($src)->not->toContain("WHERE device_id='\"");
    });

    it('lib/mactrack_functions.php has no string-concatenated device_id in SELECT queries', function () {
        $src = file_get_contents(realpath(__DIR__ . '/../../lib/mactrack_functions.php'));
        $unsafe = preg_match('/db_fetch_assoc\s*\(\s*["\']SELECT[^"\']*WHERE\s+device_id\s*=\s*["\']/', $src);
        expect($unsafe)->toBe(0, 'device_id must be parameterized via prepared statement');
    });
});
