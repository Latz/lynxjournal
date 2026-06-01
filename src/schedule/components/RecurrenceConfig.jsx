/**
 * Renders the recurrence configuration UI for daily, weekly, and monthly modes.
 */

import { __experimentalNumberControl as NumberControl, SelectControl, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const WEEKDAYS = [
  { value: 'MO', label: 'Mon' },
  { value: 'TU', label: 'Tue' },
  { value: 'WE', label: 'Wed' },
  { value: 'TH', label: 'Thu' },
  { value: 'FR', label: 'Fri' },
  { value: 'SA', label: 'Sat' },
  { value: 'SU', label: 'Sun' },
];

const WEEKDAY_SHORT = { MO: 'Mon', TU: 'Tue', WE: 'Wed', TH: 'Thu', FR: 'Fri', SA: 'Sat', SU: 'Sun' };
const WEEKDAY_FULL  = { MO: 'Monday', TU: 'Tuesday', WE: 'Wednesday', TH: 'Thursday', FR: 'Friday', SA: 'Saturday', SU: 'Sunday' };

const NTH_OPTIONS = [
  { label: __('first',  'linkdigest'), value: '1' },
  { label: __('second', 'linkdigest'), value: '2' },
  { label: __('third',  'linkdigest'), value: '3' },
  { label: __('fourth', 'linkdigest'), value: '4' },
];

const WEEKDAY_OPTIONS = WEEKDAYS.map(d => ({ label: WEEKDAY_SHORT[d.value], value: d.value }));

/**
 * Toggles a weekday code in/out of the given array.
 *
 * @param {string[]} weekdays - Current selected weekday codes (e.g. ['MO', 'FR']).
 * @param {string}   day      - Weekday code to toggle.
 * @returns {string[]}
 */
function toggleDay(weekdays, day) {
  return weekdays.includes(day) ? weekdays.filter(d => d !== day) : [...weekdays, day];
}

/**
 * Renders recurrence configuration controls for daily, weekly, and monthly modes.
 *
 * @param {'daily'|'weekly'|'monthly'} type     - Schedule frequency type.
 * @param {object}                     value    - Current recurrence config.
 * @param {Function}                   onChange - Called with the updated recurrence config on change.
 * @returns {JSX.Element|null}
 */
export default function RecurrenceConfig({ type, value, onChange }) {
  if (type === 'daily') {
    return (
      <div className="linkdigest-rc-row">
        <span>{__('Every', 'linkdigest')}</span>
        <NumberControl
          value={String(value.interval)}
          min={1} max={365}
          onChange={v => onChange({ ...value, interval: Number.parseInt(v) || 1 })}
          style={{ width: '72px' }}
        />
        <span>{value.interval === 1 ? __('day', 'linkdigest') : __('days', 'linkdigest')}</span>
      </div>
    );
  }

  if (type === 'weekly') {
    return (
      <div className="linkdigest-rc">
        <div className="linkdigest-rc-row">
          <span>{__('Every', 'linkdigest')}</span>
          <NumberControl
            value={String(value.interval)}
            min={1} max={52}
            onChange={v => onChange({ ...value, interval: Number.parseInt(v) || 1 })}
            style={{ width: '72px' }}
          />
          <span>{value.interval === 1 ? __('week', 'linkdigest') : __('weeks', 'linkdigest')}</span>
        </div>
        <div className="linkdigest-weekdays">
          {WEEKDAYS.map(d => (
            <Button
              key={d.value}
              variant={(value.weekdays || []).includes(d.value) ? 'primary' : 'secondary'}
              size="compact"
              title={WEEKDAY_FULL[d.value]}
              onClick={() => onChange({ ...value, weekdays: toggleDay(value.weekdays || [], d.value) })}
            >
              {d.label}
            </Button>
          ))}
        </div>
      </div>
    );
  }

  if (type === 'monthly') {
    // Each entry represents one trigger day inside the month. The user can
    // choose between two modes per entry:
    //   type:'day'  → a fixed calendar date (value = 1–31)
    //   type:'nth'  → an ordinal weekday (nth = 1–4, weekday = 'MO'…'SU')
    const monthDays = value.monthDays ?? [{ type: 'day', value: 1, nth: 1, weekday: 'MO' }];

    /**
     * Merges patch into the month-day entry at position i.
     *
     * @param {number} i     - Entry index.
     * @param {object} patch - Partial entry fields to apply.
     */
    function updateEntry(i, patch) {
      onChange({ ...value, monthDays: monthDays.map((e, idx) => idx === i ? { ...e, ...patch } : e) });
    }

    /** Appends a new month-day entry cloned from the last entry, incrementing its value. */
    function addDay() {
      const last = monthDays.at(-1) ?? { type: 'day', value: 1, nth: 1, weekday: 'MO' };
      onChange({ ...value, monthDays: [...monthDays, { id: Date.now(), ...last, value: Math.min(31, last.value + 1) }] });
    }

    /**
     * Removes the month-day entry at position i.
     *
     * @param {number} i - Entry index.
     */
    function removeDay(i) {
      onChange({ ...value, monthDays: monthDays.filter((_, idx) => idx !== i) });
    }

    return (
      <div className="linkdigest-rc">
        <div className="linkdigest-rc-row">
          <span>{__('Every', 'linkdigest')}</span>
          <NumberControl
            value={String(value.interval)}
            min={1} max={12}
            onChange={v => onChange({ ...value, interval: Number.parseInt(v) || 1 })}
            style={{ width: '72px' }}
          />
          <span>{value.interval === 1 ? __('month, on', 'linkdigest') : __('months, on', 'linkdigest')}</span>
        </div>

        <div className="linkdigest-month-days">
          {monthDays.map((entry, i) => (
            // entry.id is assigned when a row is added dynamically (Date.now());
            // fall back to index for the initial row loaded from the API.
            <div key={entry.id ?? i} className="linkdigest-month-day-row">
              <span className="linkdigest-day-index">{String(i + 1).padStart(2, '0')}</span>

              {/* Outer <button> acts as a clickable selection zone that also
                  activates the 'day' mode. Clicking when already active is a
                  no-op so the inner NumberControl stays interactive. */}
              <button
                className={`linkdigest-opt ${entry.type === 'day' ? 'linkdigest-opt--on' : 'linkdigest-opt--off'}`}
                onClick={() => entry.type !== 'day' && updateEntry(i, { type: 'day' })}
                aria-pressed={entry.type === 'day'}
              >
                <NumberControl
                  value={String(entry.value ?? 1)}
                  min={1} max={31}
                  autoFocus={i === 0 && entry.type === 'day'}
                  onChange={v => updateEntry(i, { value: Number.parseInt(v) || 1 })}
                  style={{ width: '72px' }}
                />
              </button>

              <span className="linkdigest-opt-sep">{__('or', 'linkdigest')}</span>

              {/* Same pattern for 'nth' mode — clicking activates it; the inner
                  SelectControls remain interactive once the mode is active. */}
              <button
                className={`linkdigest-opt ${entry.type === 'nth' ? 'linkdigest-opt--on' : 'linkdigest-opt--off'}`}
                onClick={() => entry.type !== 'nth' && updateEntry(i, { type: 'nth' })}
                aria-pressed={entry.type === 'nth'}
              >
                <SelectControl
                  value={String(entry.nth ?? 1)}
                  options={NTH_OPTIONS}
                  onChange={v => updateEntry(i, { nth: Number.parseInt(v) })}
                  __nextHasNoMarginBottom
                />
                <SelectControl
                  value={entry.weekday ?? 'MO'}
                  options={WEEKDAY_OPTIONS}
                  onChange={v => updateEntry(i, { weekday: v })}
                  __nextHasNoMarginBottom
                />
              </button>

              {monthDays.length > 1 && (
                <Button
                  variant="destructive"
                  size="compact"
                  onClick={() => removeDay(i)}
                  aria-label={__('Remove', 'linkdigest')}
                >
                  ✕
                </Button>
              )}
            </div>
          ))}

          <Button variant="secondary" size="compact" onClick={addDay}>
            + {__('Add day', 'linkdigest')}
          </Button>
        </div>
      </div>
    );
  }

  return null;
}
