import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import TriggerCondition from '../../src/schedule/components/TriggerCondition.jsx';

describe('TriggerCondition', () => {
    it('renders the count control with the singular label for 1 link', () => {
        render(<TriggerCondition mode="count" value={{ count: 1 }} onChange={() => {}} />);

        expect(screen.getByText('link')).toBeInTheDocument();
        expect(screen.getByDisplayValue('1')).toBeInTheDocument();
    });

    it('renders the count control with the plural label for multiple links', () => {
        render(<TriggerCondition mode="count" value={{ count: 5 }} onChange={() => {}} />);

        expect(screen.getByText('links')).toBeInTheDocument();
    });

    it('calls onChange with a parsed integer count', () => {
        const onChange = vi.fn();
        render(<TriggerCondition mode="count" value={{ count: 5 }} onChange={onChange} />);

        fireEvent.change(screen.getByDisplayValue('5'), { target: { value: '8' } });

        expect(onChange).toHaveBeenCalledWith({ count: 8 });
    });

    it('falls back to 1 when the count input is not a valid number', () => {
        const onChange = vi.fn();
        render(<TriggerCondition mode="count" value={{ count: 5 }} onChange={onChange} />);

        fireEvent.change(screen.getByDisplayValue('5'), { target: { value: 'abc' } });

        expect(onChange).toHaveBeenCalledWith({ count: 1 });
    });

    it('renders the age control with the singular label for 1 day', () => {
        render(<TriggerCondition mode="age" value={{ days: 1 }} onChange={() => {}} />);

        expect(screen.getByText('day')).toBeInTheDocument();
    });

    it('renders the age control with the plural label for multiple days', () => {
        render(<TriggerCondition mode="age" value={{ days: 7 }} onChange={() => {}} />);

        expect(screen.getByText('days')).toBeInTheDocument();
    });

    it('defaults days to 1 when value.days is absent', () => {
        render(<TriggerCondition mode="age" value={{}} onChange={() => {}} />);

        expect(screen.getByDisplayValue('1')).toBeInTheDocument();
        expect(screen.getByText('day')).toBeInTheDocument();
    });

    it('calls onChange with a parsed integer days value', () => {
        const onChange = vi.fn();
        render(<TriggerCondition mode="age" value={{ days: 7 }} onChange={onChange} />);

        fireEvent.change(screen.getByDisplayValue('7'), { target: { value: '14' } });

        expect(onChange).toHaveBeenCalledWith({ days: 14 });
    });

    it('renders nothing for an unrecognized mode', () => {
        const { container } = render(<TriggerCondition mode="manual" value={{}} onChange={() => {}} />);

        expect(container).toBeEmptyDOMElement();
    });
});
