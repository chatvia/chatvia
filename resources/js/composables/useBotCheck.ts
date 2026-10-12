import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed, type ComputedRef } from 'vue';

/** The public forms the bot check can protect (App\Http\Auth\BotCheckForm). */
export type BotCheckForm = 'sign_up' | 'sign_in' | 'password_reset';

/** The form field the Turnstile widget fills (App\Http\Auth\BotCheck::FIELD). */
export const BOT_CHECK_FIELD = 'cf-turnstile-response';

/**
 * Whether a public form shows the Cloudflare Turnstile bot check (Settings →
 * Security), and the site key it renders with: null when it does not.
 */
export function useBotCheck(form: BotCheckForm): { siteKey: ComputedRef<string | null> } {
    const page = usePage<SharedData>();

    return {
        siteKey: computed(() => {
            const turnstile = page.props.turnstile;

            return turnstile && turnstile.forms.includes(form) ? turnstile.site_key : null;
        }),
    };
}
