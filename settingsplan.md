# Plan: Settings X — Card Grid Experimental Settings Page

## Context

The user wants a new admin page called "Setting X" under the LinkDigest menu. The UI design comes from `wordpress_card_grid_ui.pdf`, which specifies:
- A **2×2 card grid** layout (flex-wrap, min-width 320px per card)
- **CSS toggle switches** (custom styled checkboxes, no JS required)
- A **glassmorphism sticky save bar** fixed at the bottom (backdrop-filter blur)
- Standard WordPress Settings API form flow

This is an experimental scaffold — the cards hold placeholder settings that can be replaced with real ones later. The implementation follows existing LinkDigest patterns: all PHP in traits, CSS appended to `dashboard.css`, hooks registered in `class-linkdigest.php`.

---

## Files to Modify

| File | Change |
|---|---|
| `src/php/traits/Admin/Menu.php` | Add `settingXPage()` render method; add submenu in `adminMenu()`; add CSS enqueue in `enqueueAdminAssets()` |
| `src/php/class-linkdigest.php` | Register `admin_init` hook for `registerSettingX()` |
| `dashboard.css` | Append card grid, toggle switch, and sticky bar CSS |

No new files needed — everything fits existing structure.

---

## Step-by-Step Implementation

### 1. Register Settings (`class-linkdigest.php`)

Add one hook in the `register()` method, alongside the existing `admin_menu` hook:

```php
add_action('admin_init', [$instance, 'registerSettingX']);
```

### 2. Add `registerSettingX()` to `Menu.php`

Registers a single WP option that stores all card settings as an array:

```php
public function registerSettingX(): void {
    register_setting('linkdigest_x_group', 'linkdigest_x_settings', [
        'sanitize_callback' => [$this, 'sanitizeSettingX'],
    ]);
}

public function sanitizeSettingX(mixed $input): array {
    $clean = [];
    $toggles = ['auto_publish', 'compact_view', 'public_api', 'auto_trash'];
    foreach ($toggles as $key) {
        $clean[$key] = !empty($input[$key]) ? 1 : 0;
    }
    $clean['accent_color'] = isset($input['accent_color'])
        ? sanitize_hex_color($input['accent_color']) ?? '#2271b1'
        : '#2271b1';
    $clean['trash_after_days'] = isset($input['trash_after_days'])
        ? absint($input['trash_after_days'])
        : 30;
    return $clean;
}
```

### 3. Add Submenu in `adminMenu()` (`Menu.php`)

Insert after the existing Settings submenu:

```php
add_submenu_page(
    'linkdigest-dashboard',
    __('Setting X', 'linkdigest'),
    __('Setting X', 'linkdigest'),
    'manage_options',
    'linkdigest-setting-x',
    [$this, 'settingXPage']
);
```

### 4. Add `settingXPage()` render method (`Menu.php`)

Four cards in a 2×2 grid:

- **Card 1 — Publishing** (`dashicons-admin-post`): toggles for "Auto-publish links" and "Digest notifications"
- **Card 2 — Display** (`dashicons-art`): color picker for accent color, toggle for compact view
- **Card 3 — API** (`dashicons-rest-api`): toggles for "Enable public API" and "Allow CORS"
- **Card 4 — Cleanup** (`dashicons-trash`): toggle for "Auto-trash after publishing", number input for days

All within a `<form action="options.php" method="post">` with `settings_fields('linkdigest_x_group')`.

Sticky save bar rendered inside the form, outside the grid `<div>`.

### 5. Update `enqueueAdminAssets()` (`Menu.php`)

Add a condition alongside the existing `linkdigest-schedule` check. No JS needed — the sticky bar uses CSS only (no "changes detected" JS behaviour at this stage):

```php
// dashboard.css already loaded for all linkdigest pages — no extra enqueue needed.
// Sticky bar CSS will be in dashboard.css.
```

The page slug `linkdigest-setting-x` already passes the `strpos($hook, 'linkdigest')` check, so `dashboard.css` and dashicons load automatically.

### 6. Append CSS to `dashboard.css`

Add at the end of the file:

```css
/* === Setting X: Card Grid === */
.lb-x-grid {
    display: flex; flex-wrap: wrap; gap: 20px;
    margin-top: 20px; padding-bottom: 80px;
}
.lb-x-card {
    background: #fff; border: 1px solid #ccd0d4; border-radius: 8px;
    padding: 20px; flex: 1 1 calc(50% - 20px); min-width: 320px;
    box-shadow: 0 2px 4px rgba(0,0,0,.03);
}
.lb-x-card h3 {
    margin: 0 0 10px; display: flex; align-items: center; font-size: 1.1rem;
}
.lb-x-card h3 .dashicons { margin-right: 8px; color: #2271b1; }

/* Toggle switch */
.lb-toggle { position: relative; display: inline-block; width: 40px; height: 22px; }
.lb-toggle input { opacity: 0; width: 0; height: 0; }
.lb-slider {
    position: absolute; cursor: pointer; inset: 0;
    background: #ccc; transition: .3s; border-radius: 20px;
}
.lb-slider::before {
    position: absolute; content: ""; height: 16px; width: 16px;
    left: 3px; bottom: 3px; background: #fff; transition: .3s; border-radius: 50%;
}
.lb-toggle input:checked + .lb-slider { background: #2271b1; }
.lb-toggle input:checked + .lb-slider::before { transform: translateX(18px); }

/* Sticky save bar */
.lb-x-sticky-bar {
    position: fixed; bottom: 0; left: 160px; right: 0;
    padding: 14px 30px; background: rgba(255,255,255,.88);
    backdrop-filter: blur(8px); border-top: 1px solid #ddd;
    z-index: 100; box-shadow: 0 -4px 14px rgba(0,0,0,.06);
}
.lb-x-sticky-bar .inside {
    display: flex; justify-content: flex-end; align-items: center;
    max-width: 1200px;
}
@media (max-width: 782px) { .lb-x-sticky-bar { left: 0; } }
```

---

## Verification

1. Navigate to **LinkDigest → Setting X** in WP admin — page renders with 4 cards.
2. Toggle any switch → sticky bar is visible at the bottom.
3. Click **Save All Changes** → page reloads, toggles retain their saved state.
4. Inspect: accent color saved via `get_option('linkdigest_x_settings')['accent_color']`.
5. No JS errors in console.
6. Mobile: sticky bar spans full width (left: 0).
