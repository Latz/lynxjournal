import React from 'react';

export const Button = ({ children, onClick, disabled }) =>
    <button onClick={onClick} disabled={disabled}>{children}</button>;

export const Notice = ({ children, status, onRemove }) =>
    <div data-status={status}>{children}<button onClick={onRemove}>×</button></div>;

export const CheckboxControl = ({ label, checked, onChange }) =>
    <label><input type="checkbox" checked={checked} onChange={e => onChange(e.target.checked)} />{label}</label>;

export const TextControl = ({ label, value, onChange, type, placeholder }) =>
    <label>{label}<input type={type || 'text'} value={value} placeholder={placeholder} onChange={e => onChange(e.target.value)} /></label>;

export const SelectControl = ({ label, value, options = [], onChange }) =>
    <label>{label}<select value={value} onChange={e => onChange(e.target.value)}>
        {options.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
    </select></label>;
