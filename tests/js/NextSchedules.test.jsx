import { describe, it, expect, afterEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import NextSchedules from '../../src/schedule/components/NextSchedules.jsx';

describe('NextSchedules', () => {
    afterEach(() => {
        delete globalThis.lynxjournalSchedule;
    });

    it('renders nothing for a non-schedule (trigger-based) mode', () => {
        const { container } = render(
            <NextSchedules config={{ rrule: null, times: [], trigger: { type: 'count' } }} form={{ mode: 'count', times: [] }} />
        );

        expect(container).toBeEmptyDOMElement();
    });

    it('shows the no-occurrences message when the rrule is null', () => {
        render(
            <NextSchedules config={{ rrule: null, times: ['09:00'] }} form={{ mode: 'daily', times: ['09:00'] }} />
        );

        expect(screen.getByText('No occurrences — check recurrence settings.')).toBeInTheDocument();
    });

    it('lists upcoming occurrence dates with the execution times', () => {
        render(
            <NextSchedules
                config={{ rrule: 'FREQ=DAILY', times: ['09:00', '17:00'] }}
                form={{ mode: 'daily', times: ['09:00', '17:00'] }}
            />
        );

        expect(screen.getByText('Next 10 Schedules')).toBeInTheDocument();
        const rows = screen.getAllByText('09:00, 17:00');
        expect(rows.length).toBeGreaterThan(0);
    });

    it('falls back to 00:00 as the displayed time when no times are set', () => {
        render(
            <NextSchedules config={{ rrule: 'FREQ=DAILY', times: [] }} form={{ mode: 'daily', times: [] }} />
        );

        expect(screen.getAllByText('00:00').length).toBeGreaterThan(0);
    });

    it('shows the no-occurrences message when the rrule string is invalid', () => {
        render(
            <NextSchedules config={{ rrule: 'NOT-A-VALID-RULE', times: ['09:00'] }} form={{ mode: 'daily', times: ['09:00'] }} />
        );

        expect(screen.getByText('No occurrences — check recurrence settings.')).toBeInTheDocument();
    });

    it('shows the site timezone note when configured', async () => {
        // SITE_TIMEZONE is snapshotted from globalThis at module-evaluation time,
        // so the global must be set before a fresh import of the module.
        globalThis.lynxjournalSchedule = { timezone: 'Europe/Berlin' };
        vi.resetModules();
        const { default: NextSchedulesWithTz } = await import('../../src/schedule/components/NextSchedules.jsx?tz-test');

        render(
            <NextSchedulesWithTz config={{ rrule: 'FREQ=DAILY', times: ['09:00'] }} form={{ mode: 'daily', times: ['09:00'] }} />
        );

        expect(screen.getByText('Times in Europe/Berlin')).toBeInTheDocument();
    });
});
