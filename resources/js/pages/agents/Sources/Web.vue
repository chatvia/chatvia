<script setup lang="ts">
import AgentLayout from '@/layouts/AgentLayout.vue';
import WebComponent from './WebComponent.vue';

interface Props {
    agent: App.Data.Resources.Agent.AgentResourceData;
}

const props = defineProps<Props>();
</script>

<template>
    <AgentLayout :agent="agent" title="Website">
        <WebComponent :agent="agent" />
    </AgentLayout>
</template>
