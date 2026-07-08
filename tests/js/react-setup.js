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
    fillText: () => {},
    clearRect: () => {},
    getImageData: () => ({ data: new Uint8ClampedArray(4) }),
});

afterEach(() => {
    cleanup();
});
