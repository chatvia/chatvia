/**
 * Makes an ECharts option safe to render in the page.
 *
 * Chart options reach the chat as tool artifacts, and their text can come
 * from the model (series names, units, titles). ECharts is not pure canvas
 * everywhere: an HTML tooltip writes its formatter output with innerHTML,
 * `title.link` / `sublink` open any URL (also `javascript:`), rich-text and
 * `image://` symbols load images from any address, and the toolbox data view
 * builds markup from the data. So before `setOption`:
 *
 * - tooltips always render as rich text on the canvas (`renderMode: 'richText'`);
 * - `link`, `sublink`, `rich`, `graphic` and `toolbox` are removed anywhere;
 * - a `formatter` stays only when it is a plain template such as
 *   `'{b}: {c}'`: anything that could hold markup is removed;
 * - any value that names an `image://` or `javascript:` address is removed.
 *
 * The input is not changed; a cleaned copy is returned.
 */

/** Keys removed wherever they appear in the option. */
export const DROPPED_KEYS = ['link', 'sublink', 'rich', 'graphic', 'toolbox'] as const;

const UNSAFE_URL = /^\s*(image|javascript):/i;
const MARKUP = /[<>&]/;

type Json = null | boolean | number | string | Json[] | { [key: string]: Json };

function isPlainObject(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function clean(value: unknown): Json | undefined {
    if (Array.isArray(value)) {
        return value.map((item) => clean(item)).filter((item): item is Json => item !== undefined);
    }

    if (isPlainObject(value)) {
        const out: Record<string, Json> = {};

        for (const [key, inner] of Object.entries(value)) {
            if ((DROPPED_KEYS as readonly string[]).includes(key)) {
                continue;
            }

            if (key === 'formatter' && (typeof inner !== 'string' || MARKUP.test(inner))) {
                continue;
            }

            const cleaned = clean(inner);

            if (cleaned !== undefined) {
                out[key] = cleaned;
            }
        }

        return out;
    }

    if (typeof value === 'string') {
        return UNSAFE_URL.test(value) ? undefined : value;
    }

    if (typeof value === 'number' || typeof value === 'boolean' || value === null) {
        return value;
    }

    // Functions and other non-JSON values never belong in a chart option.
    return undefined;
}

function richTextTooltip(tooltip: Json | undefined): Json | undefined {
    if (Array.isArray(tooltip)) {
        return tooltip.map((item) => (isPlainObject(item) ? { ...item, renderMode: 'richText' } : item));
    }

    return isPlainObject(tooltip) ? { ...tooltip, renderMode: 'richText' } : tooltip;
}

export function safeEchartsOption(option: Record<string, unknown>): Record<string, unknown> {
    const cleaned = clean(option);

    if (!isPlainObject(cleaned)) {
        return {};
    }

    if ('tooltip' in cleaned) {
        const tooltip = richTextTooltip(cleaned.tooltip as Json);

        if (tooltip !== undefined) {
            cleaned.tooltip = tooltip;
        }
    }

    return cleaned;
}
