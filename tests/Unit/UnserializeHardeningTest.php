<?php

declare(strict_types=1);

describe('unserialize hardening', function () {
    $macs = file_get_contents(realpath(__DIR__ . '/../../mactrack_view_macs.php'));

    it('uses allowed_classes:false on all unserialize calls with user input', function () use ($macs) {
        $safe = preg_match_all(
            "/unserialize\s*\(\s*get_nfilter_request_var\s*\([^)]+\)\s*,\s*\['allowed_classes'\s*=>\s*false\]\s*\)/",
            $macs,
            $safeMatches
        );
        $bare = preg_match_all(
            "/unserialize\s*\(\s*get_nfilter_request_var\s*\([^)]+\)\s*\)/",
            $macs,
            $bareMatches
        );
        expect($safe)->toBeGreaterThanOrEqual(1, 'at least one safe unserialize with allowed_classes:false');
        expect($bare)->toBe(0, 'no bare unserialize of user input without allowed_classes');
    });

    it('does not have any bare unserialize of get_nfilter_request_var across the codebase', function () {
        $phpFiles = glob(realpath(__DIR__ . '/../../') . '/*.php');
        $phpFiles = array_merge($phpFiles, glob(realpath(__DIR__ . '/../../lib/') . '/*.php'));

        $violations = [];

        foreach ($phpFiles as $file) {
            $source = file_get_contents($file);
            if (preg_match('/unserialize\s*\(\s*get_nfilter_request_var/', $source) &&
                !preg_match("/unserialize\s*\([^,]+,\s*\['allowed_classes'\s*=>\s*false\]/", $source)) {
                $violations[] = basename($file);
            }
        }

        expect($violations)->toBeEmpty('these files have unsafe unserialize: ' . implode(', ', $violations));
    });

    it('uses sanitize_unserialize_selected_items for integer id arrays', function () use ($macs) {
        expect($macs)->toContain('sanitize_unserialize_selected_items');
    });
});
