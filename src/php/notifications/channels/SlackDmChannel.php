<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Slack "personal DM" notification target.
 *
 * @since 1.0.0
 */
final class LynxJournalNotifySlackDmChannel extends LynxJournalNotifySlackBase {

    public function key(): string {
        return 'slack_dm';
    }

    protected function targetIdField(): string {
        return 'slackUserId';
    }

    protected function enabledField(): string {
        return 'slackDmEnabled';
    }

    protected function testMissingFieldMessage(): string {
        return __('Enable Slack DM notifications and fill in the bot token and user ID first.', 'lynx-journal');
    }

    protected function targetIdPattern(): string {
        return '/^U[A-Z0-9]+$/';
    }

    protected function targetIdErrorCode(): string {
        return 'invalid_notify_slack_user';
    }

    protected function invalidTargetIdMessage(): string {
        return __('notify.slackUserId must be a valid Slack user ID', 'lynx-journal');
    }

    protected function tokenRequiredMessage(): string {
        return __('notify.slackBotToken is required when Slack DM notifications are enabled', 'lynx-journal');
    }
}
