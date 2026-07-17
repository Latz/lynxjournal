<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Slack "post to a channel" notification target.
 *
 * @since 1.0.0
 */
final class LynxJournal_Notify_SlackChannelChannel extends LynxJournal_Notify_SlackBase {

    public function key(): string {
        return 'slack_channel';
    }

    protected function targetIdField(): string {
        return 'slackChannelId';
    }

    protected function enabledField(): string {
        return 'slackChannelEnabled';
    }

    protected function testMissingFieldMessage(): string {
        return __('Enable Slack channel notifications and fill in the bot token and channel ID first.', 'lynx-journal');
    }

    protected function targetIdPattern(): string {
        return '/^[CG][A-Z0-9]+$/';
    }

    protected function targetIdErrorCode(): string {
        return 'invalid_notify_slack_channel';
    }

    protected function invalidTargetIdMessage(): string {
        return __('notify.slackChannelId must be a valid Slack channel ID', 'lynx-journal');
    }

    protected function tokenRequiredMessage(): string {
        return __('notify.slackBotToken is required when Slack channel notifications are enabled', 'lynx-journal');
    }
}
