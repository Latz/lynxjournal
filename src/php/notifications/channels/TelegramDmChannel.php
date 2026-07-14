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
final class LynxJournal_Notify_TelegramDmChannel extends LynxJournal_Notify_TelegramBase {

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
}
