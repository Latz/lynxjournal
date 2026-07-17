<?php

/**
 * Pest configuration for LynxJournal plugin.
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
    ->in('Unit');

// Helpers are defined in tests/helpers.php, loaded by bootstrap-unit.php.
