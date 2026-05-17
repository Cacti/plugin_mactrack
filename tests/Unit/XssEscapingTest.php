<?php

declare(strict_types=1);

describe('XSS escaping at output boundaries', function () {
    $arp    = file_get_contents(realpath(__DIR__ . '/../../mactrack_view_arp.php'));
    $macs   = file_get_contents(realpath(__DIR__ . '/../../mactrack_view_macs.php'));
    $ips    = file_get_contents(realpath(__DIR__ . '/../../mactrack_view_ips.php'));
    $iface  = file_get_contents(realpath(__DIR__ . '/../../mactrack_view_interfaces.php'));
    $dot1x  = file_get_contents(realpath(__DIR__ . '/../../mactrack_view_dot1x.php'));
    $devs   = file_get_contents(realpath(__DIR__ . '/../../mactrack_devices.php'));
    $funcs  = file_get_contents(realpath(__DIR__ . '/../../lib/mactrack_functions.php'));

    it('escapes site_name in mactrack_view_arp.php select option', function () use ($arp) {
        expect($arp)->toContain("html_escape(\$site['site_name'])");
        expect($arp)->not->toContain("'>' . \$site['site_name'] . '</option>'");
    });

    it('escapes device_name and hostname in mactrack_view_arp.php select option', function () use ($arp) {
        expect($arp)->toContain("html_escape(\$filter_device['device_name'])");
        expect($arp)->toContain("html_escape(\$filter_device['hostname'])");
        expect($arp)->not->toContain("'>' . \$filter_device['device_name']");
    });

    it('escapes site_name in mactrack_view_macs.php select option', function () use ($macs) {
        expect($macs)->toContain("html_escape(\$site['site_name'])");
        expect($macs)->not->toContain("'>' . \$site['site_name'] . '</option>'");
    });

    it('escapes device_name and hostname in mactrack_view_macs.php select option', function () use ($macs) {
        expect($macs)->toContain("html_escape(\$filter_device['device_name'])");
        expect($macs)->toContain("html_escape(\$filter_device['hostname'])");
    });

    it('escapes mac address list entry in mactrack_view_macs.php', function () use ($macs) {
        expect($macs)->toContain('html_escape(mactrack_format_mac($mac))');
        expect($macs)->not->toMatch("/'\\.\\s*mactrack_format_mac\\(\\\\\\$mac\\)\\s*\\.'<\\/li>'/");
    });

    it('escapes site_name in mactrack_view_ips.php', function () use ($ips) {
        expect($ips)->toContain("html_escape(\$site['site_name'])");
    });

    it('escapes site_name in mactrack_view_interfaces.php', function () use ($iface) {
        expect($iface)->toContain("html_escape(\$site['site_name'])");
    });

    it('escapes device_name in mactrack_view_interfaces.php', function () use ($iface) {
        expect($iface)->toContain('html_escape($device_name)');
    });

    it('escapes site_name in mactrack_view_dot1x.php', function () use ($dot1x) {
        expect($dot1x)->toContain("html_escape(\$site['site_name'])");
    });

    it('escapes device_name in mactrack_devices.php', function () use ($devs) {
        expect($devs)->toContain("html_escape(\$device['device_name'])");
    });

    it('escapes hostname in mactrack_devices.php', function () use ($devs) {
        expect($devs)->toContain("html_escape(\$device['hostname'])");
    });

    it('escapes site_name in mactrack_devices.php select option', function () use ($devs) {
        expect($devs)->toContain("html_escape(\$site['site_name'])");
    });

    it('escapes site_name in lib/mactrack_functions.php', function () use ($funcs) {
        expect($funcs)->toContain("html_escape(\$site['site_name'])");
    });

    it('escapes device_type description and sysDescr_match in lib/mactrack_functions.php', function () use ($funcs) {
        expect($funcs)->toContain("html_escape(\$device_type['description'])");
        expect($funcs)->toContain("html_escape(\$device_type['sysDescr_match'])");
    });
});
