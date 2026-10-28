<script setup lang="ts">
/** The small title + description line that opens a Setup section or form. */
defineProps<{
    title: string;
    description: string;
}>();
</script>

<template>
    <div>
        <h3 class="text-sm font-medium">{{ title }}</h3>
        <p class="text-muted-foreground mt-1 text-xs">{{ description }}</p>
    </div>
</template>
