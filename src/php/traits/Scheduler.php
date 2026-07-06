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
        add_action('lynxjournal_after_run', [$this, 'maybeSendDiscordNotification'], 10, 3);
        add_action('lynxjournal_after_run', [$this, 'maybeSendSlackChannelNotification'], 10, 3);
        add_action('lynxjournal_after_run', [$this, 'maybeSendSlackDmNotification'], 10, 3);
        add_action('lynxjournal_after_run', [$this, 'maybeSendTelegramNotification'], 10, 3);
        add_action('lynxjournal_after_run', [$this, 'maybeSendMastodonNotification'], 10, 3);
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
    private const WEEKDAY_MAP = ['MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6, 'SU' => 7];

    private function matchesWeeklySchedule(\DateTime $date, array $rec): bool {
        $dow = (int) $date->format('N');
        foreach ($rec['weekdays'] ?? [] as $wd) {
            if ((self::WEEKDAY_MAP[$wd] ?? 0) === $dow) {
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
            if ($type === 'weekday') {
                $target_dow = self::WEEKDAY_MAP[$md['weekday'] ?? ''] ?? 0;
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
        $to        = !empty($notify['email']) ? $notify['email'] : get_option('admin_email');
        $link_count = count($link_ids);
        /* translators: %d: number of links published */
        $subject = sprintf(__('[LynxJournal] Roundup published: %d links', 'lynx-journal'), $link_count);
        if ($post_id) {
            $message = sprintf(
                /* translators: 1: link count, 2: post URL */
                __("A new roundup was published.\n\nLinks: %1\$d\nView: %2\$s", 'lynx-journal'),
                $link_count,
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
     * Send a Discord webhook notification after schedule runs, if enabled.
     *
     * @since 1.0.0
     * @param int|null $post_id The published post ID, or null if nothing was published.
     * @param array $link_ids Array of link post IDs that were published.
     * @param string $mode The schedule mode that ran.
     * @return void
     */
    public function maybeSendDiscordNotification(int|null $post_id, array $link_ids, string $mode): void {
        $config = get_option('lynxjournal_schedule', []);
        $notify = $config['notify'] ?? [];
        if (empty($notify['discordEnabled']) || empty($notify['discordWebhookUrl'])) {
            return;
        }

        $embed = $this->buildDiscordEmbed($post_id, count($link_ids), $mode);
        $this->postDiscordWebhook($notify['discordWebhookUrl'], $embed);
    }

    /**
     * Post an embed to a Discord webhook.
     *
     * @since 1.0.0
     * @param string $url Discord webhook URL.
     * @param array $embed Discord embed object.
     * @return true|\WP_Error True on success, WP_Error with the failure reason otherwise.
     */
    private function postDiscordWebhook(string $url, array $embed): true|\WP_Error {
        $response = wp_remote_post($url, [
            'timeout' => 8,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode(['embeds' => [$embed]]),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }
        if (wp_remote_retrieve_response_code($response) >= 300) {
            return new \WP_Error('discord_request_failed', wp_remote_retrieve_body($response), ['status' => 500]);
        }
        return true;
    }

    /**
     * Build the Discord embed payload for a run notification.
     *
     * @since 1.0.0
     * @param int|null $post_id The published post ID, or null if nothing was published.
     * @param int $link_count Number of links included in the run.
     * @param string $mode The schedule mode that ran.
     * @return array Discord embed object.
     */
    private function buildDiscordEmbed(int|null $post_id, int $link_count, string $mode): array {
        if ($post_id) {
            return [
                'title'       => get_the_title($post_id),
                'url'         => get_permalink($post_id),
                /* translators: %d: number of links published */
                'description' => sprintf(__('A new roundup was published with %d links.', 'lynx-journal'), $link_count),
                'color'       => 0x5865F2, // Discord blurple
                'fields'      => [
                    ['name' => __('Links', 'lynx-journal'), 'value' => (string) $link_count, 'inline' => true],
                    ['name' => __('Mode', 'lynx-journal'), 'value' => $mode, 'inline' => true],
                ],
                'timestamp'   => gmdate('c'),
            ];
        }

        return [
            'title'       => __('LynxJournal schedule ran', 'lynx-journal'),
            /* translators: %s: schedule mode */
            'description' => sprintf(__('Schedule ran in %s mode but no post was published.', 'lynx-journal'), $mode),
            'color'       => 0x99AAB5, // neutral grey
            'timestamp'   => gmdate('c'),
        ];
    }

    /**
     * Send a Slack channel notification after schedule runs, if enabled.
     *
     * @since 1.0.0
     * @param int|null $post_id The published post ID, or null if nothing was published.
     * @param array $link_ids Array of link post IDs that were published.
     * @param string $mode The schedule mode that ran.
     * @return void
     */
    public function maybeSendSlackChannelNotification(int|null $post_id, array $link_ids, string $mode): void {
        $config = get_option('lynxjournal_schedule', []);
        $notify = $config['notify'] ?? [];
        if (empty($notify['slackChannelEnabled']) || empty($notify['slackBotToken']) || empty($notify['slackChannelId'])) {
            return;
        }
        $blocks = $this->buildSlackBlocks($post_id, count($link_ids), $mode);
        $this->postToSlack($notify['slackChannelId'], $blocks, $notify['slackBotToken']);
    }

    /**
     * Send a Slack direct message notification after schedule runs, if enabled.
     *
     * @since 1.0.0
     * @param int|null $post_id The published post ID, or null if nothing was published.
     * @param array $link_ids Array of link post IDs that were published.
     * @param string $mode The schedule mode that ran.
     * @return void
     */
    public function maybeSendSlackDmNotification(int|null $post_id, array $link_ids, string $mode): void {
        $config = get_option('lynxjournal_schedule', []);
        $notify = $config['notify'] ?? [];
        if (empty($notify['slackDmEnabled']) || empty($notify['slackBotToken']) || empty($notify['slackUserId'])) {
            return;
        }
        $blocks = $this->buildSlackBlocks($post_id, count($link_ids), $mode);
        $this->postToSlack($notify['slackUserId'], $blocks, $notify['slackBotToken']);
    }

    /**
     * Post a Block Kit message to a Slack channel or user via the Web API.
     *
     * @since 1.0.0
     * @param string $channel Slack channel ID or user ID (DM target).
     * @param array $blocks Slack Block Kit blocks.
     * @param string $token Slack bot token (xoxb-...).
     * @return true|\WP_Error True on success, WP_Error with the failure reason otherwise.
     */
    private function postToSlack(string $channel, array $blocks, string $token): true|\WP_Error {
        $response = wp_remote_post('https://slack.com/api/chat.postMessage', [
            'timeout' => 8,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
            'body' => wp_json_encode(['channel' => $channel, 'blocks' => $blocks]),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }
        if (wp_remote_retrieve_response_code($response) >= 300) {
            return new \WP_Error('slack_request_failed', wp_remote_retrieve_body($response), ['status' => 500]);
        }

        // Slack's chat.postMessage returns HTTP 200 even on failure; the real
        // success/failure signal is the "ok" boolean in the JSON body.
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['ok'])) {
            return new \WP_Error('slack_request_failed', $body['error'] ?? __('Unknown Slack API error', 'lynx-journal'), ['status' => 500]);
        }
        return true;
    }

    /**
     * Build the Slack Block Kit payload for a run notification.
     *
     * @since 1.0.0
     * @param int|null $post_id The published post ID, or null if nothing was published.
     * @param int $link_count Number of links included in the run.
     * @param string $mode The schedule mode that ran.
     * @return array Slack Block Kit blocks array.
     */
    private function buildSlackBlocks(int|null $post_id, int $link_count, string $mode): array {
        if ($post_id) {
            return [
                ['type' => 'header', 'text' => ['type' => 'plain_text', 'text' => get_the_title($post_id), 'emoji' => true]],
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        /* translators: %s: post URL */
                        'text' => sprintf(__("A new roundup was published.\n<%s|View post>", 'lynx-journal'), get_permalink($post_id)),
                    ],
                ],
                [
                    'type'     => 'context',
                    'elements' => [
                        /* translators: %d: number of links published */
                        ['type' => 'mrkdwn', 'text' => sprintf(__('*Links:* %d', 'lynx-journal'), $link_count)],
                        /* translators: %s: schedule mode */
                        ['type' => 'mrkdwn', 'text' => sprintf(__('*Mode:* %s', 'lynx-journal'), $mode)],
                    ],
                ],
            ];
        }

        return [
            ['type' => 'header', 'text' => ['type' => 'plain_text', 'text' => __('LynxJournal schedule ran', 'lynx-journal'), 'emoji' => true]],
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    /* translators: %s: schedule mode */
                    'text' => sprintf(__('Schedule ran in %s mode but no post was published.', 'lynx-journal'), $mode),
                ],
            ],
        ];
    }

    /**
     * Send a Telegram notification after schedule runs, if enabled.
     *
     * @since 1.0.0
     * @param int|null $post_id The published post ID, or null if nothing was published.
     * @param array $link_ids Array of link post IDs that were published.
     * @param string $mode The schedule mode that ran.
     * @return void
     */
    public function maybeSendTelegramNotification(int|null $post_id, array $link_ids, string $mode): void {
        $config = get_option('lynxjournal_schedule', []);
        $notify = $config['notify'] ?? [];
        if (empty($notify['telegramEnabled']) || empty($notify['telegramBotToken']) || empty($notify['telegramChatId'])) {
            return;
        }

        $message = $this->buildTelegramMessage($post_id, count($link_ids), $mode);
        $this->postTelegramMessage($notify['telegramBotToken'], $notify['telegramChatId'], $message);
    }

    /**
     * Send a message via the Telegram Bot API.
     *
     * @since 1.0.0
     * @param string $token Telegram bot token.
     * @param string $chatId Telegram chat ID to send to.
     * @param string $text HTML-formatted message text.
     * @return true|\WP_Error True on success, WP_Error with the failure reason otherwise.
     */
    private function postTelegramMessage(string $token, string $chatId, string $text): true|\WP_Error {
        $response = wp_remote_post("https://api.telegram.org/bot{$token}/sendMessage", [
            'timeout' => 8,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode([
                'chat_id'    => $chatId,
                'text'       => $text,
                'parse_mode' => 'HTML',
            ]),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }
        if (wp_remote_retrieve_response_code($response) >= 300) {
            return new \WP_Error('telegram_request_failed', wp_remote_retrieve_body($response), ['status' => 500]);
        }

        // Telegram's sendMessage returns HTTP 200 even on failure (bad chat_id,
        // bot blocked, etc.); the real signal is the "ok" boolean in the body,
        // same as Slack's chat.postMessage.
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['ok'])) {
            return new \WP_Error('telegram_request_failed', $body['description'] ?? __('Unknown Telegram API error', 'lynx-journal'), ['status' => 500]);
        }
        return true;
    }

    /**
     * Build the Telegram HTML message for a run notification.
     *
     * @since 1.0.0
     * @param int|null $post_id The published post ID, or null if nothing was published.
     * @param int $link_count Number of links included in the run.
     * @param string $mode The schedule mode that ran.
     * @return string HTML-formatted message text.
     */
    private function buildTelegramMessage(int|null $post_id, int $link_count, string $mode): string {
        if ($post_id) {
            $title = esc_html(get_the_title($post_id));
            $url   = esc_url(get_permalink($post_id));
            /* translators: %d: number of links published */
            $body  = sprintf(__('A new roundup was published with %d links.', 'lynx-journal'), $link_count);
            return "<b>{$title}</b>\n{$body}\n{$url}";
        }

        /* translators: %s: schedule mode */
        return sprintf(__('Schedule ran in %s mode but no post was published.', 'lynx-journal'), $mode);
    }

    /**
     * Send a Mastodon direct message after schedule runs, if enabled.
     *
     * @since 1.0.0
     * @param int|null $post_id The published post ID, or null if nothing was published.
     * @param array $link_ids Array of link post IDs that were published.
     * @param string $mode The schedule mode that ran.
     * @return void
     */
    public function maybeSendMastodonNotification(int|null $post_id, array $link_ids, string $mode): void {
        $config = get_option('lynxjournal_schedule', []);
        $notify = $config['notify'] ?? [];
        if (empty($notify['mastodonEnabled']) || empty($notify['mastodonInstanceUrl']) || empty($notify['mastodonAccessToken']) || empty($notify['mastodonRecipient'])) {
            return;
        }

        $message = $this->buildMastodonMessage($notify['mastodonRecipient'], $post_id, count($link_ids), $mode);
        $this->postMastodonStatus($notify['mastodonInstanceUrl'], $notify['mastodonAccessToken'], $message);
    }

    /**
     * Post a direct-message status to a Mastodon instance.
     *
     * @since 1.0.0
     * @param string $instanceUrl Mastodon instance base URL.
     * @param string $token Mastodon access token.
     * @param string $status Status text (must include the recipient handle to be treated as a DM).
     * @return true|\WP_Error True on success, WP_Error with the failure reason otherwise.
     */
    private function postMastodonStatus(string $instanceUrl, string $token, string $status): true|\WP_Error {
        $url = rtrim($instanceUrl, '/') . '/api/v1/statuses';

        $response = wp_remote_post($url, [
            'timeout' => 8,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
            'body' => wp_json_encode(['status' => $status, 'visibility' => 'direct']),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }
        if (wp_remote_retrieve_response_code($response) >= 300) {
            return new \WP_Error('mastodon_request_failed', wp_remote_retrieve_body($response), ['status' => 500]);
        }
        return true;
    }

    /**
     * Build the Mastodon status text for a run notification.
     *
     * The recipient handle must appear in the status text for Mastodon to
     * treat this as a direct message rather than a public post.
     *
     * @since 1.0.0
     * @param string $recipient Mastodon handle to address, e.g. @user@instance.social.
     * @param int|null $post_id The published post ID, or null if nothing was published.
     * @param int $link_count Number of links included in the run.
     * @param string $mode The schedule mode that ran.
     * @return string Status text.
     */
    private function buildMastodonMessage(string $recipient, int|null $post_id, int $link_count, string $mode): string {
        if ($post_id) {
            $title = esc_html(get_the_title($post_id));
            $url   = esc_url(get_permalink($post_id));
            /* translators: %d: number of links published */
            $body  = sprintf(__('A new roundup was published with %d links.', 'lynx-journal'), $link_count);
            return "{$recipient}\n{$title}\n{$body}\n{$url}";
        }

        /* translators: %s: schedule mode */
        $body = sprintf(__('Schedule ran in %s mode but no post was published.', 'lynx-journal'), $mode);
        return "{$recipient}\n{$body}";
    }

    /**
     * Send a one-off test notification for a single channel, using ad-hoc
     * (possibly unsaved) notify settings rather than the stored option.
     *
     * @since 1.0.0
     * @param string $channel One of email|discord|slack_channel|slack_dm|telegram|mastodon.
     * @param array $notify Notify settings to test with (already sanitized by validateNotify()).
     * @return true|\WP_Error True on success, WP_Error describing why the test couldn't be sent.
     */
    public function dispatchTestNotification(string $channel, array $notify): true|\WP_Error {
        $message = __('This is a test notification from LynxJournal.', 'lynx-journal');

        switch ($channel) {
            case 'email':
                if (empty($notify['enabled'])) {
                    return new \WP_Error('test_missing_field', __('Enable email notifications first.', 'lynx-journal'), ['status' => 400]);
                }
                $to = !empty($notify['email']) ? $notify['email'] : get_option('admin_email');
                return wp_mail($to, __('[LynxJournal] Test notification', 'lynx-journal'), $message)
                    ? true
                    : new \WP_Error('test_failed', __('wp_mail() failed to send the test email.', 'lynx-journal'), ['status' => 500]);

            case 'discord':
                if (empty($notify['discordEnabled']) || empty($notify['discordWebhookUrl'])) {
                    return new \WP_Error('test_missing_field', __('Enable Discord notifications and enter a webhook URL first.', 'lynx-journal'), ['status' => 400]);
                }
                return $this->postDiscordWebhook($notify['discordWebhookUrl'], [
                    'title'       => __('Test notification', 'lynx-journal'),
                    'description' => $message,
                    'color'       => 0x5865F2,
                    'timestamp'   => gmdate('c'),
                ]);

            case 'slack_channel':
                if (empty($notify['slackChannelEnabled']) || empty($notify['slackBotToken']) || empty($notify['slackChannelId'])) {
                    return new \WP_Error('test_missing_field', __('Enable Slack channel notifications and fill in the bot token and channel ID first.', 'lynx-journal'), ['status' => 400]);
                }
                return $this->postToSlack($notify['slackChannelId'], $this->buildTestSlackBlocks($message), $notify['slackBotToken']);

            case 'slack_dm':
                if (empty($notify['slackDmEnabled']) || empty($notify['slackBotToken']) || empty($notify['slackUserId'])) {
                    return new \WP_Error('test_missing_field', __('Enable Slack DM notifications and fill in the bot token and user ID first.', 'lynx-journal'), ['status' => 400]);
                }
                return $this->postToSlack($notify['slackUserId'], $this->buildTestSlackBlocks($message), $notify['slackBotToken']);

            case 'telegram':
                if (empty($notify['telegramEnabled']) || empty($notify['telegramBotToken']) || empty($notify['telegramChatId'])) {
                    return new \WP_Error('test_missing_field', __('Enable Telegram notifications and fill in the bot token and chat ID first.', 'lynx-journal'), ['status' => 400]);
                }
                return $this->postTelegramMessage($notify['telegramBotToken'], $notify['telegramChatId'], $message);

            case 'mastodon':
                if (empty($notify['mastodonEnabled']) || empty($notify['mastodonInstanceUrl']) || empty($notify['mastodonAccessToken']) || empty($notify['mastodonRecipient'])) {
                    return new \WP_Error('test_missing_field', __('Enable Mastodon notifications and fill in the instance URL, access token, and recipient handle first.', 'lynx-journal'), ['status' => 400]);
                }
                return $this->postMastodonStatus($notify['mastodonInstanceUrl'], $notify['mastodonAccessToken'], $notify['mastodonRecipient'] . "\n" . $message);

            default:
                return new \WP_Error('invalid_channel', __('Unknown notification channel.', 'lynx-journal'), ['status' => 400]);
        }
    }

    /**
     * Build the Slack Block Kit payload for a test notification.
     *
     * @since 1.0.0
     * @param string $message Test message text.
     * @return array Slack Block Kit blocks array.
     */
    private function buildTestSlackBlocks(string $message): array {
        return [
            ['type' => 'header', 'text' => ['type' => 'plain_text', 'text' => __('Test notification', 'lynx-journal'), 'emoji' => true]],
            ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => $message]],
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
}
