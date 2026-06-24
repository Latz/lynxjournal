<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Brain\Monkey\Functions;

beforeEach(function (): void {
    Functions\when('__')->returnArg();
    Functions\when('rest_ensure_response')->returnArg();
    $this->plugin = Mockery::mock(LynxJournal::class)->makePartial();
});

describe('LynxJournal::restGetApiKey()', function (): void {

    it('returns 404 WP_Error when no API key is configured', function (): void {
        Functions\when('get_option')->justReturn('');

        $result = $this->plugin->restGetApiKey();

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('no_key');
        expect($result->get_error_data()['status'])->toBe(404);
    });

    it('returns the key in the response when configured', function (): void {
        Functions\when('get_option')->justReturn('my-secret-key');

        $result = $this->plugin->restGetApiKey();

        expect($result['key'])->toBe('my-secret-key');
    });
});

describe('LynxJournal::restGetNonce() cookie auth fallback', function (): void {

    it('returns a nonce when user is already logged in', function (): void {
        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('wp_create_nonce')->justReturn('nonce-123');

        $result = $this->plugin->restGetNonce();

        expect($result['nonce'])->toBe('nonce-123');
    });

    it('sets the current user from cookie and returns a nonce when not logged in', function (): void {
        Functions\when('is_user_logged_in')->justReturn(false);
        Functions\when('wp_validate_auth_cookie')->justReturn(7);
        Functions\when('wp_create_nonce')->justReturn('nonce-from-cookie');

        $setUserId = null;
        Functions\when('wp_set_current_user')->alias(function (int $id) use (&$setUserId): void {
            $setUserId = $id;
        });

        $result = $this->plugin->restGetNonce();

        expect($setUserId)->toBe(7);
        expect($result['nonce'])->toBe('nonce-from-cookie');
    });

    it('still returns a nonce when cookie auth also fails', function (): void {
        Functions\when('is_user_logged_in')->justReturn(false);
        Functions\when('wp_validate_auth_cookie')->justReturn(false);
        Functions\when('wp_create_nonce')->justReturn('anon-nonce');

        $result = $this->plugin->restGetNonce();

        expect($result['nonce'])->toBe('anon-nonce');
    });
});
