<script setup lang="ts">
import AgentLayout from '@/layouts/AgentLayout.vue';
import WebComponent from './WebComponent.vue';

interface Props {
    agent: Domain.Agents.Data.Resources.AgentResourceData;
}

const _props = defineProps<Props>();
</script>

<template>
    <AgentLayout :agent="agent" title="Website">
        <WebComponent :agent="agent" />
    </AgentLayout>
</template>
