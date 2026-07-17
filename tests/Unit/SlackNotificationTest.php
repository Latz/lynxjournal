<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Brain\Monkey\Functions;

beforeEach(function (): void {
    Functions\when('__')->returnArg();
    $this->channelChannel = new LynxJournalNotifySlackChannelChannel();
    $this->dmChannel = new LynxJournalNotifySlackDmChannel();
});

describe('LynxJournalNotifySlackChannelChannel::send()', function (): void {

    it('does nothing when slack channel is disabled', function (): void {
        $called = false;
        Functions\when('wp_remote_post')->alias(function () use (&$called): array {
            $called = true;
            return [];
        });

        $this->channelChannel->send(42, [1, 2, 3], 'daily', ['slackChannelEnabled' => false, 'slackBotToken' => 'xoxb-123', 'slackChannelId' => 'C123']);

        expect($called)->toBeFalse();
    });

    it('does nothing when enabled but bot token is missing', function (): void {
        $called = false;
        Functions\when('wp_remote_post')->alias(function () use (&$called): array {
            $called = true;
            return [];
        });

        $this->channelChannel->send(42, [1, 2, 3], 'daily', ['slackChannelEnabled' => true, 'slackBotToken' => '', 'slackChannelId' => 'C123']);

        expect($called)->toBeFalse();
    });

    it('does nothing when enabled but channel id is missing', function (): void {
        $called = false;
        Functions\when('wp_remote_post')->alias(function () use (&$called): array {
            $called = true;
            return [];
        });

        $this->channelChannel->send(42, [1, 2, 3], 'daily', ['slackChannelEnabled' => true, 'slackBotToken' => 'xoxb-123', 'slackChannelId' => '']);

        expect($called)->toBeFalse();
    });

    it('posts a Block Kit message to the channel when enabled with a post_id', function (): void {
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

        $this->channelChannel->send(42, [1, 2, 3], 'daily', ['slackChannelEnabled' => true, 'slackBotToken' => 'xoxb-123-abc', 'slackChannelId' => 'C0123456789']);

        expect($captured['url'])->toBe('https://slack.com/api/chat.postMessage');
        expect($captured['args']['headers']['Authorization'])->toBe('Bearer xoxb-123-abc');
        $body = json_decode($captured['args']['body'], true);
        expect($body['channel'])->toBe('C0123456789');
        expect($body['blocks'][0]['text']['text'])->toBe('Links: April 15, 2026');
        expect($body['blocks'][1]['text']['text'])->toContain('https://site.example/roundup-42');
        expect($body['blocks'][2]['elements'][0]['text'])->toContain('3');
    });

    it('posts a neutral message when post_id is null', function (): void {
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(['ok' => true]));

        $captured = [];
        Functions\when('wp_remote_post')->alias(function ($url, $args) use (&$captured): array {
            $captured = compact('url', 'args');
            return ['response' => ['code' => 200]];
        });

        $this->channelChannel->send(null, [1, 2], 'count', ['slackChannelEnabled' => true, 'slackBotToken' => 'xoxb-123', 'slackChannelId' => 'C0123456789']);

        $body = json_decode($captured['args']['body'], true);
        expect($body['blocks'][1]['text']['text'])->toContain('count');
    });

    it('returns a WP_Error when wp_remote_post returns a WP_Error', function (): void {
        Functions\when('wp_remote_post')->justReturn(Mockery::mock('WP_Error'));
        Functions\when('is_wp_error')->justReturn(true);

        $result = $this->channelChannel->send(42, [1], 'daily', ['slackChannelEnabled' => true, 'slackBotToken' => 'xoxb-123', 'slackChannelId' => 'C0123456789']);

        expect($result)->toBeInstanceOf(WP_Error::class);
    });

    it('returns a WP_Error when the response code is not a 2xx', function (): void {
        Functions\when('wp_remote_post')->justReturn(['response' => ['code' => 401]]);
        Functions\when('is_wp_error')->alias(fn ($v) => $v instanceof WP_Error);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(401);

        $result = $this->channelChannel->send(42, [1], 'daily', ['slackChannelEnabled' => true, 'slackBotToken' => 'xoxb-123', 'slackChannelId' => 'C0123456789']);

        expect($result)->toBeInstanceOf(WP_Error::class);
    });

    it('returns a WP_Error when Slack responds with HTTP 200 but ok:false in the body', function (): void {
        Functions\when('wp_remote_post')->justReturn(['response' => ['code' => 200]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(['ok' => false, 'error' => 'channel_not_found']));

        $result = $this->channelChannel->send(42, [1], 'daily', ['slackChannelEnabled' => true, 'slackBotToken' => 'xoxb-123', 'slackChannelId' => 'C0123456789']);

        expect($result)->toBeInstanceOf(WP_Error::class);
    });
});

describe('LynxJournalNotifySlackDmChannel::send()', function (): void {

    it('does nothing when slack DM is disabled', function (): void {
        $called = false;
        Functions\when('wp_remote_post')->alias(function () use (&$called): array {
            $called = true;
            return [];
        });

        $this->dmChannel->send(42, [1, 2, 3], 'daily', ['slackDmEnabled' => false, 'slackBotToken' => 'xoxb-123', 'slackUserId' => 'U123']);

        expect($called)->toBeFalse();
    });

    it('does nothing when enabled but user id is missing', function (): void {
        $called = false;
        Functions\when('wp_remote_post')->alias(function () use (&$called): array {
            $called = true;
            return [];
        });

        $this->dmChannel->send(42, [1, 2, 3], 'daily', ['slackDmEnabled' => true, 'slackBotToken' => 'xoxb-123', 'slackUserId' => '']);

        expect($called)->toBeFalse();
    });

    it('posts a Block Kit DM when enabled with a post_id', function (): void {
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

        $this->dmChannel->send(42, [1, 2, 3], 'daily', ['slackDmEnabled' => true, 'slackBotToken' => 'xoxb-123', 'slackUserId' => 'U0123456789']);

        $body = json_decode($captured['args']['body'], true);
        expect($body['channel'])->toBe('U0123456789');
    });
});

describe('LynxJournalNotifySlackChannelChannel + LynxJournalNotifySlackDmChannel together', function (): void {

    it('sends two independent messages when both channel and DM are enabled', function (): void {
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

        $notify = [
            'slackChannelEnabled' => true, 'slackChannelId' => 'C0123456789',
            'slackDmEnabled'      => true, 'slackUserId'    => 'U0123456789',
            'slackBotToken'       => 'xoxb-123',
        ];
        $this->channelChannel->send(42, [1], 'daily', $notify);
        $this->dmChannel->send(42, [1], 'daily', $notify);

        expect($channels)->toBe(['C0123456789', 'U0123456789']);
    });
});
