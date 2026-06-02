<?php

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait LinkDigest_ScheduleNotifier {

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
        $config = get_option('linkdigest_schedule', []);
        $notify = $config['notify'] ?? [];
        if (empty($notify['enabled'])) {
            return;
        }
        $to = !empty($notify['email']) ? $notify['email'] : get_option('admin_email');
        /* translators: %d: number of links published */
        $subject = sprintf(__('[LinkDigest] Digest published: %d links', 'linkdigest'), count($link_ids));
        if ($post_id) {
            $message = sprintf(
                /* translators: 1: link count, 2: post URL */
                __("A new digest was published.\n\nLinks: %1\$d\nView: %2\$s", 'linkdigest'),
                count($link_ids),
                get_permalink($post_id)
            );
        } else {
            $message = sprintf(
                /* translators: %s: schedule mode */
                __('Schedule ran in %s mode but no post was published.', 'linkdigest'),
                $mode
            );
        }
        wp_mail($to, $subject, $message);
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
        $config   = get_option('linkdigest_schedule', []);
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
                __('%1$d links published. [View post](%2$s)', 'linkdigest'),
                $count,
                $post_url
            );
        } else {
            /* translators: %d: number of links processed */
            $description = sprintf(__('%d links processed. No post published.', 'linkdigest'), $count);
        }
        $payload = [
            'embeds' => [[
                'title'       => __('LinkDigest: digest published', 'linkdigest'),
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
                __('<b>LinkDigest:</b> %1$d links published. <a href="%2$s">View post</a>', 'linkdigest'),
                $count,
                esc_url($post_url)
            );
        } else {
            /* translators: %d: number of links processed */
            $text = sprintf(__('<b>LinkDigest:</b> %d links processed. No post published.', 'linkdigest'), $count);
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
            $text = sprintf(__('*LinkDigest:* %1$d links published. <%2$s|View post>', 'linkdigest'), $count, $post_url);
        } else {
            /* translators: %d: number of links processed */
            $text = sprintf(__('*LinkDigest:* %d links processed. No post published.', 'linkdigest'), $count);
        }
        wp_remote_post($webhook_url, [
            'headers'     => ['Content-Type' => 'application/json'],
            'body'        => wp_json_encode(['text' => $text]),
            'blocking'    => false,
            'data_format' => 'body',
        ]);
    }
}
