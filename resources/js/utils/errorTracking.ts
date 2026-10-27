import type { App } from 'vue';

/**
 * Sends the browser's errors to Sentry when Settings → Platform has a Browser
 * DSN, which the page carries in a meta tag. Without one nothing is loaded:
 * the SDK is its own chunk, fetched only by installs that use it.
 */
export function startErrorTracking(app: App): void {
    const dsn = document.querySelector<HTMLMetaElement>('meta[name="sentry-dsn"]')?.content;

    if (!dsn) {
        return;
    }

    const environment = document.querySelector<HTMLMetaElement>('meta[name="sentry-environment"]')?.content || undefined;

    void import('@sentry/vue').then((Sentry) => {
        Sentry.init({
            app,
            dsn,
            environment,
            // Chatters' messages and people's details stay out of error reports.
            dataCollection: { userInfo: false, cookies: false, httpHeaders: false, httpBodies: [], urlQueryParams: false },
        });
    });
}
