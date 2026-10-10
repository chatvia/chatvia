/**
 * `isActiveRoute(name)` for the agent sidebar: whether the current page was
 * served from a route name. Trailing-`*` patterns (e.g. 'agents.sources.*')
 * match any nested route. Reads the Inertia page props, so templates and
 * computeds re-evaluate after each visit.
 */
import { useCurrentRoute } from '@/composables/useCurrentRoute';

export function useActiveRoute() {
    const { current } = useCurrentRoute();

    function isActiveRoute(routeName: string): boolean {
        return current(routeName);
    }

    return { isActiveRoute };
}
