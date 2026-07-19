import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { axe } from 'vitest-axe';
import RecurrenceConfig from '../../../src/schedule/components/RecurrenceConfig.jsx';
import { noop } from '../test-utils.js';

const baseValue = {
    interval: 1,
    weekdays: [],
    monthDays: [{ type: 'day', value: 1, nth: 1, weekday: 'MO' }],
    nthWeek: null,
};

describe('RecurrenceConfig — daily', () => {
    it('has no accessibility violations', async () => {
        const { container } = render(<RecurrenceConfig type="daily" value={baseValue} onChange={noop} />);
        expect(await axe(container)).toHaveNoViolations();
    });

    it('renders an interval number input', () => {
        render(<RecurrenceConfig type="daily" value={baseValue} onChange={noop} />);
        expect(screen.getByRole('spinbutton')).toBeInTheDocument();
    });

    it('shows singular "day" when interval is 1', () => {
        render(<RecurrenceConfig type="daily" value={{ ...baseValue, interval: 1 }} onChange={noop} />);
        expect(screen.getByText('day')).toBeInTheDocument();
    });

    it('shows plural "days" when interval is > 1', () => {
        render(<RecurrenceConfig type="daily" value={{ ...baseValue, interval: 3 }} onChange={noop} />);
        expect(screen.getByText('days')).toBeInTheDocument();
    });

    it('calls onChange with updated interval', () => {
        const onChange = vi.fn();
        render(<RecurrenceConfig type="daily" value={{ ...baseValue, interval: 1 }} onChange={onChange} />);
        fireEvent.change(screen.getByRole('spinbutton'), { target: { value: '7' } });
        expect(onChange).toHaveBeenLastCalledWith(expect.objectContaining({ interval: 7 }));
    });
});

describe('RecurrenceConfig — weekly', () => {
    it('renders 7 weekday toggle buttons', () => {
        render(<RecurrenceConfig type="weekly" value={baseValue} onChange={noop} />);
        const weekdayButtons = screen.getAllByRole('button');
        // 7 day buttons
        expect(weekdayButtons.length).toBeGreaterThanOrEqual(7);
    });

    it('calls onChange with the toggled weekday added', async () => {
        const onChange = vi.fn();
        render(<RecurrenceConfig type="weekly" value={{ ...baseValue, weekdays: [] }} onChange={onChange} />);
        await userEvent.click(screen.getByRole('button', { name: /^Mon$/i }));
        expect(onChange).toHaveBeenCalledWith(expect.objectContaining({ weekdays: ['MO'] }));
    });

    it('calls onChange with the weekday removed when already selected', async () => {
        const onChange = vi.fn();
        render(<RecurrenceConfig type="weekly" value={{ ...baseValue, weekdays: ['MO'] }} onChange={onChange} />);
        await userEvent.click(screen.getByRole('button', { name: /^Mon$/i }));
        expect(onChange).toHaveBeenCalledWith(expect.objectContaining({ weekdays: [] }));
    });

    it('shows singular "week" when interval is 1', () => {
        render(<RecurrenceConfig type="weekly" value={{ ...baseValue, interval: 1 }} onChange={noop} />);
        expect(screen.getByText('week')).toBeInTheDocument();
    });

    it('shows plural "weeks" when interval is > 1', () => {
        render(<RecurrenceConfig type="weekly" value={{ ...baseValue, interval: 2 }} onChange={noop} />);
        expect(screen.getByText('weeks')).toBeInTheDocument();
    });
});

describe('RecurrenceConfig — monthly', () => {
    it('renders an interval number input and an Add day button', () => {
        render(<RecurrenceConfig type="monthly" value={baseValue} onChange={noop} />);
        expect(screen.getByText(/add day/i)).toBeInTheDocument();
        expect(screen.getAllByRole('spinbutton').length).toBeGreaterThanOrEqual(1);
    });

    it('calls onChange with a new month-day entry when Add day is clicked', async () => {
        const onChange = vi.fn();
        render(<RecurrenceConfig type="monthly" value={baseValue} onChange={onChange} />);
        await userEvent.click(screen.getByText(/add day/i));
        const { monthDays } = onChange.mock.calls[0][0];
        expect(monthDays).toHaveLength(2);
    });

    it('hides the Remove button when only one month-day entry exists', () => {
        render(<RecurrenceConfig type="monthly" value={baseValue} onChange={noop} />);
        expect(screen.queryByLabelText('Remove')).not.toBeInTheDocument();
    });

    it('shows Remove buttons when multiple month-day entries exist', () => {
        const value = {
            ...baseValue,
            monthDays: [
                { type: 'day', value: 1, nth: 1, weekday: 'MO' },
                { type: 'day', value: 5, nth: 1, weekday: 'MO' },
            ],
        };
        render(<RecurrenceConfig type="monthly" value={value} onChange={noop} />);
        expect(screen.getAllByLabelText('Remove')).toHaveLength(2);
    });

    it('calls onChange without the removed entry when Remove is clicked', async () => {
        const onChange = vi.fn();
        const value = {
            ...baseValue,
            monthDays: [
                { type: 'day', value: 1, nth: 1, weekday: 'MO' },
                { type: 'day', value: 5, nth: 1, weekday: 'MO' },
            ],
        };
        render(<RecurrenceConfig type="monthly" value={value} onChange={onChange} />);
        await userEvent.click(screen.getAllByLabelText('Remove')[0]);
        const { monthDays } = onChange.mock.calls[0][0];
        expect(monthDays).toHaveLength(1);
        expect(monthDays[0].value).toBe(5);
    });

    it('renders the nth/weekday selects for an nth-type entry', () => {
        const value = {
            ...baseValue,
            monthDays: [{ type: 'nth', value: 1, nth: 2, weekday: 'FR' }],
        };
        render(<RecurrenceConfig type="monthly" value={value} onChange={noop} />);
        const selects = screen.getAllByRole('combobox');
        expect(selects.length).toBeGreaterThanOrEqual(2);
    });
});

describe('RecurrenceConfig — unknown type', () => {
    it('renders nothing for an unrecognised type', () => {
        const { container } = render(
            <RecurrenceConfig type="unknown" value={baseValue} onChange={noop} />
        );
        expect(container).toBeEmptyDOMElement();
    });
});
