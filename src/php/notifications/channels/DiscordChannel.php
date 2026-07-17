<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Discord webhook run notifications.
 *
 * @since 1.0.0
 */
final class LynxJournalNotifyDiscordChannel implements LynxJournalNotifyChannel {

    public function key(): string {
        return 'discord';
    }

    public function fields(): array {
        return ['discordEnabled', 'discordWebhookUrl'];
    }

    public function isEnabled(array $notify): bool {
        return !empty($notify['discordEnabled']);
    }

    public function isComplete(array $notify): bool {
        return $this->isEnabled($notify) && !empty($notify['discordWebhookUrl']);
    }

    public function validate(array &$notify): ?\WP_Error {
        $notify['discordEnabled'] = (bool) ($notify['discordEnabled'] ?? false);
        if (!empty($notify['discordWebhookUrl'])) {
            $url = esc_url_raw(trim((string) $notify['discordWebhookUrl']));
            if (!$this->isValidWebhookUrl($url)) {
                return new \WP_Error('invalid_notify_discord_url', __('notify.discordWebhookUrl must be a valid Discord webhook URL', 'lynx-journal'), ['status' => 400]);
            }
            $notify['discordWebhookUrl'] = $url;
        } elseif (!empty($notify['discordEnabled'])) {
            return new \WP_Error('invalid_notify_discord_url', __('notify.discordWebhookUrl is required when Discord notifications are enabled', 'lynx-journal'), ['status' => 400]);
        }
        return null;
    }

    public function send(int|null $post_id, array $link_ids, string $mode, array $notify): true|\WP_Error {
        if (!$this->isComplete($notify)) {
            return true;
        }
        return $this->post($notify['discordWebhookUrl'], $this->buildEmbed($post_id, count($link_ids), $mode));
    }

    public function sendTest(array $notify): true|\WP_Error {
        if (!$this->isComplete($notify)) {
            return new \WP_Error('test_missing_field', __('Enable Discord notifications and enter a webhook URL first.', 'lynx-journal'), ['status' => 400]);
        }
        return $this->post($notify['discordWebhookUrl'], [
            'title'       => __('Test notification', 'lynx-journal'),
            'description' => __('This is a test notification from LynxJournal.', 'lynx-journal'),
            'color'       => 0x5865F2,
            'timestamp'   => gmdate('c'),
        ]);
    }

    /**
     * Post an embed to the Discord webhook.
     *
     * @since 1.0.0
     * @param string $url Discord webhook URL.
     * @param array $embed Discord embed object.
     * @return true|\WP_Error True on success, WP_Error with the failure reason otherwise.
     */
    private function post(string $url, array $embed): true|\WP_Error {
        $result = LynxJournalNotifyHttp::postJson($url, ['embeds' => [$embed]], [], 'discord_request_failed');
        return is_wp_error($result) ? $result : true;
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
    private function buildEmbed(int|null $post_id, int $link_count, string $mode): array {
        $content = LynxJournalNotifyRunMessageContent::forRun($post_id, $link_count, $mode);

        if ($content->published) {
            return [
                'title'       => $content->title,
                'url'         => $content->url,
                'description' => $content->summary,
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
            'description' => $content->summary,
            'color'       => 0x99AAB5, // neutral grey
            'timestamp'   => gmdate('c'),
        ];
    }

    /**
     * Check whether a URL looks like a genuine Discord webhook endpoint.
     *
     * @since 1.0.0
     * @param string $url Sanitized URL to check.
     * @return bool True if it matches Discord's webhook host/path convention.
     */
    private function isValidWebhookUrl(string $url): bool {
        if ($url === '') {
            return false;
        }
        $parts = wp_parse_url($url);
        $validHost = !empty($parts['scheme']) && $parts['scheme'] === 'https' && !empty($parts['host'])
            && in_array(strtolower($parts['host']), ['discord.com', 'discordapp.com', 'ptb.discord.com', 'canary.discord.com'], true);

        return $validHost && (bool) preg_match('#^/api(?:/v\d+)?/webhooks/\d+/[\w-]+$#', $parts['path'] ?? '');
    }
}
