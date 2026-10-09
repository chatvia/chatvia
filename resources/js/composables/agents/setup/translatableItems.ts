/**
 * Helpers for list items the owner can translate by hand (starters, chat
 * bubbles): each item has a base `text`, AI `translations` and manual
 * `overrides` (locale → text). A manual edit wins over the AI translation;
 * clearing it falls back to the AI output again.
 */
export interface TranslatableItem {
    text: string;
    translations?: Record<string, string>;
    overrides?: Record<string, string>;
}

/** The manual edit of item `index` for `locale`, or '' when there is none. */
export const overrideOf = (items: TranslatableItem[], index: number, locale: string): string => items[index]?.overrides?.[locale] ?? '';

/** What a language shows when there is no manual edit: the AI translation, else the base text. */
export const fallbackOf = (items: TranslatableItem[], index: number, locale: string): string =>
    items[index]?.translations?.[locale] || items[index]?.text || '';

/** A copy of `items` with the manual edit of item `index` for `locale` set, or removed when blank. */
export function withOverride<T extends TranslatableItem>(items: T[], index: number, locale: string, value: string): T[] {
    const next = items.map((item) => ({ ...item, overrides: { ...(item.overrides ?? {}) } }));
    const overrides = next[index].overrides;
    if (value.trim() === '') delete overrides[locale];
    else overrides[locale] = value;
    return next;
}

/** Whether any item has a manual edit for `locale` (shown as a dot on its flag tab). */
export const hasAnyOverride = (items: TranslatableItem[], locale: string): boolean =>
    items.some((item) => (item.overrides?.[locale] ?? '').trim() !== '');
