<script setup lang="ts">
import AgentLayout from '@/layouts/AgentLayout.vue';
import TextComponent from './TextComponent.vue';

interface Props {
    agent: App.Data.Resources.Agent.AgentResourceData;
}

defineProps<Props>();
</script>

<template>
    <AgentLayout :agent="agent" title="Text">
        <TextComponent :agent="agent" />
    </AgentLayout>
</template>
