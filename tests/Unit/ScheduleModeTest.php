<?php

declare(strict_types=1);

if (!defined("ABSPATH")) {
    exit;
}

describe('ScheduleMode', function (): void {

    it('has exactly the six expected mode values', function (): void {
        $values = ScheduleMode::cases();

        expect($values)->toBe(['daily', 'weekly', 'monthly', 'count', 'age', 'manual']);
    });

    it('timeBased() returns only the calendar-based modes', function (): void {
        $values = ScheduleMode::timeBased();

        expect($values)->toBe(['daily', 'weekly', 'monthly']);
    });

    it('triggerBased() returns only the threshold-trigger modes', function (): void {
        $values = ScheduleMode::triggerBased();

        expect($values)->toBe(['count', 'age']);
    });

    it('Manual is neither timeBased nor triggerBased', function (): void {
        $time_based    = ScheduleMode::timeBased();
        $trigger_based = ScheduleMode::triggerBased();

        expect(in_array(ScheduleMode::MANUAL, $time_based, true))->toBeFalse();
        expect(in_array(ScheduleMode::MANUAL, $trigger_based, true))->toBeFalse();
    });
});
