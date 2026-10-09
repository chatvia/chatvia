/**
 * Typed reads from an agent's loosely typed settings groups (`appearance`,
 * `widget`, `bar`, …) as the Setup page receives them. A saved value of the
 * wrong type falls back to the group's default, then to a hard fallback.
 */

/** One settings group as sent by the server: key → stored value. */
export type SettingsGroup = Record<string, unknown>;

export const asString = (value: unknown, fallback = ''): string => (typeof value === 'string' ? value : fallback);
export const asNumber = (value: unknown, fallback = 0): number => (typeof value === 'number' ? value : fallback);
export const asBoolean = (value: unknown, fallback = false): boolean => (typeof value === 'boolean' ? value : fallback);
export const asList = <T>(value: unknown, fallback: T[] = []): T[] => (Array.isArray(value) ? (value as T[]) : fallback);

export interface SettingsReader {
    /** The saved group itself, for values that must not fall back to a default. */
    saved: SettingsGroup;
    string(key: string, fallback?: string): string;
    number(key: string, fallback?: number): number;
    boolean(key: string, fallback?: boolean): boolean;
    list<T>(key: string): T[];
}

/** Reads `key` from the saved group, else from the defaults, else the fallback. */
export function settingsReader(saved: SettingsGroup, defaults: SettingsGroup): SettingsReader {
    return {
        saved,
        string: (key, fallback = '') => asString(saved[key], asString(defaults[key], fallback)),
        number: (key, fallback = 0) => asNumber(saved[key], asNumber(defaults[key], fallback)),
        boolean: (key, fallback = false) => asBoolean(saved[key], asBoolean(defaults[key], fallback)),
        list: <T>(key: string) => asList<T>(saved[key], asList<T>(defaults[key])),
    };
}
