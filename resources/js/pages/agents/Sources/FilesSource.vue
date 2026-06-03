<script setup lang="ts">
import AgentLayout from '@/layouts/AgentLayout.vue';
import Files from './Files.vue';

interface Props {
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
}

defineProps<Props>();
</script>

<template>
    <AgentLayout :agent="agent" title="Files">
        <Files :agent="agent" />
    </AgentLayout>
</template>
