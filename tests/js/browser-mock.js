import { vi, afterEach } from 'vitest';
import enMessages from '../../firefox-extension/_locales/en/messages.json';

function getMessage(key, substitutions) {
    const entry = enMessages[key];
    if (!entry) return '';
    let msg = entry.message;
    if (substitutions && entry.placeholders) {
        for (const [name, ph] of Object.entries(entry.placeholders)) {
            const idx = parseInt(ph.content.replace('$', '')) - 1;
            if (substitutions[idx] !== undefined) {
                msg = msg.replace(`$${name.toUpperCase()}$`, substitutions[idx]);
            }
        }
    }
    return msg;
}

global.browser = {
    storage: {
        sync: {
            get: vi.fn().mockResolvedValue({}),
            set: vi.fn().mockResolvedValue(undefined),
        },
        local: {
            get: vi.fn().mockResolvedValue({}),
            set: vi.fn().mockResolvedValue(undefined),
        },
        onChanged: { addListener: vi.fn() },
    },
    runtime: {
        onStartup:   { addListener: vi.fn() },
        onInstalled: { addListener: vi.fn() },
        onMessage:   { addListener: vi.fn() },
        openOptionsPage: vi.fn(),
        sendMessage: vi.fn(),
    },
    menus: {
        create:    vi.fn(),
        onClicked: { addListener: vi.fn() },
    },
    tabs: {
        create: vi.fn(),
        query:  vi.fn().mockResolvedValue([]),
    },
    scripting: {
        executeScript: vi.fn().mockResolvedValue([{ result: '' }]),
    },
    notifications: {
        create: vi.fn(),
    },
    cookies: {
        getAll: vi.fn().mockResolvedValue([]),
    },
    i18n: {
        getMessage: vi.fn(getMessage),
    },
};

global.Tagify = global.Tagify || vi.fn(() => ({ value: [] }));

// window.close() is already stubbed by chrome-mock.js's beforeEach, which
// runs for every test file regardless of which extension it targets.

afterEach(() => {
    vi.clearAllMocks();
    // Restore default resolved values after clearing
    browser.storage.sync.get.mockResolvedValue({});
    browser.storage.sync.set.mockResolvedValue(undefined);
    browser.storage.local.get.mockResolvedValue({});
    browser.storage.local.set.mockResolvedValue(undefined);
    browser.tabs.query.mockResolvedValue([]);
    browser.scripting.executeScript.mockResolvedValue([{ result: '' }]);
    browser.cookies.getAll.mockResolvedValue([]);
    browser.i18n.getMessage.mockImplementation(getMessage);
});
