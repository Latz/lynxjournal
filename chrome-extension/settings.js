import { applyI18n } from './i18n.js';

// Load saved settings
export async function loadSettings() {
    const settings = await chrome.storage.sync.get(['apiEndpoint', 'apiKey']);

    if (settings.apiEndpoint) {
        document.getElementById('apiEndpoint').value = settings.apiEndpoint;
    }

    if (settings.apiKey) {
        document.getElementById('apiKey').value = settings.apiKey;
    }
}

// Show message
export function showMessage(text, type) {
    const messageEl = document.getElementById('message');
    messageEl.textContent = text;
    messageEl.className = `message ${type}`;
    messageEl.style.display = 'block';

    setTimeout(() => {
        messageEl.style.display = 'none';
    }, 5000);
}

// Test API connection — returns categories array on success, null on failure
export async function testConnection(apiEndpoint, apiKey) {
    try {
        const response = await fetch(`${apiEndpoint}/categories`, {
            method: 'GET',
            cache: 'no-store',
            headers: {
                'Content-Type': 'application/json',
                'X-LynxJournal-API-Key': apiKey
            }
        });

        if (!response.ok) return null;
        return await response.json();
    } catch {
        return null;
    }
}

// Handle form submission
export async function handleSubmit(e) {
    e.preventDefault();

    const apiEndpoint = document.getElementById('apiEndpoint').value.trim();
    const apiKey = document.getElementById('apiKey').value.trim();

    // Remove trailing slash from endpoint if present
    const cleanEndpoint = apiEndpoint.replace(/\/$/, '');

    // Test connection and fetch categories in one request
    const categories = await testConnection(cleanEndpoint, apiKey);

    if (!categories) {
        showMessage(chrome.i18n.getMessage('msgConnectionFailed'), 'error');
        return;
    }

    // Save settings and pre-warm category cache
    try {
        await chrome.storage.sync.set({
            apiEndpoint: cleanEndpoint,
            apiKey: apiKey
        });

        if (Array.isArray(categories)) {
            await chrome.storage.local.set({ categories, categoriesTimestamp: Date.now() });
        }

        showMessage(chrome.i18n.getMessage('msgSettingsSaved'), 'success');
    } catch {
        showMessage(chrome.i18n.getMessage('msgSettingsFailed'), 'error');
    }
}

export async function checkWpLogin(url) {
    const status = document.getElementById('wpLoginStatus');
    if (!url) { status.style.display = 'none'; return; }

    status.textContent = chrome.i18n.getMessage('msgChecking');
    status.className = 'wp-login-status';
    status.style.display = 'block';

    // Verify this is a WordPress installation.
    // Try the entered URL first, then fall back to the site origin so that
    // entering any page URL (e.g. https://example.com/lynxjournal) still works.
    const candidates = [url.replace(/\/$/, '')];
    try {
        const origin = new URL(url).origin;
        if (origin !== candidates[0]) candidates.push(origin);
    } catch { /* invalid URL — proceed with single candidate */ }

    let wpBase = null;
    let resolvedOrigin = null;
    for (const base of candidates) {
        try {
            const res = await fetch(`${base}/wp-json/`, { method: 'GET' });
            const data = await res.json();
            if (res.ok && Array.isArray(data.namespaces)) {
                wpBase = base;
                resolvedOrigin = new URL(res.url).origin;
                break;
            }
        } catch { /* try next candidate */ }
    }

    if (!wpBase) {
        status.textContent = chrome.i18n.getMessage('msgNoWp');
        status.className = 'wp-login-status logged-out';
        return;
    }

    // If WordPress was found at a different base (fallback to origin),
    // fill the API Endpoint directly instead of overwriting the address field.
    if (wpBase !== url.replace(/\/$/, '')) {
        document.getElementById('apiEndpoint').value = `${wpBase}/wp-json/lynxjournal/v1`;
        status.textContent = chrome.i18n.getMessage('msgUrlCorrected');
        status.className = 'wp-login-status logged-in';
    }

    try {
        // Use the resolved origin so Secure cookies are included
        const cookieUrl = resolvedOrigin;
        const cookies = await chrome.cookies.getAll({ url: cookieUrl });
        const loggedIn = cookies.some(c =>
            c.name.startsWith('wordpress_logged_in_') ||
            c.name.startsWith('wordpress_sec_')
        );

        if (!loggedIn) {
            status.textContent = chrome.i18n.getMessage('msgNotLoggedIn');
            status.className = 'wp-login-status logged-out';
            return;
        }

        // wpBase is already resolved above

        // Get a WP REST nonce via the plugin's REST endpoint
        status.textContent = chrome.i18n.getMessage('msgFetchingNonce');
        const nonceRes = await fetch(`${wpBase}/wp-json/lynxjournal/v1/nonce`, {
            method: 'GET',
            credentials: 'include',
        });
        const nonceText = await nonceRes.text();
        let nonceData;
        try { nonceData = JSON.parse(nonceText); }
        catch { throw new Error(`nonce: HTTP ${nonceRes.status} — non-JSON response`); }
        if (!nonceRes.ok) throw new Error(`nonce: HTTP ${nonceRes.status}`);

        // Fetch the API key using the nonce
        status.textContent = chrome.i18n.getMessage('msgFetchingKey');
        const endpoint = document.getElementById('apiEndpoint').value.trim()
            || `${wpBase}/wp-json/lynxjournal/v1`;
        const keyRes = await fetch(`${endpoint}/api-key`, {
            credentials: 'include',
            headers: { 'X-WP-Nonce': nonceData.nonce },
        });
        const keyText = await keyRes.text();
        let keyData;
        try { keyData = JSON.parse(keyText); }
        catch { throw new Error(`api-key: HTTP ${keyRes.status} — non-JSON response`); }
        if (!keyRes.ok || !keyData.key) throw new Error(`api-key: HTTP ${keyRes.status}`);

        document.getElementById('apiKey').value = keyData.key;
        status.textContent = chrome.i18n.getMessage('msgKeyFilled');
    } catch (err) {
        status.textContent = chrome.i18n.getMessage('msgAutoFetchFailed', [err.message]);
        status.className = 'wp-login-status logged-in';
        status.style.display = 'block';
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    applyI18n();
    loadSettings();
    document.getElementById('settingsForm').addEventListener('submit', handleSubmit);

    const wpInput = document.getElementById('wpAddress');

    wpInput.addEventListener('change', () => checkWpLogin(wpInput.value.trim()));

    document.getElementById('createEndpointBtn').addEventListener('click', () => {
        const wp = wpInput.value.trim().replace(/\/$/, '');
        if (wp) {
            document.getElementById('apiEndpoint').value = `${wp}/wp-json/lynxjournal/v1`;
        }
    });
});
