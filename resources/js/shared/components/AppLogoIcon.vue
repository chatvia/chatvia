<script setup lang="ts">
/**
 * The ChatVia.ai mark: a "C" drawn as a speech bubble, with a dot for the reply.
 * Inline SVG in `currentColor`, so it follows the surrounding text color and
 * the app's light/dark theme. Size it with classes (`size-8`); pass
 * `aria-hidden="true"` where text next to it already names the product.
 * public/images/logo.svg and the favicons are drawn from the same paths.
 */
</script>

<template>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="currentColor" role="img" aria-label="ChatVia.ai">
        <path d="M44.6 18.4A18 18 0 1 0 44.6 41.6" fill="none" stroke="currentColor" stroke-width="10" stroke-linecap="round" />
        <path d="M23.5 45.5 21.8 52.2C17.6 55.2 12.2 57 6.4 57.6 5.2 57.7 4.6 56.4 5.4 55.6 8.8 51.8 10.6 47 11 42L16.5 38.5Z" />
        <circle cx="53" cy="30" r="6" />
    </svg>
</template>
