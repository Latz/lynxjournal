<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Telegram "personal DM" notification target.
 *
 * @since 1.0.0
 */
final class LynxJournalNotifyTelegramDmChannel extends LynxJournalNotifyTelegramBase {

    public function key(): string {
        return 'telegram_dm';
    }

    protected function chatIdField(): string {
        return 'telegramDmChatId';
    }

    protected function enabledField(): string {
        return 'telegramDmEnabled';
    }

    protected function testMissingFieldMessage(): string {
        return __('Enable Telegram DM notifications and fill in the bot token and user chat ID first.', 'lynx-journal');
    }

    protected function chatIdErrorCode(): string {
        return 'invalid_notify_telegram_dm_chat_id';
    }

    protected function invalidChatIdMessage(): string {
        return __('notify.telegramDmChatId must be a valid Telegram chat ID', 'lynx-journal');
    }

    protected function chatIdRequiredMessage(): string {
        return __('notify.telegramDmChatId is required when Telegram DM notifications are enabled', 'lynx-journal');
    }

    protected function tokenRequiredMessage(): string {
        return __('notify.telegramBotToken is required when Telegram DM notifications are enabled', 'lynx-journal');
    }
}
