# Setting Up Telegram Notifications — A Complete Beginner's Guide

This guide walks you through everything, from zero, to get LynxJournal sending you Telegram messages
after each scheduled roundup run. It assumes you've never created a Telegram bot before and don't know
what a "chat ID" or "bot token" is. Follow the steps in order — don't skip ahead.

By the end, you'll have LynxJournal able to do either or both of:

- **DM you personally** on Telegram after each run, and/or
- **Post to a Telegram group or channel** you manage, after each run.

These are two separate, independent switches inside LynxJournal, and this guide covers setting up both.
If you only want one of them, just skip the steps for the one you don't need — they're clearly marked.

---

## Step 1 — Create your bot with @BotFather

Every Telegram bot is created and managed through Telegram's own official bot, called **BotFather**.

1. Open Telegram (on your phone or desktop — doesn't matter which).
2. In the search bar, type `BotFather` and open the chat with the one that has a blue verified checkmark
   and the username `@BotFather`.
3. Tap **Start** (or type `/start` if you don't see a Start button).
4. Type `/newbot` and send it.
5. BotFather will ask for a **name** for your bot. This is just the display name people see, e.g.
   `LynxJournal Digest`. Type it and send.
6. BotFather will then ask for a **username** for your bot. This must be unique across all of Telegram and
   must end in `bot`, e.g. `lynxdigest_bot` or `my_journal_notify_bot`. If it's taken, BotFather will tell
   you and you try another.
7. Once accepted, BotFather replies with a congratulations message that includes a line like:

   ```
   Use this token to access the HTTP API:
   8948896952:AAHxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
   ```

   **This long string is your bot token.** Copy it somewhere safe (a notes app, password manager, etc.)
   — you'll paste it into LynxJournal in Step 4. Treat it like a password: anyone with this token can
   control your bot and send messages as it.

That's it — your bot now exists. It doesn't do anything yet; LynxJournal will use this token to send
messages through it.

---

## Step 2 — Get your personal chat ID (only needed for the "DM me" option)

Skip this step if you only want the bot posting to a group/channel and don't want a personal DM.

A "chat ID" is just a number that tells Telegram *which* conversation to send a message into. For a
personal DM, that's the conversation between you and your bot — but that conversation doesn't exist yet
until you message the bot first, because **bots can never start a conversation with you first; you always
have to message the bot before it can message you.**

1. In Telegram, search for the bot username you created in Step 1 (e.g. `@lynxdigest_bot`) and open its
   chat.
2. Tap **Start**, or just type any message like `hi` and send it. This is essential — without this step,
   nothing else in this guide will work.
3. Now, on your computer, open a web browser and go to this address, replacing `<TOKEN>` with your actual
   bot token from Step 1 (keep the word `bot` right before your token, with no space):

   ```
   https://api.telegram.org/bot<TOKEN>/getUpdates
   ```

   For example, if your token is `8948896952:AAHxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx`, you'd visit:

   ```
   https://api.telegram.org/bot8948896952:AAHxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx/getUpdates
   ```

4. You'll see a page of text that looks like code (this is JSON — don't worry about understanding all of
   it). Look for a section that looks like this:

   ```json
   "chat": {
     "id": 987654321,
     "first_name": "Jane",
     "type": "private"
   }
   ```

5. The number next to `"id"` inside `"chat"` — in this example `987654321` — is **your personal chat ID**.
   Write it down. It will be a positive number (no minus sign).

**Alternative shortcut:** if the above feels confusing, you can instead message the bot **@userinfobot**
(search for it, tap Start, and it will immediately reply with your Telegram user ID) — that number is the
same thing as your personal chat ID.

**If you see `{"ok":true,"result":[]}`** (an empty list) instead of a chat section — this means Telegram
has no record of you messaging the bot yet. Go back to sub-step 2 above and make sure you actually sent a
message to the correct bot, then reload the `getUpdates` page.

---

## Step 3 — Get a group or channel chat ID (only needed for the "post to a group/channel" option)

Skip this step if you only want a personal DM and don't want the bot posting to a group or channel.

1. Open (or create) the Telegram group or channel you want the roundup posted to.
2. Add your bot to it:
   - **For a group**: open the group, tap the group name at the top → **Add members** → search for your
     bot's username → add it.
   - **For a channel**: open the channel → **Administrators** (or **Manage Channel** → **Administrators**)
     → **Add Admin** → search for your bot's username → add it and give it permission to **Post Messages**
     (channels require the bot to be an admin to post; groups don't).
3. Send any message in the group/channel (e.g. "test") so Telegram registers an update.
4. Visit the same `getUpdates` URL from Step 2 (`https://api.telegram.org/bot<TOKEN>/getUpdates`) again in
   your browser.
5. Look for a `"chat"` section, but this time `"type"` will say `"group"`, `"supergroup"`, or `"channel"`,
   and the `"id"` will be a **negative** number, e.g. `-1001234567890`. That's your group/channel chat ID
   — write it down.

---

## Step 4 — Enter everything into LynxJournal

1. In your WordPress admin, go to **LynxJournal → Schedule**.
2. Scroll down to the **Notifications** section and click it open if it's collapsed.
3. Click the **Telegram** tab.

You'll see two separate blocks, one above the other. Fill in only the one(s) you want:

**To post to a group or channel:**
- Check **"Post to a Telegram group or channel after each run"**.
- Paste your bot token (from Step 1) into **"Telegram bot token"**.
- Paste your group/channel chat ID (from Step 3, the negative number) into **"Telegram group/channel chat
  ID"**.
- Click that block's **Save** button (or the page's **Save Schedule** button).

**To get a personal DM:**
- Check **"Send me a Telegram DM after each run"**.
- Paste the same bot token into **"Telegram bot token"** (the token field is shared by both blocks — you
  only need to enter it once, either block).
- Paste your personal chat ID (from Step 2, the positive number) into **"Telegram user chat ID"**.
- Click that block's **Save** button (or the page's **Save Schedule** button).

You can fill in and enable both blocks at once if you want both a group post and a personal DM.

---

## Step 5 — Test it

Each block has its own **"Send test notification"** button, separate from the group/channel and DM
targets — use whichever one(s) you just configured.

- Click it, wait a moment.
- **Success**: a green notice appears saying the test notification was sent, and you should receive a
  message on Telegram within a few seconds.
- **Failure**: a red notice appears with an error message. See Troubleshooting below to match the error
  to a fix.

---

## Troubleshooting

### "notify.telegramChatId is required when Telegram notifications are enabled"

This means the **group/channel** checkbox ("Post to a Telegram group or channel after each run") is
checked, but its own chat ID field ("Telegram group/channel chat ID") is empty. This commonly happens if
you meant to set up a personal DM but checked the wrong box. Fix: either uncheck the group/channel box (if
you don't want it) and only use the DM block, or fill in the group/channel chat ID if you do want it.
There's an equivalent DM version of this error if the DM checkbox is on but its chat ID field is empty.

### `{"ok":false,"error_code":400,"description":"Bad Request: chat not found"}`

This error comes straight from Telegram, not from LynxJournal — it means the bot has no record of ever
being in a conversation with that chat ID. It almost always means one of:

- You never actually sent a message to the bot (see Step 2 or 3 above — you must message the bot before
  it can message you).
- The chat ID you entered doesn't belong to this bot at all (e.g. copy-pasted from a different bot's
  setup, or a typo).

**How to check which it is:**

1. Re-visit `https://api.telegram.org/bot<TOKEN>/getUpdates` using the *exact* token currently saved in
   LynxJournal's "Telegram bot token" field.
2. **If you see `{"ok":true,"result":[]}`** (empty) — the bot has genuinely never received any message.
   Go to the bot's chat in Telegram and message it again (see Step 2), then re-check `getUpdates`. If it's
   still empty after that, see "Empty getUpdates result" below — there may be a webhook problem.
3. **If you do see a `"chat"` entry** — carefully compare the `"id"` number there against exactly what you
   typed into LynxJournal's chat ID field. A single digit off, or an extra space/character pasted in, will
   cause this exact error.

### Empty `getUpdates` result even after messaging the bot

If you're sure you messaged the bot and `getUpdates` still returns `{"ok":true,"result":[]}`, check these
two things:

1. **Confirm you messaged the right bot.** Visit `https://api.telegram.org/bot<TOKEN>/getMe` (same token
   as in LynxJournal). It returns something like:

   ```json
   {"ok":true,"result":{"id":8948896952,"is_bot":true,"first_name":"LynxDigest","username":"lynxdigest_bot", ...}}
   ```

   The `"username"` field is the bot's real, current identity for this token. Go back to Telegram and
   confirm you messaged *this exact* username — it's easy to accidentally message a different bot with a
   similar name, or an old bot from an earlier attempt.

2. **Check for a stuck webhook.** Visit `https://api.telegram.org/bot<TOKEN>/getWebhookInfo`. If the
   `"url"` field in the response is **not empty**, that's the problem: Telegram bots can receive updates
   either via `getUpdates` polling *or* via a webhook, never both. If a webhook was set up at some point
   (even accidentally, by a different tool or a previous test), all your messages are being silently
   delivered there instead of showing up in `getUpdates` — and LynxJournal (and this whole guide) relies
   on the bot being in plain polling mode with no webhook.

   **Fix:** visit `https://api.telegram.org/bot<TOKEN>/deleteWebhook` in your browser. You should get back
   `{"ok":true,"result":true,"description":"Webhook was deleted"}`. Then go back to Telegram, send the bot
   a fresh message, and check `getUpdates` again — it should now show your message.

### Bot token invalid / "Unauthorized"

If testing returns an error mentioning the token or "Unauthorized" (rather than "chat not found"), the
token itself is wrong or was revoked. Go back to @BotFather, send `/mybots`, select your bot, choose **API
Token**, and either copy the current token again or tap **Revoke current token** to generate a fresh one
— then update the "Telegram bot token" field in LynxJournal with the new value and save again.

### It used to work, now messages have stopped arriving

- **Personal DM stopped**: you may have blocked the bot by accident. Unblock it in Telegram (open its
  chat → the block banner will have an unblock option) and send it a message again.
- **Group/channel post stopped**: check the bot hasn't been removed from the group, or (for channels)
  demoted from admin — it needs to still be a member (and admin, for channels) to post.
- **Either**: the bot token may have been revoked/regenerated via BotFather since you last saved it — see
  the "Bot token invalid" section above.

Telegram notifications are "fire and forget" — if a send fails for any reason, LynxJournal doesn't retry
and doesn't show an error anywhere except when you use the **Test** button. A failed Telegram notification
never affects the actual scheduled roundup publishing — that always continues normally regardless.
