import React from 'react';

export function Button({ children, onClick, variant, size, 'aria-label': ariaLabel, type = 'button', ...rest }) {
    return <button onClick={onClick} aria-label={ariaLabel} type={type} data-variant={variant} {...rest}>{children}</button>;
}

export function __experimentalNumberControl({ value, onChange, min, max, style }) {
    return (
        <input
            type="number"
            value={value}
            min={min}
            max={max}
            style={style}
            onChange={e => onChange(e.target.value)}
        />
    );
}

export function SelectControl({ value, options = [], onChange, label }) {
    return (
        <select value={value} aria-label={label} onChange={e => onChange(e.target.value)}>
            {options.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
    );
}

export function Notice({ children, status, isDismissible, onRemove }) {
    return <div role="alert" data-status={status}>{children}</div>;
}

export function CheckboxControl({ label, checked, onChange }) {
    return (
        <label>
            <input type="checkbox" checked={checked} onChange={e => onChange(e.target.checked)} />
            {label}
        </label>
    );
}

export function TextControl({ label, value, onChange, type = 'text', ...rest }) {
    return (
        <label>
            {label}
            <input type={type} value={value} onChange={e => onChange(e.target.value)} {...rest} />
        </label>
    );
}
