import { applyI18n } from './i18n.js';
import { extractPageDescription as extractPageDescriptionUtil, renderCategories as renderCategoriesUtil } from '../src/js/popup-utils.js';

// Check if settings are configured
export async function checkSettings() {
    const settings = await chrome.storage.sync.get(['apiEndpoint', 'apiKey']);

    if (!settings.apiEndpoint || !settings.apiKey) {
        document.getElementById('setupMessage').style.display = 'block';
        document.getElementById('mainForm').style.display = 'none';
        return false;
    }

    document.getElementById('setupMessage').style.display = 'none';
    document.getElementById('mainForm').style.display = 'block';
    return true;
}

// Extract description from page meta tags (runs inside the tab's context)
export function extractPageDescription() {
    return extractPageDescriptionUtil(document);
}

// Load current page info
export async function loadPageInfo() {
    try {
        const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });

        if (tab) {
            document.getElementById('title').value = tab.title || '';
            document.getElementById('url').value = tab.url || '';

            // Fill description from page meta tags
            try {
                const [{ result }] = await chrome.scripting.executeScript({
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

// Render categories to DOM with sorting and i18n
export function renderCategories(categories) {
    const categoriesList = document.getElementById('categoriesList');

    if (!categories || categories.length === 0) {
        categoriesList.innerHTML = `<div class="loading">${chrome.i18n.getMessage('msgNoCategories')}</div>`;
        return;
    }

    // Sort categories by name and use the shared render function
    const sorted = [...categories].sort((a, b) => a.name.localeCompare(b.name));
    renderCategoriesUtil(sorted, categoriesList);
}

// Load categories from WordPress
export async function loadCategories() {
    const categoriesList = document.getElementById('categoriesList');

    // Show cached data immediately if available (optimistic)
    const cached = await chrome.storage.local.get(['categories']);
    if (cached.categories) {
        renderCategories(cached.categories);
    }

    // Always fetch fresh
    try {
        const settings = await chrome.storage.sync.get(['apiEndpoint', 'apiKey']);
        const response = await fetch(`${settings.apiEndpoint}/categories`, {
            method: 'GET',
            cache: 'no-store',
            headers: {
                'Content-Type': 'application/json',
                'X-LinkDigest-API-Key': settings.apiKey
            }
        });
        if (!response.ok) throw new Error('Failed to load categories');
        const categories = await response.json();
        await chrome.storage.local.set({ categories, categoriesTimestamp: Date.now() });
        renderCategories(categories);
    } catch {
        if (!cached.categories) {
            categoriesList.innerHTML = `<div class="loading">${chrome.i18n.getMessage('msgFailedCategories')}</div>`;
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
        const settings = await chrome.storage.sync.get(['apiEndpoint', 'apiKey']);

        // Get selected categories
        const selectedRadio = document.querySelector('.category-checkbox:checked');
        const selectedCategories = selectedRadio ? [selectedRadio.value] : [];

        if (selectedCategories.length === 0) {
            showMessage(chrome.i18n.getMessage('msgSelectCategory'), 'error');
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
            tags: tags
        };

        const response = await fetch(`${settings.apiEndpoint}/add-link`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-LinkDigest-API-Key': settings.apiKey
            },
            body: JSON.stringify(formData)
        });

        const result = await response.json();

        if (response.status === 409) {
            chrome.notifications.create({
                type: 'basic',
                iconUrl: 'icon48.png',
                title: chrome.i18n.getMessage('notifAlreadySavedTitle'),
                message: result.message || chrome.i18n.getMessage('notifAlreadySavedBody')
            }, () => window.close());
            return;
        }

        if (!response.ok) {
            throw new Error(result.message || chrome.i18n.getMessage('msgSaveFailed'));
        }

        chrome.notifications.create({
            type: 'basic',
            iconUrl: 'icon48.png',
            title: chrome.i18n.getMessage('notifLinkSavedTitle'),
            message: formData.title || chrome.i18n.getMessage('notifLinkSavedBody')
        }, () => window.close());

    } catch (error) {
        showMessage(error.message || chrome.i18n.getMessage('msgSaveFailed'), 'error');
    } finally {
        saveBtn.disabled = false;
        btnText.style.display = 'inline';
        btnLoading.style.display = 'none';
    }
}

// Open settings page
export function openSettings() {
    chrome.runtime.openOptionsPage();
}

// Tagify instance
let tagify;

// Initialize
document.addEventListener('DOMContentLoaded', async () => {
    applyI18n();

    // Add event listeners immediately
    document.getElementById('linkForm')?.addEventListener('submit', handleSubmit);
    document.getElementById('settingsBtn')?.addEventListener('click', openSettings);
    document.getElementById('openSettings')?.addEventListener('click', openSettings);

    // Check settings
    const hasSettings = await checkSettings();

    if (hasSettings) {
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
                placeholder: 'Add tags...',
                dropdown: {
                    enabled: 0
                }
            });
        }

        // Load page info and categories in parallel (non-blocking)
        Promise.all([
            loadPageInfo(),
            loadCategories()
        ]).catch(() => {});
    }
});
