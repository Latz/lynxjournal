import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig({
    esbuild: {
        jsx: 'automatic',
    },
    resolve: {
        alias: {
            // @wordpress/element, @wordpress/i18n, @wordpress/api-fetch, and
            // @wordpress/components are externalized in production (provided
            // by WP core as wp.element etc.) and aren't installed as
            // dependencies, so Vite can't resolve them directly.
            '@wordpress/element':     'react',
            '@wordpress/i18n':        path.resolve(__dirname, 'tests/js/mocks/wordpress-i18n.js'),
            '@wordpress/api-fetch':   path.resolve(__dirname, 'tests/js/mocks/wordpress-api-fetch.js'),
            '@wordpress/components':  path.resolve(__dirname, 'tests/js/mocks/wordpress-components.jsx'),
        },
    },
    test: {
        globals:     true,
        environment: 'jsdom',
        include:     ['tests/js/**/*.test.{js,ts,jsx,tsx}'],
        setupFiles:  ['tests/js/chrome-mock.js', 'tests/js/browser-mock.js', 'tests/js/react-setup.js'],
        coverage: {
            provider:          'v8',
            reportsDirectory:  'bin/reports/coverage-js',
            reporter:          ['text', 'lcov'],
            include:           ['src/**', 'chrome-extension/**', 'firefox-extension/**'],
            exclude:           ['**/*.min.js', '**/tagify*'],
        },
        // rest-routes.test.js uses createRequire (Node APIs) — keep it in node env
        environmentOptions: {},
    },
});
