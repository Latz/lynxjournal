import { useState, useId } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * A single collapsible disclosure item used to group a notification
 * channel's fields (Email/Discord/Slack) under one header, so only the
 * channel being configured needs to take up space at once.
 *
 * @param {Object}  props
 * @param {string}  props.title       Header label for the channel.
 * @param {boolean} props.enabled     Whether the channel is currently on — drives the header badge.
 * @param {boolean} props.defaultOpen Initial open/closed state, set once at mount.
 * @param {import('react').ReactNode} props.children Field controls shown when expanded.
 * @returns {JSX.Element}
 */
export default function AccordionItem({ title, enabled, defaultOpen, children }) {
  const [isOpen, setIsOpen] = useState(defaultOpen);
  const panelId = useId();

  return (
    <div className="lynxjournal-accordion-item">
      <button
        type="button"
        className="lynxjournal-accordion-header"
        aria-expanded={isOpen}
        aria-controls={panelId}
        onClick={() => setIsOpen(open => !open)}
      >
        <span className="lynxjournal-accordion-title">{title}</span>
        <span className={`lynxjournal-accordion-badge ${enabled ? 'is-on' : 'is-off'}`}>
          {enabled ? __('On', 'lynx-journal') : __('Off', 'lynx-journal')}
        </span>
        <span className="lynxjournal-accordion-chevron" aria-hidden="true" />
      </button>
      <div id={panelId} className="lynxjournal-accordion-body" hidden={!isOpen}>
        {children}
      </div>
    </div>
  );
}
