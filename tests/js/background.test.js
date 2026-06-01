import { describe, it, expect, vi, beforeEach } from 'vitest';
import { refreshCategories, handleContextMenuClick } from '../../chrome-extension/background.js';

const ENDPOINT = 'https://example.com/wp-json/linkdigest/v1';
const API_KEY  = 'test-key';

describe('refreshCategories', () => {
    it('fetches categories and stores them when credentials are set', async () => {
        const categories = [{ id: 1, name: 'Tech' }];
        chrome.storage.sync.get.mockResolvedValue({ apiEndpoint: ENDPOINT, apiKey: API_KEY });
        global.fetch = vi.fn().mockResolvedValue({
            ok:   true,
            json: async () => categories,
        });

        await refreshCategories();

        expect(fetch).toHaveBeenCalledWith(`${ENDPOINT}/categories`, expect.objectContaining({ method: 'GET' }));
        expect(chrome.storage.local.set).toHaveBeenCalledWith(
            expect.objectContaining({ categories })
        );
    });

    it('returns early without fetching when credentials are missing', async () => {
        chrome.storage.sync.get.mockResolvedValue({});
        global.fetch = vi.fn();

        await refreshCategories();

        expect(fetch).not.toHaveBeenCalled();
    });

    it('fails silently when fetch throws', async () => {
        chrome.storage.sync.get.mockResolvedValue({ apiEndpoint: ENDPOINT, apiKey: API_KEY });
        global.fetch = vi.fn().mockRejectedValue(new Error('network error'));

        await expect(refreshCategories()).resolves.toBeUndefined();
        expect(chrome.storage.local.set).not.toHaveBeenCalled();
    });

    it('does not store categories when response is not ok', async () => {
        chrome.storage.sync.get.mockResolvedValue({ apiEndpoint: ENDPOINT, apiKey: API_KEY });
        global.fetch = vi.fn().mockResolvedValue({ ok: false });

        await refreshCategories();

        expect(chrome.storage.local.set).not.toHaveBeenCalled();
    });
});

describe('handleContextMenuClick', () => {
    it('opens admin tab with correct URL when apiEndpoint is set', async () => {
        chrome.storage.sync.get.mockResolvedValue({ apiEndpoint: ENDPOINT });

        await handleContextMenuClick({ menuItemId: 'linkdigest-admin' });

        expect(chrome.tabs.create).toHaveBeenCalledWith({
            url: 'https://example.com/wp-admin/admin.php?page=linkdigest-dashboard',
        });
    });

    it('opens options page when apiEndpoint is not set', async () => {
        chrome.storage.sync.get.mockResolvedValue({ apiEndpoint: undefined });

        await handleContextMenuClick({ menuItemId: 'linkdigest-admin' });

        expect(chrome.runtime.openOptionsPage).toHaveBeenCalled();
        expect(chrome.tabs.create).not.toHaveBeenCalled();
    });

    it('calls refreshCategories on refresh menu item click', async () => {
        chrome.storage.sync.get.mockResolvedValue({ apiEndpoint: ENDPOINT, apiKey: API_KEY });
        global.fetch = vi.fn().mockResolvedValue({ ok: true, json: async () => [] });

        await handleContextMenuClick({ menuItemId: 'linkdigest-refresh-categories' });

        expect(fetch).toHaveBeenCalled();
        expect(chrome.tabs.create).not.toHaveBeenCalled();
    });

    it('ignores unknown menu item IDs', async () => {
        await handleContextMenuClick({ menuItemId: 'unknown-menu' });

        expect(chrome.tabs.create).not.toHaveBeenCalled();
        expect(chrome.runtime.openOptionsPage).not.toHaveBeenCalled();
    });
});
