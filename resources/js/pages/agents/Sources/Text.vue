<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AgentLayout from '@/layouts/AgentLayout.vue';
import Sources from './Sources.vue';

interface Props {
    agent: any;
}

const props = defineProps<Props>();

// This component will be rendered when accessing /knowledge/text
// It will use the Sources component but with currentType set to 'text'
</script>

<template>
    <!-- Use the main Sources component which will detect the route and show Text content -->
    <Sources :agent="agent" />
</template>
