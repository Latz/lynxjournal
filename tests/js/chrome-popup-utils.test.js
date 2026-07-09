import { describe, it, expect } from 'vitest';
import { extractPageDescription, renderCategories } from '../../chrome-extension/popup-utils.js';

describe('extractPageDescription', () => {
    it('returns og:description content when present', () => {
        document.head.innerHTML = '<meta property="og:description" content="OG desc">';
        expect(extractPageDescription(document)).toBe('OG desc');
    });

    it('returns plain description when og:description is absent', () => {
        document.head.innerHTML = '<meta name="description" content="Plain desc">';
        expect(extractPageDescription(document)).toBe('Plain desc');
    });

    it('falls back to twitter:description', () => {
        document.head.innerHTML = '<meta name="twitter:description" content="Twitter desc">';
        expect(extractPageDescription(document)).toBe('Twitter desc');
    });

    it('trims leading/trailing newlines', () => {
        document.head.innerHTML = '<meta name="description" content="\n  Padded desc  \n">';
        expect(extractPageDescription(document)).toBe('Padded desc');
    });

    it('returns empty string when no meta description exists', () => {
        document.head.innerHTML = '';
        expect(extractPageDescription(document)).toBe('');
    });
});

describe('renderCategories', () => {
    it('renders a radio input and label per category', () => {
        const container = document.createElement('div');

        renderCategories([{ id: 1, name: 'Tech' }, { id: 2, name: 'News' }], container, document);

        const radios = container.querySelectorAll('.category-checkbox');
        const labels = container.querySelectorAll('.category-label');
        expect(radios).toHaveLength(2);
        expect(labels).toHaveLength(2);
        expect(radios[0].value).toBe('Tech');
        expect(labels[0].textContent).toBe('Tech');
        expect(labels[0].htmlFor).toBe('cat-1');
    });

    it('clears existing content before rendering', () => {
        const container = document.createElement('div');
        container.innerHTML = '<span>stale</span>';

        renderCategories([{ id: 1, name: 'Tech' }], container, document);

        expect(container.innerHTML).not.toContain('stale');
    });

    it('renders nothing for an empty category list', () => {
        const container = document.createElement('div');

        renderCategories([], container, document);

        expect(container.innerHTML).toBe('');
    });
});
