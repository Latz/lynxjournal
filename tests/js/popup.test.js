import { describe, it, expect, vi, beforeEach } from 'vitest';
import { http, HttpResponse } from 'msw';
import { server } from './server.js';
import {
    checkSettings,
    renderCategories,
    loadCategories,
    loadPageInfo,
    handleSubmit,
    showMessage,
    openSettings,
    initPopup,
    extractPageDescription,
} from '../../chrome-extension/popup.js';

const ENDPOINT = 'https://example.com/wp-json/lynxjournal/v1';
const API_KEY  = 'test-key';

function buildPopupDOM() {
    document.body.innerHTML = `
        <div id="setupMessage" style="display:none"></div>
        <div id="mainForm" style="display:none"></div>
        <div id="categoriesList"></div>
        <div id="message"></div>
        <form id="linkForm">
            <input id="title" value="My Link">
            <input id="url" value="https://example.com">
            <textarea id="content">A description</textarea>
            <input type="radio" class="category-checkbox" value="Tech" checked>
            <input id="tags" value="">
            <button id="saveBtn">
                <span class="btn-text">Save</span>
                <span class="btn-loading" style="display:none">Saving…</span>
            </button>
        </form>
        <button id="settingsBtn"></button>
        <button id="openSettings"></button>
    `;
}

describe('checkSettings', () => {
    beforeEach(buildPopupDOM);

    it('returns null and shows setup message when credentials are missing', async () => {
        chrome.storage.sync.get.mockResolvedValue({});

        const result = await checkSettings();

        expect(result).toBeNull();
        expect(document.getElementById('setupMessage').style.display).toBe('block');
        expect(document.getElementById('mainForm').style.display).toBe('none');
    });

    it('returns the settings object and shows main form when credentials are present', async () => {
        chrome.storage.sync.get.mockResolvedValue({ apiEndpoint: ENDPOINT, apiKey: API_KEY });

        const result = await checkSettings();

        expect(result).toEqual({ apiEndpoint: ENDPOINT, apiKey: API_KEY });
        expect(document.getElementById('mainForm').style.display).toBe('block');
        expect(document.getElementById('setupMessage').style.display).toBe('none');
    });
});

describe('renderCategories', () => {
    beforeEach(buildPopupDOM);

    it('renders radio buttons sorted alphabetically', () => {
        renderCategories([
            { id: 2, name: 'Zeal' },
            { id: 1, name: 'Apple' },
            { id: 3, name: 'Mango' },
        ]);

        const labels = [...document.querySelectorAll('.category-label')].map(el => el.textContent);
        expect(labels).toEqual(['Apple', 'Mango', 'Zeal']);
    });

    it('shows fallback message when categories list is empty', () => {
        renderCategories([]);
        expect(document.getElementById('categoriesList').innerHTML).toContain('No categories available');
    });

    it('shows fallback message when categories is null', () => {
        renderCategories(null);
        expect(document.getElementById('categoriesList').innerHTML).toContain('No categories available');
    });
});

describe('loadCategories', () => {
    beforeEach(buildPopupDOM);

    const settings = { apiEndpoint: ENDPOINT, apiKey: API_KEY };

    it('fetches categories and renders them', async () => {
        chrome.storage.local.get.mockResolvedValue({});

        await loadCategories(settings);

        expect(document.querySelector('.category-label').textContent).toBe('Tech');
    });

    it('renders cached categories immediately before fetching', async () => {
        const cached = [{ id: 2, name: 'Cached' }];
        chrome.storage.local.get.mockResolvedValue({ categories: cached });

        await loadCategories(settings);

        expect(chrome.storage.local.get).toHaveBeenCalled();
    });

    it('shows error message when fetch fails and no cache exists', async () => {
        chrome.storage.local.get.mockResolvedValue({});
        server.use(http.get(`${ENDPOINT}/categories`, () => HttpResponse.error()));

        await loadCategories(settings);

        expect(document.getElementById('categoriesList').innerHTML).toContain('Failed to load categories');
    });

    it.each([401, 403, 404])('shows the setup message and hides the form on a %i response', async status => {
        chrome.storage.local.get.mockResolvedValue({});
        server.use(http.get(`${ENDPOINT}/categories`, () => new HttpResponse(null, { status })));

        await loadCategories(settings);

        expect(document.getElementById('setupMessage').style.display).toBe('block');
        expect(document.getElementById('mainForm').style.display).toBe('none');
    });
});

describe('loadPageInfo', () => {
    beforeEach(buildPopupDOM);

    it('fills title, url, and description from the active tab', async () => {
        chrome.tabs.query.mockResolvedValue([{ id: 1, title: 'My Page', url: 'https://example.com/page' }]);
        chrome.scripting.executeScript.mockResolvedValue([{ result: 'Page description' }]);

        await loadPageInfo();

        expect(document.getElementById('title').value).toBe('My Page');
        expect(document.getElementById('url').value).toBe('https://example.com/page');
        expect(document.getElementById('content').value).toBe('Page description');
    });

    it('fills title and url but leaves content untouched when description extraction throws', async () => {
        chrome.tabs.query.mockResolvedValue([{ id: 1, title: 'My Page', url: 'https://example.com/page' }]);
        chrome.scripting.executeScript.mockRejectedValue(new Error('restricted page'));

        await loadPageInfo();

        expect(document.getElementById('title').value).toBe('My Page');
        expect(document.getElementById('url').value).toBe('https://example.com/page');
        expect(document.getElementById('content').value).toBe('A description');
    });

    it('does nothing when there is no active tab', async () => {
        chrome.tabs.query.mockResolvedValue([]);

        await expect(loadPageInfo()).resolves.toBeUndefined();
        expect(document.getElementById('title').value).toBe('My Link');
    });

    it('fails silently when chrome.tabs.query itself throws', async () => {
        chrome.tabs.query.mockRejectedValue(new Error('boom'));

        await expect(loadPageInfo()).resolves.toBeUndefined();
    });
});

describe('handleSubmit', () => {
    beforeEach(buildPopupDOM);

    it('posts form data to the API and shows success notification', async () => {
        chrome.storage.sync.get.mockResolvedValue({ apiEndpoint: ENDPOINT, apiKey: API_KEY });

        const event = { preventDefault: vi.fn() };
        await handleSubmit(event);

        expect(event.preventDefault).toHaveBeenCalled();
        expect(chrome.notifications.create).toHaveBeenCalled();
    });

    it('shows error message when no category is selected', async () => {
        document.querySelector('.category-checkbox').checked = false;
        const event = { preventDefault: vi.fn() };
        await handleSubmit(event);

        expect(document.getElementById('message').className).toContain('error');
    });

    it('shows an "already saved" notification and closes the popup on a 409 response', async () => {
        chrome.storage.sync.get.mockResolvedValue({ apiEndpoint: ENDPOINT, apiKey: API_KEY });
        server.use(http.post(`${ENDPOINT}/add-link`, () =>
            HttpResponse.json({ message: 'Already saved' }, { status: 409 })
        ));

        const event = { preventDefault: vi.fn() };
        await handleSubmit(event);

        expect(chrome.notifications.create).toHaveBeenCalledWith(
            expect.objectContaining({ message: 'Already saved' })
        );
        expect(window.close).toHaveBeenCalled();
    });

    it('shows an error message when the API returns a non-ok, non-409 response', async () => {
        chrome.storage.sync.get.mockResolvedValue({ apiEndpoint: ENDPOINT, apiKey: API_KEY });
        server.use(http.post(`${ENDPOINT}/add-link`, () =>
            HttpResponse.json({ message: 'Server exploded' }, { status: 500 })
        ));

        const event = { preventDefault: vi.fn() };
        await handleSubmit(event);

        const messageEl = document.getElementById('message');
        expect(messageEl.className).toContain('error');
        expect(messageEl.textContent).toBe('Server exploded');
    });

    it('shows an error message when the request itself throws', async () => {
        chrome.storage.sync.get.mockResolvedValue({ apiEndpoint: ENDPOINT, apiKey: API_KEY });
        server.use(http.post(`${ENDPOINT}/add-link`, () => HttpResponse.error()));

        const event = { preventDefault: vi.fn() };
        await handleSubmit(event);

        expect(document.getElementById('message').className).toContain('error');
    });
});

describe('showMessage', () => {
    beforeEach(buildPopupDOM);

    it('sets the text and class immediately, then removes "show" after 5 seconds', () => {
        vi.useFakeTimers();
        try {
            showMessage('Saved!', 'success');

            const messageEl = document.getElementById('message');
            expect(messageEl.textContent).toBe('Saved!');
            expect(messageEl.className).toBe('message success show');

            vi.advanceTimersByTime(5000);
            expect(messageEl.className).not.toContain('show');
        } finally {
            vi.useRealTimers();
        }
    });
});

describe('openSettings', () => {
    it('opens the extension options page', () => {
        openSettings();
        expect(chrome.runtime.openOptionsPage).toHaveBeenCalled();
    });
});

describe('initPopup', () => {
    beforeEach(buildPopupDOM);

    it('initializes Tagify, hides the settings button, and loads page info + categories when configured', async () => {
        chrome.storage.sync.get.mockResolvedValue({ apiEndpoint: ENDPOINT, apiKey: API_KEY });
        chrome.storage.local.get.mockResolvedValue({});
        chrome.tabs.query.mockResolvedValue([{ id: 1, title: 'My Page', url: 'https://example.com/page' }]);

        await initPopup();
        await vi.waitFor(() => expect(document.querySelector('.category-label')).not.toBeNull());

        expect(Tagify).toHaveBeenCalled();
        expect(document.getElementById('settingsBtn').style.display).toBe('none');
        expect(document.getElementById('title').value).toBe('My Page');
        expect(document.querySelector('.category-label').textContent).toBe('Tech');
    });

    it('does not load page info or categories when settings are missing', async () => {
        chrome.storage.sync.get.mockResolvedValue({});

        await initPopup();

        expect(chrome.tabs.query).not.toHaveBeenCalled();
        expect(document.getElementById('setupMessage').style.display).toBe('block');
    });
});

describe('extractPageDescription', () => {
    it('returns og:description content when present', () => {
        document.head.innerHTML = '<meta property="og:description" content="OG desc">';
        expect(extractPageDescription()).toBe('OG desc');
    });

    it('returns plain description when og:description is absent', () => {
        document.head.innerHTML = '<meta name="description" content="Plain desc">';
        expect(extractPageDescription()).toBe('Plain desc');
    });

    it('returns empty string when no meta description exists', () => {
        document.head.innerHTML = '';
        expect(extractPageDescription()).toBe('');
    });
});
