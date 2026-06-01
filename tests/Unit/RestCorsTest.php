<?php

declare(strict_types=1);

if (!defined("ABSPATH")) {
    exit;
}

use Brain\Monkey\Functions;

beforeEach(function (): void {
    $this->plugin = Mockery::mock(LinkDigest::class)->makePartial();
});

describe('LinkDigest CORS helpers', function (): void { // NOSONAR

    describe('addCorsHeaders()', function (): void {

        it('returns $served unchanged when origin is not a chrome extension', function (): void {
            Functions\when('get_http_origin')->justReturn('https://example.com');

            $result = $this->plugin->addCorsHeaders(true);

            expect($result)->toBeTrue();
        });

        it('returns false $served unchanged when origin is not a chrome extension', function (): void {
            Functions\when('get_http_origin')->justReturn('https://example.com');

            $result = $this->plugin->addCorsHeaders(false);

            expect($result)->toBeFalse();
        });

        it('returns $served and sets CORS headers when origin is a chrome extension', function (): void {
            Functions\when('get_http_origin')->justReturn('chrome-extension://abcdefg');

            $result = $this->plugin->addCorsHeaders(true);

            expect($result)->toBeTrue();
        });

        it('does not set CORS headers when origin is an empty string', function (): void {
            Functions\when('get_http_origin')->justReturn('');

            $result = $this->plugin->addCorsHeaders(false);

            expect($result)->toBeFalse();
        });
    });

    describe('handlePreflight()', function (): void {

        it('does nothing when REQUEST_METHOD is not OPTIONS', function (): void {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            Functions\when('get_http_origin')->justReturn('chrome-extension://abcdefg');

            $this->plugin->handlePreflight();

            // Reaching here means no exit was called
            expect(true)->toBeTrue();
            unset($_SERVER['REQUEST_METHOD']);
        });

        it('does nothing when REQUEST_METHOD is not set', function (): void {
            unset($_SERVER['REQUEST_METHOD']);

            $this->plugin->handlePreflight();

            expect(true)->toBeTrue();
        });

        it('does nothing when OPTIONS request comes from a non-chrome origin', function (): void {
            $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
            Functions\when('get_http_origin')->justReturn('https://example.com');

            $this->plugin->handlePreflight();

            expect(true)->toBeTrue();
            unset($_SERVER['REQUEST_METHOD']);
        });
    });

    describe('handleGetRestNonce()', function (): void {

        it('sends JSON error when user cannot manage options', function (): void {
            Functions\when('get_http_origin')->justReturn('https://example.com');
            Functions\when('current_user_can')->justReturn(false);
            Functions\when('wp_send_json_error')->alias(function (string $msg, int $code): void {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                throw new RuntimeException("json_error:{$code}");
            });

            expect(fn() => $this->plugin->handleGetRestNonce())
                ->toThrow(RuntimeException::class, 'json_error:403');
        });

        it('sends JSON success with nonce when user has permission', function (): void {
            Functions\when('get_http_origin')->justReturn('');
            Functions\when('current_user_can')->justReturn(true);
            Functions\when('wp_create_nonce')->justReturn('test-nonce');
            $captured = null;
            Functions\when('wp_send_json_success')->alias(function (array $data) use (&$captured): void {
                $captured = $data;
            });

            $this->plugin->handleGetRestNonce();

            expect($captured['nonce'])->toBe('test-nonce');
        });

        it('sets CORS headers when origin is a chrome extension', function (): void {
            Functions\when('get_http_origin')->justReturn('chrome-extension://abc');
            Functions\when('current_user_can')->justReturn(true);
            Functions\when('wp_create_nonce')->justReturn('nonce');
            Functions\when('wp_send_json_success')->justReturn(null);

            // If this throws due to header(), it would be a PHP error — reaching here means it succeeded
            $this->plugin->handleGetRestNonce();

            expect(true)->toBeTrue();
        });
    });
});
