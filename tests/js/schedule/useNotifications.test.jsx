import { renderHook, act } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import { useNotifications } from '../../../src/schedule/lib/notifications.js';

function setup(notify = {}, configLoaded = true) {
    let form = { notify };
    const setForm = vi.fn(updater => { form = typeof updater === 'function' ? updater(form) : updater; });
    const setSavedForm = vi.fn();
    const utils = renderHook(
        ({ form: f, configLoaded: cl }) => useNotifications(f, setForm, setSavedForm, cl),
        { initialProps: { form, configLoaded } },
    );
    return { ...utils, setForm, setSavedForm, getForm: () => form };
}

beforeEach(() => {
    apiFetch.mockReset();
});

describe('useNotifications — initial tab / enabled state', () => {
    it('derives initialNotifyTab from initialTabFor(form.notify)', () => {
        const { result } = setup({ discordEnabled: true });
        expect(result.current.initialNotifyTab).toBe('discord');
        expect(result.current.activeNotifyTab).toBe('discord');
    });

    it('falls back to the email tab when nothing is enabled', () => {
        const { result } = setup({});
        expect(result.current.initialNotifyTab).toBe('email');
    });

    it('derives anyNotifyEnabled from anyChannelEnabled(form.notify)', () => {
        expect(setup({}).result.current.anyNotifyEnabled).toBe(false);
        expect(setup({ bskyEnabled: true }).result.current.anyNotifyEnabled).toBe(true);
    });
});

describe('useNotifications — handleTest()', () => {
    it('calls apiFetch with the channel and current notify settings, then records a success notice', async () => {
        apiFetch.mockResolvedValue({ success: true });
        const { result } = setup({ discordEnabled: true, discordWebhookUrl: 'https://discord.com/api/webhooks/1/a' });

        await act(async () => {
            await result.current.handleTest('discord');
        });

        expect(apiFetch).toHaveBeenCalledWith({
            path: '/lynxjournal/v1/schedule/test-notification',
            method: 'POST',
            data: { channel: 'discord', notify: { discordEnabled: true, discordWebhookUrl: 'https://discord.com/api/webhooks/1/a' } },
        });
        expect(result.current.testState.discord.testing).toBe(false);
        expect(result.current.testState.discord.notice.status).toBe('success');
    });

    it('records an error notice when the request fails', async () => {
        apiFetch.mockRejectedValue(new Error('boom'));
        const { result } = setup({ discordEnabled: true });

        await act(async () => {
            await result.current.handleTest('discord');
        });

        expect(result.current.testState.discord.notice.status).toBe('error');
        expect(result.current.testState.discord.notice.message).toBe('boom');
    });
});

describe('useNotifications — handleSaveChannel()', () => {
    it('calls apiFetch, merges the response into form.notify, and records a success notice', async () => {
        apiFetch.mockResolvedValue({ success: true, notify: { discordWebhookUrl: 'https://discord.com/api/webhooks/9/z' } });
        const { result, setForm, setSavedForm } = setup({ discordEnabled: true, discordWebhookUrl: 'old' });

        await act(async () => {
            await result.current.handleSaveChannel('discord');
        });

        expect(apiFetch).toHaveBeenCalledWith({
            path: '/lynxjournal/v1/schedule/save-notification',
            method: 'POST',
            data: { channel: 'discord', notify: { discordEnabled: true, discordWebhookUrl: 'old' } },
        });
        expect(setForm).toHaveBeenCalled();
        expect(setSavedForm).toHaveBeenCalled();
        expect(result.current.channelSaveState.discord.notice.status).toBe('success');
    });

    it('records an error notice when the save fails', async () => {
        apiFetch.mockRejectedValue(new Error('save failed'));
        const { result } = setup({ discordEnabled: true });

        await act(async () => {
            await result.current.handleSaveChannel('discord');
        });

        expect(result.current.channelSaveState.discord.notice.status).toBe('error');
        expect(result.current.channelSaveState.discord.notice.message).toBe('save failed');
    });
});
