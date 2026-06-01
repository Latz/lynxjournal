/**
 * Vitest — Unit tests for REST URL construction.
 *
 * These run with: npm run test:js
 * No WordPress or Docker needed.
 */

import { createRequire } from 'node:module';
const require = createRequire(import.meta.url);
const { REST_NAMESPACE, ROUTES } = require('../../constants.json');

// ---------------------------------------------------------------------------
// Helper extracted from plugin logic (inline here to keep the test self-contained;
// move to src/js/rest.js and import when you have more callers).
// ---------------------------------------------------------------------------
function buildRestUrl(baseUrl, namespace, route) {
    const clean = (s) => s.replaceAll(/^\/+|\/+$/g, '');
    return `${clean(baseUrl)}/wp-json/${clean(namespace)}${route}`;
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------
describe('constants.json — REST_NAMESPACE contract', () => {
    it('namespace follows the "slug/vN" pattern', () => {
        expect(REST_NAMESPACE).toMatch(/^[a-z0-9-]+\/v\d+$/);
    });

    it('every route starts with a leading slash', () => {
        for (const route of Object.values(ROUTES)) {
            expect(route).toMatch(/^\//);
        }
    });
});

describe('buildRestUrl()', () => {
    const base = 'http://localhost:8888';

    it('builds the add-link URL', () => {
        expect(buildRestUrl(base, REST_NAMESPACE, ROUTES.ADD_LINK))
            .toBe('http://localhost:8888/wp-json/linkdigest/v1/add-link');
    });

    it('builds the categories URL', () => {
        expect(buildRestUrl(base, REST_NAMESPACE, ROUTES.CATEGORIES))
            .toBe('http://localhost:8888/wp-json/linkdigest/v1/categories');
    });

    it('builds the links URL', () => {
        expect(buildRestUrl(base, REST_NAMESPACE, ROUTES.LINKS))
            .toBe('http://localhost:8888/wp-json/linkdigest/v1/links');
    });

    it('tolerates a trailing slash on baseUrl', () => {
        expect(buildRestUrl(base + '/', REST_NAMESPACE, ROUTES.ADD_LINK))
            .toBe('http://localhost:8888/wp-json/linkdigest/v1/add-link');
    });
});
