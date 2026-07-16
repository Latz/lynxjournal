import { useLayoutEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const createMode = (value, label, desc) => ({ value, label, desc });

const GROUPS = [
  {
    label: __('Scheduled', 'lynx-journal'),
    modes: [
      createMode('daily',   __('Daily', 'lynx-journal'),   __('Every N days', 'lynx-journal')),
      createMode('weekly',  __('Weekly', 'lynx-journal'),  __('Specific weekdays', 'lynx-journal')),
      createMode('monthly', __('Monthly', 'lynx-journal'), __('Calendar days', 'lynx-journal')),
    ],
  },
  {
    label: __('Trigger-based', 'lynx-journal'),
    modes: [
      createMode('count', __('By Count', 'lynx-journal'), __('When N links queue', 'lynx-journal')),
      createMode('age',   __('By Age', 'lynx-journal'),   __('When oldest link ages', 'lynx-journal')),
    ],
  },
  {
    label: __('Manual', 'lynx-journal'),
    modes: [
      createMode('manual', __('Manual', 'lynx-journal'), __('No auto-publish', 'lynx-journal')),
    ],
  },
];

export default function ScheduleTypePicker({ value, onChange }) {
  const cardRefs = useRef([]);
  const [cardHeight, setCardHeight] = useState(null);
  let cardIndex = 0;

  useLayoutEffect(() => {
    /**
     * Measure every mode card's natural (unforced) height across all groups
     * and equalize them to the tallest one, so translations that wrap onto
     * more lines than the English original don't leave shorter boxes uneven.
     *
     * @returns {void}
     */
    function measureAndEqualize() {
      const nodes = cardRefs.current.filter(Boolean);
      nodes.forEach(node => node.style.removeProperty('height'));
      const max = Math.max(...nodes.map(node => node.getBoundingClientRect().height));
      if (max > 0) {
        setCardHeight(max);
      }
    }

    measureAndEqualize();

    /** @listens resize Re-equalizes card heights when viewport width changes rewrap the text. */
    window.addEventListener('resize', measureAndEqualize);
    return () => window.removeEventListener('resize', measureAndEqualize);
  }, []);

  return (
    <div className="lynxjournal-mode-picker-v2" role="radiogroup">
      {GROUPS.map(group => (
        <div key={group.label} className="lynxjournal-mode-card-group">
          <div className="lynxjournal-mode-card-group-label">{group.label}</div>
          <div className="lynxjournal-mode-cards">
            {group.modes.map(mode => {
              const active = value === mode.value;
              const refIndex = cardIndex++;
              return (
                <button
                  key={mode.value}
                  ref={node => { cardRefs.current[refIndex] = node; }}
                  role="radio"
                  aria-checked={active}
                  className={`lynxjournal-mode-card${active ? ' lynxjournal-mode-card--active' : ''}`}
                  onClick={() => onChange(mode.value)}
                  type="button"
                  style={{ height: cardHeight != null ? `${cardHeight}px` : undefined }}
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
