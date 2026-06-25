export const __ = (str) => str;

export function sprintf(fmt, ...args) {
    let i = 0;
    return fmt.replace(/%[sd]/g, () => String(args[i++] ?? ''));
}
