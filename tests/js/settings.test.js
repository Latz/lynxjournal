import { describe, it, expect, vi, beforeEach } from 'vitest';
import { http, HttpResponse } from 'msw';
import { server } from './server.js';
import {
    loadSettings,
    showMessage,
    testConnection,
    handleSubmit,
    checkWpLogin,
} from '../../chrome-extension/settings.js';

const ENDPOINT = 'https://example.com/wp-json/lynxjournal/v1';
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
        const result = await testConnection(ENDPOINT, API_KEY);

        expect(result).toEqual([{ id: 1, name: 'Tech' }]);
    });

    it('returns null when API responds with non-ok status', async () => {
        server.use(http.get(`${ENDPOINT}/categories`, () => new HttpResponse(null, { status: 500 })));

        const result = await testConnection(ENDPOINT, API_KEY);

        expect(result).toBeNull();
    });

    it('returns null when fetch throws', async () => {
        server.use(http.get(`${ENDPOINT}/categories`, () => HttpResponse.error()));

        const result = await testConnection(ENDPOINT, API_KEY);

        expect(result).toBeNull();
    });
});

describe('handleSubmit', () => {
    beforeEach(buildSettingsDOM);

    it('saves settings and shows success when connection succeeds', async () => {
        document.getElementById('apiEndpoint').value = ENDPOINT;
        document.getElementById('apiKey').value = API_KEY;

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
        server.use(http.get(`${ENDPOINT}/categories`, () => new HttpResponse(null, { status: 500 })));

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
        server.use(http.get('https://notwordpress.com/wp-json/', () => HttpResponse.error()));

        await checkWpLogin('https://notwordpress.com');

        const status = document.getElementById('wpLoginStatus');
        expect(status.textContent).toContain('No WordPress installation found');
    });

    it('recognises wordpress_sec_ cookie as logged-in', async () => {
        // wp-json check succeeds via default handler; nonce fetch fails via default handler
        chrome.cookies.getAll.mockResolvedValueOnce([{ name: 'wordpress_sec_abc123' }]);

        await checkWpLogin('https://example.com');

        const status = document.getElementById('wpLoginStatus');
        expect(status.textContent).not.toContain('Not logged in');
    });

    it('shows logged-out message when no WP cookies are found', async () => {
        chrome.cookies.getAll.mockResolvedValueOnce([]);

        await checkWpLogin('https://example.com');

        const status = document.getElementById('wpLoginStatus');
        expect(status.textContent).toContain('Not logged in');
    });
});
