<?php

/**
 * Statera test runner for the Sukarix library.
 *
 * Usage:
 *   php tools/statera.php                  # run all tests
 *   php tools/statera.php "?statera=all"   # run all tests explicitly
 *   php tools/statera.php "?statera=withCoverage"  # run with code coverage
 *   php tools/statera.php "?test=injector" # run a specific test group
 *
 * Copyright (c) RIADVICE SUARL
 * All rights reserved.
 */

// load composer autoload
require_once __DIR__ . '/../vendor/autoload.php';

// Change to the library root so coverage and relative paths resolve correctly
chdir(realpath(__DIR__ . '/..'));

$libraryRoot = realpath(__DIR__ . '/..');

$f3 = \Base::instance();

// Minimal F3 configuration for the library test suite.
// The Statera UI templates are provided by the sukarix/statera package and
// are wired up by Statera::afterroute() at render time.
$f3->set('ROOT', $libraryRoot);
$f3->set('BASE', '');
$f3->set('TEMP', $libraryRoot . '/tmp/');
$f3->set('LOGS', $libraryRoot . '/logs/');
$f3->set('DEBUG', 0);
$f3->set('CACHE', 'folder=' . $libraryRoot . '/tmp/cache/');

// Redis configuration for QueueService tests
$f3->set('redis.host', '127.0.0.1');
$f3->set('redis.port', 6379);
$f3->set('redis.timeout', 2);

// Make sure the runtime directories exist
foreach (['tmp', 'tmp/cache', 'logs', 'statera'] as $dir) {
    $path = $libraryRoot . '/' . $dir;
    if (!is_dir($path)) {
        @mkdir($path, 0o755, true);
    }
}

// Parse CLI arguments into $_GET and F3 GET variables (mirrors Boot::detectCli()).
// Statera reads PHP's $_GET directly for test selection and coverage flags.
if (PHP_SAPI === 'cli') {
    $argv = $f3->get('SERVER.argv') ?? [];
    if (isset($argv[1])) {
        [$path, $query] = array_pad(explode('?', $argv[1], 2), 2, null);
        $f3->set('PATH', '/' . mb_ltrim($path, '/'));
        if (null !== $query) {
            parse_str($query, $params);
            foreach ($params as $key => $value) {
                $_GET[$key]           = $value;
                $f3->set('GET.' . $key, $value);
            }
        }
    } else {
        $f3->set('PATH', '/');
    }
}

// Activate the test environment unless a specific statera flag was provided
if (!array_key_exists('statera', $_GET)) {
    $_GET['statera']         = 'all';
    $f3->set('GET.statera', 'all');
}

$GLOBALS['test_cli'] = PHP_SAPI === 'cli';

// Register the library test groups and run the suite
Test\Statera::registerGroups();
Sukarix\Statera::startCoverage('Statera Bootstrap');

$f3->route('GET /', 'Test\Statera->index');

$f3->run();
