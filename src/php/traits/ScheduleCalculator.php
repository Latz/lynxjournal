<?php

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait LinkDigest_ScheduleCalculator {

    /**
     * Calculate the next schedule timestamp based on configuration.
     *
     * Returns next UNIX timestamp in UTC based on schedule config, or null for manual mode.
     *
     * @since 1.0.0
     * @return int|null Next schedule timestamp or null if manual mode.
     */
    public function getNextScheduleTimestamp(): ?int {
        $config     = get_option('linkdigest_schedule', []);
        $mode       = $config['mode']       ?? 'daily';
        $times      = $config['times']      ?? [];
        if (empty($times)) {
            $times = [self::DEFAULT_TIME];
        }
        $recurrence = $config['recurrence'] ?? [];

        if ($mode === 'manual') {
            return null;
        }

        $tz  = wp_timezone();
        $now = new \DateTime('now', $tz);
        sort($times);

        // 367-day window handles monthly schedules where no day matches in the current
        // month (e.g., 31st in a 30-day month) and leap-year edge cases.
        for ($i = 0; $i <= self::SEARCH_HORIZON_DAYS; $i++) {
            $day = (clone $now)->modify("+{$i} days");

            if (!$this->dayMatchesSchedule($day, $mode, $recurrence)) {
                continue;
            }

            foreach ($times as $t) {
                [$h, $m] = explode(':', $t);
                $candidate = (clone $day)->setTime((int) $h, (int) $m, 0);
                if ($candidate > $now) {
                    return $candidate->getTimestamp();
                }
            }
        }

        return null;
    }

    /**
     * Check if a date matches the schedule mode and recurrence settings.
     *
     * @since 1.0.0
     * @param \DateTime $date The date to check.
     * @param string $mode The schedule mode.
     * @param array $rec The recurrence settings.
     * @return bool True if date matches schedule.
     */
    private function dayMatchesSchedule(\DateTime $date, string $mode, array $rec): bool {
        if (in_array($mode, ['daily', 'count', 'age'], true)) {
            return true;
        }
        return match ($mode) {
            'weekly'  => $this->matchesWeeklySchedule($date, $rec),
            'monthly' => $this->matchesMonthlySchedule($date, $rec),
            default   => true,
        };
    }

    /**
     * Check if a date matches the weekly schedule.
     *
     * @since 1.0.0
     * @param \DateTime $date The date to check.
     * @param array $rec The recurrence settings with weekdays.
     * @return bool True if date is on a scheduled weekday.
     */
    private function matchesWeeklySchedule(\DateTime $date, array $rec): bool {
        $map = ['MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6, 'SU' => 7];
        $dow = (int) $date->format('N');
        foreach ($rec['weekdays'] ?? [] as $wd) {
            if (($map[$wd] ?? 0) === $dow) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if a date matches the monthly schedule.
     *
     * @since 1.0.0
     * @param \DateTime $date The date to check.
     * @param array $rec The recurrence settings with month days and weekday patterns.
     * @return bool True if date matches the monthly schedule.
     */
    private function matchesMonthlySchedule(\DateTime $date, array $rec): bool {
        $dom = (int) $date->format('j');
        $map = ['MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6, 'SU' => 7];
        foreach ($rec['monthDays'] ?? [] as $md) {
            $type = $md['type'] ?? '';
            if ($type === 'day' && (int) ($md['value'] ?? 0) === $dom) {
                return true;
            }
            if ($type === 'weekday') {
                $target_dow = $map[$md['weekday'] ?? ''] ?? 0;
                $nth        = (int) ($md['nth'] ?? 0);
                if ($target_dow === 0 || $nth === 0) {
                    continue;
                }
                $first      = (clone $date)->modify('first day of this month');
                $offset     = ($target_dow - (int) $first->format('N') + 7) % 7;
                $target_dom = 1 + $offset + ($nth - 1) * 7;
                if ($dom === $target_dom) {
                    return true;
                }
            }
        }
        return false;
    }
}
