<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Brain\Monkey\Functions;

beforeEach(function (): void {
    Functions\when('__')->returnArg();
    Functions\when('sanitize_text_field')->returnArg();
    Functions\when('wp_unslash')->returnArg();
    $this->plugin = Mockery::mock(LynxJournal::class)->makePartial();
    $_POST = [];
});

afterEach(function (): void {
    $_POST = [];
});

describe('LynxJournal::saveUrl()', function (): void {

    it('returns early when nonce field is absent from POST', function (): void {
        $_POST = [];

        $called = false;
        Functions\when('update_post_meta')->alias(function () use (&$called): bool {
            $called = true;
            return true;
        });

        $this->plugin->saveUrl(42);

        expect($called)->toBeFalse();
    });

    it('returns early when nonce verification fails', function (): void {
        $_POST = ['lynxjournal_url_nonce' => 'bad'];
        Functions\when('wp_verify_nonce')->justReturn(false);

        $called = false;
        Functions\when('update_post_meta')->alias(function () use (&$called): bool {
            $called = true;
            return true;
        });

        $this->plugin->saveUrl(42);

        expect($called)->toBeFalse();
    });

    it('returns early when user lacks edit_post capability', function (): void {
        $_POST = ['lynxjournal_url_nonce' => 'valid', 'lynxjournal_url' => 'https://example.com'];
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('current_user_can')->justReturn(false);

        $called = false;
        Functions\when('update_post_meta')->alias(function () use (&$called): bool {
            $called = true;
            return true;
        });

        $this->plugin->saveUrl(42);

        expect($called)->toBeFalse();
    });

    it('saves the sanitized URL when nonce and permissions are valid', function (): void {
        $_POST = ['lynxjournal_url_nonce' => 'valid', 'lynxjournal_url' => 'https://example.com'];
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('esc_url_raw')->returnArg();

        $savedId  = null;
        $savedKey = null;
        $savedUrl = null;
        Functions\when('update_post_meta')->alias(function (int $id, string $key, mixed $value) use (&$savedId, &$savedKey, &$savedUrl): bool {
            $savedId  = $id;
            $savedKey = $key;
            $savedUrl = $value;
            return true;
        });

        $this->plugin->saveUrl(42);

        expect($savedId)->toBe(42);
        expect($savedKey)->toBe('_lynxjournal_url');
        expect($savedUrl)->toBe('https://example.com');
    });
});
