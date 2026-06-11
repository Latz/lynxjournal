/**
 * Playwright — E2E tests for the LynxJournal Chrome extension.
 *
 * Loads the extension unpacked into a real Chromium instance and verifies
 * that the popup, settings page, and i18n strings render correctly.
 *
 * Run with: npm run test:e2e:ext
 */

import { test as base, chromium, expect } from '@playwright/test';
import path from 'path';
import os from 'os';
import fs from 'fs';

const EXT_PATH = path.resolve(process.cwd(), 'chrome-extension');

const test = base.extend({
    context: async ({}, use) => {
        const userDataDir = fs.mkdtempSync(path.join(os.tmpdir(), 'pw-ext-'));
        const context = await chromium.launchPersistentContext(userDataDir, {
            // Use the full Chromium binary (not headless shell) so extensions load.
            // headless: false + --headless=new gives headless mode that supports extensions.
            executablePath: chromium.executablePath(),
            headless: false,
            args: [
                '--headless=new',
                '--no-sandbox',
                '--disable-dev-shm-usage',
                `--disable-extensions-except=${EXT_PATH}`,
                `--load-extension=${EXT_PATH}`,
            ],
        });
        // Block all http/https requests — extension tests must be self-contained.
        await context.route('**', route => {
            const url = route.request().url();
            if (url.startsWith('http://') || url.startsWith('https://')) {
                route.abort();
            } else {
                route.continue();
            }
        });
        await use(context);
        await context.close();
        fs.rmSync(userDataDir, { recursive: true, force: true });
    },
    extensionId: async ({ context }, use) => {
        let [sw] = context.serviceWorkers();
        if (!sw) sw = await context.waitForEvent('serviceworker');
        await use(sw.url().split('/')[2]);
    },
});

test('popup renders title and save button', async ({ context, extensionId }) => {
    const page = await context.newPage();
    await page.goto(`chrome-extension://${extensionId}/popup.html`);
    await expect(page.locator('#saveBtn, #openSettings').first()).toBeVisible();
    // Title must be the resolved i18n value, not a raw __MSG_ placeholder
    const title = await page.title();
    expect(title).not.toMatch(/__MSG_/);
    expect(title.length).toBeGreaterThan(0);
});

test('settings page renders API endpoint field', async ({ context, extensionId }) => {
    const page = await context.newPage();
    await page.goto(`chrome-extension://${extensionId}/settings.html`);
    await expect(page.locator('#apiEndpoint')).toBeVisible();
    const title = await page.title();
    expect(title).not.toMatch(/__MSG_/);
    expect(title.length).toBeGreaterThan(0);
});

test('popup loads without console errors', async ({ context, extensionId }) => {
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.goto(`chrome-extension://${extensionId}/popup.html`);
    await page.waitForLoadState('networkidle');
    expect(errors).toEqual([]);
});

test('settings page loads without console errors', async ({ context, extensionId }) => {
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.goto(`chrome-extension://${extensionId}/settings.html`);
    await page.waitForLoadState('networkidle');
    expect(errors).toEqual([]);
});

test('popup shows setup message when not configured', async ({ context, extensionId }) => {
    const page = await context.newPage();
    await page.goto(`chrome-extension://${extensionId}/popup.html`);
    await expect(page.locator('#openSettings')).toBeVisible();
    await expect(page.locator('#mainForm')).toBeHidden();
});

test('popup i18n labels are resolved', async ({ context, extensionId }) => {
    const page = await context.newPage();
    await page.goto(`chrome-extension://${extensionId}/popup.html`);
    const btnText = await page.locator('#openSettings').textContent();
    expect(btnText?.trim()).not.toBe('');
    expect(btnText).not.toMatch(/__MSG_/);
});

test('settings page has all config fields', async ({ context, extensionId }) => {
    const page = await context.newPage();
    await page.goto(`chrome-extension://${extensionId}/settings.html`);
    await expect(page.locator('#wpAddress')).toBeVisible();
    await expect(page.locator('#apiEndpoint')).toBeVisible();
    await expect(page.locator('#apiKey')).toBeVisible();
});

test('icons load without 404', async ({ context, extensionId }) => {
    const page = await context.newPage();
    const failed = [];
    page.on('response', r => {
        if (r.url().includes('icon') && r.status() === 404) failed.push(r.url());
    });
    await page.goto(`chrome-extension://${extensionId}/popup.html`);
    await page.waitForLoadState('networkidle');
    expect(failed).toEqual([]);
});
