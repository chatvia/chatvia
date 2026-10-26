import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Whether this install holds a license key (shared as `license`). Without one
 * the free-use limits apply: pages lock what needs a key and link to the buy
 * page. The server refuses the locked actions too.
 */
export function useLicense() {
    const page = usePage();

    const licensed = computed(() => page.props.license?.licensed === true);
    const buyUrl = computed(() => page.props.license?.buyUrl ?? '');
    const agentLimit = computed(() => page.props.license?.limits.agents ?? null);
    const messageLimit = computed(() => page.props.license?.limits.messages ?? null);

    return { licensed, buyUrl, agentLimit, messageLimit };
}
