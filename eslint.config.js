import prettier from 'eslint-config-prettier';
import vue from 'eslint-plugin-vue';

import { defineConfigWithVueTs, vueTsConfigs } from '@vue/eslint-config-typescript';

export default defineConfigWithVueTs(
    vue.configs['flat/essential'],
    vueTsConfigs.recommended,
    {
        ignores: [
            'vendor',
            'node_modules',
            'public',
            'bootstrap/ssr',
            'tailwind.config.js',
            'resources/js/components/ui/*',
            // VitePress' on-disk dep cache and built output — both contain
            // pre-bundled vendor JS (Vue runtime, vueuse, mark.js, etc.)
            // that lints with thousands of irrelevant errors. Source docs
            // under `docs/` are still linted; only the generated trees are
            // ignored.
            'docs/.vitepress/cache',
            'docs/.vitepress/dist',
            // The embeddable widget is a separate vanilla-JS package with its
            // own package.json and eslint.config.js; lint it from widget/.
            'widget',
        ],
    },
    {
        rules: {
            'vue/multi-word-component-names': 'off',
            // Type responses with the generated PHP types or small interfaces
            // (see resources/js/api/); `unknown` + narrowing where the shape
            // is really open. A warning, so it flags new `any` without
            // blocking a build.
            '@typescript-eslint/no-explicit-any': 'warn',
            // No debug logging in shipped code; console.warn / console.error
            // stay allowed for real problems.
            'no-console': ['warn', { allow: ['warn', 'error'] }],
            // Allow leading-underscore identifiers to opt out of unused-var
            // warnings — the convention for callback args you must accept
            // but don't reference, and for refs kept for template binding.
            '@typescript-eslint/no-unused-vars': [
                'error',
                {
                    argsIgnorePattern: '^_',
                    varsIgnorePattern: '^_',
                    caughtErrorsIgnorePattern: '^_',
                    destructuredArrayIgnorePattern: '^_',
                },
            ],
            // Every SFC script is TypeScript (`<script setup lang="ts">`).
            'vue/block-lang': [
                'error',
                {
                    script: {
                        lang: ['ts', 'tsx'],
                        allowNoLang: false,
                    },
                },
            ],
        },
    },
    {
        // Node CLI scripts and the Vite config report progress on the terminal.
        files: ['scripts/**', 'vite.config.ts'],
        rules: {
            'no-console': 'off',
        },
    },
    prettier,
);
