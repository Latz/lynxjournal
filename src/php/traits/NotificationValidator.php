<?php

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait LynxJournal_NotificationValidator {

    private function validateNotify(array &$data): ?\WP_Error {
        if (!isset($data['notify']) || !is_array($data['notify'])) {
            return isset($data['notify']) ? new \WP_Error('invalid_notify', __('notify must be an object', 'lynx-journal'), ['status' => 400]) : null;
        }
        $data['notify']['enabled'] = (bool) ($data['notify']['enabled'] ?? false);

        return $this->validateNotifyEmail($data)
            ?? $this->validateNotifyDiscord($data)
            ?? $this->validateNotifySlack($data)
            ?? $this->validateNotifyTelegram($data)
            ?? $this->validateNotifyMastodon($data);
    }

    /**
     * Validate and sanitize only the notification fields for one channel.
     *
     * Used by the single-channel test-notification endpoint so that stray
     * or unfinished input in an unrelated channel's fields doesn't block
     * testing the channel currently being configured.
     *
     * @since 1.0.0
     * @param string $channel One of email|discord|slack_channel|slack_dm|telegram|mastodon.
     * @param array $data The request data containing a 'notify' key.
     * @return \WP_Error|null Error if invalid, null if valid.
     */
    public function validateNotifyChannel(string $channel, array &$data): ?\WP_Error {
        if (!isset($data['notify']) || !is_array($data['notify'])) {
            return isset($data['notify']) ? new \WP_Error('invalid_notify', __('notify must be an object', 'lynx-journal'), ['status' => 400]) : null;
        }
        return match ($channel) {
            'email' => $this->validateNotifyEmail($data),
            'discord' => $this->validateNotifyDiscord($data),
            'slack_channel', 'slack_dm' => $this->validateNotifySlack($data),
            'telegram' => $this->validateNotifyTelegram($data),
            'mastodon' => $this->validateNotifyMastodon($data),
            default => new \WP_Error('invalid_channel', __('Unknown notification channel.', 'lynx-journal'), ['status' => 400]),
        };
    }

    private function validateNotifyEmail(array &$data): ?\WP_Error {
        if (!empty($data['notify']['email'])) {
            $data['notify']['email'] = sanitize_email($data['notify']['email']);
            if (!is_email($data['notify']['email'])) {
                return new \WP_Error('invalid_notify_email', __('notify.email is not a valid email address', 'lynx-journal'), ['status' => 400]);
            }
        }
        return null;
    }

    private function validateNotifyDiscord(array &$data): ?\WP_Error {
        $data['notify']['discordEnabled'] = (bool) ($data['notify']['discordEnabled'] ?? false);
        if (!empty($data['notify']['discordWebhookUrl'])) {
            $url = esc_url_raw(trim((string) $data['notify']['discordWebhookUrl']));
            if (!$this->isValidDiscordWebhookUrl($url)) {
                return new \WP_Error('invalid_notify_discord_url', __('notify.discordWebhookUrl must be a valid Discord webhook URL', 'lynx-journal'), ['status' => 400]);
            }
            $data['notify']['discordWebhookUrl'] = $url;
        } elseif (!empty($data['notify']['discordEnabled'])) {
            return new \WP_Error('invalid_notify_discord_url', __('notify.discordWebhookUrl is required when Discord notifications are enabled', 'lynx-journal'), ['status' => 400]);
        }
        return null;
    }

    private function validateNotifySlack(array &$data): ?\WP_Error {
        $data['notify']['slackBotToken'] = !empty($data['notify']['slackBotToken'])
            ? sanitize_text_field((string) $data['notify']['slackBotToken'])
            : '';
        if ($data['notify']['slackBotToken'] !== '' && !preg_match('/^xoxb-[\w-]+$/', $data['notify']['slackBotToken'])) {
            return new \WP_Error('invalid_notify_slack_token', __('notify.slackBotToken must be a valid Slack bot token (starting with xoxb-)', 'lynx-journal'), ['status' => 400]);
        }

        $data['notify']['slackChannelEnabled'] = (bool) ($data['notify']['slackChannelEnabled'] ?? false);
        $data['notify']['slackChannelId'] = !empty($data['notify']['slackChannelId'])
            ? strtoupper(sanitize_text_field(trim((string) $data['notify']['slackChannelId'])))
            : '';
        if (!empty($data['notify']['slackChannelEnabled'])) {
            if ($data['notify']['slackBotToken'] === '') {
                return new \WP_Error('invalid_notify_slack_token', __('notify.slackBotToken is required when Slack channel notifications are enabled', 'lynx-journal'), ['status' => 400]);
            }
            if (!preg_match('/^[CG][A-Z0-9]+$/', $data['notify']['slackChannelId'])) {
                return new \WP_Error('invalid_notify_slack_channel', __('notify.slackChannelId must be a valid Slack channel ID', 'lynx-journal'), ['status' => 400]);
            }
        }

        $data['notify']['slackDmEnabled'] = (bool) ($data['notify']['slackDmEnabled'] ?? false);
        $data['notify']['slackUserId'] = !empty($data['notify']['slackUserId'])
            ? strtoupper(sanitize_text_field(trim((string) $data['notify']['slackUserId'])))
            : '';
        if (!empty($data['notify']['slackDmEnabled'])) {
            if ($data['notify']['slackBotToken'] === '') {
                return new \WP_Error('invalid_notify_slack_token', __('notify.slackBotToken is required when Slack DM notifications are enabled', 'lynx-journal'), ['status' => 400]);
            }
            if (!preg_match('/^U[A-Z0-9]+$/', $data['notify']['slackUserId'])) {
                return new \WP_Error('invalid_notify_slack_user', __('notify.slackUserId must be a valid Slack user ID', 'lynx-journal'), ['status' => 400]);
            }
        }
        return null;
    }

    private function validateNotifyTelegram(array &$data): ?\WP_Error {
        $data['notify']['telegramEnabled'] = (bool) ($data['notify']['telegramEnabled'] ?? false);
        $data['notify']['telegramBotToken'] = !empty($data['notify']['telegramBotToken'])
            ? sanitize_text_field(trim((string) $data['notify']['telegramBotToken']))
            : '';
        $data['notify']['telegramChatId'] = !empty($data['notify']['telegramChatId'])
            ? sanitize_text_field(trim((string) $data['notify']['telegramChatId']))
            : '';

        if ($data['notify']['telegramBotToken'] !== '' && !preg_match('/^\d+:[\w-]{20,}$/', $data['notify']['telegramBotToken'])) {
            return new \WP_Error('invalid_notify_telegram_token', __('notify.telegramBotToken must be a valid Telegram bot token', 'lynx-journal'), ['status' => 400]);
        }
        if ($data['notify']['telegramChatId'] !== '' && !preg_match('/^-?\d+$/', $data['notify']['telegramChatId'])) {
            return new \WP_Error('invalid_notify_telegram_chat_id', __('notify.telegramChatId must be a valid Telegram chat ID', 'lynx-journal'), ['status' => 400]);
        }

        if (!empty($data['notify']['telegramEnabled'])) {
            if ($data['notify']['telegramBotToken'] === '') {
                return new \WP_Error('invalid_notify_telegram_token', __('notify.telegramBotToken is required when Telegram notifications are enabled', 'lynx-journal'), ['status' => 400]);
            }
            if ($data['notify']['telegramChatId'] === '') {
                return new \WP_Error('invalid_notify_telegram_chat_id', __('notify.telegramChatId is required when Telegram notifications are enabled', 'lynx-journal'), ['status' => 400]);
            }
        }
        return null;
    }

    private function validateNotifyMastodon(array &$data): ?\WP_Error {
        $data['notify']['mastodonEnabled'] = (bool) ($data['notify']['mastodonEnabled'] ?? false);
        $data['notify']['mastodonAccessToken'] = !empty($data['notify']['mastodonAccessToken'])
            ? sanitize_text_field(trim((string) $data['notify']['mastodonAccessToken']))
            : '';
        $data['notify']['mastodonRecipient'] = !empty($data['notify']['mastodonRecipient'])
            ? sanitize_text_field(trim((string) $data['notify']['mastodonRecipient']))
            : '';

        if (!empty($data['notify']['mastodonInstanceUrl'])) {
            $instanceUrl = esc_url_raw(trim((string) $data['notify']['mastodonInstanceUrl']));
            if (!$this->isValidMastodonInstanceUrl($instanceUrl)) {
                return new \WP_Error('invalid_notify_mastodon_instance', __('notify.mastodonInstanceUrl must be a valid https:// URL', 'lynx-journal'), ['status' => 400]);
            }
            $data['notify']['mastodonInstanceUrl'] = $instanceUrl;
        } else {
            $data['notify']['mastodonInstanceUrl'] = '';
        }

        if ($data['notify']['mastodonRecipient'] !== '' && !preg_match('/^@[\w.]+@[\w.-]+\.[a-z]{2,}$/i', $data['notify']['mastodonRecipient'])) {
            return new \WP_Error('invalid_notify_mastodon_recipient', __('notify.mastodonRecipient must be a valid handle, e.g. @user@instance.social', 'lynx-journal'), ['status' => 400]);
        }

        if (!empty($data['notify']['mastodonEnabled'])) {
            if ($data['notify']['mastodonInstanceUrl'] === '') {
                return new \WP_Error('invalid_notify_mastodon_instance', __('notify.mastodonInstanceUrl is required when Mastodon notifications are enabled', 'lynx-journal'), ['status' => 400]);
            }
            if ($data['notify']['mastodonAccessToken'] === '') {
                return new \WP_Error('invalid_notify_mastodon_token', __('notify.mastodonAccessToken is required when Mastodon notifications are enabled', 'lynx-journal'), ['status' => 400]);
            }
            if ($data['notify']['mastodonRecipient'] === '') {
                return new \WP_Error('invalid_notify_mastodon_recipient', __('notify.mastodonRecipient is required when Mastodon notifications are enabled', 'lynx-journal'), ['status' => 400]);
            }
        }
        return null;
    }

    /**
     * Check whether a URL looks like a genuine Discord webhook endpoint.
     *
     * @since 1.0.0
     * @param string $url Sanitized URL to check.
     * @return bool True if it matches Discord's webhook host/path convention.
     */
    private function isValidDiscordWebhookUrl(string $url): bool {
        if ($url === '') {
            return false;
        }
        $parts = wp_parse_url($url);
        if (empty($parts['scheme']) || $parts['scheme'] !== 'https' || empty($parts['host'])) {
            return false;
        }
        $host = strtolower($parts['host']);
        if (!in_array($host, ['discord.com', 'discordapp.com', 'ptb.discord.com', 'canary.discord.com'], true)) {
            return false;
        }
        $path = $parts['path'] ?? '';
        return (bool) preg_match('#^/api(?:/v\d+)?/webhooks/\d+/[\w-]+$#', $path);
    }

    /**
     * Check whether a URL looks like a valid Mastodon instance URL.
     *
     * Mastodon is federated, so any HTTPS host is potentially valid — this
     * only checks the URL is well-formed, not a specific host allowlist.
     *
     * @since 1.0.0
     * @param string $url Sanitized URL to check.
     * @return bool True if it's a well-formed https:// URL.
     */
    private function isValidMastodonInstanceUrl(string $url): bool {
        if ($url === '') {
            return false;
        }
        $parts = wp_parse_url($url);
        return !empty($parts['scheme']) && $parts['scheme'] === 'https' && !empty($parts['host']);
    }
}
