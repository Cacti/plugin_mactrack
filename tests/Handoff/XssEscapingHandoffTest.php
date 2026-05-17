<?php

declare(strict_types=1);

describe('XSS escaping handoff contract', function () {
    it('mactrack_view_arp.php has no unescaped site_name in option tags', function () {
        $src = file_get_contents(realpath(__DIR__ . '/../../mactrack_view_arp.php'));
        // raw pattern: } print '>' . $site['site_name'] . '</option>';
        $unescaped = preg_match("/print\s+'>'\\s*\\.\\s*\\\$site\['site_name'\]\\s*\\.\\s*'<\\/option>'/", $src);
        expect($unescaped)->toBe(0, 'site_name must be wrapped in html_escape() before printing');
    });

    it('mactrack_view_arp.php has no unescaped device_name in option tags', function () {
        $src = file_get_contents(realpath(__DIR__ . '/../../mactrack_view_arp.php'));
        $unescaped = preg_match("/print\s+'>'\\s*\\.\\s*\\\$filter_device\['device_name'\]\\s*\\./", $src);
        expect($unescaped)->toBe(0, 'device_name must be wrapped in html_escape() before printing');
    });

    it('mactrack_view_macs.php has no unescaped site_name in option tags', function () {
        $src = file_get_contents(realpath(__DIR__ . '/../../mactrack_view_macs.php'));
        $unescaped = preg_match("/print\s+'>'\\s*\\.\\s*\\\$site\['site_name'\]\\s*\\.\\s*'<\\/option>'/", $src);
        expect($unescaped)->toBe(0);
    });

    it('mactrack_devices.php has no unescaped device_name direct print', function () {
        $src = file_get_contents(realpath(__DIR__ . '/../../mactrack_devices.php'));
        // verify escaped version is present and bare print is absent
        expect($src)->toContain('html_escape($device[\'device_name\'])');
        expect($src)->not->toContain('print $device[\'device_name\']');
    });

    it('lib/mactrack_functions.php has no unescaped description in option tags', function () {
        $src = file_get_contents(realpath(__DIR__ . '/../../lib/mactrack_functions.php'));
        $unescaped = preg_match("/print\s+'>'\\s*\\.\\s*\\\$device_type\['description'\]\\s*\\./", $src);
        expect($unescaped)->toBe(0, 'description must be wrapped in html_escape()');
    });
});
