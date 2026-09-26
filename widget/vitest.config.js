import { defineConfig } from 'vitest/config';

// jsdom is required because the markdown sanitizer (DOMPurify) and i18n run
// against a DOM. The pure logic under test needs no bundler-replaced globals
// (utils.js guards __DEV__ with `typeof`), so no `define` block is needed.
export default defineConfig({
  test: {
    environment: 'jsdom',
    include: ['test/**/*.test.js']
  }
});
