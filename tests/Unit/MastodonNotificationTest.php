<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Brain\Monkey\Functions;

beforeEach(function (): void {
    Functions\when('__')->returnArg();
    $this->plugin = Mockery::mock(LynxJournal::class)->makePartial();
});

describe('LynxJournal::maybeSendMastodonNotification()', function (): void {

    it('does nothing when mastodon is disabled', function (): void {
        Functions\when('get_option')->justReturn([
            'notify' => ['mastodonEnabled' => false, 'mastodonInstanceUrl' => 'https://mastodon.social', 'mastodonAccessToken' => 'token123', 'mastodonRecipient' => '@you@mastodon.social'],
        ]);

        $called = false;
        Functions\when('wp_remote_post')->alias(function () use (&$called): array {
            $called = true;
            return [];
        });

        $this->plugin->maybeSendMastodonNotification(42, [1, 2, 3], 'daily');

        expect($called)->toBeFalse();
    });

    it('does nothing when enabled but instance url is missing', function (): void {
        Functions\when('get_option')->justReturn([
            'notify' => ['mastodonEnabled' => true, 'mastodonInstanceUrl' => '', 'mastodonAccessToken' => 'token123', 'mastodonRecipient' => '@you@mastodon.social'],
        ]);

        $called = false;
        Functions\when('wp_remote_post')->alias(function () use (&$called): array {
            $called = true;
            return [];
        });

        $this->plugin->maybeSendMastodonNotification(42, [1, 2, 3], 'daily');

        expect($called)->toBeFalse();
    });

    it('does nothing when enabled but access token is missing', function (): void {
        Functions\when('get_option')->justReturn([
            'notify' => ['mastodonEnabled' => true, 'mastodonInstanceUrl' => 'https://mastodon.social', 'mastodonAccessToken' => '', 'mastodonRecipient' => '@you@mastodon.social'],
        ]);

        $called = false;
        Functions\when('wp_remote_post')->alias(function () use (&$called): array {
            $called = true;
            return [];
        });

        $this->plugin->maybeSendMastodonNotification(42, [1, 2, 3], 'daily');

        expect($called)->toBeFalse();
    });

    it('does nothing when enabled but recipient is missing', function (): void {
        Functions\when('get_option')->justReturn([
            'notify' => ['mastodonEnabled' => true, 'mastodonInstanceUrl' => 'https://mastodon.social', 'mastodonAccessToken' => 'token123', 'mastodonRecipient' => ''],
        ]);

        $called = false;
        Functions\when('wp_remote_post')->alias(function () use (&$called): array {
            $called = true;
            return [];
        });

        $this->plugin->maybeSendMastodonNotification(42, [1, 2, 3], 'daily');

        expect($called)->toBeFalse();
    });

    it('posts a direct status to the instance API when enabled with a post_id', function (): void {
        Functions\when('get_option')->justReturn([
            'notify' => ['mastodonEnabled' => true, 'mastodonInstanceUrl' => 'https://mastodon.social', 'mastodonAccessToken' => 'token123', 'mastodonRecipient' => '@you@mastodon.social'],
        ]);
        Functions\when('get_permalink')->justReturn('https://site.example/roundup-42');
        Functions\when('get_the_title')->justReturn('Links: April 15, 2026');
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);

        $captured = [];
        Functions\when('wp_remote_post')->alias(function ($url, $args) use (&$captured): array {
            $captured = compact('url', 'args');
            return ['response' => ['code' => 200]];
        });

        $this->plugin->maybeSendMastodonNotification(42, [1, 2, 3], 'daily');

        expect($captured['url'])->toBe('https://mastodon.social/api/v1/statuses');
        expect($captured['args']['headers']['Authorization'])->toBe('Bearer token123');
        $body = json_decode($captured['args']['body'], true);
        expect($body['visibility'])->toBe('direct');
        expect($body['status'])->toContain('@you@mastodon.social');
        expect($body['status'])->toContain('Links: April 15, 2026');
        expect($body['status'])->toContain('https://site.example/roundup-42');
    });

    it('strips a trailing slash from the instance url', function (): void {
        Functions\when('get_option')->justReturn([
            'notify' => ['mastodonEnabled' => true, 'mastodonInstanceUrl' => 'https://mastodon.social/', 'mastodonAccessToken' => 'token123', 'mastodonRecipient' => '@you@mastodon.social'],
        ]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);

        $captured = [];
        Functions\when('wp_remote_post')->alias(function ($url, $args) use (&$captured): array {
            $captured = compact('url', 'args');
            return ['response' => ['code' => 200]];
        });

        $this->plugin->maybeSendMastodonNotification(null, [1], 'count');

        expect($captured['url'])->toBe('https://mastodon.social/api/v1/statuses');
    });

    it('builds a neutral message when post_id is null', function (): void {
        Functions\when('get_option')->justReturn([
            'notify' => ['mastodonEnabled' => true, 'mastodonInstanceUrl' => 'https://mastodon.social', 'mastodonAccessToken' => 'token123', 'mastodonRecipient' => '@you@mastodon.social'],
        ]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);

        $captured = [];
        Functions\when('wp_remote_post')->alias(function ($url, $args) use (&$captured): array {
            $captured = compact('url', 'args');
            return ['response' => ['code' => 200]];
        });

        $this->plugin->maybeSendMastodonNotification(null, [1, 2], 'count');

        $body = json_decode($captured['args']['body'], true);
        expect($body['status'])->toContain('count');
    });

    it('silently returns when wp_remote_post returns a WP_Error', function (): void {
        Functions\when('get_option')->justReturn([
            'notify' => ['mastodonEnabled' => true, 'mastodonInstanceUrl' => 'https://mastodon.social', 'mastodonAccessToken' => 'token123', 'mastodonRecipient' => '@you@mastodon.social'],
        ]);
        Functions\when('wp_remote_post')->justReturn(Mockery::mock('WP_Error'));
        Functions\when('is_wp_error')->justReturn(true);

        $this->plugin->maybeSendMastodonNotification(42, [1], 'daily');
    })->throwsNoExceptions();

    it('silently returns when the response code is not a 2xx', function (): void {
        Functions\when('get_option')->justReturn([
            'notify' => ['mastodonEnabled' => true, 'mastodonInstanceUrl' => 'https://mastodon.social', 'mastodonAccessToken' => 'token123', 'mastodonRecipient' => '@you@mastodon.social'],
        ]);
        Functions\when('wp_remote_post')->justReturn(['response' => ['code' => 401]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(401);

        $this->plugin->maybeSendMastodonNotification(42, [1], 'daily');
    })->throwsNoExceptions();
});
