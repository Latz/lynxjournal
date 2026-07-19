import { vi, afterEach, beforeEach, beforeAll, afterAll } from 'vitest';
import enMessages from '../../chrome-extension/_locales/en/messages.json';
import { server } from './server.js';

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
            set: vi.fn().mockResolvedValue(),
        },
        local: {
            get: vi.fn().mockResolvedValue({}),
            set: vi.fn().mockResolvedValue(),
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

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }));
afterAll(() => server.close());

// Prevent window.close() from destroying the jsdom window between tests
beforeEach(() => {
    vi.spyOn(window, 'close').mockImplementation(() => {});
});

afterEach(() => {
    server.resetHandlers();
    vi.clearAllMocks();
    // Restore default resolved values after clearing
    chrome.storage.sync.get.mockResolvedValue({});
    chrome.storage.sync.set.mockResolvedValue();
    chrome.storage.local.get.mockResolvedValue({});
    chrome.storage.local.set.mockResolvedValue();
    chrome.tabs.query.mockResolvedValue([]);
    chrome.scripting.executeScript.mockResolvedValue([{ result: '' }]);
    chrome.cookies.getAll.mockResolvedValue([]);
    chrome.i18n.getMessage.mockImplementation(getMessage);
});
