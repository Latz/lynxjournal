<?php

declare(strict_types=1);

if (!defined("ABSPATH")) {
    exit;
}

use Brain\Monkey\Functions;

/**
 * Tests for LinkDigest::unpublishLink()
 */

beforeEach(function (): void {
    Functions\when('__')->returnArg();
    $this->plugin = Mockery::mock(LinkDigest::class)->makePartial();
});

describe('LinkDigest::unpublishLink()', function (): void {

    it('returns an error when the link has no published post id in meta', function (): void {
        Functions\when('get_post_meta')->justReturn(''); // no stored post ID

        $result = $this->plugin->unpublishLink(1);

        expect($result['success'])->toBeFalse();
        expect($result['message'])->toContain('not been published');
    });

    it('returns an error when wp_trash_post fails', function (): void {
        Functions\when('get_post_meta')->justReturn(50); // has published post ID
        Functions\when('wp_trash_post')->justReturn(false);

        $result = $this->plugin->unpublishLink(1);

        expect($result['success'])->toBeFalse();
        expect($result['message'])->toContain('Failed to unpublish');
    });

    it('returns success and removes all three meta keys on success', function (): void {
        Functions\when('get_post_meta')->justReturn(50);
        Functions\when('wp_trash_post')->justReturn(linkdigest_make_post(50, 'Blog Post', 'post'));

        $deleted = [];
        Functions\when('delete_post_meta')
            ->alias(function (int $_id, string $key) use (&$deleted): bool {
                $deleted[] = $key;
                return true;
            });

        $result = $this->plugin->unpublishLink(1);

        expect($result['success'])->toBeTrue();
        expect($deleted)->toContain('_linkdigest_published_post_id');
        expect($deleted)->toContain('_linkdigest_publish_status');
        expect($deleted)->toContain('_linkdigest_published_date');
    });

    it('trashes the correct blog post ID', function (): void {
        Functions\when('get_post_meta')->justReturn(77);
        Functions\when('delete_post_meta')->justReturn(true);

        $trashedId = null;
        Functions\when('wp_trash_post')->alias(
            function (int $id) use (&$trashedId): WP_Post|false|null {
                $trashedId = $id;
                return linkdigest_make_post($id, 'Post', 'post');
            }
        );

        $this->plugin->unpublishLink(1);

        expect($trashedId)->toBe(77);
    });
});
