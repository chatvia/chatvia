<script setup lang="ts">
import AgentLayout from '@/layouts/AgentLayout.vue';
import QaComponent from './QaComponent.vue';

interface Props {
    agent: any;
}

defineProps<Props>();
</script>

<template>
    <AgentLayout :agent="agent" title="Q&A">
        <QaComponent :agent="agent" />
    </AgentLayout>
</template>

