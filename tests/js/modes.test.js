import { describe, it, expect } from 'vitest';
import { SCHEDULE_MODES, TRIGGER_MODES, ALL_MODES } from '../../src/schedule/lib/modes.js';

describe('modes', () => {
    describe('SCHEDULE_MODES', () => {
        it('contains time-based modes', () => {
            expect(SCHEDULE_MODES.has('daily')).toBe(true);
            expect(SCHEDULE_MODES.has('weekly')).toBe(true);
            expect(SCHEDULE_MODES.has('monthly')).toBe(true);
        });

        it('does not contain trigger or manual modes', () => {
            expect(SCHEDULE_MODES.has('count')).toBe(false);
            expect(SCHEDULE_MODES.has('age')).toBe(false);
            expect(SCHEDULE_MODES.has('manual')).toBe(false);
        });
    });

    describe('TRIGGER_MODES', () => {
        it('contains trigger-based modes', () => {
            expect(TRIGGER_MODES.has('count')).toBe(true);
            expect(TRIGGER_MODES.has('age')).toBe(true);
        });

        it('does not contain schedule or manual modes', () => {
            expect(TRIGGER_MODES.has('daily')).toBe(false);
            expect(TRIGGER_MODES.has('manual')).toBe(false);
        });
    });

    describe('ALL_MODES', () => {
        it('includes all six modes', () => {
            expect(ALL_MODES).toEqual(expect.arrayContaining(['daily', 'weekly', 'monthly', 'count', 'age', 'manual']));
        });

        it('has exactly six entries', () => {
            expect(ALL_MODES).toHaveLength(6);
        });
    });
});
