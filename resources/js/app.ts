import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import { initializeTheme } from './composables/useAppearance';
import { createChatEventBus } from './utils/eventBus'
import Vue3ColorPicker from "vue3-colorpicker";
import "vue3-colorpicker/style.css";
import { toast } from 'vue-sonner';
import { router } from '@inertiajs/vue3';

const chatEventBus = createChatEventBus()
import { createGtag } from "vue-gtag";
import { configureEcho } from '@laravel/echo-vue';

configureEcho({
    broadcaster: 'reverb',
});

const vueTag = createGtag({ tagId: "G-RTCBL1ZT5C"})

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(Vue3ColorPicker)
            .use(vueTag)
            .provide('chatEventBus', chatEventBus)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
        showSpinner: true,
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
        Object.entries(errors).forEach(([field, messages]) => {
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
