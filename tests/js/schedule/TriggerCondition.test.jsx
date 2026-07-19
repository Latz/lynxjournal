import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { axe } from 'vitest-axe';
import TriggerCondition from '../../../src/schedule/components/TriggerCondition.jsx';

describe('TriggerCondition', () => {
    it('has no accessibility violations (count mode)', async () => {
        const { container } = render(
            <TriggerCondition mode="count" value={{ count: 10, days: 7 }} onChange={() => {}} />  // skipcq: JS-0057 - intentional no-op test stub
        );
        expect(await axe(container)).toHaveNoViolations();
    });

    it('has no accessibility violations (age mode)', async () => {
        const { container } = render(
            <TriggerCondition mode="age" value={{ count: 10, days: 7 }} onChange={() => {}} />  // skipcq: JS-0057 - intentional no-op test stub
        );
        expect(await axe(container)).toHaveNoViolations();
    });

    it('renders nothing for an unknown mode', () => {
        const { container } = render(
            <TriggerCondition mode="unknown" value={{ count: 5, days: 7 }} onChange={() => {}} />  // skipcq: JS-0057 - intentional no-op test stub
        );
        expect(container).toBeEmptyDOMElement();
    });

    describe('count mode', () => {
        it('renders a number input', () => {
            render(<TriggerCondition mode="count" value={{ count: 10, days: 7 }} onChange={() => {}} />);  // skipcq: JS-0057 - intentional no-op test stub
            expect(screen.getByRole('spinbutton')).toBeInTheDocument();
        });

        it('shows singular "link" label when count is 1', () => {
            render(<TriggerCondition mode="count" value={{ count: 1, days: 7 }} onChange={() => {}} />);  // skipcq: JS-0057 - intentional no-op test stub
            expect(screen.getByText('link')).toBeInTheDocument();
        });

        it('shows plural "links" label when count is > 1', () => {
            render(<TriggerCondition mode="count" value={{ count: 5, days: 7 }} onChange={() => {}} />);  // skipcq: JS-0057 - intentional no-op test stub
            expect(screen.getByText('links')).toBeInTheDocument();
        });

        it('calls onChange with updated count', () => {
            const onChange = vi.fn();
            render(<TriggerCondition mode="count" value={{ count: 10, days: 7 }} onChange={onChange} />);
            fireEvent.change(screen.getByRole('spinbutton'), { target: { value: '5' } });
            expect(onChange).toHaveBeenLastCalledWith({ count: 5, days: 7 });
        });
    });

    describe('age mode', () => {
        it('renders a number input', () => {
            render(<TriggerCondition mode="age" value={{ count: 10, days: 7 }} onChange={() => {}} />);  // skipcq: JS-0057 - intentional no-op test stub
            expect(screen.getByRole('spinbutton')).toBeInTheDocument();
        });

        it('shows singular "day" label when days is 1', () => {
            render(<TriggerCondition mode="age" value={{ count: 10, days: 1 }} onChange={() => {}} />);  // skipcq: JS-0057 - intentional no-op test stub
            expect(screen.getByText('day')).toBeInTheDocument();
        });

        it('shows plural "days" label when days is > 1', () => {
            render(<TriggerCondition mode="age" value={{ count: 10, days: 7 }} onChange={() => {}} />);  // skipcq: JS-0057 - intentional no-op test stub
            expect(screen.getByText('days')).toBeInTheDocument();
        });

        it('defaults days to 1 when value.days is undefined', () => {
            render(<TriggerCondition mode="age" value={{ count: 10 }} onChange={() => {}} />);  // skipcq: JS-0057 - intentional no-op test stub
            expect(screen.getByRole('spinbutton')).toHaveValue(1);
        });

        it('calls onChange with updated days', () => {
            const onChange = vi.fn();
            render(<TriggerCondition mode="age" value={{ count: 10, days: 7 }} onChange={onChange} />);
            fireEvent.change(screen.getByRole('spinbutton'), { target: { value: '3' } });
            expect(onChange).toHaveBeenLastCalledWith({ count: 10, days: 3 });
        });
    });
});
