import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import apiFetch from '@wordpress/api-fetch';
import App from '../../src/schedule/App.jsx';

function mockScheduleAndDiagnostics(scheduleOverrides = {}, diagOverrides = {}) {
    apiFetch.mockImplementation(({ path }) => {
        if (path === '/lynxjournal/v1/schedule') {
            return Promise.resolve({ mode: 'daily', ...scheduleOverrides });
        }
        if (path === '/lynxjournal/v1/schedule/diagnostics') {
            return Promise.resolve({ ...diagOverrides });
        }
        return Promise.resolve({});
    });
}

describe('App', () => {
    beforeEach(() => {
        apiFetch.mockReset();
        apiFetch.mockImplementation(() => Promise.resolve({}));
    });

    it('loads the schedule and diagnostics on mount and renders the mode picker', async () => {
        mockScheduleAndDiagnostics();
        render(<App />);

        await waitFor(() => expect(apiFetch).toHaveBeenCalledWith({ path: '/lynxjournal/v1/schedule' }));
        expect(screen.getByText('Mode')).toBeInTheDocument();
    });

    it('shows the WP-Cron warning notice when cron is disabled and not dismissed', async () => {
        mockScheduleAndDiagnostics({}, { wp_cron_disabled: true, cron_notice_dismissed: false });
        render(<App />);

        expect(await screen.findByText('WP-Cron is disabled.')).toBeInTheDocument();
    });

    it('does not show the WP-Cron warning when already dismissed', async () => {
        mockScheduleAndDiagnostics({}, { wp_cron_disabled: true, cron_notice_dismissed: true });
        render(<App />);

        await waitFor(() => expect(apiFetch).toHaveBeenCalled());
        expect(screen.queryByText('WP-Cron is disabled.')).not.toBeInTheDocument();
    });

    it('dismisses the WP-Cron notice and posts the dismissal', async () => {
        mockScheduleAndDiagnostics({}, { wp_cron_disabled: true, cron_notice_dismissed: false });
        const user = userEvent.setup();
        render(<App />);

        await screen.findByText('WP-Cron is disabled.');
        await user.click(screen.getByRole('button', { name: 'Dismiss this notice' }));

        expect(screen.queryByText('WP-Cron is disabled.')).not.toBeInTheDocument();
        expect(apiFetch).toHaveBeenCalledWith({ path: '/lynxjournal/v1/schedule/dismiss-cron-notice', method: 'POST' });
    });

    it('shows the recurrence config for schedule modes and switches to trigger condition for count mode', async () => {
        mockScheduleAndDiagnostics();
        const user = userEvent.setup();
        render(<App />);

        await waitFor(() => expect(apiFetch).toHaveBeenCalledWith({ path: '/lynxjournal/v1/schedule' }));
        expect(screen.getByText('Recurrence')).toBeInTheDocument();

        await user.click(screen.getByRole('radio', { name: /By Count/ }));

        expect(screen.getByText('Condition')).toBeInTheDocument();
    });

    it('shows the manual mode description and hides execution times for manual mode', async () => {
        mockScheduleAndDiagnostics();
        const user = userEvent.setup();
        render(<App />);

        await waitFor(() => expect(apiFetch).toHaveBeenCalledWith({ path: '/lynxjournal/v1/schedule' }));
        await user.click(screen.getByRole('radio', { name: /^Manual/ }));

        expect(screen.getByText('No automatic trigger — posts must be triggered manually.')).toBeInTheDocument();
        expect(screen.queryByText('Execution Times')).not.toBeInTheDocument();
    });

    it('shows execution times for schedule modes', async () => {
        mockScheduleAndDiagnostics();
        render(<App />);

        await waitFor(() => expect(apiFetch).toHaveBeenCalledWith({ path: '/lynxjournal/v1/schedule' }));
        expect(screen.getByText('Execution Times')).toBeInTheDocument();
    });

    it('shows the email field only when notifications are enabled', async () => {
        mockScheduleAndDiagnostics();
        const user = userEvent.setup();
        render(<App />);

        await waitFor(() => expect(apiFetch).toHaveBeenCalledWith({ path: '/lynxjournal/v1/schedule' }));
        expect(screen.queryByLabelText('Email address')).not.toBeInTheDocument();

        await user.click(screen.getByLabelText('Email me after each run'));

        expect(screen.getByLabelText('Email address')).toBeInTheDocument();
    });

    it('saves the schedule and shows a success notice', async () => {
        mockScheduleAndDiagnostics();
        const user = userEvent.setup();
        render(<App />);

        await waitFor(() => expect(apiFetch).toHaveBeenCalledWith({ path: '/lynxjournal/v1/schedule' }));
        await user.click(screen.getByRole('button', { name: 'Save Schedule' }));

        expect(await screen.findByText('Schedule saved.')).toBeInTheDocument();
        expect(apiFetch).toHaveBeenCalledWith(expect.objectContaining({
            path: '/lynxjournal/v1/schedule',
            method: 'POST',
        }));
    });

    it('shows an error notice when saving rejects', async () => {
        apiFetch.mockImplementation(({ path, method }) => {
            if (path === '/lynxjournal/v1/schedule' && method === 'POST') {
                return Promise.reject(new Error('network error'));
            }
            if (path === '/lynxjournal/v1/schedule') {
                return Promise.resolve({ mode: 'daily' });
            }
            return Promise.resolve({});
        });
        const user = userEvent.setup();
        render(<App />);

        await waitFor(() => expect(apiFetch).toHaveBeenCalledWith({ path: '/lynxjournal/v1/schedule' }));
        await user.click(screen.getByRole('button', { name: 'Save Schedule' }));

        expect(await screen.findByText('Failed to save schedule.')).toBeInTheDocument();
    });

    it('blocks saving with a validation error when execution times are duplicated', async () => {
        apiFetch.mockImplementation(({ path }) => {
            if (path === '/lynxjournal/v1/schedule') {
                return Promise.resolve({ mode: 'daily', times: ['09:00', '09:00'] });
            }
            return Promise.resolve({});
        });
        const user = userEvent.setup();
        render(<App />);

        await waitFor(() => expect(apiFetch).toHaveBeenCalledWith({ path: '/lynxjournal/v1/schedule' }));
        await user.click(screen.getByRole('button', { name: 'Save Schedule' }));

        expect(await screen.findByText('Execution times must be unique.')).toBeInTheDocument();
        expect(apiFetch).not.toHaveBeenCalledWith(expect.objectContaining({ method: 'POST' }));
    });

    it('resets recurrence to defaults when switching between schedule modes', async () => {
        mockScheduleAndDiagnostics({ mode: 'weekly', recurrence: { interval: 3, weekdays: ['MO'], monthDays: [], nthWeek: null } });
        const user = userEvent.setup();
        render(<App />);

        await waitFor(() => expect(apiFetch).toHaveBeenCalledWith({ path: '/lynxjournal/v1/schedule' }));
        await user.click(screen.getByRole('radio', { name: /^Daily/ }));

        // After switching to daily, the recurrence interval resets to the default of 1.
        expect(screen.getByDisplayValue('1')).toBeInTheDocument();
    });

    it('changes post status via the select control', async () => {
        mockScheduleAndDiagnostics();
        const user = userEvent.setup();
        render(<App />);

        await waitFor(() => expect(apiFetch).toHaveBeenCalledWith({ path: '/lynxjournal/v1/schedule' }));

        const select = screen.getByRole('combobox');
        await user.selectOptions(select, 'draft');

        expect(select.value).toBe('draft');
    });
});
