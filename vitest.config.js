import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        globals:     true,
        environment: 'jsdom',
        include:     ['tests/js/**/*.test.{js,ts}'],
        setupFiles:  ['tests/js/chrome-mock.js'],
        coverage: {
            provider:          'v8',
            reportsDirectory:  'bin/reports/coverage-js',
            reporter:          ['text', 'lcov'],
            include:           ['src/**', 'chrome-extension/**'],
            exclude:           ['**/*.min.js', '**/tagify*'],
        },
        // rest-routes.test.js uses createRequire (Node APIs) — keep it in node env
        environmentOptions: {},
    },
});
