/** Heroicons (outline) lookup by export name, used for conversation-starter icons. */
import * as outlineIcons from '@heroicons/vue/24/outline';
import type { FunctionalComponent } from 'vue';

// Every Heroicons (outline) component, keyed by its export name
// (e.g. `ChatBubbleLeftRightIcon`). We persist the export name as a starter's
// icon value, so rendering is a direct, transform-free lookup in this map.
//
// NOTE: `import *` pulls the full outline set into any bundle that imports this
// module (including the hosted-chat bundle, which renders saved icons). That
// keeps every icon available; switch to a curated subset if bundle size
// becomes a concern.
const icons = outlineIcons as unknown as Record<string, FunctionalComponent>;

/** Sorted list of every outline icon export name — drives the picker grid. */
export const heroiconNames: string[] = Object.keys(icons)
    .filter((name) => name.endsWith('Icon'))
    .sort();

/** Resolve an icon export name to its component, or null when unknown/empty. */
export function getHeroicon(name?: string | null): FunctionalComponent | null {
    if (!name) return null;
    return icons[name] ?? null;
}

/** Humanize an export name for search and tooltips:
 *  `ChatBubbleLeftRightIcon` → `chat bubble left right`. */
export function humanizeIconName(name: string): string {
    return name
        .replace(/Icon$/, '')
        .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
        .toLowerCase();
}
