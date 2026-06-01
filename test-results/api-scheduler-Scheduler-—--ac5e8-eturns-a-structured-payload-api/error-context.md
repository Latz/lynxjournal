# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: api/scheduler.spec.js >> Scheduler — daily mode >> POST /schedule/run publishes links and returns a structured payload
- Location: tests/e2e/api/scheduler.spec.js:45:9

# Error details

```
Error: expect(received).toBe(expected) // Object.is equality

Expected: 200
Received: 404
```

# Test source

```ts
  1   | /**
  2   |  * Playwright — E2E tests for the LinkDigest scheduler.
  3   |  *
  4   |  * Covers:
  5   |  *  - POST /schedule/run (structured payload, daily mode)
  6   |  *  - POST /schedule/run with count mode (threshold met / not met)
  7   |  *  - POST /schedule/run with age mode (threshold met)
  8   |  *  - GET  /schedule/diagnostics
  9   |  *  - POST /schedule/preview
  10  |  *
  11  |  * Run with: npm run test:e2e:api
  12  |  */
  13  | 
  14  | import { test, expect } from '@playwright/test';
  15  | import constants from '../../../constants.json' assert { type: 'json' };
  16  | 
  17  | const { REST_NAMESPACE, ROUTES } = constants;
  18  | 
  19  | const api   = (route) => `/?rest_route=/${REST_NAMESPACE}${route}`;
  20  | const wpApi = (route) => `/?rest_route=/wp/v2${route}`;
  21  | 
  22  | /** Creates a linkdigest link via REST and returns its post_id. */
  23  | async function createLink(request, suffix = Date.now()) {
  24  |     const res = await request.post(api(ROUTES.ADD_LINK), {
  25  |         data: { title: `Scheduler E2E Link ${suffix}`, url: `https://example.com/e2e-${suffix}` },
  26  |     });
  27  |     expect(res.status()).toBe(200);
  28  |     const { post_id } = await res.json();
  29  |     return post_id;
  30  | }
  31  | 
  32  | /** Saves a schedule config via REST. */
  33  | async function saveSchedule(request, config) {
  34  |     const res = await request.post(api(ROUTES.SCHEDULE), { data: config });
  35  |     expect(res.status()).toBe(200);
  36  | }
  37  | 
  38  | // ---------------------------------------------------------------------------
  39  | // Daily mode — basic publish flow + structured payload
  40  | // ---------------------------------------------------------------------------
  41  | 
  42  | test.describe('Scheduler — daily mode', () => {
  43  |     let linkId;
  44  | 
  45  |     test('POST /schedule/run publishes links and returns a structured payload', async ({ request }) => {
  46  |         linkId = await createLink(request);
  47  | 
  48  |         await saveSchedule(request, {
  49  |             mode: 'daily',
  50  |             times: ['09:00'],
  51  |             recurrence: {},
  52  |             trigger: {},
  53  |         });
  54  | 
  55  |         const runRes = await request.post(api('/schedule/run'));
  56  |         expect(runRes.status()).toBe(200);
  57  | 
  58  |         const body = await runRes.json();
  59  |         // Structured payload check
  60  |         expect(typeof body.published).toBe('boolean');
  61  |         expect(typeof body.link_count).toBe('number');
  62  |         expect('post_id' in body).toBeTruthy();
  63  |         expect('reason' in body).toBeTruthy();
  64  |         expect(body.published).toBe(true);
  65  |         expect(body.link_count).toBeGreaterThan(0);
  66  | 
  67  |         // Roundup post was created
  68  |         const postsRes = await request.get(wpApi('/posts?search=Links%3A'));
> 69  |         expect(postsRes.status()).toBe(200);
      |                                   ^ Error: expect(received).toBe(expected) // Object.is equality
  70  |         const posts = await postsRes.json();
  71  |         expect(posts.length).toBeGreaterThan(0);
  72  | 
  73  |         // Link was marked as published via meta (still written alongside the custom status)
  74  |         const linkRes = await request.get(wpApi(`/linkdigest/${linkId}?context=edit`));
  75  |         expect(linkRes.status()).toBe(200);
  76  |         const link = await linkRes.json();
  77  |         expect(link.meta._linkdigest_publish_status).toBe('published');
  78  |     });
  79  | });
  80  | 
  81  | // ---------------------------------------------------------------------------
  82  | // Count mode
  83  | // ---------------------------------------------------------------------------
  84  | 
  85  | test.describe('Scheduler — count mode', () => {
  86  | 
  87  |     test('publishes when total pending links meets the threshold', async ({ request }) => {
  88  |         await createLink(request, `count-met-${Date.now()}`);
  89  | 
  90  |         await saveSchedule(request, {
  91  |             mode: 'count',
  92  |             times: ['09:00'],
  93  |             recurrence: {},
  94  |             trigger: { count: 1 }, // threshold = 1 → always met with ≥1 link
  95  |         });
  96  | 
  97  |         const runRes = await request.post(api('/schedule/run'));
  98  |         expect(runRes.status()).toBe(200);
  99  |         const body = await runRes.json();
  100 |         expect(body.published).toBe(true);
  101 |         expect(body.link_count).toBeGreaterThan(0);
  102 |     });
  103 | 
  104 |     test('skips publishing when total pending links is below the threshold', async ({ request }) => {
  105 |         await saveSchedule(request, {
  106 |             mode: 'count',
  107 |             times: ['09:00'],
  108 |             recurrence: {},
  109 |             trigger: { count: 99999 }, // impossibly high threshold
  110 |         });
  111 | 
  112 |         const runRes = await request.post(api('/schedule/run'));
  113 |         expect(runRes.status()).toBe(200);
  114 |         const body = await runRes.json();
  115 |         expect(body.published).toBe(false);
  116 |         expect(body.link_count).toBe(0);
  117 |     });
  118 | });
  119 | 
  120 | // ---------------------------------------------------------------------------
  121 | // Age mode
  122 | // ---------------------------------------------------------------------------
  123 | 
  124 | test.describe('Scheduler — age mode', () => {
  125 | 
  126 |     test('publishes when age threshold is 0 days (always met)', async ({ request }) => {
  127 |         await createLink(request, `age-${Date.now()}`);
  128 | 
  129 |         await saveSchedule(request, {
  130 |             mode: 'age',
  131 |             times: ['09:00'],
  132 |             recurrence: {},
  133 |             trigger: { days: 0 }, // 0-day threshold: any link qualifies
  134 |         });
  135 | 
  136 |         const runRes = await request.post(api('/schedule/run'));
  137 |         expect(runRes.status()).toBe(200);
  138 |         const body = await runRes.json();
  139 |         // With days=0 the cutoff is now, and any post older than "now" passes
  140 |         expect(body.published).toBe(true);
  141 |     });
  142 | });
  143 | 
  144 | // ---------------------------------------------------------------------------
  145 | // GET /schedule/diagnostics
  146 | // ---------------------------------------------------------------------------
  147 | 
  148 | test.describe('GET /schedule/diagnostics', () => {
  149 | 
  150 |     test('returns the expected diagnostics shape', async ({ request }) => {
  151 |         const res = await request.get(api('/schedule/diagnostics'));
  152 |         expect(res.status()).toBe(200);
  153 | 
  154 |         const body = await res.json();
  155 |         expect('next_scheduled' in body).toBeTruthy();
  156 |         expect('last_run' in body).toBeTruthy();
  157 |         expect('wp_cron_disabled' in body).toBeTruthy();
  158 |         expect(typeof body.wp_cron_disabled).toBe('boolean');
  159 |     });
  160 | 
  161 |     test('last_run is populated after a run', async ({ request }) => {
  162 |         // Trigger a run first
  163 |         await saveSchedule(request, { mode: 'daily', times: ['09:00'], recurrence: {}, trigger: {} });
  164 |         await request.post(api('/schedule/run'));
  165 | 
  166 |         const res  = await request.get(api('/schedule/diagnostics'));
  167 |         const body = await res.json();
  168 | 
  169 |         expect(body.last_run).not.toBeNull();
```