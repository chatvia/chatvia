import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Whether this install holds a license key (shared as `license`). Without one
 * the free-use limits apply: pages lock what needs a key and send staff to
 * their administrator. The server refuses the locked actions too.
 */
export function useLicense() {
    const page = usePage();

    const licensed = computed(() => page.props.license?.licensed === true);
    const agentLimit = computed(() => page.props.license?.limits.agents ?? null);
    const messageLimit = computed(() => page.props.license?.limits.messages ?? null);

    return { licensed, agentLimit, messageLimit };
}
