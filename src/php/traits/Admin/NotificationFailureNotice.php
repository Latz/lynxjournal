<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dismissible admin notice for failed notification channel sends.
 *
 * @since 1.0.0
 */
trait LynxJournal_Admin_NotificationFailureNotice {

    /**
     * Render a dismissible notice listing recent notification channel
     * failures, if any occurred since the notice was last dismissed.
     *
     * @since 1.0.0
     * @return void
     */
    public function renderNotificationFailureNotice(): void {
        if (!current_user_can('edit_posts')) {
            return;
        }

        $failures = $this->pendingNotificationFailures();
        if (empty($failures)) {
            return;
        }

        echo '<div class="notice notice-error is-dismissible" id="lynxjournal-notification-failure-notice" data-nonce="' . esc_attr(wp_create_nonce('lynxjournal_dismiss_notification_failures')) . '">';
        echo '<p><strong>' . esc_html__('LynxJournal: a notification failed to send.', 'lynx-journal') . '</strong></p><ul>';
        foreach ($failures as $failure) {
            $line = sprintf(
                /* translators: 1: channel name, 2: date/time, 3: error message */
                __('%1$s on %2$s: %3$s', 'lynx-journal'),
                $failure['label'],
                wp_date(get_option('date_format') . ' ' . get_option('time_format'), $failure['ts']),
                $failure['message']
            );
            echo '<li>' . esc_html($line) . '</li>';
        }
        echo '</ul></div>';
    }

    /**
     * Recent notification failures not yet dismissed by the admin.
     *
     * @since 1.0.0
     * @return array<int, array{ts:int, channel:string, label:string, message:string}> Failures newer than the last dismissal.
     */
    private function pendingNotificationFailures(): array {
        $failures = get_option('lynxjournal_notification_failures', []);
        if (!is_array($failures)) {
            return [];
        }

        $dismissed_at = (int) get_option('lynxjournal_notification_failures_dismissed_at', 0);

        return array_values(array_filter(
            $failures,
            static fn (array $failure): bool => ($failure['ts'] ?? 0) > $dismissed_at
        ));
    }

    /**
     * AJAX handler: persist that the admin dismissed the current batch of
     * notification failure notices.
     *
     * @since 1.0.0
     * @return void
     */
    public function handleDismissNotificationFailureNotice(): void {
        check_ajax_referer('lynxjournal_dismiss_notification_failures', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Forbidden', 'lynx-journal'), 403);
        }

        update_option('lynxjournal_notification_failures_dismissed_at', time());
        wp_send_json_success();
    }
}
