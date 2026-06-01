import { defineConfig, devices } from '@playwright/test';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const { WP_ENV } = require('./constants.json');

const baseURL = process.env.WP_BASE_URL ?? WP_ENV.BASE_URL;

export default defineConfig({
    testDir:    'tests/e2e',
    fullyParallel: true,
    retries:    process.env.CI ? 2 : 0,
    reporter:   process.env.CI ? 'github' : 'list',
    timeout:    30_000,

    use: {
        baseURL,
        extraHTTPHeaders: {
            // WordPress application passwords: base64("admin:password")
            // Override via WP_AUTH env var in CI.
            Authorization: `Basic ${
                Buffer.from(
                    `${WP_ENV.ADMIN_USER}:${process.env.WP_ADMIN_PASSWORD ?? WP_ENV.ADMIN_PASSWORD}`
                ).toString('base64')
            }`,
        },
    },

    projects: [
        {
            name: 'api',
            testMatch: 'tests/e2e/api/**/*.spec.js',
            use: { ...devices['Desktop Chrome'] },
        },
        {
            name: 'ui',
            testMatch: 'tests/e2e/ui/**/*.spec.js',
            use: { ...devices['Desktop Chrome'] },
        },
    ],

    // Automatically start wp-env before the suite runs.
    // Comment out if you manage wp-env manually.
    // webServer: {
    //     command: 'npm run env:start',
    //     url: baseURL,
    //     reuseExistingServer: true,
    // },
});
