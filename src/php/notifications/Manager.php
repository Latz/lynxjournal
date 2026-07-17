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
final class LynxJournal_Notify_Manager {

    /** @var LynxJournal_Notify_Channel[] Keyed by ->key(), in a fixed order. */
    private array $channels;

    public function __construct() {
        $this->channels = [];
        foreach ([
            new LynxJournal_Notify_EmailChannel(),
            new LynxJournal_Notify_DiscordChannel(),
            new LynxJournal_Notify_SlackChannelChannel(),
            new LynxJournal_Notify_SlackDmChannel(),
            new LynxJournal_Notify_TelegramChannel(),
            new LynxJournal_Notify_TelegramDmChannel(),
            new LynxJournal_Notify_MastodonChannel(),
            new LynxJournal_Notify_BlueskyChannel(),
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
    }

    /**
     * Dispatch a run notification to every enabled, complete channel.
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
                $result = $channel->send($post_id, $link_ids, $mode, $notify);
                if (is_wp_error($result)) {
                    $this->recordChannelFailure($channel->key(), $result);
                }
            }
        }
    }

    /**
     * Persist a channel send failure so it can surface as an admin notice,
     * and log it for site admins who monitor debug.log.
     *
     * @since 1.0.0
     * @param string $key Channel key that failed.
     * @param \WP_Error $error The error returned by the channel's send().
     * @return void
     */
    private function recordChannelFailure(string $key, \WP_Error $error): void {
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
        update_option('lynxjournal_notification_failures', array_slice($failures, 0, 10));

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log(sprintf('LynxJournal: %s notification failed: %s', $key, $message));
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
            'bluesky'       => __('Bluesky', 'lynx-journal'),
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
            $this->recordChannelFailure($key, $result);
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
     * @return LynxJournal_Notify_Channel|null The channel, or null if unknown.
     */
    public function channel(string $key): ?LynxJournal_Notify_Channel {
        return $this->channels[$key] ?? null;
    }
}
