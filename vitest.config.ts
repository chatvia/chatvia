import path from 'node:path';
import { defineConfig } from 'vitest/config';

// Unit tests of the frontend's plain TypeScript: files named *.test.ts next to
// the code they test. They run in Node without the app's Vite plugins
// (Laravel, Wayfinder, Tailwind), which a test of a helper does not need.
export default defineConfig({
    resolve: {
        alias: {
            '@': path.resolve(import.meta.dirname, './resources/js'),
        },
    },
    test: {
        include: ['resources/js/**/*.test.ts'],
        environment: 'node',
    },
});
