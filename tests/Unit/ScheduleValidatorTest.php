<?php

declare(strict_types=1);

if (!defined("ABSPATH")) {
    exit;
}

use Brain\Monkey\Functions;

beforeEach(function (): void {
    Functions\when('__')->returnArg();
    $this->plugin = Mockery::mock(LinkDigest::class)->makePartial();
});

describe('LinkDigest::validateScheduleConfig()', function (): void {

    it('returns 400 unknown_keys when an unrecognized top-level key is present', function (): void {
        $result = $this->plugin->validateScheduleConfig(['mode' => 'daily', 'foo' => 'bar']);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('unknown_keys');
        expect($result->get_error_data()['status'])->toBe(400);
    });

    it('returns 400 invalid_mode when mode key is missing', function (): void {
        $result = $this->plugin->validateScheduleConfig(['times' => ['09:00']]);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_mode');
        expect($result->get_error_data()['status'])->toBe(400);
    });

    it('returns 400 invalid_mode when mode is not in the whitelist', function (): void {
        $result = $this->plugin->validateScheduleConfig(['mode' => 'invalid']);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_mode');
    });

    it('returns 400 invalid_times when times is not an array', function (): void {
        $result = $this->plugin->validateScheduleConfig(['mode' => 'daily', 'times' => '09:00']);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_times');
        expect($result->get_error_data()['status'])->toBe(400);
    });

    it('returns 400 invalid_times when a times entry is not an HH:MM string', function (): void {
        $result = $this->plugin->validateScheduleConfig(['mode' => 'daily', 'times' => ['9am']]);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_times');
    });

    it('returns 400 invalid_recurrence when recurrence is not an array', function (): void {
        $result = $this->plugin->validateScheduleConfig(['mode' => 'weekly', 'recurrence' => 'bad']);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_recurrence');
        expect($result->get_error_data()['status'])->toBe(400);
    });

    it('returns 400 invalid_trigger when trigger is not an array', function (): void {
        $result = $this->plugin->validateScheduleConfig(['mode' => 'count', 'trigger' => 'bad']);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_trigger');
        expect($result->get_error_data()['status'])->toBe(400);
    });

    it('returns 400 invalid_trigger when trigger.count is zero', function (): void {
        $result = $this->plugin->validateScheduleConfig(['mode' => 'count', 'trigger' => ['count' => 0]]);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_trigger');
    });

    it('returns 400 invalid_trigger when trigger.days is negative', function (): void {
        $result = $this->plugin->validateScheduleConfig(['mode' => 'age', 'trigger' => ['days' => -3]]);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_trigger');
    });

    it('returns the sanitized payload for valid input', function (): void {
        $data   = ['mode' => 'daily', 'times' => ['09:00'], 'trigger' => ['count' => '5']];
        $result = $this->plugin->validateScheduleConfig($data);

        expect($result)->toBeArray();
        expect($result['mode'])->toBe('daily');
    });

    it('normalizes times by deduplicating and sorting', function (): void {
        $data   = ['mode' => 'daily', 'times' => ['09:00', '08:00', '09:00']];
        $result = $this->plugin->validateScheduleConfig($data);

        expect($result['times'])->toBe(['08:00', '09:00']);
    });

    it('coerces trigger.count and trigger.days to integers', function (): void {
        $data   = ['mode' => 'count', 'trigger' => ['count' => '10', 'days' => '7']];
        $result = $this->plugin->validateScheduleConfig($data);

        expect($result['trigger']['count'])->toBe(10);
        expect($result['trigger']['days'])->toBe(7);
    });

    it('accepts publishAs as a known key', function (): void {
        $data   = ['mode' => 'daily', 'publishAs' => 5];
        $result = $this->plugin->validateScheduleConfig($data);

        expect($result)->toBeArray();
        expect($result['publishAs'])->toBe(5);
    });

    it('coerces publishAs to integer', function (): void {
        $data   = ['mode' => 'daily', 'publishAs' => '3'];
        $result = $this->plugin->validateScheduleConfig($data);

        expect($result['publishAs'])->toBe(3);
    });

    it('returns 400 invalid_publish_as when publishAs is negative', function (): void {
        $result = $this->plugin->validateScheduleConfig(['mode' => 'daily', 'publishAs' => -1]);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_publish_as');
        expect($result->get_error_data()['status'])->toBe(400);
    });

    it('accepts post_status "publish" as a valid value', function (): void {
        $result = $this->plugin->validateScheduleConfig(['mode' => 'daily', 'post_status' => 'publish']);

        expect($result)->toBeArray();
        expect($result['post_status'])->toBe('publish');
    });

    it('accepts post_status "draft" as a valid value', function (): void {
        $result = $this->plugin->validateScheduleConfig(['mode' => 'daily', 'post_status' => 'draft']);

        expect($result)->toBeArray();
        expect($result['post_status'])->toBe('draft');
    });

    it('returns 400 invalid_post_status for an unrecognized value', function (): void {
        $result = $this->plugin->validateScheduleConfig(['mode' => 'daily', 'post_status' => 'pending']);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_post_status');
        expect($result->get_error_data()['status'])->toBe(400);
    });

    it('accepts notify as a known top-level key', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['enabled' => true, 'email' => ''],
        ]);

        expect($result)->toBeArray();
    });

    it('returns 400 invalid_notify when notify is not an array', function (): void {
        $result = $this->plugin->validateScheduleConfig(['mode' => 'daily', 'notify' => 'yes']);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_notify');
        expect($result->get_error_data()['status'])->toBe(400);
    });

    it('coerces notify.enabled to boolean', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['enabled' => 1],
        ]);

        expect($result)->toBeArray();
        expect($result['notify']['enabled'])->toBeBool();
        expect($result['notify']['enabled'])->toBeTrue();
    });

    it('returns 400 invalid_notify_email when notify.email is an invalid address', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['enabled' => true, 'email' => 'not-an-email'],
        ]);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_notify_email');
        expect($result->get_error_data()['status'])->toBe(400);
    });

    it('accepts a valid email address in notify.email', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['enabled' => true, 'email' => 'admin@example.com'],
        ]);

        expect($result)->toBeArray();
        expect($result['notify']['email'])->toBe('admin@example.com');
    });

    it('allows notify.email to be empty (falls back to admin email at send time)', function (): void {
        $result = $this->plugin->validateScheduleConfig([
            'mode'   => 'daily',
            'notify' => ['enabled' => true, 'email' => ''],
        ]);

        expect($result)->toBeArray();
    });
});
