import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, screen, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { axe } from 'vitest-axe';
import ScheduleTypePicker from '../../../src/schedule/components/ScheduleTypePicker.jsx';
import { noop } from '../test-utils.js';

/**
 * Stubs getBoundingClientRect so each mode card reports a distinguishable
 * natural height, keyed by a substring of its text content, to verify the
 * cross-group height-equalization logic without real browser layout.
 *
 * @param {Record<string, number>} heightsByText Map of text substring to height.
 * @param {number} defaultHeight Height for cards not matching any key.
 * @returns {void}
 */
function stubCardHeights(heightsByText, defaultHeight) {
    vi.spyOn(HTMLElement.prototype, 'getBoundingClientRect').mockImplementation(function () {
        const match = Object.entries(heightsByText).find(([text]) => this.textContent.includes(text));
        const height = match ? match[1] : defaultHeight;
        return { height, width: 96, top: 0, left: 0, right: 96, bottom: height, x: 0, y: 0, toJSON: () => ({}) };
    });
}

describe('ScheduleTypePicker', () => {
    it('renders all 6 mode buttons', () => {
        render(<ScheduleTypePicker value="daily" onChange={noop} />);
        expect(screen.getAllByRole('radio')).toHaveLength(6);
    });

    it('marks the active mode as aria-checked=true', () => {
        render(<ScheduleTypePicker value="weekly" onChange={noop} />);
        expect(screen.getByRole('radio', { name: /weekly/i })).toHaveAttribute('aria-checked', 'true');
    });

    it('marks all other modes as aria-checked=false', () => {
        render(<ScheduleTypePicker value="daily" onChange={noop} />);
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
        const { container } = render(<ScheduleTypePicker value="daily" onChange={noop} />);
        expect(await axe(container)).toHaveNoViolations();
    });

    it('renders the three group labels', () => {
        render(<ScheduleTypePicker value="daily" onChange={noop} />);
        expect(screen.getByText('Scheduled')).toBeInTheDocument();
        expect(screen.getByText('Trigger-based')).toBeInTheDocument();
        // "Manual" appears both as a group label and as a button title — verify at least one
        expect(screen.getAllByText('Manual').length).toBeGreaterThanOrEqual(1);
    });

    describe('cross-group height equalization', () => {
        afterEach(() => {
            vi.restoreAllMocks();
        });

        it('sizes every card — across all 3 groups — to the tallest one\'s natural height', () => {
            // "By Age" lives in the "Trigger-based" group; simulate it wrapping to
            // more lines (as it does in German) than any card in "Scheduled" or "Manual".
            stubCardHeights({ 'By Age': 120 }, 40);

            render(<ScheduleTypePicker value="daily" onChange={noop} />);

            for (const radio of screen.getAllByRole('radio')) {
                expect(radio.style.height).toBe('120px');
            }
        });

        it('re-equalizes on window resize when rewrapping changes which card is tallest', () => {
            stubCardHeights({ 'By Age': 120 }, 40);
            render(<ScheduleTypePicker value="daily" onChange={noop} />);
            expect(screen.getByRole('radio', { name: /monthly/i }).style.height).toBe('120px');

            // Narrower viewport: now "Monthly" wraps onto more lines than "By Age".
            stubCardHeights({ Monthly: 150 }, 40);
            act(() => {
                window.dispatchEvent(new Event('resize'));
            });

            for (const radio of screen.getAllByRole('radio')) {
                expect(radio.style.height).toBe('150px');
            }
        });
    });
});
