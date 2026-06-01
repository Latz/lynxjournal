/**
 * Manages the list of HH:MM execution times for a schedule.
 */

import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Manages a list of HH:MM execution times for a schedule.
 *
 * @param {string[]} times    - Current list of time strings.
 * @param {Function} onChange - Called with the updated times array on any change.
 * @returns {JSX.Element}
 */
export default function TimePicker({ times, onChange }) {
  /** Appends a default '09:00' entry to the times list. */
  function addTime() {
    onChange([...times, '09:00']);
  }

  /**
   * Replaces the time at position i with the given value.
   *
   * @param {number} i - Index of the time to update.
   * @param {string} v - New HH:MM value.
   */
  function updateTime(i, v) {
    onChange(times.map((t, idx) => idx === i ? v : t));
  }

  /**
   * Removes the time at position i. No-op when only one time remains.
   *
   * @param {number} i - Index of the time to remove.
   */
  function removeTime(i) {
    onChange(times.filter((_, idx) => idx !== i));
  }

  return (
    <div className="linkdigest-timepicker">
      {times.map((t, i) => (
        <div key={`time-${t}`} className="linkdigest-time-row">
          <input
            type="time"
            className="linkdigest-time-input"
            value={t}
            onChange={e => updateTime(i, e.target.value)}
          />
          {/* At least one time must remain; hide the remove button when only one entry exists. */}
          {times.length > 1 && (
            <Button
              variant="destructive"
              size="compact"
              onClick={() => removeTime(i)}
              aria-label={__('Remove time', 'linkdigest')}
            >
              ✕
            </Button>
          )}
        </div>
      ))}
      <Button variant="secondary" size="compact" onClick={addTime}>
        + {__('Add time', 'linkdigest')}
      </Button>
    </div>
  );
}
