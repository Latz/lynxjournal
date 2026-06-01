<?php

declare(strict_types=1);

namespace LinkDigest\Tests\Unit;

if (!defined('ABSPATH')) {
    exit;
}

use LinkDigest;
use Mockery;
use Brain\Monkey\Functions;
use Brain\Monkey\Actions;
use Brain\Monkey\Filters;

describe('LinkDigest_Publishing Trait', function () {
    beforeEach(function () {
        $this->plugin = Mockery::mock(LinkDigest::class)->makePartial();
    });

    describe('validateLinkForPublish()', function () {
        it('returns error if user cannot publish posts', function () {
            Functions\when('current_user_can')->justReturn(false);

            $result = $this->plugin->validateLinkForPublish(123);

            expect($result)->toBeArray();
            expect($result['error_code'])->toBe('no_permission');
        });

        it('returns error if post is not a linkdigest type', function () {
            Functions\when('current_user_can')->justReturn(true);

            $post = linkdigest_make_post(123, 'Title', 'post'); // Helper from tests/helpers.php
            Functions\when('get_post')->justReturn($post);

            $result = $this->plugin->validateLinkForPublish(123);
            expect($result['error_code'])->toBe('invalid_link');
        });

        it('returns error if link has no title', function () {
            Functions\when('current_user_can')->justReturn(true);

            $post = linkdigest_make_post(123, '', 'linkdigest');
            Functions\when('get_post')->justReturn($post);

            $result = $this->plugin->validateLinkForPublish(123);
            expect($result['error_code'])->toBe('missing_title');
        });

        it('returns error if link is already published', function () {
            Functions\when('current_user_can')->justReturn(true);

            // Mock that a published post ID exists in meta
            Functions\when('get_post_meta')->justReturn(456);

            Functions\when('get_post')->alias(function ($id) {
                return match ($id) {
                    123 => linkdigest_make_post(123, 'Title', 'linkdigest'),
                    456 => linkdigest_make_post(456, 'Published', 'post'),
                    default => null,
                };
            });

            $result = $this->plugin->validateLinkForPublish(123);
            expect($result['error_code'])->toBe('already_published');
        });

        it('returns null when validation passes', function () {
            Functions\when('current_user_can')->justReturn(true);
            Functions\when('get_post')->justReturn(linkdigest_make_post(123, 'Title', 'linkdigest'));
            Functions\when('get_post_meta')->justReturn(null);

            $result = $this->plugin->validateLinkForPublish(123);
            expect($result)->toBeNull();
        });
    });

    describe('buildPostContent()', function () {
        it('correctly constructs HTML and applies filters', function () {
            $title = 'My Link';
            $url = 'https://google.com';
            $desc = 'A search engine';

            $applied = false;
            Functions\when('apply_filters')->alias(function ($hook, $value) use (&$applied) {
                if ($hook === 'linkdigest_blog_post_content') {
                    $applied = true;
                }
                return $value;
            });

            $html = $this->plugin->buildPostContent($title, 123, $url, $desc);

            expect($applied)->toBeTrue();
            expect($html)->toContain('<h2>My Link</h2>');
            expect($html)->toContain('A search engine');
            expect($html)->toContain('href="https://google.com"');
        });
    });

    describe('createBlogPost()', function () {
        it('successfully creates a post and triggers actions', function () {
            $link_id = 100;
            $new_post_id = 200;

            // 1. Partially mock internal calls within the same class
            $this->plugin->shouldReceive('validateLinkForPublish')->andReturn(null);
            $this->plugin->shouldReceive('buildPostContent')->andReturn('Generated Content');
            $this->plugin->shouldReceive('mapTaxonomies')->once();

            // 2. Mock WordPress functions
            Functions\when('get_post')->justReturn(linkdigest_make_post($link_id, 'Original Title', 'linkdigest'));
            Functions\when('get_post_meta')->justReturn('https://test.com');
            Functions\when('wp_insert_post')->justReturn($new_post_id);
            Functions\when('update_post_meta')->justReturn(true);
            Functions\when('current_time')->justReturn('2023-01-01 12:00:00');

            // 3. Track action hook
            $actionFired = false;
            $actionArgs = null;
            Functions\when('do_action')->alias(function ($hook, ...$args) use (&$actionFired, &$actionArgs) {
                if ($hook === 'linkdigest_after_publish') {
                    $actionFired = true;
                    $actionArgs = $args;
                }
            });

            $result = $this->plugin->createBlogPost($link_id, false);

            expect($result['success'])->toBeTrue();
            expect($result['post_id'])->toBe($new_post_id);
            expect($actionFired)->toBeTrue();
            expect($actionArgs)->toBe([$link_id, $new_post_id, false]);
        });
    });
});
