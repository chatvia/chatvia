import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { renderToString } from '@vue/server-renderer';
import { createSSRApp, h, type DefineComponent } from 'vue';
import { route as ziggyRoute } from 'ziggy-js';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

interface SsrZiggyConfig {
    location: string;
    [key: string]: unknown;
}

createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
        title: (title) => `${title} - ${appName}`,
        // Page components live alongside the rest of the SPA and are
        // discovered via Vite's glob import. Same convention as `app.ts`.
        resolve: (name) => {
            const pages = import.meta.glob<{ default: DefineComponent }>('./pages/**/*.vue');
            const loader = pages[`./pages/${name}.vue`];

            if (!loader) {
                throw new Error(`Inertia page not found: ./pages/${name}.vue`);
            }

            return loader().then((module) => module.default);
        },
        setup({ App, props, plugin }) {
            const app = createSSRApp({ render: () => h(App, props) });

            // Configure Ziggy for SSR.
            const ziggyProp = page.props.ziggy as SsrZiggyConfig;
            const ziggyConfig = {
                ...ziggyProp,
                location: new URL(ziggyProp.location),
            };

            // Ziggy's overloads expect compile-time known route names; in
            // SSR we route through `never` and trust the runtime arguments.
            const route = (name: string, params?: Record<string, unknown>, absolute?: boolean) =>
                ziggyRoute(name as never, params as never, absolute, ziggyConfig as never);

            // Expose `route()` on every component instance.
            (app.config.globalProperties as any).route = route;

            if (typeof window === 'undefined') {
                (globalThis as any).route = route;
            }

            app.use(plugin);

            return app;
        },
    }),
);
