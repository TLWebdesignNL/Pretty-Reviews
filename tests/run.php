<?php

/**
 * @package     TLWeb.Module
 * @subpackage  mod_prettyreviews
 *
 * @copyright   Copyright (C) 2024 TLWebdesign. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Runs every test file in its own process, each against its own temporary site root,
 * so one suite's fixtures can never reach another.
 *
 * Usage: php tests/run.php
 */

$suites = glob(__DIR__ . '/*Test.php');
sort($suites);

$root   = sys_get_temp_dir() . '/prettyreviews-tests-' . getmypid();
$failed = [];

foreach ($suites as $suite) {
    $name = basename($suite, '.php');
    $home = $root . '/' . $name;

    if (!is_dir($home)) {
        mkdir($home, 0777, true);
    }

    echo "\n== $name ==\n";

    $command = 'TEST_ROOT=' . escapeshellarg($home) . ' ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($suite);
    passthru($command, $status);

    if ($status !== 0) {
        $failed[] = $name;
    }
}

// Leave nothing behind, whatever happened.
$sweep = static function (string $path) use (&$sweep): void {
    if (!is_dir($path)) {
        return;
    }

    foreach (scandir($path) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $child = $path . '/' . $entry;

        is_dir($child) ? $sweep($child) : @unlink($child);
    }

    @rmdir($path);
};
$sweep($root);

echo "\n";

if ($failed !== []) {
    echo count($failed) . ' of ' . count($suites) . " suites failed: " . implode(', ', $failed) . "\n";

    exit(1);
}

echo count($suites) . " suites passed\n";
