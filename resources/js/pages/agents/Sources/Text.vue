<script setup lang="ts">
import AgentLayout from '@/layouts/AgentLayout.vue';
import TextComponent from './TextComponent.vue';

interface Props {
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
}

defineProps<Props>();
</script>

<template>
    <AgentLayout :agent="agent" title="Text">
        <TextComponent :agent="agent" />
    </AgentLayout>
</template>
