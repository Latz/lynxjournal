# LinkDigest — Notifications

After each publish run, LinkDigest can notify you via **email**, **Discord**, **Slack**, and **Telegram**. All channels are independent — enable any combination. Notifications fire only when a digest post is actually created; skipped runs produce nothing.

All notification settings live in **LinkDigest › Settings**.

---

## Email

Email notifications are sent to an address you specify. If the field is left blank, the WordPress admin email is used as the fallback.

### How to enable

1. Go to **LinkDigest › Settings**.
2. Check **Email me after each run**.
3. Optionally enter an address in the **Email address** field. Leave it blank to use the site admin email.
4. Click **Save Settings**.

### Testing

Click **Test** next to the email field. A test message is sent immediately; a snackbar confirms success or reports the error.

### What the message looks like

**Subject:** `[LinkDigest] Digest published: 12 links`

**Body:**
```
A new digest was published.

Links: 12
View: https://example.com/links-may-8-2026/
```

If the run produced no post, the body reads: `Schedule ran in daily mode but no post was published.`

### Notes

- Delivery depends on your server's mail configuration. If email does not arrive, check `wp_mail()` logs or install an SMTP plugin.
- The **enabled** checkbox controls whether email fires. The address field is always visible so you can fill it in before enabling.

---

## Discord

### How to enable

1. Open your Discord server and go to **Server Settings › Integrations › Webhooks**.
2. Click **New Webhook**, choose a channel, and copy the webhook URL.
3. Go to **LinkDigest › Settings**.
4. Paste the URL into **Discord Webhook URL**.
5. Click **Save Settings**.

To disable, clear the field and save.

### Testing

Click **Test** next to the Discord field. The button is disabled until you enter a URL.

### What the message looks like

> **LinkDigest: digest published**
> 12 links published. [View post](https://example.com/links-may-8-2026/)

The embed uses a blue accent colour (`#2D9BF0`). If no post was published, the description reads: `12 links processed. No post published.`

---

## Slack

### How to enable

1. Go to [api.slack.com/apps](https://api.slack.com/apps) and open or create an app.
2. Under **Features**, click **Incoming Webhooks** and toggle it on.
3. Click **Add New Webhook to Workspace**, pick a channel, and copy the webhook URL.
4. Go to **LinkDigest › Settings**.
5. Paste the URL into **Slack Webhook URL**.
6. Click **Save Settings**.

To disable, clear the field and save.

### Testing

Click **Test** next to the Slack field. The button is disabled until you enter a URL.

### What the message looks like

> **LinkDigest:** 12 links published. [View post](https://example.com/links-may-8-2026/)

---

## Telegram

Telegram notifications are sent via the Telegram Bot API. You need two things: a **bot token** (identifies your bot) and a **chat ID** (identifies where to send the message). Both are free and take about five minutes to set up.

---

### Step 1 — Create a bot with BotFather

BotFather is Telegram's official bot-management bot. Everything starts here.

1. Open Telegram (desktop, web, or mobile) and search for **@BotFather**, or open `https://t.me/BotFather` directly.
2. Start the chat if you haven't before — click **Start** or send `/start`.
3. Send the command:
   ```
   /newbot
   ```
4. BotFather asks for a **display name** — this is what people see in chats. It can be anything, e.g. `My Site Notifier`.
5. BotFather then asks for a **username** — this must be unique across all of Telegram and must end in `bot`, e.g. `mysitenotifier_bot`.
6. On success, BotFather replies with a message containing your token:

   ```
   Done! Congratulations on your new bot. You will find it at t.me/mysitenotifier_bot.
   Use this token to access the HTTP API:
   1234567890:AAFsomeRandomCharactersHere-xyz
   ```

7. Copy the token. It is the entire string after "Use this token:" — numbers, colon, and letters included. Keep it secret; anyone with the token can send messages as your bot.

> **Already have a bot?** You can reuse an existing one. Send `/mybots` to BotFather, select the bot, then **API Token** to retrieve the token.

---

### Step 2 — Get a chat ID

The chat ID tells the bot *where* to deliver messages. The process differs slightly depending on whether you want notifications in a private chat, a group, or a channel.

#### Option A — Private chat (notifications go only to you)

This is the simplest setup.

1. In Telegram, search for your bot by its username (e.g. `@mysitenotifier_bot`) and open the chat.
2. Click **Start** (or send `/start`). This is required — a bot cannot message you until you initiate contact.
3. Send any message to the bot, for example: `hello`.
4. In a browser, open the following URL — substituting the token BotFather gave you in Step 1 for `1234567890:AAFsomeRandomCharactersHere-xyz`:
   ```
   https://api.telegram.org/bot1234567890:AAFsomeRandomCharactersHere-xyz/getUpdates
   ```
   `bot` is a fixed literal prefix required by the Telegram API — it is not part of the token BotFather gave you. Paste the token exactly as received, directly after `/bot`, with no space or separator.
5. The page returns JSON. Look for `"chat":{"id":` — the number is your chat ID:
   ```json
   {
     "ok": true,
     "result": [{
       "message": {
         "chat": {
           "id": 98765432,
           "first_name": "Alex",
           "type": "private"
         }
       }
     }]
   }
   ```
   Your chat ID is `98765432`. Private chat IDs are positive integers.

> **getUpdates returns `{"ok":true,"result":[]}`?** The bot hasn't received any messages yet. Open Telegram, find your bot by username, send it any message (e.g. `hi`), then reload the URL in the browser.

#### Option B — Group chat (notifications go to a shared group)

Use this if you want your whole team to see the notifications.

1. Create a Telegram group (or use an existing one).
2. Add your bot to the group: open the group, tap the group name → **Add Members** → search for your bot's username → add it.
3. **Disable Privacy Mode** (required to receive group messages):
   - Go to BotFather, send `/mybots`, select your bot, then **Bot Settings → Group Privacy → Turn off**.
   - Without this, the bot cannot see messages in the group and `getUpdates` will stay empty.
4. Send any message in the group (e.g. `test`).
5. In a browser, open the following URL — substituting your token for `1234567890:AAFsomeRandomCharactersHere-xyz`:
   ```
   https://api.telegram.org/bot1234567890:AAFsomeRandomCharactersHere-xyz/getUpdates
   ```
6. Find the entry where `"type":"group"` or `"type":"supergroup"` and read the `"id"`:
   ```json
   "chat": {
     "id": -1001234567890,
     "title": "My Team",
     "type": "supergroup"
   }
   ```
   Group and supergroup IDs are **negative numbers**. Copy the whole value including the minus sign.

> **The group was upgraded to a supergroup?** Telegram silently upgrades groups to supergroups once they have more than a few members or when certain features are used. The supergroup ID starts with `-100` and is different from the old group ID — re-run `getUpdates` to get the new one.

#### Option C — Channel (notifications go to a public or private channel)

Use this to broadcast notifications as a channel post.

1. Create a Telegram channel (or use an existing one).
2. Add your bot as an **administrator** of the channel:
   - Open the channel → tap the channel name → **Administrators → Add Admin** → search for your bot → grant at least **Post Messages** permission.
3. Post any message in the channel.
4. In a browser, open the following URL — substituting your token for `1234567890:AAFsomeRandomCharactersHere-xyz`:
   ```
   https://api.telegram.org/bot1234567890:AAFsomeRandomCharactersHere-xyz/getUpdates
   ```
5. Find the entry with `"type":"channel"` and read `"id"`:
   ```json
   "chat": {
     "id": -1009876543210,
     "title": "My Announcements",
     "type": "channel"
   }
   ```
   Channel IDs are negative numbers starting with `-100`.

> **Alternative — forward method:** If `getUpdates` returns nothing, forward any message from the channel to **[@JsonDumpBot](https://t.me/JsonDumpBot)**. It replies with the full message JSON; find `"forward_from_chat":{"id":...}`.

> **Public channels:** you can also use the channel's public username as the chat ID, e.g. `@myannouncements`. Using the numeric ID is more reliable.

---

### Step 3 — Enter credentials in LinkDigest

1. Go to **LinkDigest › Settings**.
2. Paste the bot token into **Telegram Bot Token** (the full string, e.g. `1234567890:AAFsomeRandom...`).
3. Paste the chat ID into **Telegram Chat ID** (e.g. `98765432`, `-1001234567890`, or `@mychannel`).
4. Click **Save Settings**.

To disable Telegram notifications, clear both fields and save.

---

### Testing

Click **Test** on the Chat ID row. The button is disabled until both fields contain a value.

- On success, a "Test message sent" snackbar appears and a message shows up in your Telegram chat or channel within a few seconds.
- On failure, the snackbar shows the HTTP status code returned by the Telegram API. Common causes:

| Error | Likely cause |
|-------|-------------|
| HTTP 401 | Bot token is wrong or has been revoked. Re-generate it in BotFather (`/mybots → API Token → Revoke current token`). |
| HTTP 400 | Chat ID is wrong, or the bot was never started / added to the target. Double-check Step 2. |
| HTTP 403 | Bot is blocked by the user, or lacks admin rights in the channel. |
| Connection error | Your server cannot reach `api.telegram.org`. Check firewall rules or outbound HTTP restrictions. |

---

### What the message looks like

> **LinkDigest:** 12 links published. [View post](https://example.com/links-may-8-2026/)

Messages use Telegram's HTML parse mode: bold for the plugin name, a hyperlink for the post URL. If the run produced no post, the message reads:

> **LinkDigest:** 12 links processed. No post published.

---

## General notes

- **Non-blocking** — Discord, Slack, and Telegram requests are fire-and-forget. A slow or unreachable endpoint does not delay or abort the publish run. Email uses `wp_mail()` which is also non-blocking in most configurations.
- **Manual runs count** — using **Run Now** on the Schedule page produces a post and sends notifications just like a scheduled run.
- **No post = no notification** — if the run is skipped (no links, trigger condition not met), nothing is sent.
- **Channels are independent** — you can enable any combination of email, Discord, Slack, and Telegram simultaneously.
