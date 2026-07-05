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

describe('LynxJournal::maybeSendSlackChannelNotification()', function (): void {

    it('does nothing when slack channel is disabled', function (): void {
        Functions\when('get_option')->justReturn([
            'notify' => ['slackChannelEnabled' => false, 'slackBotToken' => 'xoxb-123', 'slackChannelId' => 'C123'],
        ]);

        $called = false;
        Functions\when('wp_remote_post')->alias(function () use (&$called): array {
            $called = true;
            return [];
        });

        $this->plugin->maybeSendSlackChannelNotification(42, [1, 2, 3], 'daily');

        expect($called)->toBeFalse();
    });

    it('does nothing when enabled but bot token is missing', function (): void {
        Functions\when('get_option')->justReturn([
            'notify' => ['slackChannelEnabled' => true, 'slackBotToken' => '', 'slackChannelId' => 'C123'],
        ]);

        $called = false;
        Functions\when('wp_remote_post')->alias(function () use (&$called): array {
            $called = true;
            return [];
        });

        $this->plugin->maybeSendSlackChannelNotification(42, [1, 2, 3], 'daily');

        expect($called)->toBeFalse();
    });

    it('does nothing when enabled but channel id is missing', function (): void {
        Functions\when('get_option')->justReturn([
            'notify' => ['slackChannelEnabled' => true, 'slackBotToken' => 'xoxb-123', 'slackChannelId' => ''],
        ]);

        $called = false;
        Functions\when('wp_remote_post')->alias(function () use (&$called): array {
            $called = true;
            return [];
        });

        $this->plugin->maybeSendSlackChannelNotification(42, [1, 2, 3], 'daily');

        expect($called)->toBeFalse();
    });

    it('posts a Block Kit message to the channel when enabled with a post_id', function (): void {
        Functions\when('get_option')->justReturn([
            'notify' => ['slackChannelEnabled' => true, 'slackBotToken' => 'xoxb-123-abc', 'slackChannelId' => 'C0123456789'],
        ]);
        Functions\when('get_permalink')->justReturn('https://site.example/roundup-42');
        Functions\when('get_the_title')->justReturn('Links: April 15, 2026');
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(['ok' => true]));

        $captured = [];
        Functions\when('wp_remote_post')->alias(function ($url, $args) use (&$captured): array {
            $captured = compact('url', 'args');
            return ['response' => ['code' => 200]];
        });

        $this->plugin->maybeSendSlackChannelNotification(42, [1, 2, 3], 'daily');

        expect($captured['url'])->toBe('https://slack.com/api/chat.postMessage');
        expect($captured['args']['headers']['Authorization'])->toBe('Bearer xoxb-123-abc');
        $body = json_decode($captured['args']['body'], true);
        expect($body['channel'])->toBe('C0123456789');
        expect($body['blocks'][0]['text']['text'])->toBe('Links: April 15, 2026');
        expect($body['blocks'][1]['text']['text'])->toContain('https://site.example/roundup-42');
        expect($body['blocks'][2]['elements'][0]['text'])->toContain('3');
    });

    it('posts a neutral message when post_id is null', function (): void {
        Functions\when('get_option')->justReturn([
            'notify' => ['slackChannelEnabled' => true, 'slackBotToken' => 'xoxb-123', 'slackChannelId' => 'C0123456789'],
        ]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(['ok' => true]));

        $captured = [];
        Functions\when('wp_remote_post')->alias(function ($url, $args) use (&$captured): array {
            $captured = compact('url', 'args');
            return ['response' => ['code' => 200]];
        });

        $this->plugin->maybeSendSlackChannelNotification(null, [1, 2], 'count');

        $body = json_decode($captured['args']['body'], true);
        expect($body['blocks'][1]['text']['text'])->toContain('count');
    });

    it('silently returns when wp_remote_post returns a WP_Error', function (): void {
        Functions\when('get_option')->justReturn([
            'notify' => ['slackChannelEnabled' => true, 'slackBotToken' => 'xoxb-123', 'slackChannelId' => 'C0123456789'],
        ]);
        Functions\when('wp_remote_post')->justReturn(Mockery::mock('WP_Error'));
        Functions\when('is_wp_error')->justReturn(true);

        $this->plugin->maybeSendSlackChannelNotification(42, [1], 'daily');
    })->throwsNoExceptions();

    it('silently returns when the response code is not a 2xx', function (): void {
        Functions\when('get_option')->justReturn([
            'notify' => ['slackChannelEnabled' => true, 'slackBotToken' => 'xoxb-123', 'slackChannelId' => 'C0123456789'],
        ]);
        Functions\when('wp_remote_post')->justReturn(['response' => ['code' => 401]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(401);

        $this->plugin->maybeSendSlackChannelNotification(42, [1], 'daily');
    })->throwsNoExceptions();

    it('silently returns when Slack responds with HTTP 200 but ok:false in the body', function (): void {
        Functions\when('get_option')->justReturn([
            'notify' => ['slackChannelEnabled' => true, 'slackBotToken' => 'xoxb-123', 'slackChannelId' => 'C0123456789'],
        ]);
        Functions\when('wp_remote_post')->justReturn(['response' => ['code' => 200]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(['ok' => false, 'error' => 'channel_not_found']));

        $this->plugin->maybeSendSlackChannelNotification(42, [1], 'daily');
    })->throwsNoExceptions();
});

describe('LynxJournal::maybeSendSlackDmNotification()', function (): void {

    it('does nothing when slack DM is disabled', function (): void {
        Functions\when('get_option')->justReturn([
            'notify' => ['slackDmEnabled' => false, 'slackBotToken' => 'xoxb-123', 'slackUserId' => 'U123'],
        ]);

        $called = false;
        Functions\when('wp_remote_post')->alias(function () use (&$called): array {
            $called = true;
            return [];
        });

        $this->plugin->maybeSendSlackDmNotification(42, [1, 2, 3], 'daily');

        expect($called)->toBeFalse();
    });

    it('does nothing when enabled but user id is missing', function (): void {
        Functions\when('get_option')->justReturn([
            'notify' => ['slackDmEnabled' => true, 'slackBotToken' => 'xoxb-123', 'slackUserId' => ''],
        ]);

        $called = false;
        Functions\when('wp_remote_post')->alias(function () use (&$called): array {
            $called = true;
            return [];
        });

        $this->plugin->maybeSendSlackDmNotification(42, [1, 2, 3], 'daily');

        expect($called)->toBeFalse();
    });

    it('posts a Block Kit DM when enabled with a post_id', function (): void {
        Functions\when('get_option')->justReturn([
            'notify' => ['slackDmEnabled' => true, 'slackBotToken' => 'xoxb-123', 'slackUserId' => 'U0123456789'],
        ]);
        Functions\when('get_permalink')->justReturn('https://site.example/roundup-42');
        Functions\when('get_the_title')->justReturn('Links: April 15, 2026');
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(['ok' => true]));

        $captured = [];
        Functions\when('wp_remote_post')->alias(function ($url, $args) use (&$captured): array {
            $captured = compact('url', 'args');
            return ['response' => ['code' => 200]];
        });

        $this->plugin->maybeSendSlackDmNotification(42, [1, 2, 3], 'daily');

        $body = json_decode($captured['args']['body'], true);
        expect($body['channel'])->toBe('U0123456789');
    });
});

describe('LynxJournal::maybeSendSlackChannelNotification() + maybeSendSlackDmNotification() together', function (): void {

    it('sends two independent messages when both channel and DM are enabled', function (): void {
        Functions\when('get_option')->justReturn([
            'notify' => [
                'slackChannelEnabled' => true, 'slackChannelId' => 'C0123456789',
                'slackDmEnabled'      => true, 'slackUserId'    => 'U0123456789',
                'slackBotToken'       => 'xoxb-123',
            ],
        ]);
        Functions\when('get_permalink')->justReturn('https://site.example/roundup-42');
        Functions\when('get_the_title')->justReturn('Links: April 15, 2026');
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(['ok' => true]));

        $channels = [];
        Functions\when('wp_remote_post')->alias(function ($url, $args) use (&$channels): array {
            $body = json_decode($args['body'], true);
            $channels[] = $body['channel'];
            return ['response' => ['code' => 200]];
        });

        $this->plugin->maybeSendSlackChannelNotification(42, [1], 'daily');
        $this->plugin->maybeSendSlackDmNotification(42, [1], 'daily');

        expect($channels)->toBe(['C0123456789', 'U0123456789']);
    });
});
