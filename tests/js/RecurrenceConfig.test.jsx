import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import RecurrenceConfig from '../../src/schedule/components/RecurrenceConfig.jsx';

describe('RecurrenceConfig — daily', () => {
    it('renders the interval control with the singular label', () => {
        render(<RecurrenceConfig type="daily" value={{ interval: 1 }} onChange={() => {}} />);

        expect(screen.getByDisplayValue('1')).toBeInTheDocument();
        expect(screen.getByText('day')).toBeInTheDocument();
    });

    it('renders the plural label for interval > 1', () => {
        render(<RecurrenceConfig type="daily" value={{ interval: 3 }} onChange={() => {}} />);

        expect(screen.getByText('days')).toBeInTheDocument();
    });

    it('calls onChange with a parsed interval', () => {
        const onChange = vi.fn();
        render(<RecurrenceConfig type="daily" value={{ interval: 1 }} onChange={onChange} />);

        fireEvent.change(screen.getByDisplayValue('1'), { target: { value: '5' } });

        expect(onChange).toHaveBeenCalledWith({ interval: 5 });
    });
});

describe('RecurrenceConfig — weekly', () => {
    it('renders a button per weekday and highlights the selected ones', () => {
        render(<RecurrenceConfig type="weekly" value={{ interval: 1, weekdays: ['MO', 'WE'] }} onChange={() => {}} />);

        const mon = screen.getByRole('button', { name: 'Mon' });
        const tue = screen.getByRole('button', { name: 'Tue' });
        expect(mon.getAttribute('title')).toBe('Monday');
        expect(tue.getAttribute('title')).toBe('Tuesday');
    });

    it('toggles a weekday on when its button is clicked', async () => {
        const onChange = vi.fn();
        const user = userEvent.setup();
        render(<RecurrenceConfig type="weekly" value={{ interval: 1, weekdays: ['MO'] }} onChange={onChange} />);

        await user.click(screen.getByRole('button', { name: 'Fri' }));

        expect(onChange).toHaveBeenCalledWith({ interval: 1, weekdays: ['MO', 'FR'] });
    });

    it('toggles a weekday off when it is already selected', async () => {
        const onChange = vi.fn();
        const user = userEvent.setup();
        render(<RecurrenceConfig type="weekly" value={{ interval: 1, weekdays: ['MO', 'FR'] }} onChange={onChange} />);

        await user.click(screen.getByRole('button', { name: 'Mon' }));

        expect(onChange).toHaveBeenCalledWith({ interval: 1, weekdays: ['FR'] });
    });

    it('handles a missing weekdays array as empty', async () => {
        const onChange = vi.fn();
        const user = userEvent.setup();
        render(<RecurrenceConfig type="weekly" value={{ interval: 1 }} onChange={onChange} />);

        await user.click(screen.getByRole('button', { name: 'Sun' }));

        expect(onChange).toHaveBeenCalledWith({ interval: 1, weekdays: ['SU'] });
    });

    it('renders the plural "weeks" label for interval > 1', () => {
        render(<RecurrenceConfig type="weekly" value={{ interval: 2, weekdays: [] }} onChange={() => {}} />);

        expect(screen.getByText('weeks')).toBeInTheDocument();
    });
});

describe('RecurrenceConfig — monthly', () => {
    it('defaults to a single day-1 entry when monthDays is absent', () => {
        render(<RecurrenceConfig type="monthly" value={{ interval: 1 }} onChange={() => {}} />);

        expect(screen.getAllByDisplayValue('1')).toHaveLength(2); // interval input + day-1 entry
        expect(screen.getByText('month, on')).toBeInTheDocument();
    });

    it('renders the plural "months, on" label for interval > 1', () => {
        render(<RecurrenceConfig type="monthly" value={{ interval: 2, monthDays: [{ type: 'day', value: 1 }] }} onChange={() => {}} />);

        expect(screen.getByText('months, on')).toBeInTheDocument();
    });

    it('hides the remove button when only one month-day entry exists', () => {
        render(<RecurrenceConfig type="monthly" value={{ interval: 1, monthDays: [{ type: 'day', value: 1 }] }} onChange={() => {}} />);

        expect(screen.queryByRole('button', { name: 'Remove' })).not.toBeInTheDocument();
    });

    it('shows remove buttons once more than one entry exists', () => {
        const monthDays = [{ type: 'day', value: 1 }, { type: 'day', value: 15 }];
        render(<RecurrenceConfig type="monthly" value={{ interval: 1, monthDays }} onChange={() => {}} />);

        expect(screen.getAllByRole('button', { name: 'Remove' })).toHaveLength(2);
    });

    it('appends a cloned entry (value+1) when "Add day" is clicked', async () => {
        const onChange = vi.fn();
        const user = userEvent.setup();
        const monthDays = [{ type: 'day', value: 5, nth: 1, weekday: 'MO' }];
        render(<RecurrenceConfig type="monthly" value={{ interval: 1, monthDays }} onChange={onChange} />);

        await user.click(screen.getByRole('button', { name: /Add day/ }));

        const call = onChange.mock.calls[0][0];
        expect(call.monthDays).toHaveLength(2);
        expect(call.monthDays[1]).toMatchObject({ type: 'day', value: 6, nth: 1, weekday: 'MO' });
    });

    it('caps the cloned value at 31', async () => {
        const onChange = vi.fn();
        const user = userEvent.setup();
        const monthDays = [{ type: 'day', value: 31, nth: 1, weekday: 'MO' }];
        render(<RecurrenceConfig type="monthly" value={{ interval: 1, monthDays }} onChange={onChange} />);

        await user.click(screen.getByRole('button', { name: /Add day/ }));

        expect(onChange.mock.calls[0][0].monthDays[1].value).toBe(31);
    });

    it('removes the entry at the clicked position', async () => {
        const onChange = vi.fn();
        const user = userEvent.setup();
        const monthDays = [{ type: 'day', value: 1 }, { type: 'day', value: 15 }];
        render(<RecurrenceConfig type="monthly" value={{ interval: 1, monthDays }} onChange={onChange} />);

        await user.click(screen.getAllByRole('button', { name: 'Remove' })[0]);

        expect(onChange).toHaveBeenCalledWith({ interval: 1, monthDays: [{ type: 'day', value: 15 }] });
    });

    it('switches an entry to nth mode when its selector zone is clicked', async () => {
        const onChange = vi.fn();
        const user = userEvent.setup();
        const monthDays = [{ type: 'day', value: 1, nth: 1, weekday: 'MO' }];
        render(<RecurrenceConfig type="monthly" value={{ interval: 1, monthDays }} onChange={onChange} />);

        await user.click(screen.getByRole('button', { pressed: false }));

        expect(onChange).toHaveBeenCalledWith({
            interval: 1,
            monthDays: [{ type: 'nth', value: 1, nth: 1, weekday: 'MO' }],
        });
    });

    it('updates the nth select for an nth-type entry', () => {
        const onChange = vi.fn();
        const monthDays = [{ type: 'nth', nth: 1, weekday: 'MO' }];
        render(<RecurrenceConfig type="monthly" value={{ interval: 1, monthDays }} onChange={onChange} />);

        const [nthSelect] = screen.getAllByRole('combobox');
        fireEvent.change(nthSelect, { target: { value: '3' } });

        expect(onChange).toHaveBeenCalledWith({
            interval: 1,
            monthDays: [{ type: 'nth', nth: 3, weekday: 'MO' }],
        });
    });

    it('updates the weekday select for an nth-type entry', () => {
        const onChange = vi.fn();
        const monthDays = [{ type: 'nth', nth: 1, weekday: 'MO' }];
        render(<RecurrenceConfig type="monthly" value={{ interval: 1, monthDays }} onChange={onChange} />);

        const selects = screen.getAllByRole('combobox');
        fireEvent.change(selects[1], { target: { value: 'FR' } });

        expect(onChange).toHaveBeenCalledWith({
            interval: 1,
            monthDays: [{ type: 'nth', nth: 1, weekday: 'FR' }],
        });
    });
});

describe('RecurrenceConfig — unrecognized type', () => {
    it('renders nothing', () => {
        const { container } = render(<RecurrenceConfig type="manual" value={{}} onChange={() => {}} />);

        expect(container).toBeEmptyDOMElement();
    });
});
