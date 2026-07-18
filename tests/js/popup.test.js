import { describe, it, expect, vi, beforeEach } from 'vitest';
import { http, HttpResponse } from 'msw';
import { server } from './server.js';
import {
    checkSettings,
    renderCategories,
    loadCategories,
    handleSubmit,
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
