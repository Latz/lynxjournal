# Building a Tab Panel That Never Jumps

## The problem

Take the most common way anyone builds a tab component: a row of tab labels, and a set of content panels, one per tab. Only the active panel is shown; the rest are hidden with `display: none`. Click a different tab, some JavaScript flips the `display` value on the outgoing and incoming panels, done.

It works, and it's wrong in a way that's easy to miss in development and obvious in production: every panel has a different amount of content, so every panel has a different rendered height. Because `display: none` removes an element from layout entirely — it contributes nothing to its container's size, as if it didn't exist — the container's height is, at any given moment, simply the height of whichever panel happens to be active. Switch from a three-line panel to a fifteen-line panel and the container visibly grows underneath the user's cursor. Switch back and it shrinks again. Everything below the tab component — other content, a "Save" button, a footer — jumps up or down in lockstep.

This is not a cosmetic nitpick. It is the textbook definition of a layout shift: content the user was looking at, or was about to click, physically moves. If the click that triggered the tab switch and the next intended click land close together in time, the second one can hit the wrong target after the layout has jumped — a real, reproducible interaction bug, not just an aesthetic one. Search engines quantify exactly this phenomenon as Cumulative Layout Shift for a reason: unexpected movement of on-screen content is one of the more reliably annoying things a UI can do to a person, and it costs nothing to avoid once you know the trick.

## The instinct to avoid: measuring with JavaScript

The obvious fix, once you notice the jump, is to measure. On load, walk every panel, record its rendered height, take the maximum, and lock the container to that height with `min-height`. It works, but it drags in a surprising amount of machinery for what should be a static, three-line CSS rule:

- You can't measure a panel's height while it's `display: none` — a hidden element reports zero for `offsetHeight` regardless of its content. So you have to briefly force each hidden panel visible (off-screen, so nothing flashes), measure it, then hide it again. That's a read-write-read layout thrashing pattern for every panel, on every measurement pass.
- If the tab component itself starts hidden — inside a collapsed accordion, a `<details>` element, a panel that only appears after a click, exactly the situation WordPress's own contextual Help box is in — you can't measure on page load at all, because the whole thing is `display: none` and every descendant reports zero height. You need a `MutationObserver` (or an event hook into whatever reveals the container) to defer the first measurement until the container is actually visible.
- The measurement is a snapshot. It's correct at the instant it was taken and stale forever after. Resize the window, zoom, load a webfont that changes line-wrapping, or change the panel's text content, and the recorded maximum no longer reflects reality. So now you need a `resize` listener too, debounced so you're not thrashing layout on every intermediate frame of a drag-resize, and you need to remember to reset the old `min-height` before re-measuring or the stale value inflates the new measurement.

None of this is exotic — it's normal, working code, and plenty of production sites ship exactly this pattern. But it's also a lot of moving parts, several of them timing-dependent, in service of a problem that CSS can solve outright.

## The technique: stack every panel in the same grid cell

CSS Grid track sizing has a property that turns out to solve this completely: when an `auto`-sized track contains multiple items, the track's size is computed from *every item placed in it*, not just the one you're currently looking at. If you make every panel occupy the same grid row and column — literally overlap them — the grid container's height becomes "as tall as the tallest of the stacked items," continuously and automatically, for as long as the items themselves exist in the DOM with a real (non-`none`) `display` value.

That last clause is the whole trick. A `display: none` element is excluded from grid sizing exactly as it's excluded from any other layout, because it generates no box at all. Grid sizing has no opinion about hidden panels — it can only account for panels that are still, in the technical sense, *there*. So the fix is to stop using `display` to hide inactive panels, and use `visibility` instead:

```css
.tab-panels {
  display: grid;
}

.tab-panel {
  display: block;      /* every panel stays in normal flow */
  grid-column: 1;
  grid-row: 1;          /* ...and in the same cell, overlapping */
  visibility: hidden;
  pointer-events: none;
}

.tab-panel.active {
  visibility: visible;
  pointer-events: auto;
}
```

```html
<div class="tab-panels">
  <div class="tab-panel active" id="panel-one">Short content.</div>
  <div class="tab-panel" id="panel-two">Much longer content that
    wraps onto several lines and would otherwise make this panel
    noticeably taller than the others.</div>
  <div class="tab-panel" id="panel-three">Medium content.</div>
</div>
```

`visibility: hidden` removes a panel from paint and from the accessibility tree, and (like `display: none`) takes it out of the tab order, so hidden panels are neither seen nor reachable — but unlike `display: none`, the element still occupies its box for layout purposes. That's precisely what grid sizing needs to see it. `pointer-events: none` is the belt-and-suspenders companion: since the hidden panels are still boxes stacked in the same cell, without it they'd sit invisibly on top of (or underneath, depending on DOM order) the visible one and could intercept clicks meant for it.

Nothing here is measured. Nothing is cached. The container's height is a live, first-class layout computation the browser is already doing — you're just telling it to consider all the panels instead of one.

## Two things that will bite you if you skip them

**Hide with `visibility`, never `display: none`.** This is the one rule the whole technique hangs on. Reach for `display: none` out of habit — plenty of existing tab-switching JavaScript does exactly that — and the panel vanishes from grid sizing along with everything else, and you're straight back to the original jump.

**Watch for JavaScript that sets `display` directly.** Framework-agnostic tab widgets, and older jQuery-based ones especially, love `.show()` / `.hide()`, which write an inline `style="display: ..."` onto the element. An inline style always wins over an ordinary stylesheet rule, so your `display: block` in CSS would silently lose. The fix is one keyword:

```css
.tab-panel {
  display: block !important;
  /* ...rest as above */
}
```

`!important` on a stylesheet rule *does* beat a non-`!important` inline style — that's one of the few cases where reaching for `!important` isn't a code smell, it's the only lever available when you don't control the script writing the inline style and don't want to rewrite it. If you do control the JS, the cleaner fix is to make it toggle a class instead of calling `.show()`/`.hide()` directly — but the CSS override is a legitimate, minimal patch when you're layering this on top of code you'd rather not touch.

## Why this beats the JavaScript version, concretely

| | JS measurement | CSS grid stacking |
|---|---|---|
| Lines of code | ~40–60 (measure loop, observer, resize handler, debounce) | ~15 (three rules) |
| Correct on first paint if the container starts hidden | Only with a `MutationObserver` watching for reveal | Yes — it's just layout, computed whenever the box becomes visible |
| Stays correct after resize / zoom / font load / text change | Only if you added a `resize` listener and remembered every other trigger | Yes, automatically — there's no snapshot to go stale |
| Works with JavaScript disabled | No | Yes |
| Failure mode when something's missed | Silent: a stale height, or a `0px` measured too early | Visible immediately in a real browser — easy to catch in review |

The JS approach isn't wrong, exactly — it's solving the right problem with more machinery than the problem needs, and every extra moving part (the observer, the debounce timer, the measure-then-hide dance) is a place a future edit can reintroduce the original bug without anyone noticing until a user does. The CSS approach has no timing dimension at all: there's no "before the observer fires" state, no "haven't resized since the last edit" staleness. It's either laid out correctly, right now, by the same engine that lays out everything else on the page, or the rule is missing — and a missing rule is something a code reviewer can see, not something that only shows up under specific timing.

## Where this doesn't apply

If you genuinely want each panel to size to its own content — an accordion where a short panel *should* be short — this technique is the wrong tool; forcing a shared height is the point here, not a side effect to route around.

And if your panels are conditionally mounted and unmounted by a framework (React, Vue, and similar, where switching tabs means the inactive panel's markup isn't just hidden but doesn't exist in the DOM at all), the grid-stacking trick has nothing to overlap — an unmounted component contributes no box, for the same reason a `display: none` one doesn't. The underlying idea still transfers: keep every panel mounted and toggle a visibility flag on it instead of conditionally rendering it. That's a bigger structural decision than a CSS tweak, but it's the same principle — the browser can only size what's actually there.
