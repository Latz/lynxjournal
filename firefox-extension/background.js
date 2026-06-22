/**
 * LynxJournal Firefox Extension — background script.
 *
 * @since 1.0.0
 */

const MENU_ID = 'lynxjournal-admin';
const MENU_ID_REFRESH = 'lynxjournal-refresh-categories';

/**
 * Fetch and cache categories from the LynxJournal API.
 *
 * @since 1.0.0
 * @async
 * @returns {Promise<void>}
 */
async function refreshCategories() {
    const { apiEndpoint, apiKey } = await browser.storage.sync.get(['apiEndpoint', 'apiKey']);
    if (!apiEndpoint || !apiKey) return;

    try {
        const response = await fetch(`${apiEndpoint}/categories`, {
            method: 'GET',
            cache: 'no-store',
            headers: {
                'Content-Type': 'application/json',
                'X-LynxJournal-API-Key': apiKey
            }
        });
        if (!response.ok) return;
        const categories = await response.json();
        await browser.storage.local.set({ categories, categoriesTimestamp: Date.now() });
    } catch {
        // Fail silently — popup will fetch on next open if cache is missing
    }
}

browser.runtime.onStartup.addListener(() => refreshCategories());

browser.storage.onChanged.addListener((changes, area) => {
    if (area === 'sync' && (changes.apiEndpoint || changes.apiKey)) {
        refreshCategories();
    }
});

browser.runtime.onInstalled.addListener(() => {
    browser.menus.create({
        id: MENU_ID,
        title: browser.i18n.getMessage('menuOpenAdmin'),
        contexts: ['action']
    });
    browser.menus.create({
        id: MENU_ID_REFRESH,
        title: browser.i18n.getMessage('menuUpdateCategories'),
        contexts: ['action']
    });
    refreshCategories();
});

/**
 * Handle context menu clicks from the extension.
 *
 * @since 1.0.0
 * @async
 * @param {browser.menus.OnClickData} info - Context menu click info.
 * @returns {Promise<void>}
 */
async function handleContextMenuClick(info) {
    if (info.menuItemId === MENU_ID_REFRESH) {
        refreshCategories();
        return;
    }

    if (info.menuItemId !== MENU_ID) return;

    const { apiEndpoint } = await browser.storage.sync.get('apiEndpoint');

    if (!apiEndpoint) {
        browser.runtime.openOptionsPage();
        return;
    }

    const wpBase = apiEndpoint.split('/wp-json/')[0];
    browser.tabs.create({ url: `${wpBase}/wp-admin/admin.php?page=lynxjournal-dashboard` });
}

browser.menus.onClicked.addListener(handleContextMenuClick);

browser.runtime.onMessage.addListener((msg) => {
    if (msg.type === 'notify') {
        browser.notifications.create({
            type: 'basic',
            iconUrl: 'icons/icon48.png',
            title: msg.title,
            message: msg.message
        });
    }
});
