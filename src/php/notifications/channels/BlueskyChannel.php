<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Bluesky direct-message run notifications via the AT Protocol.
 *
 * @since 1.0.0
 */
final class LynxJournalNotifyBlueskyChannel implements LynxJournalNotifyChannel {

    private const BEARER_PREFIX = 'Bearer ';

    public function key(): string {
        return 'bluesky';
    }

    public function fields(): array {
        return ['bskyEnabled', 'bskyHandle', 'bskyAppPassword', 'bskyRecipient'];
    }

    public function isEnabled(array $notify): bool {
        return !empty($notify['bskyEnabled']);
    }

    public function isComplete(array $notify): bool {
        return $this->isEnabled($notify)
            && !empty($notify['bskyHandle'])
            && !empty($notify['bskyAppPassword'])
            && !empty($notify['bskyRecipient']);
    }

    public function validate(array &$notify): ?\WP_Error {
        $notify['bskyEnabled'] = (bool) ($notify['bskyEnabled'] ?? false);
        foreach (['bskyHandle', 'bskyAppPassword', 'bskyRecipient'] as $field) {
            $notify[$field] = !empty($notify[$field]) ? sanitize_text_field(trim((string) $notify[$field])) : '';
        }

        return $this->validateFieldFormats($notify) ?? $this->validateRequiredWhenEnabled($notify);
    }

    /**
     * Check that any non-empty Bluesky fields match their expected format.
     *
     * @since 1.0.0
     * @param array $notify The sanitized notify settings.
     * @return \WP_Error|null Error for the first malformed field, or null if all are valid.
     */
    private function validateFieldFormats(array $notify): ?\WP_Error {
        $checks = [
            'bskyHandle'      => ['/^[\w.-]+\.[a-z]{2,}$/i', 'invalid_notify_bsky_handle', __('notify.bskyHandle must be a valid Bluesky handle, e.g. user.bsky.social', 'lynx-journal')],
            'bskyAppPassword' => ['/^[\w]{4}-[\w]{4}-[\w]{4}-[\w]{4}$/', 'invalid_notify_bsky_password', __('notify.bskyAppPassword must be a valid Bluesky app password (xxxx-xxxx-xxxx-xxxx)', 'lynx-journal')],
            'bskyRecipient'   => ['/^[\w.-]+\.[a-z]{2,}$/i', 'invalid_notify_bsky_recipient', __('notify.bskyRecipient must be a valid Bluesky handle, e.g. friend.bsky.social', 'lynx-journal')],
        ];
        foreach ($checks as $field => [$pattern, $code, $message]) {
            if ($notify[$field] !== '' && !preg_match($pattern, $notify[$field])) {
                return new \WP_Error($code, $message, ['status' => 400]);
            }
        }
        return null;
    }

    /**
     * When Bluesky notifications are enabled, check that all required fields are filled in.
     *
     * @since 1.0.0
     * @param array $notify The sanitized notify settings.
     * @return \WP_Error|null Error for the first missing required field, or null if valid.
     */
    private function validateRequiredWhenEnabled(array $notify): ?\WP_Error {
        if (empty($notify['bskyEnabled'])) {
            return null;
        }
        $required = [
            'bskyHandle'      => ['invalid_notify_bsky_handle', __('notify.bskyHandle is required when Bluesky notifications are enabled', 'lynx-journal')],
            'bskyAppPassword' => ['invalid_notify_bsky_password', __('notify.bskyAppPassword is required when Bluesky notifications are enabled', 'lynx-journal')],
            'bskyRecipient'   => ['invalid_notify_bsky_recipient', __('notify.bskyRecipient is required when Bluesky notifications are enabled', 'lynx-journal')],
        ];
        foreach ($required as $field => [$code, $message]) {
            if ($notify[$field] === '') {
                return new \WP_Error($code, $message, ['status' => 400]);
            }
        }
        return null;
    }

    public function send(int|null $post_id, array $link_ids, string $mode, array $notify): true|\WP_Error {
        if (!$this->isComplete($notify)) {
            return true;
        }
        $message = $this->buildMessage($post_id, count($link_ids), $mode);
        return $this->postDm($notify['bskyHandle'], $notify['bskyAppPassword'], $notify['bskyRecipient'], $message);
    }

    public function sendTest(array $notify): true|\WP_Error {
        if (!$this->isComplete($notify)) {
            return new \WP_Error('test_missing_field', __('Enable Bluesky notifications and fill in the handle, app password, and recipient handle first.', 'lynx-journal'), ['status' => 400]);
        }
        $message = __('This is a test notification from LynxJournal.', 'lynx-journal');
        return $this->postDm($notify['bskyHandle'], $notify['bskyAppPassword'], $notify['bskyRecipient'], $message);
    }

    /**
     * Send a Bluesky direct message to a recipient handle.
     *
     * Performs the full AT Protocol handshake: create a session, resolve the
     * recipient handle to a DID, open the DM conversation, then send the
     * message. A fresh session is created per send since Bluesky sessions
     * are short-lived and sends only happen on cron runs or test clicks.
     *
     * @since 1.0.0
     * @param string $handle Bluesky handle (identifier) of the sending account.
     * @param string $appPassword Bluesky app password.
     * @param string $recipientHandle Bluesky handle of the DM recipient.
     * @param string $text Message text.
     * @return true|\WP_Error True on success, WP_Error with the failure reason otherwise.
     */
    private function postDm(string $handle, string $appPassword, string $recipientHandle, string $text): true|\WP_Error {
        $session = $this->createSession($handle, $appPassword);
        if (is_wp_error($session)) {
            return $session;
        }
        return $this->sendToRecipient($session, $recipientHandle, $text);
    }

    /**
     * Resolve the recipient and open a DM conversation, then send the message.
     *
     * @since 1.0.0
     * @param array{accessJwt: string, did: string} $session Session data from createSession().
     * @param string $recipientHandle Bluesky handle of the DM recipient.
     * @param string $text Message text.
     * @return true|\WP_Error True on success, WP_Error with the failure reason otherwise.
     */
    private function sendToRecipient(array $session, string $recipientHandle, string $text): true|\WP_Error {
        $recipientDid = $this->resolveHandle($recipientHandle, $session['accessJwt']);
        if (is_wp_error($recipientDid)) {
            return $recipientDid;
        }

        $convoId = $this->getConvoId($session['did'], $recipientDid, $session['accessJwt']);
        if (is_wp_error($convoId)) {
            return $convoId;
        }

        $result = LynxJournalNotifyHttp::postJson(
            'https://bsky.chat/xrpc/chat.bsky.convo.sendMessage',
            ['convoId' => $convoId, 'message' => ['text' => $text]],
            ['Authorization' => self::BEARER_PREFIX . $session['accessJwt']],
            'bluesky_send_failed'
        );
        return is_wp_error($result) ? $result : true;
    }

    /**
     * Create a short-lived Bluesky session for the sending account.
     *
     * @since 1.0.0
     * @param string $handle Bluesky handle (identifier) of the sending account.
     * @param string $appPassword Bluesky app password.
     * @return array{accessJwt: string, did: string}|\WP_Error Session data on success, WP_Error otherwise.
     */
    private function createSession(string $handle, string $appPassword): array|\WP_Error {
        $result = LynxJournalNotifyHttp::postJson(
            'https://bsky.social/xrpc/com.atproto.server.createSession',
            ['identifier' => $handle, 'password' => $appPassword],
            [],
            'bluesky_auth_failed'
        );
        if (is_wp_error($result)) {
            return $result;
        }

        $body = json_decode(wp_remote_retrieve_body($result), true);
        if (empty($body['accessJwt']) || empty($body['did'])) {
            return new \WP_Error('bluesky_auth_failed', __('Bluesky did not return a valid session. Check the handle and app password.', 'lynx-journal'), ['status' => 500]);
        }
        return ['accessJwt' => $body['accessJwt'], 'did' => $body['did']];
    }

    /**
     * Resolve a Bluesky handle to its DID.
     *
     * @since 1.0.0
     * @param string $handle Bluesky handle to resolve, e.g. "user.bsky.social".
     * @param string $accessJwt Access token from createSession().
     * @return string|\WP_Error The resolved DID, or WP_Error on failure.
     */
    private function resolveHandle(string $handle, string $accessJwt): string|\WP_Error {
        $url = add_query_arg('handle', rawurlencode($handle), 'https://bsky.social/xrpc/com.atproto.identity.resolveHandle');
        $response = wp_remote_get($url, [
            'timeout' => 8,
            'headers' => ['Authorization' => self::BEARER_PREFIX . $accessJwt],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $failed = wp_remote_retrieve_response_code($response) >= 300;
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if ($failed || empty($body['did'])) {
            return new \WP_Error('bluesky_resolve_failed', __('Could not resolve the Bluesky recipient handle.', 'lynx-journal'), ['status' => 500]);
        }
        return $body['did'];
    }

    /**
     * Get (or create) the DM conversation ID between the sender and recipient.
     *
     * @since 1.0.0
     * @param string $senderDid DID of the sending account.
     * @param string $recipientDid DID of the recipient account.
     * @param string $accessJwt Access token from createSession().
     * @return string|\WP_Error The conversation ID, or WP_Error on failure.
     */
    private function getConvoId(string $senderDid, string $recipientDid, string $accessJwt): string|\WP_Error {
        $result = LynxJournalNotifyHttp::postJson(
            'https://bsky.chat/xrpc/chat.bsky.convo.getConvoForMembers',
            ['members' => [$senderDid, $recipientDid]],
            ['Authorization' => self::BEARER_PREFIX . $accessJwt],
            'bluesky_convo_failed'
        );
        if (is_wp_error($result)) {
            return $result;
        }

        $body = json_decode(wp_remote_retrieve_body($result), true);
        if (empty($body['convo']['id'])) {
            return new \WP_Error('bluesky_convo_failed', __('Could not open a Bluesky DM conversation with the recipient.', 'lynx-journal'), ['status' => 500]);
        }
        return $body['convo']['id'];
    }

    /**
     * Build the Bluesky DM text for a run notification.
     *
     * @since 1.0.0
     * @param int|null $post_id The published post ID, or null if nothing was published.
     * @param int $link_count Number of links included in the run.
     * @param string $mode The schedule mode that ran.
     * @return string Message text.
     */
    private function buildMessage(int|null $post_id, int $link_count, string $mode): string {
        $content = LynxJournalNotifyRunMessageContent::forRun($post_id, $link_count, $mode);

        if ($content->published) {
            return "{$content->title}\n{$content->summary}\n{$content->url}";
        }
        return $content->summary;
    }
}
