import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Whether this install holds a license key (shared as `license`). Without one
 * pages lock what needs a key and send staff to their administrator. The
 * server refuses the locked actions too.
 */
export function useLicense() {
    const page = usePage();

    const licensed = computed(() => page.props.license?.licensed === true);

    return { licensed };
}
