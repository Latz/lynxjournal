<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Brain\Monkey\Functions;

beforeEach(function (): void {
    Functions\when('__')->returnArg();
    $this->channel = new LynxJournalNotifyDiscordChannel();
});

describe('LynxJournalNotifyDiscordChannel::send()', function (): void {

    it('does nothing when discord is disabled', function (): void {
        $called = false;
        Functions\when('wp_remote_post')->alias(function () use (&$called): array {
            $called = true;
            return [];
        });

        $this->channel->send(42, [1, 2, 3], 'daily', ['discordEnabled' => false, 'discordWebhookUrl' => 'https://discord.com/api/webhooks/1/abc']);

        expect($called)->toBeFalse();
    });

    it('does nothing when webhook url is missing even if enabled', function (): void {
        $called = false;
        Functions\when('wp_remote_post')->alias(function () use (&$called): array {
            $called = true;
            return [];
        });

        $this->channel->send(42, [1, 2, 3], 'daily', ['discordEnabled' => true, 'discordWebhookUrl' => '']);

        expect($called)->toBeFalse();
    });

    it('posts an embed to the webhook when enabled with a post_id', function (): void {
        Functions\when('get_permalink')->justReturn('https://site.example/roundup-42');
        Functions\when('get_the_title')->justReturn('Links: April 15, 2026');
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(204);

        $captured = [];
        Functions\when('wp_remote_post')->alias(function ($url, $args) use (&$captured): array {
            $captured = compact('url', 'args');
            return ['response' => ['code' => 204]];
        });

        $this->channel->send(42, [1, 2, 3], 'daily', ['discordEnabled' => true, 'discordWebhookUrl' => 'https://discord.com/api/webhooks/123/abc']);

        expect($captured['url'])->toBe('https://discord.com/api/webhooks/123/abc');
        $body = json_decode($captured['args']['body'], true);
        expect($body['embeds'][0]['url'])->toBe('https://site.example/roundup-42');
        expect($body['embeds'][0]['fields'][0]['value'])->toBe('3');
    });

    it('posts a neutral embed when post_id is null', function (): void {
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(204);

        $captured = [];
        Functions\when('wp_remote_post')->alias(function ($url, $args) use (&$captured): array {
            $captured = compact('url', 'args');
            return ['response' => ['code' => 204]];
        });

        $this->channel->send(null, [1, 2], 'count', ['discordEnabled' => true, 'discordWebhookUrl' => 'https://discord.com/api/webhooks/123/abc']);

        $body = json_decode($captured['args']['body'], true);
        expect($body['embeds'][0]['description'])->toContain('count');
    });

    it('returns a WP_Error when wp_remote_post returns a WP_Error', function (): void {
        Functions\when('wp_remote_post')->justReturn(Mockery::mock('WP_Error'));
        Functions\when('is_wp_error')->justReturn(true);

        $result = $this->channel->send(42, [1], 'daily', ['discordEnabled' => true, 'discordWebhookUrl' => 'https://discord.com/api/webhooks/123/abc']);

        expect($result)->toBeInstanceOf(WP_Error::class);
    });

    it('returns a WP_Error when the response code is not a 2xx', function (): void {
        Functions\when('wp_remote_post')->justReturn(['response' => ['code' => 401]]);
        Functions\when('is_wp_error')->alias(fn ($v) => $v instanceof WP_Error);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(401);

        $result = $this->channel->send(42, [1], 'daily', ['discordEnabled' => true, 'discordWebhookUrl' => 'https://discord.com/api/webhooks/123/abc']);

        expect($result)->toBeInstanceOf(WP_Error::class);
    });
});
