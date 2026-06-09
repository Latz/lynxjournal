<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Brain\Monkey\Functions;

beforeEach(function (): void {
    Functions\when('__')->returnArg();
    $this->plugin = Mockery::mock(LynxJournal::class)->makePartial();
});

describe('LynxJournal::maybeSendRunNotification()', function (): void {

    it('does nothing when notify is disabled', function (): void {
        Functions\when('get_option')->justReturn(['notify' => ['enabled' => false]]);

        $mailSent = false;
        Functions\when('wp_mail')->alias(function () use (&$mailSent): bool {
            $mailSent = true;
            return true;
        });

        $this->plugin->maybeSendRunNotification(42, [1, 2, 3], 'daily');

        expect($mailSent)->toBeFalse();
    });

    it('does nothing when notify key is absent', function (): void {
        Functions\when('get_option')->justReturn([]);

        $mailSent = false;
        Functions\when('wp_mail')->alias(function () use (&$mailSent): bool {
            $mailSent = true;
            return true;
        });

        $this->plugin->maybeSendRunNotification(null, [], 'daily');

        expect($mailSent)->toBeFalse();
    });

    it('sends email with post URL when notify is enabled and post_id is given', function (): void {
        Functions\when('get_option')->alias(fn($key, $default = false) =>
            $key === 'lynxjournal_schedule'
                ? ['notify' => ['enabled' => true, 'email' => 'editor@example.com']]
                : $default
        );
        Functions\when('get_permalink')->justReturn('https://site.example/roundup-42');

        $captured = [];
        Functions\when('wp_mail')->alias(function (mixed $to, string $subject, string $message) use (&$captured): bool {
            $captured = compact('to', 'subject', 'message');
            return true;
        });

        $this->plugin->maybeSendRunNotification(42, [1, 2, 3], 'daily');

        expect($captured['to'])->toBe('editor@example.com');
        expect($captured['message'])->toContain('https://site.example/roundup-42');
        expect($captured['message'])->toContain('3');
    });

    it('sends fallback message without URL when post_id is null', function (): void {
        Functions\when('get_option')->alias(fn($key, $default = false) =>
            $key === 'lynxjournal_schedule'
                ? ['notify' => ['enabled' => true, 'email' => 'editor@example.com']]
                : $default
        );

        $captured = [];
        Functions\when('wp_mail')->alias(function (mixed $to, string $subject, string $message) use (&$captured): bool {
            $captured = compact('to', 'subject', 'message');
            return true;
        });

        $this->plugin->maybeSendRunNotification(null, [1, 2], 'count');

        expect($captured['message'])->toContain('count');
    });

    it('falls back to admin_email when no email is configured', function (): void {
        Functions\when('get_option')->alias(fn($key, $default = false) =>
            match($key) {
                'lynxjournal_schedule' => ['notify' => ['enabled' => true]],
                'admin_email'          => 'admin@example.com',
                default                => $default,
            }
        );
        Functions\when('get_permalink')->justReturn('https://site.example/post');

        $capturedTo = null;
        Functions\when('wp_mail')->alias(function (mixed $to) use (&$capturedTo): bool {
            $capturedTo = $to;
            return true;
        });

        $this->plugin->maybeSendRunNotification(10, [1], 'daily');

        expect($capturedTo)->toBe('admin@example.com');
    });
});
