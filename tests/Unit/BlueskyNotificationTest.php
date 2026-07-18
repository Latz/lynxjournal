<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Brain\Monkey\Functions;

beforeEach(function (): void {
    Functions\when('__')->returnArg();
    Functions\when('add_query_arg')->alias(fn ($key, $value, $url) => $url . '?' . $key . '=' . $value);
    Functions\when('get_transient')->justReturn(false);
    Functions\when('set_transient')->justReturn(true);
    Functions\when('delete_transient')->justReturn(true);
    $this->channel = new LynxJournalNotifyBlueskyChannel();
});

/**
 * Wires up wp_remote_post/wp_remote_get so the full Bluesky handshake
 * (createSession -> resolveHandle -> getConvoForMembers -> sendMessage)
 * succeeds, capturing each request keyed by its URL.
 *
 * @param array $captured Reference populated with one entry per request, keyed by URL.
 * @return void
 */
function mockBlueskyHandshakeSuccess(array &$captured): void
{
    Functions\when('is_wp_error')->justReturn(false);
    Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
    Functions\when('wp_remote_retrieve_body')->alias(fn ($response) => $response['body'] ?? '');

    Functions\when('wp_remote_post')->alias(function ($url, $args) use (&$captured): array {
        $captured[$url] = compact('args');
        if (str_contains($url, 'createSession')) {
            return ['body' => json_encode(['accessJwt' => 'jwt123', 'did' => 'did:plc:sender'])];
        }
        if (str_contains($url, 'getConvoForMembers')) {
            return ['body' => json_encode(['convo' => ['id' => 'convo123']])];
        }
        if (str_contains($url, 'sendMessage')) {
            return ['body' => json_encode(['id' => 'msg123'])];
        }
        return ['body' => '{}'];
    });

    Functions\when('wp_remote_get')->alias(function ($url, $args) use (&$captured): array {
        $captured[$url] = compact('args');
        return ['body' => json_encode(['did' => 'did:plc:recipient'])];
    });
}

describe('LynxJournalNotifyBlueskyChannel::send()', function (): void {

    it('does nothing when bluesky is disabled', function (): void {
        $called = false;
        Functions\when('wp_remote_post')->alias(function () use (&$called): array {
            $called = true;
            return [];
        });

        $this->channel->send(42, [1, 2, 3], 'daily', ['bskyEnabled' => false, 'bskyHandle' => 'you.bsky.social', 'bskyAppPassword' => 'aaaa-bbbb-cccc-dddd', 'bskyRecipient' => 'friend.bsky.social']);

        expect($called)->toBeFalse();
    });

    it('does nothing when enabled but handle is missing', function (): void {
        $called = false;
        Functions\when('wp_remote_post')->alias(function () use (&$called): array {
            $called = true;
            return [];
        });

        $this->channel->send(42, [1, 2, 3], 'daily', ['bskyEnabled' => true, 'bskyHandle' => '', 'bskyAppPassword' => 'aaaa-bbbb-cccc-dddd', 'bskyRecipient' => 'friend.bsky.social']);

        expect($called)->toBeFalse();
    });

    it('does nothing when enabled but app password is missing', function (): void {
        $called = false;
        Functions\when('wp_remote_post')->alias(function () use (&$called): array {
            $called = true;
            return [];
        });

        $this->channel->send(42, [1, 2, 3], 'daily', ['bskyEnabled' => true, 'bskyHandle' => 'you.bsky.social', 'bskyAppPassword' => '', 'bskyRecipient' => 'friend.bsky.social']);

        expect($called)->toBeFalse();
    });

    it('does nothing when enabled but recipient is missing', function (): void {
        $called = false;
        Functions\when('wp_remote_post')->alias(function () use (&$called): array {
            $called = true;
            return [];
        });

        $this->channel->send(42, [1, 2, 3], 'daily', ['bskyEnabled' => true, 'bskyHandle' => 'you.bsky.social', 'bskyAppPassword' => 'aaaa-bbbb-cccc-dddd', 'bskyRecipient' => '']);

        expect($called)->toBeFalse();
    });

    it('sends the raw post title, not HTML-entity-escaped, since Bluesky DMs are plain text', function (): void {
        Functions\when('get_permalink')->justReturn('https://site.example/roundup-42');
        Functions\when('get_the_title')->justReturn('Coffee & Code roundup');

        $captured = [];
        mockBlueskyHandshakeSuccess($captured);

        $this->channel->send(42, [1, 2, 3], 'daily', ['bskyEnabled' => true, 'bskyHandle' => 'you.bsky.social', 'bskyAppPassword' => 'aaaa-bbbb-cccc-dddd', 'bskyRecipient' => 'friend.bsky.social']);

        $sendCall = $captured['https://bsky.chat/xrpc/chat.bsky.convo.sendMessage'];
        $sendBody = json_decode($sendCall['args']['body'], true);
        expect($sendBody['message']['text'])->toContain('Coffee & Code roundup');
        expect($sendBody['message']['text'])->not->toContain('&amp;');
    });

    it('runs the full handshake and sends the DM when enabled with a post_id', function (): void {
        Functions\when('get_permalink')->justReturn('https://site.example/roundup-42');
        Functions\when('get_the_title')->justReturn('Links: April 15, 2026');

        $captured = [];
        mockBlueskyHandshakeSuccess($captured);

        $this->channel->send(42, [1, 2, 3], 'daily', ['bskyEnabled' => true, 'bskyHandle' => 'you.bsky.social', 'bskyAppPassword' => 'aaaa-bbbb-cccc-dddd', 'bskyRecipient' => 'friend.bsky.social']);

        $sessionCall = $captured['https://bsky.social/xrpc/com.atproto.server.createSession'];
        $sessionBody = json_decode($sessionCall['args']['body'], true);
        expect($sessionBody['identifier'])->toBe('you.bsky.social');
        expect($sessionBody['password'])->toBe('aaaa-bbbb-cccc-dddd');

        $convoCall = $captured['https://bsky.chat/xrpc/chat.bsky.convo.getConvoForMembers'];
        $convoBody = json_decode($convoCall['args']['body'], true);
        expect($convoBody['members'])->toBe(['did:plc:sender', 'did:plc:recipient']);
        expect($convoCall['args']['headers']['Authorization'])->toBe('Bearer jwt123');

        $sendCall = $captured['https://bsky.chat/xrpc/chat.bsky.convo.sendMessage'];
        $sendBody = json_decode($sendCall['args']['body'], true);
        expect($sendBody['convoId'])->toBe('convo123');
        expect($sendBody['message']['text'])->toContain('Links: April 15, 2026');
        expect($sendBody['message']['text'])->toContain('https://site.example/roundup-42');
    });

    it('reuses a cached session instead of re-authenticating', function (): void {
        Functions\when('get_permalink')->justReturn('https://site.example/roundup-42');
        Functions\when('get_the_title')->justReturn('Links: April 15, 2026');
        Functions\when('get_transient')->justReturn(['accessJwt' => 'cached-jwt', 'did' => 'did:plc:sender']);

        $captured = [];
        mockBlueskyHandshakeSuccess($captured);

        $this->channel->send(42, [1, 2, 3], 'daily', ['bskyEnabled' => true, 'bskyHandle' => 'you.bsky.social', 'bskyAppPassword' => 'aaaa-bbbb-cccc-dddd', 'bskyRecipient' => 'friend.bsky.social']);

        expect($captured)->not->toHaveKey('https://bsky.social/xrpc/com.atproto.server.createSession');

        $convoCall = $captured['https://bsky.chat/xrpc/chat.bsky.convo.getConvoForMembers'];
        expect($convoCall['args']['headers']['Authorization'])->toBe('Bearer cached-jwt');
    });

    it('drops the cached session when the send fails downstream, so the next attempt re-authenticates', function (): void {
        Functions\when('get_transient')->justReturn(['accessJwt' => 'stale-jwt', 'did' => 'did:plc:sender']);
        Functions\when('wp_remote_get')->justReturn(Mockery::mock('WP_Error'));
        Functions\when('is_wp_error')->alias(fn ($v) => $v instanceof \Mockery\MockInterface);

        $deletedKeys = [];
        Functions\when('delete_transient')->alias(function ($key) use (&$deletedKeys): bool {
            $deletedKeys[] = $key;
            return true;
        });

        $result = $this->channel->send(42, [1], 'daily', ['bskyEnabled' => true, 'bskyHandle' => 'you.bsky.social', 'bskyAppPassword' => 'aaaa-bbbb-cccc-dddd', 'bskyRecipient' => 'friend.bsky.social']);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($deletedKeys)->toHaveCount(1);
        expect($deletedKeys[0])->toContain('lynxjournal_bsky_session_');
    });

    it('builds a neutral message when post_id is null', function (): void {
        $captured = [];
        mockBlueskyHandshakeSuccess($captured);

        $this->channel->send(null, [1, 2], 'count', ['bskyEnabled' => true, 'bskyHandle' => 'you.bsky.social', 'bskyAppPassword' => 'aaaa-bbbb-cccc-dddd', 'bskyRecipient' => 'friend.bsky.social']);

        $sendCall = $captured['https://bsky.chat/xrpc/chat.bsky.convo.sendMessage'];
        $sendBody = json_decode($sendCall['args']['body'], true);
        expect($sendBody['message']['text'])->toContain('count');
    });

    it('returns a WP_Error when session creation fails', function (): void {
        Functions\when('wp_remote_post')->justReturn(Mockery::mock('WP_Error'));
        Functions\when('is_wp_error')->justReturn(true);

        $result = $this->channel->send(42, [1], 'daily', ['bskyEnabled' => true, 'bskyHandle' => 'you.bsky.social', 'bskyAppPassword' => 'aaaa-bbbb-cccc-dddd', 'bskyRecipient' => 'friend.bsky.social']);

        expect($result)->toBeInstanceOf(WP_Error::class);
    });

    it('returns a WP_Error when handle resolution fails', function (): void {
        Functions\when('wp_remote_post')->alias(function ($url) {
            if (str_contains($url, 'createSession')) {
                return ['body' => json_encode(['accessJwt' => 'jwt123', 'did' => 'did:plc:sender'])];
            }
            return [];
        });
        Functions\when('wp_remote_get')->justReturn(Mockery::mock('WP_Error'));
        Functions\when('is_wp_error')->alias(fn ($v) => $v instanceof \Mockery\MockInterface);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->alias(fn ($response) => $response['body'] ?? '');

        $result = $this->channel->send(42, [1], 'daily', ['bskyEnabled' => true, 'bskyHandle' => 'you.bsky.social', 'bskyAppPassword' => 'aaaa-bbbb-cccc-dddd', 'bskyRecipient' => 'friend.bsky.social']);

        expect($result)->toBeInstanceOf(WP_Error::class);
    });
});
