<script setup lang="ts">
import AgentLayout from '@/layouts/AgentLayout.vue';
import Files from './Files.vue';

interface Props {
    agent: Domain.Agents.Data.Resources.AgentResourceData;
}

defineProps<Props>();
</script>

<template>
    <AgentLayout :agent="agent" title="Files">
        <Files :agent="agent" />
    </AgentLayout>
</template>
