<?php

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

/**
 * Pest configuration for LinkDigest plugin.
 *
 * Unit suite  — Brain Monkey mocks WP functions; no real WordPress.
 * Integration — loads a real WordPress + test DB.
 */

uses()
    ->beforeEach(function (): void {
        Brain\Monkey\setUp();
    })
    ->afterEach(function (): void {
        Brain\Monkey\tearDown();
        Mockery::close();
    })
    ->in('tests/Unit');

// Helpers are defined in tests/helpers.php, loaded by bootstrap-unit.php.
