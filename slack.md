# Slack Notifications — User Manual

LynxJournal can notify Slack every time a scheduled roundup run happens,
using a Slack Bot Token. Two independent targets are supported from the
same token: a **channel message** and a **personal message** (DM to a
specific person). This works alongside the existing email and Discord
notifications.

## Where to find it

Go to **LynxJournal → Schedule** in the WordPress admin menu. In the
**Notifications** section, below the email and Discord options, you'll
find:

- **Slack Bot Token** — appears once either Slack option below is enabled.
- **Post to a Slack channel after each run** — checkbox, plus a channel ID
  field when checked.
- **Send me a Slack DM after each run** — checkbox, plus a user ID field
  when checked.

Both can be enabled at the same time, independently — channel only, DM
only, both, or neither.

## Why a bot token instead of a webhook

Discord notifications use a webhook URL, which always posts to one fixed
place. Slack notifications need to reach two different, independently
configurable destinations (a channel *and* a specific person), so
LynxJournal uses a **Slack Bot Token** with Slack's `chat.postMessage` API
instead — one token can post to any channel or DM the bot has access to.

## Setting up a Slack Bot Token

You need a Slack App with a bot token before you can enable either option:

1. Go to [api.slack.com/apps](https://api.slack.com/apps) and click
   **Create New App** → **From scratch**. Name it (e.g. "LynxJournal") and
   pick your workspace.
2. Under **OAuth & Permissions**, scroll to **Scopes → Bot Token Scopes**
   and add:
   - `chat:write` — required for both channel and DM messages.
3. Click **Install to Workspace** (top of the OAuth & Permissions page)
   and approve.
4. Copy the **Bot User OAuth Token** — it starts with `xoxb-`.
5. **For the channel message**: invite the bot to the target channel in
   Slack (`/invite @YourAppName` in that channel), and get the channel ID
   (in Slack, open the channel → **View channel details** → the ID is
   shown near the bottom, or in the channel's URL).
6. **For the DM**: no invite needed, but the recipient's Slack user ID is
   required (in Slack, open their profile → **More** → **Copy member ID**).

## Enabling it

1. Paste the bot token into **Slack Bot Token** (it's masked like a
   password field, since it grants broader access than a single-channel
   webhook and is shared by both options below).
2. To post to a channel: check **Post to a Slack channel after each run**
   and paste the channel ID.
3. To DM someone: check **Send me a Slack DM after each run** and paste
   their user ID.
4. Click **Save Schedule**.

If a checkbox is checked but its required field (token, or the
channel/user ID) is missing or malformed, saving fails with a validation
error — nothing is stored until it's fixed.

### Accepted ID formats

- **Bot token**: must start with `xoxb-`.
- **Channel ID**: starts with `C` (public channel) or `G` (private
  channel), e.g. `C0123456789`.
- **User ID**: starts with `U`, e.g. `U0123456789`.

Channel/DM names (like `#general` or `@someone`) are **not** accepted —
Slack IDs are required, not display names.

## When it fires

Same trigger point as email and Discord: right after a scheduled run
**actually creates or attempts to create a roundup post**. This only
happens once the schedule's trigger condition is met (e.g. daily mode with
at least one pending link, or count/age mode once enough links have
accumulated). If a run happens but there's nothing to publish yet, **no
Slack message is sent at all** — there's simply nothing to report.

## What the message looks like

LynxJournal sends a Slack Block Kit message (not plain text):

- **If a roundup was published**: a header with the post's title, a
  section with a "View post" link to the published post, and a line
  showing the link count and which schedule mode ran.
- **If a run reached the point of creating a post but that post creation
  failed** (rare): a header noting the schedule ran, with a line
  explaining no post was published. This is distinct from "no pending
  links yet", which sends nothing at all (see above).

If both the channel and DM options are enabled, you'll get two separate
messages — one to the channel, one as a DM — for the same run.

## If something goes wrong

Sending to Slack is "fire and forget": if the bot token is revoked, the
channel/user ID is wrong, the bot isn't in the channel, or the request
otherwise fails, the notification is silently skipped — no retry, no error
shown in WordPress. This never affects the roundup publish itself; a
failed Slack notification never blocks or breaks a scheduled run.

Common causes of a missing message:
- The bot hasn't been invited to the target channel (channel messages
  only — DMs don't need an invite).
- The bot token was regenerated/revoked in the Slack App settings.
- The channel or user ID was mistyped or copied from the wrong workspace.

If messages stop arriving, double-check the bot is still in the channel
and the token/IDs are current, then re-save the Schedule page.
