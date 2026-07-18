#!/usr/bin/env node
/**
 * Verifies that each browser extension's background script actually parses
 * under the loading mode its manifest.json declares.
 *
 * Background: firefox-extension/background.js once used top-level `export`
 * without manifest.json declaring `background.type: "module"`. That's a
 * hard SyntaxError when a real browser loads it as a classic script — but
 * every test suite missed it, because Vitest imports the file directly as
 * an ES module regardless of what the manifest says. `node --check` is
 * NOT a reliable substitute here: Node 20+/22 auto-detects import/export
 * syntax and silently parses plain .js files as modules even with no
 * "type": "module" anywhere. `new Function(source)` forces classic-script
 * parsing the same way a browser would, so it's what's used below.
 */

import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const ROOT = path.dirname(path.dirname(fileURLToPath(import.meta.url)));

/**
 * @param {string} extensionDir Absolute path to the extension's directory.
 * @returns {{ file: string, isModule: boolean }[]} Background scripts declared in its manifest.
 */
function getBackgroundScripts(extensionDir) {
    const manifest = JSON.parse(readFileSync(path.join(extensionDir, 'manifest.json'), 'utf8'));
    const bg = manifest.background;
    if (!bg) return [];

    const isModule = bg.type === 'module';
    const files = bg.service_worker
        ? [bg.service_worker]
        : (bg.scripts ?? []);

    return files.map(file => ({ file, isModule }));
}

/**
 * @param {string} extensionDir Absolute path to the extension's directory.
 * @returns {string[]} Error messages, empty if the extension is clean.
 */
function checkExtension(extensionDir) {
    const errors = [];

    for (const { file, isModule } of getBackgroundScripts(extensionDir)) {
        if (isModule) continue; // import/export is always legal here — not the risk case.

        const fullPath = path.join(extensionDir, file);
        const source = readFileSync(fullPath, 'utf8');
        try {
            new Function(source); // eslint-disable-line no-new-func -- syntax check only, never called
        } catch (e) {
            errors.push(
                `${path.relative(ROOT, fullPath)}: fails to parse as a classic (non-module) script, ` +
                `but manifest.json doesn't declare background.type = "module".\n  ${e.message}`
            );
        }
    }

    return errors;
}

const extensionDirs = ['chrome-extension', 'firefox-extension'].map(name => path.join(ROOT, name));
const allErrors = extensionDirs.flatMap(checkExtension);

if (allErrors.length > 0) {
    console.error('Background-script module-parity check failed:\n');
    for (const err of allErrors) {
        console.error(`  ✗ ${err}\n`);
    }
    process.exit(1);
}

console.log('Background-script module-parity check: OK');
