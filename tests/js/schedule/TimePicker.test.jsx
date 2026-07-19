import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { axe } from 'vitest-axe';
import TimePicker from '../../../src/schedule/components/TimePicker.jsx';

describe('TimePicker', () => {
    it('has no accessibility violations', async () => {
        const { container } = render(<TimePicker times={['09:00']} onChange={() => {}} />);  // skipcq: JS-0057 - intentional no-op test stub
        expect(await axe(container)).toHaveNoViolations();
    });

    it('renders one time input per initial time', () => {
        render(<TimePicker times={['09:00', '18:00']} onChange={() => {}} />);  // skipcq: JS-0057 - intentional no-op test stub
        expect(screen.getAllByDisplayValue(/\d{2}:\d{2}/)).toHaveLength(2);
    });

    it('does not show remove button when only one time exists', () => {
        render(<TimePicker times={['09:00']} onChange={() => {}} />);  // skipcq: JS-0057 - intentional no-op test stub
        expect(screen.queryByLabelText(/remove time/i)).not.toBeInTheDocument();
    });

    it('shows remove buttons when multiple times exist', () => {
        render(<TimePicker times={['09:00', '18:00']} onChange={() => {}} />);  // skipcq: JS-0057 - intentional no-op test stub
        expect(screen.getAllByLabelText(/remove time/i)).toHaveLength(2);
    });

    it('calls onChange with a new 09:00 entry when Add time is clicked', async () => {
        const onChange = vi.fn();
        render(<TimePicker times={['08:00']} onChange={onChange} />);
        await userEvent.click(screen.getByRole('button', { name: /add time/i }));
        expect(onChange).toHaveBeenCalledWith(['08:00', '09:00']);
    });

    it('calls onChange without the removed entry when remove is clicked', async () => {
        const onChange = vi.fn();
        render(<TimePicker times={['09:00', '18:00']} onChange={onChange} />);
        const removeButtons = screen.getAllByLabelText(/remove time/i);
        await userEvent.click(removeButtons[0]);
        expect(onChange).toHaveBeenCalledWith(['18:00']);
    });

    it('calls onChange with updated time when a time input changes', () => {
        const onChange = vi.fn();
        render(<TimePicker times={['09:00']} onChange={onChange} />);
        fireEvent.change(screen.getByDisplayValue('09:00'), { target: { value: '12:30' } });
        expect(onChange).toHaveBeenLastCalledWith(['12:30']);
    });
});
