# LinkDigest — Webhook Notifications

After each successful publish run, LinkDigest can send a notification to **Discord** or **Slack** (or both). Notifications fire only when a digest post is actually created — skipped runs produce nothing.

---

## Setting up Discord

1. Open your Discord server and go to **Server Settings › Integrations › Webhooks**.
2. Click **New Webhook**, choose the channel you want messages in, and copy the webhook URL.
3. In WordPress go to **LinkDigest › Schedule**.
4. Scroll to the **Notifications** panel and paste the URL into **Discord Webhook URL**.
5. Click **Save Schedule**.

To disable Discord notifications, clear the field and save.

**What the message looks like:**

> **LinkDigest: digest published**
> 12 links published. [View post](https://example.com/links-may-8-2026/)

The embed uses a blue accent colour (#2D9BF0).

---

## Setting up Slack

1. Go to **api.slack.com/apps** and create a new app (or open an existing one).
2. Under **Features**, click **Incoming Webhooks** and toggle it on.
3. Click **Add New Webhook to Workspace**, pick a channel, and copy the webhook URL.
4. In WordPress go to **LinkDigest › Schedule**.
5. Scroll to the **Notifications** panel and paste the URL into **Slack Webhook URL**.
6. Click **Save Schedule**.

To disable Slack notifications, clear the field and save.

**What the message looks like:**

> **LinkDigest:** 12 links published. [View post](https://example.com/links-may-8-2026/)

---

## Notes

- **Non-blocking** — webhooks are sent fire-and-forget. A slow or unreachable endpoint does not delay or abort the publish run.
- **Discord and Slack are independent** — you can enable either, both, or neither.
- **Manual runs count** — if a **Run Now** on the dashboard produces a post, notifications are sent.
- **No post = no notification** — if the run is skipped (no links, condition not met), nothing is sent.
