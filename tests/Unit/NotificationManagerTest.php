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

    it('sets notify.adminAlertEnabled to a strict bool', function (): void {
        $notify = [];
        $this->manager->validateAll($notify);
        expect($notify['adminAlertEnabled'])->toBeFalse();
    });

    it('sanitizes a valid notify.adminAlertEmail', function (): void {
        $notify = ['adminAlertEmail' => 'admin@example.com'];
        expect($this->manager->validateAll($notify))->toBeNull();
        expect($notify['adminAlertEmail'])->toBe('admin@example.com');
    });

    it('rejects an invalid notify.adminAlertEmail', function (): void {
        $notify = ['adminAlertEmail' => 'not-an-email'];
        $error = $this->manager->validateAll($notify);
        expect($error)->toBeInstanceOf(WP_Error::class);
        expect($error->get_error_code())->toBe('invalid_admin_alert_email');
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
    it('schedules a dispatch event only for enabled channels, skipping disabled ones', function (): void {
        Functions\when('get_option')->justReturn([
            'notify' => [
                'discordEnabled' => true, 'discordWebhookUrl' => 'https://discord.com/api/webhooks/1/abc',
                'mastodonEnabled' => false,
            ],
        ]);

        $scheduled = [];
        Functions\when('wp_schedule_single_event')->alias(function ($ts, $hook, $args) use (&$scheduled): bool {
            $scheduled[] = $args;
            return true;
        });

        $this->manager->runAfterPublish(42, [1, 2], 'daily');

        expect($scheduled)->toBe([['discord', 42, [1, 2], 'daily']]);
    });

    it('schedules one dispatch event per enabled channel when more than one is configured', function (): void {
        Functions\when('get_option')->justReturn([
            'notify' => [
                'discordEnabled' => true, 'discordWebhookUrl' => 'https://discord.com/api/webhooks/1/abc',
                'slackChannelEnabled' => true, 'slackBotToken' => 'xoxb-123', 'slackChannelId' => 'C0123456789',
            ],
        ]);

        $scheduledHooks = [];
        $scheduledChannels = [];
        Functions\when('wp_schedule_single_event')->alias(function ($ts, $hook, $args) use (&$scheduledHooks, &$scheduledChannels): bool {
            $scheduledHooks[] = $hook;
            $scheduledChannels[] = $args[0];
            return true;
        });

        $this->manager->runAfterPublish(42, [1], 'daily');

        expect($scheduledHooks)->toBe(['lynxjournal_send_notification', 'lynxjournal_send_notification']);
        expect($scheduledChannels)->toBe(['discord', 'slack_channel']);
    });

    it('schedules nothing when no channel is enabled', function (): void {
        Functions\when('get_option')->justReturn(['notify' => []]);

        $scheduled = [];
        Functions\when('wp_schedule_single_event')->alias(function () use (&$scheduled): bool {
            $scheduled[] = true;
            return true;
        });

        $this->manager->runAfterPublish(42, [1], 'daily');

        expect($scheduled)->toBe([]);
    });
});

describe('LynxJournalNotifyManager::dispatchChannelNotification()', function (): void {
    it('sends the named channel using the current notify settings', function (): void {
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

        $this->manager->dispatchChannelNotification('discord', 42, [1, 2], 'daily');

        expect($calledUrls)->toBe(['https://discord.com/api/webhooks/1/abc']);
    });

    it('does nothing for an unknown channel key', function (): void {
        $called = false;
        Functions\when('wp_remote_post')->alias(function () use (&$called): array {
            $called = true;
            return [];
        });

        $this->manager->dispatchChannelNotification('not_a_channel', 42, [1], 'daily');

        expect($called)->toBeFalse();
    });

    it('records a failure when the channel send() returns a WP_Error', function (): void {
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

        $this->manager->dispatchChannelNotification('discord', 42, [1], 'daily');

        expect($captured)->toHaveCount(1);
        expect($captured[0]['channel'])->toBe('discord');
        expect($captured[0]['label'])->toBe('Discord');
        expect($captured[0]['message'])->toBe('Could not resolve host');
        expect($captured[0]['ts'])->toBeInt();
    });

    it('appends to existing failures, newest first, across separate dispatch calls', function (): void {
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

        $this->manager->dispatchChannelNotification('discord', 42, [1], 'daily');
        $this->manager->dispatchChannelNotification('slack_channel', 42, [1], 'daily');

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

        $this->manager->dispatchChannelNotification('discord', 42, [1], 'daily');

        expect($captured)->toHaveCount(10);
        expect($captured[0]['channel'])->toBe('discord');
        expect(array_column($captured, 'channel'))->not->toContain('old_9');
    });

    it('emails the admin-alert address when notify.adminAlertEnabled is true', function (): void {
        Functions\when('get_option')->alias(function ($key, $default = false) {
            if ($key === 'lynxjournal_schedule') {
                return [
                    'notify' => [
                        'discordEnabled' => true, 'discordWebhookUrl' => 'https://discord.com/api/webhooks/1/abc',
                        'adminAlertEnabled' => true, 'adminAlertEmail' => 'alerts@example.com',
                    ],
                ];
            }
            if ($key === 'lynxjournal_notification_failures') {
                return [];
            }
            return $default;
        });
        Functions\when('wp_remote_post')->justReturn(new WP_Error('http_request_failed', 'Could not resolve host'));
        Functions\when('update_option')->justReturn(true);

        $captured = null;
        Functions\when('wp_mail')->alias(function ($to, $subject, $message) use (&$captured): bool {
            $captured = compact('to', 'subject', 'message');
            return true;
        });

        $this->manager->dispatchChannelNotification('discord', 42, [1], 'daily');

        expect($captured['to'])->toBe('alerts@example.com');
        expect($captured['subject'])->toContain('Discord');
        expect($captured['message'])->toContain('Could not resolve host');
    });

    it('falls back to admin_email when notify.adminAlertEmail is blank', function (): void {
        Functions\when('get_option')->alias(function ($key, $default = false) {
            if ($key === 'lynxjournal_schedule') {
                return [
                    'notify' => [
                        'discordEnabled' => true, 'discordWebhookUrl' => 'https://discord.com/api/webhooks/1/abc',
                        'adminAlertEnabled' => true,
                    ],
                ];
            }
            if ($key === 'lynxjournal_notification_failures') {
                return [];
            }
            if ($key === 'admin_email') {
                return 'site-admin@example.com';
            }
            return $default;
        });
        Functions\when('wp_remote_post')->justReturn(new WP_Error('http_request_failed', 'boom'));
        Functions\when('update_option')->justReturn(true);

        $captured = null;
        Functions\when('wp_mail')->alias(function ($to, $subject, $message) use (&$captured): bool {
            $captured = compact('to', 'subject', 'message');
            return true;
        });

        $this->manager->dispatchChannelNotification('discord', 42, [1], 'daily');

        expect($captured['to'])->toBe('site-admin@example.com');
    });

    it('does not email the admin when notify.adminAlertEnabled is false', function (): void {
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
        Functions\when('wp_remote_post')->justReturn(new WP_Error('http_request_failed', 'boom'));
        Functions\when('update_option')->justReturn(true);

        $mailed = false;
        Functions\when('wp_mail')->alias(function () use (&$mailed): bool {
            $mailed = true;
            return true;
        });

        $this->manager->dispatchChannelNotification('discord', 42, [1], 'daily');

        expect($mailed)->toBeFalse();
    });

    it('does not touch the failures option when send() succeeds', function (): void {
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

        $this->manager->dispatchChannelNotification('discord', 42, [1], 'daily');

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
