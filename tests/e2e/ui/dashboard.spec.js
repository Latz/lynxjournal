/**
 * Playwright — UI tests for the LynxJournal dashboard.
 *
 * Logs into wp-admin and verifies the plugin dashboard renders correctly.
 *
 * Run with: npm run test:e2e:ui
 */

import { test, expect } from '@playwright/test';
import constants from '../../../constants.json' assert { type: 'json' };

// All UI tests share a login session — run serially to avoid concurrent login conflicts.
test.describe.configure({ mode: 'serial' });

const { WP_ENV, REST_NAMESPACE, ROUTES } = constants;

const ADMIN_URL     = `${WP_ENV.BASE_URL}/wp-admin`;
const DASHBOARD_URL = `${ADMIN_URL}/admin.php?page=lynxjournal-dashboard`;
const api = (route) => `/?rest_route=/${REST_NAMESPACE}${route}`;

// Shared login helper — reused across tests.
async function wpLogin(page) {
    await page.goto(`${ADMIN_URL}/`);
    await page.fill('#user_login', WP_ENV.ADMIN_USER);
    await page.fill('#user_pass', process.env.WP_ADMIN_PASSWORD ?? WP_ENV.ADMIN_PASSWORD);
    await page.click('#wp-submit');
    await page.waitForURL(url => url.href.includes('/wp-admin/') && !url.href.includes('wp-login'));
}

// ---------------------------------------------------------------------------
// Dashboard presence
// ---------------------------------------------------------------------------
test.describe('LynxJournal dashboard', () => {
    // Seed at least one link so stats grid and link list always render.
    test.beforeAll(async ({ request }) => {
        await request.post(api(ROUTES.ADD_LINK), {
            data: { title: 'UI Test Seed Link', url: `https://example.com/ui-seed-${Date.now()}` },
        });
    });

    test.beforeEach(async ({ page }) => {
        await wpLogin(page);
    });

    test('page loads without a PHP fatal', async ({ page }) => {
        await page.goto(DASHBOARD_URL);
        // A PHP fatal would render "Parse error" or "Fatal error" in the body.
        await expect(page.locator('body')).not.toContainText('Fatal error');
        await expect(page.locator('body')).not.toContainText('Parse error');
    });

    test('stats header is visible', async ({ page }) => {
        await page.goto(DASHBOARD_URL);
        // The compact stats header added during the dashboard redesign.
        await expect(page.locator('.lynxjournal-stats-grid')).toBeVisible();
    });

    test('link list renders in the page', async ({ page }) => {
        await page.goto(DASHBOARD_URL);
        await expect(page.locator('#lynxjournal-postbox-container-1 .postbox').first()).toBeVisible();
    });
});

// ---------------------------------------------------------------------------
// Trash / delete confirmation (inline, not native confirm())
// ---------------------------------------------------------------------------
test('clicking trash shows inline confirmation, not browser dialog', async ({ page }) => {
    await wpLogin(page);
    await page.goto(DASHBOARD_URL);

    const trashBtn = page.locator('.lynxjournal-delete-btn').first();

    // Only run if there is at least one link to trash.
    if (await trashBtn.count() === 0) {
        test.skip();
    }

    // No browser dialog should appear — the key decision from CLAUDE.local.md.
    page.on('dialog', (dialog) => {
        throw new Error(`Unexpected native dialog: ${dialog.message()}`);
    });

    await trashBtn.click();

    // Inline confirm UI should appear instead.
    await expect(page.locator('.lynxjournal-delete-confirm-row')).toBeVisible();
});
