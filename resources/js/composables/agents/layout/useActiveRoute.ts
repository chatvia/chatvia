/**
 * `isActiveRoute(name)` for the agent sidebar: whether the current URL matches
 * a Ziggy route name. Trailing-`*` patterns (e.g. 'agents.sources.*') match any
 * nested route. Reads `page.url`, so templates and computeds re-evaluate after
 * each Inertia visit.
 */
import { usePage } from '@inertiajs/vue3';

export function useActiveRoute() {
    const page = usePage();

    function isActiveRoute(routeName: string): boolean {
        // Ziggy reads `window.location`, which Vue cannot track; touching
        // `page.url` makes the caller depend on the current visit.
        void page.url;
        return Boolean(route().current(routeName));
    }

    return { isActiveRoute };
}
