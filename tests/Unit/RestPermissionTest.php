<?php

declare(strict_types=1);

if (!defined("ABSPATH")) {
    exit;
}

use Brain\Monkey\Functions;

/**
 * Tests for linkdigest_rest_permission_check()
 *
 * Two auth paths:
 *  1. API key in X-LinkDigest-API-Key header — compared with stored key via hash_equals
 *  2. Fallback: current_user_can('edit_posts')
 */

beforeEach(function (): void {
    $this->plugin = Mockery::mock(LinkDigest::class)->makePartial();
});

describe('LinkDigest::restPermissionCheck()', function (): void {

    it('grants access when a valid API key matches the stored key', function (): void {
        $key = 'super-secret-key-abc123';

        Functions\when('get_option')
            ->alias(fn($opt) => $opt === 'linkdigest_api_key' ? $key : false);

        $request = linkdigest_make_request([], ['X-LinkDigest-API-Key' => $key]);

        $result = $this->plugin->restPermissionCheck($request);

        expect($result)->toBeTrue();
    });

    it('denies access when the API key does not match the stored key', function (): void {
        Functions\when('get_option')
            ->alias(fn($opt) => $opt === 'linkdigest_api_key' ? 'correct-key' : false);
        Functions\when('current_user_can')->justReturn(false);

        $request = linkdigest_make_request([], ['X-LinkDigest-API-Key' => 'wrong-key']);

        $result = $this->plugin->restPermissionCheck($request);

        expect($result)->toBeFalse();
    });

    it('falls back to current_user_can when no API key is sent', function (): void {
        Functions\when('get_option')->justReturn('some-stored-key');
        Functions\when('current_user_can')->justReturn(true);

        $request = linkdigest_make_request(); // no API key header

        $result = $this->plugin->restPermissionCheck($request);

        expect($result)->toBeTrue();
    });

    it('falls back to current_user_can when no key is stored in options', function (): void {
        Functions\when('get_option')->justReturn(''); // empty stored key
        Functions\when('current_user_can')->justReturn(true);

        $request = linkdigest_make_request([], ['X-LinkDigest-API-Key' => 'any-key']);

        $result = $this->plugin->restPermissionCheck($request);

        expect($result)->toBeTrue();
    });

    it('denies access when no key is sent and user lacks edit_posts', function (): void {
        Functions\when('get_option')->justReturn('');
        Functions\when('current_user_can')->justReturn(false);

        $request = linkdigest_make_request();

        $result = $this->plugin->restPermissionCheck($request);

        expect($result)->toBeFalse();
    });
});
