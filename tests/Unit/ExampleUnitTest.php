<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Example Unit test using Brain Monkey.
 *
 * Brain\Monkey\setUp() / tearDown() are called automatically via pest.php hooks.
 * Use Brain\Monkey\Functions\expect() to stub any WordPress function.
 */

use Brain\Monkey\Functions;

it('stubs a WordPress function with Brain Monkey', function (): void {
    // Arrange – tell Brain Monkey what get_option() should return.
    Functions\when('get_option')->justReturn(['per_page' => 20]);

    // Act – call the code that internally calls get_option().
    $result = get_option('linkdigest_settings');

    // Assert
    expect($result)->toBe(['per_page' => 20]);
});

it('stubs apply_filters to pass the value through unchanged', function (): void {
    // returnArg(2) returns the second argument — the $value param of apply_filters.
    Brain\Monkey\Functions\when('apply_filters')->returnArg(2);

    $tag = apply_filters('linkdigest_tag', 'my-tag');

    expect($tag)->toBe('my-tag');
});
