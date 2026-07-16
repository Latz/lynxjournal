# Mastodon Notifications — User Manual

LynxJournal can send you a private Mastodon direct message every time a
scheduled roundup run happens. This works independently of (and alongside)
the existing email, Discord, Slack, and Telegram notifications.

## Where to find it

Go to **LynxJournal → Schedule** in the WordPress admin menu. In the
**Notifications** section, below the existing Email/Discord/Slack/Telegram
options, you'll find:

- **Send a Mastodon direct message after each run** — checkbox to enable it.
- **Mastodon instance URL** — the server your account lives on.
- **Mastodon access token** — from an app you create on that instance.
- **Recipient handle** — who the message is addressed to (usually you).

## Creating a Mastodon application

1. Log into your Mastodon instance and go to **Settings → Development →
   New application**.
2. Name it something like "LynxJournal".
3. Under **Scopes**, check only **write:statuses** — no other permissions
   are needed, and it's good practice to uncheck everything else.
4. Save the application, then open it again and copy the **Access Token**.
   Also note your instance's URL (e.g. `https://mastodon.social`).

## Enabling it

1. Check **Send a Mastodon direct message after each run**.
2. Paste your instance URL into the **Mastodon instance URL** field.
3. Paste the access token into the **Mastodon access token** field.
4. Enter the handle to send to (e.g. `@you@mastodon.social`) in
   **Recipient handle**.
5. Click **Save Schedule**.

If you leave the checkbox checked but any field empty (or paste values that
don't look valid), saving fails with a validation error and nothing is
stored — fix the values and save again.

### Accepted formats

- **Instance URL**: must start with `https://` and include a host, e.g.
  `https://mastodon.social`. Any federated Mastodon instance works.
- **Recipient handle**: a full fediverse handle including both parts, e.g.
  `@you@mastodon.social`.

Anything else is rejected when you try to save.

## When it fires

The Mastodon message fires at the same point as the other notifications:
right after a scheduled run **actually creates or attempts to create a
roundup post**. This only happens when the schedule's trigger condition is
met (e.g. daily mode with at least one pending link, or count/age mode once
enough links have accumulated). If a run happens but there's nothing to
publish yet (no pending links, or a count/age trigger not yet reached),
**no notification is sent at all** — there's simply nothing to report. It's
a separate, independent toggle — mix and match email, Discord, Slack,
Telegram, and Mastodon however you like.

## What the message looks like

Mastodon has no separate DM system — a "direct message" is just a regular
status post whose visibility is restricted to the people mentioned in it.
LynxJournal posts a status addressed to your configured recipient handle:

- **If a roundup was published**: the post's title, a line noting how many
  links were included, and the post's URL.
- **If a run reached the point of creating a post but that post creation
  failed**: a plain message explains that the schedule ran in that mode but
  no post was published. This is rare and distinct from "no pending links
  yet", which sends nothing at all (see above).

Because this is just a visibility-restricted post rather than a true private
channel, it is **not end-to-end encrypted** — treat it the same way you
would any other automated status update.

## If something goes wrong

Sending to Mastodon is "fire and forget": if the access token is no longer
valid, the instance is unreachable, or the request otherwise fails, there's
no retry — but a dismissible error notice appears in wp-admin naming the
Mastodon channel and the reason, so the failure isn't invisible. Importantly,
this never affects the roundup publish itself; a failed Mastodon
notification never blocks or breaks a scheduled run. If messages stop
arriving:
- confirm the access token hasn't been revoked (check **Settings →
  Development** on your instance),
- confirm the instance URL is correct and the instance is reachable,
- confirm the recipient handle is spelled exactly right, including both the
  username and the instance domain.
