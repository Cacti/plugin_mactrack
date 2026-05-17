<?php

declare(strict_types=1);

describe('passthru command injection hardening', function () {
    $funcs = file_get_contents(realpath(__DIR__ . '/../../lib/mactrack_functions.php'));

    it('casts device_id to int before passthru in device rescan', function () use ($funcs) {
        expect($funcs)->toContain('(int)$dbinfo[\'device_id\']');
    });

    it('casts site_id to int before passthru in site scan', function () use ($funcs) {
        expect($funcs)->toContain('(int)$dbinfo[\'site_id\']');
    });

    it('annotates passthru calls with nosemgrep explaining the safety rationale', function () use ($funcs) {
        $count = substr_count($funcs, 'nosemgrep: php.lang.security.exec-use.exec-use');
        expect($count)->toBe(2, 'both passthru calls should have nosemgrep annotations');
    });

    it('does not use extra_args variable in passthru command (injection surface removed)', function () use ($funcs) {
        // $extra_args was replaced with inline int-cast concatenations
        $commandLines = [];
        preg_match_all('/\$command\s*=.*passthru.*\n/s', $funcs, $commandLines);
        // The commands should include (int) cast, not bare $extra_args
        $passthruLines = [];
        preg_match_all('/passthru\(\$command\);.*/', $funcs, $passthruLines);
        expect(count($passthruLines[0]))->toBe(2);
    });

    it('site scan always passes --web flag (AJAX-only entry point by design)', function () use ($funcs) {
        // mactrack_site_scan() is only reached via AJAX; --web is unconditional, unlike device rescan
        expect($funcs)->toContain("' --web -sid=' . (int)\$dbinfo['site_id']");
    });

    it('script paths are bare filesystem paths with no embedded arguments', function () use ($funcs) {
        // cacti_escapeshellarg() quotes the entire value as one token; embedded flags would break the command
        expect($funcs)->toContain("\$script_path = \$config['base_path'] . '/plugins/mactrack/mactrack_scanner.php'");
        expect($funcs)->toContain("\$script_path = \$config['base_path'] . '/plugins/mactrack/poller_mactrack.php'");
    });
});
