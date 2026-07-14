<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Email run notifications, sent via wp_mail().
 *
 * @since 1.0.0
 */
final class LynxJournal_Notify_EmailChannel implements LynxJournal_Notify_Channel {

    public function key(): string {
        return 'email';
    }

    public function fields(): array {
        return ['enabled', 'email'];
    }

    public function isEnabled(array $notify): bool {
        return !empty($notify['enabled']);
    }

    public function isComplete(array $notify): bool {
        return $this->isEnabled($notify);
    }

    public function validate(array &$notify): ?\WP_Error {
        if (!empty($notify['email'])) {
            $notify['email'] = sanitize_email($notify['email']);
            if (!is_email($notify['email'])) {
                return new \WP_Error('invalid_notify_email', __('notify.email is not a valid email address', 'lynx-journal'), ['status' => 400]);
            }
        }
        return null;
    }

    public function send(int|null $post_id, array $link_ids, string $mode, array $notify): true|\WP_Error {
        if (!$this->isEnabled($notify)) {
            return true;
        }
        $to        = !empty($notify['email']) ? $notify['email'] : get_option('admin_email');
        $link_count = count($link_ids);
        /* translators: %d: number of links published */
        $subject = sprintf(__('[LynxJournal] Roundup published: %d links', 'lynx-journal'), $link_count);
        $message = $this->buildMessage($post_id, $link_count, $mode);
        return wp_mail($to, $subject, $message)
            ? true
            : new \WP_Error('email_send_failed', __('wp_mail() failed to send the notification email.', 'lynx-journal'), ['status' => 500]);
    }

    public function sendTest(array $notify): true|\WP_Error {
        if (empty($notify['enabled'])) {
            return new \WP_Error('test_missing_field', __('Enable email notifications first.', 'lynx-journal'), ['status' => 400]);
        }
        $to = !empty($notify['email']) ? $notify['email'] : get_option('admin_email');
        $message = __('This is a test notification from LynxJournal.', 'lynx-journal');
        return wp_mail($to, __('[LynxJournal] Test notification', 'lynx-journal'), $message)
            ? true
            : new \WP_Error('test_failed', __('wp_mail() failed to send the test email.', 'lynx-journal'), ['status' => 500]);
    }

    /**
     * Build the run-notification email body.
     *
     * @since 1.0.0
     * @param int|null $post_id The published post ID, or null if nothing was published.
     * @param int $link_count Number of links included in the run.
     * @param string $mode The schedule mode that ran.
     * @return string Message body.
     */
    private function buildMessage(int|null $post_id, int $link_count, string $mode): string {
        if ($post_id) {
            return sprintf(
                /* translators: 1: link count, 2: post URL */
                __("A new roundup was published.\n\nLinks: %1\$d\nView: %2\$s", 'lynx-journal'),
                $link_count,
                get_permalink($post_id)
            );
        }
        /* translators: %s: schedule mode */
        return sprintf(__('Schedule ran in %s mode but no post was published.', 'lynx-journal'), $mode);
    }
}
