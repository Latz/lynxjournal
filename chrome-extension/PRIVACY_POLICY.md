# Privacy Policy for LinkDigest Browser Extension

*Last updated: 2026-05-16*

LinkDigest is a browser extension that saves web pages to a self-hosted WordPress installation running the LinkDigest plugin. All data stays between your browser and your own server — no data is sent to the extension developer or any third party.

## Data collected and how it is used

| Data | Purpose | Where it goes |
|------|---------|---------------|
| Current tab URL, title, meta description | Pre-fills the save form | Sent to your WordPress site when you click Save |
| Tags, categories, notes you enter | Saved with the link | Sent to your WordPress site when you click Save |
| WordPress site URL and API key | Authenticate requests to your WordPress site | Stored locally in the browser |
| WordPress session cookies | Detect whether you are logged into WordPress to auto-retrieve your API key | Read locally; never transmitted |
| Category list | Populate the category selector in the popup | Cached locally; fetched from your WordPress site |

## Data sharing

No data is shared with the extension developer, Google, or any third party. All network requests go exclusively to the WordPress URL you configure in the extension settings. That server is operated by you.

## Data storage

- `chrome.storage.sync` stores your WordPress URL and API key. Chrome may sync this across your devices via your Google account, subject to [Google's privacy policy](https://policies.google.com/privacy).
- `chrome.storage.local` stores the cached category list on your local device only.
- No data is stored on any server controlled by the extension developer.

## Permissions

| Permission | Reason |
|-----------|--------|
| `activeTab` | Read the current tab's URL, title, and meta description to pre-fill the save form |
| `storage` | Store your WordPress URL and API key; cache the category list |
| `cookies` | Detect WordPress login status to auto-retrieve your API key during setup |
| `notifications` | Show a confirmation after a link is saved or when a duplicate is detected |
| `contextMenus` | Add "Open Admin" and "Update Categories" shortcuts to the extension icon's right-click menu |
| `scripting` | Extract the meta description from the current page |
| Host permissions | Connect to your self-hosted WordPress site, whose URL is set by you at runtime and cannot be known in advance |

## Contact

If you have questions about this privacy policy, contact: [l.schroeer@gmail.com](mailto:l.schroeer@gmail.com)
