/**
 * Shared no-op stub for test doubles (callback props, mock methods) that
 * intentionally do nothing — a single reusable reference instead of a
 * `() => {}` literal (and its own suppression comment) at every call site.
 */
export const noop = () => {};  // skipcq: JS-0057 - intentional no-op test stub, reused throughout tests/js/**
