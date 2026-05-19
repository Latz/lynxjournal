=== LinkDigest ===
Contributors: latz
Tags: links, blogging, roundup, curation
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Save and publish curated link roundups to your blog.

== Description ==

LinkDigest is a WordPress plugin for managing and publishing curated link roundups. Save interesting links, organise them by category, and publish them as blog post roundups — individually or as a grouped collection.

**Features:**

* Save links with title, URL, description, categories, and tags
* Publish links individually or as a grouped roundup post
* Organise links by category (inspired by frankysnotes.com)
* REST API (`linkdigest/v1`) for external integrations and browser extensions
* Schedule automatic roundup publishing (daily, weekly, monthly, or by count/age)
* Chrome extension support

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/linkdigest` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Use the LinkDigest menu in the admin dashboard to start adding links.

== Usage ==

=== Dashboard ===

The LinkDigest dashboard (LinkDigest › Dashboard) gives you an at-a-glance overview:

* **Stats bar** — total links, categories, published, and unpublished counts
* **Quick Add** — enter a title and URL to save a link in seconds without leaving the dashboard
* **Recent Unpublished** — the last five unsaved links; delete any of them directly from this list
* **Recently Published** — the last five published roundup posts with their status

=== Adding Links ===

**Manually (full form):** Go to LinkDigest › Add Link. Fill in:

* Title (required)
* URL
* Description (rich text)
* Categories — assign to one or more existing categories
* Tags — comma-separated keywords

**Quick add:** Use the Quick Add box on the Dashboard for a bare-minimum title + URL entry.

**Via Chrome extension:** Browse to any page and click the extension icon. The title and URL are pre-filled; add a description, pick a category, and click Save Link.

=== Managing Links ===

LinkDigest › All Links shows every saved link in a table:

* **Status badges** — Unpublished, Draft, or Published
* **Publish** — creates a WordPress post immediately for that single link
* **Delete** — removes the link permanently (shows an inline confirmation first)

=== Publishing ===

**Individual post:** Click Publish on any link in All Links. A new WordPress post is created with the link's title, description, and a "Read more" link to the source URL.

**Roundup post:** Click Publish on the Dashboard. All unpublished links are bundled into one post, grouped by category. Enter a custom title or leave the default ("Links Roundup – [date]"). Choose to publish immediately or save as draft.

Both flows support draft mode — use the Draft toggle before confirming.

=== Scheduling ===

LinkDigest › Schedule lets automatic roundup publishing run without manual action:

* **Daily / Weekly / Monthly** — pick a day and time
* **Count-based** — publish when a set number of unpublished links accumulates
* **Age-based** — publish when the oldest unpublished link reaches a set number of days
* **Manual** — disable automatic publishing entirely

The "Run Schedule Now" button triggers the next scheduled publish immediately, regardless of the configured interval.

=== Notifications ===

After each successful publish run, LinkDigest can send a notification to your team via webhook. Configure notification channels in the **Notifications** panel on LinkDigest › Schedule and click **Save Schedule**.

Notifications fire only when a roundup post is actually created. Skipped runs produce nothing.

* **Email** — check "Email me after each run". Leave the address blank to use the WordPress admin email, or enter a specific address to override it.
* **Discord** — create a webhook in your Discord server (Server Settings › Integrations › Webhooks) and paste the URL into **Discord Webhook URL**.
* **Slack** — create an Incoming Webhook at api.slack.com/apps and paste the URL into **Slack Webhook URL**.

All three channels are independent. Leave a field blank to disable that channel.

=== Chrome Extension ===

1. Open Chrome and navigate to `chrome://extensions`
2. Enable **Developer mode** (top-right toggle)
3. Click **Load unpacked** and select the `chrome-extension` folder inside the plugin directory
4. In WordPress, go to LinkDigest › Settings, generate an API key, and copy the API Endpoint URL and key
5. Click the extension icon › **Settings**, paste both values, and save
6. From now on, click the extension icon on any page to save the current URL directly to your WordPress site

=== Settings ===

LinkDigest › Settings contains:

* **API Key** — generate or regenerate the key used by the Chrome extension
* **Post defaults** — default publish status, author, excerpt generation, source URL in content
* **UI options** — date format, compact view, links per page, category badges, accent color
* **Schedule** — see the dedicated Schedule page (above)
* **Advanced** — public API access, CORS headers, cache duration, debug logging

== REST API ==

LinkDigest provides a REST API under the namespace `linkdigest/v1`. The full endpoint reference is in [docs/rest-api.md](docs/rest-api.md).

**Base URL:**

```
https://your-site.com/wp-json/linkdigest/v1/
```

**Authentication:**

* **API key** — pass the key from LinkDigest › Settings as an `X-LinkDigest-API-Key` request header. Grants access to the link-saving and category endpoints; intended for the Chrome extension and external scripts.
* **WordPress user** — browser-based requests use the standard WordPress nonce/cookie flow. Required capabilities vary by endpoint (`edit_posts`, `delete_posts`, `manage_categories`, `manage_options`).

**Endpoints summary:**

| Method | Path | Description |
|---|---|---|
| POST | `/add-link` | Add a new link |
| DELETE | `/links/{id}` | Delete a link |
| GET | `/categories` | List all categories |
| POST | `/categories/{id}` | Update a category |
| GET | `/schedule` | Get schedule configuration |
| POST | `/schedule` | Save schedule configuration |
| POST | `/schedule/run` | Trigger schedule immediately |
| POST | `/schedule/preview` | Preview next scheduled publish |
| GET | `/schedule/diagnostics` | Scheduler status and history |
| POST | `/schedule/dismiss-cron-notice` | Dismiss WP-Cron warning |
| GET | `/notify` | Get notification configuration |
| POST | `/notify` | Save notification configuration |
| POST | `/notify/test` | Send a test notification |
| POST | `/notify/telegram-chat-id` | Look up Telegram chat ID |
| GET | `/api-key` | Retrieve the current API key |

== Frequently Asked Questions ==

= How do I add links? =

Navigate to LinkDigest > Add New Link in the WordPress admin dashboard.

= Can I use this with the Chrome extension? =

Yes. Generate an API key in LinkDigest > Settings and configure it in the Chrome extension.

= How do I install the Chrome extension? =

The Chrome extension is not currently available in the Chrome Web Store. To install it, open Chrome and go to chrome://extensions, enable Developer mode, click "Load unpacked", and select the `chrome-extension` folder from the plugin directory.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
