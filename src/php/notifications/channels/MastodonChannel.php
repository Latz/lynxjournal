<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Mastodon direct-message run notifications.
 *
 * @since 1.0.0
 */
final class LynxJournal_Notify_MastodonChannel implements LynxJournal_Notify_Channel {

    public function key(): string {
        return 'mastodon';
    }

    public function fields(): array {
        return ['mastodonEnabled', 'mastodonInstanceUrl', 'mastodonAccessToken', 'mastodonRecipient'];
    }

    public function isEnabled(array $notify): bool {
        return !empty($notify['mastodonEnabled']);
    }

    public function isComplete(array $notify): bool {
        return $this->isEnabled($notify)
            && !empty($notify['mastodonInstanceUrl'])
            && !empty($notify['mastodonAccessToken'])
            && !empty($notify['mastodonRecipient']);
    }

    public function validate(array &$notify): ?\WP_Error {
        $notify['mastodonEnabled'] = (bool) ($notify['mastodonEnabled'] ?? false);
        $notify['mastodonAccessToken'] = !empty($notify['mastodonAccessToken'])
            ? sanitize_text_field(trim((string) $notify['mastodonAccessToken']))
            : '';
        $notify['mastodonRecipient'] = !empty($notify['mastodonRecipient'])
            ? sanitize_text_field(trim((string) $notify['mastodonRecipient']))
            : '';

        if (!empty($notify['mastodonInstanceUrl'])) {
            $instanceUrl = esc_url_raw(trim((string) $notify['mastodonInstanceUrl']));
            if (!$this->isValidInstanceUrl($instanceUrl)) {
                return new \WP_Error('invalid_notify_mastodon_instance', __('notify.mastodonInstanceUrl must be a valid https:// URL', 'lynx-journal'), ['status' => 400]);
            }
            $notify['mastodonInstanceUrl'] = $instanceUrl;
        } else {
            $notify['mastodonInstanceUrl'] = '';
        }

        if ($notify['mastodonRecipient'] !== '' && !preg_match('/^@[\w.]+@[\w.-]+\.[a-z]{2,}$/i', $notify['mastodonRecipient'])) {
            return new \WP_Error('invalid_notify_mastodon_recipient', __('notify.mastodonRecipient must be a valid handle, e.g. @user@instance.social', 'lynx-journal'), ['status' => 400]);
        }

        if (!empty($notify['mastodonEnabled'])) {
            if ($notify['mastodonInstanceUrl'] === '') {
                return new \WP_Error('invalid_notify_mastodon_instance', __('notify.mastodonInstanceUrl is required when Mastodon notifications are enabled', 'lynx-journal'), ['status' => 400]);
            }
            if ($notify['mastodonAccessToken'] === '') {
                return new \WP_Error('invalid_notify_mastodon_token', __('notify.mastodonAccessToken is required when Mastodon notifications are enabled', 'lynx-journal'), ['status' => 400]);
            }
            if ($notify['mastodonRecipient'] === '') {
                return new \WP_Error('invalid_notify_mastodon_recipient', __('notify.mastodonRecipient is required when Mastodon notifications are enabled', 'lynx-journal'), ['status' => 400]);
            }
        }
        return null;
    }

    public function send(int|null $post_id, array $link_ids, string $mode, array $notify): true|\WP_Error {
        if (!$this->isComplete($notify)) {
            return true;
        }
        $message = $this->buildMessage($notify['mastodonRecipient'], $post_id, count($link_ids), $mode);
        return $this->postStatus($notify['mastodonInstanceUrl'], $notify['mastodonAccessToken'], $message);
    }

    public function sendTest(array $notify): true|\WP_Error {
        if (!$this->isComplete($notify)) {
            return new \WP_Error('test_missing_field', __('Enable Mastodon notifications and fill in the instance URL, access token, and recipient handle first.', 'lynx-journal'), ['status' => 400]);
        }
        $message = $notify['mastodonRecipient'] . "\n" . __('This is a test notification from LynxJournal.', 'lynx-journal');
        return $this->postStatus($notify['mastodonInstanceUrl'], $notify['mastodonAccessToken'], $message);
    }

    /**
     * Post a direct-message status to a Mastodon instance.
     *
     * @since 1.0.0
     * @param string $instanceUrl Mastodon instance base URL.
     * @param string $token Mastodon access token.
     * @param string $status Status text (must include the recipient handle to be treated as a DM).
     * @return true|\WP_Error True on success, WP_Error with the failure reason otherwise.
     */
    private function postStatus(string $instanceUrl, string $token, string $status): true|\WP_Error {
        $url = rtrim($instanceUrl, '/') . '/api/v1/statuses';
        $result = LynxJournal_Notify_Http::postJson(
            $url,
            ['status' => $status, 'visibility' => 'direct'],
            ['Authorization' => 'Bearer ' . $token],
            'mastodon_request_failed'
        );
        return is_wp_error($result) ? $result : true;
    }

    /**
     * Build the Mastodon status text for a run notification.
     *
     * The recipient handle must appear in the status text for Mastodon to
     * treat this as a direct message rather than a public post.
     *
     * @since 1.0.0
     * @param string $recipient Mastodon handle to address, e.g. @user@instance.social.
     * @param int|null $post_id The published post ID, or null if nothing was published.
     * @param int $link_count Number of links included in the run.
     * @param string $mode The schedule mode that ran.
     * @return string Status text.
     */
    private function buildMessage(string $recipient, int|null $post_id, int $link_count, string $mode): string {
        $content = LynxJournal_Notify_RunMessageContent::forRun($post_id, $link_count, $mode);

        if ($content->published) {
            return "{$recipient}\n{$content->title}\n{$content->summary}\n{$content->url}";
        }
        return "{$recipient}\n{$content->summary}";
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
    private function isValidInstanceUrl(string $url): bool {
        if ($url === '') {
            return false;
        }
        $parts = wp_parse_url($url);
        return !empty($parts['scheme']) && $parts['scheme'] === 'https' && !empty($parts['host']);
    }
}
