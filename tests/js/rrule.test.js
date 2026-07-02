import { describe, it, expect } from 'vitest';
import { buildRRule, describeSchedule } from '../../src/schedule/lib/rrule.js';

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

describe('describeSchedule()', () => {
    it('describes daily with default interval', () => {
        expect(describeSchedule({ type: 'daily' })).toBe('Every day');
    });

    it('describes daily with an interval', () => {
        expect(describeSchedule({ type: 'daily', interval: 3 })).toBe('Every 3 days');
    });

    it('describes weekly with selected days and default interval', () => {
        expect(describeSchedule({ type: 'weekly', weekdays: ['MO', 'WE'] })).toBe('Every week on Monday, Wednesday');
    });

    it('describes weekly with no selected days', () => {
        expect(describeSchedule({ type: 'weekly', weekdays: [] })).toBe('Every week on selected days');
    });

    it('describes weekly with an interval greater than 1', () => {
        expect(describeSchedule({ type: 'weekly', weekdays: ['MO'], interval: 2 })).toBe('Every 2 weeks on Monday');
    });

    it('describes weekly with nthWeek even at interval 1', () => {
        expect(describeSchedule({ type: 'weekly', weekdays: ['MO'], nthWeek: 2 })).toBe('Every 1 week on Monday');
    });

    it('describes monthly with day-type entries using ordinals', () => {
        const monthDays = [{ type: 'day', value: 1 }, { type: 'day', value: 22 }];
        expect(describeSchedule({ type: 'monthly', monthDays })).toBe('Every month on the 1st, 22nd');
    });

    it('describes monthly with nth-weekday entries', () => {
        const monthDays = [{ type: 'nth', nth: 1, weekday: 'MO' }];
        expect(describeSchedule({ type: 'monthly', monthDays })).toBe('Every month on the first Monday');
    });

    it('describes monthly with an interval greater than 1', () => {
        const monthDays = [{ type: 'day', value: 1 }];
        expect(describeSchedule({ type: 'monthly', monthDays, interval: 2 })).toBe('Every 2 months on the 1st');
    });

    it('returns an empty string for an unrecognized type', () => {
        expect(describeSchedule({ type: 'bogus' })).toBe('');
    });

    it('formats ordinals correctly for teens (11th, 12th, 13th)', () => {
        const monthDays = [{ type: 'day', value: 11 }, { type: 'day', value: 12 }, { type: 'day', value: 13 }];
        expect(describeSchedule({ type: 'monthly', monthDays })).toBe('Every month on the 11th, 12th, 13th');
    });

    it('formats ordinals correctly for 2nd and 3rd', () => {
        const monthDays = [{ type: 'day', value: 2 }, { type: 'day', value: 3 }];
        expect(describeSchedule({ type: 'monthly', monthDays })).toBe('Every month on the 2nd, 3rd');
    });
});
