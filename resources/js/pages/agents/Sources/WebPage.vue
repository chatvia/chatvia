<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AgentLayout from '@/layouts/AgentLayout.vue';
import Sources from './Sources.vue';

interface Props {
    agent: App.Data.Resources.Agent.AgentResourceData;
}

const props = defineProps<Props>();

// This component will be rendered when accessing /knowledge/web
// It will use the Sources component but with currentType set to 'web'
</script>

<template>
    <!-- Use the main Sources component which will detect the route and show Web content -->
    <Sources :agent="agent" />
</template>
