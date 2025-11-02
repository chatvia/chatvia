<script setup lang="ts">
import Sources from './Sources.vue';

interface Props {
    agent: any;
}

 defineProps<Props>();

// This component will be rendered when accessing /knowledge/text
// It will use the Sources component but with currentType set to 'text'
</script>

<template>
    <!-- Use the main Sources component which will detect the route and show TextCleaner content -->
    <Sources :agent="agent" />
</template>
