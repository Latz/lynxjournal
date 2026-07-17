<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Telegram "post to a group/channel" notification target.
 *
 * @since 1.0.0
 */
final class LynxJournal_Notify_TelegramChannel extends LynxJournal_Notify_TelegramBase {

    public function key(): string {
        return 'telegram';
    }

    protected function chatIdField(): string {
        return 'telegramChatId';
    }

    protected function enabledField(): string {
        return 'telegramEnabled';
    }

    protected function testMissingFieldMessage(): string {
        return __('Enable Telegram notifications and fill in the bot token and chat ID first.', 'lynx-journal');
    }

    protected function chatIdErrorCode(): string {
        return 'invalid_notify_telegram_chat_id';
    }

    protected function invalidChatIdMessage(): string {
        return __('notify.telegramChatId must be a valid Telegram chat ID', 'lynx-journal');
    }

    protected function chatIdRequiredMessage(): string {
        return __('notify.telegramChatId is required when Telegram notifications are enabled', 'lynx-journal');
    }

    protected function tokenRequiredMessage(): string {
        return __('notify.telegramBotToken is required when Telegram notifications are enabled', 'lynx-journal');
    }
}
