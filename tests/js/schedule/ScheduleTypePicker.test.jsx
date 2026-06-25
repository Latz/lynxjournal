import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { axe } from 'vitest-axe';
import ScheduleTypePicker from '../../../src/schedule/components/ScheduleTypePicker.jsx';

describe('ScheduleTypePicker', () => {
    it('renders all 6 mode buttons', () => {
        render(<ScheduleTypePicker value="daily" onChange={() => {}} />);
        expect(screen.getAllByRole('radio')).toHaveLength(6);
    });

    it('marks the active mode as aria-checked=true', () => {
        render(<ScheduleTypePicker value="weekly" onChange={() => {}} />);
        expect(screen.getByRole('radio', { name: /weekly/i })).toHaveAttribute('aria-checked', 'true');
    });

    it('marks all other modes as aria-checked=false', () => {
        render(<ScheduleTypePicker value="daily" onChange={() => {}} />);
        const unchecked = screen.getAllByRole('radio').filter(b => b.getAttribute('aria-checked') === 'false');
        expect(unchecked).toHaveLength(5);
    });

    it('calls onChange with the correct value when a mode is clicked', async () => {
        const onChange = vi.fn();
        render(<ScheduleTypePicker value="daily" onChange={onChange} />);
        await userEvent.click(screen.getByRole('radio', { name: /monthly/i }));
        expect(onChange).toHaveBeenCalledWith('monthly');
    });

    it('has no accessibility violations', async () => {
        const { container } = render(<ScheduleTypePicker value="daily" onChange={() => {}} />);
        expect(await axe(container)).toHaveNoViolations();
    });

    it('renders the three group labels', () => {
        render(<ScheduleTypePicker value="daily" onChange={() => {}} />);
        expect(screen.getByText('Scheduled')).toBeInTheDocument();
        expect(screen.getByText('Trigger-based')).toBeInTheDocument();
        // "Manual" appears both as a group label and as a button title — verify at least one
        expect(screen.getAllByText('Manual').length).toBeGreaterThanOrEqual(1);
    });
});
