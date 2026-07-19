import { render, screen, fireEvent, within } from '@testing-library/react';
import { axe } from 'vitest-axe';
import NotificationsSection from '../../../src/schedule/components/NotificationsSection.jsx';
import { openHelpTab } from '../../../src/schedule/lib/helpPanel';

vi.mock('../../../src/schedule/lib/helpPanel', () => ({ openHelpTab: vi.fn() }));

const baseProps = {
    configLoaded: true,
    initialNotifyTab: 'email',
    activeNotifyTab: 'email',
    setActiveNotifyTab: () => {},  // skipcq: JS-0057 - intentional no-op test stub
    testState: {},
    channelSaveState: {},
    handleTest: () => {},  // skipcq: JS-0057 - intentional no-op test stub
    handleSaveChannel: () => {},  // skipcq: JS-0057 - intentional no-op test stub
    tabsWrapRef: { current: null },
    tabsOverflow: false,
    scrollNotifyTabs: () => {},  // skipcq: JS-0057 - intentional no-op test stub
};

function renderSection(notify = {}, extraProps = {}) {
    const form = { notify };
    const setForm = vi.fn();
    const utils = render(<NotificationsSection {...baseProps} {...extraProps} form={form} setForm={setForm} />);
    return { ...utils, form, setForm };
}

describe('NotificationsSection', () => {
    it('has no accessibility violations', async () => {
        const { container } = renderSection();
        expect(await axe(container)).toHaveNoViolations();
    });

    it('renders a tab for every notification channel', () => {
        renderSection();
        for (const label of ['Email', 'Discord', 'Slack', 'Telegram', 'Mastodon', 'Bluesky']) {
            expect(screen.getByRole('tab', { name: new RegExp(label) })).toBeInTheDocument();
        }
    });

    it('renders both Slack sub-groups with their own group labels', () => {
        renderSection();
        expect(screen.getByText('Channel')).toBeInTheDocument();
        // Slack and Telegram both use "Personal message" as their DM sub-group label.
        expect(screen.getAllByText('Personal message')).toHaveLength(2);
        expect(screen.getByLabelText('Slack channel ID')).toBeInTheDocument();
        expect(screen.getByLabelText('Slack user ID')).toBeInTheDocument();
    });

    it('renders both Telegram sub-groups with their own group labels', () => {
        renderSection();
        expect(screen.getByText('Group or channel')).toBeInTheDocument();
        expect(screen.getByLabelText('Telegram group/channel chat ID')).toBeInTheDocument();
        expect(screen.getByLabelText('Telegram personal chat ID')).toBeInTheDocument();
    });

    it('calls setForm with the updated field value when editing the Discord webhook URL', () => {
        const { setForm, form } = renderSection();

        fireEvent.change(screen.getByLabelText('Discord webhook URL'), {
            target: { value: 'https://discord.com/api/webhooks/1/abc' },
        });

        expect(setForm).toHaveBeenCalledTimes(1);
        const updater = setForm.mock.calls[0][0];
        expect(updater(form).notify.discordWebhookUrl).toBe('https://discord.com/api/webhooks/1/abc');
    });

    it('calls setForm with the target-specific field when checking a Slack DM checkbox', () => {
        const { setForm, form } = renderSection();

        fireEvent.click(screen.getByLabelText('Send me a Slack DM after each run'));

        expect(setForm).toHaveBeenCalledTimes(1);
        const updater = setForm.mock.calls[0][0];
        expect(updater(form).notify.slackDmEnabled).toBe(true);
    });

    it('marks the Discord tab badge as "On" when enabled and complete', () => {
        renderSection({ discordEnabled: true, discordWebhookUrl: 'https://discord.com/api/webhooks/1/abc' });
        expect(screen.getByLabelText('Discord: On')).toBeInTheDocument();
    });

    it('marks the Discord tab badge as "On, incomplete" when enabled but missing the webhook URL', () => {
        renderSection({ discordEnabled: true, discordWebhookUrl: '' });
        expect(screen.getByLabelText('Discord: On, incomplete')).toBeInTheDocument();
    });

    it('marks the Discord tab badge as "Off" when disabled', () => {
        renderSection({ discordEnabled: false });
        expect(screen.getByLabelText('Discord: Off')).toBeInTheDocument();
    });

    it('opens the Discord help tab when that panel\'s Help link is clicked', () => {
        renderSection();
        const discordPanel = screen.getByLabelText('Discord webhook URL').closest('.lynxjournal-notify-tab-panel');

        fireEvent.click(within(discordPanel).getByText('Help'));

        expect(openHelpTab).toHaveBeenCalledWith('discord');
    });

    it('opens the overview help tab when the Email panel\'s Help link is clicked', () => {
        renderSection();
        const emailPanel = screen.getByLabelText('Email address').closest('.lynxjournal-notify-tab-panel');

        fireEvent.click(within(emailPanel).getByText('Help'));

        expect(openHelpTab).toHaveBeenCalledWith('overview');
    });
});
