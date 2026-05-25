<script setup lang="ts">
import AgentLayout from '@/layouts/AgentLayout.vue';
import YoutubeComponent from './YoutubeComponent.vue';

interface Props {
    agent: Domain.Agents.Data.Resources.AgentResourceData;
}

const _props = defineProps<Props>();
</script>

<template>
    <AgentLayout :agent="agent" title="YouTube">
        <YoutubeComponent :agent="agent" />
    </AgentLayout>
</template>
