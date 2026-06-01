/**
 * Vitest — Unit tests for src/js/settings-utils.js
 *
 * Run with: npm run test:js
 * Environment: jsdom
 */

import {
    normalizeEndpoint,
    buildRequestHeaders,
    testConnection,
} from '../../src/js/settings-utils.js';

// ---------------------------------------------------------------------------
// normalizeEndpoint()
// ---------------------------------------------------------------------------

describe('normalizeEndpoint()', () => {
    it('removes a single trailing slash', () => {
        expect(normalizeEndpoint('https://example.com/wp-json/linkdigest/v1/'))
            .toBe('https://example.com/wp-json/linkdigest/v1');
    });

    it('removes multiple trailing slashes', () => {
        expect(normalizeEndpoint('https://example.com//'))
            .toBe('https://example.com');
    });

    it('does not alter a URL with no trailing slash', () => {
        expect(normalizeEndpoint('https://example.com/wp-json/linkdigest/v1'))
            .toBe('https://example.com/wp-json/linkdigest/v1');
    });

    it('returns empty string for empty input', () => {
        expect(normalizeEndpoint('')).toBe('');
    });

    it('returns empty string for null/undefined', () => {
        expect(normalizeEndpoint(null)).toBe('');
        expect(normalizeEndpoint(undefined)).toBe('');
    });

    it('trims trailing whitespace and removes trailing slash', () => {
        expect(normalizeEndpoint('https://example.com/  ')).toBe('https://example.com');
    });
});

// ---------------------------------------------------------------------------
// buildRequestHeaders()
// ---------------------------------------------------------------------------

describe('buildRequestHeaders()', () => {
    it('returns an object with Content-Type application/json', () => {
        const headers = buildRequestHeaders('any-key');
        expect(headers['Content-Type']).toBe('application/json');
    });

    it('returns an object with X-LinkDigest-API-Key set to the given key', () => {
        const headers = buildRequestHeaders('my-secret-key');
        expect(headers['X-LinkDigest-API-Key']).toBe('my-secret-key');
    });

    it('returns exactly two keys', () => {
        const headers = buildRequestHeaders('k');
        expect(Object.keys(headers)).toHaveLength(2);
    });
});

// ---------------------------------------------------------------------------
// testConnection()
// ---------------------------------------------------------------------------

describe('testConnection()', () => {
    it('returns true when fetch responds with ok=true', async () => {
        const mockFetch = vi.fn().mockResolvedValue({ ok: true });
        const result = await testConnection('https://example.com/wp-json/linkdigest/v1', 'key', mockFetch);
        expect(result).toBe(true);
    });

    it('returns false when fetch responds with ok=false', async () => {
        const mockFetch = vi.fn().mockResolvedValue({ ok: false });
        const result = await testConnection('https://example.com/wp-json/linkdigest/v1', 'key', mockFetch);
        expect(result).toBe(false);
    });

    it('returns false when fetch throws a network error', async () => {
        const mockFetch = vi.fn().mockRejectedValue(new Error('Network error'));
        const result = await testConnection('https://example.com/wp-json/linkdigest/v1', 'key', mockFetch);
        expect(result).toBe(false);
    });

    it('calls fetch with the /categories route appended', async () => {
        const mockFetch = vi.fn().mockResolvedValue({ ok: true });
        await testConnection('https://example.com/wp-json/linkdigest/v1', 'abc', mockFetch);
        expect(mockFetch).toHaveBeenCalledWith(
            'https://example.com/wp-json/linkdigest/v1/categories',
            expect.any(Object)
        );
    });

    it('passes the API key in the X-LinkDigest-API-Key header', async () => {
        const mockFetch = vi.fn().mockResolvedValue({ ok: true });
        await testConnection('https://example.com/wp-json/linkdigest/v1', 'secret-key', mockFetch);
        const [, options] = mockFetch.mock.calls[0];
        expect(options.headers['X-LinkDigest-API-Key']).toBe('secret-key');
    });

    it('uses GET method', async () => {
        const mockFetch = vi.fn().mockResolvedValue({ ok: true });
        await testConnection('https://example.com/wp-json/linkdigest/v1', 'key', mockFetch);
        const [, options] = mockFetch.mock.calls[0];
        expect(options.method).toBe('GET');
    });
});
