/**
 * Schedule mode sets derived from values localized by Admin/Menu.php at runtime.
 * PHP source of truth: ScheduleMode enum in src/php/ScheduleMode.php.
 */

// PHP source of truth: ScheduleMode enum in src/php/ScheduleMode.php.
// Values are localized to globalThis.linkdigestSchedule at runtime by Admin/Menu.php.
const loc = ( typeof globalThis !== 'undefined' && globalThis.linkdigestSchedule ) || {};

/** @type {Set<string>} Time-based schedule modes (daily, weekly, monthly). */
export const SCHEDULE_MODES = new Set( loc.timeModes    ?? [ 'daily', 'weekly', 'monthly' ] );

/** @type {Set<string>} Trigger-based schedule modes (count, age). */
export const TRIGGER_MODES  = new Set( loc.triggerModes ?? [ 'count', 'age' ] );

/** @type {string[]} All available schedule mode values in display order. */
export const ALL_MODES      = loc.allModes ?? [ 'daily', 'weekly', 'monthly', 'count', 'age', 'manual' ];
