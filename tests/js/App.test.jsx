import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor, act } from '@testing-library/react';
import '@testing-library/jest-dom';
import apiFetch from './stubs/wp-api-fetch.js';

// Stub child components so App's own logic is what's under test.
vi.mock('../../src/schedule/components/ScheduleTypePicker', () => ({
    default: ({ value, onChange }) => (
        <div data-testid="mode-picker" data-value={value}>
            <button onClick={() => onChange('weekly')}>pick-weekly</button>
            <button onClick={() => onChange('count')}>pick-count</button>
            <button onClick={() => onChange('manual')}>pick-manual</button>
        </div>
    ),
}));
vi.mock('../../src/schedule/components/RecurrenceConfig',  () => ({ default: () => <div data-testid="recurrence-config" /> }));
vi.mock('../../src/schedule/components/TriggerCondition',  () => ({ default: () => <div data-testid="trigger-condition" /> }));
vi.mock('../../src/schedule/components/TimePicker',        () => ({ default: () => <div data-testid="time-picker" /> }));
vi.mock('../../src/schedule/components/NextSchedules',     () => ({ default: () => <div data-testid="next-schedules" /> }));
vi.mock('../../src/schedule/components/DiagnosticsPanel',  () => ({ default: () => <div data-testid="diagnostics-panel" /> }));

import App from '../../src/schedule/App.jsx';

const SCHEDULE = { mode: 'daily', times: [], recurrence: { interval: 1, weekdays: [], monthDays: [] }, trigger: { count: 10, days: 7, tag_id: null }, notify: { enabled: false, email: '' }, post_status: 'publish' };
const DIAG     = { next_scheduled: null, last_run: null, wp_cron_disabled: false, cron_notice_dismissed: false };

function mockApiFetch({ schedule = SCHEDULE, diag = DIAG } = {}) {
    apiFetch.mockImplementation(({ path, method }) => {
        if (method === 'POST') return Promise.resolve({});
        if (path.includes('diagnostics'))  return Promise.resolve(diag);
        if (path.includes('/schedule'))    return Promise.resolve(schedule);
        return Promise.resolve({});
    });
}

beforeEach(() => {
    vi.clearAllMocks();
    mockApiFetch();
});

describe('App', () => {
    it('renders mode picker and sidebar panels', async () => {
        render(<App />);
        await waitFor(() => expect(apiFetch).toHaveBeenCalled());
        expect(screen.getByTestId('mode-picker')).toBeInTheDocument();
        expect(screen.getByTestId('next-schedules')).toBeInTheDocument();
        expect(screen.getByTestId('diagnostics-panel')).toBeInTheDocument();
    });

    it('fetches schedule and diagnostics on mount', async () => {
        render(<App />);
        await waitFor(() => expect(apiFetch).toHaveBeenCalledTimes(2));
        const paths = apiFetch.mock.calls.map(c => c[0].path);
        expect(paths).toContain('/linkdigest/v1/schedule');
        expect(paths.some(p => p.includes('diagnostics'))).toBe(true);
    });

    it('shows RecurrenceConfig for schedule mode', async () => {
        render(<App />);
        await waitFor(() => expect(screen.getByTestId('mode-picker')).toBeInTheDocument());
        expect(screen.getByTestId('recurrence-config')).toBeInTheDocument();
    });

    it('switches to TriggerCondition when trigger mode is selected', async () => {
        render(<App />);
        await waitFor(() => expect(screen.getByTestId('mode-picker')).toBeInTheDocument());

        fireEvent.click(screen.getByText('pick-count'));

        expect(screen.getByTestId('trigger-condition')).toBeInTheDocument();
        expect(screen.queryByTestId('recurrence-config')).not.toBeInTheDocument();
    });

    it('hides TimePicker in manual mode', async () => {
        render(<App />);
        await waitFor(() => expect(screen.getByTestId('mode-picker')).toBeInTheDocument());

        fireEvent.click(screen.getByText('pick-manual'));

        expect(screen.queryByTestId('time-picker')).not.toBeInTheDocument();
    });

    it('shows TimePicker in schedule mode', async () => {
        render(<App />);
        await waitFor(() => expect(screen.getByTestId('mode-picker')).toBeInTheDocument());
        expect(screen.getByTestId('time-picker')).toBeInTheDocument();
    });

    it('switching to weekly mode resets recurrence', async () => {
        render(<App />);
        await waitFor(() => expect(screen.getByTestId('mode-picker')).toBeInTheDocument());

        fireEvent.click(screen.getByText('pick-weekly'));

        expect(screen.getByTestId('mode-picker').dataset.value).toBe('weekly');
        fireEvent.click(screen.getByText('Save Schedule'));
        await waitFor(() => {
            const postCall = apiFetch.mock.calls.find(c => c[0].method === 'POST');
            expect(postCall[0].data.mode).toBe('weekly');
        });
    });

    describe('handleSave', () => {
        it('posts form data and shows success notice', async () => {
            render(<App />);
            await waitFor(() => expect(apiFetch).toHaveBeenCalledWith(expect.objectContaining({ path: '/linkdigest/v1/schedule' })));

            fireEvent.click(screen.getByText('Save Schedule'));

            await waitFor(() => expect(screen.getByText('Schedule saved.')).toBeInTheDocument());
            const postCall = apiFetch.mock.calls.find(c => c[0].method === 'POST');
            expect(postCall[0].data).toMatchObject({ mode: 'daily' });
        });

        it('shows error notice on API failure', async () => {
            apiFetch.mockImplementation(({ path, method }) => {
                if (method === 'POST') return Promise.reject(new Error('server error'));
                if (path.includes('diagnostics')) return Promise.resolve(DIAG);
                return Promise.resolve(SCHEDULE);
            });

            render(<App />);
            await waitFor(() => expect(screen.getByText('Save Schedule')).toBeInTheDocument());

            fireEvent.click(screen.getByText('Save Schedule'));

            await waitFor(() => expect(screen.getByText('Failed to save schedule.')).toBeInTheDocument());
        });

        it('validates duplicate times before posting', async () => {
            mockApiFetch({ schedule: { ...SCHEDULE, times: ['09:00', '09:00'] } });

            render(<App />);
            await waitFor(() => expect(screen.getByText('Save Schedule')).toBeInTheDocument());

            fireEvent.click(screen.getByText('Save Schedule'));

            await waitFor(() => expect(screen.getByText('Execution times must be unique.')).toBeInTheDocument());
            expect(apiFetch.mock.calls.every(c => c[0].method !== 'POST')).toBe(true);
        });
    });

    describe('WP-Cron warning', () => {
        it('shows warning banner when wp_cron_disabled is true', async () => {
            mockApiFetch({ diag: { ...DIAG, wp_cron_disabled: true } });

            render(<App />);

            await waitFor(() => expect(screen.getByText('WP-Cron is disabled.')).toBeInTheDocument());
        });

        it('hides warning when already dismissed', async () => {
            mockApiFetch({ diag: { ...DIAG, wp_cron_disabled: true, cron_notice_dismissed: true } });

            render(<App />);
            await waitFor(() => expect(apiFetch).toHaveBeenCalled());

            expect(screen.queryByText('WP-Cron is disabled.')).not.toBeInTheDocument();
        });
    });

    describe('Notifications', () => {
        it('shows email input when notify.enabled is true', async () => {
            mockApiFetch({ schedule: { ...SCHEDULE, notify: { enabled: true, email: 'test@example.com' } } });

            render(<App />);
            await waitFor(() => expect(screen.getByText('Email address')).toBeInTheDocument());
        });

        it('hides email input when notify.enabled is false', async () => {
            render(<App />);
            await waitFor(() => expect(apiFetch).toHaveBeenCalled());

            expect(screen.queryByText('Email address')).not.toBeInTheDocument();
        });
    });
});
