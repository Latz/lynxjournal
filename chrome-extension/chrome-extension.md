---
description: Cutting-edge Chrome Extension standards (Manifest V3, ES2024+) maximizing performance, security, and web store compliance.
keep-coding-instructions: true
style:
  conciseness: 5
  verbosity: "low"
---

# Chrome Extension MV3 Rules

## 1. MV3 Architecture & Lifecycle
* **Strict Manifest V3:** Never use Manifest V2 features. Never use `background.page` or persistent scripts.
* **Service Workers:** Background scripts MUST be registered as ephemeral, event-driven Service Workers. They must be completely stateless. Always write data to storage immediately before the worker sleeps.
* **Asynchronous APIs:** Always use the modern Promise-based architecture for `chrome.*` APIs instead of callbacks (use `async/await`).

## 2. Security & Content Security Policy (CSP)
* **No Remote Code Execution:** Never use `eval()`, `setTimeout(string)`, or `new Function()`.
* **Strict CSP:** Never fetch, load, or execute external JavaScript inside extension pages or content scripts. All assets and libraries must be bundled locally.
* **Message Passing:** Always sanitize and validate the `request` object inside `chrome.runtime.onMessage.addListener` before executing actions to prevent privilege escalation.
* **WordPress API Integration:** When fetching data from a companion WordPress plugin REST API, always securely read and attach the `X-WP-Nonce` header to the fetch request. Never hardcode endpoints; always use dynamically verified REST URLs.

## 3. Script Isolation & DOM (ES2024+)
* **Syntax Standards:** Use modern ECMAScript features exclusively (`const/let`, Arrow Functions, Optional Chaining `?.`, Destructuring).
* **Native Web APIs:** Prioritize modern browser APIs: Use `fetch()` instead of XHR, `URLSearchParams` for queries, and `FormData` for form submissions.
* **No Framework Bloat:** Avoid jQuery or heavy utility libraries in standard scripts. Use native DOM methods (`querySelector`, `classList`, Event Delegation).
* **Storage APIs:** Use `chrome.storage.local` or `chrome.storage.session` for data persistence. Never use standard `localStorage` inside service workers (it is not available).
* **Content Scripts:** Keep the global scope isolated. Use content scripts only for DOM manipulation and offload heavy processing or external API fetches to the Service Worker via `chrome.runtime.sendMessage`.
