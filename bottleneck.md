# LinkDigest — Bottleneck Check

## Scope
PHP back end (`linkdigest.php` + `src/php/**`). Front-end (React schedule app, dashboard CSS, Chrome extension) was scanned but the dominant bottlenecks are all server-side.

---

## 1. `posts_per_page => -1` everywhere — primary scaling risk 🔴

The plugin loads **the full result set** for every relevant query. With even a few thousand `linkdigest` posts this becomes the biggest single bottleneck (memory + DB time) on the dashboard, the Links page, the cron job, and the dashboard widget on every admin page load.

Locations:

- `src/php/traits/Queries.php:14, 29` – `getPublishStatistics()` loads **all** published and **all** draft IDs purely to `count()` them.
- `src/php/traits/Queries.php:65, 86` – `getLinksGroupedByCategory()` does `posts_per_page=-1` once **per category** plus once for uncategorized → O(categories × N) memory.
- `src/php/traits/Admin/Dashboard.php:80` – `getUnpublishedLinkIds()` returns *all* unpublished IDs; this is fed straight into `batchPublishLinks()` and the cron `executeSchedule()`.
- `src/php/traits/Admin/Dashboard.php:434` – `count(get_terms(...))` materialises every term object just to count them.

Recommended fixes (cheapest first):
1. `getPublishStatistics()` → use `WP_Query` with `'fields' => 'ids', 'posts_per_page' => 1` and read `$q->found_posts`. Or a single `$wpdb` query joining `postmeta` once and `GROUP BY meta_value`. Cache the result in a transient invalidated on `save_post_linkdigest` / `linkdigest_after_publish`.
2. `getLinksGroupedByCategory()` → paginate (the page already has no UI pagination — add one) or run a single query ordered by category, then group in PHP. The current approach scales as O(categories × N) and re-issues the same `meta_query` hot path.
3. `count(get_terms(...))` → `wp_count_terms('linkdigest_category', ['hide_empty' => false])`.
4. `getUnpublishedLinkIds()` → cap with a sane upper bound (e.g. `'posts_per_page' => 500`) and warn / chunk in `batchPublishLinks()`. Prevents OOM if a user accumulates 10k+ links.

---

## 2. Redundant DB calls in the dashboard (every admin page load) 🟠

`addDashboardWidget` is bound on `wp_dashboard_setup`, so `dashboardWidgetContent()` runs whenever the WP main dashboard is rendered. Combined with `dashboardPage()`:

- `dashboardPage()` calls `getPublishStatistics()` (3 queries), then runs **two more `get_posts` with meta_query** (`Admin/Dashboard.php:436` and `:448`) and an extra `get_terms` (`:434`). That's ~6 expensive queries per render.
- `dashboardWidgetContent()` again calls `getPublishStatistics()` (3 more queries) plus another `get_posts` with OR meta_query.

Fix: cache `getPublishStatistics()` and `getUnpublishedLinkIds()` for the request (static memoization on `$this`) **and** a 60–300 s transient. Invalidate on `save_post_linkdigest`, `deleted_post`, and `linkdigest_after_publish`.

---

## 3. Meta-key / meta-value queries without an index 🟠

Every dashboard render hits multiple `meta_query` clauses on `_linkdigest_publish_status`, `_linkdigest_published_date`, `_linkdigest_url`. WordPress' `wp_postmeta` has only a `meta_key` index, no `(meta_key, meta_value)` index, so the OR `NOT EXISTS / NOT IN` patterns force full subselects.

The duplicate-URL check is the clearest symptom (`src/php/traits/RestApi.php:195`):

```php
'meta_key'   => '_linkdigest_url',
'meta_value' => $url,
'numberposts'=> 1,
```

Recommendations:
- Replace meta-based duplicate detection with a custom column on `wp_posts` is overkill — instead store a **hash** of the URL in a meta key and rely on `meta_key` index, or maintain a tiny custom table `wp_linkdigest_urls (post_id, url_hash UNIQUE)`. Even simpler: register the URL as the `post_name` (slug) so WP's existing `post_name` index handles uniqueness.
- For the publish-status filter, consider promoting publish status to **post status** (register custom statuses `lb_unpublished` / `lb_draft` / `lb_published`) — uses the indexed `post_status` column and removes every `meta_query`. This is the highest-ROI change.

---

## 4. N+1 patterns in the publishing path 🟠

- `src/php/traits/Batch.php:104` `groupLinksByCategory()` — `get_post()` + `get_the_terms()` per link.
- `src/php/traits/Batch.php:184` `collectCategoryTerms()` — `get_the_terms()` per link **again**.
- `src/php/traits/Batch.php:199` `assignDigestTags()` — third `get_the_terms()` loop.
- `src/php/traits/Batch.php:130` inside `buildDigestContent()` — `get_post()` + `get_post_meta()` per link.
- `src/php/traits/Batch.php:217` `markLinksAsPublished()` — three `update_post_meta()` calls per link plus `get_post()` again.
- `src/php/traits/Publishing.php:55` `resolveWpCategoryIds()` — `get_category_by_slug()` per linkdigest category.

Fixes:
- Batch-prime caches once at the top of `createDigestPost()`:
  - `_prime_post_caches($link_ids, true, true)` (loads posts, terms, meta in 3 queries total).
  - Single `get_post_meta($id)` returns all meta as array; no further DB calls.
- Replace per-link `update_post_meta` triple with one `update_post_meta(..., 'json_blob')` *or* still 3 calls but no `get_post()` in the loop (already cached after priming).
- `resolveWpCategoryIds` / `resolveOrCreateCategories`: do one `get_terms(['slug' => $slugs])` to resolve everything in one query.

For 500 links a digest currently issues **~5–7 queries × 500 ≈ 2500–3500 queries**; with priming this drops to ~10.

---

## 5. Cron `executeSchedule()` recurses into an unbounded publish 🟠

```php
$link_ids = $this->getUnpublishedLinkIds();      // -1 fetch
...
$this->createDigestPost($link_ids, $title);     // can be huge
```

A single late cron run after a long pause can attempt to publish thousands of links in one PHP process. Combined with the N+1 patterns in §4 this can hit `max_execution_time` mid-digest and leave half-marked links.

Fix: cap to e.g. 200 per run; if more remain, schedule a follow-up `wp_schedule_single_event(time()+60, 'linkdigest_execute_schedule')`. Also wrap in `wp_defer_term_counting(true)` / `wp_defer_comment_counting(true)` and `wp_suspend_cache_invalidation(true)` while bulk-writing meta.

---

## 6. `getNextScheduleTimestamp()` is fine but allocates 366 DateTime objects worst case 🟢

Cosmetic. Not a real bottleneck (microseconds), but the day-by-day search is quadratic-ish if combined with many time slots. Could short-circuit weekly mode by computing the next matching weekday directly. Keep as is unless profiled hot.

---

## 7. Bootstrap costs on every request 🟢/🟠

`linkdigest.php:21` runs `file_get_contents` + `json_decode` on **every** PHP request to the site (even front-end), just to read 2 string constants. With OPcache this is a few µs but still avoidable. 11 `require_once` files run on every request before WP even decides whether the admin / REST hook fires.

Fixes:
- Inline the two strings or set the constants directly; keep `constants.json` as a build-time export consumed only by tests.
- Use Composer/PSR-4 autoloading (you already have `vendor/composer/autoload.php`); only the trait actually used is then loaded — although since they're all `use`d on the class, this saves nothing. The bigger win is **lazy class instantiation**: only build the `LinkDigest` instance when an admin/REST request is detected. Right now `LinkDigest::register()` always runs and binds 14+ hooks even on logged-out front-end requests.

---

## 8. CORS preflight handler runs on every `init` 🟢

`handlePreflight()` (priority 1 on `init`) checks `$_SERVER['REQUEST_METHOD']` for **every** request type, not just REST. Cheap, but makes the OPTIONS exit unreachable for non-REST URLs anyway. Move to `rest_api_init` or guard with `defined('REST_REQUEST')` once REST routing has marked the request. Negligible perf, more about correctness.

---

## 9. `enqueueAdminAssets` filemtime per render 🟢

`src/php/traits/Admin/Menu.php:247` — the hook check `strpos($hook, 'LinkDigest') === false` is **case-sensitive** and the menu slugs are lower-case (`linkdigest-dashboard`). Result: this enqueue **never matches**, so `dashboard.css` is never loaded via this hook in the current code. That's a correctness bug masquerading as a perf win — confirm whether the dashboard is actually styled (probably loaded elsewhere or the bug exists).

---

## 10. Categories transient — partially good, partially not 🟢

`restGetCategories()` uses a 1 h transient (good). But `invalidateCategoriesCache()` only clears `linkdigest_api_categories_list`. The dashboard, Links page, and Add Link page all call `get_terms('linkdigest_category')` directly with no caching at all. Either:
- Reuse the same transient in the admin pages, or
- Rely on WP's object cache (already done by `get_terms`) — fine on sites with persistent object cache (Redis/Memcached), painful on shared hosts without one.

---

## Summary table

| # | Severity | Area | One-line fix |
|---|---|---|---|
| 1 | 🔴 | `posts_per_page=-1` | Use `found_posts` / pagination / capped batches |
| 2 | 🟠 | Dashboard re-queries | Memoize + transient invalidated on save |
| 3 | 🟠 | meta_query on publish_status | Promote to custom post statuses |
| 4 | 🟠 | N+1 in digest path | `_prime_post_caches`, batch term lookups |
| 5 | 🟠 | Cron unbounded | Cap per run + reschedule |
| 6 | 🟢 | Schedule loop | Direct weekday math |
| 7 | 🟠 | Bootstrap on every request | Lazy register, drop json_decode |
| 8 | 🟢 | `handlePreflight` on every init | Gate by REST_REQUEST |
| 9 | 🟢 | `enqueueAdminAssets` hook check | Case-sensitive bug — dashboard css not loaded |
| 10 | 🟢 | Categories cache | Reuse transient in admin |

The two changes with by far the biggest payoff are **#1 (statistics + grouping)** and **#3 (custom post statuses)** — together they remove the dominant cost on the admin dashboard for any non-trivial dataset. **#4** matters once digests exceed ~50 links.
