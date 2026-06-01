# Plan: Fix Diagnostics panel alignment in trigger/manual modes

## Context

When By Count, By Age, or Manual is selected, `NextSchedules` returns `null` — leaving `DiagnosticsPanel` as the first element in the sidebar. `.linkdigest-diagnostics` had `margin-top: 16px` intended to space it from `NextSchedules`, which pushed it 16px below the top of the sidebar instead of aligning with the Mode section.

## Fix

Moved spacing responsibility to the sidebar container instead of the child:

- `.linkdigest-schedule-sidebar` → `display: flex; flex-direction: column; gap: 16px`
- `.linkdigest-diagnostics` → removed `margin-top: 16px`

Flex `gap` only applies between rendered children, so when `NextSchedules` is absent the Diagnostics panel sits flush at the top.

## Files changed

| File | Change |
|------|--------|
| `src/schedule/schedule.css` | Sidebar flex column gap; remove diagnostics margin-top |
