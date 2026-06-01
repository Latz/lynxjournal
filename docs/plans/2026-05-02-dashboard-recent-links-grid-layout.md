# Plan: Recent Unpublished Links — trash icon size + compact grid layout

## Context

Three sequential improvements to the Recent Unpublished Links dashboard box:

1. **Trash icon doubled** — user requested the delete button icon be 2× its original 18 px size (→ 36 px).
2. **Layout-jump fix** — doubling the icon to 36 px made the `.lb-link-item-header` flex container 40 px tall. When the delete button was hidden (`display:none`) and the smaller confirm row appeared, the header shrank and the URL/meta rows below jumped upward. Fixed with `min-height: 40px` on the header.
3. **Compact grid layout** — the large button inside the flex header forced every title row to be 40 px. Restructured to a CSS Grid so the button no longer inflates the text rows, and reduced padding/margins throughout.

## Implementation

### `dashboard.css`

- `.lb-link-item`: `display:grid; grid-template-columns:1fr auto; grid-template-rows:auto auto auto; padding:5px 12px`  
  — three explicit rows (header · url · meta) so `1/-1` spans work correctly.
- `.lb-delete-btn`: `grid-column:2; grid-row:1/-1; align-self:center`  
  — button spans all content rows in its own column; never inflates a text row.
- `.lb-delete-btn .dashicons`: `font-size/width/height: 36px`
- `.lb-delete-confirm-row`: `grid-column:1`  (already handled by group selector)
- Removed `display:flex / min-height` from `.lb-link-item-header`; tightened title/url/meta margins to 0–1 px.

### `src/php/traits/Admin/Dashboard.php`

- Moved `<button class="lb-delete-btn">` out of `<div class="lb-link-item-header">` to be a direct child of `<li class="lb-link-item">`, so it is a grid item in column 2.
- JS confirm-row logic unchanged: `btn.parentElement` is now `<li>`, so the row appends directly into the grid and auto-places below the text rows.

## Files changed

| File | Change |
|------|--------|
| `dashboard.css` | Grid layout, 36 px icon, removed flex/min-height, tighter spacing |
| `src/php/traits/Admin/Dashboard.php` | Button moved outside header div |
