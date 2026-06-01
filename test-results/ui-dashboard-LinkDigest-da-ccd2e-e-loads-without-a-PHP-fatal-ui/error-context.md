# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: ui/dashboard.spec.js >> LinkDigest dashboard >> page loads without a PHP fatal
- Location: tests/e2e/ui/dashboard.spec.js:34:9

# Error details

```
Error: page.waitForURL: Test ended.
=========================== logs ===========================
  "domcontentloaded" event fired
============================================================
```

# Test source

```ts
  1  | /**
  2  |  * Playwright — UI tests for the LinkDigest dashboard.
  3  |  *
  4  |  * Logs into wp-admin and verifies the plugin dashboard renders correctly.
  5  |  *
  6  |  * Run with: npm run test:e2e:ui
  7  |  */
  8  | 
  9  | import { test, expect } from '@playwright/test';
  10 | import constants from '../../../constants.json' assert { type: 'json' };
  11 | 
  12 | const { WP_ENV } = constants;
  13 | 
  14 | const ADMIN_URL     = `${WP_ENV.BASE_URL}/wp-admin`;
  15 | const DASHBOARD_URL = `${ADMIN_URL}/admin.php?page=linkdigest-dashboard`;
  16 | 
  17 | // Shared login helper — reused across tests.
  18 | async function wpLogin(page) {
  19 |     await page.goto(`${ADMIN_URL}/`);
  20 |     await page.fill('#user_login', WP_ENV.ADMIN_USER);
  21 |     await page.fill('#user_pass', process.env.WP_ADMIN_PASSWORD ?? WP_ENV.ADMIN_PASSWORD);
  22 |     await page.click('#wp-submit');
> 23 |     await page.waitForURL(/wp-admin/);
     |                ^ Error: page.waitForURL: Test ended.
  24 | }
  25 | 
  26 | // ---------------------------------------------------------------------------
  27 | // Dashboard presence
  28 | // ---------------------------------------------------------------------------
  29 | test.describe('LinkDigest dashboard', () => {
  30 |     test.beforeEach(async ({ page }) => {
  31 |         await wpLogin(page);
  32 |     });
  33 | 
  34 |     test('page loads without a PHP fatal', async ({ page }) => {
  35 |         await page.goto(DASHBOARD_URL);
  36 |         // A PHP fatal would render "Parse error" or "Fatal error" in the body.
  37 |         await expect(page.locator('body')).not.toContainText('Fatal error');
  38 |         await expect(page.locator('body')).not.toContainText('Parse error');
  39 |     });
  40 | 
  41 |     test('stats header is visible', async ({ page }) => {
  42 |         await page.goto(DASHBOARD_URL);
  43 |         // The compact stats header added during the dashboard redesign.
  44 |         await expect(page.locator('.linkdigest-stats-grid')).toBeVisible();
  45 |     });
  46 | 
  47 |     test('link list renders in the page', async ({ page }) => {
  48 |         await page.goto(DASHBOARD_URL);
  49 |         // The unpublished links section is always present; the list itself only
  50 |         // renders when links exist, so check the containing postbox.
  51 |         await expect(page.locator('#postbox-container-1 .postbox').first()).toBeVisible();
  52 |     });
  53 | });
  54 | 
  55 | // ---------------------------------------------------------------------------
  56 | // Trash / delete confirmation (inline, not native confirm())
  57 | // ---------------------------------------------------------------------------
  58 | test('clicking trash shows inline confirmation, not browser dialog', async ({ page }) => {
  59 |     await wpLogin(page);
  60 |     await page.goto(DASHBOARD_URL);
  61 | 
  62 |     const trashBtn = page.locator('.linkdigest-delete-btn').first();
  63 | 
  64 |     // Only run if there is at least one link to trash.
  65 |     if (await trashBtn.count() === 0) {
  66 |         test.skip();
  67 |     }
  68 | 
  69 |     // No browser dialog should appear — the key decision from CLAUDE.local.md.
  70 |     page.on('dialog', (dialog) => {
  71 |         throw new Error(`Unexpected native dialog: ${dialog.message()}`);
  72 |     });
  73 | 
  74 |     await trashBtn.click();
  75 | 
  76 |     // Inline confirm UI should appear instead.
  77 |     await expect(page.locator('.linkdigest-delete-confirm-row')).toBeVisible();
  78 | });
  79 | 
```