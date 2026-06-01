/**
 * Playwright — REST API tests for the LinkDigest plugin.
 *
 * Prerequisites:
 *   npm run env:start          (first time: pulls Docker images, ~2 min)
 *   npm run test:e2e:api
 *
 * The wp-env container runs at http://localhost:8888.
 * Authorization header is set globally in playwright.config.js.
 */

import { test, expect } from '@playwright/test';
import constants from '../../../constants.json' assert { type: 'json' };

const { REST_NAMESPACE, ROUTES } = constants;

const api = (route) => `/?rest_route=/${REST_NAMESPACE}${route}`;

// ---------------------------------------------------------------------------
// GET /categories
// ---------------------------------------------------------------------------
test.describe('GET /categories', () => {
    test('returns 200 with an array', async ({ request }) => {
        const res = await request.get(api(ROUTES.CATEGORIES));
        expect(res.status()).toBe(200);

        const body = await res.json();
        expect(Array.isArray(body)).toBe(true);
    });
});

// ---------------------------------------------------------------------------
// POST /add-link → GET /links/{id} → DELETE /links/{id}
// ---------------------------------------------------------------------------
test.describe('Link CRUD', () => {
    let createdId;
    const testUrl = `https://example.com/test-${Date.now()}`;

    test('POST /add-link creates a link and returns its ID', async ({ request }) => {
        const res = await request.post(api(ROUTES.ADD_LINK), {
            data: {
                url:        testUrl,
                title:      'Playwright Test Link',
                categories: ['Uncategorized'],
            },
        });

        // Accept 200 or 201 depending on your handler.
        expect([200, 201]).toContain(res.status());

        const body = await res.json();
        expect(body).toHaveProperty('post_id');
        expect(typeof body.post_id).toBe('number');
        createdId = body.post_id;
    });

    test('DELETE /links/{id} removes the link', async ({ request }) => {
        test.skip(!createdId, 'No link was created in the previous step');

        const res = await request.delete(api(`${ROUTES.LINKS}/${createdId}`));
        expect([200, 204]).toContain(res.status());
    });
});

// ---------------------------------------------------------------------------
// Auth guard
// ---------------------------------------------------------------------------
test('unauthenticated POST /add-link returns 401 or 403', async ({ request }) => {
    const res = await request.post(api(ROUTES.ADD_LINK), {
        headers: { Authorization: '' },   // strip the global header
        data:    { url: 'https://example.com', title: 'No auth' },
    });
    expect([401, 403]).toContain(res.status());
});
