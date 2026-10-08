import type { Brand, SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed, type ComputedRef } from 'vue';

// An unset env var can reach the page as null or as an empty string. Both mean
// "no link", so callers only ever have to check for null.
const urlOrNull = (value: string | null | undefined): string | null => (value && value.trim() !== '' ? value : null);

/**
 * Product name and links shared from `config('chatvia.brand')`, so a
 * self-hosted install shows its own name and hides links it has not set.
 * Every URL is either a non-empty string or null — render the link only when
 * it is not null.
 */
export function useBrand(): { brand: ComputedRef<Brand> } {
    const page = usePage<SharedData>();

    const brand = computed<Brand>(() => {
        const shared = page.props.brand;
        const name = shared?.name || page.props.name || 'Chatvia';

        return {
            name,
            website_url: urlOrNull(shared?.website_url),
            docs_url: urlOrNull(shared?.docs_url),
            api_docs_url: urlOrNull(shared?.api_docs_url),
            powered_by: {
                enabled: shared?.powered_by?.enabled === true,
                label: shared?.powered_by?.label || name,
                url: urlOrNull(shared?.powered_by?.url),
            },
            onboarding_video_url: urlOrNull(shared?.onboarding_video_url),
            marketing_site: shared?.marketing_site !== false,
        };
    });

    return { brand };
}
