=== LynxJournal ===
Contributors: latz
Tags: links, blogging, roundup, curation
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Save and publish curated link digests to your blog.

== Description ==

LynxJournal is a WordPress plugin for managing and publishing curated link digests. Save interesting links, organise them by category, and publish them as blog post digests.

**Features:**

* Save links with title, URL, description, categories, and tags
* Organise links by category (inspired by frankysnotes.com)
* REST API for integration with browser extensions (Chrome/Firefox)
* Schedule automatic digest publishing (daily, weekly, monthly, or by count/age)
* Notify Email, Discord, Slack, Telegram, or Mastodon when a digest is published, with admin alerts on delivery failures
* Customise the layout of roundup posts with a Post Template editor
* Chrome extension support

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/lynxjournal` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Use the LynxJournal menu in the admin dashboard to start adding links.

== Usage ==

=== Dashboard ===

The LynxJournal dashboard (LynxJournal › Dashboard) gives you an at-a-glance overview:

* **Stats bar** — total links, categories, published, and unpublished counts
* **Quick Add** — enter a title, URL, and category to save a link in seconds without leaving the dashboard
* **Recent Unpublished** — the last five unpublished links; delete any of them directly from this list
* **Recently Published** — the last five published roundup posts with their status

=== Adding Links ===

**Manually (full form):** Go to LynxJournal › Add Link. Fill in:

* Title (required)
* URL
* Description
* Categories — assign to one or more existing categories
* Tags — comma-separated keywords

**Quick add:** Use the Quick Add box on the Dashboard for a fast title + URL + category entry.

**Via Chrome extension:** Browse to any page and click the extension icon. The title and URL are pre-filled; add a description, pick a category, and click Save Link.

=== Managing Links ===

LynxJournal › All Links shows your saved links in a paginated table, grouped by category. Use the search box, or filter by date or category, to narrow the list.

* **Status badges** — Unpublished, Draft, or Published
* **View** — open the published post or draft (published/draft links only)
* **Unpublish** — revert a published or draft link back to unpublished (published/draft links only)
* **Edit** — open the link in the standard WordPress post editor
* **Delete** — removes the link permanently (confirmation required)

=== Publishing ===

Click **Publish** on the Dashboard. All unpublished links are bundled into one post, grouped by category. Enter a custom title or leave the default ("Links Roundup – [date]"). Choose to publish immediately or save as draft, using the Draft toggle before confirming.

=== Post Template ===

By default, roundup posts use a fixed look: a heading for each category followed by a bulleted list of that category's links. If you'd like a different layout, LynxJournal › Post Template lets you design your own.

**How it works:** You write your layout as plain text. Special placeholders written in square brackets — such as `[title]`, `[link]`, or `[category_name]` — get swapped for the real post, link, and category information whenever a roundup post is published. A live preview below the editor shows exactly what your layout will look like as you type — nothing is ever saved or published from the preview. Use the Theme/Default switch to see it styled with your site's active theme or with a plain generic look, and the Desktop/Mobile switch to check both widths. (Theme styling only applies to the post content itself — it doesn't show your site's header, footer, or sidebar.)

**Formatting toolbar:** Above the editor is a simple toolbar — Undo, Redo, Bold, Italic, Underline, headings, bullet and numbered lists, indent, outdent, and a horizontal rule — much like a basic word processor. Clicking a button inserts the matching formatting at your cursor.

**Available tokens:** Click **Available tokens** to open a reference panel listing every placeholder you can use, grouped by what it represents (post details, links, categories, tags). Click any one to insert it at the cursor.

**Saving:** Click **Save Template** to keep your changes. A confirmation message appears once it's saved.

This feature is entirely optional — if you don't set up a template, LynxJournal simply keeps using its original built-in layout.

*Note:* extra blank lines in your template are collapsed to a single blank line in the actual published post. The small `¶` marks you see while editing are just to help you see spacing while you type — they don't represent the final spacing exactly.

=== Scheduling ===

LynxJournal › Schedule lets automatic roundup publishing run without manual action. Open **LynxJournal › Schedule**, choose a mode, set at least one Execution Time, and click **Save Schedule**. The next time that moment arrives, LynxJournal publishes a roundup post from all unpublished links. To trigger a run immediately, use the **Run Now** button on the dashboard. To stop all automation, switch to Manual mode.

**Modes**

* **Daily** — Publishes at a fixed time every day, provided at least one unpublished link exists.
* **Weekly** — Publishes on specific days of the week. Toggle any combination of Mon–Sun. At least one day must be selected.
* **Monthly** — Publishes on dates you define, every month. Each "day entry" is either a fixed calendar day (1–31) or an ordinal weekday ("the first Monday"). Multiple entries produce multiple runs per month. If a month is shorter than the configured day (e.g. 31st in a 30-day month), that entry is skipped for that month.
* **By Count** — Publishes when the number of unpublished links reaches or exceeds a threshold. The check runs at every configured execution time.
* **By Age** — Publishes when the oldest unpublished link is older than N days. The check runs at every configured execution time.
* **Manual** — Disables all automatic publishing. Use **Run Now** or the dashboard Publish form to publish on demand.

**Execution Times**

Applies to all modes except Manual. Specify one or more 24-hour times (HH:MM) at which the scheduler wakes up. Click **+ Add time** to add a row; click **✕** to remove one. At least one time must remain. Duplicate values are silently removed on save. Adding multiple times means the scheduler can publish more than once per day.

**Previewing upcoming runs**

In Daily, Weekly, and Monthly modes the **Next 10 Schedules** sidebar panel lists the next ten wake-up dates based on your current (unsaved) settings. Use it to verify your recurrence pattern before saving.

**Saving the schedule**

Click **Save Schedule** at the bottom of the settings column. On save, any previously pending cron event is cancelled and a new one is scheduled for the next matching time.

**Running the schedule immediately**

The **Run Now** button on the LynxJournal dashboard triggers an immediate publish run. It respects the same publish condition as the automatic schedule. In every mode, the run is silently skipped when there are no unpublished links — no empty roundup is ever created. The automatic schedule is unaffected.

**What a roundup post looks like**

Each run creates one WordPress post titled `Links: [Full Date]` (e.g. `Links: April 28, 2026`), published immediately as a standard post by default — or saved as a draft instead if you set Post Status to Draft on the Schedule page. The body contains one section per category, each with a list of links. Each link shows its title (linked to the saved URL, opening in a new tab) and, optionally, a description below it. Uncategorised links appear in their own section at the end. Every included link is marked accordingly (published, or draft if Post Status is set to Draft) and excluded from future digests either way.

**Edge cases and caveats**

* **WP-Cron timing** — WordPress's built-in cron fires when a visitor loads a page. On low-traffic sites, the actual run may be a few minutes late. For precise timing, set up a real system cron job (see Server-side cron setup below).
* **Timezone and DST** — Execution times use your WordPress site timezone (Settings › General › Timezone). On daylight-saving transitions, spring-forward runs may be delayed by ~1 hour; fall-back runs fire once at the first occurrence of the repeated local time.
* **Backfill after downtime** — Missed events are not backfilled. As soon as WordPress runs again, a single catch-up run executes and the next slot is computed from the current time. Use **Run Now** to force a catch-up immediately.
* **Deactivating the plugin** — Deactivation automatically cancels any pending cron event. No event is re-registered until you save the schedule again after reactivation.
* **Uninstalling the plugin** — Deleting the plugin (not just deactivating it) permanently removes everything: all saved links, categories, and tags, all plugin options (including the schedule), all transients, and the pending cron event. No manual cleanup is needed.

**Server-side cron setup**

WP-Cron only fires when a visitor hits the site. For reliable scheduling:

1. Disable WP-Cron in `wp-config.php`: `define( 'DISABLE_WP_CRON', true );`
2. Add a system cron entry that runs every minute:

   `* * * * * curl -sS https://example.com/wp-cron.php?doing_wp_cron > /dev/null 2>&1`

   Or with WP-CLI: `* * * * * cd /path/to/wordpress && wp cron event run --due-now > /dev/null 2>&1`

**WP-CLI usage**

LynxJournal does not register custom WP-CLI commands, but the standard cron commands work:

* `wp cron event list | grep lynxjournal` — list pending LynxJournal cron events
* `wp cron event run lynxjournal_execute_schedule` — force an immediate run (equivalent to Run Now)
* `wp option get lynxjournal_schedule --format=json` — inspect the saved schedule
* `wp option delete lynxjournal_schedule` — delete the schedule configuration

**Logging and observability**

The scheduler does not write to a custom log. To verify a run happened, check: Posts › All Posts (a new "Links: …" post appears on each successful run); the WP Crontrol plugin; or `wp cron event list` for the next-scheduled timestamp. The **Next 10 Schedules** panel on the Schedule page shows upcoming times for time-based modes.

**Capabilities**

Viewing the Schedule page, saving the schedule, and triggering Run Now all require the `manage_options` capability (administrator role by default). To delegate to other roles, grant the capability using a tool such as the User Role Editor plugin or `add_cap()` in your own code.

=== Chrome Extension ===

1. install the Chrome Extension "LynxJournal" from https://chromewebstore.google.com/detail/link-digest/majjnembpebmpoaeoildijhhgbjfgjmn
2. In WordPress, go to LynxJournal › Browser Extension, generate an API key, and copy the API Endpoint URL and key
3. Click the extension icon › **Settings**, paste both values, and save
4. From now on, click the extension icon on any page to save the current URL directly to your WordPress site

== Source Code & Build Tools ==
The production JavaScript assets in `schedule/schedule.js` are compiled from React components. 
The unminified source code, build scripts, and configuration files are publicly maintained at:
https://github.com/Latz/lynxjournal

== Changelog ==

= 1.1.0 =
* Added Slack, Telegram, and Mastodon notification channels, joining Email and Discord (all five channels: Email, Discord, Slack, Telegram, Mastodon).
* Added an independent second Telegram target to DM a personal chat, alongside the existing group/channel target.
* Added per-channel "Send test notification" buttons and On/Off/Incomplete status badges on the Schedule notifications tabs.
* Added a dismissible admin notice, plus an optional admin email alert, when a notification channel fails to send.
* Added contextual Help tabs on the Schedule page with per-channel setup instructions and links to hosted setup guides.
* Added a reveal toggle to show/hide sensitive Slack fields (bot token, channel ID, user ID).
* Fixed: the Schedule page now shows the actual default execution time (09:00) instead of an empty time picker when no time has been set.
* Fixed: testing or saving one Slack or Telegram target no longer fails because an unrelated, incomplete target is also enabled.
* Fixed: notification field validation is now scoped per channel, and each channel saves independently.
* Fixed: German translations now load correctly, including the notification-failure notice.
* Fixed: Slack API errors show friendlier messages for common failures (e.g. bot not invited to the channel).

= 1.0.2 =
* Initial release.
* Added a Post Template editor for customising the layout of roundup posts.
* Added Discord, Slack, Telegram, and Mastodon notification channels alongside Email.
* Moved the Slack field reveal/hide toggle inside the input box instead of beside it.
