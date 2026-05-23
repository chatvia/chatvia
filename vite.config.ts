import inertia from '@inertiajs/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import path from 'path';
import tailwindcss from "@tailwindcss/vite";
import { resolve } from 'node:path';
import { defineConfig, type Plugin } from 'vite';
import { compression } from 'vite-plugin-compression2';
import { exec } from 'node:child_process';
import fs from 'node:fs';

// Plugin to watch app/Data and run typescript:transform on changes
function typescriptTransformPlugin(): Plugin {
    const runTransform = () => {
        exec('php artisan typescript:transform', (error, stdout, stderr) => {
            if (error) {
                console.error('\x1b[31m[typescript:transform] Error:\x1b[0m', stderr || error.message);
                return;
            }
            console.log('\x1b[32m[typescript:transform] Types regenerated\x1b[0m');
        });
    };

    const watchDir = (dir: string) => {
        if (!fs.existsSync(dir)) return;

        fs.watch(dir, { recursive: true }, (eventType, filename) => {
            if (filename && filename.endsWith('.php')) {
                console.log(`\x1b[33m[typescript:transform] Change detected: ${filename}\x1b[0m`);
                runTransform();
            }
        });
    };

    return {
        name: 'typescript-transform',
        configureServer() {
            console.log('\x1b[36m[typescript:transform] Watching app/Data for changes...\x1b[0m');
            runTransform();
            watchDir(path.resolve(__dirname, 'app/Data'));
        },
    };
}

export default defineConfig({
    plugins: [
        typescriptTransformPlugin(),
        laravel({
            input: ['resources/js/app.ts', 'resources/css/landing.css'],
            ssr: 'resources/js/ssr.ts',
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
