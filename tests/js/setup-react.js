import '@testing-library/jest-dom/vitest';
import { toHaveNoViolations } from 'vitest-axe/matchers';
expect.extend({ toHaveNoViolations });

// jsdom doesn't implement HTMLCanvasElement.getContext; stub it so axe's
// color-contrast rule can run without crashing on icon-ligature detection.
HTMLCanvasElement.prototype.getContext = () => ({
    font: '',
    measureText: () => ({ width: 0 }),
    fillText: () => {},
    clearRect: () => {},
    getImageData: () => ({ data: new Uint8ClampedArray(4) }),
});

// jsdom doesn't implement the two-argument (pseudoElt) form of
// getComputedStyle and logs "Not implemented" to stderr instead of throwing;
// axe's color-contrast check hits this while probing ::before/::after for
// icon ligatures. Drop the pseudoElt arg so jsdom's real implementation runs.
const nativeGetComputedStyle = window.getComputedStyle.bind(window);
window.getComputedStyle = (elt) => nativeGetComputedStyle(elt);
