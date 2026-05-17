<?php

declare(strict_types=1);

describe('mactrack setup.php structure', function () {
    $setup = file_get_contents(realpath(__DIR__ . '/../../setup.php'));

    it('defines plugin_mactrack_install function', function () use ($setup) {
        expect($setup)->toContain('function plugin_mactrack_install');
    });

    it('defines plugin_mactrack_version function', function () use ($setup) {
        expect($setup)->toContain('function plugin_mactrack_version');
    });

    it('defines plugin_mactrack_uninstall function', function () use ($setup) {
        expect($setup)->toContain('function plugin_mactrack_uninstall');
    });

    it('registers hooks in install function', function () use ($setup) {
        expect($setup)->toContain('api_plugin_register_hook');
    });

    it('reads version from INFO ini file', function () use ($setup) {
        expect($setup)->toContain('parse_ini_file');
    });

    it('has INFO file with required fields', function () {
        $info = parse_ini_file(realpath(__DIR__ . '/../../INFO'));
        expect($info)->toHaveKey('name');
        expect($info)->toHaveKey('version');
        expect($info['name'])->toBe('mactrack');
    });
});

describe('mactrack required entry points', function () {
    $setupSource = file_get_contents(realpath(__DIR__ . '/../../setup.php'));

    it('defines plugin_mactrack_check_config', function () use ($setupSource) {
        expect($setupSource)->toContain('function plugin_mactrack_check_config');
    });

    it('defines plugin_mactrack_upgrade', function () use ($setupSource) {
        expect($setupSource)->toContain('function plugin_mactrack_upgrade');
    });
});
