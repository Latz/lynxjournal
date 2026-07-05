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

describe('LynxJournal::maybeSendDiscordNotification()', function (): void {

    it('does nothing when discord is disabled', function (): void {
        Functions\when('get_option')->justReturn(['notify' => ['discordEnabled' => false, 'discordWebhookUrl' => 'https://discord.com/api/webhooks/1/abc']]);

        $called = false;
        Functions\when('wp_remote_post')->alias(function () use (&$called): array {
            $called = true;
            return [];
        });

        $this->plugin->maybeSendDiscordNotification(42, [1, 2, 3], 'daily');

        expect($called)->toBeFalse();
    });

    it('does nothing when webhook url is missing even if enabled', function (): void {
        Functions\when('get_option')->justReturn(['notify' => ['discordEnabled' => true, 'discordWebhookUrl' => '']]);

        $called = false;
        Functions\when('wp_remote_post')->alias(function () use (&$called): array {
            $called = true;
            return [];
        });

        $this->plugin->maybeSendDiscordNotification(42, [1, 2, 3], 'daily');

        expect($called)->toBeFalse();
    });

    it('posts an embed to the webhook when enabled with a post_id', function (): void {
        Functions\when('get_option')->justReturn([
            'notify' => ['discordEnabled' => true, 'discordWebhookUrl' => 'https://discord.com/api/webhooks/123/abc'],
        ]);
        Functions\when('get_permalink')->justReturn('https://site.example/roundup-42');
        Functions\when('get_the_title')->justReturn('Links: April 15, 2026');
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(204);

        $captured = [];
        Functions\when('wp_remote_post')->alias(function ($url, $args) use (&$captured): array {
            $captured = compact('url', 'args');
            return ['response' => ['code' => 204]];
        });

        $this->plugin->maybeSendDiscordNotification(42, [1, 2, 3], 'daily');

        expect($captured['url'])->toBe('https://discord.com/api/webhooks/123/abc');
        $body = json_decode($captured['args']['body'], true);
        expect($body['embeds'][0]['url'])->toBe('https://site.example/roundup-42');
        expect($body['embeds'][0]['fields'][0]['value'])->toBe('3');
    });

    it('posts a neutral embed when post_id is null', function (): void {
        Functions\when('get_option')->justReturn([
            'notify' => ['discordEnabled' => true, 'discordWebhookUrl' => 'https://discord.com/api/webhooks/123/abc'],
        ]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(204);

        $captured = [];
        Functions\when('wp_remote_post')->alias(function ($url, $args) use (&$captured): array {
            $captured = compact('url', 'args');
            return ['response' => ['code' => 204]];
        });

        $this->plugin->maybeSendDiscordNotification(null, [1, 2], 'count');

        $body = json_decode($captured['args']['body'], true);
        expect($body['embeds'][0]['description'])->toContain('count');
    });

    it('silently returns when wp_remote_post returns a WP_Error', function (): void {
        Functions\when('get_option')->justReturn([
            'notify' => ['discordEnabled' => true, 'discordWebhookUrl' => 'https://discord.com/api/webhooks/123/abc'],
        ]);
        Functions\when('wp_remote_post')->justReturn(Mockery::mock('WP_Error'));
        Functions\when('is_wp_error')->justReturn(true);

        $this->plugin->maybeSendDiscordNotification(42, [1], 'daily');
    })->throwsNoExceptions();

    it('silently returns when the response code is not a 2xx', function (): void {
        Functions\when('get_option')->justReturn([
            'notify' => ['discordEnabled' => true, 'discordWebhookUrl' => 'https://discord.com/api/webhooks/123/abc'],
        ]);
        Functions\when('wp_remote_post')->justReturn(['response' => ['code' => 401]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(401);

        $this->plugin->maybeSendDiscordNotification(42, [1], 'daily');
    })->throwsNoExceptions();
});
