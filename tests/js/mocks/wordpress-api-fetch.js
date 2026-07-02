/**
 * Lightweight stand-in for @wordpress/api-fetch in the Vitest environment.
 * @wordpress/api-fetch is externalized in production (provided by WP core
 * as wp.apiFetch) and isn't installed as a dependency, so it can't be
 * resolved directly by Vite — this mock is aliased in vitest.config.js.
 *
 * Tests override behavior per-call via `apiFetch.mockResolvedValueOnce(...)`
 * / `apiFetch.mockRejectedValueOnce(...)` etc.
 */

import { vi } from 'vitest';

const apiFetch = vi.fn(() => Promise.resolve({}));

export default apiFetch;
