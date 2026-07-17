<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Brain\Monkey\Functions;

beforeEach(function (): void {
    Functions\when('__')->returnArg();
    $this->channel = new LynxJournalNotifyEmailChannel();
});

describe('LynxJournalNotifyEmailChannel::send()', function (): void {

    it('does nothing when notify is disabled', function (): void {
        $mailSent = false;
        Functions\when('wp_mail')->alias(function () use (&$mailSent): bool {
            $mailSent = true;
            return true;
        });

        $this->channel->send(42, [1, 2, 3], 'daily', ['enabled' => false]);

        expect($mailSent)->toBeFalse();
    });

    it('does nothing when notify is empty', function (): void {
        $mailSent = false;
        Functions\when('wp_mail')->alias(function () use (&$mailSent): bool {
            $mailSent = true;
            return true;
        });

        $this->channel->send(null, [], 'daily', []);

        expect($mailSent)->toBeFalse();
    });

    it('sends email with post URL when notify is enabled and post_id is given', function (): void {
        Functions\when('get_permalink')->justReturn('https://site.example/roundup-42');

        $captured = [];
        Functions\when('wp_mail')->alias(function (mixed $to, string $subject, string $message) use (&$captured): bool {
            $captured = compact('to', 'subject', 'message');
            return true;
        });

        $this->channel->send(42, [1, 2, 3], 'daily', ['enabled' => true, 'email' => 'editor@example.com']);

        expect($captured['to'])->toBe('editor@example.com');
        expect($captured['message'])->toContain('https://site.example/roundup-42');
        expect($captured['message'])->toContain('3');
    });

    it('sends fallback message without URL when post_id is null', function (): void {
        $captured = [];
        Functions\when('wp_mail')->alias(function (mixed $to, string $subject, string $message) use (&$captured): bool {
            $captured = compact('to', 'subject', 'message');
            return true;
        });

        $this->channel->send(null, [1, 2], 'count', ['enabled' => true, 'email' => 'editor@example.com']);

        expect($captured['message'])->toContain('count');
    });

    it('falls back to admin_email when no email is configured', function (): void {
        Functions\when('get_option')->alias(fn($key, $default = false) =>
            $key === 'admin_email' ? 'admin@example.com' : $default
        );
        Functions\when('get_permalink')->justReturn('https://site.example/post');

        $capturedTo = null;
        Functions\when('wp_mail')->alias(function (mixed $to) use (&$capturedTo): bool {
            $capturedTo = $to;
            return true;
        });

        $this->channel->send(10, [1], 'daily', ['enabled' => true]);

        expect($capturedTo)->toBe('admin@example.com');
    });
});
