<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shared logic for the two Telegram targets (group/channel post, personal
 * DM), which both use the same bot token and sendMessage mechanics and
 * differ only in which chat-id field they send to.
 *
 * @since 1.0.0
 */
abstract class LynxJournal_Notify_TelegramBase implements LynxJournal_Notify_Channel {

    /**
     * The `notify` field holding this target's Telegram chat ID.
     *
     * @since 1.0.0
     * @return string Field name.
     */
    abstract protected function chatIdField(): string;

    /**
     * The `notify` field toggling this target on/off.
     *
     * @since 1.0.0
     * @return string Field name.
     */
    abstract protected function enabledField(): string;

    /**
     * Error message shown when testing this target without required fields filled in.
     *
     * @since 1.0.0
     * @return string Translated message.
     */
    abstract protected function testMissingFieldMessage(): string;

    public function fields(): array {
        return ['telegramBotToken', $this->enabledField(), $this->chatIdField()];
    }

    public function isEnabled(array $notify): bool {
        return !empty($notify[$this->enabledField()]);
    }

    public function isComplete(array $notify): bool {
        return $this->isEnabled($notify) && !empty($notify['telegramBotToken']) && !empty($notify[$this->chatIdField()]);
    }

    /**
     * Validates the full Telegram field set (bot token, group/channel
     * target, DM target) regardless of which target this instance
     * represents — mirrors the original single validateNotifyTelegram()
     * which always sanitized both targets together since they share the
     * bot token.
     *
     * @since 1.0.0
     * @param array $notify The `notify` option array, modified in place.
     * @return \WP_Error|null Error if invalid, null if valid.
     */
    public function validate(array &$notify): ?\WP_Error {
        $notify['telegramEnabled'] = (bool) ($notify['telegramEnabled'] ?? false);
        $notify['telegramBotToken'] = !empty($notify['telegramBotToken'])
            ? sanitize_text_field(trim((string) $notify['telegramBotToken']))
            : '';
        $notify['telegramChatId'] = !empty($notify['telegramChatId'])
            ? sanitize_text_field(trim((string) $notify['telegramChatId']))
            : '';

        if ($notify['telegramBotToken'] !== '' && !preg_match('/^\d+:[\w-]{20,}$/', $notify['telegramBotToken'])) {
            return new \WP_Error('invalid_notify_telegram_token', __('notify.telegramBotToken must be a valid Telegram bot token', 'lynx-journal'), ['status' => 400]);
        }
        if ($notify['telegramChatId'] !== '' && !preg_match('/^-?\d+$/', $notify['telegramChatId'])) {
            return new \WP_Error('invalid_notify_telegram_chat_id', __('notify.telegramChatId must be a valid Telegram chat ID', 'lynx-journal'), ['status' => 400]);
        }

        if (!empty($notify['telegramEnabled'])) {
            if ($notify['telegramBotToken'] === '') {
                return new \WP_Error('invalid_notify_telegram_token', __('notify.telegramBotToken is required when Telegram notifications are enabled', 'lynx-journal'), ['status' => 400]);
            }
            if ($notify['telegramChatId'] === '') {
                return new \WP_Error('invalid_notify_telegram_chat_id', __('notify.telegramChatId is required when Telegram notifications are enabled', 'lynx-journal'), ['status' => 400]);
            }
        }

        $notify['telegramDmEnabled'] = (bool) ($notify['telegramDmEnabled'] ?? false);
        $notify['telegramDmChatId'] = !empty($notify['telegramDmChatId'])
            ? sanitize_text_field(trim((string) $notify['telegramDmChatId']))
            : '';

        if ($notify['telegramDmChatId'] !== '' && !preg_match('/^-?\d+$/', $notify['telegramDmChatId'])) {
            return new \WP_Error('invalid_notify_telegram_dm_chat_id', __('notify.telegramDmChatId must be a valid Telegram chat ID', 'lynx-journal'), ['status' => 400]);
        }

        if (!empty($notify['telegramDmEnabled'])) {
            if ($notify['telegramBotToken'] === '') {
                return new \WP_Error('invalid_notify_telegram_token', __('notify.telegramBotToken is required when Telegram DM notifications are enabled', 'lynx-journal'), ['status' => 400]);
            }
            if ($notify['telegramDmChatId'] === '') {
                return new \WP_Error('invalid_notify_telegram_dm_chat_id', __('notify.telegramDmChatId is required when Telegram DM notifications are enabled', 'lynx-journal'), ['status' => 400]);
            }
        }
        return null;
    }

    public function send(int|null $post_id, array $link_ids, string $mode, array $notify): true|\WP_Error {
        if (!$this->isComplete($notify)) {
            return true;
        }
        $message = $this->buildMessage($post_id, count($link_ids), $mode);
        return $this->postMessage($notify['telegramBotToken'], $notify[$this->chatIdField()], $message);
    }

    public function sendTest(array $notify): true|\WP_Error {
        if (!$this->isComplete($notify)) {
            return new \WP_Error('test_missing_field', $this->testMissingFieldMessage(), ['status' => 400]);
        }
        $message = __('This is a test notification from LynxJournal.', 'lynx-journal');
        return $this->postMessage($notify['telegramBotToken'], $notify[$this->chatIdField()], $message);
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
    private function postMessage(string $token, string $chatId, string $text): true|\WP_Error {
        $result = LynxJournal_Notify_Http::postJson(
            "https://api.telegram.org/bot{$token}/sendMessage",
            ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML'],
            [],
            'telegram_request_failed'
        );
        if (is_wp_error($result)) {
            return $result;
        }

        // Telegram's sendMessage returns HTTP 200 even on failure (bad chat_id,
        // bot blocked, etc.); the real signal is the "ok" boolean in the body,
        // same as Slack's chat.postMessage.
        $body = json_decode(wp_remote_retrieve_body($result), true);
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
    private function buildMessage(int|null $post_id, int $link_count, string $mode): string {
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
}
