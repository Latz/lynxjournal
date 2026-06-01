# LinkDigest — Test Suite

## Overview

The plugin has three layers of tests:

| Layer | Runner | Requires WordPress? | Command |
|---|---|---|---|
| PHP Unit | Pest + Brain Monkey | No | `composer test:unit` |
| JS Unit | Vitest | No | `npm run test:js` |
| E2E | Playwright + wp-env | Yes (Docker) | `npm run test:e2e` |

---

## PHP Unit Tests

### Dependencies

- **Pest v4** — test runner (thin wrapper over PHPUnit)
- **Brain Monkey** — patches WordPress functions without loading WordPress
- **Patchwork** — low-level function replacement, loaded first in the bootstrap

Install with:

```bash
composer install
```

### Running

```bash
# All PHP tests
composer test

# Unit suite only
composer test:unit

# Integration suite (requires a real WP database — see below)
composer test:integration
```

### How it works

The bootstrap (`tests/bootstrap-unit.php`) loads in this fixed order:

1. **Patchwork** — must be first; rewrites PHP's include stream so every subsequent file is patchable
2. **Composer autoload** — Brain Monkey, Mockery, Pest internals
3. **WP stubs** (`tests/stubs/wp-stubs.php`) — thin PHP classes (`WP_Post`, `WP_Error`, `WP_REST_Request`) so tests can reference WP types without loading WP
4. **Test helpers** (`tests/helpers.php`) — `makePost()` and `makeRequest()` factory functions used across test files
5. **Plugin bootstrap** (`linkdigest.php`) — `add_action`/`add_filter` calls are absorbed by a one-shot Brain Monkey setUp/tearDown

`pest.php` wraps every test in `Brain\Monkey\setUp()` / `Brain\Monkey\tearDown()` + `Mockery::close()` so each test starts clean.

### Helpers

`makePost(int $id, string $title, string $type = 'linkdigest', string $content = ''): WP_Post`
— builds a minimal `WP_Post` object for use in assertions.

`makeRequest(array $params = [], array $headers = []): WP_REST_Request`
— builds a `WP_REST_Request` stub with preset params and headers.

### Test files

| File | Function under test |
|---|---|
| `ValidateLinkTest.php` | `linkdigestValidateLinkForPublish()` |
| `RestAddLinkTest.php` | `linkdigest_rest_add_link()` |
| `RestPermissionTest.php` | `linkdigest_rest_permission_check()` |
| `BuildPostContentTest.php` | `linkdigestBuildPostContent()` |
| `BatchPublishTest.php` | `linkdigestBatchPublishLinks()` |
| `ScheduleTest.php` | `linkdigest_get_schedule()`, `linkdigest_save_schedule()` |
| `CategoriesTest.php` | Category-related functions |
| `UnpublishLinkTest.php` | Link unpublish flow |
| `CreateBlogPostTest.php` | Blog post creation |
| `ExampleUnitTest.php` | Sanity / smoke test |

### Writing a new PHP unit test

Create a file in `tests/Unit/`, for example `tests/Unit/MyFunctionTest.php`:

```php
<?php

declare(strict_types=1);

use Brain\Monkey\Functions;

describe('myFunction()', function (): void {

    it('does the thing', function (): void {
        // Stub any WP function you need:
        Functions\when('get_option')->justReturn('value');

        $result = myFunction();

        expect($result)->toBe('expected');
    });
});
```

Use `Functions\when('wp_fn')->justReturn($value)` for simple stubs.
Use `Functions\when('wp_fn')->alias(fn(...$args) => ...)` when you need to inspect arguments or vary the return value.

---

## JS Unit Tests

### Dependencies

- **Vitest v3** — test runner
- **jsdom** — DOM environment (available in `popup-utils.test.js` and `settings-utils.test.js`)

Install with:

```bash
npm install
```

### Running

```bash
# Single run
npm run test:js

# Watch mode (re-runs on save)
npm run test:js:watch

# With code coverage report
npm run test:js:coverage
```

### Test files

| File | Module under test |
|---|---|
| `tests/js/rest-routes.test.js` | `constants.json` REST namespace/route contracts + `buildRestUrl()` |
| `tests/js/popup-utils.test.js` | `src/js/popup-utils.js` — `extractPageDescription()`, `renderCategories()`, `isCacheFresh()`, `buildApiUrl()` |
| `tests/js/settings-utils.test.js` | `src/js/settings-utils.js` — `normalizeEndpoint()`, `buildRequestHeaders()`, `testConnection()` |

### Writing a new JS unit test

Create a file in `tests/js/` matching `*.test.js`. Vitest auto-discovers it.

```js
import { myHelper } from '../../src/js/my-module.js';

describe('myHelper()', () => {
    it('returns the expected value', () => {
        expect(myHelper('input')).toBe('expected');
    });
});
```

Use `vi.fn()` to mock dependencies injected as arguments (see `testConnection()` in `settings-utils.test.js` for the pattern).

---

## E2E Tests (Playwright + wp-env)

### Prerequisites

- Docker installed and running
- Node dependencies installed: `npm install`
- Playwright browsers installed: `npx playwright install chromium`

### Starting the WordPress environment

```bash
# Pull images and start (first run takes ~2 min)
npm run env:start

# Stop when done
npm run env:stop

# Wipe state and start fresh
npm run env:clean && npm run env:start
```

The environment runs at `http://localhost:8888`. Default credentials are in `constants.json`:

```json
"WP_ENV": {
  "BASE_URL":       "http://localhost:8888",
  "ADMIN_USER":     "admin",
  "ADMIN_PASSWORD": "password"
}
```

Override the password at runtime with `WP_ADMIN_PASSWORD=yourpass npm run test:e2e`.

### Running

```bash
# All E2E tests
npm run test:e2e

# REST API tests only (no browser, fast)
npm run test:e2e:api

# UI tests only (headed browser)
npm run test:e2e:ui

# Interactive debug mode
npm run test:e2e:debug
```

### Test files

**`tests/e2e/api/links.spec.js`** — REST API contract tests:
- `GET /wp-json/linkdigest/v1/categories` returns 200 + array
- `POST /add-link` creates a link and returns its ID
- `DELETE /links/{id}` removes the created link
- Unauthenticated `POST /add-link` returns 401 or 403

**`tests/e2e/ui/dashboard.spec.js`** — Dashboard UI tests:
- Page loads without a PHP fatal error
- Stats header is visible
- Link list or empty-state renders
- Clicking trash shows an inline confirmation (not a browser `confirm()` dialog)

### Authentication

`playwright.config.js` sets a global `Authorization: Basic ...` header using the credentials from `constants.json`. API tests inherit this automatically. UI tests log in via the WP login form (`wpLogin()` helper in `dashboard.spec.js`).

### Writing a new E2E test

Place API tests in `tests/e2e/api/` and UI tests in `tests/e2e/ui/`. Use the `api()` helper for REST routes:

```js
import { test, expect } from '@playwright/test';
import { createRequire } from 'module';
const require = createRequire(import.meta.url);
const { REST_NAMESPACE, ROUTES } = require('../../../constants.json');

const api = (route) => `/wp-json/${REST_NAMESPACE}${route}`;

test('GET /links returns an array', async ({ request }) => {
    const res = await request.get(api(ROUTES.LINKS));
    expect(res.status()).toBe(200);
    expect(Array.isArray(await res.json())).toBe(true);
});
```

---

## CI notes

Playwright is configured with `retries: 2` and `reporter: 'github'` when `CI=true`. The `webServer` block in `playwright.config.js` is commented out — start `wp-env` as a separate step in your pipeline before running the E2E suite.

Example GitHub Actions snippet:

```yaml
- run: npm run env:start
- run: npm run test:e2e
  env:
    CI: true
    WP_ADMIN_PASSWORD: ${{ secrets.WP_ADMIN_PASSWORD }}
```
