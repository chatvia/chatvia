<script setup lang="ts">
// One numbered line in a channel's setup instructions (an `<li>` of the steps list).
defineProps<{ step: number }>();
</script>

<template>
    <li class="flex items-center gap-2.5">
        <span class="bg-muted text-foreground/80 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold">{{
            step
        }}</span>
        <span><slot /></span>
    </li>
</template>
