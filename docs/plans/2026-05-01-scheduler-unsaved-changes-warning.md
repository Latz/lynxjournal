# Plan: Unsaved-changes warning on scheduler page

## Context

Users can edit the schedule form and then navigate away (click a WP admin menu item, close the tab) without saving. There is no guard, so changes are silently lost. The fix is a standard browser `beforeunload` warning that fires whenever the form differs from the last-saved state.

---

## Approach

Track a `savedForm` alongside the live `form`. After the initial API load and after every successful save, sync `savedForm` to the current values. Whenever `form !== savedForm` (by deep equality), register a `beforeunload` handler; remove it when they match.

---

## Files changed

| File | Change |
|------|--------|
| `src/schedule/App.jsx` | Added `savedForm` state, sync on load/save, `isDirty` derived value, `beforeunload` effect |

---

## Implementation summary

- `savedForm` state (null = not yet loaded) initialised alongside `form`
- After initial API load: both `form` and `savedForm` set to the loaded data
- After successful save: `savedForm` synced to current `form`
- `isDirty = savedForm !== null && JSON.stringify(form) !== JSON.stringify(savedForm)`
- `useEffect` keyed on `isDirty`: adds `beforeunload` handler while dirty, removes it when clean

---

## Verification

1. `npm run build` — no errors
2. Open WP admin → LinkDigest → Schedule
3. Change any field → try to navigate away → browser shows "Leave site?" dialog
4. Save → navigate away → no dialog
5. Reload → change a field → close tab → dialog appears
6. `npm run test:js` — 82 tests passing
