<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Brain\Monkey\Functions;

beforeEach(function (): void {
    Functions\when('__')->returnArg();
    Functions\when('is_wp_error')->alias(fn ($thing) => $thing instanceof WP_Error);
    $this->manager = new LynxJournal_Notify_Manager();
});

describe('LynxJournal_Notify_Manager::knownChannelKeys()', function (): void {
    it('returns all 8 channel keys in the fixed registration order', function (): void {
        expect($this->manager->knownChannelKeys())->toBe([
            'email', 'discord', 'slack_channel', 'slack_dm', 'telegram', 'telegram_dm', 'mastodon', 'bluesky',
        ]);
    });
});

describe('LynxJournal_Notify_Manager::channelFields()', function (): void {
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

describe('LynxJournal_Notify_Manager::channel()', function (): void {
    it('returns the matching channel instance', function (): void {
        expect($this->manager->channel('discord'))->toBeInstanceOf(LynxJournal_Notify_DiscordChannel::class);
        expect($this->manager->channel('slack_dm'))->toBeInstanceOf(LynxJournal_Notify_SlackDmChannel::class);
    });

    it('returns null for an unknown channel', function (): void {
        expect($this->manager->channel('not_a_channel'))->toBeNull();
    });
});

describe('LynxJournal_Notify_Manager::validateAll()', function (): void {
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

describe('LynxJournal_Notify_Manager::validateChannel()', function (): void {
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

describe('LynxJournal_Notify_Manager::runAfterPublish()', function (): void {
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
});
