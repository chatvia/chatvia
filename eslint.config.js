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
            '@typescript-eslint/no-explicit-any': 'off',
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
            // Permit plain `<script setup>` (no lang attribute) in addition
            // to TypeScript. A handful of legacy chat-UI components are still
            // plain JS — keep them out of strict TS until they're rewritten.
            'vue/block-lang': [
                'error',
                {
                    script: {
                        lang: ['ts', 'tsx'],
                        allowNoLang: true,
                    },
                },
            ],
        },
    },
    prettier,
);
