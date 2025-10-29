<script setup lang="ts">
import Sources from './Sources.vue';

interface Props {
    agent: any;
}

defineProps<Props>();
</script>

<template>
    <Sources :agent="agent" />
</template>

