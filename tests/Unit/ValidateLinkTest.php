<?php

declare(strict_types=1);

if (!defined("ABSPATH")) {
    exit;
}

use Brain\Monkey\Functions;

/**
 * Tests for linkdigestValidateLinkForPublish()
 *
 * Returns null on success, or an error array with 'error_code' on failure.
 */

beforeEach(function (): void {
    $this->plugin = Mockery::mock(LinkDigest::class)->makePartial();
});

describe('LinkDigest::validateLinkForPublish()', function (): void {

    it('returns null when every validation condition passes', function (): void {
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_post')->justReturn(linkdigest_make_post(1, LINKDIGEST_TITLE_MY_LINK));
        Functions\when('get_post_meta')->justReturn(''); // no published_post_id

        $result = $this->plugin->validateLinkForPublish(1);

        expect($result)->toBeNull();
    });

    it('returns no_permission error when user cannot publish', function (): void {
        Functions\when('current_user_can')->justReturn(false);

        $result = $this->plugin->validateLinkForPublish(1);

        expect($result)->not->toBeNull();
        expect($result['success'])->toBeFalse();
        expect($result['error_code'])->toBe('no_permission');
    });

    it('returns invalid_link error when post does not exist', function (): void {
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_post')->justReturn(null);

        $result = $this->plugin->validateLinkForPublish(999);

        expect($result['error_code'])->toBe('invalid_link');
    });

    it('returns invalid_link error when post type is not linkdigest', function (): void {
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_post')->justReturn(linkdigest_make_post(1, 'Title', 'post'));

        $result = $this->plugin->validateLinkForPublish(1);

        expect($result['error_code'])->toBe('invalid_link');
    });

    it('returns missing_title error when post title is empty', function (): void {
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_post')->justReturn(linkdigest_make_post(1, ''));

        $result = $this->plugin->validateLinkForPublish(1);

        expect($result['error_code'])->toBe('missing_title');
    });

    it('returns already_published error when link already has a live published post', function (): void {
        $publishedPost = linkdigest_make_post(50, 'Blog Post', 'post');

        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_post')
            ->alias(function (int $id) use ($publishedPost): ?WP_Post {
                return match ($id) {
                    1  => linkdigest_make_post(1, LINKDIGEST_TITLE_MY_LINK),  // the link
                    50 => $publishedPost,           // the published blog post
                    default => null,
                };
            });
        Functions\when('get_post_meta')->justReturn(50); // _linkdigest_published_post_id

        $result = $this->plugin->validateLinkForPublish(1);

        expect($result['error_code'])->toBe('already_published');
    });

    it('allows re-publish when the referenced blog post was deleted', function (): void {
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_post')
            ->alias(function (int $id): ?WP_Post {
                return $id === 1 ? linkdigest_make_post(1, LINKDIGEST_TITLE_MY_LINK) : null; // published post 50 is gone
            });
        Functions\when('get_post_meta')->justReturn(50);

        $result = $this->plugin->validateLinkForPublish(1);

        // Should be null (valid) because the previously published post no longer exists
        expect($result)->toBeNull();
    });
});
