import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir:  'tests/e2e/extension',
    timeout:  60_000,
    reporter: 'list',
    projects: [
        {
            name: 'extension',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
