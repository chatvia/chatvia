import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { configureEcho } from '@laravel/echo-vue';
import 'primeicons/primeicons.css';
import { createApp, h } from 'vue';
import { createGtag } from 'vue-gtag';
import { toast } from 'vue-sonner';
import 'vue-sonner/style.css';
import Vue3ColorPicker from 'vue3-colorpicker';
import 'vue3-colorpicker/style.css';
import { ZiggyVue } from 'ziggy-js';
import { initializeTheme } from './composables/useAppearance';
import { createChatEventBus } from './utils/eventBus';

const chatEventBus = createChatEventBus();

configureEcho({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});

const vueTag = createGtag({ tagId: 'G-RTCBL1ZT5C' });

// Extend ImportMeta interface for Vite...
interface ImportMetaEnv {
    readonly VITE_APP_NAME: string;
    [key: string]: string | boolean | undefined;
}

interface _ImportMeta {
    readonly env: ImportMetaEnv;
    readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(Vue3ColorPicker)
            .use(vueTag)
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
router.on('navigate', (event: any) => {
    const page = event.detail.page;
    const { props } = page;

    // Handle Laravel validation errors
    if (props.errors && Object.keys(props.errors).length > 0) {
        const errors = props.errors;

        // Show each error as a toast
        Object.entries(errors).forEach(([_field, messages]) => {
            const messageArray = Array.isArray(messages) ? messages : [messages];
            messageArray.forEach((message: string) => {
                toast.error(message, {
                    duration: 5000,
                });
            });
        });
    }

    // Handle Laravel flash messages
    if (props.flash) {
        const flash = props.flash as any;

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
