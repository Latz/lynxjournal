# The LynxJournal Notification System

## 1. Overview

When people hear "notification system" in the context of a WordPress plugin, they usually picture a bell icon in the admin bar, a dropdown of unread items, and a database table tracking read/unread state per user. LynxJournal has none of that. Its notification system is something narrower and more specific: an **outbound alerting pipeline** that fires when the plugin's scheduled "roundup" publishing job runs, sending a message to whatever external destinations the site owner has configured — email, Discord, Slack, Telegram, Mastodon, or Bluesky.

There is no notification *center*. There is no per-user read/unread state. There is no database table dedicated to notifications at all — the entire system is built on `wp_options`, WP-Cron, and the REST API. Understanding this up front reframes everything that follows: this is not a feature for end users of the journal to keep up with activity, it's a feature for the *site administrator* to be told, elsewhere, that something happened.

Two adjacent concepts are easy to conflate with this system and worth separating immediately:

- **The WP-admin failure notice.** A small, unrelated-but-nearby feature surfaces *delivery failures* from the outbound pipeline as a dismissible notice in wp-admin. It reuses the word "notification" (as in `lynxjournal_notification_failures`) but is really an error-reporting UI layered on top of the real system, not a second notification system in its own right. Section 7 covers it.
- **Browser-extension toast popups.** The companion Chrome and Firefox extensions call the native `chrome.notifications.create()` / `browser.notifications.create()` APIs to show OS-level toasts like "Link saved" (`chrome-extension/popup.js`, `firefox-extension/background.js`). These are pure browser-extension UX and share no code, no data, and no concepts with the PHP notify pipeline described here. They're mentioned only so a reader searching the codebase for "notification" doesn't confuse the two.

## 2. Trigger path: WP-Cron and the roundup publish flow

The notification system doesn't have its own trigger — it rides on the back of LynxJournal's scheduled publishing feature. `LynxJournal_Scheduler` (`src/php/traits/Scheduler.php`) registers a single WP-Cron event, `lynxjournal_execute_schedule`, wired up in its constructor-time hook registration (`Scheduler.php:20`). `scheduleNextEvent()` (line 29) computes when that event should next fire based on the configured cadence (daily/weekly/monthly, or count/age-based thresholds).

When the cron event fires, `executeSchedule()` (line 46) runs. It first takes a `lynxjournal_run_lock` transient with a 5-minute TTL (lines 47–50) to guard against overlapping runs if cron fires twice in close succession — a real risk with WP-Cron's request-triggered model — and releases it in a `finally`-style cleanup (line 54) regardless of outcome. The bulk of the decision logic lives in `doExecuteSchedule()`, which decides whether enough new links have accumulated (or enough time has passed) to justify publishing a roundup post.

If a post is published, `attemptPublish()` does something notable at line 133–139: because WP-Cron requests run without any logged-in user (`user 0`), the code deliberately avoids `wp_set_current_user()` and instead reads a stored `publishAs` user ID straight out of the schedule configuration and passes it explicitly wherever an author is needed. It's a small detail, but it reflects an awareness that cron execution context is not the same as a normal request context — a distinction that matters again later for how the notification system authenticates (it doesn't; see Section 9).

The actual entry point into the notification system is two action hooks fired around the publish attempt:

```php
do_action('lynxjournal_before_run', $link_ids, $mode);   // Scheduler.php:141
// ... publish happens ...
do_action('lynxjournal_after_run', $post_id, $link_ids, $mode);   // Scheduler.php:146
```

`lynxjournal_after_run` is the hook the notification manager listens on. Everything downstream — channel fan-out, message formatting, delivery, failure logging — starts here.

## 3. The manager and its fan-out design

`LynxJournalNotifyManager` (`src/php/notifications/Manager.php`) is the coordinator. Its `registerHooks()` method (line 41) wires up two hooks:

```php
add_action('lynxjournal_after_run', [$this, 'runAfterPublish'], 10, 3);           // Manager.php:42
add_action('lynxjournal_send_notification', [$this, 'dispatchChannelNotification'], 10, 4); // Manager.php:43
```

The split into two hooks is the most important architectural decision in the whole system. `runAfterPublish()` (line 61) does not send anything itself. For every channel that is both registered and enabled, it schedules a **separate one-off WP-Cron event**:

```php
wp_schedule_single_event(time(), 'lynxjournal_send_notification', [$channel->key(), $post_id, $link_ids, $mode]); // Manager.php:67
```

In other words, publishing a roundup doesn't send N notifications inline — it schedules N independent cron jobs, one per enabled channel, each of which will fire (in practice, near-immediately, since `time()` is "now") and independently call `dispatchChannelNotification()` (line 85), which resolves the channel object and invokes `$channel->send(...)`.

The reasoning is documented directly in the code: some channels have expensive, multi-step delivery mechanics — the comment specifically calls out Bluesky's multi-request AT Protocol handshake — and the design goal is to prevent one slow or failing channel from blocking the publish request itself, or from blocking delivery to the other channels. This is a legitimate resilience pattern, but it comes with a real asymmetry worth flagging: **test notifications sent from the admin UI are synchronous** (Section 6), while **real run notifications are always deferred through cron**. A developer debugging "why did my test send instantly but the real notification take a few seconds to show up" needs to know this distinction exists by design, not by accident.

## 4. Storage model: everything lives in `wp_options`

LynxJournal's notification system has no custom database table and no post meta. All state lives in a handful of options:

- **`lynxjournal_schedule`** — the schedule configuration as a whole; its `notify` sub-array is the single source of truth for every channel's settings (enabled flags, bot tokens, webhook URLs, target IDs). This is a flat associative array keyed by ad hoc names like `discordEnabled`, `slackBotToken`, `bskyAppPassword` — channel-agnostic in shape, but not namespaced per channel, so all channels' fields coexist in one dictionary.
- **`lynxjournal_notification_failures`** — a capped list (10 most recent) of failure records, each `{ ts, channel, label, message }`, written by `recordChannelFailure()` (`Manager.php:109`).
- **`lynxjournal_notification_failures_dismissed_at`** — a single timestamp watermark. Failures with `ts` after this value are treated as "pending" for the admin notice. This is the closest thing to read/unread tracking anywhere in the system, and it's coarse: one watermark for *all* failures, not per-item state, and it only governs whether the admin banner reappears — not any concept of an individual notification being "read."
- **`lynxjournal_last_run`** / **`lynxjournal_run_history`** (last 25 entries) — general run history, useful context but not notification-specific.

A consequence worth naming plainly: **there is no historical record of successfully sent notifications** — only the last 10 failures and the last 25 runs. If a site owner wants to know "did last Tuesday's Discord notification actually go out," the system has no way to answer that once the run-history window has rolled past it. It also means secrets (bot tokens, app passwords, webhook URLs) are stored in plaintext inside `wp_options`; the `password`-type inputs used in the admin UI (Section 8) are a UI affordance for masking on-screen, not an at-rest encryption mechanism.

## 5. Channel architecture: a strategy pattern with real code reuse

Every delivery destination implements `LynxJournalNotifyChannel` (`src/php/notifications/Channel.php`), a small interface: `key()`, `fields()`, `isEnabled()`, `isComplete()`, `validate(&$notify)`, `send($post_id, $link_ids, $mode, $notify)`, `sendTest($notify)`. Eight concrete channels live in `src/php/notifications/channels/`:

| Channel | File | Transport |
|---|---|---|
| Email | `EmailChannel.php` | `wp_mail()` |
| Discord | `DiscordChannel.php` | Webhook POST with embed JSON; validates the host against `discord.com`/`discordapp.com` and the path against a strict regex `^/api(?:/v\d+)?/webhooks/\d+/[\w-]+$` |
| Slack (channel) | `SlackChannelChannel.php` (extends `SlackBase.php`) | Bot token, Block Kit message |
| Slack (DM) | `SlackDmChannel.php` (extends `SlackBase.php`) | Same bot token, different target field |
| Telegram (group) | `TelegramChannel.php` (extends `TelegramBase.php`) | Bot token + chat ID, HTML formatting |
| Telegram (DM) | `TelegramDmChannel.php` (extends `TelegramBase.php`) | Same bot token, different chat ID |
| Mastodon | `MastodonChannel.php` | Instance URL + access token + recipient handle |
| Bluesky | `BlueskyChannel.php` | AT Protocol; caches a session bearer token for `SESSION_TTL = 50 * MINUTE_IN_SECONDS` (the real token is valid ~2 hours, so this refreshes conservatively early) |

Two design choices here are worth calling out as genuinely clean engineering:

**Grouped multi-target channels share a base class.** Slack and Telegram each support two independent send destinations (a channel/group vs. a personal DM) that nonetheless share one bot token and most of their request-building logic. Rather than duplicating that logic, `SlackBase.php` and `TelegramBase.php` hold the shared mechanics, and the channel/DM variants are thin subclasses that differ only in which field supplies the destination ID. This models "one credential, two independent enable/configure targets" without either duplicating code or conflating the two targets into one channel with hidden branching.

**Cross-cutting concerns are factored out, not copy-pasted.** `LynxJournalNotifyHttp::postJson()` (`Http.php`) is the single `wp_remote_post()` wrapper used by every webhook-style channel, handling timeouts and HTTP-status checking uniformly and returning a `WP_Error` on failure. `LynxJournalNotifyRunMessageContent::forRun()` (`RunMessageContent.php`) is the single source of truth for the human-readable message text ("A new roundup was published with %d links." / "Schedule ran in %s mode but no post was published."), which each channel then reformats into its own wire format — a Discord embed, Slack Block Kit, Telegram HTML, or plain email text — rather than every channel independently deciding what to say. And `RequiredFieldsValidation` (a trait, `RequiredFieldsValidation.php`) supplies shared "all required fields present when enabled" logic reused by channels whose `send()` pipelines are otherwise unrelated (currently just Bluesky, but written to be adopted more broadly).

The result reads as a legitimate strategy pattern: a stable interface, an extensible list of interchangeable implementations, and real reuse of the parts that are actually shared, without forcing structurally different channels (webhook-based vs. bot-token-based vs. session-token-based) into an artificially uniform shape.

## 6. REST API surface

The REST layer lives in `src/php/traits/RestApi.php`, registered under the namespace constant `LYNXJOURNAL_REST_NAMESPACE` (`lynxjournal/v1`). Three routes are notification-specific:

- **`POST /schedule/test-notification`** (line 212) — body `{ channel, notify }`. Calls `restTestNotification()` → `validateChannelAndNotify()` → `dispatchTestNotification()` → `LynxJournalNotifyManager::test()` → `$channel->sendTest($notify)`. This is the one place a message is sent **synchronously** within the request/response cycle, in contrast to the always-deferred real-run path from Section 3.
- **`POST /schedule/save-notification`** (line 222) — persists only the fields belonging to *one* channel into `lynxjournal_schedule['notify']`, via `notifyChannelFields()`/`channelFields()`. Because the save is scoped to a single channel, editing and saving Discord settings can't accidentally clobber in-progress, unsaved edits to Slack settings sitting in the same form.
- **`POST /schedule/dismiss-cron-notice`** (line 232) — unrelated to notification delivery; dismisses a separate WP-Cron health warning (see the disambiguation note in Section 7).

Permission gating is notably stricter here than on most of the plugin's other schedule routes. Most `/schedule/*` endpoints require only `edit_posts` (RestApi.php, e.g. lines 84, 95, 148), but all three notification routes require `manage_options` (lines 215, 225, 238) — appropriate, since these routes read and write secrets like bot tokens and webhook URLs. `class-lynxjournal.php` wires the manager into the plugin lifecycle: `notifications()` (line 127) lazily instantiates `LynxJournalNotifyManager`, `registerNotificationHooks()` (line 139) is hooked on `init` at priority 0 (line 63) so the manager is listening before anything else on `init` could plausibly fire a schedule run, and `validateNotify()`/`validateNotifyChannel()` (lines 168–191) delegate to the manager's `validateAll()`/`validateChannel()` so both REST routes and the general schedule-save flow share one validation path.

That per-channel validation entry point (`Manager.php:175`) is itself a small but deliberate UX decision: because `/schedule/save-notification` only ever writes one channel, it also only ever *validates* that one channel, so an admin fixing their Discord webhook doesn't get blocked by a stale, unrelated validation error sitting in their half-filled-out Bluesky tab. Partial-save and partial-validate semantics were designed together, not bolted on afterward.

## 7. Failure surfacing: the admin notice

Delivery failures — from either a real run or a test send — funnel through `recordChannelFailure()` (`Manager.php:109`) into the `lynxjournal_notification_failures` option described in Section 4. Displaying them is the job of the `LynxJournal_Admin_NotificationFailureNotice` trait (`src/php/traits/Admin/NotificationFailureNotice.php`):

- `renderNotificationFailureNotice()` hooks `admin_notices` (wired in `class-lynxjournal.php:102`), gated on `current_user_can('edit_posts')` — a lower bar than the `manage_options` required to configure channels, since merely *seeing* that something failed is less sensitive than being able to read or change secrets.
- It filters the failure list for entries where `ts` is newer than `lynxjournal_notification_failures_dismissed_at`, then renders a standard dismissible `notice notice-error is-dismissible` block, one line per failure: channel label, timestamp, and message.
- Dismissal doesn't go through the REST API at all — it's a classic WordPress AJAX action, `wp_ajax_lynxjournal_dismiss_notification_failures`, handled by `handleDismissNotificationFailureNotice()`, nonce-checked via `check_ajax_referer`, which advances the dismissed-at watermark.
- On the client, `assets/js/notification-failure-notice.js` is a small vanilla-JS listener on WP core's built-in `.notice-dismiss` button click. WP core already hides the notice visually on click; this script's only job is to POST to `ajaxurl` so the dismissal is *persisted* server-side and the notice doesn't silently reappear on the next page load.

Two things about this feature are easy to trip over:

1. **Test failures and real failures share one bucket.** `sendTest()` and `send()` both report through the same `recordChannelFailure()` path, so an admin repeatedly fiddling with a broken webhook while testing it generates the same kind of admin-notice entries as a genuine production delivery failure. There's no way to tell, from the failure notice alone, whether a given entry came from an actual scheduled run or from someone testing their setup.
2. **This is not the same feature as the "cron notice."** A separate, unrelated flag — `lynxjournal_cron_notice_dismissed` (`RestApi.php:235, 413`) — governs a warning about WP-Cron reliability in general, with a similarly-shaped dismiss-via-REST pattern. It lives in the same file, follows the same dismissal idiom, and is easy to conflate with the notification-failure notice at a glance, but the two are about entirely different problems (cron *not running at all* vs. a specific channel *send failing*).

## 8. Frontend: the React admin UI

The Schedule admin screen's notification configuration lives under `src/schedule/`.

`src/schedule/lib/notificationChannels.js` defines `NOTIFICATION_CHANNELS`, a single declarative array describing every channel and target: its fields, labels, `enabledField`, and — for Slack/Telegram — a `targets[]` array so the channel/DM split is modeled explicitly, with `sharedFields` (the bot token) hoisted out of the per-target fields. A cluster of small pure functions computed entirely from form state — `isTargetEnabled`, `isTargetComplete`, `isChannelEnabled`, `isChannelComplete`, `isChannelIncomplete`, `initialTabFor`, `anyChannelEnabled` — let the UI answer questions like "is this tab fully configured" without a server round trip.

`src/schedule/lib/notifications.js` exports `useNotifications(form, setForm, setSavedForm, configLoaded)`, the hook that owns UI state: `testState` and `channelSaveState` (both keyed by channel, so each tab tracks its own in-flight/success/error state independently), the currently active tab, and tab-overflow scroll measurement for when there are more channel tabs than fit on screen. `handleTest()` calls `/schedule/test-notification` via `@wordpress/api-fetch`; `handleSaveChannel()` calls `/schedule/save-notification` and, notably, merges the server's response back into *both* `form` and `savedForm` — necessary so the form's "you have unsaved changes" dirty-check doesn't misfire immediately after a successful per-channel save.

`src/schedule/components/NotificationsSection.jsx` renders one `TabPanel` tab per channel, with `TabTitleWithBadge` color-coding each tab (on / off / incomplete) so an admin can see configuration status at a glance without opening every tab. Rather than mounting only the active channel's panel, it renders every channel's panel simultaneously in a CSS grid stack, marking inactive ones `inert` and visually hidden rather than unmounting them — a deliberate trick so the grid row auto-sizes to the *tallest* channel's content and switching tabs never causes a layout shift. Secret fields (bot tokens, app passwords, chat IDs) are rendered through a `RevealableTextControl` component: a password-type input with a show/hide toggle, which — as noted in Section 4 — is purely a display affordance, not an encryption boundary.

Test coverage for this layer is fairly thorough on both sides: `tests/js/schedule/useNotifications.test.jsx`, `tests/js/schedule/NotificationsSection.test.jsx`, `tests/js/notificationChannels.test.js` on the frontend, and `tests/Unit/NotificationManagerTest.php`, `NotificationFailureNoticeTest.php`, `NotificationValidationTest.php`, `NotificationHttpTest.php`, `RestNotificationTest.php`, `NotificationTestSendTest.php`, plus per-channel `*NotificationTest.php` files, on the backend.

## 9. Permissions summary

| Action | Required capability | Where |
|---|---|---|
| View the failure notice | `edit_posts` | `NotificationFailureNotice.php` |
| Test a channel | `manage_options` | `RestApi.php:215` |
| Save a channel's settings | `manage_options` | `RestApi.php:225` |
| Dismiss the cron notice (unrelated feature) | `manage_options` | `RestApi.php:238` |
| Actual notification delivery (scheduled run) | none — runs as WP-Cron, no logged-in user | `Manager.php:61`, `Scheduler.php:133–139` |

The last row is the interesting one architecturally: because the real send path runs entirely inside WP-Cron, there is no "current user" to check a capability against, which is exactly why `attemptPublish()` resolves `publishAs` as an explicit parameter instead of relying on `wp_set_current_user()` (Section 2) — a pattern that keeps the publish-as-user concern and the "who's allowed to configure this" concern cleanly separated, since the former needs to work with *no* authenticated context at all.

## 10. Design decisions, quirks, and technical debt

Pulling together observations from the sections above into one list, roughly in order of how much they'd matter to a future maintainer:

1. **No historical log of successful sends.** Only the last 10 failures and last 25 runs are retained; there's no way to confirm after the fact that a specific past notification actually went out.
2. **Synchronous test sends vs. always-deferred real sends.** A deliberate resilience choice for production runs (Section 3), but an asymmetry that can confuse debugging if it isn't documented for the next person who touches this code.
3. **Flat, plaintext, per-option secret storage.** All channels' fields — including bot tokens, app passwords, and webhook URLs — live in one flat array inside `wp_options`, unencrypted. The `password`-type UI fields mask input visually but provide no protection at rest.
4. **Test failures and real failures share one bucket.** The admin failure notice can't distinguish "this happened during a real scheduled run" from "this happened while someone was poking at the settings form."
5. **Naming collision between two unrelated "notice" features.** `lynxjournal_cron_notice_dismissed` (WP-Cron health) and `lynxjournal_notification_failures_dismissed_at` (channel delivery failures) live in the same file, follow the same dismiss-pattern idiom, and are easy to conflate despite addressing unrelated problems.
6. **Browser-extension "notifications" are a false cognate.** The Chrome/Firefox extensions' native OS toast popups share nothing with this system beyond the English word "notification" — worth remembering when grepping the codebase.
7. **Validation is centralized but scoped for partial saves.** `Manager::validateAll()` and `Manager::validateChannel()` exist side by side specifically so a single-channel save doesn't get rejected by unrelated, half-filled-out channels elsewhere in the form — a genuinely thoughtful piece of API design (Section 6).
8. **Inconsistent validation rigor across channels.** Discord's webhook URL is checked against a host allowlist and a precise path regex; Slack and Telegram largely just check that required fields are non-empty and let the live API call surface real problems. Not wrong, but not symmetric either.

## 11. Closing synthesis

Taken as a whole, LynxJournal's "notification system" is best understood as a small, well-factored outbound integration layer rather than a user-facing feature. It borrows WordPress's own primitives — options, WP-Cron, the REST API, `admin_notices` — rather than inventing new infrastructure, which keeps it lightweight and idiomatic for a plugin of this size. Within that scope, the channel abstraction (a real interface, shared base classes for grouped targets, shared helpers for HTTP and message formatting) is more disciplined than most WordPress plugins bother to be for a feature this size, and the per-channel partial-save/partial-validate design shows genuine UX thinking baked into the API contract rather than retrofitted.

Its limitations — no send history, no per-item read state, plaintext secrets, the test/real asymmetry — are the natural tradeoffs of that same minimalism: the system was built to reliably *fire an alert*, not to be an auditable record of alerting. Anyone extending it (a ninth channel, a persistent send log, encrypted credential storage) has a clean strategy-pattern foundation to build on, and the places where that foundation shows its edges are, at least, narrow and well-contained rather than pervasive.
