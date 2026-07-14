<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Brain\Monkey\Functions;

beforeEach(function (): void {
    Functions\when('__')->returnArg();
    Functions\when('rest_ensure_response')->alias(fn (mixed $data) => new WP_REST_Response($data));
    Functions\when('is_wp_error')->alias(fn ($thing) => $thing instanceof WP_Error);
    $this->plugin = Mockery::mock(LynxJournal::class)->makePartial();
});

describe('LynxJournal::restTestNotification()', function (): void {
    it('returns invalid_channel for an unknown channel', function (): void {
        $request = lynxjournal_make_request(['channel' => 'not_a_channel', 'notify' => []]);

        $result = $this->plugin->restTestNotification($request);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_channel');
    });

    it('passes through a validation error for malformed fields', function (): void {
        $request = lynxjournal_make_request(['channel' => 'discord', 'notify' => [
            'discordEnabled' => true, 'discordWebhookUrl' => 'https://example.com/not-a-webhook',
        ]]);

        $result = $this->plugin->restTestNotification($request);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_notify_discord_url');
    });

    it('returns success when the test notification sends', function (): void {
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(204);
        Functions\when('wp_remote_post')->justReturn(['response' => ['code' => 204]]);

        $request = lynxjournal_make_request(['channel' => 'discord', 'notify' => [
            'discordEnabled' => true, 'discordWebhookUrl' => 'https://discord.com/api/webhooks/1/abc',
        ]]);

        $result = $this->plugin->restTestNotification($request);

        expect($result->get_data()['success'])->toBeTrue();
    });
});

describe('LynxJournal::restSaveNotification()', function (): void {
    it('returns invalid_channel for an unknown channel', function (): void {
        $request = lynxjournal_make_request(['channel' => 'not_a_channel', 'notify' => []]);

        $result = $this->plugin->restSaveNotification($request);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_channel');
    });

    it('passes through a validation error for malformed fields', function (): void {
        $request = lynxjournal_make_request(['channel' => 'bluesky', 'notify' => [
            'bskyEnabled' => true, 'bskyHandle' => 'not a handle',
        ]]);

        $result = $this->plugin->restSaveNotification($request);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_notify_bsky_handle');
    });

    it('saves only the given channel\'s fields, leaving its sibling target and unrelated channels untouched', function (): void {
        Functions\when('get_option')->justReturn([
            'notify' => [
                'discordEnabled' => true, 'discordWebhookUrl' => 'https://discord.com/api/webhooks/9/xyz',
                'slackBotToken' => 'xoxb-old',
                'slackChannelEnabled' => false, 'slackChannelId' => '',
                'slackDmEnabled' => true, 'slackUserId' => 'U0123456789',
            ],
        ]);

        $saved = null;
        Functions\when('update_option')->alias(function ($name, $value) use (&$saved): bool {
            $saved = $value;
            return true;
        });

        $request = lynxjournal_make_request(['channel' => 'slack_channel', 'notify' => [
            'slackBotToken' => 'xoxb-new', 'slackChannelEnabled' => true, 'slackChannelId' => 'C0999999999',
        ]]);

        $result = $this->plugin->restSaveNotification($request);

        expect($result->get_data()['success'])->toBeTrue();
        // The channel's own (and shared) fields were updated.
        expect($saved['notify']['slackBotToken'])->toBe('xoxb-new');
        expect($saved['notify']['slackChannelEnabled'])->toBeTrue();
        expect($saved['notify']['slackChannelId'])->toBe('C0999999999');
        // The sibling DM target's fields were left exactly as they were.
        expect($saved['notify']['slackDmEnabled'])->toBeTrue();
        expect($saved['notify']['slackUserId'])->toBe('U0123456789');
        // An unrelated channel's fields were left exactly as they were.
        expect($saved['notify']['discordEnabled'])->toBeTrue();
        expect($saved['notify']['discordWebhookUrl'])->toBe('https://discord.com/api/webhooks/9/xyz');
    });

    it('initializes the notify option when none was stored yet', function (): void {
        Functions\when('get_option')->justReturn([]);

        $saved = null;
        Functions\when('update_option')->alias(function ($name, $value) use (&$saved): bool {
            $saved = $value;
            return true;
        });

        $request = lynxjournal_make_request(['channel' => 'discord', 'notify' => [
            'discordEnabled' => true, 'discordWebhookUrl' => 'https://discord.com/api/webhooks/1/abc',
        ]]);

        $result = $this->plugin->restSaveNotification($request);

        expect($result->get_data()['success'])->toBeTrue();
        expect($saved['notify']['discordWebhookUrl'])->toBe('https://discord.com/api/webhooks/1/abc');
    });

    it('returns only the saved channel\'s fields in the response', function (): void {
        Functions\when('get_option')->justReturn(['notify' => []]);
        Functions\when('update_option')->justReturn(true);

        $request = lynxjournal_make_request(['channel' => 'discord', 'notify' => [
            'discordEnabled' => true, 'discordWebhookUrl' => 'https://discord.com/api/webhooks/1/abc',
        ]]);

        $result = $this->plugin->restSaveNotification($request);

        expect(array_keys($result->get_data()['notify']))->toBe(['discordEnabled', 'discordWebhookUrl']);
    });
});
