<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The shared facts every notification channel needs to describe one
 * schedule run — what to say, before each channel formats it into its
 * own wire format (Discord embed, Slack blocks, Telegram HTML, plain text).
 *
 * @since 1.0.0
 */
final class LynxJournal_Notify_RunMessageContent {

    /**
     * @since 1.0.0
     * @param string|null $title Published post title, or null if nothing was published.
     * @param string|null $url Published post permalink, or null if nothing was published.
     * @param string $summary One-line human-readable summary of the run.
     * @param bool $published Whether a post was actually published this run.
     */
    public function __construct(
        public readonly ?string $title,
        public readonly ?string $url,
        public readonly string $summary,
        public readonly bool $published,
    ) {
    }

    /**
     * Build the message content for one schedule run.
     *
     * @since 1.0.0
     * @param int|null $post_id The published post ID, or null if nothing was published.
     * @param int $link_count Number of links included in the run.
     * @param string $mode The schedule mode that ran.
     * @return self
     */
    public static function forRun(int|null $post_id, int $link_count, string $mode): self {
        if ($post_id) {
            return new self(
                get_the_title($post_id),
                get_permalink($post_id),
                /* translators: %d: number of links published */
                sprintf(__('A new roundup was published with %d links.', 'lynx-journal'), $link_count),
                true,
            );
        }

        return new self(
            null,
            null,
            /* translators: %s: schedule mode */
            sprintf(__('Schedule ran in %s mode but no post was published.', 'lynx-journal'), $mode),
            false,
        );
    }
}
