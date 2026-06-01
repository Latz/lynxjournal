import { describe, it, expect, beforeEach } from 'vitest';
import './chrome-mock.js';
import { applyI18n } from '../../chrome-extension/i18n.js';

beforeEach(() => {
    document.body.innerHTML = '';
});

describe('applyI18n', () => {
    it('sets textContent on data-i18n elements when message exists', () => {
        chrome.i18n.getMessage.mockImplementation(key => key === 'saveLink' ? 'Save Link' : '');
        document.body.innerHTML = '<button data-i18n="saveLink"></button>';

        applyI18n();

        expect(document.querySelector('button').textContent).toBe('Save Link');
    });

    it('leaves textContent unchanged when message is empty', () => {
        chrome.i18n.getMessage.mockImplementation(() => '');
        document.body.innerHTML = '<span data-i18n="unknownKey">Original</span>';

        applyI18n();

        expect(document.querySelector('span').textContent).toBe('Original');
    });

    it('sets placeholder on data-i18n-placeholder elements when message exists', () => {
        chrome.i18n.getMessage.mockImplementation(key => key === 'tagsPlaceholder' ? 'Add tags…' : '');
        document.body.innerHTML = '<input data-i18n-placeholder="tagsPlaceholder">';

        applyI18n();

        expect(document.querySelector('input').placeholder).toBe('Add tags…');
    });

    it('leaves placeholder unchanged when message is empty', () => {
        chrome.i18n.getMessage.mockImplementation(() => '');
        document.body.innerHTML = '<input data-i18n-placeholder="unknownKey" placeholder="Keep me">';

        applyI18n();

        expect(document.querySelector('input').placeholder).toBe('Keep me');
    });

    it('handles multiple data-i18n elements', () => {
        chrome.i18n.getMessage.mockImplementation(key => ({ btnA: 'A', btnB: 'B' })[key] ?? '');
        document.body.innerHTML = '<span data-i18n="btnA"></span><span data-i18n="btnB"></span>';

        applyI18n();

        const spans = document.querySelectorAll('span');
        expect(spans[0].textContent).toBe('A');
        expect(spans[1].textContent).toBe('B');
    });
});
