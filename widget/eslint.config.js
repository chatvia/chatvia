import js from '@eslint/js';
import globals from 'globals';
import eslintPluginPrettierRecommended from 'eslint-plugin-prettier/recommended';

// Flat config (ESLint 9+/10). Replaces the legacy .eslintrc.json:
// - "eslint:recommended"        -> js.configs.recommended
// - "plugin:prettier/recommended" -> eslintPluginPrettierRecommended (must be LAST
//   so eslint-config-prettier disables formatting rules that would fight Prettier)
// - env: { browser, node }      -> languageOptions.globals (from the `globals` package)
// `semi` and `quotes` are intentionally omitted — Prettier owns formatting.
export default [
  // Global ignores (an object with only `ignores` applies repo-wide).
  {
    ignores: ['dist/**', 'node_modules/**']
  },

  js.configs.recommended,

  // Source: browser + node globals, ESM, latest syntax.
  {
    files: ['src/**/*.js'],
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      globals: {
        ...globals.browser,
        ...globals.node
      }
    },
    rules: {
      'no-console': ['warn', { allow: ['log', 'warn', 'error'] }],
      'prefer-const': 'error',
      eqeqeq: ['error', 'always'],
      'no-var': 'error',
      'no-unused-vars': ['warn', { argsIgnorePattern: '^_' }]
    }
  },

  // Tests import describe/it/expect explicitly from 'vitest', so no test globals
  // are needed; console is unrestricted in tests.
  {
    files: ['**/*.test.js', '**/*.spec.js', '**/__tests__/**/*.js'],
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      globals: {
        ...globals.node
      }
    },
    rules: {
      'no-console': 'off'
    }
  },

  // Prettier — keep last.
  eslintPluginPrettierRecommended
];
