/**
 * Lightweight stand-in for @wordpress/i18n in the Vitest environment.
 * @wordpress/i18n is externalized in production (provided by WP core as
 * wp.i18n) and isn't installed as a dependency, so it can't be resolved
 * directly by Vite — this mock is aliased in vitest.config.js instead.
 */

export function __(text) {
    return text;
}

export function _n(single, plural, number) {
    return number === 1 ? single : plural;
}

export function sprintf(format, ...args) {
    let i = 0;
    return format.replace(/%[sd]/g, () => args[i++]);
}
