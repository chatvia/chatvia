/**
 * The route the current page was served from, as shared by the server
 * (`route` in HandleInertiaRequests). Build URLs with the Wayfinder functions
 * in `@/routes`; this is only for "am I on this page?" checks.
 *
 * `current(pattern, params?)`: `pattern` is a route name where `*` matches any
 * run of characters ('agents.sources.*' matches 'agents.sources.files' but not
 * 'agents.sources' itself), and every given param must equal the current one.
 * It reads the Inertia page props, so templates and computeds re-evaluate
 * after each visit.
 */
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function routeNameMatches(name: string | null, pattern: string): boolean {
    if (name === null) {
        return false;
    }

    const regex = new RegExp('^' + pattern.split('*').map(escapeRegExp).join('.*') + '$');

    return regex.test(name);
}

function escapeRegExp(value: string): string {
    return value.replace(/[.+?^${}()|[\]\\]/g, '\\$&');
}

export function useCurrentRoute() {
    const page = usePage<SharedData>();

    const name = computed<string | null>(() => page.props.route?.name ?? null);
    const params = computed<Record<string, string>>(() => page.props.route?.params ?? {});

    function current(pattern: string, routeParams: Record<string, string | number> = {}): boolean {
        if (!routeNameMatches(name.value, pattern)) {
            return false;
        }

        return Object.entries(routeParams).every(([key, value]) => params.value[key] === String(value));
    }

    return { name, params, current };
}
