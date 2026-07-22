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

describe('LynxJournal::dispatchTestNotification() — missing fields', function (): void {
    it('returns test_missing_field for email when disabled', function (): void {
        $result = $this->plugin->dispatchTestNotification('email', ['enabled' => false]);
        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('test_missing_field');
        expect($result->get_error_message())->toBe('Enable email notifications first.');
    });

    it('returns test_missing_field for discord when webhook url is missing', function (): void {
        $result = $this->plugin->dispatchTestNotification('discord', ['discordEnabled' => true, 'discordWebhookUrl' => '']);
        expect($result->get_error_code())->toBe('test_missing_field');
        expect($result->get_error_message())->toBe('Enable Discord notifications and enter a webhook URL first.');
    });

    it('returns test_missing_field for slack_channel when incomplete', function (): void {
        $result = $this->plugin->dispatchTestNotification('slack_channel', ['slackChannelEnabled' => true]);
        expect($result->get_error_code())->toBe('test_missing_field');
        expect($result->get_error_message())->toBe('Enable Slack channel notifications and fill in the bot token and channel ID first.');
    });

    it('returns test_missing_field for slack_dm when incomplete', function (): void {
        $result = $this->plugin->dispatchTestNotification('slack_dm', ['slackDmEnabled' => true]);
        expect($result->get_error_message())->toBe('Enable Slack DM notifications and fill in the bot token and user ID first.');
    });

    it('returns test_missing_field for telegram when incomplete', function (): void {
        $result = $this->plugin->dispatchTestNotification('telegram', ['telegramEnabled' => true]);
        expect($result->get_error_message())->toBe('Enable Telegram notifications and fill in the bot token and chat ID first.');
    });

    it('returns test_missing_field for telegram_dm when incomplete', function (): void {
        $result = $this->plugin->dispatchTestNotification('telegram_dm', ['telegramDmEnabled' => true]);
        expect($result->get_error_message())->toBe('Enable Telegram DM notifications and fill in the bot token and user chat ID first.');
    });

    it('returns test_missing_field for mastodon when incomplete', function (): void {
        $result = $this->plugin->dispatchTestNotification('mastodon', ['mastodonEnabled' => true]);
        expect($result->get_error_message())->toBe('Enable Mastodon notifications and fill in the instance URL, access token, and recipient handle first.');
    });

    it('returns invalid_channel for an unknown channel', function (): void {
        $result = $this->plugin->dispatchTestNotification('not_a_channel', []);
        expect($result->get_error_code())->toBe('invalid_channel');
    });
});

describe('LynxJournal::dispatchTestNotification() — success', function (): void {
    it('sends a test email', function (): void {
        $captured = [];
        Functions\when('wp_mail')->alias(function ($to, $subject, $message) use (&$captured): bool {
            $captured = compact('to', 'subject', 'message');
            return true;
        });

        $result = $this->plugin->dispatchTestNotification('email', ['enabled' => true, 'email' => 'you@example.com']);

        expect($result)->toBeTrue();
        expect($captured['to'])->toBe('you@example.com');
        expect($captured['subject'])->toBe('[LynxJournal] Test notification');
        expect($captured['message'])->toBe('This is a test notification from LynxJournal.');
    });

    it('sends a test Discord embed', function (): void {
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(204);

        $captured = [];
        Functions\when('wp_remote_post')->alias(function ($url, $args) use (&$captured): array {
            $captured = compact('url', 'args');
            return ['response' => ['code' => 204]];
        });

        $result = $this->plugin->dispatchTestNotification('discord', [
            'discordEnabled' => true, 'discordWebhookUrl' => 'https://discord.com/api/webhooks/1/abc',
        ]);

        expect($result)->toBeTrue();
        expect($captured['url'])->toBe('https://discord.com/api/webhooks/1/abc');
        $body = json_decode($captured['args']['body'], true);
        expect($body['embeds'][0]['title'])->toBe('Test notification');
    });

    it('sends a test Slack channel message', function (): void {
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(['ok' => true]));

        $captured = [];
        Functions\when('wp_remote_post')->alias(function ($url, $args) use (&$captured): array {
            $captured = compact('url', 'args');
            return ['response' => ['code' => 200]];
        });

        $result = $this->plugin->dispatchTestNotification('slack_channel', [
            'slackBotToken' => 'xoxb-123', 'slackChannelEnabled' => true, 'slackChannelId' => 'C0123456789',
        ]);

        expect($result)->toBeTrue();
        expect($captured['url'])->toBe('https://slack.com/api/chat.postMessage');
        $body = json_decode($captured['args']['body'], true);
        expect($body['channel'])->toBe('C0123456789');
        expect($body['blocks'][0]['text']['text'])->toBe('Test notification');
    });

    it('sends a test Telegram message', function (): void {
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(['ok' => true]));

        $captured = [];
        Functions\when('wp_remote_post')->alias(function ($url, $args) use (&$captured): array {
            $captured = compact('url', 'args');
            return ['response' => ['code' => 200]];
        });

        $result = $this->plugin->dispatchTestNotification('telegram', [
            'telegramEnabled' => true, 'telegramBotToken' => '123:abc', 'telegramChatId' => '-100123',
        ]);

        expect($result)->toBeTrue();
        expect($captured['url'])->toBe('https://api.telegram.org/bot123:abc/sendMessage');
        $body = json_decode($captured['args']['body'], true);
        expect($body['chat_id'])->toBe('-100123');
        expect($body['text'])->toBe('This is a test notification from LynxJournal.');
    });

    it('sends a test Mastodon status addressed to the recipient', function (): void {
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);

        $captured = [];
        Functions\when('wp_remote_post')->alias(function ($url, $args) use (&$captured): array {
            $captured = compact('url', 'args');
            return ['response' => ['code' => 200]];
        });

        $result = $this->plugin->dispatchTestNotification('mastodon', [
            'mastodonEnabled' => true,
            'mastodonInstanceUrl' => 'https://mastodon.social',
            'mastodonAccessToken' => 'tok',
            'mastodonRecipient' => '@you@mastodon.social',
        ]);

        expect($result)->toBeTrue();
        expect($captured['url'])->toBe('https://mastodon.social/api/v1/statuses');
        $body = json_decode($captured['args']['body'], true);
        expect($body['status'])->toBe("@you@mastodon.social\nThis is a test notification from LynxJournal.");
        expect($body['visibility'])->toBe('direct');
    });

});
