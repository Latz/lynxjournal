import { vi, afterEach } from 'vitest';
import enMessages from '../../chrome-extension/_locales/en/messages.json';

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

global.chrome = {
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
        openOptionsPage: vi.fn(),
    },
    contextMenus: {
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

global.Tagify = vi.fn(() => ({ value: [] }));

afterEach(() => {
    vi.clearAllMocks();
    // Restore default resolved values after clearing
    chrome.storage.sync.get.mockResolvedValue({});
    chrome.storage.sync.set.mockResolvedValue(undefined);
    chrome.storage.local.get.mockResolvedValue({});
    chrome.storage.local.set.mockResolvedValue(undefined);
    chrome.tabs.query.mockResolvedValue([]);
    chrome.scripting.executeScript.mockResolvedValue([{ result: '' }]);
    chrome.cookies.getAll.mockResolvedValue([]);
    chrome.i18n.getMessage.mockImplementation(getMessage);
});
