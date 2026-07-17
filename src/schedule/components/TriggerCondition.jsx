/**
 * Renders the trigger condition UI (link count or oldest-link age) for
 * trigger-based schedule modes. Returns null for unrecognised modes.
 */

import { __experimentalNumberControl as NumberControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Renders the trigger condition UI for count- or age-based schedule modes.
 * Returns null for any other mode.
 *
 * @param {'count'|'age'} mode     - Trigger mode determining which control is shown.
 * @param {object}        value    - Current trigger config ({ count, days }).
 * @param {Function}      onChange - Called with the updated trigger config on change.
 * @returns {JSX.Element|null}
 */
export default function TriggerCondition({ mode, value, onChange }) {
  if (mode === 'count') {
    return (
      <div className="lynxjournal-rc-row">
        <span>{__('Post when there are at least', 'lynx-journal')}</span>
        <NumberControl
          label={__('Link count threshold', 'lynx-journal')}
          hideLabelFromVision
          value={String(value.count)}
          min={1}
          onChange={v => onChange({ ...value, count: Number.parseInt(v) || 1 })}
          style={{ width: '72px' }}
        />
        <span>{value.count === 1 ? __('link', 'lynx-journal') : __('links', 'lynx-journal')}</span>
      </div>
    );
  }

  if (mode === 'age') {
    return (
      <div className="lynxjournal-rc-row">
        <span>{__('Post when oldest link is older than', 'lynx-journal')}</span>
        {/* value.days can be absent on a freshly-initialised trigger object;
            default to 1 day so the control never renders an empty string. */}
        <NumberControl
          label={__('Age threshold in days', 'lynx-journal')}
          hideLabelFromVision
          value={String(value.days ?? 1)}
          min={1}
          onChange={v => onChange({ ...value, days: Number.parseInt(v) || 1 })}
          style={{ width: '72px' }}
        />
        <span>{(value.days ?? 1) === 1 ? __('day', 'lynx-journal') : __('days', 'lynx-journal')}</span>
      </div>
    );
  }

  return null;
}
