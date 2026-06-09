import { useMemo } from '@wordpress/element';
import { RRule } from 'rrule';
import { __, sprintf } from '@wordpress/i18n';
import { SCHEDULE_MODES } from '../lib/modes';

const DAYS   = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

const loc = globalThis.lynxjournalSchedule || {};
const SITE_TIMEZONE = loc.timezone || null;

/**
 * Formats a UTC date as "Day DD Mon YYYY".
 *
 * @param {Date} d - Date to format.
 * @returns {string}
 */
function formatDate(d) {
  return `${DAYS[d.getUTCDay()]} ${d.getUTCDate()} ${MONTHS[d.getUTCMonth()]} ${d.getUTCFullYear()}`;
}

/**
 * Sidebar panel showing the next 10 scheduled execution dates.
 *
 * @param {object}      config         - Resolved schedule config from App.
 * @param {string|null} config.rrule   - RFC 5545 recurrence rule string.
 * @param {string[]}    config.times   - HH:MM execution times.
 * @param {object|null} config.trigger - Trigger config (null for time-based modes).
 * @param {object}      form           - Current form state (used to read mode and times).
 * @returns {JSX.Element|null}
 */
export default function NextSchedules({ config, form }) {
  const isSchedule = SCHEDULE_MODES.has(form.mode);

  const displayTimes = form.times.length > 0 ? form.times : ['00:00'];

  const nextDates = useMemo(() => {
    if (!isSchedule || !config.rrule) return [];
    try {
      const now = new Date();
      const times = form.times.length > 0 ? form.times : ['00:00'];

      const allTimesPast = times.every(t => {
        const [h, m] = t.split(':').map(Number);
        const todayAt = new Date(now.getFullYear(), now.getMonth(), now.getDate(), h, m, 0);
        return now >= todayAt;
      });
      const dtstart = allTimesPast
        ? new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1, 0, 0, 0)
        : now;

      const parsed = RRule.fromString(config.rrule);
      const rule = new RRule({ ...parsed.options, dtstart });
      return rule.all((_, i) => i < 10);
    } catch {
      return [];
    }
  }, [config.rrule, form.times, isSchedule]);

  if (!isSchedule) return null;

  return (
    <div className="postbox lynxjournal-next-postbox">
      <div className="lynxjournal-next-heading">{__('Next 10 Schedules', 'lynx-journal')}</div>
      <div className="inside lynxjournal-next-schedules-inside">
        {nextDates.length > 0 ? (
          <ol className="lynxjournal-next-schedules">
            {nextDates.map(d => (
              <li key={d.toISOString()} className="lynxjournal-next-schedule-row">
                <span className="lynxjournal-next-date">{formatDate(d)}</span>
                <span className="lynxjournal-next-time">{displayTimes.join(', ')}</span>
              </li>
            ))}
          </ol>
        ) : (
          <p className="description">{__('No occurrences — check recurrence settings.', 'lynx-journal')}</p>
        )}
        {SITE_TIMEZONE && (
          <p className="lynxjournal-next-tz">
            {/* translators: %s: timezone name, e.g. Europe/Berlin */}
            {sprintf(__('Times in %s', 'lynx-journal'), SITE_TIMEZONE)}
          </p>
        )}
      </div>
    </div>
  );
}
