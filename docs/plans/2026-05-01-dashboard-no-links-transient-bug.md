# Plan: Dashboard shows "No links yet" despite saved links

## Context

The dashboard reads link counts from a `linkdigest_publish_stats` transient (300-second TTL). The transient was invalidated after publish runs, unpublish, and migration — but not when a link was added or deleted. When a user added their first link after visiting the dashboard, the transient stayed at 0 for up to 5 minutes, causing "No links yet" to persist despite the link being correctly stored with `linkdigest_pending` status.

## Fix

Added `delete_transient('linkdigest_publish_stats')` at the three mutation sites that were missing it:

| File | Function | Location |
|------|----------|----------|
| `src/php/traits/RestApi.php` | `restAddLink()` | After successful `wp_insert_post` |
| `src/php/traits/RestApi.php` | `restDeleteLink()` | After successful `wp_delete_post` |
| `src/php/traits/Admin/AddLink.php` | `insertNewLink()` | After taxonomy assignment, before `$_POST` clear |
