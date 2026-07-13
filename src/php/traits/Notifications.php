<?php

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait LynxJournal_Notifications {

    /**
     * Register notification hooks and callbacks.
     *
     * Called from the main plugin initialization.
     *
     * @since 1.0.0
     * @return void
     */
    public function registerNotificationHooks(): void {
        add_action('lynxjournal_after_run', [$this, 'maybeSendRunNotification'], 10, 3);
        add_action('lynxjournal_after_run', [$this, 'maybeSendDiscordNotification'], 10, 3);
        add_action('lynxjournal_after_run', [$this, 'maybeSendSlackChannelNotification'], 10, 3);
        add_action('lynxjournal_after_run', [$this, 'maybeSendSlackDmNotification'], 10, 3);
        add_action('lynxjournal_after_run', [$this, 'maybeSendTelegramNotification'], 10, 3);
        add_action('lynxjournal_after_run', [$this, 'maybeSendTelegramDmNotification'], 10, 3);
        add_action('lynxjournal_after_run', [$this, 'maybeSendMastodonNotification'], 10, 3);
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
            $error = $body['error'] ?? '';
            return new \WP_Error('slack_request_failed', $this->describeSlackError($error), ['status' => 500]);
        }
        return true;
    }

    /**
     * Translate a Slack Web API error code into a friendlier, actionable
     * message. Unrecognized codes are passed through so nothing is hidden.
     *
     * @since 1.0.0
     * @param string $error Slack API error code (e.g. "not_in_channel").
     * @return string Human-readable error message.
     */
    private function describeSlackError(string $error): string {
        return match ($error) {
            'not_in_channel' => __('The bot isn\'t a member of this channel. In Slack, open the channel and run "/invite @YourBotName", then try again.', 'lynx-journal'),
            'channel_not_found' => __('Slack channel not found. Double-check the channel ID.', 'lynx-journal'),
            'invalid_auth', 'account_inactive', 'token_revoked' => __('Slack bot token is invalid or has been revoked. Generate a new token and update it here.', 'lynx-journal'),
            'missing_scope' => __('The Slack bot token is missing a required permission scope. Reinstall the Slack app with the chat:write scope.', 'lynx-journal'),
            '' => __('Unknown Slack API error', 'lynx-journal'),
            default => $error,
        };
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
     * Send a Telegram personal DM notification after schedule runs, if enabled.
     *
     * @since 1.0.0
     * @param int|null $post_id The published post ID, or null if nothing was published.
     * @param array $link_ids Array of link post IDs that were published.
     * @param string $mode The schedule mode that ran.
     * @return void
     */
    public function maybeSendTelegramDmNotification(int|null $post_id, array $link_ids, string $mode): void {
        $config = get_option('lynxjournal_schedule', []);
        $notify = $config['notify'] ?? [];
        if (empty($notify['telegramDmEnabled']) || empty($notify['telegramBotToken']) || empty($notify['telegramDmChatId'])) {
            return;
        }

        $message = $this->buildTelegramMessage($post_id, count($link_ids), $mode);
        $this->postTelegramMessage($notify['telegramBotToken'], $notify['telegramDmChatId'], $message);
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
     * @param string $channel One of email|discord|slack_channel|slack_dm|telegram|telegram_dm|mastodon.
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

            case 'telegram_dm':
                if (empty($notify['telegramDmEnabled']) || empty($notify['telegramBotToken']) || empty($notify['telegramDmChatId'])) {
                    return new \WP_Error('test_missing_field', __('Enable Telegram DM notifications and fill in the bot token and user chat ID first.', 'lynx-journal'), ['status' => 400]);
                }
                return $this->postTelegramMessage($notify['telegramBotToken'], $notify['telegramDmChatId'], $message);

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
     * Send a one-off test notification for a single channel via REST.
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request The REST request with channel and notify settings.
     * @return \WP_REST_Response|\WP_Error Response or error.
     */
    public function restTestNotification(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        $channel = (string) $request->get_param('channel');
        if (!in_array($channel, ['email', 'discord', 'slack_channel', 'slack_dm', 'telegram', 'telegram_dm', 'mastodon'], true)) {
            return new \WP_Error('invalid_channel', __('Unknown notification channel.', 'lynx-journal'), array('status' => 400));
        }

        $data = array('notify' => $request->get_param('notify'));
        $validated = $this->validateNotifyChannel($channel, $data);
        if (is_wp_error($validated)) {
            return $validated;
        }

        $result = $this->dispatchTestNotification($channel, $data['notify']);
        if (is_wp_error($result)) {
            return $result;
        }
        return rest_ensure_response(array('success' => true));
    }

    /**
     * Persist just one notification channel's fields into the stored
     * schedule config, leaving everything else (other channels, mode,
     * times, trigger, etc.) untouched.
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request The REST request with channel and notify settings.
     * @return \WP_REST_Response|\WP_Error Response with the saved fields, or error.
     */
    public function restSaveNotification(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        $channel = (string) $request->get_param('channel');
        if (!in_array($channel, ['email', 'discord', 'slack_channel', 'slack_dm', 'telegram', 'telegram_dm', 'mastodon'], true)) {
            return new \WP_Error('invalid_channel', __('Unknown notification channel.', 'lynx-journal'), array('status' => 400));
        }

        $data = array('notify' => $request->get_param('notify'));
        $validated = $this->validateNotifyChannel($channel, $data);
        if (is_wp_error($validated)) {
            return $validated;
        }

        $fields = $this->notifyChannelFields($channel);
        $config = get_option('lynxjournal_schedule', array());
        if (!is_array($config)) {
            $config = array();
        }
        if (!isset($config['notify']) || !is_array($config['notify'])) {
            $config['notify'] = array();
        }
        foreach ($fields as $field) {
            $config['notify'][$field] = $data['notify'][$field];
        }
        update_option('lynxjournal_schedule', $config);

        return rest_ensure_response(array(
            'success' => true,
            'notify'  => array_intersect_key($data['notify'], array_flip($fields)),
        ));
    }

    /**
     * The notify field names that belong to one notification channel.
     *
     * @since 1.0.0
     * @param string $channel One of email|discord|slack_channel|slack_dm|telegram|telegram_dm|mastodon.
     * @return string[] Field names within notify.
     */
    private function notifyChannelFields(string $channel): array {
        return match ($channel) {
            'email' => ['enabled', 'email'],
            'discord' => ['discordEnabled', 'discordWebhookUrl'],
            'slack_channel' => ['slackBotToken', 'slackChannelEnabled', 'slackChannelId'],
            'slack_dm' => ['slackBotToken', 'slackDmEnabled', 'slackUserId'],
            'telegram' => ['telegramEnabled', 'telegramBotToken', 'telegramChatId'],
            'telegram_dm' => ['telegramBotToken', 'telegramDmEnabled', 'telegramDmChatId'],
            'mastodon' => ['mastodonEnabled', 'mastodonInstanceUrl', 'mastodonAccessToken', 'mastodonRecipient'],
            default => [],
        };
    }
}
