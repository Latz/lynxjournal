import { __ } from '@wordpress/i18n';

const GROUPS = [
  {
    label: __('Scheduled', 'lynx-journal'),
    modes: [
      { value: 'daily',   label: __('Daily', 'lynx-journal'),   desc: __('Every N days', 'lynx-journal') },
      { value: 'weekly',  label: __('Weekly', 'lynx-journal'),  desc: __('Specific weekdays', 'lynx-journal') },
      { value: 'monthly', label: __('Monthly', 'lynx-journal'), desc: __('Calendar days', 'lynx-journal') },
    ],
  },
  {
    label: __('Trigger-based', 'lynx-journal'),
    modes: [
      { value: 'count', label: __('By Count', 'lynx-journal'), desc: __('When N links queue', 'lynx-journal') },
      { value: 'age',   label: __('By Age', 'lynx-journal'),   desc: __('When oldest link ages', 'lynx-journal') },
    ],
  },
  {
    label: __('Manual', 'lynx-journal'),
    modes: [
      { value: 'manual', label: __('Manual', 'lynx-journal'), desc: __('No auto-publish', 'lynx-journal') },
    ],
  },
];

export default function ScheduleTypePicker({ value, onChange }) {
  return (
    <div className="lynxjournal-mode-picker-v2" role="radiogroup">
      {GROUPS.map(group => (
        <div key={group.label} className="lynxjournal-mode-card-group">
          <div className="lynxjournal-mode-card-group-label">{group.label}</div>
          <div className="lynxjournal-mode-cards">
            {group.modes.map(mode => {
              const active = value === mode.value;
              return (
                <button
                  key={mode.value}
                  role="radio"
                  aria-checked={active}
                  className={`lynxjournal-mode-card${active ? ' lynxjournal-mode-card--active' : ''}`}
                  onClick={() => onChange(mode.value)}
                  type="button"
                >
                  <div className="lynxjournal-mode-card__title">{mode.label}</div>
                  <div className="lynxjournal-mode-card__desc">{mode.desc}</div>
                </button>
              );
            })}
          </div>
        </div>
      ))}
    </div>
  );
}
