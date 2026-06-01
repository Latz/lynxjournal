<?php

declare(strict_types=1);

if (!defined("ABSPATH")) {
    exit;
}

use Brain\Monkey\Functions;

/**
 * Tests for linkdigest_get_schedule() and linkdigest_save_schedule()
 */

beforeEach(function (): void {
    Functions\when('rest_ensure_response')->returnArg();
    $this->plugin = Mockery::mock(LinkDigest::class)->makePartial();
});

describe('LinkDigest::getSchedule()', function (): void {

    it('returns the stored option when one exists', function (): void {
        $stored = ['mode' => 'weekly', 'times' => ['08:00']];
        Functions\when('get_option')->justReturn($stored);

        $result = $this->plugin->getSchedule();

        expect($result['mode'])->toBe('weekly');
        expect($result['times'])->toBe(['08:00']);
    });

    it('returns the default schedule when no option is stored', function (): void {
        Functions\when('get_option')
            ->alias(fn($key, $default) => $default);

        $result = $this->plugin->getSchedule();

        expect($result)->toHaveKey('mode');
        expect($result['mode'])->toBe('daily');
        expect($result)->toHaveKey('times');
        expect($result)->toHaveKey('trigger');
    });
});

describe('LinkDigest::saveSchedule()', function (): void {

    beforeEach(function (): void {
        Functions\when('__')->returnArg();
    });

    it('returns a 400 WP_Error when the request body is empty', function (): void {
        $request = linkdigest_make_request(); // no JSON body

        $result = $this->plugin->saveSchedule($request);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_data');
        expect($result->get_error_data()['status'])->toBe(400);
    });

    it('returns a 400 WP_Error when mode key is missing', function (): void {
        $request = linkdigest_make_request(['recurrence' => []]);

        $result = $this->plugin->saveSchedule($request);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_mode');
    });

    it('returns a 400 WP_Error when mode is not in the whitelist', function (): void {
        $request = linkdigest_make_request(['mode' => 'invalid']);

        $result = $this->plugin->saveSchedule($request);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_mode');
        expect($result->get_error_data()['status'])->toBe(400);
    });

    it('returns a 400 WP_Error when times contains a non-HH:MM entry', function (): void {
        $request = linkdigest_make_request(['mode' => 'daily', 'times' => ['9am']]);

        $result = $this->plugin->saveSchedule($request);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_times');
        expect($result->get_error_data()['status'])->toBe(400);
    });

    it('returns a 400 WP_Error when trigger.count is zero', function (): void {
        $request = linkdigest_make_request(['mode' => 'count', 'trigger' => ['count' => 0]]);

        $result = $this->plugin->saveSchedule($request);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_trigger');
        expect($result->get_error_data()['status'])->toBe(400);
    });

    it('returns a 400 WP_Error when trigger.days is negative', function (): void {
        $request = linkdigest_make_request(['mode' => 'age', 'trigger' => ['days' => -1]]);

        $result = $this->plugin->saveSchedule($request);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('invalid_trigger');
        expect($result->get_error_data()['status'])->toBe(400);
    });

    it('saves valid schedule data and returns success', function (): void {
        $data    = ['mode' => 'daily', 'times' => ['09:00']];
        $request = linkdigest_make_request($data);

        $savedKey = null;
        $savedVal = null;
        Functions\when('update_option')->alias(
            function (string $key, mixed $val) use (&$savedKey, &$savedVal): bool {
                $savedKey = $key;
                $savedVal = $val;
                return true;
            }
        );

        Functions\when('get_current_user_id')->justReturn(1);

        $result = $this->plugin->saveSchedule($request);

        expect($result['success'])->toBeTrue();
        expect($savedKey)->toBe('linkdigest_schedule');
        expect($savedVal['mode'])->toBe($data['mode']);
        expect($savedVal['times'])->toBe($data['times']);
        expect($savedVal)->toHaveKey('publishAs');
    });
});

describe('LinkDigest::runScheduleNow()', function (): void {

    it('returns a 429 WP_Error when a run is already in progress', function (): void {
        Functions\when('__')->returnArg();
        Functions\when('get_transient')->justReturn('1');

        $result = $this->plugin->runScheduleNow();

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('run_in_progress');
        expect($result->get_error_data()['status'])->toBe(429);
    });
});
