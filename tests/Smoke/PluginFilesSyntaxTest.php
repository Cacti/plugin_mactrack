<?php

declare(strict_types=1);

describe('plugin PHP files syntax', function () {
    it('all root PHP files parse without syntax errors', function () {
        $root     = realpath(__DIR__ . '/../../');
        $phpBin   = PHP_BINARY;
        $rootFiles = glob($root . '/*.php');
        $failures  = [];

        foreach ($rootFiles as $file) {
            $output     = [];
            $returnCode = 0;
            // bare escapeshellarg(): cacti_escapeshellarg() requires the full Cacti bootstrap (DB connection, config); $file is a glob-returned server-local path, not user input
            exec("$phpBin -l " . escapeshellarg($file) . ' 2>&1', $output, $returnCode); // nosemgrep: php.lang.security.exec-use.exec-use -- phpBin is PHP_BINARY (constant); file is escapeshellarg'd glob result
            if ($returnCode !== 0) {
                $failures[] = basename($file) . ': ' . implode(' ', $output);
            }
        }

        expect($failures)->toBeEmpty('PHP syntax errors: ' . implode('; ', $failures));
    });

    it('all lib PHP files parse without syntax errors', function () {
        $root    = realpath(__DIR__ . '/../../');
        $phpBin  = PHP_BINARY;
        $libFiles = glob($root . '/lib/*.php');
        $failures = [];

        foreach ($libFiles as $file) {
            $output     = [];
            $returnCode = 0;
            // bare escapeshellarg(): cacti_escapeshellarg() requires the full Cacti bootstrap (DB connection, config); $file is a glob-returned server-local path, not user input
            exec("$phpBin -l " . escapeshellarg($file) . ' 2>&1', $output, $returnCode); // nosemgrep: php.lang.security.exec-use.exec-use -- phpBin is PHP_BINARY (constant); file is escapeshellarg'd glob result
            if ($returnCode !== 0) {
                $failures[] = basename($file) . ': ' . implode(' ', $output);
            }
        }

        expect($failures)->toBeEmpty('PHP syntax errors in lib/: ' . implode('; ', $failures));
    });
});

describe('plugin required hooks and functions', function () {
    $setup = file_get_contents(realpath(__DIR__ . '/../../setup.php'));

    it('setup.php defines all required Cacti plugin hook functions', function () use ($setup) {
        $required = [
            'plugin_mactrack_install',
            'plugin_mactrack_uninstall',
            'plugin_mactrack_version',
            'plugin_mactrack_check_config',
            'plugin_mactrack_upgrade',
        ];

        $missing = [];
        foreach ($required as $fn) {
            if (!str_contains($setup, "function $fn")) {
                $missing[] = $fn;
            }
        }

        expect($missing)->toBeEmpty('Missing functions: ' . implode(', ', $missing));
    });
});

describe('datasource file discovery', function () {
    it('lib directory contains mactrack_functions.php', function () {
        expect(file_exists(realpath(__DIR__ . '/../../lib/mactrack_functions.php')))->toBeTrue();
    });

    it('lib directory contains at least one mactrack library file', function () {
        $lib = glob(realpath(__DIR__ . '/../../lib/') . '/mactrack_*.php');
        expect(count($lib))->toBeGreaterThan(0);
    });
});
