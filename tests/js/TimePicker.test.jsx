import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import TimePicker from '../../src/schedule/components/TimePicker.jsx';

describe('TimePicker', () => {
    it('renders one time input per entry', () => {
        render(<TimePicker times={['09:00', '17:00']} onChange={() => {}} />);  // skipcq: JS-0057 - intentional no-op test stub

        expect(screen.getAllByDisplayValue(/09:00|17:00/)).toHaveLength(2);
    });

    it('hides the remove button when only one time remains', () => {
        render(<TimePicker times={['09:00']} onChange={() => {}} />);  // skipcq: JS-0057 - intentional no-op test stub

        expect(screen.queryByRole('button', { name: 'Remove time' })).not.toBeInTheDocument();
    });

    it('shows remove buttons when more than one time exists', () => {
        render(<TimePicker times={['09:00', '17:00']} onChange={() => {}} />);  // skipcq: JS-0057 - intentional no-op test stub

        expect(screen.getAllByRole('button', { name: 'Remove time' })).toHaveLength(2);
    });

    it('appends a default 09:00 entry when "Add time" is clicked', async () => {
        const onChange = vi.fn();
        const user = userEvent.setup();
        render(<TimePicker times={['17:00']} onChange={onChange} />);

        await user.click(screen.getByRole('button', { name: /Add time/ }));

        expect(onChange).toHaveBeenCalledWith(['17:00', '09:00']);
    });

    it('removes the time at the clicked position', async () => {
        const onChange = vi.fn();
        const user = userEvent.setup();
        render(<TimePicker times={['09:00', '17:00']} onChange={onChange} />);

        await user.click(screen.getAllByRole('button', { name: 'Remove time' })[0]);

        expect(onChange).toHaveBeenCalledWith(['17:00']);
    });

    it('updates the time at the changed position', () => {
        const onChange = vi.fn();
        render(<TimePicker times={['09:00', '17:00']} onChange={onChange} />);

        const inputs = screen.getAllByDisplayValue(/09:00|17:00/);
        fireEvent.change(inputs[0], { target: { value: '10:30' } });

        expect(onChange).toHaveBeenCalledWith(['10:30', '17:00']);
    });
});
