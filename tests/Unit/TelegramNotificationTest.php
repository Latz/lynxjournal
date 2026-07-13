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

describe('LynxJournal::maybeSendTelegramNotification()', function (): void {

    it('does nothing when telegram is disabled', function (): void {
        Functions\when('get_option')->justReturn([
            'notify' => ['telegramEnabled' => false, 'telegramBotToken' => '123:abc', 'telegramChatId' => '123'],
        ]);

        $called = false;
        Functions\when('wp_remote_post')->alias(function () use (&$called): array {
            $called = true;
            return [];
        });

        $this->plugin->maybeSendTelegramNotification(42, [1, 2, 3], 'daily');

        expect($called)->toBeFalse();
    });

    it('does nothing when enabled but bot token is missing', function (): void {
        Functions\when('get_option')->justReturn([
            'notify' => ['telegramEnabled' => true, 'telegramBotToken' => '', 'telegramChatId' => '123'],
        ]);

        $called = false;
        Functions\when('wp_remote_post')->alias(function () use (&$called): array {
            $called = true;
            return [];
        });

        $this->plugin->maybeSendTelegramNotification(42, [1, 2, 3], 'daily');

        expect($called)->toBeFalse();
    });

    it('does nothing when enabled but chat id is missing', function (): void {
        Functions\when('get_option')->justReturn([
            'notify' => ['telegramEnabled' => true, 'telegramBotToken' => '123:abc', 'telegramChatId' => ''],
        ]);

        $called = false;
        Functions\when('wp_remote_post')->alias(function () use (&$called): array {
            $called = true;
            return [];
        });

        $this->plugin->maybeSendTelegramNotification(42, [1, 2, 3], 'daily');

        expect($called)->toBeFalse();
    });

    it('posts a message to the Telegram API when enabled with a post_id', function (): void {
        Functions\when('get_option')->justReturn([
            'notify' => ['telegramEnabled' => true, 'telegramBotToken' => '123456789:AAAbbbCCCdddEEEfffGGGhhh', 'telegramChatId' => '-1001234567890'],
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

        $this->plugin->maybeSendTelegramNotification(42, [1, 2, 3], 'daily');

        expect($captured['url'])->toBe('https://api.telegram.org/bot123456789:AAAbbbCCCdddEEEfffGGGhhh/sendMessage');
        $body = json_decode($captured['args']['body'], true);
        expect($body['chat_id'])->toBe('-1001234567890');
        expect($body['text'])->toContain('Links: April 15, 2026');
        expect($body['text'])->toContain('https://site.example/roundup-42');
    });

    it('builds a neutral message when post_id is null', function (): void {
        Functions\when('get_option')->justReturn([
            'notify' => ['telegramEnabled' => true, 'telegramBotToken' => '123456789:AAAbbbCCCdddEEEfffGGGhhh', 'telegramChatId' => '123456789'],
        ]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(['ok' => true]));

        $captured = [];
        Functions\when('wp_remote_post')->alias(function ($url, $args) use (&$captured): array {
            $captured = compact('url', 'args');
            return ['response' => ['code' => 200]];
        });

        $this->plugin->maybeSendTelegramNotification(null, [1, 2], 'count');

        $body = json_decode($captured['args']['body'], true);
        expect($body['text'])->toContain('count');
    });

    it('silently returns when wp_remote_post returns a WP_Error', function (): void {
        Functions\when('get_option')->justReturn([
            'notify' => ['telegramEnabled' => true, 'telegramBotToken' => '123456789:AAAbbbCCCdddEEEfffGGGhhh', 'telegramChatId' => '123456789'],
        ]);
        Functions\when('wp_remote_post')->justReturn(Mockery::mock('WP_Error'));
        Functions\when('is_wp_error')->justReturn(true);

        $this->plugin->maybeSendTelegramNotification(42, [1], 'daily');
    })->throwsNoExceptions();

    it('silently returns when the response code is not a 2xx', function (): void {
        Functions\when('get_option')->justReturn([
            'notify' => ['telegramEnabled' => true, 'telegramBotToken' => '123456789:AAAbbbCCCdddEEEfffGGGhhh', 'telegramChatId' => '123456789'],
        ]);
        Functions\when('wp_remote_post')->justReturn(['response' => ['code' => 401]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(401);

        $this->plugin->maybeSendTelegramNotification(42, [1], 'daily');
    })->throwsNoExceptions();

    it('silently returns when Telegram responds with HTTP 200 but ok:false in the body', function (): void {
        Functions\when('get_option')->justReturn([
            'notify' => ['telegramEnabled' => true, 'telegramBotToken' => '123456789:AAAbbbCCCdddEEEfffGGGhhh', 'telegramChatId' => '123456789'],
        ]);
        Functions\when('wp_remote_post')->justReturn(['response' => ['code' => 200]]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(['ok' => false, 'description' => 'chat not found']));

        $this->plugin->maybeSendTelegramNotification(42, [1], 'daily');
    })->throwsNoExceptions();
});

describe('LynxJournal::maybeSendTelegramDmNotification()', function (): void {

    it('does nothing when the DM target is disabled', function (): void {
        Functions\when('get_option')->justReturn([
            'notify' => ['telegramDmEnabled' => false, 'telegramBotToken' => '123:abc', 'telegramDmChatId' => '123'],
        ]);

        $called = false;
        Functions\when('wp_remote_post')->alias(function () use (&$called): array {
            $called = true;
            return [];
        });

        $this->plugin->maybeSendTelegramDmNotification(42, [1, 2, 3], 'daily');

        expect($called)->toBeFalse();
    });

    it('does nothing when enabled but bot token is missing', function (): void {
        Functions\when('get_option')->justReturn([
            'notify' => ['telegramDmEnabled' => true, 'telegramBotToken' => '', 'telegramDmChatId' => '123'],
        ]);

        $called = false;
        Functions\when('wp_remote_post')->alias(function () use (&$called): array {
            $called = true;
            return [];
        });

        $this->plugin->maybeSendTelegramDmNotification(42, [1, 2, 3], 'daily');

        expect($called)->toBeFalse();
    });

    it('does nothing when enabled but DM chat id is missing', function (): void {
        Functions\when('get_option')->justReturn([
            'notify' => ['telegramDmEnabled' => true, 'telegramBotToken' => '123:abc', 'telegramDmChatId' => ''],
        ]);

        $called = false;
        Functions\when('wp_remote_post')->alias(function () use (&$called): array {
            $called = true;
            return [];
        });

        $this->plugin->maybeSendTelegramDmNotification(42, [1, 2, 3], 'daily');

        expect($called)->toBeFalse();
    });

    it('posts a message to the personal chat id using the shared bot token', function (): void {
        Functions\when('get_option')->justReturn([
            'notify' => ['telegramDmEnabled' => true, 'telegramBotToken' => '123456789:AAAbbbCCCdddEEEfffGGGhhh', 'telegramDmChatId' => '987654321'],
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

        $this->plugin->maybeSendTelegramDmNotification(42, [1, 2, 3], 'daily');

        expect($captured['url'])->toBe('https://api.telegram.org/bot123456789:AAAbbbCCCdddEEEfffGGGhhh/sendMessage');
        $body = json_decode($captured['args']['body'], true);
        expect($body['chat_id'])->toBe('987654321');
        expect($body['text'])->toContain('Links: April 15, 2026');
    });
});
