import { describe, it, expect, vi, beforeEach } from 'vitest';
import {
    checkSettings,
    renderCategories,
    loadCategories,
    handleSubmit,
    extractPageDescription,
} from '../../firefox-extension/popup.js';

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
        browser.storage.sync.get.mockResolvedValue({});

        const result = await checkSettings();

        expect(result).toBeNull();
        expect(document.getElementById('setupMessage').style.display).toBe('block');
        expect(document.getElementById('mainForm').style.display).toBe('none');
    });

    it('returns the settings object and shows main form when credentials are present', async () => {
        browser.storage.sync.get.mockResolvedValue({ apiEndpoint: ENDPOINT, apiKey: API_KEY });

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
        const cats = [{ id: 1, name: 'Tech' }];
        browser.storage.local.get.mockResolvedValue({});
        global.fetch = vi.fn().mockResolvedValue({
            ok:   true,
            json: async () => cats,
        });

        await loadCategories(settings);

        expect(fetch).toHaveBeenCalledWith(`${ENDPOINT}/categories`, expect.any(Object));
        expect(document.querySelector('.category-label').textContent).toBe('Tech');
    });

    it('renders cached categories immediately before fetching', async () => {
        const cached = [{ id: 2, name: 'Cached' }];
        browser.storage.local.get.mockResolvedValue({ categories: cached });
        global.fetch = vi.fn().mockResolvedValue({
            ok:   true,
            json: async () => [],
        });

        await loadCategories(settings);

        expect(browser.storage.local.get).toHaveBeenCalled();
    });

    it('shows error message when fetch fails and no cache exists', async () => {
        browser.storage.local.get.mockResolvedValue({});
        global.fetch = vi.fn().mockRejectedValue(new Error('network'));

        await loadCategories(settings);

        expect(document.getElementById('categoriesList').innerHTML).toContain('Failed to load categories');
    });
});

describe('handleSubmit', () => {
    beforeEach(buildPopupDOM);

    it('posts form data to the API and notifies via runtime.sendMessage on success', async () => {
        browser.storage.sync.get.mockResolvedValue({ apiEndpoint: ENDPOINT, apiKey: API_KEY });
        global.fetch = vi.fn().mockResolvedValue({
            ok:     true,
            status: 200,
            json:   async () => ({ id: 1 }),
        });

        const event = { preventDefault: vi.fn() };
        await handleSubmit(event);

        expect(event.preventDefault).toHaveBeenCalled();
        expect(fetch).toHaveBeenCalledWith(
            `${ENDPOINT}/add-link`,
            expect.objectContaining({ method: 'POST' })
        );
        expect(browser.runtime.sendMessage).toHaveBeenCalledWith(
            expect.objectContaining({ type: 'notify' })
        );
    });

    it('notifies about an already-saved link via runtime.sendMessage on 409', async () => {
        browser.storage.sync.get.mockResolvedValue({ apiEndpoint: ENDPOINT, apiKey: API_KEY });
        global.fetch = vi.fn().mockResolvedValue({
            ok:     false,
            status: 409,
            json:   async () => ({ message: 'Already saved' }),
        });

        const event = { preventDefault: vi.fn() };
        await handleSubmit(event);

        expect(browser.runtime.sendMessage).toHaveBeenCalledWith(
            expect.objectContaining({ type: 'notify', message: 'Already saved' })
        );
    });

    it('shows error message when no category is selected', async () => {
        document.querySelector('.category-checkbox').checked = false;
        const event = { preventDefault: vi.fn() };
        await handleSubmit(event);

        expect(document.getElementById('message').className).toContain('error');
        expect(fetch).not.toHaveBeenCalled();
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
