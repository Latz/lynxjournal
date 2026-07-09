import { describe, it, expect, beforeEach } from 'vitest';
import { applyI18n } from '../../chrome-extension/i18n.js';

describe('applyI18n', () => {
    beforeEach(() => {
        chrome.i18n.getMessage.mockImplementation((key) => {
            const messages = {
                greeting:        'Hello there',
                placeholderText: 'Type here',
            };
            return messages[key] || '';
        });
    });

    it('sets textContent for elements with data-i18n', () => {
        document.body.innerHTML = '<span data-i18n="greeting"></span>';

        applyI18n();

        expect(document.querySelector('span').textContent).toBe('Hello there');
    });

    it('sets placeholder for elements with data-i18n-placeholder', () => {
        document.body.innerHTML = '<input data-i18n-placeholder="placeholderText">';

        applyI18n();

        expect(document.querySelector('input').placeholder).toBe('Type here');
    });

    it('leaves element untouched when the message is empty', () => {
        document.body.innerHTML = '<span data-i18n="missingKey">fallback</span>';

        applyI18n();

        expect(document.querySelector('span').textContent).toBe('fallback');
    });
});
