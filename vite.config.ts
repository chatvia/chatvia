import inertia from '@inertiajs/vite';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { exec } from 'node:child_process';
import fs from 'node:fs';
import { resolve } from 'node:path';
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
            TYPESCRIPT_SOURCE_DIRS.forEach((dir) => watchDir(path.resolve(__dirname, dir)));
        },
    };
}

export default defineConfig({
    plugins: [
        typescriptTransformPlugin(),
        laravel({
            input: ['resources/js/app.ts', 'resources/css/landing.css'],
            refresh: true,
        }),
        inertia(),
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
            '@': path.resolve(__dirname, './resources/js'),
            'ziggy-js': resolve(__dirname, 'vendor/tightenco/ziggy'),
        },
    },
    build: {
        chunkSizeWarningLimit: 1000,
        minify: 'terser',
        terserOptions: {
            compress: {
                drop_console: true,
                drop_debugger: true,
            },
        },
        cssMinify: 'lightningcss', // Better CSS minification
        cssCodeSplit: true, // Split CSS for better caching
    },
});
