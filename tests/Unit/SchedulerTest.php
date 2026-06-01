<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Brain\Monkey\Functions;

// ---------------------------------------------------------------------------
// executeSchedule()
// ---------------------------------------------------------------------------

describe('LinkDigest::executeSchedule()', function (): void {

    beforeEach(function (): void {
        Functions\when('__')->returnArg();
        Functions\when('set_transient')->justReturn(true);
        Functions\when('delete_transient')->justReturn(true);
        Functions\when('get_current_user_id')->justReturn(1);
        Functions\when('wp_date')->justReturn('April 29, 2026');
        Functions\when('wp_clear_scheduled_hook')->justReturn(0);
        Functions\when('wp_schedule_single_event')->justReturn(true);

        $this->plugin = Mockery::mock(LinkDigest::class)->makePartial();
        $this->plugin->shouldReceive('scheduleNextEvent')->andReturnNull();
    });

    it('returns locked result immediately when the run-lock transient is set', function (): void {
        Functions\when('get_transient')->justReturn('1');

        $result = $this->plugin->executeSchedule(false);

        expect($result['published'])->toBeFalse();
        expect($result['reason'])->toBe('locked');
        expect($result['link_count'])->toBe(0);
        expect($result['post_id'])->toBeNull();
    });

    it('daily: publishes and returns structured result when links exist', function (): void {
        Functions\when('get_transient')->justReturn(false);
        Functions\when('update_option')->justReturn(true);
        Functions\when('get_option')->alias(fn($k, $d = false) =>
            $k === 'linkdigest_schedule' ? ['mode' => 'daily', 'publishAs' => 1] : $d
        );
        $this->plugin->shouldReceive('getUnpublishedLinkIds')->andReturn([10, 20, 30]);
        $this->plugin->shouldReceive('createRoundupPost')->andReturn(
            ['success' => true, 'post_id' => 42, 'message' => 'ok']
        );

        $result = $this->plugin->executeSchedule(false);

        expect($result['published'])->toBeTrue();
        expect($result['post_id'])->toBe(42);
        expect($result['link_count'])->toBe(3);
    });

    it('daily: skips when no links exist and records condition_not_met', function (): void {
        Functions\when('get_transient')->justReturn(false);
        Functions\when('update_option')->justReturn(true);
        Functions\when('get_option')->alias(fn($k, $d = false) =>
            $k === 'linkdigest_schedule' ? ['mode' => 'daily', 'publishAs' => 1] : $d
        );
        $this->plugin->shouldReceive('getUnpublishedLinkIds')->andReturn([]);

        $result = $this->plugin->executeSchedule(false);

        expect($result['published'])->toBeFalse();
        expect($result['reason'])->toBe('condition_not_met');
    });

    it('writes linkdigest_last_run option on every execution', function (): void {
        Functions\when('get_transient')->justReturn(false);
        Functions\when('get_option')->alias(fn($k, $d = false) =>
            $k === 'linkdigest_schedule' ? ['mode' => 'daily', 'publishAs' => 1] : $d
        );
        $this->plugin->shouldReceive('getUnpublishedLinkIds')->andReturn([]);

        $lastRun = null;
        Functions\when('update_option')->alias(function ($k, $v) use (&$lastRun) {
            if ($k === 'linkdigest_last_run') {
                $lastRun = $v;
            }
            return true;
        });

        $this->plugin->executeSchedule(false);

        expect($lastRun)->toBeArray();
        expect($lastRun)->toHaveKeys(['ts', 'mode', 'link_count', 'post_id', 'status', 'reason']);
        expect($lastRun['mode'])->toBe('daily');
        expect($lastRun['status'])->toBe('skipped');
        expect($lastRun['ts'])->toBeInt();
    });

    it('count: publishes when total_count meets the threshold', function (): void {
        Functions\when('get_transient')->justReturn(false);
        Functions\when('update_option')->justReturn(true);
        Functions\when('get_option')->alias(fn($k, $d = false) =>
            $k === 'linkdigest_schedule'
                ? ['mode' => 'count', 'trigger' => ['count' => 3], 'publishAs' => 1]
                : $d
        );
        $this->plugin->shouldReceive('getUnpublishedLinkIds')->andReturn([1, 2, 3]);
        $this->plugin->shouldReceive('createRoundupPost')->andReturn(
            ['success' => true, 'post_id' => 5, 'message' => 'ok']
        );

        $result = $this->plugin->executeSchedule(false);

        expect($result['published'])->toBeTrue();
        expect($result['link_count'])->toBe(3);
    });

    it('count: skips when total_count is below the threshold', function (): void {
        Functions\when('get_transient')->justReturn(false);
        Functions\when('update_option')->justReturn(true);
        Functions\when('get_option')->alias(fn($k, $d = false) =>
            $k === 'linkdigest_schedule'
                ? ['mode' => 'count', 'trigger' => ['count' => 10], 'publishAs' => 1]
                : $d
        );
        $this->plugin->shouldReceive('getUnpublishedLinkIds')->andReturn([1, 2, 3]);

        $result = $this->plugin->executeSchedule(false);

        expect($result['published'])->toBeFalse();
        expect($result['reason'])->toBe('condition_not_met');
    });

    it('age: publishes when the oldest link exceeds the age threshold', function (): void {
        Functions\when('get_transient')->justReturn(false);
        Functions\when('update_option')->justReturn(true);
        Functions\when('get_option')->alias(fn($k, $d = false) =>
            $k === 'linkdigest_schedule'
                ? ['mode' => 'age', 'trigger' => ['days' => 7], 'publishAs' => 1]
                : $d
        );
        // isLinkOlderThan() is private — drive it via get_post() returning an old post
        $oldPost                = new WP_Post();
        $oldPost->post_date_gmt = gmdate('Y-m-d H:i:s', strtotime('-8 days'));
        Functions\when('get_post')->justReturn($oldPost);

        $this->plugin->shouldReceive('getUnpublishedLinkIds')->andReturn([99]);
        $this->plugin->shouldReceive('createRoundupPost')->andReturn(
            ['success' => true, 'post_id' => 7, 'message' => 'ok']
        );

        $result = $this->plugin->executeSchedule(false);

        expect($result['published'])->toBeTrue();
    });

    it('age: skips when the oldest link is within the age threshold', function (): void {
        Functions\when('get_transient')->justReturn(false);
        Functions\when('update_option')->justReturn(true);
        Functions\when('get_option')->alias(fn($k, $d = false) =>
            $k === 'linkdigest_schedule'
                ? ['mode' => 'age', 'trigger' => ['days' => 7], 'publishAs' => 1]
                : $d
        );
        // isLinkOlderThan() is private — drive it via get_post() returning a recent post
        $recentPost                = new WP_Post();
        $recentPost->post_date_gmt = gmdate('Y-m-d H:i:s', strtotime('-2 days'));
        Functions\when('get_post')->justReturn($recentPost);

        $this->plugin->shouldReceive('getUnpublishedLinkIds')->andReturn([99]);

        $result = $this->plugin->executeSchedule(false);

        expect($result['published'])->toBeFalse();
        expect($result['reason'])->toBe('condition_not_met');
    });

    it('slices to max_per_run and reports the batch size when has_more is true', function (): void {
        Functions\when('get_transient')->justReturn(false);
        Functions\when('update_option')->justReturn(true);
        Functions\when('get_option')->alias(fn($k, $d = false) =>
            $k === 'linkdigest_schedule' ? ['mode' => 'daily', 'publishAs' => 1] : $d
        );

        // 201 links → triggers has_more (MAX_PER_RUN = 200)
        $this->plugin->shouldReceive('getUnpublishedLinkIds')->andReturn(range(1, 201));
        $this->plugin->shouldReceive('createRoundupPost')
            ->with(range(1, 200), Mockery::any(), false, 'daily')
            ->andReturn(['success' => true, 'post_id' => 99, 'message' => 'ok']);

        // scheduleNextEvent must NOT be called; the code schedules a raw catchup instead.
        $this->plugin->shouldNotReceive('scheduleNextEvent');

        $result = $this->plugin->executeSchedule(true);

        expect($result['link_count'])->toBe(200);
        expect($result['published'])->toBeTrue();
    });
});

// ---------------------------------------------------------------------------
// getNextScheduleTimestamp()
// ---------------------------------------------------------------------------

describe('LinkDigest::getNextScheduleTimestamp()', function (): void {

    beforeEach(function (): void {
        $this->plugin = Mockery::mock(LinkDigest::class)->makePartial();
    });

    it('returns null for manual mode', function (): void {
        Functions\when('get_option')->alias(fn($k, $d = false) =>
            $k === 'linkdigest_schedule' ? ['mode' => 'manual', 'times' => ['09:00']] : $d
        );

        expect($this->plugin->getNextScheduleTimestamp())->toBeNull();
    });

    it('returns a future timestamp for daily mode', function (): void {
        Functions\when('get_option')->alias(fn($k, $d = false) =>
            $k === 'linkdigest_schedule' ? ['mode' => 'daily', 'times' => ['23:59']] : $d
        );

        $ts = $this->plugin->getNextScheduleTimestamp();

        expect($ts)->toBeInt();
        expect($ts)->toBeGreaterThan(time());
    });

    it('falls back to DEFAULT_TIME when times array is empty', function (): void {
        Functions\when('get_option')->alias(fn($k, $d = false) =>
            $k === 'linkdigest_schedule' ? ['mode' => 'daily', 'times' => []] : $d
        );

        $ts = $this->plugin->getNextScheduleTimestamp();

        // Should still return a timestamp (using DEFAULT_TIME = '09:00')
        expect($ts)->toBeInt();
    });

    it('returns null for weekly mode when no weekdays are configured', function (): void {
        Functions\when('get_option')->alias(fn($k, $d = false) =>
            $k === 'linkdigest_schedule'
                ? ['mode' => 'weekly', 'times' => ['09:00'], 'recurrence' => ['weekdays' => []]]
                : $d
        );

        // No matching weekdays → no occurrence found within the horizon → null
        expect($this->plugin->getNextScheduleTimestamp())->toBeNull();
    });

    it('returns a future timestamp for weekly mode when today is configured', function (): void {
        // Use all weekdays so today always matches
        $allDays = ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'];
        Functions\when('get_option')->alias(fn($k, $d = false) =>
            $k === 'linkdigest_schedule'
                ? ['mode' => 'weekly', 'times' => ['23:59'], 'recurrence' => ['weekdays' => $allDays, 'interval' => 1]]
                : $d
        );

        $ts = $this->plugin->getNextScheduleTimestamp();

        expect($ts)->toBeInt();
        expect($ts)->toBeGreaterThan(time());
    });

    it('returns a future timestamp for monthly mode with a day-of-month trigger', function (): void {
        // Use every day of the month so one always matches within the horizon
        $monthDays = array_map(fn($d) => ['type' => 'day', 'value' => $d, 'nth' => 1, 'weekday' => 'MO'], range(1, 31));
        Functions\when('get_option')->alias(fn($k, $d = false) =>
            $k === 'linkdigest_schedule'
                ? ['mode' => 'monthly', 'times' => ['23:59'], 'recurrence' => ['monthDays' => $monthDays, 'interval' => 1]]
                : $d
        );

        $ts = $this->plugin->getNextScheduleTimestamp();

        expect($ts)->toBeInt();
        expect($ts)->toBeGreaterThan(time());
    });
});

// ---------------------------------------------------------------------------
// matchesWeeklySchedule() — via reflection (private method)
// ---------------------------------------------------------------------------

describe('LinkDigest::matchesWeeklySchedule()', function (): void {

    beforeEach(function (): void {
        $this->plugin = Mockery::mock(LinkDigest::class)->makePartial();
        $this->method = new \ReflectionMethod(LinkDigest::class, 'matchesWeeklySchedule');
        $this->method->setAccessible(true);
    });

    it('returns true when the date weekday is in the configured weekdays', function (): void {
        $monday = new \DateTime('2026-04-27', new \DateTimeZone('UTC')); // a known Monday
        $result = $this->method->invoke($this->plugin, $monday, ['weekdays' => ['MO', 'FR']]);
        expect($result)->toBeTrue();
    });

    it('returns false when the date weekday is not in configured weekdays', function (): void {
        $monday = new \DateTime('2026-04-27', new \DateTimeZone('UTC')); // Monday
        $result = $this->method->invoke($this->plugin, $monday, ['weekdays' => ['TU', 'WE']]);
        expect($result)->toBeFalse();
    });

    it('returns false when weekdays array is empty', function (): void {
        $monday = new \DateTime('2026-04-27', new \DateTimeZone('UTC'));
        $result = $this->method->invoke($this->plugin, $monday, ['weekdays' => []]);
        expect($result)->toBeFalse();
    });

    it('handles all seven weekday codes correctly', function (): void {
        // Map a known date per weekday
        $dates = [
            'MO' => '2026-04-27',
            'TU' => '2026-04-28',
            'WE' => '2026-04-29',
            'TH' => '2026-04-30',
            'FR' => '2026-05-01',
            'SA' => '2026-05-02',
            'SU' => '2026-05-03',
        ];
        foreach ($dates as $code => $dateStr) {
            $date = new \DateTime($dateStr, new \DateTimeZone('UTC'));
            $result = $this->method->invoke($this->plugin, $date, ['weekdays' => [$code]]);
            expect($result)->toBeTrue("Expected {$code} to match {$dateStr}");
        }
    });
});

// ---------------------------------------------------------------------------
// matchesMonthlySchedule() — via reflection (private method)
// ---------------------------------------------------------------------------

describe('LinkDigest::matchesMonthlySchedule()', function (): void {

    beforeEach(function (): void {
        $this->plugin = Mockery::mock(LinkDigest::class)->makePartial();
        $this->method = new \ReflectionMethod(LinkDigest::class, 'matchesMonthlySchedule');
        $this->method->setAccessible(true);
    });

    it('type=day: returns true when the calendar day matches', function (): void {
        $date   = new \DateTime('2026-04-15', new \DateTimeZone('UTC'));
        $result = $this->method->invoke($this->plugin, $date, [
            'monthDays' => [['type' => 'day', 'value' => 15, 'nth' => 1, 'weekday' => 'MO']],
        ]);
        expect($result)->toBeTrue();
    });

    it('type=day: returns false when the calendar day does not match', function (): void {
        $date   = new \DateTime('2026-04-14', new \DateTimeZone('UTC'));
        $result = $this->method->invoke($this->plugin, $date, [
            'monthDays' => [['type' => 'day', 'value' => 15, 'nth' => 1, 'weekday' => 'MO']],
        ]);
        expect($result)->toBeFalse();
    });

    it('type=weekday: returns true for the first Monday of the month', function (): void {
        // April 2026: first Monday is the 6th
        $date   = new \DateTime('2026-04-06', new \DateTimeZone('UTC'));
        $result = $this->method->invoke($this->plugin, $date, [
            'monthDays' => [['type' => 'weekday', 'nth' => 1, 'weekday' => 'MO', 'value' => 1]],
        ]);
        expect($result)->toBeTrue();
    });

    it('type=weekday: returns false for the second Monday when first Monday is configured', function (): void {
        // April 2026: second Monday is the 13th
        $date   = new \DateTime('2026-04-13', new \DateTimeZone('UTC'));
        $result = $this->method->invoke($this->plugin, $date, [
            'monthDays' => [['type' => 'weekday', 'nth' => 1, 'weekday' => 'MO', 'value' => 1]],
        ]);
        expect($result)->toBeFalse();
    });

    it('type=weekday: returns true for the third Friday of the month', function (): void {
        // April 2026 Fridays: 3rd, 10th, 17th, 24th → 3rd Friday = 17th
        $date   = new \DateTime('2026-04-17', new \DateTimeZone('UTC'));
        $result = $this->method->invoke($this->plugin, $date, [
            'monthDays' => [['type' => 'weekday', 'nth' => 3, 'weekday' => 'FR', 'value' => 1]],
        ]);
        expect($result)->toBeTrue();
    });

    it('type=weekday: skips entries with invalid nth or weekday', function (): void {
        $date   = new \DateTime('2026-04-06', new \DateTimeZone('UTC'));
        $result = $this->method->invoke($this->plugin, $date, [
            'monthDays' => [['type' => 'weekday', 'nth' => 0, 'weekday' => 'MO', 'value' => 1]],
        ]);
        expect($result)->toBeFalse();
    });
});

// ---------------------------------------------------------------------------
// isLinkOlderThan() — via reflection (private method)
// ---------------------------------------------------------------------------

describe('LinkDigest::isLinkOlderThan()', function (): void {

    beforeEach(function (): void {
        $this->plugin = Mockery::mock(LinkDigest::class)->makePartial();
        $this->method = new \ReflectionMethod(LinkDigest::class, 'isLinkOlderThan');
        $this->method->setAccessible(true);
    });

    it('returns false when the post is not found', function (): void {
        Functions\when('get_post')->justReturn(null);
        $result = $this->method->invoke($this->plugin, 999, 7);
        expect($result)->toBeFalse();
    });

    it('returns true when the post_date_gmt is older than the threshold', function (): void {
        $post                = new WP_Post();
        $post->post_date_gmt = gmdate('Y-m-d H:i:s', strtotime('-8 days'));
        Functions\when('get_post')->justReturn($post);

        $result = $this->method->invoke($this->plugin, 1, 7);
        expect($result)->toBeTrue();
    });

    it('returns false when the post_date_gmt is newer than the threshold', function (): void {
        $post                = new WP_Post();
        $post->post_date_gmt = gmdate('Y-m-d H:i:s', strtotime('-3 days'));
        Functions\when('get_post')->justReturn($post);

        $result = $this->method->invoke($this->plugin, 1, 7);
        expect($result)->toBeFalse();
    });

    it('returns false at the exact boundary (same second as cutoff)', function (): void {
        // Cutoff is strtotime('-7 days'); post at exactly -7 days is NOT older
        $post                = new WP_Post();
        $post->post_date_gmt = gmdate('Y-m-d H:i:s', strtotime('-7 days'));
        Functions\when('get_post')->justReturn($post);

        $result = $this->method->invoke($this->plugin, 1, 7);
        // Boundary: post_date_gmt == cutoff → '<' is false
        expect($result)->toBeFalse();
    });
});

// ---------------------------------------------------------------------------
// previewSchedule()
// ---------------------------------------------------------------------------

describe('LinkDigest::previewSchedule()', function (): void {

    beforeEach(function (): void {
        Functions\when('__')->returnArg();
        $this->plugin = Mockery::mock(LinkDigest::class)->makePartial();
    });

    it('returns would_publish=true for daily mode when links exist', function (): void {
        Functions\when('get_option')->alias(fn($k, $d = false) =>
            $k === 'linkdigest_schedule' ? ['mode' => 'daily'] : $d
        );
        Functions\when('wp_get_object_terms')->justReturn([]);
        $this->plugin->shouldReceive('getUnpublishedLinkIds')->andReturn([1, 2, 3]);

        $result = $this->plugin->previewSchedule();

        expect($result['would_publish'])->toBeTrue();
        expect($result['link_count'])->toBe(3);
        expect($result['total_pending'])->toBe(3);
        expect($result['mode'])->toBe('daily');
    });

    it('returns would_publish=false for daily mode when no links exist', function (): void {
        Functions\when('get_option')->alias(fn($k, $d = false) =>
            $k === 'linkdigest_schedule' ? ['mode' => 'daily'] : $d
        );
        $this->plugin->shouldReceive('getUnpublishedLinkIds')->andReturn([]);

        $result = $this->plugin->previewSchedule();

        expect($result['would_publish'])->toBeFalse();
        expect($result['link_count'])->toBe(0);
    });

    it('returns would_publish=false for count mode when threshold is not met', function (): void {
        Functions\when('get_option')->alias(fn($k, $d = false) =>
            $k === 'linkdigest_schedule'
                ? ['mode' => 'count', 'trigger' => ['count' => 10]]
                : $d
        );
        $this->plugin->shouldReceive('getUnpublishedLinkIds')->andReturn([1, 2]);

        $result = $this->plugin->previewSchedule();

        expect($result['would_publish'])->toBeFalse();
    });

    it('returns would_publish=true for count mode when threshold is met', function (): void {
        Functions\when('get_option')->alias(fn($k, $d = false) =>
            $k === 'linkdigest_schedule'
                ? ['mode' => 'count', 'trigger' => ['count' => 2]]
                : $d
        );
        Functions\when('wp_get_object_terms')->justReturn([]);
        $this->plugin->shouldReceive('getUnpublishedLinkIds')->andReturn([1, 2]);

        $result = $this->plugin->previewSchedule();

        expect($result['would_publish'])->toBeTrue();
        expect($result['link_count'])->toBe(2);
    });

    it('returns would_publish=false for manual mode regardless of link count', function (): void {
        Functions\when('get_option')->alias(fn($k, $d = false) =>
            $k === 'linkdigest_schedule' ? ['mode' => 'manual'] : $d
        );
        $this->plugin->shouldReceive('getUnpublishedLinkIds')->andReturn([1, 2, 3]);

        $result = $this->plugin->previewSchedule();

        expect($result['would_publish'])->toBeFalse();
    });

    it('groups links by category in by_category when would_publish is true', function (): void {
        Functions\when('get_option')->alias(fn($k, $d = false) =>
            $k === 'linkdigest_schedule' ? ['mode' => 'daily'] : $d
        );
        Functions\when('wp_get_object_terms')->alias(function ($id) {
            // Link 1 → Tech, Link 2 → Tech, Link 3 → News
            return match ((int) $id) {
                1, 2 => ['Tech'],
                3    => ['News'],
                default => [],
            };
        });
        $this->plugin->shouldReceive('getUnpublishedLinkIds')->andReturn([1, 2, 3]);

        $result = $this->plugin->previewSchedule();

        expect($result['would_publish'])->toBeTrue();
        $cats = array_column($result['by_category'], 'count', 'name');
        expect($cats['Tech'])->toBe(2);
        expect($cats['News'])->toBe(1);
    });

    it('labels uncategorized links correctly when no category terms exist', function (): void {
        Functions\when('get_option')->alias(fn($k, $d = false) =>
            $k === 'linkdigest_schedule' ? ['mode' => 'daily'] : $d
        );
        Functions\when('wp_get_object_terms')->justReturn([]); // no terms
        $this->plugin->shouldReceive('getUnpublishedLinkIds')->andReturn([1]);

        $result = $this->plugin->previewSchedule();

        expect($result['by_category'][0]['name'])->toBe('Uncategorized');
        expect($result['by_category'][0]['count'])->toBe(1);
    });
});
