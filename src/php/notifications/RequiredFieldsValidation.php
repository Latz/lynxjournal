<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shared "all required fields present when enabled" check, used by channels
 * that otherwise have nothing in common (different platforms, different
 * send() pipelines) but happen to validate the same shape: a single
 * enabled flag plus a handful of required text fields.
 *
 * @since 1.1.0
 */
trait LynxJournalNotifyRequiredFieldsValidation {

    /**
     * Check that all required fields are non-empty when this channel is enabled.
     *
     * @since 1.1.0
     * @param array $notify The sanitized notify settings.
     * @param bool $enabled Whether this channel is enabled.
     * @param array<string, array{0: string, 1: string}> $required Map of field name => [error code, message].
     * @return \WP_Error|null Error for the first missing required field, or null if valid/disabled.
     */
    private function validateRequiredFields(array $notify, bool $enabled, array $required): ?\WP_Error {
        if (!$enabled) {
            return null;
        }
        foreach ($required as $field => [$code, $message]) {
            if ($notify[$field] === '') {
                return new \WP_Error($code, $message, ['status' => 400]);
            }
        }
        return null;
    }
}
