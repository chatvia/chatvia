import '../css/app.css';

import '@formkit/themes/genesis';
import { defaultConfig as formKitConfig, plugin as formKitPlugin } from '@formkit/vue';
import { createInertiaApp, http, router } from '@inertiajs/vue3';
import { configureEcho } from '@laravel/echo-vue';
import axios from 'axios';
import 'primeicons/primeicons.css';
import { ConfigProvider } from 'reka-ui';
import { computed, createApp, h } from 'vue';
import { toast } from 'vue-sonner';
import 'vue-sonner/style.css';
import Vue3ColorPicker from 'vue3-colorpicker';
import 'vue3-colorpicker/style.css';
import { initializeTheme } from './composables/useAppearance';
import { i18n, isRTL, normalizeLocale, resolveLocale, setI18nLocale } from './i18n';
import type { SharedData } from './types';
import { csrfHeaders, setCsrfToken } from './utils/csrf';
import { createChatEventBus } from './utils/eventBus';
import { setUrlDefaults } from './wayfinder';

const chatEventBus = createChatEventBus();

// Reverb settings baked in at build time win. A build without them (the Docker
// image) reads the public app key from the page and connects to the page's own
// host, where the web server forwards /app to Reverb.
const reverbScheme = import.meta.env.VITE_REVERB_SCHEME || window.location.protocol.replace(':', '');
const reverbPort = Number(import.meta.env.VITE_REVERB_PORT || window.location.port || (reverbScheme === 'https' ? 443 : 80));

configureEcho({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY || document.querySelector<HTMLMetaElement>('meta[name="reverb-key"]')?.content,
    wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
    wsPort: reverbPort,
    wssPort: reverbPort,
    forceTLS: reverbScheme === 'https',
    enabledTransports: ['ws', 'wss'],
});

// Hosted chat pages live under /chat/{agentSlug}. The server sends the page's
// segment as `agentSlug`, and every chat route helper fills it in from here, so
// call sites never pass the agent.
let agentSlug: string | null = null;
setUrlDefaults(() => (agentSlug ? { agentSlug } : {}));

// Every request (axios and Inertia's own) sends the page's CSRF token as a
// header; see utils/csrf.ts for why the cookie is not enough.
axios.interceptors.request.use((config) => {
    Object.entries(csrfHeaders()).forEach(([name, value]) => config.headers.set(name, value));
    return config;
});
http.onRequest((config) => ({ ...config, headers: { ...config.headers, ...csrfHeaders() } }));

/** Takes the agent segment and the CSRF token from each page the server sends. */
function syncPageContext(pageProps: Record<string, unknown>): void {
    agentSlug = typeof pageProps.agentSlug === 'string' && pageProps.agentSlug !== '' ? pageProps.agentSlug : null;
    setCsrfToken(pageProps.csrfToken);
}

// The Docker image is built without VITE_APP_NAME; the server-rendered <title>
// holds the brand name then.
const appName = import.meta.env.VITE_APP_NAME || document.title || 'Chatvia';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    setup({ el, App, props, plugin }) {
        syncPageContext(props.initialPage.props);

        const initialLocale: unknown = props.initialPage?.props?.locale;
        if (typeof initialLocale === 'string' && initialLocale.length > 0) {
            void setI18nLocale(resolveLocale(initialLocale));
        }

        // reka-ui primitives (NavigationMenu, DropdownMenu, Tabs, Sheet, …) read
        // their layout direction from a ConfigProvider, defaulting to LTR. Bind
        // it to the active locale so RTL languages mirror the whole UI (nav order,
        // dropdown alignment, sheet side) — `dir` is reactive via the i18n locale.
        const dir = computed<'ltr' | 'rtl'>(() => (isRTL(normalizeLocale(i18n.global.locale.value)) ? 'rtl' : 'ltr'));

        createApp({ render: () => h(ConfigProvider, { dir: dir.value }, () => h(App, props)) })
            .use(plugin)
            .use(i18n)
            .use(Vue3ColorPicker)
            .use(formKitPlugin, formKitConfig())
            .provide('chatEventBus', chatEventBus)
            .mount(el as Element);
    },
    progress: {
        color: '#4B5563',
        showSpinner: false,
        delay: 0,
    },
});

// Global error handler for Laravel validation errors and flash messages
router.on('navigate', (event) => {
    const page = event.detail.page;
    const { props } = page;

    syncPageContext(props);

    // Sync the active i18n locale (and document dir/lang) with whatever
    // the server resolved for this Inertia page (chat surfaces send `locale`).
    // `auto` falls back to the browser language; anything else is forced.
    const incomingLocale: unknown = props.locale;
    if (typeof incomingLocale === 'string' && incomingLocale.length > 0) {
        void setI18nLocale(resolveLocale(incomingLocale));
    }

    // Handle Laravel validation errors
    if (props.errors && Object.keys(props.errors).length > 0) {
        const errors = props.errors;

        // Show each error as a toast
        Object.entries(errors).forEach(([_field, messages]) => {
            const messageArray: string[] = Array.isArray(messages) ? messages : [messages];
            messageArray.forEach((message) => {
                toast.error(message, {
                    duration: 5000,
                });
            });
        });
    }

    // Handle Laravel flash messages
    if (props.flash) {
        const flash: SharedData['flash'] = props.flash;

        if (flash.success) {
            toast.success(flash.success, {
                duration: 4000,
            });
        }

        if (flash.error) {
            toast.error(flash.error, {
                duration: 5000,
            });
        }

        if (flash.warning) {
            toast.warning(flash.warning, {
                duration: 4500,
            });
        }

        if (flash.info) {
            toast.info(flash.info, {
                duration: 4000,
            });
        }
    }
});

// This will set light / dark mode on page load...
initializeTheme();
