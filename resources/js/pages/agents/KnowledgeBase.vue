<script setup lang="ts">
import { ref } from 'vue';
import AgentLayout from '@/layouts/AgentLayout.vue';
import KnowledgeFiles from './KnowledgeBase/KnowledgeFiles.vue';

interface Props {
    agent: any
}

const props = defineProps<Props>();




</script>

<template>
    <AgentLayout :agent="agent" title="Knowledge Base - Files">
        <KnowledgeFiles :agent="agent" />
    </AgentLayout>
</template>
