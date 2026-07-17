/**
 * Lightweight stand-in for @wordpress/components in the Vitest environment.
 * @wordpress/components is externalized in production (provided by WP core
 * as wp.components) and isn't installed as a dependency, so it can't be
 * resolved directly by Vite — this mock is aliased in vitest.config.js.
 *
 * Each export renders plain, accessible HTML that preserves the prop
 * contracts the real components expose (value/onChange, label, etc.) so
 * component tests can interact with them via @testing-library/react.
 */

import { useState } from 'react';

export function Button({ children, onClick, disabled, isBusy, className, variant, size, 'aria-label': ariaLabel, title, type = 'button', ...rest }) {
    return (
        <button
            type={type}
            onClick={onClick}
            disabled={disabled}
            className={className}
            aria-label={ariaLabel}
            aria-busy={isBusy || undefined}
            title={title}
            data-variant={variant}
            data-size={size}
            {...rest}
        >
            {children}
        </button>
    );
}

export function Notice({ status, children, isDismissible, onRemove, className }) {
    return (
        <div role="alert" className={className} data-status={status}>
            <div>{children}</div>
            {isDismissible && (
                <button type="button" aria-label="Dismiss this notice" onClick={onRemove}>
                    &times;
                </button>
            )}
        </div>
    );
}

export function CheckboxControl({ label, checked, onChange }) {
    return (
        <label>
            <input type="checkbox" checked={checked} onChange={e => onChange(e.target.checked)} />
            {label}
        </label>
    );
}

export function TextControl({ label, value, onChange, type = 'text', placeholder, ...rest }) {
    return (
        <label>
            {label}
            <input
                type={type}
                value={value}
                placeholder={placeholder}
                onChange={e => onChange(e.target.value)}
                {...rest}
            />
        </label>
    );
}

export function SelectControl({ label, value, options = [], onChange }) {
    return (
        <label>
            {label}
            <select value={value} aria-label={label} onChange={e => onChange(e.target.value)}>
                {options.map(o => (
                    <option key={o.value} value={o.value}>{o.label}</option>
                ))}
            </select>
        </label>
    );
}

export function TabPanel({ tabs = [], initialTabName, onSelect, children }) {
    const [active, setActive] = useState(initialTabName ?? tabs[0]?.name);
    const activeTab = tabs.find(t => t.name === active) ?? tabs[0];

    function handleSelect(name) {
        setActive(name);
        onSelect?.(name);
    }

    return (
        <div className="components-tab-panel">
            <div className="components-tab-panel__tabs" role="tablist">
                {tabs.map(tab => (
                    <button
                        key={tab.name}
                        type="button"
                        role="tab"
                        aria-selected={tab.name === active}
                        className={`components-tab-panel__tabs-item${tab.name === active ? ' is-active' : ''}`}
                        onClick={() => handleSelect(tab.name)}
                    >
                        {tab.title}
                    </button>
                ))}
            </div>
            {activeTab && children ? children(activeTab) : null}
        </div>
    );
}

let _ncId = 0;
export function __experimentalNumberControl({ value, onChange, min, max, style, autoFocus, label }) {
    const id = `nc-${++_ncId}`;
    return (
        <>
            <label htmlFor={id} style={{ position: 'absolute', width: 1, height: 1, overflow: 'hidden' }}>
                {label ?? 'Value'}
            </label>
            <input
                id={id}
                type="number"
                value={value}
                min={min}
                max={max}
                style={style}
                autoFocus={autoFocus}
                onChange={e => onChange(e.target.value)}
            />
        </>
    );
}
