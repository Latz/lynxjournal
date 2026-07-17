<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Brain\Monkey\Functions;

beforeEach(function (): void {
    Functions\when('is_wp_error')->alias(fn ($thing) => $thing instanceof WP_Error);
});

describe('LynxJournal_Notify_Http::postJson()', function (): void {
    it('returns the raw response array on success', function (): void {
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_post')->justReturn(['response' => ['code' => 200], 'body' => '{}']);

        $result = LynxJournal_Notify_Http::postJson('https://example.com/x', ['a' => 1], [], 'some_error');

        expect($result)->toBe(['response' => ['code' => 200], 'body' => '{}']);
    });

    it('encodes the body as JSON and merges the Content-Type header', function (): void {
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);

        $captured = [];
        Functions\when('wp_remote_post')->alias(function ($url, $args) use (&$captured): array {
            $captured = compact('url', 'args');
            return ['response' => ['code' => 200]];
        });

        LynxJournal_Notify_Http::postJson('https://example.com/x', ['a' => 1], ['Authorization' => 'Bearer tok'], 'some_error');

        expect($captured['url'])->toBe('https://example.com/x');
        expect($captured['args']['headers'])->toBe(['Content-Type' => 'application/json', 'Authorization' => 'Bearer tok']);
        expect($captured['args']['body'])->toBe('{"a":1}');
        expect($captured['args']['timeout'])->toBe(8);
    });

    it('respects a custom timeout', function (): void {
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);

        $captured = [];
        Functions\when('wp_remote_post')->alias(function ($url, $args) use (&$captured): array {
            $captured = compact('args');
            return ['response' => ['code' => 200]];
        });

        LynxJournal_Notify_Http::postJson('https://example.com/x', [], [], 'some_error', 20);

        expect($captured['args']['timeout'])->toBe(20);
    });

    it('passes a WP_Error from wp_remote_post through unchanged', function (): void {
        $transportError = new WP_Error('http_request_failed', 'Could not resolve host');
        Functions\when('wp_remote_post')->justReturn($transportError);

        $result = LynxJournal_Notify_Http::postJson('https://example.com/x', [], [], 'some_error');

        expect($result)->toBe($transportError);
    });

    it('returns a WP_Error tagged with the caller error code when the response is >= 300', function (): void {
        Functions\when('wp_remote_retrieve_response_code')->justReturn(404);
        Functions\when('wp_remote_retrieve_body')->justReturn('Not Found');
        Functions\when('wp_remote_post')->justReturn(['response' => ['code' => 404]]);

        $result = LynxJournal_Notify_Http::postJson('https://example.com/x', [], [], 'my_custom_error_code');

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('my_custom_error_code');
        expect($result->get_error_message())->toBe('Not Found');
        expect($result->get_error_data()['status'])->toBe(500);
    });
});
