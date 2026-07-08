// PHP source of truth: LynxJournal_ScheduleMode enum in src/php/schedule-mode.php.
// Values are localized to globalThis.lynxjournalSchedule at runtime by Admin/Menu.php.
const loc = ( typeof globalThis !== 'undefined' && globalThis.lynxjournalSchedule ) || {};

export const SCHEDULE_MODES = new Set( loc.timeModes    ?? [ 'daily', 'weekly', 'monthly' ] );
export const TRIGGER_MODES  = new Set( loc.triggerModes ?? [ 'count', 'age' ] );
export const ALL_MODES      = loc.allModes ?? [ 'daily', 'weekly', 'monthly', 'count', 'age', 'manual' ];
