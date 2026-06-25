<?php

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait LynxJournal_ScheduleValidator {

    /**
     * Validate and sanitize schedule configuration data.
     *
     * @since 1.0.0
     * @param array $data The schedule configuration to validate.
     * @return array|\WP_Error Validated configuration or WP_Error.
     */
    public function validateScheduleConfig(array $data): array|\WP_Error {
        $error = $this->validateModeAndKeys($data)
              ?? $this->validateTimes($data)
              ?? $this->validateRecurrence($data)
              ?? $this->validateTrigger($data)
              ?? $this->validatePublishOptions($data)
              ?? $this->validateNotify($data);
        return $error ?? $data;
    }

    /**
     * Validate that mode is present and no unknown keys exist.
     *
     * @since 1.0.0
     * @param array $data The schedule configuration data.
     * @return \WP_Error|null WP_Error on failure, null on success.
     */
    private function validateModeAndKeys(array $data): ?\WP_Error {
        $allowed = ['mode', 'times', 'recurrence', 'trigger', 'publishAs', 'notify', 'post_status'];
        $unknown = array_diff(array_keys($data), $allowed);
        if (!empty($unknown)) {
            return new \WP_Error(
                'unknown_keys',
                /* translators: %s: comma-separated list of unrecognized field names */
                sprintf(__('Unknown schedule fields: %s', 'lynx-journal'), implode(', ', $unknown)),
                ['status' => 400]
            );
        }
        $valid_modes = array_column(ScheduleMode::cases(), 'value');
        if (!isset($data['mode']) || !in_array($data['mode'], $valid_modes, true)) {
            return new \WP_Error('invalid_mode', __('Invalid schedule mode', 'lynx-journal'), ['status' => 400]);
        }
        return null;
    }

    /**
     * Validate and normalise the times array (HH:MM format, deduped, sorted).
     *
     * @since 1.0.0
     * @param array $data The schedule configuration data (passed by reference).
     * @return \WP_Error|null WP_Error on failure, null on success.
     */
    private function validateTimes(array &$data): ?\WP_Error {
        if (!isset($data['times']) || !is_array($data['times'])) {
            return isset($data['times']) ? new \WP_Error('invalid_times', __('times must be an array', 'lynx-journal'), ['status' => 400]) : null;
        }
        foreach ($data['times'] as $t) {
            if (!is_string($t) || !preg_match('/^\d{2}:\d{2}$/', $t)) {
                return new \WP_Error('invalid_times', __('times entries must be HH:MM strings', 'lynx-journal'), ['status' => 400]);
            }
        }
        $data['times'] = array_values(array_unique($data['times']));
        sort($data['times']);
        return null;
    }

    /**
     * Validate that the recurrence field, if present, is an array/object.
     *
     * @since 1.0.0
     * @param array $data The schedule configuration data.
     * @return \WP_Error|null WP_Error on failure, null on success.
     */
    private function validateRecurrence(array $data): ?\WP_Error {
        if (isset($data['recurrence']) && !is_array($data['recurrence'])) {
            return new \WP_Error('invalid_recurrence', __('recurrence must be an object', 'lynx-journal'), ['status' => 400]);
        }
        return null;
    }

    /**
     * Validate the trigger field shape and delegate to value validation.
     *
     * @since 1.0.0
     * @param array $data The schedule configuration data (passed by reference).
     * @return \WP_Error|null WP_Error on failure, null on success.
     */
    private function validateTrigger(array &$data): ?\WP_Error {
        if (!isset($data['trigger']) || !is_array($data['trigger'])) {
            return isset($data['trigger']) ? new \WP_Error('invalid_trigger', __('trigger must be an object', 'lynx-journal'), ['status' => 400]) : null;
        }
        return $this->validateTriggerValues($data['trigger']);
    }

    /**
     * Validate trigger count and days values are positive integers.
     *
     * @since 1.0.0
     * @param array $trigger The trigger sub-array (passed by reference for coercion).
     * @return \WP_Error|null WP_Error on failure, null on success.
     */
    private function validateTriggerValues(array &$trigger): ?\WP_Error {
        if (isset($trigger['count'])) {
            $trigger['count'] = (int) $trigger['count'];
            if ($trigger['count'] <= 0) {
                return new \WP_Error('invalid_trigger', __('trigger.count must be a positive integer', 'lynx-journal'), ['status' => 400]);
            }
        }
        if (isset($trigger['days'])) {
            $trigger['days'] = (int) $trigger['days'];
            if ($trigger['days'] <= 0) {
                return new \WP_Error('invalid_trigger', __('trigger.days must be a positive integer', 'lynx-journal'), ['status' => 400]);
            }
        }
        return null;
    }

    /**
     * Validate publishAs (user ID) and post_status fields.
     *
     * @since 1.0.0
     * @param array $data The schedule configuration data (passed by reference).
     * @return \WP_Error|null WP_Error on failure, null on success.
     */
    private function validatePublishOptions(array &$data): ?\WP_Error {
        if (isset($data['publishAs'])) {
            $data['publishAs'] = (int) $data['publishAs'];
            if ($data['publishAs'] < 0) {
                return new \WP_Error('invalid_publish_as', __('publishAs must be a non-negative integer', 'lynx-journal'), ['status' => 400]);
            }
        }
        if (isset($data['post_status']) && !in_array($data['post_status'], ['publish', 'draft'], true)) {
            return new \WP_Error('invalid_post_status', __('post_status must be "publish" or "draft"', 'lynx-journal'), ['status' => 400]);
        }
        return null;
    }

    protected function validateNotify(array &$data): ?\WP_Error {
        if (!isset($data['notify']) || !is_array($data['notify'])) {
            return isset($data['notify']) ? new \WP_Error('invalid_notify', __('notify must be an object', 'lynx-journal'), ['status' => 400]) : null;
        }
        $data['notify']['enabled'] = (bool) ($data['notify']['enabled'] ?? false);
        if (!empty($data['notify']['email'])) {
            $data['notify']['email'] = sanitize_email($data['notify']['email']);
            if (!is_email($data['notify']['email'])) {
                return new \WP_Error('invalid_notify_email', __('notify.email is not a valid email address', 'lynx-journal'), ['status' => 400]);
            }
        }
        foreach (['discord_webhook', 'slack_webhook'] as $key) {
            if (!empty($data['notify'][$key])) {
                $url = esc_url_raw($data['notify'][$key]);
                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                    return new \WP_Error(
                        'invalid_notify_webhook',
                        /* translators: %s: webhook field name (discord_webhook or slack_webhook) */
                        sprintf(__('notify.%s is not a valid URL', 'lynx-journal'), $key),
                        ['status' => 400]
                    );
                }
                $data['notify'][$key] = $url;
            }
        }
        if (!empty($data['notify']['telegram_bot_token'])) {
            $data['notify']['telegram_bot_token'] = sanitize_text_field($data['notify']['telegram_bot_token']);
        }
        if (!empty($data['notify']['telegram_chat_id'])) {
            $data['notify']['telegram_chat_id'] = sanitize_text_field($data['notify']['telegram_chat_id']);
        }
        return null;
    }
}
