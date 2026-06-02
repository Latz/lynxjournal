<?php

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait LinkDigest_Scheduler {

    /**
     * Register scheduler hooks and callbacks.
     *
     * Called from the main plugin initialization.
     *
     * @since 1.0.0
     * @return void
     */
    public function registerSchedulerHooks(): void {
        add_action('linkdigest_execute_schedule', [$this, 'executeSchedule']);
        add_action('linkdigest_after_run', [$this, 'maybeSendRunNotification'], 10, 3);
        add_action('linkdigest_after_run', [$this, 'maybeSendWebhookNotification'], 20, 3);
    }

    /**
     * Calculate and schedule the next event based on schedule configuration.
     *
     * @since 1.0.0
     * @return void
     */
    public function scheduleNextEvent(): void {
        wp_clear_scheduled_hook('linkdigest_execute_schedule');
        $ts = $this->getNextScheduleTimestamp();
        if ($ts !== null) {
            wp_schedule_single_event($ts, 'linkdigest_execute_schedule');
        }
    }

    /**
     * Execute the schedule if conditions are met.
     *
     * Cron callback that checks trigger conditions, publishes if needed, and reschedules.
     *
     * @since 1.0.0
     * @param bool $reschedule Whether to reschedule the next event after execution.
     * @return array Result array with published status, post_id, link_count, and reason.
     */
    public function executeSchedule(bool $reschedule = true): array {
        if (get_transient('linkdigest_run_lock')) {
            return ['published' => false, 'post_id' => null, 'link_count' => 0, 'reason' => 'locked'];
        }
        set_transient('linkdigest_run_lock', 1, 5 * MINUTE_IN_SECONDS);
        try {
            return $this->doExecuteSchedule($reschedule);
        } finally {
            delete_transient('linkdigest_run_lock');
        }
    }

    /**
     * Internal implementation of schedule execution.
     *
     * @since 1.0.0
     * @param bool $reschedule Whether to reschedule the next event.
     * @return array Result array with execution details.
     */
    private function doExecuteSchedule(bool $reschedule): array {
        $config  = get_option('linkdigest_schedule', []);
        $mode    = $config['mode']    ?? 'daily';
        $trigger = $config['trigger'] ?? [];

        $link_ids       = $this->getUnpublishedLinkIds(); // returns oldest-first (ASC)
        $total_count    = count($link_ids);
        $oldest_link_id = $link_ids[0] ?? null; // capture before any slice for age-mode check

        // Cap per-run to prevent max_execution_time / OOM on large queues.
        // Remaining links are handled by an immediate reschedule below.
        $max_per_run = (int) apply_filters('linkdigest_max_per_run', self::MAX_PER_RUN);
        $has_more    = $total_count > $max_per_run;
        if ($has_more) {
            $link_ids = array_slice($link_ids, 0, $max_per_run);
        }

        $should_publish = $this->evaluateTrigger($mode, $trigger, $total_count, $oldest_link_id);
        $should_publish = (bool) apply_filters('linkdigest_should_publish', $should_publish, $link_ids, $mode, $trigger);

        if ($should_publish && !empty($link_ids)) {
            $result = $this->attemptPublish($link_ids, $config, $mode, $reschedule, $has_more);
        } else {
            if ($reschedule) {
                $this->scheduleNextEvent();
            }
            $result = ['published' => false, 'post_id' => null, 'link_count' => 0, 'reason' => 'condition_not_met'];
        }

        $run_record = [
            'ts'         => time(),
            'mode'       => $mode,
            'link_count' => $result['link_count'],
            'post_id'    => $result['post_id'],
            'status'     => $result['published'] ? 'success' : 'skipped',
            'reason'     => $result['reason'],
        ];
        update_option('linkdigest_last_run', $run_record);
        $history = get_option('linkdigest_run_history', []);
        array_unshift($history, $run_record);
        update_option('linkdigest_run_history', array_slice($history, 0, 25));

        return $result;
    }

    /**
     * Publish a digest post and handle rescheduling.
     *
     * @since 1.0.0
     * @param array  $link_ids   Link IDs to include in the digest.
     * @param array  $config     Schedule configuration option.
     * @param string $mode       Schedule mode.
     * @param bool   $reschedule Whether to schedule the next regular event.
     * @param bool   $has_more   Whether more links remain beyond this batch.
     * @return array Result array with published status, post_id, link_count, and reason.
     */
    private function attemptPublish(array $link_ids, array $config, string $mode, bool $reschedule, bool $has_more): array {
        /* translators: %s is the formatted date (e.g. "April 15, 2026") */
        $title = sprintf(__('Links: %s', 'linkdigest'), wp_date('F j, Y'));
        $title = (string) apply_filters('linkdigest_digest_title', $title, $link_ids, $mode);

        // Resolve the author for the digest post. WP-Cron runs unauthenticated
        // (user 0), so we pass the stored publishAs user ID directly to
        // createDigestPost() as post_author instead of using wp_set_current_user().
        $publish_as = (int) ($config['publishAs'] ?? 0);
        if ($publish_as === 0 || !user_can($publish_as, 'publish_posts')) {
            $admin_ids  = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ids']);
            $publish_as = !empty($admin_ids) ? (int) $admin_ids[0] : 0;
        }

        do_action('linkdigest_before_run', $link_ids, $mode);
        $as_draft = ($config['post_status'] ?? 'publish') === 'draft';
        $digest   = $this->createDigestPost($link_ids, $title, $as_draft, $mode, $publish_as);

        $post_id = ($digest['post_id'] ?? 0) ?: null;
        do_action('linkdigest_after_run', $post_id, $link_ids, $mode);

        $scheduled_catchup = false;
        if ($has_more) {
            wp_schedule_single_event(time() + self::RESCHEDULE_DELAY, 'linkdigest_execute_schedule');
            $scheduled_catchup = true;
        }
        if (!$scheduled_catchup && $reschedule) {
            $this->scheduleNextEvent();
        }

        return [
            'published'  => $digest['success'] ?? false,
            'post_id'    => $post_id,
            'link_count' => count($link_ids),
            'reason'     => $digest['message'] ?? null,
        ];
    }

    /**
     * Evaluate whether the trigger condition is met for the current mode.
     *
     * @since 1.0.0
     * @param string   $mode           Schedule mode.
     * @param array    $trigger        Trigger configuration (count/days).
     * @param int      $total_count    Total number of pending links.
     * @param int|null $oldest_link_id Post ID of the oldest pending link, or null if none.
     * @return bool True if publishing should proceed.
     */
    private function evaluateTrigger(string $mode, array $trigger, int $total_count, ?int $oldest_link_id): bool {
        return match ($mode) {
            'count' => $total_count >= (int) ($trigger['count'] ?? 10),
            'age'   => $oldest_link_id !== null && $this->isLinkOlderThan($oldest_link_id, (int) ($trigger['days'] ?? 7)),
            default => $total_count > 0,
        };
    }

    /**
     * Preview what would be published if schedule ran now.
     *
     * @since 1.0.0
     * @return array Preview data with would_publish status and link information.
     */
    public function previewSchedule(): array {
        $config  = get_option('linkdigest_schedule', []);
        $mode    = $config['mode']    ?? 'daily';
        $trigger = $config['trigger'] ?? [];

        $link_ids       = $this->getUnpublishedLinkIds();
        $total_count    = count($link_ids);
        $oldest_link_id = $link_ids[0] ?? null;
        $max_per_run    = (int) apply_filters('linkdigest_max_per_run', self::MAX_PER_RUN);
        $batch_ids      = array_slice($link_ids, 0, $max_per_run);

        $would_publish = $mode !== 'manual' && $this->evaluateTrigger($mode, $trigger, $total_count, $oldest_link_id);

        $by_category = [];
        if ($would_publish && !empty($batch_ids)) {
            foreach ($batch_ids as $id) {
                $terms    = wp_get_object_terms($id, 'linkdigest_category', ['fields' => 'names']);
                $cat_name = (!is_wp_error($terms) && !empty($terms))
                    ? $terms[0]
                    : __('Uncategorized', 'linkdigest');
                $by_category[$cat_name] = ($by_category[$cat_name] ?? 0) + 1;
            }
            arsort($by_category);
            $by_category = array_map(
                fn($name, $count) => ['name' => $name, 'count' => $count],
                array_keys($by_category), array_values($by_category)
            );
        }

        return [
            'would_publish' => $would_publish,
            'link_count'    => $would_publish ? count($batch_ids) : 0,
            'total_pending' => $total_count,
            'by_category'   => array_values($by_category),
            'mode'          => $mode,
        ];
    }

    /**
     * Check if a link is older than a specified number of days.
     *
     * @since 1.0.0
     * @param int $link_id The link post ID.
     * @param int $days Number of days to check against.
     * @return bool True if link is older than specified days.
     */
    private function isLinkOlderThan(int $link_id, int $days): bool {
        $post = get_post($link_id);
        if (!$post) {
            return false;
        }
        $cutoff = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));
        return $post->post_date_gmt < $cutoff;
    }
}
