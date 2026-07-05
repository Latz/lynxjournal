# Discord Notifications — User Manual

LynxJournal can post a message to a Discord channel every time a scheduled
roundup run happens, using a Discord webhook. This works independently of
(and alongside) the existing email notification.

## Where to find it

Go to **LynxJournal → Schedule** in the WordPress admin menu. In the
**Notifications** section, below the existing "Email me after each run"
option, you'll find:

- **Send a Discord notification after each run** — checkbox to enable it.
- **Discord webhook URL** — appears once the checkbox above is checked.

## Creating a Discord webhook

You need a webhook URL from the Discord channel you want notifications sent
to:

1. In Discord, open **Server Settings → Integrations → Webhooks**.
2. Click **New Webhook**, pick the channel it should post to, and optionally
   rename/re-icon it.
3. Click **Copy Webhook URL**.

The URL looks like:
`https://discord.com/api/webhooks/123456789012345678/AbCdEf...`

## Enabling it

1. Check **Send a Discord notification after each run**.
2. Paste the copied webhook URL into the **Discord webhook URL** field.
3. Click **Save Schedule**.

If you leave the checkbox checked but the URL field empty (or paste
something that isn't a real Discord webhook URL), saving fails with a
validation error and nothing is stored — fix the URL and save again.

### Accepted URL format

The URL must:
- start with `https://`
- point to `discord.com`, `discordapp.com`, `ptb.discord.com`, or
  `canary.discord.com`
- have a path shaped like `/api/webhooks/{id}/{token}` (an optional API
  version segment such as `/api/v10/webhooks/...` is also accepted)

Anything else is rejected when you try to save.

## When it fires

The Discord message fires at the same point as the email notification:
right after a scheduled run **actually creates or attempts to create a
roundup post**. This only happens when the schedule's trigger condition is
met (e.g. daily mode with at least one pending link, or count/age mode once
enough links have accumulated). If a run happens but there's nothing to
publish yet (no pending links, or a count/age trigger not yet reached),
**no notification is sent at all** — there's simply nothing to report. It's
a separate, independent toggle — you can have email only, Discord only,
both, or neither enabled at the same time.

## What the message looks like

LynxJournal posts a rich embed, not a plain text message:

- **If a roundup was published**: the embed title is the post's title
  (linking to the published post), with a description noting how many
  links were included, and fields showing the link count and which
  schedule mode ran. It's colored Discord's "blurple".
- **If a run reached the point of creating a post but that post creation
  failed**: a neutral grey embed explains that the schedule ran in that
  mode but no post was published. This is rare and distinct from "no
  pending links yet", which sends nothing at all (see above).

## If something goes wrong

Sending to Discord is "fire and forget": if the webhook URL is no longer
valid, Discord is unreachable, or the request otherwise fails, the
notification is silently skipped — there's no retry and no error shown in
WordPress. Importantly, this never affects the roundup publish itself; a
failed Discord notification never blocks or breaks a scheduled run. If
messages stop arriving, re-copy a fresh webhook URL from Discord (webhooks
can be deleted/regenerated on Discord's side) and re-save it here.
