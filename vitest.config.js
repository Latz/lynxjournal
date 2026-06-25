import { defineConfig } from 'vitest/config';
import { fileURLToPath } from 'node:url';

export default defineConfig({
    esbuild: {
        jsx: 'automatic',
    },
    test: {
        globals:     true,
        environment: 'jsdom',
        include:     ['tests/js/**/*.test.{js,ts,jsx,tsx}'],
        setupFiles:  ['tests/js/chrome-mock.js', 'tests/js/setup-react.js'],
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
    resolve: {
        alias: {
            '@wordpress/element':    'react',
            '@wordpress/i18n':       fileURLToPath(new URL('tests/js/__mocks__/wordpress-i18n.js', import.meta.url)),
            '@wordpress/components': fileURLToPath(new URL('tests/js/__mocks__/wordpress-components.jsx', import.meta.url)),
            '@wordpress/api-fetch':  fileURLToPath(new URL('tests/js/__mocks__/wordpress-api-fetch.js', import.meta.url)),
        },
    },
});
