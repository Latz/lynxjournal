<?php

declare(strict_types=1);

if (!defined("ABSPATH")) {
    exit;
}

use Brain\Monkey\Functions;

beforeEach(function (): void {
    Functions\when('__')->returnArg();
    Functions\when('user_can')->justReturn(true);
    $this->plugin = Mockery::mock(LynxJournal::class)->makePartial();
});

describe('LynxJournal::validateScheduleConfig()', function (): void {

    it('returns 400 unknown_keys when an unrecognized top-level key is present', function (): void {
        $result = $this->plugin->validateScheduleConfig(['mode' => 'daily', 'foo' => 'bar']);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('unknown_keys');
        expect($result->get_error_data()['status'])->toBe(400);
    });

    it('returns 400 invalid_mode when mode key is missing', function (): void {
        $result = $this->plugin->validateScheduleConfig(['times' => ['09:00']]);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_mode');
        expect($result->get_error_data()['status'])->toBe(400);
    });

    it('returns 400 invalid_mode when mode is not in the whitelist', function (): void {
        $result = $this->plugin->validateScheduleConfig(['mode' => 'invalid']);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_mode');
    });

    it('returns 400 invalid_times when times is not an array', function (): void {
        $result = $this->plugin->validateScheduleConfig(['mode' => 'daily', 'times' => '09:00']);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_times');
        expect($result->get_error_data()['status'])->toBe(400);
    });

    it('returns 400 invalid_times when a times entry is not an HH:MM string', function (): void {
        $result = $this->plugin->validateScheduleConfig(['mode' => 'daily', 'times' => ['9am']]);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_times');
    });

    it('returns 400 invalid_recurrence when recurrence is not an array', function (): void {
        $result = $this->plugin->validateScheduleConfig(['mode' => 'weekly', 'recurrence' => 'bad']);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_recurrence');
        expect($result->get_error_data()['status'])->toBe(400);
    });

    it('returns 400 invalid_trigger when trigger is not an array', function (): void {
        $result = $this->plugin->validateScheduleConfig(['mode' => 'count', 'trigger' => 'bad']);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_trigger');
        expect($result->get_error_data()['status'])->toBe(400);
    });

    it('returns 400 invalid_trigger when trigger.count is zero', function (): void {
        $result = $this->plugin->validateScheduleConfig(['mode' => 'count', 'trigger' => ['count' => 0]]);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_trigger');
    });

    it('returns 400 invalid_trigger when trigger.days is negative', function (): void {
        $result = $this->plugin->validateScheduleConfig(['mode' => 'age', 'trigger' => ['days' => -3]]);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_trigger');
    });

    it('returns the sanitized payload for valid input', function (): void {
        $data   = ['mode' => 'daily', 'times' => ['09:00'], 'trigger' => ['count' => '5']];
        $result = $this->plugin->validateScheduleConfig($data);

        expect($result)->toBeArray();
        expect($result['mode'])->toBe('daily');
    });

    it('normalizes times by deduplicating and sorting', function (): void {
        $data   = ['mode' => 'daily', 'times' => ['09:00', '08:00', '09:00']];
        $result = $this->plugin->validateScheduleConfig($data);

        expect($result['times'])->toBe(['08:00', '09:00']);
    });

    it('coerces trigger.count and trigger.days to integers', function (): void {
        $data   = ['mode' => 'count', 'trigger' => ['count' => '10', 'days' => '7']];
        $result = $this->plugin->validateScheduleConfig($data);

        expect($result['trigger']['count'])->toBe(10);
        expect($result['trigger']['days'])->toBe(7);
    });

    it('accepts publishAs as a known key', function (): void {
        $data   = ['mode' => 'daily', 'publishAs' => 5];
        $result = $this->plugin->validateScheduleConfig($data);

        expect($result)->toBeArray();
        expect($result['publishAs'])->toBe(5);
    });

    it('coerces publishAs to integer', function (): void {
        $data   = ['mode' => 'daily', 'publishAs' => '3'];
        $result = $this->plugin->validateScheduleConfig($data);

        expect($result['publishAs'])->toBe(3);
    });

    it('returns 400 invalid_publish_as when publishAs is negative', function (): void {
        $result = $this->plugin->validateScheduleConfig(['mode' => 'daily', 'publishAs' => -1]);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_publish_as');
        expect($result->get_error_data()['status'])->toBe(400);
    });

    it('accepts post_status "publish" as a valid value', function (): void {
        $result = $this->plugin->validateScheduleConfig(['mode' => 'daily', 'post_status' => 'publish']);

        expect($result)->toBeArray();
        expect($result['post_status'])->toBe('publish');
    });

    it('accepts post_status "draft" as a valid value', function (): void {
        $result = $this->plugin->validateScheduleConfig(['mode' => 'daily', 'post_status' => 'draft']);

        expect($result)->toBeArray();
        expect($result['post_status'])->toBe('draft');
    });

    it('returns 400 invalid_post_status for an unrecognized value', function (): void {
        $result = $this->plugin->validateScheduleConfig(['mode' => 'daily', 'post_status' => 'pending']);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_post_status');
        expect($result->get_error_data()['status'])->toBe(400);
    });

    it('accepts notify as a known top-level key', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['enabled' => true, 'email' => ''],
        ]);

        expect($result)->toBeArray();
    });

    it('returns 400 invalid_notify when notify is not an array', function (): void {
        $result = $this->plugin->validateScheduleConfig(['mode' => 'daily', 'notify' => 'yes']);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_notify');
        expect($result->get_error_data()['status'])->toBe(400);
    });

    it('coerces notify.enabled to boolean', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['enabled' => 1],
        ]);

        expect($result)->toBeArray();
        expect($result['notify']['enabled'])->toBeBool();
        expect($result['notify']['enabled'])->toBeTrue();
    });

    it('returns 400 invalid_notify_email when notify.email is an invalid address', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['enabled' => true, 'email' => 'not-an-email'],
        ]);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_notify_email');
        expect($result->get_error_data()['status'])->toBe(400);
    });

    it('accepts a valid email address in notify.email', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['enabled' => true, 'email' => 'admin@example.com'],
        ]);

        expect($result)->toBeArray();
        expect($result['notify']['email'])->toBe('admin@example.com');
    });

    it('allows notify.email to be empty (falls back to admin email at send time)', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['enabled' => true, 'email' => ''],
        ]);

        expect($result)->toBeArray();
    });

    it('returns 400 invalid_notify_discord_url when discordEnabled is truthy but no URL is given', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['discordEnabled' => 1],
        ]);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_notify_discord_url');
    });

    it('accepts a valid discord.com webhook URL', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['discordEnabled' => true, 'discordWebhookUrl' => 'https://discord.com/api/webhooks/123456789/abcDEF_-token'],
        ]);

        expect($result)->toBeArray();
        expect($result['notify']['discordEnabled'])->toBeTrue();
        expect($result['notify']['discordWebhookUrl'])->toBe('https://discord.com/api/webhooks/123456789/abcDEF_-token');
    });

    it('accepts a valid webhook URL with an API version segment', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['discordEnabled' => true, 'discordWebhookUrl' => 'https://discord.com/api/v10/webhooks/123456789/abcDEF'],
        ]);

        expect($result)->toBeArray();
    });

    it('accepts a valid webhook URL on the legacy discordapp.com host', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['discordEnabled' => true, 'discordWebhookUrl' => 'https://discordapp.com/api/webhooks/123456789/abcDEF'],
        ]);

        expect($result)->toBeArray();
    });

    it('returns 400 invalid_notify_discord_url for a non-Discord host', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['discordEnabled' => true, 'discordWebhookUrl' => 'https://evil.example.com/api/webhooks/123/abc'],
        ]);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_notify_discord_url');
        expect($result->get_error_data()['status'])->toBe(400);
    });

    it('returns 400 invalid_notify_discord_url for a malformed webhook path', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['discordEnabled' => true, 'discordWebhookUrl' => 'https://discord.com/not-a-webhook'],
        ]);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_notify_discord_url');
    });

    it('returns 400 invalid_notify_discord_url when discordEnabled is true but the URL is empty', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['discordEnabled' => true, 'discordWebhookUrl' => ''],
        ]);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_notify_discord_url');
    });

    it('allows discordWebhookUrl to be empty when discordEnabled is false', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['discordEnabled' => false, 'discordWebhookUrl' => ''],
        ]);

        expect($result)->toBeArray();
    });

    it('accepts a valid Slack bot token format', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['slackBotToken' => 'xoxb-123456789-abcDEF'],
        ]);

        expect($result)->toBeArray();
        expect($result['notify']['slackBotToken'])->toBe('xoxb-123456789-abcDEF');
    });

    it('returns 400 invalid_notify_slack_token for a malformed bot token', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['slackBotToken' => 'not-a-token'],
        ]);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_notify_slack_token');
        expect($result->get_error_data()['status'])->toBe(400);
    });

    it('returns 400 invalid_notify_slack_token when slackChannelEnabled is true but no token is given', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['slackChannelEnabled' => true, 'slackChannelId' => 'C0123456789'],
        ]);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_notify_slack_token');
    });

    it('returns 400 invalid_notify_slack_channel when slackChannelEnabled is true but the channel id is malformed', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['slackChannelEnabled' => true, 'slackBotToken' => 'xoxb-123', 'slackChannelId' => 'not-a-channel'],
        ]);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_notify_slack_channel');
        expect($result->get_error_data()['status'])->toBe(400);
    });

    it('accepts a valid slack channel id starting with C', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['slackChannelEnabled' => true, 'slackBotToken' => 'xoxb-123', 'slackChannelId' => 'C0123456789'],
        ]);

        expect($result)->toBeArray();
        expect($result['notify']['slackChannelId'])->toBe('C0123456789');
    });

    it('accepts a valid slack channel id starting with G (private channel)', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['slackChannelEnabled' => true, 'slackBotToken' => 'xoxb-123', 'slackChannelId' => 'G0123456789'],
        ]);

        expect($result)->toBeArray();
    });

    it('returns 400 invalid_notify_slack_token when slackDmEnabled is true but no token is given', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['slackDmEnabled' => true, 'slackUserId' => 'U0123456789'],
        ]);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_notify_slack_token');
    });

    it('returns 400 invalid_notify_slack_user when slackDmEnabled is true but the user id is malformed', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['slackDmEnabled' => true, 'slackBotToken' => 'xoxb-123', 'slackUserId' => 'not-a-user'],
        ]);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_notify_slack_user');
        expect($result->get_error_data()['status'])->toBe(400);
    });

    it('accepts a valid slack user id', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['slackDmEnabled' => true, 'slackBotToken' => 'xoxb-123', 'slackUserId' => 'U0123456789'],
        ]);

        expect($result)->toBeArray();
        expect($result['notify']['slackUserId'])->toBe('U0123456789');
    });

    it('allows all Slack fields to be empty/false when neither toggle is enabled', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['slackChannelEnabled' => false, 'slackDmEnabled' => false],
        ]);

        expect($result)->toBeArray();
    });

    it('returns 400 invalid_notify_telegram_token when telegramEnabled is true but no bot token is given', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['telegramEnabled' => true, 'telegramChatId' => '123456789'],
        ]);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_notify_telegram_token');
        expect($result->get_error_data()['status'])->toBe(400);
    });

    it('returns 400 invalid_notify_telegram_chat_id when telegramEnabled is true but no chat id is given', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['telegramEnabled' => true, 'telegramBotToken' => '123456789:AAAbbbCCCdddEEEfffGGGhhh'],
        ]);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_notify_telegram_chat_id');
    });

    it('returns 400 invalid_notify_telegram_token for a malformed bot token', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['telegramBotToken' => 'not-a-token'],
        ]);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_notify_telegram_token');
        expect($result->get_error_data()['status'])->toBe(400);
    });

    it('returns 400 invalid_notify_telegram_chat_id for a malformed chat id', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['telegramChatId' => 'not-a-chat-id'],
        ]);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_notify_telegram_chat_id');
    });

    it('accepts a valid negative Telegram chat id for a group or channel', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['telegramEnabled' => true, 'telegramBotToken' => '123456789:AAAbbbCCCdddEEEfffGGGhhh', 'telegramChatId' => '-1001234567890'],
        ]);

        expect($result)->toBeArray();
        expect($result['notify']['telegramChatId'])->toBe('-1001234567890');
    });

    it('accepts a valid positive Telegram chat id for a personal chat', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['telegramEnabled' => true, 'telegramBotToken' => '123456789:AAAbbbCCCdddEEEfffGGGhhh', 'telegramChatId' => '123456789'],
        ]);

        expect($result)->toBeArray();
        expect($result['notify']['telegramChatId'])->toBe('123456789');
    });

    it('allows all Telegram fields to be empty/false when telegramEnabled is false', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['telegramEnabled' => false],
        ]);

        expect($result)->toBeArray();
    });

    it('returns 400 invalid_notify_telegram_token when telegramDmEnabled is true but no bot token is given', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['telegramDmEnabled' => true, 'telegramDmChatId' => '987654321'],
        ]);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_notify_telegram_token');
        expect($result->get_error_data()['status'])->toBe(400);
    });

    it('returns 400 invalid_notify_telegram_dm_chat_id when telegramDmEnabled is true but no chat id is given', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['telegramDmEnabled' => true, 'telegramBotToken' => '123456789:AAAbbbCCCdddEEEfffGGGhhh'],
        ]);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_notify_telegram_dm_chat_id');
    });

    it('returns 400 invalid_notify_telegram_dm_chat_id for a malformed DM chat id', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['telegramDmChatId' => 'not-a-chat-id'],
        ]);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_notify_telegram_dm_chat_id');
    });

    it('accepts a valid positive Telegram DM chat id and does not clobber the channel target', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => [
                'telegramEnabled'   => true,
                'telegramChatId'    => '-1001234567890',
                'telegramDmEnabled' => true,
                'telegramBotToken'  => '123456789:AAAbbbCCCdddEEEfffGGGhhh',
                'telegramDmChatId'  => '987654321',
            ],
        ]);

        expect($result)->toBeArray();
        expect($result['notify']['telegramChatId'])->toBe('-1001234567890');
        expect($result['notify']['telegramDmChatId'])->toBe('987654321');
    });

    it('allows all Telegram DM fields to be empty/false when telegramDmEnabled is false', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['telegramDmEnabled' => false],
        ]);

        expect($result)->toBeArray();
    });

    it('returns 400 invalid_notify_mastodon_instance when mastodonEnabled is true but no instance url is given', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['mastodonEnabled' => true, 'mastodonAccessToken' => 'token123', 'mastodonRecipient' => '@you@mastodon.social'],
        ]);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_notify_mastodon_instance');
        expect($result->get_error_data()['status'])->toBe(400);
    });

    it('returns 400 invalid_notify_mastodon_token when mastodonEnabled is true but no access token is given', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['mastodonEnabled' => true, 'mastodonInstanceUrl' => 'https://mastodon.social', 'mastodonRecipient' => '@you@mastodon.social'],
        ]);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_notify_mastodon_token');
    });

    it('returns 400 invalid_notify_mastodon_recipient when mastodonEnabled is true but no recipient is given', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['mastodonEnabled' => true, 'mastodonInstanceUrl' => 'https://mastodon.social', 'mastodonAccessToken' => 'token123'],
        ]);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_notify_mastodon_recipient');
    });

    it('returns 400 invalid_notify_mastodon_instance for a non-https instance url', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['mastodonInstanceUrl' => 'http://mastodon.social'],
        ]);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_notify_mastodon_instance');
        expect($result->get_error_data()['status'])->toBe(400);
    });

    it('returns 400 invalid_notify_mastodon_recipient for a malformed handle', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['mastodonRecipient' => 'not-a-handle'],
        ]);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_notify_mastodon_recipient');
    });

    it('accepts valid Mastodon settings', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['mastodonEnabled' => true, 'mastodonInstanceUrl' => 'https://mastodon.social', 'mastodonAccessToken' => 'token123', 'mastodonRecipient' => '@you@mastodon.social'],
        ]);

        expect($result)->toBeArray();
        expect($result['notify']['mastodonInstanceUrl'])->toBe('https://mastodon.social');
        expect($result['notify']['mastodonRecipient'])->toBe('@you@mastodon.social');
    });

    it('allows all Mastodon fields to be empty/false when mastodonEnabled is false', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['mastodonEnabled' => false],
        ]);

        expect($result)->toBeArray();
    });
});
