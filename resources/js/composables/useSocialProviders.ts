import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';

export type SocialProviderName = 'google' | 'slack' | 'microsoft' | 'github';

/**
 * The sign-in providers whose OAuth app the admin has set up (Settings →
 * Sign-in). A button for any other provider would end on an error page.
 */
export function useSocialProviders() {
    const page = usePage<SharedData>();

    const has = (provider: SocialProviderName): boolean => (page.props.socialProviders ?? []).includes(provider);

    const hasAny = (providers: SocialProviderName[]): boolean => providers.some(has);

    return { has, hasAny };
}
