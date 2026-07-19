<?php

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ScheduleMode {
    public const DAILY   = 'daily';
    public const WEEKLY  = 'weekly';
    public const MONTHLY = 'monthly';
    public const COUNT   = 'count';
    public const AGE     = 'age';
    public const MANUAL  = 'manual';

    /**
     * Returns all valid schedule mode values.
     *
     * @return string[]
     */
    public static function cases(): array {
        return [self::DAILY, self::WEEKLY, self::MONTHLY, self::COUNT, self::AGE, self::MANUAL];
    }

    /**
     * Returns the modes that use a calendar-based recurrence config.
     *
     * @return string[]
     */
    public static function timeBased(): array {
        return [self::DAILY, self::WEEKLY, self::MONTHLY];
    }

    /**
     * Returns the modes that use a threshold trigger instead of a schedule.
     *
     * @return string[]
     */
    public static function triggerBased(): array {
        return [self::COUNT, self::AGE];
    }
}
