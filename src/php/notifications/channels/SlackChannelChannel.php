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
}
