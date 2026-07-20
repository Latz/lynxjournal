<?php

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait LynxJournal_Scheduler {

    /**
     * Register scheduler hooks and callbacks.
     *
     * Called from the main plugin initialization.
     *
     * @since 1.0.0
     * @return void
     */
    public function registerSchedulerHooks(): void {
        add_action('lynxjournal_execute_schedule', function (): void { $this->executeSchedule(); });
    }

    /**
     * Calculate and schedule the next event based on schedule configuration.
     *
     * @since 1.0.0
     * @return void
     */
    public function scheduleNextEvent(): void {
        wp_clear_scheduled_hook('lynxjournal_execute_schedule');
        $ts = $this->getNextScheduleTimestamp();
        if ($ts !== null) {
            wp_schedule_single_event($ts, 'lynxjournal_execute_schedule');
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
        if (get_transient('lynxjournal_run_lock')) {
            return ['published' => false, 'post_id' => null, 'link_count' => 0, 'reason' => 'locked'];
        }
        set_transient('lynxjournal_run_lock', 1, 5 * MINUTE_IN_SECONDS);
        try {
            return $this->doExecuteSchedule($reschedule);
        } finally {
            delete_transient('lynxjournal_run_lock');
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
        $config  = get_option('lynxjournal_schedule', []);
        $mode    = $config['mode']    ?? 'daily';
        $trigger = $config['trigger'] ?? [];

        $link_ids       = $this->getUnpublishedLinkIds(); // returns oldest-first (ASC)
        $total_count    = count($link_ids);
        $oldest_link_id = $link_ids[0] ?? null; // capture before any slice for age-mode check

        // Cap per-run to prevent max_execution_time / OOM on large queues.
        // Remaining links are handled by an immediate reschedule below.
        $max_per_run = (int) apply_filters('lynxjournal_max_per_run', self::MAX_PER_RUN);
        $has_more    = $total_count > $max_per_run;
        if ($has_more) {
            $link_ids = array_slice($link_ids, 0, $max_per_run);
        }

        $should_publish = match ($mode) {
            'count' => $total_count >= (int) ($trigger['count'] ?? 10),
            // Reuse the already-fetched list: oldest link is index 0 (ASC order).
            // No second WP_Query needed.
            'age'   => $oldest_link_id !== null && $this->isLinkOlderThan($oldest_link_id, (int) ($trigger['days'] ?? 7)),
            default => !empty($link_ids), // daily/weekly/monthly: publish if any links exist
        };
        $should_publish = (bool) apply_filters('lynxjournal_should_publish', $should_publish, $link_ids, $mode, $trigger);

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
        update_option('lynxjournal_last_run', $run_record);
        $history = get_option('lynxjournal_run_history', []);
        array_unshift($history, $run_record);
        update_option('lynxjournal_run_history', array_slice($history, 0, 25), false);

        return $result;
    }

    /**
     * Publish a roundup post and handle rescheduling.
     *
     * @since 1.0.0
     * @param array  $link_ids   Link IDs to include in the roundup.
     * @param array  $config     Schedule configuration option.
     * @param string $mode       Schedule mode.
     * @param bool   $reschedule Whether to schedule the next regular event.
     * @param bool   $has_more   Whether more links remain beyond this batch.
     * @return array Result array with published status, post_id, link_count, and reason.
     */
    private function attemptPublish(array $link_ids, array $config, string $mode, bool $reschedule, bool $has_more): array {
        /* translators: %s is the formatted date (e.g. "April 15, 2026") */
        $title = sprintf(__('Links: %s', 'lynx-journal'), wp_date('F j, Y'));
        $title = (string) apply_filters('lynxjournal_roundup_title', $title, $link_ids, $mode);

        // Resolve the author for the roundup post. WP-Cron runs unauthenticated
        // (user 0), so we pass the stored publishAs user ID directly to
        // createRoundupPost() as post_author instead of using wp_set_current_user().
        $publish_as = (int) ($config['publishAs'] ?? 0);
        if ($publish_as === 0 || !user_can($publish_as, 'publish_posts')) {
            $admin_ids  = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ids']);
            $publish_as = !empty($admin_ids) ? (int) $admin_ids[0] : 0;
        }

        do_action('lynxjournal_before_run', $link_ids, $mode);
        $as_draft = ($config['post_status'] ?? 'publish') === 'draft';
        $roundup  = $this->createRoundupPost($link_ids, $title, $as_draft, $mode, $publish_as);

        $post_id = ($roundup['post_id'] ?? 0) ?: null;
        do_action('lynxjournal_after_run', $post_id, $link_ids, $mode);

        $scheduled_catchup = false;
        if ($has_more) {
            wp_schedule_single_event(time() + self::RESCHEDULE_DELAY, 'lynxjournal_execute_schedule');
            $scheduled_catchup = true;
        }
        if (!$scheduled_catchup && $reschedule) {
            $this->scheduleNextEvent();
        }

        return [
            'published'  => $roundup['success'] ?? false,
            'post_id'    => $post_id,
            'link_count' => count($link_ids),
            'reason'     => $roundup['message'] ?? null,
        ];
    }

    /**
     * Calculate the next schedule timestamp based on configuration.
     *
     * Returns next UNIX timestamp in UTC based on schedule config, or null for manual mode.
     *
     * @since 1.0.0
     * @return int|null Next schedule timestamp or null if manual mode.
     */
    public function getNextScheduleTimestamp(): ?int {
        $config     = get_option('lynxjournal_schedule', []);
        $mode       = $config['mode']       ?? 'daily';
        $times      = $config['times']      ?? [];
        if (empty($times)) {
            $times = [self::DEFAULT_TIME];
        }
        $recurrence = $config['recurrence'] ?? [];

        if ($mode === 'manual') {
            return null;
        }

        $tz  = wp_timezone();
        $now = new \DateTime('now', $tz);
        sort($times);

        // 367-day window handles monthly schedules where no day matches in the current
        // month (e.g., 31st in a 30-day month) and leap-year edge cases.
        for ($i = 0; $i <= self::SEARCH_HORIZON_DAYS; $i++) {
            $day = (clone $now)->modify("+{$i} days");

            if (!$this->dayMatchesSchedule($day, $mode, $recurrence)) {
                continue;
            }

            foreach ($times as $t) {
                [$h, $m] = explode(':', $t);
                $candidate = (clone $day)->setTime((int) $h, (int) $m, 0);
                if ($candidate > $now) {
                    return $candidate->getTimestamp();
                }
            }
        }

        return null;
    }

    /**
     * Check if a date matches the schedule mode and recurrence settings.
     *
     * @since 1.0.0
     * @param \DateTime $date The date to check.
     * @param string $mode The schedule mode.
     * @param array $rec The recurrence settings.
     * @return bool True if date matches schedule.
     */
    private function dayMatchesSchedule(\DateTime $date, string $mode, array $rec): bool {
        if (in_array($mode, ['daily', 'count', 'age'], true)) {
            return true;
        }
        return match ($mode) {
            'weekly'  => $this->matchesWeeklySchedule($date, $rec),
            'monthly' => $this->matchesMonthlySchedule($date, $rec),
            default   => true,
        };
    }

    /**
     * Check if a date matches the weekly schedule.
     *
     * @since 1.0.0
     * @param \DateTime $date The date to check.
     * @param array $rec The recurrence settings with weekdays.
     * @return bool True if date is on a scheduled weekday.
     */
    private function matchesWeeklySchedule(\DateTime $date, array $rec): bool {
        $dow = (int) $date->format('N');
        foreach ($rec['weekdays'] ?? [] as $wd) {
            if ((self::weekdayMap()[$wd] ?? 0) === $dow) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if a date matches the monthly schedule.
     *
     * @since 1.0.0
     * @param \DateTime $date The date to check.
     * @param array $rec The recurrence settings with month days and weekday patterns.
     * @return bool True if date matches the monthly schedule.
     */
    private function matchesMonthlySchedule(\DateTime $date, array $rec): bool {
        $dom = (int) $date->format('j');
        foreach ($rec['monthDays'] ?? [] as $md) {
            $type = $md['type'] ?? '';
            if ($type === 'day' && (int) ($md['value'] ?? 0) === $dom) {
                return true;
            }
            if ($type === 'nth') {
                $target_dow = self::weekdayMap()[$md['weekday'] ?? ''] ?? 0;
                $nth        = (int) ($md['nth'] ?? 0);
                if ($target_dow === 0 || $nth === 0) {
                    continue;
                }
                $first      = (clone $date)->modify('first day of this month');
                $offset     = ($target_dow - (int) $first->format('N') + 7) % 7;
                $target_dom = 1 + $offset + ($nth - 1) * 7;
                if ($dom === $target_dom) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Preview what would be published if schedule ran now.
     *
     * @since 1.0.0
     * @return array Preview data with would_publish status and link information.
     */
    public function previewSchedule(): array {
        $config  = get_option('lynxjournal_schedule', []);
        $mode    = $config['mode']    ?? 'daily';
        $trigger = $config['trigger'] ?? [];

        $link_ids       = $this->getUnpublishedLinkIds();
        $total_count    = count($link_ids);
        $oldest_link_id = $link_ids[0] ?? null;
        $max_per_run    = (int) apply_filters('lynxjournal_max_per_run', self::MAX_PER_RUN);
        $batch_ids      = array_slice($link_ids, 0, $max_per_run);

        $would_publish = match ($mode) {
            'count'  => $total_count >= (int) ($trigger['count'] ?? 10),
            'age'    => $oldest_link_id !== null && $this->isLinkOlderThan($oldest_link_id, (int) ($trigger['days'] ?? 7)),
            'manual' => false,
            default  => !empty($batch_ids),
        };

        $by_category = [];
        if ($would_publish && !empty($batch_ids)) {
            // Prime post + term caches once so the per-link get_the_terms() calls
            // below are cache hits instead of a query per link.
            $this->primeLinkCaches($batch_ids);

            foreach ($batch_ids as $id) {
                $terms    = get_the_terms($id, 'lynxjournal_category');
                $cat_name = (!is_wp_error($terms) && !empty($terms))
                    ? $terms[0]->name
                    : __('Uncategorized', 'lynx-journal');
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
            'by_category'   => $by_category,
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
        if ($days === 0) {
            return true; // 0 = no age restriction, every link qualifies
        }
        $post = get_post($link_id);
        if (!$post) {
            return false;
        }
        $cutoff = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));
        return $post->post_date_gmt < $cutoff;
    }

    /**
     * ISO-8601 weekday abbreviation to DateTime::format('N') weekday number.
     *
     * Traits cannot declare `const` on PHP < 8.2, so this is a static
     * accessor instead of a trait constant (this project's minimum
     * supported PHP version is 8.0).
     *
     * @since 1.0.0
     * @return array<string,int>
     */
    private static function weekdayMap(): array {
        return ['MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6, 'SU' => 7];
    }
}
