<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Brain\Monkey\Functions;

beforeEach(function (): void {
    Functions\when('__')->returnArg();
    Functions\when('is_wp_error')->alias(fn ($thing) => $thing instanceof WP_Error);
    $this->manager = new LynxJournalNotifyManager();
});

describe('LynxJournalNotifyManager::knownChannelKeys()', function (): void {
    it('returns all 8 channel keys in the fixed registration order', function (): void {
        expect($this->manager->knownChannelKeys())->toBe([
            'email', 'discord', 'slack_channel', 'slack_dm', 'telegram', 'telegram_dm', 'mastodon', 'bluesky',
        ]);
    });
});

describe('LynxJournalNotifyManager::channelFields()', function (): void {
    it('matches each channel class\'s own fields()', function (): void {
        expect($this->manager->channelFields('discord'))->toBe(['discordEnabled', 'discordWebhookUrl']);
        expect($this->manager->channelFields('email'))->toBe(['enabled', 'email']);
        expect($this->manager->channelFields('slack_channel'))->toBe(['slackBotToken', 'slackChannelEnabled', 'slackChannelId']);
        expect($this->manager->channelFields('slack_dm'))->toBe(['slackBotToken', 'slackDmEnabled', 'slackUserId']);
    });

    it('returns an empty array for an unknown channel', function (): void {
        expect($this->manager->channelFields('not_a_channel'))->toBe([]);
    });
});

describe('LynxJournalNotifyManager::channel()', function (): void {
    it('returns the matching channel instance', function (): void {
        expect($this->manager->channel('discord'))->toBeInstanceOf(LynxJournalNotifyDiscordChannel::class);
        expect($this->manager->channel('slack_dm'))->toBeInstanceOf(LynxJournalNotifySlackDmChannel::class);
    });

    it('returns null for an unknown channel', function (): void {
        expect($this->manager->channel('not_a_channel'))->toBeNull();
    });
});

describe('LynxJournalNotifyManager::validateAll()', function (): void {
    it('sets notify.enabled to a strict bool', function (): void {
        $notify = [];
        $this->manager->validateAll($notify);
        expect($notify['enabled'])->toBeFalse();
    });

    it('short-circuits on the first invalid channel, in registration order', function (): void {
        // Both email and discord are invalid here; email is validated first.
        $notify = ['email' => 'not-an-email', 'discordEnabled' => true, 'discordWebhookUrl' => 'not-a-webhook'];
        $error = $this->manager->validateAll($notify);
        expect($error)->toBeInstanceOf(WP_Error::class);
        expect($error->get_error_code())->toBe('invalid_notify_email');
    });

    it('returns null when every channel is valid', function (): void {
        $notify = ['email' => 'a@example.com'];
        expect($this->manager->validateAll($notify))->toBeNull();
    });
});

describe('LynxJournalNotifyManager::validateChannel()', function (): void {
    it('delegates to the named channel only', function (): void {
        $notify = ['discordEnabled' => true, 'discordWebhookUrl' => 'not-a-webhook'];
        $error = $this->manager->validateChannel('discord', $notify);
        expect($error->get_error_code())->toBe('invalid_notify_discord_url');
    });

    it('returns invalid_channel for an unknown channel', function (): void {
        $notify = [];
        $error = $this->manager->validateChannel('not_a_channel', $notify);
        expect($error->get_error_code())->toBe('invalid_channel');
    });
});

describe('LynxJournalNotifyManager::runAfterPublish()', function (): void {
    it('sends only to enabled channels, skipping disabled ones', function (): void {
        Functions\when('get_option')->justReturn([
            'notify' => [
                'discordEnabled' => true, 'discordWebhookUrl' => 'https://discord.com/api/webhooks/1/abc',
                'mastodonEnabled' => false,
            ],
        ]);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(204);

        $calledUrls = [];
        Functions\when('wp_remote_post')->alias(function ($url) use (&$calledUrls): array {
            $calledUrls[] = $url;
            return ['response' => ['code' => 204]];
        });

        $this->manager->runAfterPublish(42, [1, 2], 'daily');

        expect($calledUrls)->toBe(['https://discord.com/api/webhooks/1/abc']);
    });

    it('sends to every enabled channel when more than one is configured', function (): void {
        Functions\when('get_option')->justReturn([
            'notify' => [
                'discordEnabled' => true, 'discordWebhookUrl' => 'https://discord.com/api/webhooks/1/abc',
                'slackChannelEnabled' => true, 'slackBotToken' => 'xoxb-123', 'slackChannelId' => 'C0123456789',
            ],
        ]);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(['ok' => true]));

        $calledUrls = [];
        Functions\when('wp_remote_post')->alias(function ($url) use (&$calledUrls): array {
            $calledUrls[] = $url;
            return ['response' => ['code' => 200]];
        });

        $this->manager->runAfterPublish(42, [1], 'daily');

        expect($calledUrls)->toBe([
            'https://discord.com/api/webhooks/1/abc',
            'https://slack.com/api/chat.postMessage',
        ]);
    });

    it('records a failure when a channel send() returns a WP_Error', function (): void {
        Functions\when('get_option')->alias(function ($key, $default = false) {
            if ($key === 'lynxjournal_schedule') {
                return [
                    'notify' => [
                        'discordEnabled' => true, 'discordWebhookUrl' => 'https://discord.com/api/webhooks/1/abc',
                    ],
                ];
            }
            if ($key === 'lynxjournal_notification_failures') {
                return [];
            }
            return $default;
        });
        Functions\when('wp_remote_post')->justReturn(new WP_Error('http_request_failed', 'Could not resolve host'));

        $captured = null;
        Functions\when('update_option')->alias(function ($key, $value) use (&$captured): bool {
            if ($key === 'lynxjournal_notification_failures') {
                $captured = $value;
            }
            return true;
        });

        $this->manager->runAfterPublish(42, [1], 'daily');

        expect($captured)->toHaveCount(1);
        expect($captured[0]['channel'])->toBe('discord');
        expect($captured[0]['label'])->toBe('Discord');
        expect($captured[0]['message'])->toBe('Could not resolve host');
        expect($captured[0]['ts'])->toBeInt();
    });

    it('records every failing channel from the same run, newest first', function (): void {
        $stored = [];
        Functions\when('get_option')->alias(function ($key, $default = false) use (&$stored) {
            if ($key === 'lynxjournal_schedule') {
                return [
                    'notify' => [
                        'discordEnabled' => true, 'discordWebhookUrl' => 'https://discord.com/api/webhooks/1/abc',
                        'slackChannelEnabled' => true, 'slackBotToken' => 'xoxb-123', 'slackChannelId' => 'C0123456789',
                    ],
                ];
            }
            if ($key === 'lynxjournal_notification_failures') {
                return $stored;
            }
            return $default;
        });
        Functions\when('wp_remote_post')->justReturn(new WP_Error('http_request_failed', 'boom'));

        Functions\when('update_option')->alias(function ($key, $value) use (&$stored): bool {
            if ($key === 'lynxjournal_notification_failures') {
                $stored = $value;
            }
            return true;
        });

        $this->manager->runAfterPublish(42, [1], 'daily');

        expect($stored)->toHaveCount(2);
        expect($stored[0]['channel'])->toBe('slack_channel');
        expect($stored[1]['channel'])->toBe('discord');
    });

    it('caps the stored failures list at 10, dropping the oldest', function (): void {
        $existing = [];
        for ($i = 0; $i < 10; $i++) {
            $existing[] = ['ts' => 1000 + $i, 'channel' => 'old_' . $i, 'label' => 'Old ' . $i, 'message' => 'm'];
        }

        Functions\when('get_option')->alias(function ($key, $default = false) use ($existing) {
            if ($key === 'lynxjournal_schedule') {
                return [
                    'notify' => [
                        'discordEnabled' => true, 'discordWebhookUrl' => 'https://discord.com/api/webhooks/1/abc',
                    ],
                ];
            }
            if ($key === 'lynxjournal_notification_failures') {
                return $existing;
            }
            return $default;
        });
        Functions\when('wp_remote_post')->justReturn(new WP_Error('http_request_failed', 'boom'));

        $captured = null;
        Functions\when('update_option')->alias(function ($key, $value) use (&$captured): bool {
            if ($key === 'lynxjournal_notification_failures') {
                $captured = $value;
            }
            return true;
        });

        $this->manager->runAfterPublish(42, [1], 'daily');

        expect($captured)->toHaveCount(10);
        expect($captured[0]['channel'])->toBe('discord');
        expect(array_column($captured, 'channel'))->not->toContain('old_9');
    });

    it('does not touch the failures option when every channel send() succeeds', function (): void {
        Functions\when('get_option')->alias(function ($key, $default = false) {
            if ($key === 'lynxjournal_schedule') {
                return [
                    'notify' => [
                        'discordEnabled' => true, 'discordWebhookUrl' => 'https://discord.com/api/webhooks/1/abc',
                    ],
                ];
            }
            return $default;
        });
        Functions\when('wp_remote_retrieve_response_code')->justReturn(204);
        Functions\when('wp_remote_post')->justReturn(['response' => ['code' => 204]]);

        $updatedKeys = [];
        Functions\when('update_option')->alias(function ($key) use (&$updatedKeys): bool {
            $updatedKeys[] = $key;
            return true;
        });

        $this->manager->runAfterPublish(42, [1], 'daily');

        expect($updatedKeys)->not->toContain('lynxjournal_notification_failures');
    });
});

describe('LynxJournalNotifyManager::channelLabel()', function (): void {
    it('returns a human label for each known channel key', function (): void {
        expect($this->manager->channelLabel('email'))->toBe('Email');
        expect($this->manager->channelLabel('discord'))->toBe('Discord');
        expect($this->manager->channelLabel('slack_channel'))->toBe('Slack (Channel)');
        expect($this->manager->channelLabel('slack_dm'))->toBe('Slack (DM)');
        expect($this->manager->channelLabel('telegram'))->toBe('Telegram');
        expect($this->manager->channelLabel('telegram_dm'))->toBe('Telegram (DM)');
        expect($this->manager->channelLabel('mastodon'))->toBe('Mastodon');
        expect($this->manager->channelLabel('bluesky'))->toBe('Bluesky');
    });

    it('falls back to the raw key for an unknown channel', function (): void {
        expect($this->manager->channelLabel('not_a_channel'))->toBe('not_a_channel');
    });
});

describe('LynxJournalNotifyManager::test()', function (): void {
    it('records a failure when sendTest() returns a WP_Error', function (): void {
        $notify = ['discordEnabled' => true, 'discordWebhookUrl' => 'https://discord.com/api/webhooks/1/abc'];
        Functions\when('wp_remote_post')->justReturn(new WP_Error('http_request_failed', 'boom'));

        $captured = null;
        Functions\when('update_option')->alias(function ($key, $value) use (&$captured): bool {
            if ($key === 'lynxjournal_notification_failures') {
                $captured = $value;
            }
            return true;
        });
        Functions\when('get_option')->justReturn([]);

        $result = $this->manager->test('discord', $notify);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($captured)->toHaveCount(1);
        expect($captured[0]['channel'])->toBe('discord');
        expect($captured[0]['message'])->toBe('boom');
    });

    it('does not touch the failures option when sendTest() succeeds', function (): void {
        $notify = ['discordEnabled' => true, 'discordWebhookUrl' => 'https://discord.com/api/webhooks/1/abc'];
        Functions\when('wp_remote_retrieve_response_code')->justReturn(204);
        Functions\when('wp_remote_post')->justReturn(['response' => ['code' => 204]]);

        $updatedKeys = [];
        Functions\when('update_option')->alias(function ($key) use (&$updatedKeys): bool {
            $updatedKeys[] = $key;
            return true;
        });

        $result = $this->manager->test('discord', $notify);

        expect($result)->toBeTrue();
        expect($updatedKeys)->not->toContain('lynxjournal_notification_failures');
    });

    it('does not touch the failures option for an unknown channel', function (): void {
        $updatedKeys = [];
        Functions\when('update_option')->alias(function ($key) use (&$updatedKeys): bool {
            $updatedKeys[] = $key;
            return true;
        });

        $result = $this->manager->test('not_a_channel', []);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_channel');
        expect($updatedKeys)->not->toContain('lynxjournal_notification_failures');
    });
});
