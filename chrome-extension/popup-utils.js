/**
 * Pure utility functions extracted from popup.js.
 * No Chrome API calls, no direct document access — fully testable.
 */

/**
 * Extract page description from a document's <meta> tags.
 * Priority order: og:description, description, twitter:description.
 *
 * @param {Document} doc - The document to query (injectable for tests).
 * @returns {string}
 */
export function extractPageDescription(doc) {
    const candidates = [
        ['property', 'og:description'],
        ['name',     'description'],
        ['name',     'twitter:description'],
        ['name',     'og:description'],
        ['http-equiv', 'description'],
    ];

    for (const [attr, value] of candidates) {
        const nodes = doc.querySelectorAll(`[${attr}="${value}" i]`);
        for (const node of nodes) {
            let text = (node.content || '').trim();
            while (text.startsWith('\n')) text = text.slice(1);
            while (text.endsWith('\n')) text = text.slice(0, -1);
            if (text) return text;
        }
    }
    return '';
}

/**
 * Render a list of category objects as radio buttons into a container element.
 *
 * @param {Array<{id: number|string, name: string}>} categories
 * @param {HTMLElement} container
 * @param {Document} doc - The document used to create elements (injectable for tests).
 */
export function renderCategories(categories, container, doc = document) {
    container.innerHTML = '';
    const fragment = doc.createDocumentFragment();

    for (const category of categories) {
        const radio = doc.createElement('input');
        radio.type = 'radio';
        radio.name = 'lynxjournal_category';
        radio.id = `cat-${category.id}`;
        radio.value = category.name;
        radio.className = 'category-checkbox';

        const label = doc.createElement('label');
        label.htmlFor = `cat-${category.id}`;
        label.textContent = category.name;
        label.className = 'category-label';

        fragment.appendChild(radio);
        fragment.appendChild(label);
    }

    container.appendChild(fragment);
}
