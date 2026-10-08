import { useMediaQuery } from '@vueuse/core';
import type { Ref } from 'vue';

/**
 * The single mobile breakpoint for the chat surface. Mobile is anything
 * narrower than this.
 */
export const MOBILE_BREAKPOINT = 768;

/**
 * Reactive viewport-based mobile detection.
 *
 * Keyed off the viewport (what actually drives layout), not the user agent,
 * and reactive: the layout re-flows when a desktop window is narrowed, a
 * tablet rotates, or the app runs in split-screen.
 *
 * Returns a `Ref<boolean>` — read `.value` in script, auto-unwrapped in
 * templates.
 */
export function useIsMobile(): Ref<boolean> {
    return useMediaQuery(`(max-width: ${MOBILE_BREAKPOINT - 1}px)`);
}
