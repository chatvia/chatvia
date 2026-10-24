import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Whether this install has an Enterprise key (shared as `enterprise`). The
 * Security and Governance pages lock their Enterprise features without one;
 * the server refuses them too.
 */
export function useEnterprise() {
    const page = usePage();

    const enterprise = computed(() => page.props.enterprise === true);

    return { enterprise };
}
