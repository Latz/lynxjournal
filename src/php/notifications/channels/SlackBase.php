<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shared logic for the two Slack targets (channel post, personal DM),
 * which both use the same bot token and Block Kit posting mechanics and
 * differ only in which target-id field they send to.
 *
 * @since 1.0.0
 */
abstract class LynxJournal_Notify_SlackBase implements LynxJournal_Notify_Channel {

    /**
     * The `notify` field holding this target's Slack channel/user ID.
     *
     * @since 1.0.0
     * @return string Field name.
     */
    abstract protected function targetIdField(): string;

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
        return ['slackBotToken', $this->enabledField(), $this->targetIdField()];
    }

    public function isEnabled(array $notify): bool {
        return !empty($notify[$this->enabledField()]);
    }

    public function isComplete(array $notify): bool {
        return $this->isEnabled($notify) && !empty($notify['slackBotToken']) && !empty($notify[$this->targetIdField()]);
    }

    /**
     * Validates the full Slack field set (bot token, channel target, DM
     * target) regardless of which target this instance represents — this
     * mirrors the original single validateNotifySlack() which always
     * sanitized both targets together since they share the bot token.
     *
     * @since 1.0.0
     * @param array $notify The `notify` option array, modified in place.
     * @return \WP_Error|null Error if invalid, null if valid.
     */
    public function validate(array &$notify): ?\WP_Error {
        $notify['slackBotToken'] = !empty($notify['slackBotToken'])
            ? sanitize_text_field((string) $notify['slackBotToken'])
            : '';
        if ($notify['slackBotToken'] !== '' && !preg_match('/^xoxb-[\w-]+$/', $notify['slackBotToken'])) {
            return new \WP_Error('invalid_notify_slack_token', __('notify.slackBotToken must be a valid Slack bot token (starting with xoxb-)', 'lynx-journal'), ['status' => 400]);
        }

        $notify['slackChannelEnabled'] = (bool) ($notify['slackChannelEnabled'] ?? false);
        $notify['slackChannelId'] = !empty($notify['slackChannelId'])
            ? strtoupper(sanitize_text_field(trim((string) $notify['slackChannelId'])))
            : '';
        if (!empty($notify['slackChannelEnabled'])) {
            if ($notify['slackBotToken'] === '') {
                return new \WP_Error('invalid_notify_slack_token', __('notify.slackBotToken is required when Slack channel notifications are enabled', 'lynx-journal'), ['status' => 400]);
            }
            if (!preg_match('/^[CG][A-Z0-9]+$/', $notify['slackChannelId'])) {
                return new \WP_Error('invalid_notify_slack_channel', __('notify.slackChannelId must be a valid Slack channel ID', 'lynx-journal'), ['status' => 400]);
            }
        }

        $notify['slackDmEnabled'] = (bool) ($notify['slackDmEnabled'] ?? false);
        $notify['slackUserId'] = !empty($notify['slackUserId'])
            ? strtoupper(sanitize_text_field(trim((string) $notify['slackUserId'])))
            : '';
        if (!empty($notify['slackDmEnabled'])) {
            if ($notify['slackBotToken'] === '') {
                return new \WP_Error('invalid_notify_slack_token', __('notify.slackBotToken is required when Slack DM notifications are enabled', 'lynx-journal'), ['status' => 400]);
            }
            if (!preg_match('/^U[A-Z0-9]+$/', $notify['slackUserId'])) {
                return new \WP_Error('invalid_notify_slack_user', __('notify.slackUserId must be a valid Slack user ID', 'lynx-journal'), ['status' => 400]);
            }
        }
        return null;
    }

    public function send(int|null $post_id, array $link_ids, string $mode, array $notify): true|\WP_Error {
        if (!$this->isComplete($notify)) {
            return true;
        }
        $blocks = $this->buildBlocks($post_id, count($link_ids), $mode);
        return $this->postToSlack($notify[$this->targetIdField()], $blocks, $notify['slackBotToken']);
    }

    public function sendTest(array $notify): true|\WP_Error {
        if (!$this->isComplete($notify)) {
            return new \WP_Error('test_missing_field', $this->testMissingFieldMessage(), ['status' => 400]);
        }
        $message = __('This is a test notification from LynxJournal.', 'lynx-journal');
        $blocks = [
            ['type' => 'header', 'text' => ['type' => 'plain_text', 'text' => __('Test notification', 'lynx-journal'), 'emoji' => true]],
            ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => $message]],
        ];
        return $this->postToSlack($notify[$this->targetIdField()], $blocks, $notify['slackBotToken']);
    }

    /**
     * Post a Block Kit message to a Slack channel or user via the Web API.
     *
     * @since 1.0.0
     * @param string $channel Slack channel ID or user ID (DM target).
     * @param array $blocks Slack Block Kit blocks.
     * @param string $token Slack bot token (xoxb-...).
     * @return true|\WP_Error True on success, WP_Error with the failure reason otherwise.
     */
    private function postToSlack(string $channel, array $blocks, string $token): true|\WP_Error {
        $result = LynxJournal_Notify_Http::postJson(
            'https://slack.com/api/chat.postMessage',
            ['channel' => $channel, 'blocks' => $blocks],
            ['Authorization' => 'Bearer ' . $token],
            'slack_request_failed'
        );
        if (is_wp_error($result)) {
            return $result;
        }

        // Slack's chat.postMessage returns HTTP 200 even on failure; the real
        // success/failure signal is the "ok" boolean in the JSON body.
        $body = json_decode(wp_remote_retrieve_body($result), true);
        if (empty($body['ok'])) {
            $error = $body['error'] ?? '';
            return new \WP_Error('slack_request_failed', $this->describeError($error), ['status' => 500]);
        }
        return true;
    }

    /**
     * Translate a Slack Web API error code into a friendlier, actionable
     * message. Unrecognized codes are passed through so nothing is hidden.
     *
     * @since 1.0.0
     * @param string $error Slack API error code (e.g. "not_in_channel").
     * @return string Human-readable error message.
     */
    private function describeError(string $error): string {
        return match ($error) {
            'not_in_channel' => __('The bot isn\'t a member of this channel. In Slack, open the channel and run "/invite @YourBotName", then try again.', 'lynx-journal'),
            'channel_not_found' => __('Slack channel not found. Double-check the channel ID.', 'lynx-journal'),
            'invalid_auth', 'account_inactive', 'token_revoked' => __('Slack bot token is invalid or has been revoked. Generate a new token and update it here.', 'lynx-journal'),
            'missing_scope' => __('The Slack bot token is missing a required permission scope. Reinstall the Slack app with the chat:write scope.', 'lynx-journal'),
            '' => __('Unknown Slack API error', 'lynx-journal'),
            default => $error,
        };
    }

    /**
     * Build the Slack Block Kit payload for a run notification.
     *
     * @since 1.0.0
     * @param int|null $post_id The published post ID, or null if nothing was published.
     * @param int $link_count Number of links included in the run.
     * @param string $mode The schedule mode that ran.
     * @return array Slack Block Kit blocks array.
     */
    private function buildBlocks(int|null $post_id, int $link_count, string $mode): array {
        if ($post_id) {
            return [
                ['type' => 'header', 'text' => ['type' => 'plain_text', 'text' => get_the_title($post_id), 'emoji' => true]],
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        /* translators: %s: post URL */
                        'text' => sprintf(__("A new roundup was published.\n<%s|View post>", 'lynx-journal'), get_permalink($post_id)),
                    ],
                ],
                [
                    'type'     => 'context',
                    'elements' => [
                        /* translators: %d: number of links published */
                        ['type' => 'mrkdwn', 'text' => sprintf(__('*Links:* %d', 'lynx-journal'), $link_count)],
                        /* translators: %s: schedule mode */
                        ['type' => 'mrkdwn', 'text' => sprintf(__('*Mode:* %s', 'lynx-journal'), $mode)],
                    ],
                ],
            ];
        }

        return [
            ['type' => 'header', 'text' => ['type' => 'plain_text', 'text' => __('LynxJournal schedule ran', 'lynx-journal'), 'emoji' => true]],
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    /* translators: %s: schedule mode */
                    'text' => sprintf(__('Schedule ran in %s mode but no post was published.', 'lynx-journal'), $mode),
                ],
            ],
        ];
    }
}
