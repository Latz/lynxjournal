# Bluesky Notifications — User Manual

LynxJournal can send you a private Bluesky direct message every time a
scheduled roundup run happens. This works independently of (and alongside)
the existing email, Discord, Slack, Telegram, and Mastodon notifications.

## Where to find it

Go to **LynxJournal → Schedule** in the WordPress admin menu. In the
**Notifications** section, next to the existing channels, you'll find:

- **Send a Bluesky direct message after each run** — checkbox to enable it.
- **Bluesky handle** — the account the message is sent *from*.
- **Bluesky app password** — a dedicated app password for that account.
- **Recipient handle** — who the message is addressed to (usually you).

## Creating an app password

1. Open the Bluesky app and go to **Settings → App Passwords**.
2. Click **Add App Password**.
3. Give it a distinct name, e.g. "LynxJournal Digest Alerts".
4. Bluesky generates a password in the format `xxxx-xxxx-xxxx-xxxx` — copy it.
   You won't be able to view it again after leaving this screen.

## Enabling it

1. Check **Send a Bluesky direct message after each run**.
2. Enter the sending account's handle (e.g. `you.bsky.social`) in
   **Bluesky handle**.
3. Paste the app password into **Bluesky app password**.
4. Enter the handle to send to (e.g. `friend.bsky.social`) in
   **Recipient handle**.
5. Click **Save Schedule**.

If you leave the checkbox checked but any field empty (or paste values that
don't look valid), saving fails with a validation error and nothing is
stored — fix the values and save again.

### Accepted formats

- **Bluesky handle** / **Recipient handle**: a bare handle, e.g.
  `you.bsky.social` (no `@` prefix).
- **App password**: exactly four groups of four characters separated by
  hyphens, e.g. `aaaa-bbbb-cccc-dddd`.

Anything else is rejected when you try to save.

## When it fires

The Bluesky message fires at the same point as the other notifications:
right after a scheduled run **actually creates or attempts to create a
roundup post**. This only happens when the schedule's trigger condition is
met (e.g. daily mode with at least one pending link, or count/age mode once
enough links have accumulated). If a run happens but there's nothing to
publish yet (no pending links, or a count/age trigger not yet reached),
**no notification is sent at all** — there's simply nothing to report. It's
a separate, independent toggle — mix and match email, Discord, Slack,
Telegram, Mastodon, and Bluesky however you like.

## What the message looks like

Bluesky's Chat API sends a genuine private direct message — unlike Mastodon,
it never appears in the sending account's public timeline. LynxJournal
messages your configured recipient handle directly:

- **If a roundup was published**: the post's title, a line noting how many
  links were included, and the post's URL.
- **If a run reached the point of creating a post but that post creation
  failed**: a plain message explains that the schedule ran in that mode but
  no post was published. This is rare and distinct from "no pending links
  yet", which sends nothing at all (see above).

## If something goes wrong

Sending to Bluesky is "fire and forget": if the app password is no longer
valid, the recipient handle can't be resolved, or the request otherwise
fails at any step of the handshake, there's no retry — but a dismissible
error notice appears in wp-admin naming the Bluesky channel and the reason,
so the failure isn't invisible. Importantly, this never affects the roundup
publish itself; a failed Bluesky notification never blocks or breaks a
scheduled run. If messages stop arriving:
- confirm the app password hasn't been revoked (check **Settings → App
  Passwords** in the Bluesky app),
- confirm the sending handle is spelled correctly,
- confirm the recipient handle is spelled exactly right — a typo there means
  the handle can't be resolved to an account and the message never sends.
