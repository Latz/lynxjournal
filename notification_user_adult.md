# Notifications: User Guide

## Introduction

LynxJournal periodically publishes a "roundup" post that collects your recently saved links. The notification feature lets you receive an alert whenever this happens, delivered to one or more destinations of your choosing: email, a chat platform, or a social network. This guide covers how to set up, test, and maintain each destination, and what to do if a delivery fails.

## Supported Destinations

You can enable any combination of the following. Each is configured independently.

**Email**
Sends a plain notification email to an address you specify. No external setup required beyond providing the address.

**Discord**
Delivers a message to a Discord channel via a webhook. Create a webhook from your Discord server's channel settings (*Edit Channel → Integrations → Webhooks → New Webhook*) and paste the resulting URL into the field provided.

**Slack**
Available as either a channel post or a direct message, both using the same bot token. Create a Slack app with a bot token (from api.slack.com) with permission to post messages, then supply the token and the target channel ID or user ID depending on which mode you use.

**Telegram**
Also available as a group/channel post or a direct message, sharing one bot token. Create a bot via Telegram's BotFather to obtain a token, then provide the relevant chat ID.

**Mastodon**
Posts publicly from your account on a Mastodon instance. Requires your instance URL and an access token, generated from your Mastodon account's *Development* settings.

## Setting Up a Channel

1. Open the Schedule settings screen and locate the **Notifications** section.
2. Select the tab for the destination you want to configure.
3. Toggle the destination on.
4. Fill in the required fields (address, webhook URL, token, etc., depending on the destination).
5. Click **Save**.

Each destination is saved independently — configuring or saving one does not affect the others, so you can set up channels one at a time without losing unsaved changes elsewhere on the page.

## Testing a Channel

Before relying on a newly configured destination, use the **Send Test** button on that channel's tab. This immediately sends a sample notification to confirm the destination is reachable and correctly configured.

- A successful test confirms the message arrived at the destination.
- A failed test indicates a problem with the configuration (see Troubleshooting below) and will also appear in the failure notice described next.

## How Delivery Works

Once a channel is enabled and saved, no further action is needed. Each time a roundup post is published, a notification is sent automatically to every enabled destination. Delivery to each destination happens independently in the background, so a delay or failure on one channel does not affect or delay delivery to the others.

## Handling Delivery Failures

If a notification fails to send, a dismissible error notice appears at the top of the WordPress dashboard. It shows which destination failed, when, and a brief description of the error.

Dismissing the notice (via its close button) only clears it from view — it does not resolve the underlying issue. If a destination continues to fail, revisit its settings, verify the credentials are current, and use **Send Test** again to confirm the fix before assuming the channel is working.

## Security Notes

- Tokens, webhook URLs, app passwords, and similar credentials should be treated as sensitive information, equivalent to a password. Anyone with access to them could post or send messages on your behalf.
- Only users with administrator-level permissions can view, configure, or test notification destinations.
- Credential fields are masked on screen by default; use the show/hide control only when you need to verify or copy a value.

## Troubleshooting Checklist

If a destination isn't working, check the following:

- **Discord**: Confirm the webhook URL was copied in full and hasn't been regenerated or deleted in Discord's channel settings.
- **Slack / Telegram**: Confirm the bot token is still valid and the bot has permission to post to the specified channel or has been messaged first (for direct messages, some platforms require the bot to be initiated by the user).
- **Mastodon**: Confirm the instance URL is correct and the access token hasn't expired or been revoked.
- **Email**: Confirm the address is correctly typed and check spam/junk folders.
- For any destination, re-run **Send Test** after making changes to confirm the fix worked.

## Quick Reference

| Step | Action |
|---|---|
| Set up a destination | Notifications tab → toggle on → fill in fields → Save |
| Verify it works | Click Send Test |
| Ongoing delivery | Automatic after each roundup post, no action needed |
| Something failed | Check the dashboard notice, review credentials, re-test |
| Credentials | Treat as sensitive; admin-only access |
