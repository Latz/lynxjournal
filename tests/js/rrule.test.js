import { describe, it, expect } from 'vitest';
import { buildRRule } from '../../src/schedule/lib/rrule.js';

describe('buildRRule()', () => {
    it('builds a daily rule with default interval', () => {
        expect(buildRRule({ type: 'daily' })).toBe('FREQ=DAILY');
    });

    it('includes INTERVAL when not 1', () => {
        expect(buildRRule({ type: 'daily', interval: 3 })).toBe('FREQ=DAILY;INTERVAL=3');
    });

    it('builds a weekly rule with BYDAY for selected weekdays', () => {
        expect(buildRRule({ type: 'weekly', weekdays: ['MO', 'WE'] })).toBe('FREQ=WEEKLY;BYDAY=MO,WE');
    });

    it('omits BYDAY for weekly mode when no weekdays are selected', () => {
        expect(buildRRule({ type: 'weekly', weekdays: [] })).toBe('FREQ=WEEKLY');
    });

    it('prefixes BYDAY entries with nthWeek when set', () => {
        expect(buildRRule({ type: 'weekly', weekdays: ['MO'], nthWeek: 2 })).toBe('FREQ=WEEKLY;BYDAY=2MO');
    });

    it('builds a monthly rule with BYMONTHDAY for day-type entries', () => {
        const monthDays = [{ type: 'day', value: 1 }, { type: 'day', value: 15 }];
        expect(buildRRule({ type: 'monthly', monthDays })).toBe('FREQ=MONTHLY;BYMONTHDAY=1,15');
    });

    it('builds a monthly rule with BYDAY for nth-weekday entries', () => {
        const monthDays = [{ type: 'nth', nth: 1, weekday: 'MO' }];
        expect(buildRRule({ type: 'monthly', monthDays })).toBe('FREQ=MONTHLY;BYDAY=1MO');
    });

    it('combines BYMONTHDAY and nth BYDAY for mixed monthly entries', () => {
        const monthDays = [
            { type: 'day', value: 1 },
            { type: 'nth', nth: 2, weekday: 'FR' },
        ];
        expect(buildRRule({ type: 'monthly', monthDays })).toBe('FREQ=MONTHLY;BYMONTHDAY=1;BYDAY=2FR');
    });

    it('omits BYMONTHDAY/BYDAY for monthly mode with an empty monthDays array', () => {
        expect(buildRRule({ type: 'monthly', monthDays: [] })).toBe('FREQ=MONTHLY');
    });

    it('defaults FREQ to DAILY for an unrecognized type', () => {
        expect(buildRRule({ type: 'bogus' })).toBe('FREQ=DAILY');
    });
});
