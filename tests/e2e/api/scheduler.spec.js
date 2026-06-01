/**
 * Playwright — E2E tests for the LinkDigest scheduler.
 *
 * Covers:
 *  - POST /schedule/run (structured payload, daily mode)
 *  - POST /schedule/run with count mode (threshold met / not met)
 *  - POST /schedule/run with age mode (threshold met)
 *  - GET  /schedule/diagnostics
 *  - POST /schedule/preview
 *
 * Run with: npm run test:e2e:api
 */

import { test, expect } from '@playwright/test';
import constants from '../../../constants.json' assert { type: 'json' };

const { REST_NAMESPACE, ROUTES } = constants;

const api   = (route) => `/?rest_route=/${REST_NAMESPACE}${route}`;
const wpApi = (route) => `/?rest_route=/wp/v2${route}`;

/** Creates a linkdigest link via REST and returns its post_id. */
async function createLink(request, suffix = Date.now()) {
    const res = await request.post(api(ROUTES.ADD_LINK), {
        data: { title: `Scheduler E2E Link ${suffix}`, url: `https://example.com/e2e-${suffix}` },
    });
    expect(res.status()).toBe(200);
    const { post_id } = await res.json();
    return post_id;
}

/** Saves a schedule config via REST. */
async function saveSchedule(request, config) {
    const res = await request.post(api(ROUTES.SCHEDULE), { data: config });
    expect(res.status()).toBe(200);
}

// ---------------------------------------------------------------------------
// Daily mode — basic publish flow + structured payload
// ---------------------------------------------------------------------------

test.describe('Scheduler — daily mode', () => {
    let linkId;

    test('POST /schedule/run publishes links and returns a structured payload', async ({ request }) => {
        linkId = await createLink(request);

        await saveSchedule(request, {
            mode: 'daily',
            times: ['09:00'],
            recurrence: {},
            trigger: {},
        });

        const runRes = await request.post(api('/schedule/run'));
        expect(runRes.status()).toBe(200);

        const body = await runRes.json();
        // Structured payload check
        expect(typeof body.published).toBe('boolean');
        expect(typeof body.link_count).toBe('number');
        expect('post_id' in body).toBeTruthy();
        expect('reason' in body).toBeTruthy();
        expect(body.published).toBe(true);
        expect(body.link_count).toBeGreaterThan(0);

        // Roundup post was created
        const postsRes = await request.get(wpApi('/posts?search=Links%3A'));
        expect(postsRes.status()).toBe(200);
        const posts = await postsRes.json();
        expect(posts.length).toBeGreaterThan(0);

        // Link was marked as published via meta (still written alongside the custom status)
        const linkRes = await request.get(wpApi(`/linkdigest/${linkId}?context=edit`));
        expect(linkRes.status()).toBe(200);
        const link = await linkRes.json();
        expect(link.meta._linkdigest_publish_status).toBe('published');
    });
});

// ---------------------------------------------------------------------------
// Count mode
// ---------------------------------------------------------------------------

test.describe('Scheduler — count mode', () => {

    test('publishes when total pending links meets the threshold', async ({ request }) => {
        await createLink(request, `count-met-${Date.now()}`);

        await saveSchedule(request, {
            mode: 'count',
            times: ['09:00'],
            recurrence: {},
            trigger: { count: 1 }, // threshold = 1 → always met with ≥1 link
        });

        const runRes = await request.post(api('/schedule/run'));
        expect(runRes.status()).toBe(200);
        const body = await runRes.json();
        expect(body.published).toBe(true);
        expect(body.link_count).toBeGreaterThan(0);
    });

    test('skips publishing when total pending links is below the threshold', async ({ request }) => {
        await saveSchedule(request, {
            mode: 'count',
            times: ['09:00'],
            recurrence: {},
            trigger: { count: 99999 }, // impossibly high threshold
        });

        const runRes = await request.post(api('/schedule/run'));
        expect(runRes.status()).toBe(200);
        const body = await runRes.json();
        expect(body.published).toBe(false);
        expect(body.link_count).toBe(0);
    });
});

// ---------------------------------------------------------------------------
// Age mode
// ---------------------------------------------------------------------------

test.describe('Scheduler — age mode', () => {

    test('publishes when age threshold is 0 days (always met)', async ({ request }) => {
        await createLink(request, `age-${Date.now()}`);

        await saveSchedule(request, {
            mode: 'age',
            times: ['09:00'],
            recurrence: {},
            trigger: { days: 0 }, // 0-day threshold: any link qualifies
        });

        const runRes = await request.post(api('/schedule/run'));
        expect(runRes.status()).toBe(200);
        const body = await runRes.json();
        // With days=0 the cutoff is now, and any post older than "now" passes
        expect(body.published).toBe(true);
    });
});

// ---------------------------------------------------------------------------
// GET /schedule/diagnostics
// ---------------------------------------------------------------------------

test.describe('GET /schedule/diagnostics', () => {

    test('returns the expected diagnostics shape', async ({ request }) => {
        const res = await request.get(api('/schedule/diagnostics'));
        expect(res.status()).toBe(200);

        const body = await res.json();
        expect('next_scheduled' in body).toBeTruthy();
        expect('last_run' in body).toBeTruthy();
        expect('wp_cron_disabled' in body).toBeTruthy();
        expect(typeof body.wp_cron_disabled).toBe('boolean');
    });

    test('last_run is populated after a run', async ({ request }) => {
        // Trigger a run first
        await saveSchedule(request, { mode: 'daily', times: ['09:00'], recurrence: {}, trigger: {} });
        await request.post(api('/schedule/run'));

        const res  = await request.get(api('/schedule/diagnostics'));
        const body = await res.json();

        expect(body.last_run).not.toBeNull();
        expect(typeof body.last_run.ts).toBe('number');
        expect(typeof body.last_run.mode).toBe('string');
        expect(typeof body.last_run.status).toBe('string');
        expect(typeof body.last_run.link_count).toBe('number');
    });
});

// ---------------------------------------------------------------------------
// POST /schedule/preview
// ---------------------------------------------------------------------------

test.describe('POST /schedule/preview', () => {

    test('returns the expected preview shape', async ({ request }) => {
        await saveSchedule(request, { mode: 'daily', times: ['09:00'], recurrence: {}, trigger: {} });

        const res = await request.post(api('/schedule/preview'));
        expect(res.status()).toBe(200);

        const body = await res.json();
        expect(typeof body.would_publish).toBe('boolean');
        expect(typeof body.link_count).toBe('number');
        expect(typeof body.total_pending).toBe('number');
        expect(Array.isArray(body.by_category)).toBeTruthy();
        expect(typeof body.mode).toBe('string');
    });

    test('would_publish is true for daily mode when pending links exist', async ({ request }) => {
        await createLink(request, `preview-${Date.now()}`);
        await saveSchedule(request, { mode: 'daily', times: ['09:00'], recurrence: {}, trigger: {} });

        const res  = await request.post(api('/schedule/preview'));
        const body = await res.json();

        expect(body.would_publish).toBe(true);
        expect(body.total_pending).toBeGreaterThan(0);
    });

    test('would_publish is false for count mode when threshold is not met', async ({ request }) => {
        await saveSchedule(request, {
            mode: 'count',
            times: ['09:00'],
            recurrence: {},
            trigger: { count: 99999 },
        });

        const res  = await request.post(api('/schedule/preview'));
        const body = await res.json();

        expect(body.would_publish).toBe(false);
        expect(body.link_count).toBe(0);
    });

    test('would_publish is false for manual mode regardless of pending links', async ({ request }) => {
        await createLink(request, `manual-preview-${Date.now()}`);
        await saveSchedule(request, { mode: 'manual', times: [], recurrence: {}, trigger: {} });

        const res  = await request.post(api('/schedule/preview'));
        const body = await res.json();

        expect(body.would_publish).toBe(false);
    });
});
