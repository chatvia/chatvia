<script setup lang="ts">
import AgentLayout from '@/layouts/AgentLayout.vue';
import WebComponent from './WebComponent.vue';

interface Props {
    agent: any;
}

const props = defineProps<Props>();
</script>

<template>
    <AgentLayout :agent="agent" title="Website">
        <WebComponent :agent="agent" />
    </AgentLayout>
</template>
