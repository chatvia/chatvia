<script setup lang="ts"></script>

<template>
    <!-- Decorative: the streaming status is announced via the sr-only live
         region in Chat.vue, so the dots are hidden from assistive tech. -->
    <div class="flex items-center gap-1" aria-hidden="true">
        <span class="bg-foreground/30 inline-block h-1.5 w-1.5 animate-pulse rounded-full motion-reduce:animate-none" style="animation-delay: 0ms"></span>
        <span class="bg-foreground/30 inline-block h-1.5 w-1.5 animate-pulse rounded-full motion-reduce:animate-none" style="animation-delay: 150ms"></span>
        <span class="bg-foreground/30 inline-block h-1.5 w-1.5 animate-pulse rounded-full motion-reduce:animate-none" style="animation-delay: 300ms"></span>
    </div>
</template>
