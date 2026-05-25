<script setup lang="ts">
import Sources from './Sources.vue';

interface Props {
    agent: Domain.Agents.Data.Resources.AgentResourceData;
}

const _props = defineProps<Props>();

// This component will be rendered when accessing /knowledge/web
// It will use the Sources component but with currentType set to 'web'
</script>

<template>
    <!-- Use the main Sources component which will detect the route and show Web content -->
    <Sources :agent="agent" />
</template>
