import { applyI18n } from './i18n.js';
import { renderCategories as renderCategoriesUtil } from './popup-utils.js';

// Tagify instance
let tagify;

// Check if settings are configured. Returns the settings object when
// configured (so callers can reuse it instead of re-reading storage), or
// null when setup is incomplete.
export async function checkSettings() {
    const settings = await browser.storage.sync.get(['apiEndpoint', 'apiKey']);

    if (!settings.apiEndpoint || !settings.apiKey) {
        document.getElementById('setupMessage').style.display = 'block';
        document.getElementById('mainForm').style.display = 'none';
        return null;
    }

    document.getElementById('setupMessage').style.display = 'none';
    document.getElementById('mainForm').style.display = 'block';
    return settings;
}

// Extract description from page meta tags (runs inside the tab's context).
// Must be self-contained — no references to extension-scope imports,
// since this function is serialized and injected into the target tab.
export function extractPageDescription() {
    const candidates = [
        ['property', 'og:description'],
        ['name',     'description'],
        ['name',     'twitter:description'],
        ['name',     'og:description'],
        ['http-equiv', 'description'],
    ];
    for (const [attr, value] of candidates) {
        const nodes = document.querySelectorAll(`[${attr}="${value}" i]`);
        for (const node of nodes) {
            let text = (node.content || '').trim();
            while (text.startsWith('\n')) text = text.slice(1);
            while (text.endsWith('\n')) text = text.slice(0, -1);
            if (text) return text;
        }
    }
    return '';
}

// Load current page info
export async function loadPageInfo() {
    try {
        const [tab] = await browser.tabs.query({ active: true, currentWindow: true });

        if (tab) {
            document.getElementById('title').value = tab.title || '';
            document.getElementById('url').value = tab.url || '';

            // Fill description from page meta tags
            try {
                const [{ result }] = await browser.scripting.executeScript({
                    target: { tabId: tab.id },
                    func: extractPageDescription,
                });
                if (result) {
                    document.getElementById('content').value = result;
                }
            } catch {
                // Silently skip description extraction on restricted pages
            }
        }
    } catch {
        // Fail silently — page info is best-effort
    }
}

// Replace a container's content with a single ".loading" message, without
// using innerHTML (the message text is always a locale string, but building
// it via DOM APIs avoids web-ext lint's unsafe-innerHTML warning entirely).
function renderLoadingMessage(container, text) {
    const div = document.createElement('div');
    div.className = 'loading';
    div.textContent = text;
    container.replaceChildren(div);
}

// Render categories to DOM with sorting and i18n
export function renderCategories(categories) {
    const categoriesList = document.getElementById('categoriesList');

    if (!categories || categories.length === 0) {
        renderLoadingMessage(categoriesList, browser.i18n.getMessage('msgNoCategories'));
        return;
    }

    // Sort categories by name and use the shared render function
    const sorted = [...categories].sort((a, b) => a.name.localeCompare(b.name));
    renderCategoriesUtil(sorted, categoriesList);
}

// Load categories from WordPress
export async function loadCategories(settings) {
    const categoriesList = document.getElementById('categoriesList');

    // Show cached data immediately if available (optimistic)
    const cached = await browser.storage.local.get(['categories']);
    if (cached.categories) {
        renderCategories(cached.categories);
    }

    // Always fetch fresh
    try {
        const response = await fetch(`${settings.apiEndpoint}/categories`, {
            method: 'GET',
            cache: 'no-store',
            headers: {
                'Content-Type': 'application/json',
                'X-LynxJournal-API-Key': settings.apiKey
            }
        });
        if (response.status === 401 || response.status === 403 || response.status === 404) {
            document.getElementById('setupMessage').style.display = 'block';
            document.getElementById('mainForm').style.display = 'none';
            return;
        }
        if (!response.ok) throw new Error('Failed to load categories');
        const categories = await response.json();
        await browser.storage.local.set({ categories });
        renderCategories(categories);
    } catch {
        if (!cached.categories) {
            renderLoadingMessage(categoriesList, browser.i18n.getMessage('msgFailedCategories'));
        }
    }
}

// Show message
export function showMessage(text, type) {
    const messageEl = document.getElementById('message');
    messageEl.textContent = text;
    messageEl.className = `message ${type} show`;

    setTimeout(() => {
        messageEl.classList.remove('show');
    }, 5000);
}

// Handle form submission
export async function handleSubmit(e) {
    e.preventDefault();

    const saveBtn = document.getElementById('saveBtn');
    const btnText = saveBtn.querySelector('.btn-text');
    const btnLoading = saveBtn.querySelector('.btn-loading');

    saveBtn.disabled = true;
    btnText.style.display = 'none';
    btnLoading.style.display = 'inline';

    try {
        const settings = await browser.storage.sync.get(['apiEndpoint', 'apiKey']);

        // Get selected categories
        const selectedRadio = document.querySelector('.category-checkbox:checked');
        const selectedCategories = selectedRadio ? [selectedRadio.value] : [];

        if (selectedCategories.length === 0) {
            showMessage(browser.i18n.getMessage('msgSelectCategory'), 'error');
            saveBtn.disabled = false;
            btnText.style.display = 'inline';
            btnLoading.style.display = 'none';
            return;
        }

        // Get tags from Tagify
        const tags = tagify ? tagify.value.map(tag => tag.value).join(', ') : document.getElementById('tags').value;

        const formData = {
            title: document.getElementById('title').value,
            url: document.getElementById('url').value,
            content: document.getElementById('content').value,
            categories: selectedCategories,
            tags
        };

        const response = await fetch(`${settings.apiEndpoint}/add-link`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-LynxJournal-API-Key': settings.apiKey
            },
            body: JSON.stringify(formData)
        });

        const result = await response.json();

        if (response.status === 409) {
            browser.runtime.sendMessage({
                type: 'notify',
                title: browser.i18n.getMessage('notifAlreadySavedTitle'),
                message: result.message || browser.i18n.getMessage('notifAlreadySavedBody')
            });
            window.close();
            return;
        }

        if (!response.ok) {
            throw new Error(result.message || browser.i18n.getMessage('msgSaveFailed'));
        }

        browser.runtime.sendMessage({
            type: 'notify',
            title: browser.i18n.getMessage('notifLinkSavedTitle'),
            message: formData.title || browser.i18n.getMessage('notifLinkSavedBody')
        });
        window.close();

    } catch (error) {
        showMessage(error.message || browser.i18n.getMessage('msgSaveFailed'), 'error');
    } finally {
        saveBtn.disabled = false;
        btnText.style.display = 'inline';
        btnLoading.style.display = 'none';
    }
}

// Open settings page
export function openSettings() {
    browser.runtime.openOptionsPage();
}

// Wires up the popup: i18n, event listeners, settings check, Tagify, and
// the initial page-info/categories load. Runs once on DOMContentLoaded.
export async function initPopup() {
    applyI18n();

    // Add event listeners immediately
    document.getElementById('linkForm')?.addEventListener('submit', handleSubmit);
    document.getElementById('settingsBtn')?.addEventListener('click', openSettings);
    document.getElementById('openSettings')?.addEventListener('click', openSettings);

    // Check settings
    const settings = await checkSettings();

    if (settings) {
        // Hide settings button when connected
        const settingsBtn = document.getElementById('settingsBtn');
        if (settingsBtn) {
            settingsBtn.style.display = 'none';
        }

        // Initialize Tagify on tags input
        const tagsInput = document.getElementById('tags');
        if (tagsInput) {
            tagify = new Tagify(tagsInput, {
                delimiters: ',',
                trim: true,
                duplicates: false,
                addTagOnBlur: true,
                placeholder: browser.i18n.getMessage('placeholderAddTags'),
                dropdown: {
                    enabled: 0
                }
            });
        }

        // Load page info and categories in parallel (non-blocking)
        Promise.all([
            loadPageInfo(),
            loadCategories(settings)
        ]).catch(() => { /* individual loaders report their own errors */ });
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', initPopup);
