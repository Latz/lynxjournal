<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Example Integration test.
 *
 * Run with a real WP test DB:
 *   vendor/bin/pest --testsuite=Integration --bootstrap tests/bootstrap-integration.php
 *
 * Requires the WP test suite installed via bin/install-wp-tests.sh.
 * The WP_TESTS_DIR env var must point to that suite.
 */

it('registers the linkdigest custom post type', function (): void {
    // WP is fully loaded here – post types are registered.
    expect(post_type_exists('linkdigest'))->toBeTrue();
});

it('creates a link post and retrieves it', function (): void {
    $postId = wp_insert_post([
        'post_type'   => 'linkdigest',
        'post_title'  => 'Test Link',
        'post_status' => 'publish',
        'meta_input'  => ['_linkdigest_url' => 'https://example.com'],
    ]);

    expect($postId)->toBeInt()->toBeGreaterThan(0);

    $url = get_post_meta($postId, '_linkdigest_url', true);

    expect($url)->toBe('https://example.com');
});
