import inertia from '@inertiajs/vite';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { exec, execSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'path';
import { defineConfig, type Plugin } from 'vite';
import { compression } from 'vite-plugin-compression2';

// Dev-server plugin: re-run `php artisan typescript:transform` when a PHP class
// that feeds resources/types/generated.d.ts changes. Watches the same folders
// the transformer scans (TypeScriptTransformerServiceProvider) and skips files
// without a #[TypeScript] attribute, so ordinary PHP edits do not trigger it.
const TYPESCRIPT_SOURCE_DIRS = ['src/App', 'src/Domain'];

function typescriptTransformPlugin(): Plugin {
    let timer: ReturnType<typeof setTimeout> | undefined;

    const runTransform = () => {
        exec('php artisan typescript:transform', (error, stdout, stderr) => {
            if (error) {
                console.error('\x1b[31m[typescript:transform] Error:\x1b[0m', stderr || error.message);
                return;
            }
            console.log('\x1b[32m[typescript:transform] Types regenerated\x1b[0m');
        });
    };

    // An editor save fires several watch events; run the transform once.
    const scheduleTransform = () => {
        clearTimeout(timer);
        timer = setTimeout(runTransform, 300);
    };

    // A deleted file can no longer be read, so it always triggers a run.
    const feedsTypes = (file: string): boolean => !fs.existsSync(file) || fs.readFileSync(file, 'utf8').includes('#[TypeScript');

    const watchDir = (dir: string) => {
        if (!fs.existsSync(dir)) return;

        fs.watch(dir, { recursive: true }, (eventType, filename) => {
            if (filename && filename.endsWith('.php') && feedsTypes(path.join(dir, filename))) {
                console.log(`\x1b[33m[typescript:transform] Change detected: ${filename}\x1b[0m`);
                scheduleTransform();
            }
        });
    };

    return {
        name: 'typescript-transform',
        configureServer() {
            console.log(`\x1b[36m[typescript:transform] Watching ${TYPESCRIPT_SOURCE_DIRS.join(', ')} for changes...\x1b[0m`);
            runTransform();
            TYPESCRIPT_SOURCE_DIRS.forEach((dir) => watchDir(path.resolve(import.meta.dirname, dir)));
        },
    };
}

// Wayfinder: the typed URL functions in resources/js/routes (gitignored) are
// generated from the Laravel routes. Generate them before every dev or build
// run, and again in dev when a route file or controller changes. The Docker
// image generates them in a PHP stage and sets WAYFINDER_GENERATE=false.
const WAYFINDER_COMMAND = 'php artisan wayfinder:generate --skip-actions';
const WAYFINDER_SOURCE_DIRS = ['routes', 'src/App/Http/Controllers'];

function wayfinderPlugin(): Plugin {
    let timer: ReturnType<typeof setTimeout> | undefined;

    const regenerate = () => {
        exec(WAYFINDER_COMMAND, (error, stdout, stderr) => {
            if (error) {
                console.error('\x1b[31m[wayfinder] Error:\x1b[0m', stderr || error.message);
                return;
            }
            console.log('\x1b[32m[wayfinder] Routes regenerated\x1b[0m');
        });
    };

    return {
        name: 'wayfinder',
        buildStart() {
            if (process.env.WAYFINDER_GENERATE !== 'false') {
                execSync(WAYFINDER_COMMAND, { stdio: 'inherit' });
            }
        },
        configureServer() {
            WAYFINDER_SOURCE_DIRS.forEach((dir) => {
                const absolute = path.resolve(import.meta.dirname, dir);
                if (!fs.existsSync(absolute)) return;

                fs.watch(absolute, { recursive: true }, (eventType, filename) => {
                    if (filename && filename.endsWith('.php')) {
                        clearTimeout(timer);
                        timer = setTimeout(regenerate, 300);
                    }
                });
            });
        },
    };
}

export default defineConfig({
    plugins: [
        typescriptTransformPlugin(),
        wayfinderPlugin(),
        laravel({
            // The admin panel's Filament theme is its own entry: the panel
            // loads it instead of Filament's prebuilt stylesheet.
            input: ['resources/js/app.ts', 'resources/css/admin/theme.css'],
            refresh: true,
        }),
        // SSR is off (INERTIA_SSR_ENABLED=false) and app.ts touches window/document
        // at load, so stop the plugin from using it as a dev SSR entry.
        inertia({ ssr: false }),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        compression({
            algorithms: ['gzip', 'brotliCompress'],
            threshold: 10240, // Only compress files larger than 10kb
        }),
    ],
    resolve: {
        alias: {
            '@': path.resolve(import.meta.dirname, './resources/js'),
        },
    },
    build: {
        chunkSizeWarningLimit: 1000,
        // Rolldown's native minifier: much faster than terser, about the same output size.
        minify: 'oxc',
        rolldownOptions: {
            // The plugin-timings notice fires on every build: the Vue, Tailwind
            // and Wayfinder plugins run in JavaScript, so it is never actionable.
            checks: {
                bundlerTimings: false,
            },
            output: {
                minify: {
                    compress: {
                        dropConsole: true,
                        dropDebugger: true,
                    },
                },
            },
        },
        cssMinify: 'lightningcss', // Better CSS minification
        cssCodeSplit: true, // Split CSS for better caching
    },
});
