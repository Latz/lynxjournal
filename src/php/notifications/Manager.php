<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registry and orchestrator for all notification channels.
 *
 * @since 1.0.0
 */
final class LynxJournalNotifyManager {

    /** @var LynxJournalNotifyChannel[] Keyed by ->key(), in a fixed order. */
    private array $channels;

    public function __construct() {
        $this->channels = [];
        foreach ([
            new LynxJournalNotifyEmailChannel(),
            new LynxJournalNotifyDiscordChannel(),
            new LynxJournalNotifySlackChannelChannel(),
            new LynxJournalNotifySlackDmChannel(),
            new LynxJournalNotifyTelegramChannel(),
            new LynxJournalNotifyTelegramDmChannel(),
            new LynxJournalNotifyMastodonChannel(),
        ] as $channel) {
            $this->channels[$channel->key()] = $channel;
        }
    }

    /**
     * Hook every channel's send() onto the run-completed action.
     *
     * @since 1.0.0
     * @return void
     */
    public function registerHooks(): void {
        add_action('lynxjournal_after_run', [$this, 'runAfterPublish'], 10, 3);
        add_action('lynxjournal_send_notification', [$this, 'dispatchChannelNotification'], 10, 4);
    }

    /**
     * Schedule a notification dispatch for every enabled, complete channel.
     *
     * Each channel is sent from its own scheduled event (see
     * dispatchChannelNotification()) rather than synchronously here, so a
     * slow or unreachable channel (a timed-out webhook, a slow API, ...)
     * can't block the publish request or delay the
     * remaining channels.
     *
     * @since 1.0.0
     * @param int|null $post_id The published post ID, or null if nothing was published.
     * @param array $link_ids Array of link post IDs that were published.
     * @param string $mode The schedule mode that ran.
     * @return void
     */
    public function runAfterPublish(int|null $post_id, array $link_ids, string $mode): void {
        $config = get_option('lynxjournal_schedule', []);
        $notify = is_array($config) && isset($config['notify']) && is_array($config['notify']) ? $config['notify'] : [];

        foreach ($this->channels as $channel) {
            if ($channel->isEnabled($notify)) {
                wp_schedule_single_event(time(), 'lynxjournal_send_notification', [$channel->key(), $post_id, $link_ids, $mode]);
            }
        }
    }

    /**
     * Send one channel's run notification and record a failure if it errors.
     *
     * Runs from the lynxjournal_send_notification scheduled event registered
     * in registerHooks(), one per enabled channel per run.
     *
     * @since 1.0.0
     * @param string $channelKey Channel key to send from.
     * @param int|null $post_id The published post ID, or null if nothing was published.
     * @param array $link_ids Array of link post IDs that were published.
     * @param string $mode The schedule mode that ran.
     * @return void
     */
    public function dispatchChannelNotification(string $channelKey, int|null $post_id, array $link_ids, string $mode): void {
        $channel = $this->channels[$channelKey] ?? null;
        if (!$channel) {
            return;
        }

        $config = get_option('lynxjournal_schedule', []);
        $notify = is_array($config) && isset($config['notify']) && is_array($config['notify']) ? $config['notify'] : [];

        $result = $channel->send($post_id, $link_ids, $mode, $notify);
        if (is_wp_error($result)) {
            $this->recordChannelFailure($channel->key(), $result, $notify);
        }
    }

    /**
     * Persist a channel send failure so it can surface as an admin notice,
     * log it for site admins who monitor debug.log, and email the admin
     * if they've opted into failure alerts.
     *
     * @since 1.0.0
     * @param string $key Channel key that failed.
     * @param \WP_Error $error The error returned by the channel's send().
     * @param array $notify The `notify` option array, for the admin-alert opt-in/address.
     * @return void
     */
    private function recordChannelFailure(string $key, \WP_Error $error, array $notify = []): void {
        $message = $error->get_error_message();

        $failures = get_option('lynxjournal_notification_failures', []);
        if (!is_array($failures)) {
            $failures = [];
        }
        array_unshift($failures, [
            'ts'      => time(),
            'channel' => $key,
            'label'   => $this->channelLabel($key),
            'message' => $message,
        ]);
        update_option('lynxjournal_notification_failures', array_slice($failures, 0, 10), false);

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log(sprintf('LynxJournal: %s notification failed: %s', $key, $message));

        if (!empty($notify['adminAlertEnabled'])) {
            $this->sendAdminFailureAlert($key, $message, $notify);
        }
    }

    /**
     * Email the admin that a notification channel failed to send.
     *
     * Sent directly via wp_mail() rather than through LynxJournalNotifyEmailChannel,
     * so a failure of the Email channel itself can't recurse back into
     * recordChannelFailure(). A failure of this alert is only logged, never recorded.
     *
     * @since 1.0.0
     * @param string $key Channel key that failed.
     * @param string $message The channel's error message.
     * @param array $notify The `notify` option array, for the alert address.
     * @return void
     */
    private function sendAdminFailureAlert(string $key, string $message, array $notify): void {
        $to = !empty($notify['adminAlertEmail']) ? $notify['adminAlertEmail'] : get_option('admin_email');
        /* translators: %s: channel name */
        $subject = sprintf(__('[LynxJournal] %s notification failed to send', 'lynx-journal'), $this->channelLabel($key));
        /* translators: 1: channel name, 2: error message */
        $body = sprintf(__("The %1\$s notification failed to send:\n\n%2\$s", 'lynx-journal'), $this->channelLabel($key), $message);

        if (!wp_mail($to, $subject, $body)) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log(sprintf('LynxJournal: failed to send admin failure-alert email for %s', $key));
        }
    }

    /**
     * Human-readable label for a notification channel key.
     *
     * @since 1.0.0
     * @param string $key Channel key.
     * @return string Translated human label, or the raw key if unknown.
     */
    public function channelLabel(string $key): string {
        $labels = [
            'email'         => __('Email', 'lynx-journal'),
            'discord'       => __('Discord', 'lynx-journal'),
            'slack_channel' => __('Slack (Channel)', 'lynx-journal'),
            'slack_dm'      => __('Slack (DM)', 'lynx-journal'),
            'telegram'      => __('Telegram', 'lynx-journal'),
            'telegram_dm'   => __('Telegram (DM)', 'lynx-journal'),
            'mastodon'      => __('Mastodon', 'lynx-journal'),
        ];
        return $labels[$key] ?? $key;
    }

    /**
     * Validate/sanitize every channel's fields in place.
     *
     * @since 1.0.0
     * @param array $notify The `notify` option array, modified in place.
     * @return \WP_Error|null Error from the first invalid channel, null if all valid.
     */
    public function validateAll(array &$notify): ?\WP_Error {
        $notify['enabled'] = (bool) ($notify['enabled'] ?? false);
        foreach ($this->channels as $channel) {
            $error = $channel->validate($notify);
            if ($error !== null) {
                return $error;
            }
        }
        return $this->validateAdminAlert($notify);
    }

    /**
     * Validate/sanitize the admin failure-alert fields in place.
     *
     * @since 1.0.0
     * @param array $notify The `notify` option array, modified in place.
     * @return \WP_Error|null Error if the alert email is invalid, null if valid.
     */
    private function validateAdminAlert(array &$notify): ?\WP_Error {
        $notify['adminAlertEnabled'] = (bool) ($notify['adminAlertEnabled'] ?? false);
        if (!empty($notify['adminAlertEmail'])) {
            $notify['adminAlertEmail'] = sanitize_email($notify['adminAlertEmail']);
            if (!is_email($notify['adminAlertEmail'])) {
                return new \WP_Error('invalid_admin_alert_email', __('notify.adminAlertEmail is not a valid email address', 'lynx-journal'), ['status' => 400]);
            }
        }
        return null;
    }

    /**
     * Validate/sanitize only one channel's fields.
     *
     * @since 1.0.0
     * @param string $key Channel key.
     * @param array $notify The `notify` option array, modified in place.
     * @return \WP_Error|null Error if invalid, null if valid.
     */
    public function validateChannel(string $key, array &$notify): ?\WP_Error {
        $channel = $this->channels[$key] ?? null;
        if (!$channel) {
            return new \WP_Error('invalid_channel', __('Unknown notification channel.', 'lynx-journal'), ['status' => 400]);
        }
        return $channel->validate($notify);
    }

    /**
     * Send a one-off test notification for a single channel.
     *
     * @since 1.0.0
     * @param string $key Channel key.
     * @param array $notify Notify settings to test with (already validated).
     * @return true|\WP_Error True on success, WP_Error describing why the test couldn't be sent.
     */
    public function test(string $key, array $notify): true|\WP_Error {
        $channel = $this->channels[$key] ?? null;
        if (!$channel) {
            return new \WP_Error('invalid_channel', __('Unknown notification channel.', 'lynx-journal'), ['status' => 400]);
        }
        $result = $channel->sendTest($notify);
        if (is_wp_error($result)) {
            $this->recordChannelFailure($key, $result, $notify);
        }
        return $result;
    }

    /**
     * The notify field names that belong to one channel.
     *
     * @since 1.0.0
     * @param string $key Channel key.
     * @return string[] Field names within notify.
     */
    public function channelFields(string $key): array {
        return $this->channel($key)?->fields() ?? [];
    }

    /**
     * All registered channel keys.
     *
     * @since 1.0.0
     * @return string[] Channel keys.
     */
    public function knownChannelKeys(): array {
        return array_keys($this->channels);
    }

    /**
     * Get one channel by key.
     *
     * @since 1.0.0
     * @param string $key Channel key.
     * @return LynxJournalNotifyChannel|null The channel, or null if unknown.
     */
    public function channel(string $key): ?LynxJournalNotifyChannel {
        return $this->channels[$key] ?? null;
    }
}
