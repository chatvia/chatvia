import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * The app's name (Settings → Platform, shared as `name`), for the places that
 * name the app: tab titles, the tour's welcome and the hosted chat's fallback
 * title. The "Powered by ChatVia.ai" credit keeps its own name.
 */
export function useAppName() {
    const page = usePage();

    return computed(() => page.props.name || 'ChatVia.ai');
}
