import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterEach, expect } from 'vitest';
import { toHaveNoViolations } from 'vitest-axe/matchers';

expect.extend({ toHaveNoViolations });

// jsdom doesn't implement HTMLCanvasElement.getContext; stub it so axe's
// color-contrast rule can run without crashing on icon-ligature detection.
HTMLCanvasElement.prototype.getContext = () => ({
    font: '',
    measureText: () => ({ width: 0 }),
    fillText: () => {},  // skipcq: JS-0057 - intentional no-op test stub
    clearRect: () => {},  // skipcq: JS-0057 - intentional no-op test stub
    getImageData: () => ({ data: new Uint8ClampedArray(4) }),
});

// jsdom doesn't implement the two-argument (pseudo-element) form of
// getComputedStyle, which axe's color-contrast rule calls on every run —
// it logs a "Not implemented" error to stderr for every element checked.
// Drop the pseudo-element argument so jsdom takes its supported path.
const { getComputedStyle } = window;
window.getComputedStyle = (elt, pseudoElt) => getComputedStyle(elt, pseudoElt ? undefined : pseudoElt);

afterEach(() => {
    cleanup();
});
