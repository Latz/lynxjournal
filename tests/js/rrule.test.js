import { describe, it, expect } from 'vitest';
import { buildRRule, describeSchedule } from '../../src/schedule/lib/rrule.js';

describe('buildRRule', () => {
    describe('daily', () => {
        it('interval 1 omits INTERVAL', () => {
            expect(buildRRule({ type: 'daily' })).toBe('FREQ=DAILY');
        });

        it('interval > 1 includes INTERVAL', () => {
            expect(buildRRule({ type: 'daily', interval: 3 })).toBe('FREQ=DAILY;INTERVAL=3');
        });
    });

    describe('weekly', () => {
        it('no weekdays omits BYDAY', () => {
            expect(buildRRule({ type: 'weekly' })).toBe('FREQ=WEEKLY');
        });

        it('with weekdays includes BYDAY', () => {
            expect(buildRRule({ type: 'weekly', weekdays: ['MO', 'FR'] })).toBe('FREQ=WEEKLY;BYDAY=MO,FR');
        });

        it('with nthWeek prefixes each day', () => {
            expect(buildRRule({ type: 'weekly', weekdays: ['TU'], nthWeek: 2 })).toBe('FREQ=WEEKLY;BYDAY=2TU');
        });

        it('with interval and weekdays', () => {
            expect(buildRRule({ type: 'weekly', interval: 2, weekdays: ['WE'] })).toBe('FREQ=WEEKLY;INTERVAL=2;BYDAY=WE');
        });
    });

    describe('monthly', () => {
        it('day-type entry uses BYMONTHDAY', () => {
            const monthDays = [{ type: 'day', value: 15, nth: 1, weekday: 'MO' }];
            expect(buildRRule({ type: 'monthly', monthDays })).toBe('FREQ=MONTHLY;BYMONTHDAY=15');
        });

        it('nth-type entry uses BYDAY', () => {
            const monthDays = [{ type: 'nth', value: 1, nth: 2, weekday: 'TU' }];
            expect(buildRRule({ type: 'monthly', monthDays })).toBe('FREQ=MONTHLY;BYDAY=2TU');
        });

        it('mixed day and nth entries', () => {
            const monthDays = [
                { type: 'day', value: 1, nth: 1, weekday: 'MO' },
                { type: 'nth', value: 1, nth: 3, weekday: 'FR' },
            ];
            expect(buildRRule({ type: 'monthly', monthDays })).toBe('FREQ=MONTHLY;BYMONTHDAY=1;BYDAY=3FR');
        });

        it('multiple day entries', () => {
            const monthDays = [
                { type: 'day', value: 1, nth: 1, weekday: 'MO' },
                { type: 'day', value: 15, nth: 1, weekday: 'MO' },
            ];
            expect(buildRRule({ type: 'monthly', monthDays })).toBe('FREQ=MONTHLY;BYMONTHDAY=1,15');
        });

        it('with interval', () => {
            const monthDays = [{ type: 'day', value: 1, nth: 1, weekday: 'MO' }];
            expect(buildRRule({ type: 'monthly', interval: 3, monthDays })).toBe('FREQ=MONTHLY;INTERVAL=3;BYMONTHDAY=1');
        });

        it('empty monthDays omits BYMONTHDAY and BYDAY', () => {
            expect(buildRRule({ type: 'monthly', monthDays: [] })).toBe('FREQ=MONTHLY');
        });
    });

    it('unknown type falls back to DAILY', () => {
        expect(buildRRule({ type: 'hourly' })).toBe('FREQ=DAILY');
    });
});

describe('describeSchedule', () => {
    describe('daily', () => {
        it('interval 1 → "Every day"', () => {
            expect(describeSchedule({ type: 'daily' })).toBe('Every day');
        });

        it('interval > 1 → "Every N days"', () => {
            expect(describeSchedule({ type: 'daily', interval: 5 })).toBe('Every 5 days');
        });
    });

    describe('weekly', () => {
        it('no weekdays uses fallback text', () => {
            expect(describeSchedule({ type: 'weekly' })).toBe('Every week on selected days');
        });

        it('single weekday', () => {
            expect(describeSchedule({ type: 'weekly', weekdays: ['MO'] })).toBe('Every week on Monday');
        });

        it('multiple weekdays joined by comma', () => {
            expect(describeSchedule({ type: 'weekly', weekdays: ['MO', 'WE', 'FR'] })).toBe('Every week on Monday, Wednesday, Friday');
        });

        it('interval > 1 → "Every N weeks"', () => {
            expect(describeSchedule({ type: 'weekly', interval: 2, weekdays: ['TU'] })).toBe('Every 2 weeks on Tuesday');
        });
    });

    describe('monthly', () => {
        it('single day entry uses ordinal', () => {
            const monthDays = [{ type: 'day', value: 1, nth: 1, weekday: 'MO' }];
            expect(describeSchedule({ type: 'monthly', monthDays })).toBe('Every month on the 1st');
        });

        it('nth-type entry', () => {
            const monthDays = [{ type: 'nth', value: 1, nth: 2, weekday: 'TU' }];
            expect(describeSchedule({ type: 'monthly', monthDays })).toBe('Every month on the second Tuesday');
        });

        it('interval > 1', () => {
            const monthDays = [{ type: 'day', value: 15, nth: 1, weekday: 'MO' }];
            expect(describeSchedule({ type: 'monthly', interval: 3, monthDays })).toBe('Every 3 months on the 15th');
        });
    });

    it('unknown type returns empty string', () => {
        expect(describeSchedule({ type: 'hourly' })).toBe('');
    });
});
