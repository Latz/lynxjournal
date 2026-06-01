# Plan: Improve Mode Picker UI in Scheduler

## Context

The scheduler lets users pick exactly one of six modes: Daily, Weekly, Monthly, Count, Age, Manual. The current UI groups them into three button-groups separated by vertical dividers. This works but has a UX gap: the three separate groups with their own buttons each using primary/secondary style can visually imply "one selection per group" rather than "one selection total." The goal is to make the single-select constraint immediately legible and improve discoverability of what each mode does.

---

## Approach: Radio Cards

Replace `ScheduleTypePicker.jsx` with a grid of **radio cards** — one card per mode, grouped under section labels. The active card gets a blue border; inactive cards are flat/neutral. The layout:

```
Scheduled                          Trigger-based          Manual
┌──────────┐ ┌──────────┐ ┌──────────┐  ┌──────────┐ ┌──────────┐  ┌──────────┐
│  Daily   │ │  Weekly  │ │ Monthly  │  │  Count   │ │   Age    │  │  Manual  │
│          │ │          │ │          │  │          │ │          │  │          │
│ Every N  │ │ Specific │ │ Calendar │  │ N links  │ │ Oldest   │  │ No auto  │
│ days     │ │ weekdays │ │ days     │  │ pending  │ │ link age │  │ publish  │
└──────────┘ └──────────┘ └──────────┘  └──────────┘ └──────────┘  └──────────┘
```

**Why radio cards over alternatives:**
- A single segmented control across all 6 options is too cramped and loses the grouping context
- `RadioControl` from WP components is accessible but doesn't support the three-group layout or card-style descriptions
- Radio cards make single-select unambiguous (only one can have the blue border), carry brief descriptions, and fit the existing section-based layout

---

## Files changed

| File | Change |
|------|--------|
| `src/schedule/components/ScheduleTypePicker.jsx` | Replace button groups with radio cards; no radio indicator |
| `src/schedule/schedule.css` | Add radio card styles; fix button wrapper border on month-day rows |

---

## Follow-up fixes

- **Month-day input border** — `<button>` wrapping `NumberControl` in monthly recurrence was showing browser-default button border. Fixed by adding `border: none; background: none; padding: 0; outline: none` to `.linkdigest-opt`.
- **Radio indicator removed** — circular check element removed from cards; active state is conveyed by blue border + background alone.

---

## Verification

1. `npm run build` — no build errors
2. Open WP admin → LinkDigest → Schedule
3. Confirm only one card is ever highlighted at a time across all three groups
4. Confirm clicking each mode updates the config section below (RecurrenceConfig / TriggerCondition / manual message)
5. Confirm saved state loads with the correct card highlighted on page reload
6. Confirm monthly day rows show no stray border around the number input
7. Run unit tests: `npm run test:js`
