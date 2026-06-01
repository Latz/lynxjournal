import { describe, it, expect, vi, beforeEach } from 'vitest';
import {
    loadSettings,
    showMessage,
    testConnection,
    handleSubmit,
    checkWpLogin,
} from '../../chrome-extension/settings.js';

const ENDPOINT = 'https://example.com/wp-json/linkdigest/v1';
const API_KEY  = 'test-key';

function buildSettingsDOM() {
    document.body.innerHTML = `
        <form id="settingsForm">
            <input id="apiEndpoint" value="">
            <input id="apiKey" value="">
            <input id="wpAddress" value="">
            <button id="createEndpointBtn"></button>
        </form>
        <div id="message"></div>
        <div id="wpLoginStatus"></div>
    `;
}

describe('loadSettings', () => {
    beforeEach(buildSettingsDOM);

    it('populates form fields from storage', async () => {
        chrome.storage.sync.get.mockResolvedValue({ apiEndpoint: ENDPOINT, apiKey: API_KEY });

        await loadSettings();

        expect(document.getElementById('apiEndpoint').value).toBe(ENDPOINT);
        expect(document.getElementById('apiKey').value).toBe(API_KEY);
    });

    it('leaves fields empty when storage has no values', async () => {
        chrome.storage.sync.get.mockResolvedValue({});

        await loadSettings();

        expect(document.getElementById('apiEndpoint').value).toBe('');
        expect(document.getElementById('apiKey').value).toBe('');
    });
});

describe('showMessage', () => {
    beforeEach(buildSettingsDOM);

    it('displays the message element with the given class', () => {
        showMessage('All good!', 'success');

        const el = document.getElementById('message');
        expect(el.textContent).toBe('All good!');
        expect(el.className).toContain('success');
        expect(el.style.display).toBe('block');
    });
});

describe('testConnection', () => {
    it('returns categories array when API responds ok', async () => {
        const cats = [{ id: 1, name: 'Tech' }];
        global.fetch = vi.fn().mockResolvedValue({
            ok:   true,
            json: async () => cats,
        });

        const result = await testConnection(ENDPOINT, API_KEY);

        expect(result).toEqual(cats);
        expect(fetch).toHaveBeenCalledWith(
            `${ENDPOINT}/categories`,
            expect.objectContaining({ method: 'GET' })
        );
    });

    it('returns null when API responds with non-ok status', async () => {
        global.fetch = vi.fn().mockResolvedValue({ ok: false });

        const result = await testConnection(ENDPOINT, API_KEY);

        expect(result).toBeNull();
    });

    it('returns null when fetch throws', async () => {
        global.fetch = vi.fn().mockRejectedValue(new Error('network'));

        const result = await testConnection(ENDPOINT, API_KEY);

        expect(result).toBeNull();
    });
});

describe('handleSubmit', () => {
    beforeEach(buildSettingsDOM);

    it('saves settings and shows success when connection succeeds', async () => {
        document.getElementById('apiEndpoint').value = ENDPOINT;
        document.getElementById('apiKey').value = API_KEY;
        global.fetch = vi.fn().mockResolvedValue({
            ok:   true,
            json: async () => [{ id: 1, name: 'Tech' }],
        });

        const event = { preventDefault: vi.fn() };
        await handleSubmit(event);

        expect(chrome.storage.sync.set).toHaveBeenCalledWith(
            expect.objectContaining({ apiEndpoint: ENDPOINT, apiKey: API_KEY })
        );
        expect(document.getElementById('message').className).toContain('success');
    });

    it('shows error and does not save when connection fails', async () => {
        document.getElementById('apiEndpoint').value = ENDPOINT;
        document.getElementById('apiKey').value = API_KEY;
        global.fetch = vi.fn().mockResolvedValue({ ok: false });

        const event = { preventDefault: vi.fn() };
        await handleSubmit(event);

        expect(chrome.storage.sync.set).not.toHaveBeenCalled();
        expect(document.getElementById('message').className).toContain('error');
    });
});

describe('checkWpLogin', () => {
    beforeEach(buildSettingsDOM);

    it('hides status element when url is empty', async () => {
        const status = document.getElementById('wpLoginStatus');
        status.style.display = 'block';

        await checkWpLogin('');

        expect(status.style.display).toBe('none');
    });

    it('shows not-WordPress error when wp-json check fails', async () => {
        global.fetch = vi.fn().mockRejectedValue(new Error('network'));

        await checkWpLogin('https://notwordpress.com');

        const status = document.getElementById('wpLoginStatus');
        expect(status.textContent).toContain('No WordPress installation found');
    });

    it('recognises wordpress_sec_ cookie as logged-in', async () => {
        global.fetch = vi.fn()
            .mockResolvedValueOnce({
                ok:   true,
                url:  'https://example.com/wp-json/',
                json: async () => ({ namespaces: ['linkdigest/v1'] }),
            })
            .mockRejectedValue(new Error('nonce fetch not mocked'));
        chrome.cookies.getAll.mockResolvedValueOnce([{ name: 'wordpress_sec_abc123' }]);

        await checkWpLogin('https://example.com');

        const status = document.getElementById('wpLoginStatus');
        expect(status.textContent).not.toContain('Not logged in');
    });

    it('shows logged-out message when no WP cookies are found', async () => {
        global.fetch = vi.fn().mockResolvedValue({
            ok:   true,
            url:  'https://example.com/wp-json/',
            json: async () => ({ namespaces: ['linkdigest/v1'] }),
        });
        chrome.cookies.getAll.mockResolvedValueOnce([]);

        await checkWpLogin('https://example.com');

        const status = document.getElementById('wpLoginStatus');
        expect(status.textContent).toContain('Not logged in');
    });
});
