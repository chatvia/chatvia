import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed, type ComputedRef } from 'vue';

/**
 * Whether paid plans are switched on for this install (`config('billing.enabled')`).
 * A self-hosted install can run without billing: then every feature is on, there
 * are no limits, and plans, upgrade prompts and billing links must be hidden.
 * A page rendered without the shared prop counts as billing on, which is how the
 * hosted product runs.
 */
export function useBilling(): { billingEnabled: ComputedRef<boolean> } {
    const page = usePage<SharedData>();

    const billingEnabled = computed(() => page.props.billing?.enabled !== false);

    return { billingEnabled };
}
