<?php

declare(strict_types=1);

if (!defined("ABSPATH")) {
    exit;
}

describe('ScheduleMode', function (): void {

    it('has exactly the six expected mode values', function (): void {
        $values = array_column(ScheduleMode::cases(), 'value');

        expect($values)->toBe(['daily', 'weekly', 'monthly', 'count', 'age', 'manual']);
    });

    it('timeBased() returns only the calendar-based modes', function (): void {
        $values = array_column(ScheduleMode::timeBased(), 'value');

        expect($values)->toBe(['daily', 'weekly', 'monthly']);
    });

    it('triggerBased() returns only the threshold-trigger modes', function (): void {
        $values = array_column(ScheduleMode::triggerBased(), 'value');

        expect($values)->toBe(['count', 'age']);
    });

    it('Manual is neither timeBased nor triggerBased', function (): void {
        $time_based    = ScheduleMode::timeBased();
        $trigger_based = ScheduleMode::triggerBased();

        expect(in_array(ScheduleMode::Manual, $time_based, true))->toBeFalse();
        expect(in_array(ScheduleMode::Manual, $trigger_based, true))->toBeFalse();
    });
});
