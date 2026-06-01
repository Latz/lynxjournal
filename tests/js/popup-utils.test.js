/**
 * Vitest — Unit tests for src/js/popup-utils.js
 *
 * Run with: npm run test:js
 * Environment: jsdom (DOM APIs available)
 */

import {
    extractPageDescription,
    renderCategories,
    isCacheFresh,
    buildApiUrl,
} from '../../src/js/popup-utils.js';

// ---------------------------------------------------------------------------
// extractPageDescription()
// ---------------------------------------------------------------------------

function makeDoc(metaTags) {
    const doc = document.implementation.createHTMLDocument('test');
    for (const attrs of metaTags) {
        const meta = doc.createElement('meta');
        for (const [k, v] of Object.entries(attrs)) {
            meta.setAttribute(k, v);
        }
        doc.head.appendChild(meta);
    }
    return doc;
}

describe('extractPageDescription()', () => {
    it('returns empty string when there are no meta tags', () => {
        const doc = document.implementation.createHTMLDocument('empty');
        expect(extractPageDescription(doc)).toBe('');
    });

    it('returns og:description when present', () => {
        const doc = makeDoc([{ property: 'og:description', content: 'OG desc' }]);
        expect(extractPageDescription(doc)).toBe('OG desc');
    });

    it('returns name=description when og:description is absent', () => {
        const doc = makeDoc([{ name: 'description', content: 'Meta desc' }]);
        expect(extractPageDescription(doc)).toBe('Meta desc');
    });

    it('prefers og:description over name=description', () => {
        const doc = makeDoc([
            { name: 'description', content: 'plain desc' },
            { property: 'og:description', content: 'og desc' },
        ]);
        expect(extractPageDescription(doc)).toBe('og desc');
    });

    it('falls back to twitter:description when og is absent', () => {
        const doc = makeDoc([{ name: 'twitter:description', content: 'Twitter desc' }]);
        expect(extractPageDescription(doc)).toBe('Twitter desc');
    });

    it('skips meta tags with empty content', () => {
        const doc = makeDoc([
            { property: 'og:description', content: '' },
            { name: 'description', content: 'fallback' },
        ]);
        expect(extractPageDescription(doc)).toBe('fallback');
    });

    it('trims leading and trailing whitespace from content', () => {
        const doc = makeDoc([{ name: 'description', content: '  trimmed  ' }]);
        expect(extractPageDescription(doc)).toBe('trimmed');
    });

    it('strips leading and trailing newlines from content', () => {
        const doc = makeDoc([{ name: 'description', content: '\n\nhas newlines\n' }]);
        expect(extractPageDescription(doc)).toBe('has newlines');
    });
});

// ---------------------------------------------------------------------------
// renderCategories()
// ---------------------------------------------------------------------------

describe('renderCategories()', () => {
    let container;

    beforeEach(() => {
        container = document.createElement('div');
    });

    it('shows "No categories available" message for empty array', () => {
        renderCategories([], container, document);
        expect(container.querySelector('.loading')).not.toBeNull();
        expect(container.querySelector('.loading').textContent).toContain('No categories');
    });

    it('shows "No categories available" message for null input', () => {
        renderCategories(null, container, document);
        expect(container.innerHTML).toContain('No categories available');
    });

    it('renders one radio input per category', () => {
        const cats = [
            { id: 1, name: 'Tech' },
            { id: 2, name: 'Science' },
        ];
        renderCategories(cats, container, document);
        const radios = container.querySelectorAll('input[type="radio"]');
        expect(radios.length).toBe(2);
    });

    it('sets the radio value to the category name', () => {
        renderCategories([{ id: 5, name: 'Gaming' }], container, document);
        const radio = container.querySelector('input[type="radio"]');
        expect(radio.value).toBe('Gaming');
    });

    it('sets radio id to cat-{id}', () => {
        renderCategories([{ id: 7, name: 'Food' }], container, document);
        const radio = container.querySelector('input[type="radio"]');
        expect(radio.id).toBe('cat-7');
    });

    it('all radios share the same name attribute', () => {
        const cats = [{ id: 1, name: 'A' }, { id: 2, name: 'B' }];
        renderCategories(cats, container, document);
        const radios = container.querySelectorAll('input[type="radio"]');
        for (const r of radios) {
            expect(r.name).toBe('linkdigest_category');
        }
    });

    it('renders a label for each radio with matching htmlFor', () => {
        renderCategories([{ id: 3, name: 'Music' }], container, document);
        const label = container.querySelector('label');
        expect(label).not.toBeNull();
        expect(label.htmlFor).toBe('cat-3');
        expect(label.textContent).toBe('Music');
    });

    it('clears previous content before rendering', () => {
        container.innerHTML = '<span id="old">old</span>';
        renderCategories([{ id: 1, name: 'A' }], container, document);
        expect(container.querySelector('#old')).toBeNull();
    });
});

// ---------------------------------------------------------------------------
// isCacheFresh()
// ---------------------------------------------------------------------------

describe('isCacheFresh()', () => {
    const TTL = 5 * 60 * 1000; // 5 minutes

    it('returns false for timestamp 0 (never written)', () => {
        expect(isCacheFresh(0, TTL, Date.now())).toBe(false);
    });

    it('returns false for null/undefined timestamp', () => {
        expect(isCacheFresh(null, TTL, Date.now())).toBe(false);
        expect(isCacheFresh(undefined, TTL, Date.now())).toBe(false);
    });

    it('returns true when cache was written 1 second ago', () => {
        const now = Date.now();
        expect(isCacheFresh(now - 1000, TTL, now)).toBe(true);
    });

    it('returns true when cache is exactly one ms before TTL', () => {
        const now = 1000000;
        expect(isCacheFresh(now - TTL + 1, TTL, now)).toBe(true);
    });

    it('returns false when cache is exactly at TTL boundary', () => {
        const now = 1000000;
        expect(isCacheFresh(now - TTL, TTL, now)).toBe(false);
    });

    it('returns false when cache is older than TTL', () => {
        const now = Date.now();
        expect(isCacheFresh(now - TTL - 1000, TTL, now)).toBe(false);
    });

    it('works with a 1-second TTL', () => {
        const now = 5000;
        expect(isCacheFresh(4500, 1000, now)).toBe(true);
        expect(isCacheFresh(3999, 1000, now)).toBe(false);
    });
});

// ---------------------------------------------------------------------------
// buildApiUrl()
// ---------------------------------------------------------------------------

describe('buildApiUrl()', () => {
    it('concatenates endpoint and route', () => {
        expect(buildApiUrl('https://example.com/wp-json/linkdigest/v1', '/categories'))
            .toBe('https://example.com/wp-json/linkdigest/v1/categories');
    });

    it('strips a trailing slash from the endpoint', () => {
        expect(buildApiUrl('https://example.com/wp-json/linkdigest/v1/', '/add-link'))
            .toBe('https://example.com/wp-json/linkdigest/v1/add-link');
    });

    it('strips multiple trailing slashes', () => {
        expect(buildApiUrl('https://example.com/wp-json/linkdigest/v1///', '/links'))
            .toBe('https://example.com/wp-json/linkdigest/v1/links');
    });

    it('builds the /links route', () => {
        expect(buildApiUrl('https://site.test/wp-json/linkdigest/v1', '/links'))
            .toBe('https://site.test/wp-json/linkdigest/v1/links');
    });

    it('builds the /schedule route', () => {
        expect(buildApiUrl('https://site.test/wp-json/linkdigest/v1', '/schedule'))
            .toBe('https://site.test/wp-json/linkdigest/v1/schedule');
    });
});
