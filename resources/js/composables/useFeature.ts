import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';

/**
 * Read Laravel Pennant feature flags shared from the server. Flags are
 * entitlement-derived (active for any workspace whose plan includes them), so
 * `hasFeature('security')` gates the Enterprise Security & Compliance suite in
 * the UI the same way the `features:` middleware gates routes server-side.
 */
export function useFeature() {
    const page = usePage<SharedData>();

    const hasFeature = (feature: string): boolean => {
        return page.props.features?.[feature] === true;
    };

    return { hasFeature };
}
