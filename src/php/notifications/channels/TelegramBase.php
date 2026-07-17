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
abstract class LynxJournalNotifyTelegramBase implements LynxJournalNotifyChannel {

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

    /**
     * WP_Error code used for both a malformed and a missing chat ID.
     *
     * @since 1.0.0
     * @return string Error code.
     */
    abstract protected function chatIdErrorCode(): string;

    /**
     * Error message for a malformed chat ID.
     *
     * @since 1.0.0
     * @return string Translated message.
     */
    abstract protected function invalidChatIdMessage(): string;

    /**
     * Error message when the chat ID is missing but this target is enabled.
     *
     * @since 1.0.0
     * @return string Translated message.
     */
    abstract protected function chatIdRequiredMessage(): string;

    /**
     * Error message when the bot token is missing but this target is enabled.
     *
     * @since 1.0.0
     * @return string Translated message.
     */
    abstract protected function tokenRequiredMessage(): string;

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
     * Validates the shared bot token plus only this instance's own target
     * field (group/channel chat ID or DM chat ID). Each target is
     * validated independently so that invalid/incomplete input in the
     * *other* Telegram target never blocks testing or saving this one —
     * the two targets are otherwise-unrelated settings that merely share
     * a bot token field.
     *
     * @since 1.0.0
     * @param array $notify The `notify` option array, modified in place.
     * @return \WP_Error|null Error if invalid, null if valid.
     */
    public function validate(array &$notify): ?\WP_Error {
        $notify['telegramBotToken'] = !empty($notify['telegramBotToken'])
            ? sanitize_text_field(trim((string) $notify['telegramBotToken']))
            : '';
        if ($notify['telegramBotToken'] !== '' && !preg_match('/^\d+:[\w-]{20,}$/', $notify['telegramBotToken'])) {
            return new \WP_Error('invalid_notify_telegram_token', __('notify.telegramBotToken must be a valid Telegram bot token', 'lynx-journal'), ['status' => 400]);
        }

        $enabledField = $this->enabledField();
        $chatIdField = $this->chatIdField();
        $notify[$enabledField] = (bool) ($notify[$enabledField] ?? false);
        $notify[$chatIdField] = !empty($notify[$chatIdField])
            ? sanitize_text_field(trim((string) $notify[$chatIdField]))
            : '';

        if ($notify[$chatIdField] !== '' && !preg_match('/^-?\d+$/', $notify[$chatIdField])) {
            return new \WP_Error($this->chatIdErrorCode(), $this->invalidChatIdMessage(), ['status' => 400]);
        }
        return $this->validateRequiredWhenEnabled($notify, $enabledField, $chatIdField);
    }

    /**
     * When this target is enabled, check that the bot token and chat ID are both present.
     *
     * @since 1.0.0
     * @param array $notify The sanitized notify settings.
     * @param string $enabledField This target's enabled-toggle field name.
     * @param string $chatIdField This target's chat ID field name.
     * @return \WP_Error|null Error if invalid, null if valid.
     */
    private function validateRequiredWhenEnabled(array $notify, string $enabledField, string $chatIdField): ?\WP_Error {
        if (empty($notify[$enabledField])) {
            return null;
        }
        if ($notify['telegramBotToken'] === '') {
            return new \WP_Error('invalid_notify_telegram_token', $this->tokenRequiredMessage(), ['status' => 400]);
        }
        if ($notify[$chatIdField] === '') {
            return new \WP_Error($this->chatIdErrorCode(), $this->chatIdRequiredMessage(), ['status' => 400]);
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
        $result = LynxJournalNotifyHttp::postJson(
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
        $content = LynxJournalNotifyRunMessageContent::forRun($post_id, $link_count, $mode);

        if ($content->published) {
            $title = esc_html($content->title);
            $url   = esc_url($content->url);
            return "<b>{$title}</b>\n{$content->summary}\n{$url}";
        }
        return $content->summary;
    }
}
