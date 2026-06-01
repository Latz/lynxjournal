# Plan: Dashboard heading subtitle polish

## Changes

Four small improvements to the Recent Unpublished Links box and Publish Links box:

1. **Removed "Next run" schedule status bar** — the standalone bar below the stats grid was redundant once the subtitle was added to the box heading.

2. **Calendar icon in heading subtitle** — for daily/weekly/monthly/age modes the heading now shows a `dashicons-calendar-alt` icon before the "next: …" text. `unpublishedLinksSubtitle()` was changed from returning a plain string to returning `['text' => string, 'icon' => string]` so the icon is only rendered for time-based modes, not count mode.

3. **Removed parentheses** from the subtitle — text now reads `next: Jun 1, 2026, 9:00 a.m.` without wrapping `()`.

4. **Proper singular/plural in Publish Links box** — replaced `link(s)` with `_n()`: "1 unpublished link" / "2 unpublished links".

## Files changed

| File | Change |
|------|--------|
| `src/php/traits/Admin/Dashboard.php` | Icon/array return from subtitle method, removed status bar call, `_n()` for publish count |
| `dashboard.css` | `.lb-box-subtitle .dashicons` sizing rule |
