import { useMediaQuery } from '@vueuse/core';
import type { Ref } from 'vue';

/**
 * The single mobile breakpoint for the chat surface. Mobile is anything
 * narrower than this (matches the `innerWidth >= 768` desktop checks the
 * chat layout used to scatter inline).
 */
export const MOBILE_BREAKPOINT = 768;

/**
 * Reactive viewport-based mobile detection.
 *
 * Replaces the one-shot `is-mobile` UA sniff that was evaluated once at
 * setup and never updated — so the chat layout stayed frozen in whatever
 * mode it booted in when a desktop window was narrowed, a tablet rotated,
 * or the app ran in split-screen. This re-flows live, and keys off the
 * viewport (what actually drives layout) rather than the user agent.
 *
 * Returns a `Ref<boolean>` — read `.value` in script, auto-unwrapped in
 * templates.
 */
export function useIsMobile(): Ref<boolean> {
    return useMediaQuery(`(max-width: ${MOBILE_BREAKPOINT - 1}px)`);
}
