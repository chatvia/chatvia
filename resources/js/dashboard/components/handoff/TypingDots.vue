<script lang="ts" setup>
/** Three bouncing dots: someone in a live chat is typing. */
</script>

<template>
    <span class="flex gap-0.5" aria-hidden="true">
        <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-current" style="animation-delay: 0ms"></span>
        <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-current" style="animation-delay: 150ms"></span>
        <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-current" style="animation-delay: 300ms"></span>
    </span>
</template>
