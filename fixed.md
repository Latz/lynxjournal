# Security Fixes Applied

## File: `src/php/traits/Admin/LinksPage.php`

### 1. Per-action, per-post capability checks (commit `571c328`)

**Problem:** All four link actions (publish, draft, unpublish, delete) were gated by a single
`current_user_can('edit_posts')` check — a global capability with no awareness of post ownership.
An Author or Contributor could reach the delete or unpublish flow for posts they do not own.

**Fix:** Removed the broad `edit_posts` gate and added per-action capability checks inside each
branch, after nonce verification:

| Action | Capability |
|---|---|
| `publish_link` | `current_user_can('publish_post', $link_id)` |
| `draft_link` | `current_user_can('edit_post', $link_id)` |
| `unpublish_link` | `current_user_can('edit_post', $link_id)` |
| `delete` | `current_user_can('delete_post', $link_id)` |

---

### 2. Early nonce verification via action map (commit `b92f344`)

**Problem:** The nonce was extracted into an intermediate `$nonce` variable and verified inside
four separate `if/elseif` branches. PHPCS could not trace `$nonce` back to `$_GET['_wpnonce']`,
requiring a suppression comment on that read.

**Fix:** Replaced the four per-branch nonce checks with a single upfront gate using a nonce-action
map. `$_GET['_wpnonce']` is now passed directly into `wp_verify_nonce()`, which PHPCS can trace,
removing the need for the suppression comment on that line. Unknown actions are also rejected
before the nonce is checked.
