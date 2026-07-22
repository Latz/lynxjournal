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

/**
 * Runs validateNotifyChannel() for one channel and returns the error (or null).
 */
function validateChannel(LynxJournal $plugin, string $channel, array $notify): ?WP_Error
{
    $data = ['notify' => $notify];
    return $plugin->validateNotifyChannel($channel, $data);
}

describe('LynxJournal::validateNotifyChannel() — email', function (): void {
    it('accepts a valid email', function (): void {
        expect(validateChannel($this->plugin, 'email', ['enabled' => true, 'email' => 'a@example.com']))->toBeNull();
    });

    it('rejects a malformed email', function (): void {
        $error = validateChannel($this->plugin, 'email', ['enabled' => true, 'email' => 'not-an-email']);
        expect($error)->toBeInstanceOf(WP_Error::class);
        expect($error->get_error_code())->toBe('invalid_notify_email');
    });

    it('allows an empty email (falls back to admin_email)', function (): void {
        expect(validateChannel($this->plugin, 'email', ['enabled' => true, 'email' => '']))->toBeNull();
    });
});

describe('LynxJournal::validateNotifyChannel() — discord', function (): void {
    it('accepts a valid webhook URL', function (): void {
        expect(validateChannel($this->plugin, 'discord', [
            'discordEnabled' => true, 'discordWebhookUrl' => 'https://discord.com/api/webhooks/123/abc',
        ]))->toBeNull();
    });

    it('rejects a malformed webhook URL', function (): void {
        $error = validateChannel($this->plugin, 'discord', [
            'discordEnabled' => true, 'discordWebhookUrl' => 'https://example.com/not-a-webhook',
        ]);
        expect($error->get_error_code())->toBe('invalid_notify_discord_url');
    });

    it('requires a webhook URL when enabled', function (): void {
        $error = validateChannel($this->plugin, 'discord', ['discordEnabled' => true, 'discordWebhookUrl' => '']);
        expect($error->get_error_code())->toBe('invalid_notify_discord_url');
    });

    it('allows a missing webhook URL when disabled', function (): void {
        expect(validateChannel($this->plugin, 'discord', ['discordEnabled' => false, 'discordWebhookUrl' => '']))->toBeNull();
    });
});

describe('LynxJournal::validateNotifyChannel() — slack', function (): void {
    it('accepts valid channel fields', function (): void {
        expect(validateChannel($this->plugin, 'slack_channel', [
            'slackBotToken' => 'xoxb-123-abc', 'slackChannelEnabled' => true, 'slackChannelId' => 'C0123456789',
        ]))->toBeNull();
    });

    it('rejects a malformed bot token', function (): void {
        $error = validateChannel($this->plugin, 'slack_channel', ['slackBotToken' => 'not-a-token']);
        expect($error->get_error_code())->toBe('invalid_notify_slack_token');
    });

    it('rejects a malformed channel id', function (): void {
        $error = validateChannel($this->plugin, 'slack_channel', [
            'slackBotToken' => 'xoxb-123', 'slackChannelEnabled' => true, 'slackChannelId' => 'not-valid',
        ]);
        expect($error->get_error_code())->toBe('invalid_notify_slack_channel');
    });

    it('rejects a malformed user id for the DM target', function (): void {
        $error = validateChannel($this->plugin, 'slack_dm', [
            'slackBotToken' => 'xoxb-123', 'slackDmEnabled' => true, 'slackUserId' => 'not-valid',
        ]);
        expect($error->get_error_code())->toBe('invalid_notify_slack_user');
    });

    it('requires a bot token when channel notifications are enabled', function (): void {
        $error = validateChannel($this->plugin, 'slack_channel', [
            'slackBotToken' => '', 'slackChannelEnabled' => true, 'slackChannelId' => 'C0123456789',
        ]);
        expect($error->get_error_code())->toBe('invalid_notify_slack_token');
    });

    it('allows an empty bot token when both targets are disabled', function (): void {
        expect(validateChannel($this->plugin, 'slack_channel', [
            'slackBotToken' => '', 'slackChannelEnabled' => false, 'slackDmEnabled' => false,
        ]))->toBeNull();
    });
});

describe('LynxJournal::validateNotifyChannel() — telegram', function (): void {
    it('accepts valid fields', function (): void {
        expect(validateChannel($this->plugin, 'telegram', [
            'telegramEnabled' => true, 'telegramBotToken' => '123456789:AAHxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'telegramChatId' => '-1001234567890',
        ]))->toBeNull();
    });

    it('rejects a malformed bot token', function (): void {
        $error = validateChannel($this->plugin, 'telegram', ['telegramBotToken' => 'not-a-token']);
        expect($error->get_error_code())->toBe('invalid_notify_telegram_token');
    });

    it('rejects a malformed chat id', function (): void {
        $error = validateChannel($this->plugin, 'telegram', [
            'telegramEnabled' => true, 'telegramBotToken' => '123456789:AAHxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'telegramChatId' => 'not-numeric',
        ]);
        expect($error->get_error_code())->toBe('invalid_notify_telegram_chat_id');
    });

    it('rejects a malformed DM chat id', function (): void {
        $error = validateChannel($this->plugin, 'telegram_dm', [
            'telegramBotToken' => '123456789:AAHxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'telegramDmEnabled' => true, 'telegramDmChatId' => 'not-numeric',
        ]);
        expect($error->get_error_code())->toBe('invalid_notify_telegram_dm_chat_id');
    });

    it('requires a chat id when enabled', function (): void {
        $error = validateChannel($this->plugin, 'telegram', [
            'telegramEnabled' => true, 'telegramBotToken' => '123456789:AAHxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'telegramChatId' => '',
        ]);
        expect($error->get_error_code())->toBe('invalid_notify_telegram_chat_id');
    });

    it('allows empty fields when disabled', function (): void {
        expect(validateChannel($this->plugin, 'telegram', [
            'telegramEnabled' => false, 'telegramBotToken' => '', 'telegramChatId' => '',
        ]))->toBeNull();
    });
});

describe('LynxJournal::validateNotifyChannel() — mastodon', function (): void {
    it('accepts valid fields', function (): void {
        expect(validateChannel($this->plugin, 'mastodon', [
            'mastodonEnabled' => true,
            'mastodonInstanceUrl' => 'https://mastodon.social',
            'mastodonAccessToken' => 'token123',
            'mastodonRecipient' => '@you@mastodon.social',
        ]))->toBeNull();
    });

    it('rejects a non-https instance URL', function (): void {
        $error = validateChannel($this->plugin, 'mastodon', ['mastodonInstanceUrl' => 'http://mastodon.social']);
        expect($error->get_error_code())->toBe('invalid_notify_mastodon_instance');
    });

    it('rejects a malformed recipient handle', function (): void {
        $error = validateChannel($this->plugin, 'mastodon', ['mastodonRecipient' => 'not-a-handle']);
        expect($error->get_error_code())->toBe('invalid_notify_mastodon_recipient');
    });

    it('requires instance url, token, and recipient when enabled', function (): void {
        $error = validateChannel($this->plugin, 'mastodon', ['mastodonEnabled' => true]);
        expect($error->get_error_code())->toBe('invalid_notify_mastodon_instance');
    });

    it('allows empty fields when disabled', function (): void {
        expect(validateChannel($this->plugin, 'mastodon', ['mastodonEnabled' => false]))->toBeNull();
    });
});

describe('LynxJournal::validateNotifyChannel() — unknown channel', function (): void {
    it('returns invalid_channel for an unrecognized channel key', function (): void {
        $error = validateChannel($this->plugin, 'not_a_real_channel', []);
        expect($error->get_error_code())->toBe('invalid_channel');
    });
});
