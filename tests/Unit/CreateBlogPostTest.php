<?php

declare(strict_types=1);

if (!defined("ABSPATH")) {
    exit;
}

use Brain\Monkey\Functions;
use Brain\Monkey\Actions;

/**
 * Tests for linkdigestCreateBlogPost()
 */

beforeEach(function (): void {
    Functions\when('esc_html')->returnArg();
    Functions\when('esc_url')->returnArg();
    Functions\when('wp_kses_post')->returnArg();
    Functions\when('apply_filters')->returnArg(2);
    Functions\when('__')->returnArg();
    Functions\when('get_the_terms')->justReturn(false);
    Functions\when('current_time')->justReturn('2026-04-13 10:00:00');
    $this->plugin = Mockery::mock(LinkDigest::class)->makePartial();
});

describe('LinkDigest::createBlogPost()', function (): void { // NOSONAR — cognitive complexity acceptable in test suite

    it('returns the validation error array when validate fails', function (): void {
        // Simulate no permission
        Functions\when('current_user_can')->justReturn(false);

        $result = $this->plugin->createBlogPost(1);

        expect($result['success'])->toBeFalse();
        expect($result['error_code'])->toBe('no_permission');
    });

    it('creates a published post when as_draft is false', function (): void {
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_post')
            ->alias(fn($id) => $id === 1 ? linkdigest_make_post(1, 'My Link') : null);
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('wp_insert_post')->justReturn(99);
        Functions\when('update_post_meta')->justReturn(true);

        $result = $this->plugin->createBlogPost(1, false);

        expect($result['success'])->toBeTrue();
        expect($result['post_id'])->toBe(99);
    });

    it('creates a draft post when as_draft is true', function (): void {
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_post')
            ->alias(fn($id) => $id === 1 ? linkdigest_make_post(1, 'Draft Link') : null);
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('wp_insert_post')->justReturn(100);
        Functions\when('update_post_meta')->justReturn(true);

        $result = $this->plugin->createBlogPost(1, true);

        expect($result['success'])->toBeTrue();
        expect($result['message'])->toContain('draft');
    });

    it('passes post_status=publish to wp_insert_post when as_draft is false', function (): void {
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_post')
            ->alias(fn($id) => $id === 1 ? linkdigest_make_post(1, 'Link') : null);
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('update_post_meta')->justReturn(true);

        $capturedStatus = null;
        Functions\when('wp_insert_post')->alias(
            function (array $arr) use (&$capturedStatus): int {
                $capturedStatus = $arr['post_status'];
                return 77;
            }
        );

        $this->plugin->createBlogPost(1, false);

        expect($capturedStatus)->toBe('publish');
    });

    it('passes post_status=draft to wp_insert_post when as_draft is true', function (): void {
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_post')
            ->alias(fn($id) => $id === 1 ? linkdigest_make_post(1, 'Link') : null);
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('update_post_meta')->justReturn(true);

        $capturedStatus = null;
        Functions\when('wp_insert_post')->alias(
            function (array $arr) use (&$capturedStatus): int {
                $capturedStatus = $arr['post_status'];
                return 78;
            }
        );

        $this->plugin->createBlogPost(1, true);

        expect($capturedStatus)->toBe('draft');
    });

    it('returns insert_failed error when wp_insert_post returns 0', function (): void {
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_post')
            ->alias(fn($id) => $id === 1 ? linkdigest_make_post(1, 'Link') : null);
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('wp_insert_post')->justReturn(0);

        $result = $this->plugin->createBlogPost(1);

        expect($result['success'])->toBeFalse();
        expect($result['error_code'])->toBe('insert_failed');
    });

    it('fires the linkdigest_after_publish action on success', function (): void {
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_post')
            ->alias(fn($id) => $id === 1 ? linkdigest_make_post(1, 'Link') : null);
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('wp_insert_post')->justReturn(55);
        Functions\when('update_post_meta')->justReturn(true);

        $actionArgs = null;
        Functions\when('do_action')->alias(
            function (string $hook, mixed ...$args) use (&$actionArgs): void {
                if ($hook === 'linkdigest_after_publish') {
                    $actionArgs = $args;
                }
            }
        );

        $this->plugin->createBlogPost(1, false);

        expect($actionArgs)->toBe([1, 55, false]);
    });

    it('saves the published post id and publish status in meta', function (): void {
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_post')
            ->alias(fn($id) => $id === 1 ? linkdigest_make_post(1, 'Link') : null);
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('wp_insert_post')->justReturn(66);

        $calls = [];
        Functions\when('update_post_meta')
            ->alias(function (int $_id, string $key, mixed $value) use (&$calls): bool {
                $calls[$key] = $value;
                return true;
            });

        $this->plugin->createBlogPost(1, false);

        expect($calls)->toHaveKey('_linkdigest_published_post_id')
            ->and($calls['_linkdigest_published_post_id'])->toBe(66);
        expect($calls)->toHaveKey('_linkdigest_publish_status')
            ->and($calls['_linkdigest_publish_status'])->toBe('published');
    });
});
