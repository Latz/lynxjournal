import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import DiagnosticsPanel from '../../src/schedule/components/DiagnosticsPanel.jsx';

describe('DiagnosticsPanel', () => {
    beforeEach(() => {
        localStorage.clear();
    });

    it('shows a loading message while loading', () => {
        render(<DiagnosticsPanel data={null} loading={true} onRefresh={() => {}} mode="daily" />);

        expect(screen.getByText('Loading…')).toBeInTheDocument();
    });

    it('does not show the refresh button while loading', () => {
        render(<DiagnosticsPanel data={null} loading={true} onRefresh={() => {}} mode="daily" />);

        expect(screen.queryByRole('button', { name: 'Refresh' })).not.toBeInTheDocument();
    });

    it('shows "Not scheduled" when there is no next_scheduled and mode is not count', () => {
        render(<DiagnosticsPanel data={{}} loading={false} onRefresh={() => {}} mode="daily" />);

        expect(screen.getByText('Not scheduled')).toBeInTheDocument();
    });

    it('shows a WP-Cron-disabled reason next to "Not scheduled" when applicable', () => {
        render(<DiagnosticsPanel data={{ wp_cron_disabled: true }} loading={false} onRefresh={() => {}} mode="daily" />);

        expect(screen.getByText(/WP-Cron disabled/)).toBeInTheDocument();
    });

    it('formats next_scheduled as a date when present', () => {
        render(<DiagnosticsPanel data={{ next_scheduled: 1700000000 }} loading={false} onRefresh={() => {}} mode="daily" />);

        expect(screen.queryByText('Not scheduled')).not.toBeInTheDocument();
    });

    it('shows a links-until-post message for count mode', () => {
        render(<DiagnosticsPanel data={{ links_until_post: 4 }} loading={false} onRefresh={() => {}} mode="count" />);

        expect(screen.getByText('4 links until post')).toBeInTheDocument();
    });

    it('shows "Ready to post" for count mode when links_until_post is 0', () => {
        render(<DiagnosticsPanel data={{ links_until_post: 0 }} loading={false} onRefresh={() => {}} mode="count" />);

        expect(screen.getByText('Ready to post')).toBeInTheDocument();
    });

    it('does not show the next-run row for count mode when links_until_post is undefined', () => {
        render(<DiagnosticsPanel data={{}} loading={false} onRefresh={() => {}} mode="count" />);

        expect(screen.queryByText('Next run')).not.toBeInTheDocument();
    });

    it('shows "No runs yet" when there is no last run', () => {
        render(<DiagnosticsPanel data={{}} loading={false} onRefresh={() => {}} mode="daily" />);

        expect(screen.getByText('No runs yet')).toBeInTheDocument();
    });

    it('shows the last run status, formatted date, and link count with a post link', () => {
        render(
            <DiagnosticsPanel
                data={{ last_run: { status: 'success', ts: 1700000000, post_id: 42, link_count: 3 } }}
                loading={false}
                onRefresh={() => {}}
                mode="daily"
            />
        );

        expect(screen.getByText('success')).toBeInTheDocument();
        const link = screen.getByRole('link', { name: '3 links' });
        expect(link).toHaveAttribute('href', '/wp-admin/post.php?post=42&action=edit');
    });

    it('shows link count as plain text (no link) when there is no post_id', () => {
        render(
            <DiagnosticsPanel
                data={{ last_run: { status: 'success', ts: 1700000000, link_count: 2 } }}
                loading={false}
                onRefresh={() => {}}
                mode="daily"
            />
        );

        expect(screen.getByText((_, node) => node?.textContent === ' · 2 links')).toBeInTheDocument();
        expect(screen.queryByRole('link')).not.toBeInTheDocument();
    });

    it('shows a formatted reason label for a known reason code', () => {
        render(
            <DiagnosticsPanel
                data={{ last_run: { status: 'skipped', ts: 1700000000, reason: 'condition_not_met' } }}
                loading={false}
                onRefresh={() => {}}
                mode="daily"
            />
        );

        expect(screen.getByText('Condition not met')).toBeInTheDocument();
    });

    it('falls back to the raw reason code for an unknown reason', () => {
        render(
            <DiagnosticsPanel
                data={{ last_run: { status: 'skipped', ts: 1700000000, reason: 'weird_reason' } }}
                loading={false}
                onRefresh={() => {}}
                mode="daily"
            />
        );

        expect(screen.getByText('weird_reason')).toBeInTheDocument();
    });

    it('shows the WP-Cron Active badge when not disabled', () => {
        render(<DiagnosticsPanel data={{ wp_cron_disabled: false }} loading={false} onRefresh={() => {}} mode="daily" />);

        expect(screen.getByText('Active')).toBeInTheDocument();
    });

    it('shows the WP-Cron Disabled badge when disabled', () => {
        render(<DiagnosticsPanel data={{ wp_cron_disabled: true }} loading={false} onRefresh={() => {}} mode="daily" />);

        expect(screen.getAllByText('Disabled').length).toBeGreaterThan(0);
    });

    it('does not show a history toggle when run_history is empty', () => {
        render(<DiagnosticsPanel data={{ run_history: [] }} loading={false} onRefresh={() => {}} mode="daily" />);

        expect(screen.queryByRole('button', { name: /History/ })).not.toBeInTheDocument();
    });

    it('toggles the history list open and closed', async () => {
        const user = userEvent.setup();
        const run_history = [
            { ts: 1700000000, status: 'success', post_id: 1, link_count: 2 },
            { ts: 1700003600, status: 'skipped', reason: 'locked' },
        ];
        render(<DiagnosticsPanel data={{ run_history }} loading={false} onRefresh={() => {}} mode="daily" />);

        const toggle = screen.getByRole('button', { name: 'History (2)' });
        expect(screen.queryByText('Run was locked')).not.toBeInTheDocument();

        await user.click(toggle);

        expect(screen.getByText('Hide history')).toBeInTheDocument();
        expect(screen.getByText('Run was locked')).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Hide history' }));

        expect(screen.queryByText('Run was locked')).not.toBeInTheDocument();
    });

    it('calls onRefresh when the refresh button is clicked', async () => {
        const onRefresh = vi.fn();
        const user = userEvent.setup();
        render(<DiagnosticsPanel data={{}} loading={false} onRefresh={onRefresh} mode="daily" />);

        await user.click(screen.getByRole('button', { name: 'Refresh' }));

        expect(onRefresh).toHaveBeenCalledTimes(1);
    });
});
