import { __ } from '@wordpress/i18n';

/**
 * Single source of truth for every notification channel: its `notify`
 * option field names, form field definitions, and (for Slack/Telegram,
 * which each have two independent send targets sharing one bot token)
 * its target sub-groups. Drives both the Notifications tab bar/form
 * (NotificationsSection.jsx) and the derived enabled/complete state
 * (useNotifications hook) so a new channel only needs one entry here.
 *
 * `key` is the tab name; for entries without `targets` it's also the
 * REST `channel` value sent to /schedule/test-notification and
 * /schedule/save-notification. For grouped entries, each target's own
 * `key` is the REST `channel` value instead.
 */
export const NOTIFICATION_CHANNELS = [
  {
    key: 'email',
    tabLabel: __('Email', 'lynx-journal'),
    enabledField: 'enabled',
    checkboxLabel: __('Email me after each run', 'lynx-journal'),
    fields: [
      { name: 'email', type: 'email', label: __('Email address', 'lynx-journal'), placeholder: __('Leave blank to use admin email', 'lynx-journal'), required: false },
    ],
  },
  {
    key: 'discord',
    tabLabel: __('Discord', 'lynx-journal'),
    enabledField: 'discordEnabled',
    checkboxLabel: __('Send a Discord notification after each run', 'lynx-journal'),
    fields: [
      { name: 'discordWebhookUrl', type: 'url', label: __('Discord webhook URL', 'lynx-journal'), placeholder: __('https://discord.com/api/webhooks/...', 'lynx-journal'), required: true },
    ],
  },
  {
    key: 'slack',
    tabLabel: __('Slack', 'lynx-journal'),
    sharedFields: [
      { name: 'slackBotToken', type: 'revealable', label: __('Slack Bot Token', 'lynx-journal'), placeholder: __('xoxb-...', 'lynx-journal'), required: true },
    ],
    targets: [
      {
        key: 'slack_channel',
        enabledField: 'slackChannelEnabled',
        groupLabel: __('Channel', 'lynx-journal'),
        checkboxLabel: __('Post to a Slack channel after each run', 'lynx-journal'),
        fields: [
          { name: 'slackChannelId', type: 'revealable', label: __('Slack channel ID', 'lynx-journal'), placeholder: __('C0123456789', 'lynx-journal'), required: true },
        ],
      },
      {
        key: 'slack_dm',
        enabledField: 'slackDmEnabled',
        groupLabel: __('Personal message', 'lynx-journal'),
        checkboxLabel: __('Send me a Slack DM after each run', 'lynx-journal'),
        fields: [
          { name: 'slackUserId', type: 'revealable', label: __('Slack user ID', 'lynx-journal'), placeholder: __('U0123456789', 'lynx-journal'), required: true },
        ],
      },
    ],
  },
  {
    key: 'telegram',
    tabLabel: __('Telegram', 'lynx-journal'),
    sharedFields: [
      { name: 'telegramBotToken', type: 'revealable', label: __('Telegram bot token', 'lynx-journal'), placeholder: __('123456789:AAH...', 'lynx-journal'), required: true },
    ],
    targets: [
      {
        key: 'telegram',
        enabledField: 'telegramEnabled',
        groupLabel: __('Group or channel', 'lynx-journal'),
        checkboxLabel: __('Post to a Telegram group or channel after each run', 'lynx-journal'),
        fields: [
          { name: 'telegramChatId', type: 'revealable', label: __('Telegram group/channel chat ID', 'lynx-journal'), placeholder: __('-1001234567890', 'lynx-journal'), required: true },
        ],
      },
      {
        key: 'telegram_dm',
        enabledField: 'telegramDmEnabled',
        groupLabel: __('Personal message', 'lynx-journal'),
        checkboxLabel: __('Send me a Telegram DM after each run', 'lynx-journal'),
        fields: [
          { name: 'telegramDmChatId', type: 'revealable', label: __('Telegram personal chat ID', 'lynx-journal'), placeholder: __('123456789', 'lynx-journal'), required: true },
        ],
      },
    ],
  },
  {
    key: 'mastodon',
    tabLabel: __('Mastodon', 'lynx-journal'),
    enabledField: 'mastodonEnabled',
    checkboxLabel: __('Send a Mastodon direct message after each run', 'lynx-journal'),
    fields: [
      { name: 'mastodonInstanceUrl', type: 'url', label: __('Mastodon instance URL', 'lynx-journal'), placeholder: __('https://mastodon.social', 'lynx-journal'), required: true },
      { name: 'mastodonAccessToken', type: 'revealable', label: __('Mastodon access token', 'lynx-journal'), placeholder: __('Access token from your Mastodon app', 'lynx-journal'), required: true },
      { name: 'mastodonRecipient', type: 'text', label: __('Recipient handle', 'lynx-journal'), placeholder: __('@you@mastodon.social', 'lynx-journal'), required: true },
    ],
  },
];

/**
 * Whether a single target (or a non-grouped channel treated as its own
 * target) is turned on.
 *
 * @param {Object} target Either a channel entry (no `targets`) or one of its `targets[]` entries.
 * @param {Object} notify Current `notify` form state.
 * @returns {boolean}
 */
export function isTargetEnabled(target, notify) {
  return Boolean(notify?.[target.enabledField]);
}

/**
 * Whether a single target has all its (and its shared) required fields filled in.
 *
 * @param {Object} entry  The parent channel entry (for `sharedFields`, if any).
 * @param {Object} target Either `entry` itself (non-grouped) or one of `entry.targets[]`.
 * @param {Object} notify Current `notify` form state.
 * @returns {boolean}
 */
export function isTargetComplete(entry, target, notify) {
  const requiredNames = [
    ...(entry.sharedFields ?? []).filter(f => f.required).map(f => f.name),
    ...target.fields.filter(f => f.required).map(f => f.name),
  ];
  return isTargetEnabled(target, notify) && requiredNames.every(name => Boolean(notify?.[name]));
}

/**
 * Whether any target belonging to a channel entry is enabled.
 *
 * @param {Object} entry  A NOTIFICATION_CHANNELS entry.
 * @param {Object} notify Current `notify` form state.
 * @returns {boolean}
 */
export function isChannelEnabled(entry, notify) {
  const targets = entry.targets ?? [entry];
  return targets.some(target => isTargetEnabled(target, notify));
}

/**
 * Whether a channel entry is enabled and ready to send: for grouped
 * entries (Slack/Telegram), true if at least one target is complete.
 *
 * @param {Object} entry  A NOTIFICATION_CHANNELS entry.
 * @param {Object} notify Current `notify` form state.
 * @returns {boolean}
 */
export function isChannelComplete(entry, notify) {
  const targets = entry.targets ?? [entry];
  return targets.some(target => isTargetComplete(entry, target, notify));
}

/**
 * Whether a channel entry has at least one enabled target that's missing
 * required fields — the tab badge's "on, incomplete" state.
 *
 * @param {Object} entry  A NOTIFICATION_CHANNELS entry.
 * @param {Object} notify Current `notify` form state.
 * @returns {boolean}
 */
export function isChannelIncomplete(entry, notify) {
  const targets = entry.targets ?? [entry];
  return targets.some(target => isTargetEnabled(target, notify) && !isTargetComplete(entry, target, notify));
}

/**
 * The tab name to select once the real schedule config has loaded: the
 * first non-email channel with an enabled target, or 'email' otherwise.
 *
 * @param {Object} notify Current `notify` form state.
 * @returns {string} A NOTIFICATION_CHANNELS entry key.
 */
export function initialTabFor(notify) {
  const hit = NOTIFICATION_CHANNELS.find(entry => entry.key !== 'email' && isChannelEnabled(entry, notify));
  return hit ? hit.key : 'email';
}

/**
 * Whether any notification channel is enabled at all — used for the
 * Notifications section header badge.
 *
 * @param {Object} notify Current `notify` form state.
 * @returns {boolean}
 */
export function anyChannelEnabled(notify) {
  return NOTIFICATION_CHANNELS.some(entry => isChannelEnabled(entry, notify));
}
