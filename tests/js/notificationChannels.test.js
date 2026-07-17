/**
 * Vitest — Unit tests for src/schedule/lib/notificationChannels.js
 *
 * Run with: npm run test:js
 */

import {
    NOTIFICATION_CHANNELS,
    isTargetEnabled,
    isTargetComplete,
    isChannelEnabled,
    isChannelComplete,
    isChannelIncomplete,
    initialTabFor,
    anyChannelEnabled,
} from '../../src/schedule/lib/notificationChannels.js';

describe('NOTIFICATION_CHANNELS shape', () => {
    it('has 6 top-level entries with unique keys', () => {
        const keys = NOTIFICATION_CHANNELS.map(c => c.key);
        expect(keys).toEqual(['email', 'discord', 'slack', 'telegram', 'mastodon', 'bluesky']);
        expect(new Set(keys).size).toBe(keys.length);
    });

    it('grouped entries (slack, telegram) have targets with unique REST channel keys', () => {
        const slack = NOTIFICATION_CHANNELS.find(c => c.key === 'slack');
        const telegram = NOTIFICATION_CHANNELS.find(c => c.key === 'telegram');

        expect(slack.targets.map(t => t.key)).toEqual(['slack_channel', 'slack_dm']);
        expect(telegram.targets.map(t => t.key)).toEqual(['telegram', 'telegram_dm']);
        expect(slack.sharedFields.length).toBeGreaterThan(0);
        expect(telegram.sharedFields.length).toBeGreaterThan(0);
    });

    it('non-grouped entries have their own fields, not targets', () => {
        for (const key of ['email', 'discord', 'mastodon', 'bluesky']) {
            const entry = NOTIFICATION_CHANNELS.find(c => c.key === key);
            expect(entry.targets).toBeUndefined();
            expect(Array.isArray(entry.fields)).toBe(true);
            expect(entry.fields.length).toBeGreaterThan(0);
        }
    });
});

describe('isTargetEnabled()', () => {
    it('reflects the target\'s enabledField', () => {
        const target = { enabledField: 'discordEnabled' };
        expect(isTargetEnabled(target, { discordEnabled: true })).toBe(true);
        expect(isTargetEnabled(target, { discordEnabled: false })).toBe(false);
        expect(isTargetEnabled(target, {})).toBe(false);
    });
});

describe('isTargetComplete() — non-grouped', () => {
    const discord = NOTIFICATION_CHANNELS.find(c => c.key === 'discord');

    it('is false when disabled, even with the field filled in', () => {
        expect(isTargetComplete(discord, discord, { discordEnabled: false, discordWebhookUrl: 'https://discord.com/api/webhooks/1/a' })).toBe(false);
    });

    it('is false when enabled but the required field is empty', () => {
        expect(isTargetComplete(discord, discord, { discordEnabled: true, discordWebhookUrl: '' })).toBe(false);
    });

    it('is true when enabled and the required field is filled in', () => {
        expect(isTargetComplete(discord, discord, { discordEnabled: true, discordWebhookUrl: 'https://discord.com/api/webhooks/1/a' })).toBe(true);
    });
});

describe('isTargetComplete() — grouped (shared field)', () => {
    const slack = NOTIFICATION_CHANNELS.find(c => c.key === 'slack');
    const channelTarget = slack.targets.find(t => t.key === 'slack_channel');

    it('requires the shared field in addition to the target\'s own field', () => {
        expect(isTargetComplete(slack, channelTarget, {
            slackChannelEnabled: true, slackChannelId: 'C123', slackBotToken: '',
        })).toBe(false);
        expect(isTargetComplete(slack, channelTarget, {
            slackChannelEnabled: true, slackChannelId: '', slackBotToken: 'xoxb-1',
        })).toBe(false);
        expect(isTargetComplete(slack, channelTarget, {
            slackChannelEnabled: true, slackChannelId: 'C123', slackBotToken: 'xoxb-1',
        })).toBe(true);
    });
});

describe('isChannelEnabled() / isChannelComplete() / isChannelIncomplete() — grouped', () => {
    const slack = NOTIFICATION_CHANNELS.find(c => c.key === 'slack');

    it('is disabled when neither target is enabled', () => {
        expect(isChannelEnabled(slack, {})).toBe(false);
        expect(isChannelComplete(slack, {})).toBe(false);
        expect(isChannelIncomplete(slack, {})).toBe(false);
    });

    it('is enabled+complete, not incomplete, when one target is complete and its sibling is untouched', () => {
        const notify = { slackChannelEnabled: true, slackChannelId: 'C123', slackBotToken: 'xoxb-1' };
        expect(isChannelEnabled(slack, notify)).toBe(true);
        expect(isChannelComplete(slack, notify)).toBe(true);
        expect(isChannelIncomplete(slack, notify)).toBe(false);
    });

    it('is incomplete when one enabled target is missing a required field, regardless of its sibling', () => {
        const notify = { slackDmEnabled: true, slackUserId: '', slackBotToken: 'xoxb-1' };
        expect(isChannelEnabled(slack, notify)).toBe(true);
        expect(isChannelComplete(slack, notify)).toBe(false);
        expect(isChannelIncomplete(slack, notify)).toBe(true);
    });

    it('is complete overall when the incomplete target has a complete sibling', () => {
        const notify = {
            slackChannelEnabled: true, slackChannelId: 'C123',
            slackDmEnabled: true, slackUserId: '',
            slackBotToken: 'xoxb-1',
        };
        expect(isChannelComplete(slack, notify)).toBe(true);
        // One enabled target (the DM one) is still incomplete, so the tab badge should warn.
        expect(isChannelIncomplete(slack, notify)).toBe(true);
    });
});

describe('initialTabFor()', () => {
    it('prioritizes discord > slack > telegram > mastodon > bluesky over email', () => {
        expect(initialTabFor({ discordEnabled: true, bskyEnabled: true })).toBe('discord');
        expect(initialTabFor({ slackChannelEnabled: true, telegramEnabled: true })).toBe('slack');
        expect(initialTabFor({ telegramDmEnabled: true, mastodonEnabled: true })).toBe('telegram');
        expect(initialTabFor({ mastodonEnabled: true, bskyEnabled: true })).toBe('mastodon');
        expect(initialTabFor({ bskyEnabled: true })).toBe('bluesky');
    });

    it('falls back to email when nothing else is enabled', () => {
        expect(initialTabFor({})).toBe('email');
        expect(initialTabFor({ enabled: true })).toBe('email');
    });
});

describe('anyChannelEnabled()', () => {
    it('is false when nothing is enabled', () => {
        expect(anyChannelEnabled({})).toBe(false);
    });

    it('is true when any single channel/target is enabled', () => {
        expect(anyChannelEnabled({ enabled: true })).toBe(true);
        expect(anyChannelEnabled({ telegramDmEnabled: true })).toBe(true);
    });
});
