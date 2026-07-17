<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Brain\Monkey\Functions;

beforeEach(function (): void {
    Functions\when('__')->returnArg();
    $this->channel = new LynxJournal_Notify_MastodonChannel();
});

describe('LynxJournal_Notify_MastodonChannel::send()', function (): void {

    it('does nothing when mastodon is disabled', function (): void {
        $called = false;
        Functions\when('wp_remote_post')->alias(function () use (&$called): array {
            $called = true;
            return [];
        });

        $this->channel->send(42, [1, 2, 3], 'daily', ['mastodonEnabled' => false, 'mastodonInstanceUrl' => 'https://mastodon.social', 'mastodonAccessToken' => 'token123', 'mastodonRecipient' => '@you@mastodon.social']);

        expect($called)->toBeFalse();
    });

    it('does nothing when enabled but instance url is missing', function (): void {
        $called = false;
        Functions\when('wp_remote_post')->alias(function () use (&$called): array {
            $called = true;
            return [];
        });

        $this->channel->send(42, [1, 2, 3], 'daily', ['mastodonEnabled' => true, 'mastodonInstanceUrl' => '', 'mastodonAccessToken' => 'token123', 'mastodonRecipient' => '@you@mastodon.social']);

        expect($called)->toBeFalse();
    });

    it('does nothing when enabled but access token is missing', function (): void {
        $called = false;
        Functions\when('wp_remote_post')->alias(function () use (&$called): array {
            $called = true;
            return [];
        });

        $this->channel->send(42, [1, 2, 3], 'daily', ['mastodonEnabled' => true, 'mastodonInstanceUrl' => 'https://mastodon.social', 'mastodonAccessToken' => '', 'mastodonRecipient' => '@you@mastodon.social']);

        expect($called)->toBeFalse();
    });

    it('does nothing when enabled but recipient is missing', function (): void {
        $called = false;
        Functions\when('wp_remote_post')->alias(function () use (&$called): array {
            $called = true;
            return [];
        });

        $this->channel->send(42, [1, 2, 3], 'daily', ['mastodonEnabled' => true, 'mastodonInstanceUrl' => 'https://mastodon.social', 'mastodonAccessToken' => 'token123', 'mastodonRecipient' => '']);

        expect($called)->toBeFalse();
    });

    it('sends the raw post title, not HTML-entity-escaped, since Mastodon DMs are plain text', function (): void {
        Functions\when('get_permalink')->justReturn('https://site.example/roundup-42');
        Functions\when('get_the_title')->justReturn('Coffee & Code roundup');
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);

        $captured = [];
        Functions\when('wp_remote_post')->alias(function ($url, $args) use (&$captured): array {
            $captured = compact('url', 'args');
            return ['response' => ['code' => 200]];
        });

        $this->channel->send(42, [1, 2, 3], 'daily', ['mastodonEnabled' => true, 'mastodonInstanceUrl' => 'https://mastodon.social', 'mastodonAccessToken' => 'token123', 'mastodonRecipient' => '@you@mastodon.social']);

        $body = json_decode($captured['args']['body'], true);
        expect($body['status'])->toContain('Coffee & Code roundup');
        expect($body['status'])->not->toContain('&amp;');
    });

    it('posts a direct status to the instance API when enabled with a post_id', function (): void {
        Functions\when('get_permalink')->justReturn('https://site.example/roundup-42');
        Functions\when('get_the_title')->justReturn('Links: April 15, 2026');
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);

        $captured = [];
        Functions\when('wp_remote_post')->alias(function ($url, $args) use (&$captured): array {
            $captured = compact('url', 'args');
            return ['response' => ['code' => 200]];
        });

        $this->channel->send(42, [1, 2, 3], 'daily', ['mastodonEnabled' => true, 'mastodonInstanceUrl' => 'https://mastodon.social', 'mastodonAccessToken' => 'token123', 'mastodonRecipient' => '@you@mastodon.social']);

        expect($captured['url'])->toBe('https://mastodon.social/api/v1/statuses');
        expect($captured['args']['headers']['Authorization'])->toBe('Bearer token123');
        $body = json_decode($captured['args']['body'], true);
        expect($body['visibility'])->toBe('direct');
        expect($body['status'])->toContain('@you@mastodon.social');
        expect($body['status'])->toContain('Links: April 15, 2026');
        expect($body['status'])->toContain('https://site.example/roundup-42');
    });

    it('strips a trailing slash from the instance url', function (): void {
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);

        $captured = [];
        Functions\when('wp_remote_post')->alias(function ($url, $args) use (&$captured): array {
            $captured = compact('url', 'args');
            return ['response' => ['code' => 200]];
        });

        $this->channel->send(null, [1], 'count', ['mastodonEnabled' => true, 'mastodonInstanceUrl' => 'https://mastodon.social/', 'mastodonAccessToken' => 'token123', 'mastodonRecipient' => '@you@mastodon.social']);

        expect($captured['url'])->toBe('https://mastodon.social/api/v1/statuses');
    });

    it('builds a neutral message when post_id is null', function (): void {
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);

        $captured = [];
        Functions\when('wp_remote_post')->alias(function ($url, $args) use (&$captured): array {
            $captured = compact('url', 'args');
            return ['response' => ['code' => 200]];
        });

        $this->channel->send(null, [1, 2], 'count', ['mastodonEnabled' => true, 'mastodonInstanceUrl' => 'https://mastodon.social', 'mastodonAccessToken' => 'token123', 'mastodonRecipient' => '@you@mastodon.social']);

        $body = json_decode($captured['args']['body'], true);
        expect($body['status'])->toContain('count');
    });

    it('returns a WP_Error when wp_remote_post returns a WP_Error', function (): void {
        Functions\when('wp_remote_post')->justReturn(Mockery::mock('WP_Error'));
        Functions\when('is_wp_error')->justReturn(true);

        $result = $this->channel->send(42, [1], 'daily', ['mastodonEnabled' => true, 'mastodonInstanceUrl' => 'https://mastodon.social', 'mastodonAccessToken' => 'token123', 'mastodonRecipient' => '@you@mastodon.social']);

        expect($result)->toBeInstanceOf(WP_Error::class);
    });

    it('returns a WP_Error when the response code is not a 2xx', function (): void {
        Functions\when('wp_remote_post')->justReturn(['response' => ['code' => 401]]);
        Functions\when('is_wp_error')->alias(fn ($v) => $v instanceof WP_Error);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(401);

        $result = $this->channel->send(42, [1], 'daily', ['mastodonEnabled' => true, 'mastodonInstanceUrl' => 'https://mastodon.social', 'mastodonAccessToken' => 'token123', 'mastodonRecipient' => '@you@mastodon.social']);

        expect($result)->toBeInstanceOf(WP_Error::class);
    });
});
