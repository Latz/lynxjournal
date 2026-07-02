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

export function Button({ children, onClick, disabled, isBusy, className, 'aria-label': ariaLabel, title, ...rest }) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            className={className}
            aria-label={ariaLabel}
            aria-busy={isBusy || undefined}
            title={title}
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

export function TextControl({ label, value, onChange, type = 'text', placeholder }) {
    return (
        <label>
            {label}
            <input
                type={type}
                value={value}
                placeholder={placeholder}
                onChange={e => onChange(e.target.value)}
            />
        </label>
    );
}

export function SelectControl({ label, value, options = [], onChange }) {
    return (
        <label>
            {label}
            <select value={value} onChange={e => onChange(e.target.value)}>
                {options.map(o => (
                    <option key={o.value} value={o.value}>{o.label}</option>
                ))}
            </select>
        </label>
    );
}

export function __experimentalNumberControl({ value, onChange, min, max, style, autoFocus }) {
    return (
        <input
            type="number"
            value={value}
            min={min}
            max={max}
            style={style}
            autoFocus={autoFocus}
            onChange={e => onChange(e.target.value)}
        />
    );
}
