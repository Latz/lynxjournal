<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * A single notification target (e.g. Discord, Slack DM, Bluesky DM).
 *
 * Each implementation is self-contained: it knows which `notify` option
 * fields it owns, how to validate/sanitize them, whether it's ready to
 * send, and how to actually deliver a real or test notification.
 *
 * @since 1.0.0
 */
interface LynxJournalNotifyChannel {

    /**
     * Stable identifier used in the `notify` option, REST payloads, and
     * the notification hooks system. Must never change once released.
     *
     * @since 1.0.0
     * @return string One of email|discord|slack_channel|slack_dm|telegram|telegram_dm|mastodon|bluesky.
     */
    public function key(): string;

    /**
     * The `notify` option field names this channel reads/writes.
     *
     * @since 1.0.0
     * @return string[] Field names within the `notify` array.
     */
    public function fields(): array;

    /**
     * Whether this channel is turned on, regardless of whether its
     * required fields are actually filled in.
     *
     * @since 1.0.0
     * @param array $notify The `notify` option array.
     * @return bool True if enabled.
     */
    public function isEnabled(array $notify): bool;

    /**
     * Whether this channel is enabled AND has all required fields filled in.
     *
     * @since 1.0.0
     * @param array $notify The `notify` option array.
     * @return bool True if ready to send.
     */
    public function isComplete(array $notify): bool;

    /**
     * Sanitize and validate this channel's fields in place.
     *
     * @since 1.0.0
     * @param array $notify The `notify` option array, modified in place.
     * @return \WP_Error|null Error if invalid, null if valid.
     */
    public function validate(array &$notify): ?\WP_Error;

    /**
     * Send a real run notification, if this channel is complete.
     *
     * @since 1.0.0
     * @param int|null $post_id The published post ID, or null if nothing was published.
     * @param array $link_ids Array of link post IDs that were published.
     * @param string $mode The schedule mode that ran.
     * @param array $notify The `notify` option array.
     * @return true|\WP_Error True on success (or silently skipped), WP_Error on failure.
     */
    public function send(int|null $post_id, array $link_ids, string $mode, array $notify): true|\WP_Error;

    /**
     * Send a one-off test notification using the given (possibly unsaved) fields.
     *
     * @since 1.0.0
     * @param array $notify The `notify` option array to test with.
     * @return true|\WP_Error True on success, WP_Error describing why the test couldn't be sent.
     */
    public function sendTest(array $notify): true|\WP_Error;
}
