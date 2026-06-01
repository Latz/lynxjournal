<?php

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

enum ScheduleMode: string {
    case Daily   = 'daily';
    case Weekly  = 'weekly';
    case Monthly = 'monthly';
    case Count   = 'count';
    case Age     = 'age';
    case Manual  = 'manual';

    /** Returns the modes that use a calendar-based recurrence config. */
    public static function timeBased(): array {
        return [self::Daily, self::Weekly, self::Monthly];
    }

    /** Returns the modes that use a threshold trigger instead of a schedule. */
    public static function triggerBased(): array {
        return [self::Count, self::Age];
    }
}
