import { describe, it, expect, vi } from 'vitest';
import { refreshCategories, handleContextMenuClick } from '../../firefox-extension/background.js';

const ENDPOINT = 'https://example.com/wp-json/lynxjournal/v1';
const API_KEY  = 'test-key';

// Captured at module-load time, before any test clears the mock call history —
// background.js registers this listener once, as soon as it's imported.
const notifyListener = browser.runtime.onMessage.addListener.mock.calls.at(-1)[0];

describe('refreshCategories', () => {
    it('fetches categories and stores them when credentials are set', async () => {
        const categories = [{ id: 1, name: 'Tech' }];
        browser.storage.sync.get.mockResolvedValue({ apiEndpoint: ENDPOINT, apiKey: API_KEY });
        global.fetch = vi.fn().mockResolvedValue({
            ok:   true,
            json: async () => categories,
        });

        await refreshCategories();

        expect(fetch).toHaveBeenCalledWith(`${ENDPOINT}/categories`, expect.objectContaining({ method: 'GET' }));
        expect(browser.storage.local.set).toHaveBeenCalledWith(
            expect.objectContaining({ categories })
        );
    });

    it('returns early without fetching when credentials are missing', async () => {
        browser.storage.sync.get.mockResolvedValue({});
        global.fetch = vi.fn();

        await refreshCategories();

        expect(fetch).not.toHaveBeenCalled();
    });

    it('fails silently when fetch throws', async () => {
        browser.storage.sync.get.mockResolvedValue({ apiEndpoint: ENDPOINT, apiKey: API_KEY });
        global.fetch = vi.fn().mockRejectedValue(new Error('network error'));

        await expect(refreshCategories()).resolves.toBeUndefined();
        expect(browser.storage.local.set).not.toHaveBeenCalled();
    });

    it('does not store categories when response is not ok', async () => {
        browser.storage.sync.get.mockResolvedValue({ apiEndpoint: ENDPOINT, apiKey: API_KEY });
        global.fetch = vi.fn().mockResolvedValue({ ok: false });

        await refreshCategories();

        expect(browser.storage.local.set).not.toHaveBeenCalled();
    });
});

describe('handleContextMenuClick', () => {
    it('opens admin tab with correct URL when apiEndpoint is set', async () => {
        browser.storage.sync.get.mockResolvedValue({ apiEndpoint: ENDPOINT });

        await handleContextMenuClick({ menuItemId: 'lynxjournal-admin' });

        expect(browser.tabs.create).toHaveBeenCalledWith({
            url: 'https://example.com/wp-admin/admin.php?page=lynxjournal-dashboard',
        });
    });

    it('opens options page when apiEndpoint is not set', async () => {
        browser.storage.sync.get.mockResolvedValue({ apiEndpoint: undefined });

        await handleContextMenuClick({ menuItemId: 'lynxjournal-admin' });

        expect(browser.runtime.openOptionsPage).toHaveBeenCalled();
        expect(browser.tabs.create).not.toHaveBeenCalled();
    });

    it('calls refreshCategories on refresh menu item click', async () => {
        browser.storage.sync.get.mockResolvedValue({ apiEndpoint: ENDPOINT, apiKey: API_KEY });
        global.fetch = vi.fn().mockResolvedValue({ ok: true, json: async () => [] });

        await handleContextMenuClick({ menuItemId: 'lynxjournal-refresh-categories' });

        expect(fetch).toHaveBeenCalled();
        expect(browser.tabs.create).not.toHaveBeenCalled();
    });

    it('ignores unknown menu item IDs', async () => {
        await handleContextMenuClick({ menuItemId: 'unknown-menu' });

        expect(browser.tabs.create).not.toHaveBeenCalled();
        expect(browser.runtime.openOptionsPage).not.toHaveBeenCalled();
    });
});

describe('runtime.onMessage listener', () => {
    it('creates a notification when a notify message is received', () => {
        notifyListener({ type: 'notify', title: 'Saved', message: 'Link saved' });

        expect(browser.notifications.create).toHaveBeenCalledWith(
            expect.objectContaining({ title: 'Saved', message: 'Link saved' })
        );
    });
});
