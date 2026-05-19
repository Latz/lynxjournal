# LinkDigest REST API

## Overview

LinkDigest exposes a REST API under the namespace `linkdigest/v1`. All endpoints follow the standard WordPress REST API conventions and are accessible at:

```
https://example.com/wp-json/linkdigest/v1/<endpoint>
```

The API is used by the bundled Chrome extension, the plugin's own React UIs, and any external tool that needs to add or manage links programmatically.

---

## Authentication

### API Key

For external callers that cannot hold a WordPress session (the Chrome extension, scripts, webhooks), pass the API key generated in **LinkDigest › Settings** as a request header:

```
X-LinkDigest-API-Key: <your-key>
```

Keys are compared using constant-time comparison (`hash_equals`) to prevent timing attacks. The API key grants access to the **add-link** and **categories** endpoints only.

### WordPress User Capabilities

Browser-based requests from the admin UI authenticate via the standard WordPress nonce/cookie mechanism. Required capabilities vary by endpoint:

| Capability | Endpoints |
|---|---|
| `edit_posts` | `/add-link`, `/categories` (fallback when no API key) |
| `delete_posts` | `/links/{id}` DELETE |
| `manage_categories` | `/categories/{id}` POST |
| `manage_options` | `/schedule/*`, `/notify/*`, `/api-key` |

### CORS & Chrome Extension Support

The plugin automatically adds `Access-Control-Allow-Origin` headers when it detects a request origin starting with `chrome-extension://`. Preflight `OPTIONS` requests are handled on the `init` hook before WordPress routing takes over. Allowed methods: `POST, GET, OPTIONS, DELETE`. Allowed request headers: `Content-Type, X-LinkDigest-API-Key, Authorization`.

---

## Response Format

All successful responses return JSON. Error responses follow the standard WordPress REST API error format:

```json
{
  "code": "error_slug",
  "message": "Human-readable description.",
  "data": { "status": 400 }
}
```

---

## Endpoints

### Links

#### `POST /linkdigest/v1/add-link`

Add a new link. Used by the Chrome extension and any external tool.

**Authentication:** API key **or** `edit_posts` capability.

**Request body (JSON or form-encoded):**

| Parameter | Type | Required | Notes |
|---|---|---|---|
| `title` | string | Yes | Plain text; sanitized via `sanitize_text_field` |
| `url` | string | No | Sanitized via `esc_url_raw`; duplicate URLs are rejected |
| `content` | string | No | Rich text description; sanitized via `wp_kses_post` |
| `categories` | array of strings | No | Category names; missing categories are created automatically |
| `tags` | string | No | Comma-separated tag names |

**Success response — `200 OK`:**

```json
{
  "success": true,
  "post_id": 42,
  "message": "Link added successfully!"
}
```

**Error responses:**

| Status | Code | Reason |
|---|---|---|
| 400 | `missing_title` | `title` is empty |
| 409 | `duplicate_url` | A link with this URL already exists |
| 500 | `insert_failed` | Database insert failed |

---

#### `DELETE /linkdigest/v1/links/{id}`

Permanently delete a link by its post ID.

**Authentication:** `delete_posts` capability.

**URL parameters:**

| Parameter | Type | Required | Notes |
|---|---|---|---|
| `id` | integer | Yes | Post ID of the link to delete |

**Success response — `204 No Content`:** Empty body.

**Error responses:**

| Status | Code | Reason |
|---|---|---|
| 404 | `invalid_link` | Post not found or not a `linkdigest` post type |
| 500 | `delete_failed` | Deletion failed |

---

### Categories

#### `GET /linkdigest/v1/categories`

Return all LinkDigest categories. Results are cached for one hour via a WordPress transient.

**Authentication:** API key **or** `edit_posts` capability.

**Success response — `200 OK`:**

```json
[
  { "id": 1, "name": "Technology", "slug": "technology" },
  { "id": 2, "name": "Design",     "slug": "design" }
]
```

**Error responses:**

| Status | Code | Reason |
|---|---|---|
| 500 | `fetch_failed` | Database query failed |

---

#### `POST /linkdigest/v1/categories/{id}`

Update an existing LinkDigest category.

**Authentication:** `manage_categories` capability.

**URL parameters:**

| Parameter | Type | Required | Notes |
|---|---|---|---|
| `id` | integer | Yes | Term ID of the category to update |

**Request body:**

| Parameter | Type | Required | Notes |
|---|---|---|---|
| `name` | string | Yes | Sanitized via `sanitize_text_field` |
| `description` | string | No | Sanitized via `sanitize_textarea_field` |
| `slug` | string | No | Sanitized via `sanitize_title`; ignored if empty |

**Success response — `200 OK`:**

```json
{
  "id": 1,
  "name": "Technology",
  "slug": "technology",
  "description": "Links about tech"
}
```

Updates invalidate the categories transient cache.

---

### Schedule

#### `GET /linkdigest/v1/schedule`

Return the current schedule configuration.

**Authentication:** `manage_options` capability.

**Success response — `200 OK`:**

```json
{
  "mode": "daily",
  "recurrence": {
    "interval": 1,
    "weekdays": [],
    "monthDays": [{ "type": "day", "value": 1, "nth": 1, "weekday": "MO" }],
    "nthWeek": null
  },
  "trigger": { "count": 10, "tag_id": null, "days": 7 },
  "times": ["09:00"],
  "publishAs": 1
}
```

`mode` is one of: `daily`, `weekly`, `monthly`, `count`, `age`, `manual`.

---

#### `POST /linkdigest/v1/schedule`

Save the schedule configuration. The `notify` sub-key is preserved from the existing stored value and cannot be overwritten through this endpoint (use `POST /notify` instead).

**Authentication:** `manage_options` capability.

**Request body:** JSON object matching the schedule config shape above (see `GET /schedule`). `publishAs` defaults to the current user ID when omitted.

**Success response — `200 OK`:**

```json
{ "success": true }
```

**Error responses:**

| Status | Code | Reason |
|---|---|---|
| 400 | `invalid_data` | Request body is empty or not valid JSON |
| 400 | *(validation error)* | `validateScheduleConfig` rejected the payload |

Saving reschedules the next WordPress cron event (`linkdigest_execute_schedule`).

---

#### `POST /linkdigest/v1/schedule/run`

Trigger the schedule to execute immediately, regardless of the configured interval.

**Authentication:** `manage_options` capability.

**Success response — `200 OK`:** Result object from `executeSchedule()`.

**Error responses:**

| Status | Code | Reason |
|---|---|---|
| 429 | `run_in_progress` | A run is already executing (lock transient set) |

---

#### `POST /linkdigest/v1/schedule/preview`

Return a preview of what the next scheduled publish would include, without actually publishing.

**Authentication:** `manage_options` capability.

**Success response — `200 OK`:** Preview data from `previewSchedule()` (link count, grouped by category, estimated post title).

---

#### `GET /linkdigest/v1/schedule/diagnostics`

Return status and diagnostic information about the scheduler.

**Authentication:** `manage_options` capability.

**Success response — `200 OK`:**

```json
{
  "next_scheduled": 1716192000,
  "last_run": 1716105600,
  "wp_cron_disabled": false,
  "cron_notice_dismissed": false,
  "run_history": [],
  "links_until_post": 3
}
```

`links_until_post` is only present when `mode` is `count`; it shows how many more links are needed before the threshold is met.

---

#### `POST /linkdigest/v1/schedule/dismiss-cron-notice`

Dismiss the "WP-Cron is disabled" admin warning. Sets the `linkdigest_cron_notice_dismissed` option.

**Authentication:** `manage_options` capability.

**Success response — `200 OK`:**

```json
{ "success": true }
```

---

### Notifications

#### `GET /linkdigest/v1/notify`

Return the current notification configuration.

**Authentication:** `manage_options` capability.

**Success response — `200 OK`:**

```json
{
  "enabled": true,
  "email": "you@example.com",
  "discord_webhook": "https://discord.com/api/webhooks/...",
  "slack_webhook": "https://hooks.slack.com/services/...",
  "telegram_bot_token": "123456:ABC-...",
  "telegram_chat_id": "-1001234567890"
}
```

Missing keys are returned with empty-string or `false` defaults.

---

#### `POST /linkdigest/v1/notify`

Save the notification configuration. Stored as the `notify` sub-key inside the `linkdigest_schedule` option.

**Authentication:** `manage_options` capability.

**Request body:** JSON object matching the shape above. All fields are optional; omitted fields are not changed.

**Success response — `200 OK`:**

```json
{ "success": true }
```

**Error responses:** `validateNotify()` validation errors with `400` status.

---

#### `POST /linkdigest/v1/notify/test`

Send a test notification on a specific channel.

**Authentication:** `manage_options` capability.

**Request body:**

| Parameter | Type | Required | Notes |
|---|---|---|---|
| `type` | string | Yes | One of: `email`, `discord`, `slack`, `telegram` |
| `value` | string | Conditional | Webhook URL (discord/slack) or email address |
| `telegram_bot_token` | string | Telegram only | Bot token from @BotFather |
| `telegram_chat_id` | string | Telegram only | Chat/channel/group ID |

**Channel behaviour:**

- **email** — sends via `wp_mail()`; falls back to admin email if `value` is blank or invalid.
- **discord** — POSTs a JSON embed payload (`color: #2D9BF0`) to the webhook URL.
- **slack** — POSTs a plain text message to the webhook URL.
- **telegram** — POSTs via the Telegram Bot API using HTML parse mode.

**Success response — `200 OK`:**

```json
{ "success": true }
```

**Error responses:**

| Status | Code | Reason |
|---|---|---|
| 400 | `invalid_url` | Webhook URL is empty or not a valid URL (discord/slack) |
| 400 | `missing_telegram` | `telegram_bot_token` or `telegram_chat_id` missing |
| 400 | `invalid_type` | `type` is not one of the supported values |
| 500 | `mail_failed` | `wp_mail()` returned false |
| 500 | `request_failed` | HTTP request to external service failed |
| 502 | `webhook_error` / `telegram_error` | External service returned a non-2xx status |

---

#### `POST /linkdigest/v1/notify/telegram-chat-id`

Resolve a chat ID by calling the Telegram `getUpdates` API with the bot token. Requires that at least one message has been sent to the bot (or the bot has been added to the target group/channel).

**Authentication:** `manage_options` capability.

**Request body:**

| Parameter | Type | Required | Notes |
|---|---|---|---|
| `token` | string | Yes | Telegram bot token from @BotFather |

**Success response — `200 OK`:**

```json
{ "chat_id": "-1001234567890" }
```

**Error responses:**

| Status | Code | Reason |
|---|---|---|
| 400 | `missing_token` | `token` parameter is empty |
| 404 | `no_messages` | `getUpdates` returned no results; send a message to the bot first |
| 404 | `no_chat_id` | Updates present but no chat ID could be extracted |
| 500 | `request_failed` | HTTP request to Telegram API failed |
| 502 | `telegram_error` | Telegram returned a non-200 status (e.g. 401 = invalid token) |

---

### API Key

#### `GET /linkdigest/v1/api-key`

Return the currently configured API key.

**Authentication:** `manage_options` capability.

**Success response — `200 OK`:**

```json
{ "key": "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" }
```

**Error responses:**

| Status | Code | Reason |
|---|---|---|
| 404 | `no_key` | No API key has been generated yet |

---

## Nonce Endpoint (AJAX)

The Chrome extension retrieves a WordPress REST nonce via an AJAX action rather than a REST route:

```
POST /wp-admin/admin-ajax.php
action=linkdigest_get_rest_nonce
```

Requires the user to be logged in with `manage_options` capability. Returns:

```json
{ "success": true, "data": { "nonce": "abc123" } }
```

The nonce is passed as the `X-WP-Nonce` header on subsequent authenticated REST requests from the browser.
