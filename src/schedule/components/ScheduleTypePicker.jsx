import { __ } from '@wordpress/i18n';

const createMode = (value, label, desc) => ({ value, label: __(label, 'linkdigest'), desc: __(desc, 'linkdigest') });

const GROUPS = [
  {
    label: __('Scheduled', 'linkdigest'),
    modes: [
      createMode('daily',   'Daily',   'Every N days'),
      createMode('weekly',  'Weekly',  'Specific weekdays'),
      createMode('monthly', 'Monthly', 'Calendar days'),
    ],
  },
  {
    label: __('Trigger-based', 'linkdigest'),
    modes: [
      createMode('count', 'By Count', 'When N links queue'),
      createMode('age',   'By Age',   'When oldest link ages'),
    ],
  },
  {
    label: __('Manual', 'linkdigest'),
    modes: [
      createMode('manual', 'Manual', 'No auto-publish'),
    ],
  },
];

export default function ScheduleTypePicker({ value, onChange }) {
  return (
    <div className="linkdigest-mode-picker-v2" role="radiogroup">
      {GROUPS.map(group => (
        <div key={group.label} className="linkdigest-mode-card-group">
          <div className="linkdigest-mode-card-group-label">{group.label}</div>
          <div className="linkdigest-mode-cards">
            {group.modes.map(mode => {
              const active = value === mode.value;
              return (
                <button
                  key={mode.value}
                  role="radio"
                  aria-checked={active}
                  className={`linkdigest-mode-card${active ? ' linkdigest-mode-card--active' : ''}`}
                  onClick={() => onChange(mode.value)}
                  type="button"
                >
                  <div className="linkdigest-mode-card__title">{mode.label}</div>
                  <div className="linkdigest-mode-card__desc">{mode.desc}</div>
                </button>
              );
            })}
          </div>
        </div>
      ))}
    </div>
  );
}
