/**
 * Renders the recurrence configuration UI for daily, weekly, and monthly modes.
 */

import { __experimentalNumberControl as NumberControl, SelectControl, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const WEEKDAYS = [
  { value: 'MO', label: __('Mon', 'lynx-journal') },
  { value: 'TU', label: __('Tue', 'lynx-journal') },
  { value: 'WE', label: __('Wed', 'lynx-journal') },
  { value: 'TH', label: __('Thu', 'lynx-journal') },
  { value: 'FR', label: __('Fri', 'lynx-journal') },
  { value: 'SA', label: __('Sat', 'lynx-journal') },
  { value: 'SU', label: __('Sun', 'lynx-journal') },
];

const WEEKDAY_SHORT = {
  MO: __('Mon', 'lynx-journal'), TU: __('Tue', 'lynx-journal'), WE: __('Wed', 'lynx-journal'), TH: __('Thu', 'lynx-journal'),
  FR: __('Fri', 'lynx-journal'), SA: __('Sat', 'lynx-journal'), SU: __('Sun', 'lynx-journal'),
};
const WEEKDAY_FULL = {
  MO: __('Monday', 'lynx-journal'), TU: __('Tuesday', 'lynx-journal'), WE: __('Wednesday', 'lynx-journal'), TH: __('Thursday', 'lynx-journal'),
  FR: __('Friday', 'lynx-journal'), SA: __('Saturday', 'lynx-journal'), SU: __('Sunday', 'lynx-journal'),
};

const NTH_OPTIONS = [
  { label: __('first',  'lynx-journal'), value: '1' },
  { label: __('second', 'lynx-journal'), value: '2' },
  { label: __('third',  'lynx-journal'), value: '3' },
  { label: __('fourth', 'lynx-journal'), value: '4' },
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
 * Renders the "every N days" recurrence row for daily mode.
 *
 * @param {object}   value    - Current recurrence config ({ interval }).
 * @param {Function} onChange - Called with the updated recurrence config on change.
 * @returns {JSX.Element}
 */
function DailyRecurrence({ value, onChange }) {
  return (
    <div className="lynxjournal-rc-row">
      <span>{__('Every', 'lynx-journal')}</span>
      <NumberControl
        label={__('Day interval', 'lynx-journal')}
        hideLabelFromVision
        value={String(value.interval)}
        min={1} max={365}
        onChange={v => onChange({ ...value, interval: Number.parseInt(v) || 1 })}
        style={{ width: '72px' }}
      />
      <span>{value.interval === 1 ? __('day', 'lynx-journal') : __('days', 'lynx-journal')}</span>
    </div>
  );
}

/**
 * Renders the "every N weeks, on these weekdays" recurrence controls for weekly mode.
 *
 * @param {object}   value    - Current recurrence config ({ interval, weekdays }).
 * @param {Function} onChange - Called with the updated recurrence config on change.
 * @returns {JSX.Element}
 */
function WeeklyRecurrence({ value, onChange }) {
  return (
    <div className="lynxjournal-rc">
      <div className="lynxjournal-rc-row">
        <span>{__('Every', 'lynx-journal')}</span>
        <NumberControl
          label={__('Week interval', 'lynx-journal')}
          hideLabelFromVision
          value={String(value.interval)}
          min={1} max={52}
          onChange={v => onChange({ ...value, interval: Number.parseInt(v) || 1 })}
          style={{ width: '72px' }}
        />
        <span>{value.interval === 1 ? __('week', 'lynx-journal') : __('weeks', 'lynx-journal')}</span>
      </div>
      <div className="lynxjournal-weekdays">
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

/**
 * Renders one row of the monthly recurrence entry list: a "day of month" or
 * "nth weekday" trigger, selectable via two mutually-exclusive toggle zones.
 *
 * @param {number}   index         - Entry's position within monthDays.
 * @param {object}   entry         - The month-day entry ({ id?, type, value, nth, weekday }).
 * @param {boolean}  canRemove     - Whether a remove button should be shown.
 * @param {Function} onUpdateEntry - Called with (index, patch) to merge changes into this entry.
 * @param {Function} onRemoveEntry - Called with (index) to remove this entry.
 * @returns {JSX.Element}
 */
function MonthDayRow({ index, entry, canRemove, onUpdateEntry, onRemoveEntry }) {
  return (
    <div className="lynxjournal-month-day-row">
      <span className="lynxjournal-day-index">{String(index + 1).padStart(2, '0')}</span>

      {/* Plain <div> (not a <button>) so the NumberControl's own <input>
          remains the only interactive element — nesting a native control
          inside a <button> is invalid HTML and ambiguous for screen
          readers. Activation happens when the input receives focus
          (covers both mouse clicks and keyboard/Tab navigation). */}
      <div className={`lynxjournal-opt ${entry.type === 'day' ? 'lynxjournal-opt--on' : 'lynxjournal-opt--off'}`}>
        <NumberControl
          label={__('Day of month', 'lynx-journal')}
          hideLabelFromVision
          value={String(entry.value ?? 1)}
          min={1} max={31}
          autoFocus={index === 0 && entry.type === 'day'} // skipcq: JS-0757 - intentional: focus the first day-of-month field when it becomes the active option
          onFocus={() => entry.type !== 'day' && onUpdateEntry(index, { type: 'day' })}
          onChange={v => onUpdateEntry(index, { value: Number.parseInt(v) || 1 })}
          style={{ width: '72px' }}
        />
      </div>

      <span className="lynxjournal-opt-sep">{__('or', 'lynx-journal')}</span>

      {/* Same pattern for 'nth' mode — focusing either SelectControl activates it. */}
      <div className={`lynxjournal-opt ${entry.type === 'nth' ? 'lynxjournal-opt--on' : 'lynxjournal-opt--off'}`}>
        <SelectControl
          label={__('Week of month', 'lynx-journal')}
          hideLabelFromVision
          value={String(entry.nth ?? 1)}
          options={NTH_OPTIONS}
          onFocus={() => entry.type !== 'nth' && onUpdateEntry(index, { type: 'nth' })}
          onChange={v => onUpdateEntry(index, { nth: Number.parseInt(v) })}
          __nextHasNoMarginBottom
        />
        <SelectControl
          label={__('Weekday', 'lynx-journal')}
          hideLabelFromVision
          value={entry.weekday ?? 'MO'}
          options={WEEKDAY_OPTIONS}
          onFocus={() => entry.type !== 'nth' && onUpdateEntry(index, { type: 'nth' })}
          onChange={v => onUpdateEntry(index, { weekday: v })}
          __nextHasNoMarginBottom
        />
      </div>

      {canRemove && (
        <Button
          variant="destructive"
          size="compact"
          onClick={() => onRemoveEntry(index)}
          aria-label={__('Remove', 'lynx-journal')}
        >
          ✕
        </Button>
      )}
    </div>
  );
}

/**
 * Renders the "every N months, on these day(s)" recurrence controls for monthly mode.
 * Each entry represents one trigger day inside the month. The user can choose
 * between two modes per entry:
 *   type:'day' → a fixed calendar date (value = 1–31)
 *   type:'nth' → an ordinal weekday (nth = 1–4, weekday = 'MO'…'SU')
 *
 * @param {object}   value    - Current recurrence config ({ interval, monthDays }).
 * @param {Function} onChange - Called with the updated recurrence config on change.
 * @returns {JSX.Element}
 */
function MonthlyRecurrence({ value, onChange }) {
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
    <div className="lynxjournal-rc">
      <div className="lynxjournal-rc-row">
        <span>{__('Every', 'lynx-journal')}</span>
        <NumberControl
          label={__('Month interval', 'lynx-journal')}
          hideLabelFromVision
          value={String(value.interval)}
          min={1} max={12}
          onChange={v => onChange({ ...value, interval: Number.parseInt(v) || 1 })}
          style={{ width: '72px' }}
        />
        <span>{value.interval === 1 ? __('month, on', 'lynx-journal') : __('months, on', 'lynx-journal')}</span>
      </div>

      <div className="lynxjournal-month-days">
        {monthDays.map((entry, i) => (
          // entry.id is assigned when a row is added dynamically (Date.now());
          // fall back to index for the initial row loaded from the API.
          <MonthDayRow
            key={entry.id ?? i}
            index={i}
            entry={entry}
            canRemove={monthDays.length > 1}
            onUpdateEntry={updateEntry}
            onRemoveEntry={removeDay}
          />
        ))}

        <Button variant="secondary" size="compact" onClick={addDay}>
          + {__('Add day', 'lynx-journal')}
        </Button>
      </div>
    </div>
  );
}

/**
 * Dispatches to the recurrence controls for the given schedule frequency type.
 *
 * @param {'daily'|'weekly'|'monthly'} type     - Schedule frequency type.
 * @param {object}                     value    - Current recurrence config.
 * @param {Function}                   onChange - Called with the updated recurrence config on change.
 * @returns {JSX.Element|null}
 */
export default function RecurrenceConfig({ type, value, onChange }) {
  if (type === 'daily')   return <DailyRecurrence value={value} onChange={onChange} />;
  if (type === 'weekly')  return <WeeklyRecurrence value={value} onChange={onChange} />;
  if (type === 'monthly') return <MonthlyRecurrence value={value} onChange={onChange} />;
  return null;
}
