/**
 * Pure utility functions extracted from settings.js.
 */

/**
 * Remove trailing slashes from a string.
 *
 * @param {string} str
 * @returns {string}
 */
function removeTrailingSlashes(str) {
    let clean = str;
    while (clean.endsWith('/')) clean = clean.slice(0, -1);
    return clean;
}

/**
 * Remove a trailing slash from a URL string.
 *
 * @param {string} url
 * @returns {string}
 */
export function normalizeEndpoint(url) {
    return removeTrailingSlashes((url || '').trimEnd());
}

/**
 * Build the headers object for a LinkDigest API request.
 *
 * @param {string} apiKey
 * @returns {Record<string, string>}
 */
export function buildRequestHeaders(apiKey) {
    return {
        'Content-Type': 'application/json',
        'X-LinkDigest-API-Key': apiKey,
    };
}

/**
 * Test the API connection by fetching /categories.
 * Returns true if the server responds with a 2xx status.
 *
 * @param {string}   endpoint - Normalized (no trailing slash) base URL.
 * @param {string}   apiKey
 * @param {Function} [fetchFn] - Injectable fetch implementation (defaults to global fetch).
 * @returns {Promise<boolean>}
 */
export async function testConnection(endpoint, apiKey, fetchFn = fetch) {
    try {
        const response = await fetchFn(`${endpoint}/categories`, {
            method:  'GET',
            headers: buildRequestHeaders(apiKey),
        });
        return response.ok;
    } catch {
        return false;
    }
}
