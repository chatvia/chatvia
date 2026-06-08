<script setup lang="ts">
import { getHeroicon } from '@/lib/heroicons';
import { computed } from 'vue';

// Renders a Heroicon (outline) by its export name. Unknown/empty names render
// nothing. `class` / other attrs fall through to the underlying <svg>.
const props = defineProps<{ name?: string | null }>();

const icon = computed(() => getHeroicon(props.name));
</script>

<template>
    <component :is="icon" v-if="icon" />
</template>
