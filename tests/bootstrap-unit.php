<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__, 2) . '/');
}

/**
 * Bootstrap for the Unit test suite.
 *
 * Critical load order — do NOT change:
 *  1. Patchwork.php — must be the absolute first file loaded. It registers a
 *     stream wrapper that pre-processes every subsequent require/include so that
 *     Brain Monkey can redefine those functions in tests.
 *  2. Composer autoload — makes Brain Monkey, Mockery, etc. available as classes.
 *  3. WP stubs — NOW processed by Patchwork, so every function here is patchable.
 *  4. Plugin bootstrap — add_action / add_filter calls run safely inside
 *     a one-shot Brain Monkey setUp/tearDown.
 *
 * Per-test setUp/tearDown is handled by the pest.php beforeEach/afterEach hooks.
 */

// 1. Patchwork FIRST — before any other file is parsed.
require_once dirname(__DIR__) . '/vendor/antecedent/patchwork/Patchwork.php';

// 2. Composer autoload (Brain Monkey, Mockery, Pest internals).
require_once dirname(__DIR__) . '/vendor/autoload.php';

// 3. WP class + function stubs — pre-processed by Patchwork, fully patchable.
require_once __DIR__ . '/stubs/wp-stubs.php';

// 4. Shared test helpers available in every test closure.
require_once __DIR__ . '/helpers.php';

// 5. One-shot Brain Monkey pass to absorb plugin-level hook registrations.
Brain\Monkey\setUp();

require_once dirname(__DIR__) . '/linkdigest.php';

Brain\Monkey\tearDown();
