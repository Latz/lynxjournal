import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import ScheduleTypePicker from '../../src/schedule/components/ScheduleTypePicker.jsx';

describe('ScheduleTypePicker', () => {
    it('renders all mode cards grouped by category', () => {
        render(<ScheduleTypePicker value="daily" onChange={() => {}} />);

        expect(screen.getByText('Scheduled')).toBeInTheDocument();
        expect(screen.getByText('Trigger-based')).toBeInTheDocument();
        ['Daily', 'Weekly', 'Monthly', 'By Count', 'By Age'].forEach(label => {
            expect(screen.getByText(label)).toBeInTheDocument();
        });
        expect(screen.getByRole('radio', { name: /Manual/ })).toBeInTheDocument();
    });

    it('marks the current value as the checked radio', () => {
        render(<ScheduleTypePicker value="weekly" onChange={() => {}} />);

        const weeklyCard = screen.getByRole('radio', { name: /Weekly/ });
        expect(weeklyCard).toHaveAttribute('aria-checked', 'true');

        const dailyCard = screen.getByRole('radio', { name: /Daily/ });
        expect(dailyCard).toHaveAttribute('aria-checked', 'false');
    });

    it('calls onChange with the clicked mode value', async () => {
        const onChange = vi.fn();
        const user = userEvent.setup();
        render(<ScheduleTypePicker value="daily" onChange={onChange} />);

        await user.click(screen.getByRole('radio', { name: /By Count/ }));

        expect(onChange).toHaveBeenCalledWith('count');
    });
});
