# Telegram Notifications — User Manual

LynxJournal can send a Telegram message every time a scheduled roundup run
happens, using a Telegram bot. This works independently of (and alongside)
the existing email, Discord, and Slack notifications.

## Where to find it

Go to **LynxJournal → Schedule** in the WordPress admin menu. In the
**Notifications** section, below the existing Email/Discord/Slack options,
you'll find two independent Telegram targets, sharing one bot token:

- **Post to a Telegram group or channel after each run** — checkbox to
  enable posting to a group or channel.
- **Telegram bot token** — the token for your bot (shared by both targets
  below).
- **Telegram group/channel chat ID** — the group or channel the bot should
  post to.
- **Send me a Telegram DM after each run** — checkbox to enable a personal
  direct message to you.
- **Telegram user chat ID** — your personal chat ID to DM.

Both targets can be enabled at the same time, each with its own Test and
Save button.

## Creating a Telegram bot

You need a bot token and a chat ID to receive notifications:

1. In Telegram, message **@BotFather** and run `/newbot`.
2. Follow the prompts to name your bot. BotFather replies with a bot token
   that looks like `123456789:AAH...`.
3. Get the chat ID to send messages to:
   - **Personal chat**: send any message to your new bot, then visit
     `https://api.telegram.org/bot<TOKEN>/getUpdates` in a browser and read
     the `message.chat.id` value from the JSON response — or message a
     helper bot like **@userinfobot** to get your own user ID directly.
   - **Group or channel**: add the bot to the group/channel first (channels
     require making the bot an admin), send a message, then check
     `getUpdates` the same way. Group/channel chat IDs are negative numbers,
     e.g. `-1001234567890`.

## Enabling it

**Group/channel target:**
1. Check **Post to a Telegram group or channel after each run**.
2. Paste the bot token into the **Telegram bot token** field.
3. Paste the chat ID into the **Telegram group/channel chat ID** field.
4. Click **Save Schedule** (or the target's own **Save** button).

**Personal DM target:**
1. Check **Send me a Telegram DM after each run**.
2. Paste the bot token into the **Telegram bot token** field (same field as
   above — one token serves both targets).
3. Paste your personal chat ID into the **Telegram user chat ID** field.
4. Click **Save Schedule** (or the target's own **Save** button).

If you leave a checkbox checked but its chat ID field empty (or paste values
that don't look like a real bot token/chat ID), saving fails with a
validation error and nothing is stored — fix the values and save again.

### Accepted formats

- **Bot token**: a numeric bot ID, a colon, and a secret, e.g.
  `123456789:AAAbbbCCCdddEEEfffGGGhhh`.
- **Chat ID**: an integer — negative for groups/channels, positive for a
  personal chat. Both the group/channel and DM chat ID fields accept either
  form; Telegram has no structural marker distinguishing them.

Anything else is rejected when you try to save.

## When it fires

The Telegram message fires at the same point as the other notifications:
right after a scheduled run **actually creates or attempts to create a
roundup post**. This only happens when the schedule's trigger condition is
met (e.g. daily mode with at least one pending link, or count/age mode once
enough links have accumulated). If a run happens but there's nothing to
publish yet (no pending links, or a count/age trigger not yet reached),
**no notification is sent at all** — there's simply nothing to report. It's
a separate, independent toggle — you can mix and match email, Discord,
Slack, and Telegram however you like.

## What the message looks like

- **If a roundup was published**: a bold post title, a line noting how many
  links were included, and the post's URL on its own line (Telegram
  auto-previews the link).
- **If a run reached the point of creating a post but that post creation
  failed**: a plain message explains that the schedule ran in that mode but
  no post was published. This is rare and distinct from "no pending links
  yet", which sends nothing at all (see above).

## If something goes wrong

Sending to Telegram is "fire and forget": if the bot token is no longer
valid, the bot hasn't been messaged/added to the target chat, Telegram is
unreachable, or the request otherwise fails, there's no retry — but a
dismissible error notice appears in wp-admin naming the Telegram
channel/DM target and the reason, so the failure isn't invisible.
Importantly, this never affects the roundup publish itself; a failed
Telegram notification never blocks or breaks a scheduled run. If messages
stop arriving:
- confirm the bot token is still valid (tokens can be revoked via
  @BotFather),
- confirm the chat ID is correct,
- for a personal chat, confirm you've sent the bot at least one message,
- for a group or channel, confirm the bot is still a member (and still an
  admin, for channels).
