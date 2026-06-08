=== LynxJournal ===
Contributors: latz
Tags: links, blogging, roundup, curation
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Save and publish curated link digests to your blog.

== Description ==

LynxJournal is a WordPress plugin for managing and publishing curated link digests. Save interesting links, organise them by category, and publish them as blog post digests.

**Features:**

* Save links with title, URL, description, categories, and tags
* Organise links by category (inspired by frankysnotes.com)
* REST API for integration with browser extensions
* Schedule automatic roundup publishing (daily, weekly, monthly, or by count/age)
* Chrome extension support

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/lynxjournal` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Use the LynxJournal menu in the admin dashboard to start adding links.

== Usage ==

=== Dashboard ===

The LynxJournal dashboard (LynxJournal › Dashboard) gives you an at-a-glance overview:

* **Stats bar** — total links, categories, published, and unpublished counts
* **Quick Add** — enter a title and URL to save a link in seconds without leaving the dashboard
* **Recent Unpublished** — the last five unsaved links; delete any of them directly from this list
* **Recently Published** — the last five published roundup posts with their status

=== Adding Links ===

**Manually (full form):** Go to LynxJournal › Add Link. Fill in:

* Title (required)
* URL
* Description (rich text)
* Categories — assign to one or more existing categories
* Tags — comma-separated keywords

**Quick add:** Use the Quick Add box on the Dashboard for a bare-minimum title + URL entry.

**Via Chrome extension:** Browse to any page and click the extension icon. The title and URL are pre-filled; add a description, pick a category, and click Save Link.

=== Managing Links ===

LynxJournal › All Links shows every saved link in a table:

* **Status badges** — Unpublished, Draft, or Published
* **Publish** — creates a WordPress post immediately for that single link
* **Delete** — removes the link permanently (shows an inline confirmation first)

=== Publishing ===

**Individual post:** Click Publish on any link in All Links. A new WordPress post is created with the link's title, description, and a "Read more" link to the source URL.

**Roundup post:** Click Publish on the Dashboard. All unpublished links are bundled into one post, grouped by category. Enter a custom title or leave the default ("Links Roundup – [date]"). Choose to publish immediately or save as draft.

Both flows support draft mode — use the Draft toggle before confirming.

=== Scheduling ===

LynxJournal › Schedule lets automatic roundup publishing run without manual action. Open **LynxJournal › Schedule**, choose a mode, set at least one Execution Time, and click **Save Schedule**. The next time that moment arrives, LynxJournal publishes a roundup post from all unpublished links. To trigger a run immediately, use the **Run Now** button on the dashboard. To stop all automation, switch to Manual mode.

**Modes**

* **Daily** — Publishes at a fixed time every days, provided at least one unpublished link exists.
* **Weekly** — Publishes on specific days of the week. Toggle any combination of Mon–Sun. At least one day must be selected.
* **Monthly** — Publishes on dates you define, every month. Each "day entry" is either a fixed calendar day (1–31) or an ordinal weekday ("the first Monday"). Multiple entries produce multiple runs per month. If a month is shorter than the configured day (e.g. 31st in a 30-day month), that entry is skipped for that month.
* **By Count** — Publishes when the number of unpublished links reaches or exceeds a threshold. The check runs at every configured execution time.
* **By Age** — Publishes when the oldest unpublished link is older than N days. The check runs at every configured execution time.
* **Manual** — Disables all automatic publishing. Use **Run Now** or the dashboard Publish form to publish on demand.

**Execution Times**

Applies to all modes except Manual. Specify one or more 24-hour times (HH:MM) at which the scheduler wakes up. Click **+ Add time** to add a row; click **✕** to remove one. At least one time must remain. Duplicate values are rejected on save. Adding multiple times means the scheduler can publish more than once per day.

**Previewing upcoming runs**

In Daily, Weekly, and Monthly modes the **Next 10 Schedules** sidebar panel lists the next ten wake-up dates based on your current (unsaved) settings. Use it to verify your recurrence pattern before saving.

**Saving the schedule**

Click **Save Schedule** at the bottom of the settings column. On save, any previously pending cron event is cancelled and a new one is scheduled for the next matching time.

**Running the schedule immediately**

The **Run Now** button on the LynxJournal dashboard triggers an immediate publish run. It respects the same publish condition as the automatic schedule. In every mode, the run is silently skipped when there are no unpublished links — no empty roundup is ever created. The automatic schedule is unaffected.

**What a roundup post looks like**

Each run creates one WordPress post titled `Links: [Full Date]` (e.g. `Links: April 28, 2026`), published immediately as a standard post. The body contains one section per category, each with a list of links. Each link shows its title (linked to the saved URL, opening in a new tab) and, optionally, a description below it. Uncategorised links appear in their own section at the end. Every included link is marked as published and excluded from future digests.

**Edge cases and caveats**

* **WP-Cron timing** — WordPress's built-in cron fires when a visitor loads a page. On low-traffic sites, the actual run may be a few minutes late. For precise timing, set up a real system cron job (see Server-side cron setup below).
* **Timezone and DST** — Execution times use your WordPress site timezone (Settings › General › Timezone). On daylight-saving transitions, spring-forward runs may be delayed by ~1 hour; fall-back runs fire once at the first occurrence of the repeated local time.
* **Backfill after downtime** — Missed events are not backfilled. As soon as WordPress runs again, a single catch-up run executes and the next slot is computed from the current time. Use **Run Now** to force a catch-up immediately.
* **Deactivating the plugin** — Deactivation automatically cancels any pending cron event. No event is re-registered until you save the schedule again after reactivation.
* **Uninstalling the plugin** — Uninstall does not delete saved links or the `lynxjournal_schedule` option. To clean up, run `wp option delete lynxjournal_schedule` before reinstalling.

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
4. In WordPress, go to LynxJournal › Settings, generate an API key, and copy the API Endpoint URL and key
5. Click the extension icon › **Settings**, paste both values, and save
6. From now on, click the extension icon on any page to save the current URL directly to your WordPress site

== Source Code & Build Tools ==
The production JavaScript assets in `schedule/schedule.js` are compiled from React components. 
The unminified source code, build scripts, and configuration files are publicly maintained at:
https://github.com/Latz/lynxjournal

== Changelog ==

= 1.0.2 =
* Initial release.
