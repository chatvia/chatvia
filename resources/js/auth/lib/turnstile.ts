/**
 * Loads Cloudflare Turnstile's script once per page, however many widgets
 * render (see components/auth/TurnstileWidget.vue).
 */
export interface TurnstileApi {
    render(element: HTMLElement, options: Record<string, unknown>): string;
    reset(widgetId: string): void;
    remove(widgetId: string): void;
}

declare global {
    interface Window {
        turnstile?: TurnstileApi;
    }
}

const SCRIPT_URL = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

let loading: Promise<TurnstileApi> | null = null;

export function loadTurnstile(): Promise<TurnstileApi> {
    loading ??= new Promise<TurnstileApi>((resolve, reject) => {
        if (window.turnstile) {
            resolve(window.turnstile);

            return;
        }

        const script = document.createElement('script');
        script.src = SCRIPT_URL;
        script.async = true;
        script.onload = () => (window.turnstile ? resolve(window.turnstile) : reject(new Error('Turnstile did not start.')));
        script.onerror = () => {
            loading = null;
            reject(new Error('Turnstile could not be loaded.'));
        };
        document.head.appendChild(script);
    });

    return loading;
}
