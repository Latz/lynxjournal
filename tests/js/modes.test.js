import { describe, it, expect, afterEach } from 'vitest';

describe('schedule/lib/modes.js', () => {
    afterEach(() => {
        delete globalThis.lynxjournalSchedule;
        vi.resetModules();
    });

    it('falls back to default mode sets when globalThis.lynxjournalSchedule is absent', async () => {
        delete globalThis.lynxjournalSchedule;
        const { SCHEDULE_MODES, TRIGGER_MODES, ALL_MODES } = await import('../../src/schedule/lib/modes.js?no-loc');

        expect([...SCHEDULE_MODES]).toEqual(['daily', 'weekly', 'monthly']);
        expect([...TRIGGER_MODES]).toEqual(['count', 'age']);
        expect(ALL_MODES).toEqual(['daily', 'weekly', 'monthly', 'count', 'age', 'manual']);
    });

    it('uses localized mode lists from globalThis.lynxjournalSchedule when present', async () => {
        globalThis.lynxjournalSchedule = {
            timeModes: ['daily'],
            triggerModes: ['count'],
            allModes: ['daily', 'count', 'manual'],
        };

        const { SCHEDULE_MODES, TRIGGER_MODES, ALL_MODES } = await import('../../src/schedule/lib/modes.js?with-loc');

        expect([...SCHEDULE_MODES]).toEqual(['daily']);
        expect([...TRIGGER_MODES]).toEqual(['count']);
        expect(ALL_MODES).toEqual(['daily', 'count', 'manual']);
    });
});
