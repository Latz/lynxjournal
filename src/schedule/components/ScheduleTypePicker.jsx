import { __ } from '@wordpress/i18n';

const createMode = (value, label, desc) => ({ value, label: __(label, 'lynxjournal'), desc: __(desc, 'lynxjournal') });

const GROUPS = [
  {
    label: __('Scheduled', 'lynxjournal'),
    modes: [
      createMode('daily',   'Daily',   'Every N days'),
      createMode('weekly',  'Weekly',  'Specific weekdays'),
      createMode('monthly', 'Monthly', 'Calendar days'),
    ],
  },
  {
    label: __('Trigger-based', 'lynxjournal'),
    modes: [
      createMode('count', 'By Count', 'When N links queue'),
      createMode('age',   'By Age',   'When oldest link ages'),
    ],
  },
  {
    label: __('Manual', 'lynxjournal'),
    modes: [
      createMode('manual', 'Manual', 'No auto-publish'),
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
