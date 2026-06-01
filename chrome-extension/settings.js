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
                'X-LinkDigest-API-Key': apiKey
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

    // Verify this is a WordPress installation
    let resolvedOrigin;
    try {
        const wpBase = url.replace(/\/$/, '');
        const res = await fetch(`${wpBase}/wp-json/`, { method: 'GET' });
        const data = await res.json();
        if (!res.ok || !Array.isArray(data.namespaces)) throw new Error('not wp');
        // Use the final URL after redirects (e.g. http→https) so cookie lookup hits the right origin
        resolvedOrigin = new URL(res.url).origin;
    } catch {
        status.textContent = chrome.i18n.getMessage('msgNoWp');
        status.className = 'wp-login-status logged-out';
        return;
    }

    try {
        // Use the resolved origin so Secure cookies are included
        const cookieUrl = resolvedOrigin;
        console.log('[linkdigest] input url:', url);
        console.log('[linkdigest] resolved origin:', resolvedOrigin);
        const cookies = await chrome.cookies.getAll({ url: cookieUrl });
        console.log('[linkdigest] cookies for origin:', cookies.map(c => c.name));
        const loggedIn = cookies.some(c =>
            c.name.startsWith('wordpress_logged_in_') ||
            c.name.startsWith('wordpress_sec_')
        );
        console.log('[linkdigest] loggedIn:', loggedIn);

        if (!loggedIn) {
            status.textContent = chrome.i18n.getMessage('msgNotLoggedIn');
            status.className = 'wp-login-status logged-out';
            return;
        }

        const wpBase = url.replace(/\/$/, '');

        // Get a WP REST nonce via admin-ajax
        status.textContent = chrome.i18n.getMessage('msgFetchingNonce');
        const nonceRes = await fetch(`${wpBase}/wp-admin/admin-ajax.php`, {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=linkdigest_get_rest_nonce',
        });
        const nonceData = await nonceRes.json();
        if (!nonceData.success) throw new Error(`nonce: ${nonceRes.status}`);

        // Fetch the API key using the nonce
        status.textContent = chrome.i18n.getMessage('msgFetchingKey');
        const endpoint = document.getElementById('apiEndpoint').value.trim()
            || `${wpBase}/wp-json/linkdigest/v1`;
        const keyRes = await fetch(`${endpoint}/api-key`, {
            credentials: 'include',
            headers: { 'X-WP-Nonce': nonceData.data.nonce },
        });
        const keyData = await keyRes.json();
        if (!keyRes.ok || !keyData.key) throw new Error(`key: ${keyRes.status}`);

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
            document.getElementById('apiEndpoint').value = `${wp}/wp-json/linkdigest/v1`;
        }
    });
});
