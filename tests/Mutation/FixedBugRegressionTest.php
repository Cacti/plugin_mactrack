<?php

declare(strict_types=1);

describe('unserialize object injection fix regression', function () {
    it('mactrack_view_macs.php unserialize call includes allowed_classes option', function () {
        $src = file_get_contents(realpath(__DIR__ . '/../../mactrack_view_macs.php'));
        expect($src)->toContain("['allowed_classes' => false]");
    });

    it('the allowed_classes option appears on the same unserialize call as get_nfilter_request_var', function () {
        $src = file_get_contents(realpath(__DIR__ . '/../../mactrack_view_macs.php'));
        $pattern = "/unserialize\s*\(\s*get_nfilter_request_var\s*\([^)]+\)\s*,\s*\['allowed_classes'\s*=>\s*false\]\s*\)/";
        expect((bool) preg_match($pattern, $src))->toBeTrue('allowed_classes must be on the same unserialize call');
    });

    it('has no unserialize call without allowed_classes on user input', function () {
        $src    = file_get_contents(realpath(__DIR__ . '/../../mactrack_view_macs.php'));
        $unsafe = preg_match_all(
            "/unserialize\s*\(\s*get_nfilter_request_var\s*\([^)]+\)\s*\)(?!\s*;?\s*\/\/\s*nosemgrep)/",
            $src
        );
        expect($unsafe)->toBe(0);
    });
});

describe('passthru integer injection fix regression', function () {
    $funcs = file_get_contents(realpath(__DIR__ . '/../../lib/mactrack_functions.php'));

    it('device rescan does not concatenate raw device_id string into command', function () use ($funcs) {
        // Before fix: $extra_args = ' -id=' . $dbinfo['device_id'] concatenated as string
        expect($funcs)->not->toContain("'-id=' . \$dbinfo['device_id']");
    });

    it('site scan does not concatenate raw site_id string into command', function () use ($funcs) {
        // Before fix: ' -sid=' . $dbinfo['site_id'] (uncast)
        expect($funcs)->not->toContain("'-sid=' . \$dbinfo['site_id']");
    });

    it('device rescan uses int cast for device_id', function () use ($funcs) {
        expect($funcs)->toContain('(int)$dbinfo[\'device_id\']');
    });

    it('site scan uses int cast for site_id', function () use ($funcs) {
        expect($funcs)->toContain('(int)$dbinfo[\'site_id\']');
    });
});

describe('SQL prepared statement fix regression', function () {
    $funcs = file_get_contents(realpath(__DIR__ . '/../../lib/mactrack_functions.php'));

    it('mac_track_interfaces query uses prepared statement', function () use ($funcs) {
        expect($funcs)->toContain('db_fetch_assoc_prepared(');
        // the old raw-concat form must be gone
        expect($funcs)->not->toContain('mac_track_interfaces WHERE device_id=');
    });

    it('mac_track_interfaces query uses ? placeholder', function () use ($funcs) {
        expect($funcs)->toMatch('/db_fetch_assoc_prepared\s*\(\s*\'SELECT \* FROM mac_track_interfaces WHERE device_id = \?/');
    });
});

describe('XSS fix regression', function () {
    it('html_escape applied to site_name in all five view files', function () {
        $viewFiles = [
            'mactrack_view_arp.php',
            'mactrack_view_macs.php',
            'mactrack_view_ips.php',
            'mactrack_view_interfaces.php',
            'mactrack_view_dot1x.php',
        ];
        $root    = realpath(__DIR__ . '/../../');
        $missing = [];
        foreach ($viewFiles as $f) {
            $src = file_get_contents("$root/$f");
            if (!str_contains($src, "html_escape(\$site['site_name'])")) {
                $missing[] = $f;
            }
        }
        expect($missing)->toBeEmpty('missing html_escape for site_name: ' . implode(', ', $missing));
    });

    it('html_escape applied to device_name and hostname in arp and macs views', function () {
        $root    = realpath(__DIR__ . '/../../');
        $missing = [];
        foreach (['mactrack_view_arp.php', 'mactrack_view_macs.php'] as $f) {
            $src = file_get_contents("$root/$f");
            if (!str_contains($src, "html_escape(\$filter_device['device_name'])")) {
                $missing[] = "$f:device_name";
            }
            if (!str_contains($src, "html_escape(\$filter_device['hostname'])")) {
                $missing[] = "$f:hostname";
            }
        }
        expect($missing)->toBeEmpty('missing html_escape: ' . implode(', ', $missing));
    });
});
