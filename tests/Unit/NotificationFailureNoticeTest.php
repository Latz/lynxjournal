<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Brain\Monkey\Functions;

beforeEach(function (): void {
    Functions\when('__')->returnArg();
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_html')->returnArg();
    Functions\when('esc_attr')->returnArg();
    Functions\when('wp_create_nonce')->justReturn('test-nonce');
    Functions\when('wp_date')->alias(fn ($format, $ts) => 'DATE:' . $ts);
    Functions\when('get_option')->alias(function ($key, $default = false) {
        if ($key === 'date_format' || $key === 'time_format') {
            return '';
        }
        return $default;
    });
    $this->plugin = Mockery::mock(LynxJournal::class)->makePartial();
});

describe('LynxJournal::renderNotificationFailureNotice()', function (): void {

    it('renders nothing when the user lacks edit_posts', function (): void {
        Functions\when('current_user_can')->justReturn(false);

        ob_start();
        $this->plugin->renderNotificationFailureNotice();
        $output = ob_get_clean();

        expect($output)->toBe('');
    });

    it('renders nothing when there are no stored failures', function (): void {
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_option')->alias(function ($key, $default = false) {
            if ($key === 'lynxjournal_notification_failures') {
                return [];
            }
            return $default;
        });

        ob_start();
        $this->plugin->renderNotificationFailureNotice();
        $output = ob_get_clean();

        expect($output)->toBe('');
    });

    it('renders nothing when every failure is older than the last dismissal', function (): void {
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_option')->alias(function ($key, $default = false) {
            if ($key === 'lynxjournal_notification_failures') {
                return [['ts' => 100, 'channel' => 'discord', 'label' => 'Discord', 'message' => 'boom']];
            }
            if ($key === 'lynxjournal_notification_failures_dismissed_at') {
                return 200;
            }
            return $default;
        });

        ob_start();
        $this->plugin->renderNotificationFailureNotice();
        $output = ob_get_clean();

        expect($output)->toBe('');
    });

    it('renders the notice with channel label and message for a qualifying failure', function (): void {
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_option')->alias(function ($key, $default = false) {
            if ($key === 'lynxjournal_notification_failures') {
                return [['ts' => 300, 'channel' => 'discord', 'label' => 'Discord', 'message' => 'Could not resolve host']];
            }
            if ($key === 'lynxjournal_notification_failures_dismissed_at') {
                return 200;
            }
            return $default;
        });

        ob_start();
        $this->plugin->renderNotificationFailureNotice();
        $output = ob_get_clean();

        expect($output)->toContain('notice-error');
        expect($output)->toContain('is-dismissible');
        expect($output)->toContain('Discord');
        expect($output)->toContain('Could not resolve host');
    });
});

describe('LynxJournal::handleDismissNotificationFailureNotice()', function (): void {

    it('sends a JSON error when the user lacks edit_posts', function (): void {
        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('current_user_can')->justReturn(false);
        Functions\when('wp_send_json_error')->alias(function (string $msg, int $code): void {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new RuntimeException("json_error:{$code}");
        });

        expect(fn () => $this->plugin->handleDismissNotificationFailureNotice())
            ->toThrow(RuntimeException::class, 'json_error:403');
    });

    it('persists the dismissal timestamp and sends success on the happy path', function (): void {
        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('current_user_can')->justReturn(true);

        $captured = null;
        Functions\when('update_option')->alias(function ($key, $value) use (&$captured): bool {
            if ($key === 'lynxjournal_notification_failures_dismissed_at') {
                $captured = $value;
            }
            return true;
        });
        Functions\when('wp_send_json_success')->justReturn(null);

        $this->plugin->handleDismissNotificationFailureNotice();

        expect($captured)->toBeInt();
    });
});
