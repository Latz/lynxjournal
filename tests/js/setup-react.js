import '@testing-library/jest-dom/vitest';
import { toHaveNoViolations } from 'vitest-axe/matchers';
import { configureAxe } from 'vitest-axe';
expect.extend({ toHaveNoViolations });

// jsdom has no canvas, so axe's color-contrast rule always errors — disable it globally.
configureAxe({ rules: [{ id: 'color-contrast', enabled: false }] });
