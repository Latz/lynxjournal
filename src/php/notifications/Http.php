<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shared HTTP helper for notification channels: JSON POST with the
 * standard timeout/error-checking behavior all channels rely on.
 *
 * @since 1.0.0
 */
final class LynxJournal_Notify_Http {

    /**
     * POST a JSON body and check for transport/HTTP-level failure.
     *
     * Does not inspect the response body for an application-level
     * success flag (e.g. Slack/Telegram's "ok" field) — callers that
     * need that check the decoded body themselves.
     *
     * @since 1.0.0
     * @param string $url Request URL.
     * @param array $body Data to JSON-encode as the request body.
     * @param array $headers Extra request headers (Content-Type: application/json is always included).
     * @param string $errorCode WP_Error code to use on failure.
     * @param int $timeout Request timeout in seconds.
     * @return array|\WP_Error The decoded wp_remote_post() response array on success, WP_Error otherwise.
     */
    public static function postJson(string $url, array $body, array $headers, string $errorCode, int $timeout = 8): array|\WP_Error {
        $response = wp_remote_post($url, [
            'timeout' => $timeout,
            'headers' => array_merge(['Content-Type' => 'application/json'], $headers),
            'body'    => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }
        if (wp_remote_retrieve_response_code($response) >= 300) {
            return new \WP_Error($errorCode, wp_remote_retrieve_body($response), ['status' => 500]);
        }
        return $response;
    }
}
