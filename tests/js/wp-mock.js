/**
 * Mocks for @wordpress/* packages that are webpack externals (not installed as
 * node_modules). Loaded as a vitest setupFile so every test file gets them.
 */
import { vi } from 'vitest';

vi.mock('@wordpress/api-fetch', () => ({ default: vi.fn() }));

vi.mock('@wordpress/i18n', () => ({
    __:      (str) => str,
    sprintf: (fmt, ...args) => args.reduce((s, a, i) => s.replace(/%\d+\$s|%s/, String(a), i), fmt),
}));

vi.mock('@wordpress/components', () => {
    const React = require('react');
    return {
        Button:        ({ children, onClick, isBusy, disabled, variant, size, ...p }) =>
                           React.createElement('button', { onClick, disabled, ...p }, children),
        Notice:        ({ children, status, onRemove }) =>
                           React.createElement('div', { 'data-status': status },
                               children,
                               React.createElement('button', { onClick: onRemove }, '×')),
        CheckboxControl: ({ label, checked, onChange }) =>
                           React.createElement('label', null,
                               React.createElement('input', { type: 'checkbox', checked, onChange: e => onChange(e.target.checked) }),
                               label),
        TextControl:   ({ label, value, onChange, type, placeholder }) =>
                           React.createElement('label', null,
                               label,
                               React.createElement('input', { type: type || 'text', value, placeholder, onChange: e => onChange(e.target.value) })),
        SelectControl: ({ value, options, onChange, label }) =>
                           React.createElement('label', null,
                               label,
                               React.createElement('select', { value, onChange: e => onChange(e.target.value) },
                                   (options || []).map(o => React.createElement('option', { key: o.value, value: o.value }, o.label)))),
    };
});
