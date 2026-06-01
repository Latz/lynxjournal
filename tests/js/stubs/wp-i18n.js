export const __ = (str) => str;
export const sprintf = (fmt, ...args) => args.reduce((s, a) => s.replace(/%\d+\$s|%s/, String(a)), fmt);
