# Changelog

All notable changes to this project are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

### Added

- **[Major]** Optional email alert to the site admin when a notification channel fails to send,
  independent of the admin address (opt-in, with its own alert-email field) — complements the
  dismissible admin notice below.
- **[Major]** Dismissible WordPress admin notice when a notification channel fails to send — whether
  from a scheduled run or a "Send test notification" click — naming the channel, the time, and the
  reason, so failures are no longer silent.
- Contextual Help tabs on the Schedule page with per-channel setup instructions and deep links to the
  hosted setup guides.
- A second, independent Telegram target to DM a personal chat, alongside the existing group/channel
  target, sharing one bot token.
- The Notifications section now remembers whether it's collapsed or expanded across page loads.
- A beginner-friendly Telegram bot setup guide (`telegram_setup.md`).
- `ScheduleMode` enum, WP plugin checker script, and dist build hygiene tooling.
- Test infrastructure: MSW, vitest-axe, and `@testing-library/react` component tests.
- Slack, Telegram, and Mastodon notification channels alongside Email.
- Per-channel "Send test notification" buttons on the Schedule notifications tabs.
- Colored On/Off/Incomplete status badges on each notification tab.
- Reveal toggle to show/hide the Slack bot token, channel ID, and user ID fields.
- Left/right scroll buttons on the notification tab bar for narrow viewports.

### Fixed

- Merged the `messaging` branch's multi-channel notification system (Discord/Slack/Telegram/Mastodon/
  Email) into master alongside master's Categories & Tags, Post Template, and CI/tooling work.
- Testing or saving one Slack or Telegram target no longer fails because the *other*, unrelated target
  (e.g. the DM tab) is enabled but incomplete.
- Removed eight dead `maybeSend*Notification()` methods that were never hooked to any action; the real
  send path is `LynxJournal_Notify_Manager::runAfterPublish()`.
- Reworded the plugin description to more clearly describe self-hosted link aggregation and micro-blogging functionality.
- German translations now actually load, with previously-untranslated strings wrapped and the German
  catalog fully translated, including the new notification-failure notice.
- Schedule mode cards no longer differ in height when translated text wraps to multiple lines.
- The Help link icon on the Notifications section now has a matching underline/focus style.
- The reveal-toggle eye button no longer shows a clashing outline when clicked (keyboard focus still
  shows one, for accessibility).
- The reveal-toggle eye is now precisely vertically centered inside its input.
- Reorganized the Telegram and Slack notification tabs into clearly labeled, bordered groups per target,
  and relabeled ambiguous Telegram fields that could cause a confusing validation error.
- PHP 8.2 modernization, multisite test coverage, and suppressed composer deprecation noise.
- Pinned TypeScript version to resolve an `npm ci` failure in CI.
- Corrected documentation on when Discord notifications actually fire.
- Notification field validation is now scoped per-channel, and each channel saves independently.
- Notification tab panel no longer shifts the layout below it when switching tabs.
- Notification tab bar scroll buttons only appear when the tabs actually overflow.
- Slack API errors now show friendlier messages for common failures (e.g. bot not invited to channel).
- The Slack reveal/hide toggle button now sits inside the input box instead of beside it.

## [1.0.2]

### Added

- Initial release.
