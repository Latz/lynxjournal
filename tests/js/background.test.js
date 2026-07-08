import { describe, it, expect, vi } from 'vitest';
import { http, HttpResponse } from 'msw';
import { server } from './server.js';
import { refreshCategories, handleContextMenuClick } from '../../chrome-extension/background.js';

const ENDPOINT = 'https://example.com/wp-json/lynxjournal/v1';
const API_KEY  = 'test-key';

describe('refreshCategories', () => {
    it('fetches categories and stores them when credentials are set', async () => {
        chrome.storage.sync.get.mockResolvedValue({ apiEndpoint: ENDPOINT, apiKey: API_KEY });

        await refreshCategories();

        expect(chrome.storage.local.set).toHaveBeenCalledWith(
            expect.objectContaining({ categories: [{ id: 1, name: 'Tech' }] })
        );
    });

    it('returns early without fetching when credentials are missing', async () => {
        chrome.storage.sync.get.mockResolvedValue({});

        await refreshCategories();

        expect(chrome.storage.local.set).not.toHaveBeenCalled();
    });

    it('fails silently when fetch throws', async () => {
        chrome.storage.sync.get.mockResolvedValue({ apiEndpoint: ENDPOINT, apiKey: API_KEY });
        server.use(http.get(`${ENDPOINT}/categories`, () => HttpResponse.error()));

        await expect(refreshCategories()).resolves.toBeUndefined();
        expect(chrome.storage.local.set).not.toHaveBeenCalled();
    });

    it('does not store categories when response is not ok', async () => {
        chrome.storage.sync.get.mockResolvedValue({ apiEndpoint: ENDPOINT, apiKey: API_KEY });
        server.use(http.get(`${ENDPOINT}/categories`, () => new HttpResponse(null, { status: 500 })));

        await refreshCategories();

        expect(chrome.storage.local.set).not.toHaveBeenCalled();
    });
});

describe('handleContextMenuClick', () => {
    it('opens admin tab with correct URL when apiEndpoint is set', async () => {
        chrome.storage.sync.get.mockResolvedValue({ apiEndpoint: ENDPOINT });

        await handleContextMenuClick({ menuItemId: 'lynxjournal-admin' });

        expect(chrome.tabs.create).toHaveBeenCalledWith({
            url: 'https://example.com/wp-admin/admin.php?page=lynxjournal-dashboard',
        });
    });

    it('opens options page when apiEndpoint is not set', async () => {
        chrome.storage.sync.get.mockResolvedValue({ apiEndpoint: undefined });

        await handleContextMenuClick({ menuItemId: 'lynxjournal-admin' });

        expect(chrome.runtime.openOptionsPage).toHaveBeenCalled();
        expect(chrome.tabs.create).not.toHaveBeenCalled();
    });

    it('calls refreshCategories on refresh menu item click', async () => {
        chrome.storage.sync.get.mockResolvedValue({ apiEndpoint: ENDPOINT, apiKey: API_KEY });

        await handleContextMenuClick({ menuItemId: 'lynxjournal-refresh-categories' });

        // refreshCategories is called fire-and-forget; wait for the async side effect
        await vi.waitFor(() => expect(chrome.storage.local.set).toHaveBeenCalled());
        expect(chrome.tabs.create).not.toHaveBeenCalled();
    });

    it('ignores unknown menu item IDs', async () => {
        await handleContextMenuClick({ menuItemId: 'unknown-menu' });

        expect(chrome.tabs.create).not.toHaveBeenCalled();
        expect(chrome.runtime.openOptionsPage).not.toHaveBeenCalled();
    });
});
