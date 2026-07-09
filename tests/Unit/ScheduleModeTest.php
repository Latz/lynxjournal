<?php

declare(strict_types=1);

if (!defined("ABSPATH")) {
    exit;
}

describe('LynxJournal_ScheduleMode', function (): void {

    it('has exactly the six expected mode values', function (): void {
        $values = array_column(LynxJournal_ScheduleMode::cases(), 'value');

        expect($values)->toBe(['daily', 'weekly', 'monthly', 'count', 'age', 'manual']);
    });

    it('time_based() returns only the calendar-based modes', function (): void {
        $values = array_column(LynxJournal_ScheduleMode::time_based(), 'value');

        expect($values)->toBe(['daily', 'weekly', 'monthly']);
    });

    it('trigger_based() returns only the threshold-trigger modes', function (): void {
        $values = array_column(LynxJournal_ScheduleMode::trigger_based(), 'value');

        expect($values)->toBe(['count', 'age']);
    });

    it('Manual is neither time_based nor trigger_based', function (): void {
        $time_based    = LynxJournal_ScheduleMode::time_based();
        $trigger_based = LynxJournal_ScheduleMode::trigger_based();

        expect(in_array(LynxJournal_ScheduleMode::Manual, $time_based, true))->toBeFalse();
        expect(in_array(LynxJournal_ScheduleMode::Manual, $trigger_based, true))->toBeFalse();
    });
});
