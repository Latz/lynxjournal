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
        add_action('lynxjournal_execute_schedule', [$this, 'executeSchedule']);
        add_action('lynxjournal_after_run', [$this, 'maybeSendRunNotification'], 10, 3);
        add_action('lynxjournal_after_run', [$this, 'maybeSendWebhookNotification'], 20, 3);
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
        update_option('lynxjournal_run_history', array_slice($history, 0, 25));

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
        $title = sprintf(__('Links: %s', 'lynx-journal'), wp_date('F j, Y'));
        $title = (string) apply_filters('lynxjournal_digest_title', $title, $link_ids, $mode);

        // Resolve the author for the digest post. WP-Cron runs unauthenticated
        // (user 0), so we pass the stored publishAs user ID directly to
        // createDigestPost() as post_author instead of using wp_set_current_user().
        $publish_as = (int) ($config['publishAs'] ?? 0);
        if ($publish_as === 0 || !user_can($publish_as, 'publish_posts')) {
            $admin_ids  = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ids']);
            $publish_as = !empty($admin_ids) ? (int) $admin_ids[0] : 0;
        }

        do_action('lynxjournal_before_run', $link_ids, $mode);
        $as_draft = ($config['post_status'] ?? 'publish') === 'draft';
        $digest   = $this->createDigestPost($link_ids, $title, $as_draft, $mode, $publish_as);

        $post_id = ($digest['post_id'] ?? 0) ?: null;
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
            'published'  => $digest['success'] ?? false,
            'post_id'    => $post_id,
            'link_count' => count($link_ids),
            'reason'     => $digest['message'] ?? null,
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
        $map = ['MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6, 'SU' => 7];
        $dow = (int) $date->format('N');
        foreach ($rec['weekdays'] ?? [] as $wd) {
            if (($map[$wd] ?? 0) === $dow) {
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
        $map = ['MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6, 'SU' => 7];
        foreach ($rec['monthDays'] ?? [] as $md) {
            $type = $md['type'] ?? '';
            if ($type === 'day' && (int) ($md['value'] ?? 0) === $dom) {
                return true;
            }
            if ($type === 'weekday') {
                $target_dow = $map[$md['weekday'] ?? ''] ?? 0;
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
            foreach ($batch_ids as $id) {
                $terms    = wp_get_object_terms($id, 'lynxjournal_category', ['fields' => 'names']);
                $cat_name = (!is_wp_error($terms) && !empty($terms))
                    ? $terms[0]
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
            'by_category'   => array_values($by_category),
            'mode'          => $mode,
        ];
    }

    /**
     * Send notification email after schedule runs, if enabled.
     *
     * @since 1.0.0
     * @param int|null $post_id The published post ID, or null if nothing was published.
     * @param array $link_ids Array of link post IDs that were published.
     * @param string $mode The schedule mode that ran.
     * @return void
     */
    public function maybeSendRunNotification(int|null $post_id, array $link_ids, string $mode): void {
        $config = get_option('lynxjournal_schedule', []);
        $notify = $config['notify'] ?? [];
        if (empty($notify['enabled'])) {
            return;
        }
        $to = !empty($notify['email']) ? $notify['email'] : get_option('admin_email');
        /* translators: %d: number of links published */
        $subject = sprintf(__('[LynxJournal] Digest published: %d links', 'lynx-journal'), count($link_ids));
        if ($post_id) {
            $message = sprintf(
                /* translators: 1: link count, 2: post URL */
                __("A new digest was published.\n\nLinks: %1\$d\nView: %2\$s", 'lynx-journal'),
                count($link_ids),
                get_permalink($post_id)
            );
        } else {
            $message = sprintf(
                /* translators: %s: schedule mode */
                __('Schedule ran in %s mode but no post was published.', 'lynx-journal'),
                $mode
            );
        }
        wp_mail($to, $subject, $message);
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

    /**
     * Send Discord/Slack webhook notifications after schedule runs, if configured.
     *
     * @since 2.0.0
     * @param int|null $post_id The published post ID, or null if nothing was published.
     * @param array $link_ids Array of link post IDs that were published.
     * @param string $mode The schedule mode that ran.
     * @return void
     */
    public function maybeSendWebhookNotification(int|null $post_id, array $link_ids, string $mode): void {
        $config   = get_option('lynxjournal_schedule', []);
        $notify   = $config['notify'] ?? [];
        $count    = count($link_ids);
        $post_url = $post_id ? get_permalink($post_id) : null;

        if (!empty($notify['discord_webhook'])) {
            $this->sendDiscordNotification($notify['discord_webhook'], $count, $post_url);
        }
        if (!empty($notify['slack_webhook'])) {
            $this->sendSlackNotification($notify['slack_webhook'], $count, $post_url);
        }
        if (!empty($notify['telegram_bot_token']) && !empty($notify['telegram_chat_id'])) {
            $this->sendTelegramNotification($notify['telegram_bot_token'], $notify['telegram_chat_id'], $count, $post_url);
        }
    }

    /**
     * Send a Discord webhook notification after a digest run.
     *
     * @since 2.0.0
     * @param string      $webhook_url Discord incoming webhook URL.
     * @param int         $count       Number of links published.
     * @param string|null $post_url    URL of the published post, or null if none.
     * @return void
     */
    private function sendDiscordNotification(string $webhook_url, int $count, ?string $post_url): void {
        if ($post_url) {
            $description = sprintf(
                /* translators: 1: number of links published, 2: post URL */
                __('%1$d links published. [View post](%2$s)', 'lynx-journal'),
                $count,
                $post_url
            );
        } else {
            /* translators: %d: number of links processed */
            $description = sprintf(__('%d links processed. No post published.', 'lynx-journal'), $count);
        }
        $payload = [
            'embeds' => [[
                'title'       => __('LynxJournal: digest published', 'lynx-journal'),
                'description' => $description,
                'color'       => 0x2D9BF0,
            ]],
        ];
        wp_remote_post($webhook_url, [
            'headers'     => ['Content-Type' => 'application/json'],
            'body'        => wp_json_encode($payload),
            'blocking'    => false,
            'data_format' => 'body',
        ]);
    }

    /**
     * Send a Telegram bot message notification after a digest run.
     *
     * @since 2.0.0
     * @param string      $bot_token Telegram bot token.
     * @param string      $chat_id   Telegram chat ID.
     * @param int         $count     Number of links published.
     * @param string|null $post_url  URL of the published post, or null if none.
     * @return void
     */
    private function sendTelegramNotification(string $bot_token, string $chat_id, int $count, ?string $post_url): void {
        if ($post_url) {
            $text = sprintf(
                /* translators: 1: number of links published, 2: post URL */
                __('<b>LynxJournal:</b> %1$d links published. <a href="%2$s">View post</a>', 'lynx-journal'),
                $count,
                esc_url($post_url)
            );
        } else {
            /* translators: %d: number of links processed */
            $text = sprintf(__('<b>LynxJournal:</b> %d links processed. No post published.', 'lynx-journal'), $count);
        }
        wp_remote_post('https://api.telegram.org/bot' . $bot_token . '/sendMessage', [
            'headers'     => ['Content-Type' => 'application/json'],
            'body'        => wp_json_encode(['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'HTML']),
            'blocking'    => false,
            'data_format' => 'body',
        ]);
    }

    /**
     * Send a Slack webhook notification after a digest run.
     *
     * @since 2.0.0
     * @param string      $webhook_url Slack incoming webhook URL.
     * @param int         $count       Number of links published.
     * @param string|null $post_url    URL of the published post, or null if none.
     * @return void
     */
    private function sendSlackNotification(string $webhook_url, int $count, ?string $post_url): void {
        if ($post_url) {
            /* translators: 1: number of links published, 2: post URL */
            $text = sprintf(__('*LynxJournal:* %1$d links published. <%2$s|View post>', 'lynx-journal'), $count, $post_url);
        } else {
            /* translators: %d: number of links processed */
            $text = sprintf(__('*LynxJournal:* %d links processed. No post published.', 'lynx-journal'), $count);
        }
        wp_remote_post($webhook_url, [
            'headers'     => ['Content-Type' => 'application/json'],
            'body'        => wp_json_encode(['text' => $text]),
            'blocking'    => false,
            'data_format' => 'body',
        ]);
    }
}
