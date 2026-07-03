<?php

declare(strict_types=1);

if (!defined("ABSPATH")) {
    exit;
}

use Brain\Monkey\Functions;

/**
 * Tests for LynxJournal::restCreateTestPost()
 */

beforeEach(function (): void {
    Functions\when('__')->returnArg();
    Functions\when('rest_ensure_response')->returnArg();
    Functions\when('apply_filters')->returnArg(2);
    Functions\when('get_edit_post_link')->justReturn('http://example.test/wp-admin/post.php?post=42&action=edit');
    $this->plugin = Mockery::mock(LynxJournal::class)->makePartial();
});

describe('LynxJournal::restCreateTestPost()', function (): void {

    it('returns a WP_Error with status 400 when content is empty', function (): void {
        $request = lynxjournal_make_request(['content' => '']);

        $result = $this->plugin->restCreateTestPost($request);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('missing_content');
        expect($result->get_error_data()['status'])->toBe(400);
    });

    it('returns a WP_Error with status 500 when wp_insert_post fails', function (): void {
        Functions\when('wp_insert_post')->justReturn(new WP_Error('db_error', 'Database error'));

        $request = lynxjournal_make_request(['content' => '<!-- wp:heading --><h2>Test</h2><!-- /wp:heading -->']);

        $result = $this->plugin->restCreateTestPost($request);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('insert_failed');
        expect($result->get_error_data()['status'])->toBe(500);
    });

    it('returns a WP_Error with status 500 when wp_insert_post returns 0', function (): void {
        Functions\when('wp_insert_post')->justReturn(0);

        $request = lynxjournal_make_request(['content' => '<p>Content</p>']);

        $result = $this->plugin->restCreateTestPost($request);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('insert_failed');
    });

    it('returns success with the new post id and edit url', function (): void {
        Functions\when('wp_insert_post')->justReturn(42);

        $request = lynxjournal_make_request(['content' => '<p>Content</p>', 'title' => '[Test] Travel']);

        $result = $this->plugin->restCreateTestPost($request);

        expect($result['success'])->toBeTrue();
        expect($result['post_id'])->toBe(42);
        expect($result['edit_url'])->toBe('http://example.test/wp-admin/post.php?post=42&action=edit');
    });

    it('always creates the post as type "post" with status "draft"', function (): void {
        $capturedArgs = null;
        Functions\when('wp_insert_post')->alias(function (array $args) use (&$capturedArgs): int {
            $capturedArgs = $args;
            return 42;
        });

        $request = lynxjournal_make_request(['content' => '<p>Content</p>', 'title' => 'Anything']);
        $this->plugin->restCreateTestPost($request);

        expect($capturedArgs['post_type'])->toBe('post');
        expect($capturedArgs['post_status'])->toBe('draft');
    });

    it('falls back to a default title when none is provided', function (): void {
        $capturedArgs = null;
        Functions\when('wp_insert_post')->alias(function (array $args) use (&$capturedArgs): int {
            $capturedArgs = $args;
            return 42;
        });

        $request = lynxjournal_make_request(['content' => '<p>Content</p>']);
        $this->plugin->restCreateTestPost($request);

        expect($capturedArgs['post_title'])->toBe('Test Post');
    });

    it('uses the provided title when present', function (): void {
        $capturedArgs = null;
        Functions\when('wp_insert_post')->alias(function (array $args) use (&$capturedArgs): int {
            $capturedArgs = $args;
            return 42;
        });

        $request = lynxjournal_make_request(['content' => '<p>Content</p>', 'title' => '[Test] Travel']);
        $this->plugin->restCreateTestPost($request);

        expect($capturedArgs['post_title'])->toBe('[Test] Travel');
    });

    it('passes the rendered content through as post_content', function (): void {
        $capturedArgs = null;
        Functions\when('wp_insert_post')->alias(function (array $args) use (&$capturedArgs): int {
            $capturedArgs = $args;
            return 42;
        });

        $content = '<!-- wp:heading --><h2 class="wp-block-heading">Travel</h2><!-- /wp:heading -->';
        $request = lynxjournal_make_request(['content' => $content]);
        $this->plugin->restCreateTestPost($request);

        expect($capturedArgs['post_content'])->toBe($content);
    });
});
